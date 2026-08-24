<?php

/**
 * Tests de la couche de configuration et des règles de sécurité du client
 * Actyl : validation d'URL/token, garde d'activation par ping réussi,
 * politique de relance, plafonnement du journal, résolution des campagnes.
 *
 * Tout repose sur les options WordPress : exécutable sous le moteur
 * WorDBless « dbless » comme avec une base réelle.
 */

use Plaidact\CampaignCore\Shortcodes;
use WorDBless\BaseTestCase;

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-polylang.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-petition-workflows.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-shortcodes.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-actyl.php';

final class Test_Plaidact_Actyl extends BaseTestCase
{
    /**
     * Instance sous test (le singleton conserve ses hooks entre les tests :
     * l'état utile vit exclusivement dans les options, remises à zéro ici).
     */
    private PLAIDACT_Actyl $actyl;

    public function set_up()
    {
        parent::set_up();

        delete_option('plaidact_actyl_settings');
        delete_option('plaidact_actyl_ping_ok_at');
        delete_option('plaidact_actyl_backfill_cursor');
        delete_option('plaidact_actyl_log');

        $this->actyl = PLAIDACT_Actyl::init();
    }

    public function tear_down()
    {
        delete_option('plaidact_actyl_settings');
        delete_option('plaidact_actyl_ping_ok_at');
        delete_option('plaidact_actyl_backfill_cursor');
        delete_option('plaidact_actyl_log');

        parent::tear_down();
    }

    /**
     * Configure une connexion complète et marque le ping comme validé.
     */
    private function activate(): void
    {
        update_option('plaidact_actyl_settings', [
            'actyl_url' => 'https://actyl.exemple.fr',
            'actyl_api_token' => 'actyl_test_token',
            'actyl_enabled' => '1',
        ]);
        update_option('plaidact_actyl_ping_ok_at', time());
    }

    public function test_settings_default_to_fully_disabled(): void
    {
        $this->assertFalse($this->actyl->is_configured());
        $this->assertFalse($this->actyl->is_active());
    }

    public function test_url_must_be_https_and_is_stored_without_trailing_slash(): void
    {
        // HTTP refusé : la valeur précédente est conservée (vide au départ).
        $clean = $this->actyl->sanitize_settings([
            'actyl_url' => 'http://actyl.exemple.fr',
        ]);

        $this->assertSame('', $clean['actyl_url']);

        // HTTPS accepté et normalisé sans slash final.
        $clean = $this->actyl->sanitize_settings([
            'actyl_url' => 'https://actyl.exemple.fr/',
        ]);

        $this->assertSame('https://actyl.exemple.fr', $clean['actyl_url']);
    }

    public function test_token_with_wrong_prefix_is_rejected(): void
    {
        $clean = $this->actyl->sanitize_settings([
            'actyl_api_token' => 'sk_autre_plateforme',
        ]);

        $this->assertSame('', $clean['actyl_api_token']);

        $clean = $this->actyl->sanitize_settings([
            'actyl_api_token' => 'actyl_abc123',
        ]);

        $this->assertSame('actyl_abc123', $clean['actyl_api_token']);
    }

    public function test_empty_token_field_preserves_the_stored_secret(): void
    {
        update_option('plaidact_actyl_settings', [
            'actyl_url' => 'https://actyl.exemple.fr',
            'actyl_api_token' => 'actyl_secret',
            'actyl_enabled' => '0',
        ]);

        // Soumission sans resaisie du secret : la valeur enregistrée survit.
        $clean = $this->actyl->sanitize_settings([
            'actyl_url' => 'https://actyl.exemple.fr',
            'actyl_api_token' => '',
            'actyl_enabled' => '1',
        ]);

        $this->assertSame('actyl_secret', $clean['actyl_api_token']);
        $this->assertSame('1', $clean['actyl_enabled']);
    }

    public function test_changing_target_invalidates_previous_validation(): void
    {
        $this->activate();

        $this->assertTrue($this->actyl->is_active());

        $this->actyl->sanitize_settings([
            'actyl_url' => 'https://autre-instance.exemple.fr',
            'actyl_api_token' => '', // token conservé
            'actyl_enabled' => '1',
        ]);

        // La validation ne vaut que pour la cible testée : elle doit être rejouée.
        $this->assertFalse($this->actyl->is_active());
    }

    public function test_activation_gates_on_a_successful_ping(): void
    {
        update_option('plaidact_actyl_settings', [
            'actyl_url' => 'https://actyl.exemple.fr',
            'actyl_api_token' => 'actyl_secret',
            'actyl_enabled' => '1',
        ]);

        // Configuré + activé mais jamais validé : aucune synchro.
        $this->assertFalse($this->actyl->is_active());

        // Après un ping réussi explicite.
        update_option('plaidact_actyl_ping_ok_at', time());
        $this->assertTrue($this->actyl->is_active());

        // Désactivation manuelle : coupe tout, même avec un ping valide.
        update_option('plaidact_actyl_settings', [
            'actyl_url' => 'https://actyl.exemple.fr',
            'actyl_api_token' => 'actyl_secret',
            'actyl_enabled' => '0',
        ]);
        $this->assertFalse($this->actyl->is_active());
    }

    public function test_retry_only_for_network_errors_and_server_failures(): void
    {
        // Échec réseau ou panne serveur : tentative unique justifiée.
        $this->assertTrue($this->actyl->should_retry(0));
        $this->assertTrue($this->actyl->should_retry(500));
        $this->assertTrue($this->actyl->should_retry(503));

        // Erreurs définitives : aucune relance.
        $this->assertFalse($this->actyl->should_retry(200));
        $this->assertFalse($this->actyl->should_retry(400));
        $this->assertFalse($this->actyl->should_retry(401));
        $this->assertFalse($this->actyl->should_retry(404));
        $this->assertFalse($this->actyl->should_retry(429));
    }

    public function test_log_keeps_only_the_newest_hundred_events(): void
    {
        $log_event = new ReflectionMethod($this->actyl, 'log_event');
        $log_event->setAccessible(true);

        for ($i = 1; $i <= 105; $i++) {
            $log_event->invoke($this->actyl, '/api/v1/supporters', 200, 'evenement-' . $i);
        }

        $get_log = new ReflectionMethod($this->actyl, 'get_log');
        $get_log->setAccessible(true);
        $log = $get_log->invoke($this->actyl);

        $this->assertCount(100, $log);
        // Insertion en tête : le plus récent d'abord.
        $this->assertSame('evenement-105', $log[0]['message']);
        $this->assertSame('evenement-6', $log[99]['message']);
    }

    public function test_campaign_slug_resolves_from_post_meta(): void
    {
        // La création de posts passe par un chemin SQL non simulé par le
        // moteur WorDBless « dbless » : la résolution est donc vérifiée via
        // les métadonnées seules, qui suffisent à couvrir la logique.
        // Identifiants distincts car la résolution est cachée par requête.

        // Sans liaison : chaîne vide, aucune donnée ne doit partir.
        $this->assertSame('', $this->actyl->resolve_campaign_slug(4200));

        update_post_meta(4201, '_plaidact_actyl_campaign_slug', 'zones-humides');

        $this->assertSame('zones-humides', $this->actyl->resolve_campaign_slug(4201));
        $this->assertSame('', $this->actyl->resolve_campaign_slug(999999));
    }
}
