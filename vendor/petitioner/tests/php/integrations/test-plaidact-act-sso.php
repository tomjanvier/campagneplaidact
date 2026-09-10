<?php

use Plaidact\CampaignCore\Act_SSO;
use Plaidact\CampaignCore\Shortcodes;
use WorDBless\BaseTestCase;

if (!defined('PLAIDACT_CORE_VERSION')) {
    define('PLAIDACT_CORE_VERSION', 'test');
}
if (!defined('PLAIDACT_CORE_PATH')) {
    define('PLAIDACT_CORE_PATH', dirname(__DIR__, 5) . '/');
}
if (!defined('PLAIDACT_CORE_URL')) {
    define('PLAIDACT_CORE_URL', 'https://example.org/wp-content/plugins/plaidact-campaign-core/');
}

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-polylang.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-shortcodes.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-act-sso.php';

/**
 * Couvre la connexion Act : réglages, PKCE, rôles et validation d'identité.
 */
final class Test_Plaidact_Act_SSO extends BaseTestCase
{
    public function set_up()
    {
        parent::set_up();

        delete_option('plaidact_sso_settings');
        delete_option('plaidact_campaign_settings');
        delete_transient('plaidact_sso_discovery');
        Shortcodes::reset_settings_cache();

        Act_SSO::init();
        Shortcodes::boot();
    }

    public function tear_down()
    {
        delete_option('plaidact_sso_settings');
        delete_option('plaidact_campaign_settings');
        delete_transient('plaidact_sso_discovery');
        Shortcodes::reset_settings_cache();

        remove_shortcode('plaidact_act_login');

        parent::tear_down();
    }

    public function test_default_issuer_points_to_act(): void
    {
        $defaults = Act_SSO::get_default_sso_settings();

        $this->assertSame('https://act.plaidact.org', $defaults['issuer']);
        $this->assertSame('', $defaults['client_id']);
        $this->assertSame('', $defaults['client_secret']);
    }

    public function test_sanitize_enforces_https_and_preserves_secret(): void
    {
        update_option('plaidact_sso_settings', [
            'issuer' => 'https://act.plaidact.org',
            'client_id' => 'wp-client',
            'client_secret' => 'secret-conserve',
        ]);

        // URL non HTTPS rejetée : la valeur enregistrée est conservée.
        $clean = Act_SSO::sanitize_sso_settings([
            'issuer' => 'http://act.plaidact.org',
            'client_id' => 'wp-client',
            'client_secret' => '',
        ]);

        $this->assertSame('https://act.plaidact.org', $clean['issuer']);
        $this->assertSame('secret-conserve', $clean['client_secret']);
    }

    public function test_pkce_challenge_matches_rfc7636_vector(): void
    {
        // Vecteur RFC 7636, appendice B (vérifié avec deux implémentations).
        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            Act_SSO::pkce_challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk')
        );
    }

    public function test_authorize_url_carries_pkce_and_state(): void
    {
        $url = Act_SSO::build_authorize_url(
            ['authorization_endpoint' => 'https://act.plaidact.org/authorize'],
            'wp-client',
            'https://wp.example.org/wp-admin/admin-post.php?action=plaidact_act_sso_callback',
            'etat-aleatoire',
            'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'
        );

        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $url);
        $this->assertStringContainsString('state=etat-aleatoire', $url);
    }

    public function test_role_mapping_uses_act_roles_with_subscriber_fallback(): void
    {
        $this->assertSame('administrator', Act_SSO::map_act_roles_to_wp_role(['admin']));
        $this->assertSame('editor', Act_SSO::map_act_roles_to_wp_role(['editeur']));
        $this->assertSame('subscriber', Act_SSO::map_act_roles_to_wp_role(['inconnu']));
        $this->assertSame('subscriber', Act_SSO::map_act_roles_to_wp_role([]));
    }

    public function test_role_map_text_ignores_unknown_wp_roles(): void
    {
        $map = Act_SSO::parse_role_map_text("admin=administrator\nbidon=role_inexistant\n# commentaire\n");

        $this->assertSame('administrator', $map['admin'] ?? null);
        $this->assertArrayNotHasKey('bidon', $map);
    }

    public function test_sso_shortcode_disabled_by_default(): void
    {
        $this->assertTrue(shortcode_exists('plaidact_act_login'));
        // Toggle désactivé par défaut : aucun rendu public sans activation.
        $this->assertSame('', Act_SSO::render_act_login([]));
        $this->assertFalse(Act_SSO::is_sso_enabled());
    }

    public function test_validate_userinfo_rejects_incomplete_identities(): void
    {
        $this->assertInstanceOf(\WP_Error::class, Act_SSO::validate_userinfo('invalide'));
        $this->assertInstanceOf(\WP_Error::class, Act_SSO::validate_userinfo(['sub' => '', 'email' => 'a@example.org']));
        $this->assertInstanceOf(\WP_Error::class, Act_SSO::validate_userinfo(['sub' => '123', 'email' => 'pas-un-email']));
        $this->assertInstanceOf(\WP_Error::class, Act_SSO::validate_userinfo([
            'sub' => '123',
            'email' => 'a@example.org',
            'email_verified' => false,
        ]));
    }

    public function test_validate_userinfo_accepts_verified_identity(): void
    {
        $identity = Act_SSO::validate_userinfo([
            'sub' => 'act-123',
            'email' => 'camille@example.org',
            'email_verified' => true,
            'name' => 'Camille Dupont',
            'roles' => ['Editeur'],
        ]);

        $this->assertNotInstanceOf(\WP_Error::class, $identity);
        $this->assertSame('act-123', $identity['sub']);
        $this->assertSame('camille@example.org', $identity['email']);
        $this->assertSame(['editeur'], $identity['roles']);
    }

    public function test_redirect_uri_uses_callback_action(): void
    {
        $this->assertStringContainsString(
            'admin-post.php?action=plaidact_act_sso_callback',
            Act_SSO::get_redirect_uri()
        );
    }

    public function test_insecure_localhost_issuer_rejected_by_default(): void
    {
        $clean = Act_SSO::sanitize_sso_settings([
            'issuer' => 'http://localhost:8081',
            'client_id' => 'test-client',
        ]);

        $this->assertSame('https://act.plaidact.org', $clean['issuer']);
    }

    public function test_insecure_localhost_issuer_allowed_with_dev_filter(): void
    {
        add_filter('plaidact_act_sso_allow_insecure_issuer', '__return_true');

        $clean = Act_SSO::sanitize_sso_settings([
            'issuer' => 'http://localhost:8081/',
            'client_id' => 'test-client',
        ]);

        remove_filter('plaidact_act_sso_allow_insecure_issuer', '__return_true');

        $this->assertSame('http://localhost:8081', $clean['issuer']);
    }

    public function test_allow_act_host_adds_configured_issuer_host(): void
    {
        $hosts = Act_SSO::allow_act_host(['example.org']);

        $this->assertContains('example.org', $hosts);
        $this->assertContains('act.plaidact.org', $hosts);
    }

    public function test_provisioning_links_existing_account_without_reset(): void
    {
        try {
            $user_id = wp_insert_user([
                'user_login' => 'camille-dupont',
                'user_email' => 'camille@example.org',
                'user_pass' => wp_generate_password(24, true),
                'role' => 'subscriber',
            ]);
        } catch (\Throwable $exception) {
            $this->markTestSkipped(
                'Stockage indisponible : la création de comptes demande MySQL ou SQLite.'
            );
        }

        if (!isset($user_id) || is_wp_error($user_id) || $user_id <= 0) {
            $this->markTestSkipped(
                'Stockage indisponible : la création de comptes demande MySQL ou SQLite.'
            );
        }

        $linked = Act_SSO::provision_user('act-999', 'camille@example.org', 'Camille', ['membre']);

        // Compte existant lié par email, jamais recréé ni réinitialisé.
        $this->assertSame((int) $user_id, (int) $linked);
        $this->assertSame('act-999', get_user_meta((int) $linked, '_plaidact_act_sub', true));
        $this->assertSame('camille-dupont', get_user_by('id', (int) $linked)->user_login);
    }
}
