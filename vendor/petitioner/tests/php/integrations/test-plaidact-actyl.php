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
use Plaidact\CampaignCore\Actyl;
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
    private Actyl $actyl;

    public function set_up()
    {
        parent::set_up();

        delete_option('plaidact_actyl_settings');
        delete_option('plaidact_actyl_ping_ok_at');
        delete_option('plaidact_actyl_backfill_cursor');
        delete_option('plaidact_actyl_log');

        $this->actyl = Actyl::init();
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

    public function test_backfill_handler_is_registered(): void
    {
        $this->assertSame(
            10,
            has_action(
                'admin_post_plaidact_actyl_backfill_batch',
                [$this->actyl, 'handle_backfill_batch']
            )
        );
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

    public function test_http_requests_are_bounded_and_reject_unsafe_urls(): void
    {
        update_option('plaidact_actyl_settings', [
            'actyl_url' => 'https://actyl.exemple.fr',
            'actyl_api_token' => 'actyl_secret',
            'actyl_enabled' => '0',
        ]);

        $request_args = null;
        add_filter('pre_http_request', function ($preempt, $parsed_args) use (&$request_args) {
            $request_args = $parsed_args;

            return [
                'response' => ['code' => 200],
                'body' => '{"ok":true}',
            ];
        }, 10, 2);

        try {
            $this->assertTrue($this->actyl->ping());
        } finally {
            remove_all_filters('pre_http_request');
        }

        $this->assertIsArray($request_args);
        $this->assertSame(5, $request_args['timeout']);
        $this->assertSame(0, $request_args['redirection']);
        $this->assertTrue($request_args['reject_unsafe_urls']);
    }

    public function test_backfill_cursors_are_isolated_by_scope(): void
    {
        $set_cursor = new ReflectionMethod($this->actyl, 'set_backfill_cursor');
        $set_cursor->setAccessible(true);
        $get_cursor = new ReflectionMethod($this->actyl, 'get_backfill_cursor');
        $get_cursor->setAccessible(true);

        $set_cursor->invoke($this->actyl, 0, 120);
        $set_cursor->invoke($this->actyl, 42, 75);

        $this->assertSame(120, $get_cursor->invoke($this->actyl, 0));
        $this->assertSame(75, $get_cursor->invoke($this->actyl, 42));
        $this->assertSame(0, $get_cursor->invoke($this->actyl, 43));
    }

    public function test_backfill_counts_only_confirmed_submissions(): void
    {
        $build_filter = new ReflectionMethod($this->actyl, 'build_backfill_filter');
        $build_filter->setAccessible(true);

        $global_filter = $build_filter->invoke($this->actyl, []);
        $petition_filter = $build_filter->invoke($this->actyl, [42, 43]);

        $this->assertSame('approval_status = %s', $global_filter['sql']);
        $this->assertSame(['Confirmed'], $global_filter['params']);
        $this->assertSame(
            'approval_status = %s AND form_id IN (%d,%d)',
            $petition_filter['sql']
        );
        $this->assertSame(['Confirmed', 42, 43], $petition_filter['params']);
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

    /**
     * Capture le payload transmis à l'API lors d'un enregistrement de don.
     *
     * @return array{path:string, method:string, body:array}|null
     */
    private function capture_donation_request(callable $trigger): ?array
    {
        $captured = null;

        add_filter('pre_http_request', function ($preempt, $parsed_args, $url) use (&$captured) {
            $captured = [
                'url' => $url,
                'method' => $parsed_args['method'] ?? 'POST',
                'body' => json_decode((string) ($parsed_args['body'] ?? '{}'), true),
            ];

            return [
                'response' => ['code' => 201],
                'body' => '{"ok":true,"donationId":"d1","contactId":"c1"}',
            ];
        }, 10, 3);

        try {
            $trigger();
        } finally {
            remove_all_filters('pre_http_request');
        }

        return $captured;
    }

    public function test_givoly_donation_is_mapped_to_the_actyl_contract(): void
    {
        $this->activate();

        $captured = null;
        add_filter('pre_http_request', function ($preempt, $parsed_args, $url) use (&$captured) {
            $captured = [
                'url' => $url,
                'body' => json_decode((string) ($parsed_args['body'] ?? '{}'), true),
            ];

            return [
                'response' => ['code' => 201],
                'body' => '{"ok":true}',
            ];
        }, 10, 3);

        try {
            $this->actyl->handle_givoly_donation([
                'donation_id' => 7,
                'gateway' => 'stripe',
                'transaction_id' => 'pi_123',
                'email' => 'donateur@exemple.fr',
                'first_name' => 'Jean',
                'last_name' => 'Martin',
                'amount_cents' => 5000,
                'currency' => 'EUR',
                'campaign' => 'zones-humides',
                'occurred_at' => '2026-08-24T12:00:00Z',
            ]);
        } finally {
            remove_all_filters('pre_http_request');
        }

        $this->assertNotNull($captured);
        $this->assertStringEndsWith('/api/v1/donations', (string) parse_url($captured['url'], PHP_URL_PATH));

        $this->assertSame([
            'email' => 'donateur@exemple.fr',
            'provider' => 'stripe',
            'label' => 'Don zones-humides',
            'occurredAt' => '2026-08-24T12:00:00Z',
            'fullName' => 'Jean Martin',
            'amountCents' => 5000,
        ], $captured['body']);
    }

    public function test_inactive_connection_never_sends_givoly_donations(): void
    {
        // Connexion non activée : aucune requête ne doit partir.
        add_filter('pre_http_request', static function () {
            throw new RuntimeException('Aucune requête sortante attendue.');
        });

        try {
            $sent = $this->actyl->handle_givoly_donation([
                'email' => 'donateur@exemple.fr',
                'amount_cents' => 5000,
            ]);

            $this->assertFalse($sent);
        } finally {
            remove_all_filters('pre_http_request');
        }
    }

    public function test_incomplete_donation_payload_is_rejected_without_request(): void
    {
        $this->activate();

        add_filter('pre_http_request', static function () {
            throw new RuntimeException('Aucune requête sortante attendue.');
        });

        try {
            // Email manquant.
            $this->assertFalse($this->actyl->handle_givoly_donation(['amount_cents' => 5000]));
            // Montant manquant.
            $this->assertFalse(
                $this->actyl->handle_givoly_donation(['email' => 'donateur@exemple.fr'])
            );
        } finally {
            remove_all_filters('pre_http_request');
        }
    }
}
