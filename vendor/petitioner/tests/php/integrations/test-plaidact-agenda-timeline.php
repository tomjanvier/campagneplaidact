<?php

use Plaidact\CampaignCore\Association_Directory;
use Plaidact\CampaignCore\CPT;
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

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-cpt.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-polylang.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-shortcodes.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-association-directory.php';

/**
 * Couvre les branchements agenda/timeline : shortcodes, toggles, import additif.
 */
final class Test_Plaidact_Agenda_Timeline extends BaseTestCase
{
    public function set_up()
    {
        parent::set_up();

        delete_option('plaidact_campaign_settings');
        Shortcodes::reset_settings_cache();

        CPT::register_post_types();
        CPT::register_taxonomies();
        Association_Directory::register_agenda_post_type();
        Association_Directory::register_taxonomy();
        Association_Directory::init();
        Shortcodes::boot();
    }

    public function tear_down()
    {
        delete_option('plaidact_campaign_settings');
        Shortcodes::reset_settings_cache();

        foreach (['agenda', 'plaid_agenda_event'] as $post_type) {
            if (post_type_exists($post_type)) {
                unregister_post_type($post_type);
            }
        }
        if (taxonomy_exists('agenda_timeline')) {
            unregister_taxonomy('agenda_timeline');
        }

        foreach (['plaidact_timeline', 'plaidact_hover_term', 'plaidact_asso_directory'] as $shortcode) {
            remove_shortcode($shortcode);
        }

        parent::tear_down();
    }

    public function test_timeline_and_hover_shortcodes_are_registered(): void
    {
        $this->assertTrue(shortcode_exists('plaidact_timeline'));
        $this->assertTrue(shortcode_exists('plaidact_hover_term'));
        $this->assertTrue(shortcode_exists('plaidact_asso_directory'));
    }

    public function test_agenda_post_type_and_taxonomy_cover_both_types(): void
    {
        $this->assertTrue(post_type_exists('agenda'));
        // Type historique conservé : aucune suppression de contenu existant.
        $this->assertTrue(post_type_exists('plaid_agenda_event'));
        $this->assertTrue(taxonomy_exists('agenda_timeline'));

        $taxonomy = get_taxonomy('agenda_timeline');
        $this->assertNotFalse($taxonomy);
        $this->assertContains('agenda', (array) $taxonomy->object_type);
        $this->assertContains('plaid_agenda_event', (array) $taxonomy->object_type);
    }

    public function test_timeline_shortcode_respects_agenda_toggle(): void
    {
        update_option('plaidact_campaign_settings', ['enable_agenda' => '0']);
        Shortcodes::reset_settings_cache();

        $this->assertSame('', Association_Directory::timeline_shortcode(['term' => 'geopolitique']));
        $this->assertSame('', Association_Directory::render_timeline_block(['term' => 'geopolitique']));
    }

    public function test_asso_and_contact_directories_respect_directory_toggle(): void
    {
        update_option('plaidact_campaign_settings', ['enable_directory' => '0']);
        Shortcodes::reset_settings_cache();

        $this->assertSame('', Association_Directory::asso_directory_shortcode([]));
        $this->assertSame('', Association_Directory::render_asso_block([]));
    }

    public function test_build_timeline_data_returns_grouped_structure(): void
    {
        $payload = Association_Directory::build_timeline_data('timeline-inexistante-xyz', false);

        $this->assertArrayHasKey('years', $payload);
        $this->assertArrayHasKey('term', $payload);
        $this->assertSame([], $payload['years']);
        $this->assertNull($payload['term']);
    }

    public function test_parse_acf_date_supports_known_formats(): void
    {
        $this->assertSame('2026-06-02', Association_Directory::parse_acf_date('20260602')->format('Y-m-d'));
        $this->assertSame('2026-06-02', Association_Directory::parse_acf_date('02/06/2026')->format('Y-m-d'));
        $this->assertSame('2026-06-02', Association_Directory::parse_acf_date('2026-06-02')->format('Y-m-d'));
        $this->assertNull(Association_Directory::parse_acf_date(''));
        $this->assertNull(Association_Directory::parse_acf_date('date-invalide'));
    }

    public function test_month_helpers_format_french_labels(): void
    {
        $this->assertSame('Janvier', Association_Directory::month_name(1));
        $this->assertSame('Décembre', Association_Directory::month_name(12));
        $this->assertSame('', Association_Directory::month_name(13));

        $date = new DateTimeImmutable('2026-06-02');
        $this->assertSame('2 juin 2026', Association_Directory::format_date_short($date));
    }

    public function test_agenda_import_never_resets_existing_event(): void
    {
        try {
            $post_id = wp_insert_post([
                'post_type' => 'agenda',
                'post_status' => 'publish',
                'post_title' => 'Réunion existante',
                'post_content' => 'Contenu à conserver',
                'post_name' => 'reunion-existante',
            ]);
        } catch (\Throwable $exception) {
            $this->markTestSkipped(
                'Stockage indisponible : la création de contenus demande MySQL ou SQLite.'
            );
        }
        if (!isset($post_id) || $post_id <= 0 || null === get_post($post_id)) {
            $this->markTestSkipped(
                'Stockage indisponible : la création de contenus demande MySQL ou SQLite.'
            );
        }

        update_post_meta($post_id, 'date_debut', '20260602');
        update_post_meta($post_id, 'lieu', 'Ottawa');

        $upsert = new ReflectionMethod(Association_Directory::class, 'upsert_agenda_post');
        $upsert->setAccessible(true);
        $kept_id = $upsert->invoke(null, ['slug' => 'reunion-existante', 'title' => 'Nouveau titre']);

        // La fiche existante est réutilisée sans réinitialisation du titre ni du contenu.
        $this->assertSame($post_id, $kept_id);
        $this->assertSame('Réunion existante', get_the_title($post_id));
        $this->assertSame('Contenu à conserver', get_post_field('post_content', $post_id));
        $this->assertSame('publish', get_post_status($post_id));

        $sync_meta = new ReflectionMethod(Association_Directory::class, 'sync_agenda_meta');
        $sync_meta->setAccessible(true);
        // Un champ vide du CSV ne vide jamais la valeur existante ; un champ
        // vide en base est complété sans toucher aux autres.
        $sync_meta->invoke(null, $post_id, [
            'date_debut' => '',
            'date_fin' => '20260603',
            'type_evenement' => '',
            'lieu' => 'Nouveau lieu',
            'nom_organisation' => 'PLAID·ACT',
            'lien_evenement' => '',
        ]);

        $this->assertSame('20260602', get_post_meta($post_id, 'date_debut', true));
        $this->assertSame('20260603', get_post_meta($post_id, 'date_fin', true));
        $this->assertSame('Ottawa', get_post_meta($post_id, 'lieu', true));
        $this->assertSame('PLAID·ACT', get_post_meta($post_id, 'nom_organisation', true));
    }

    public function test_agenda_timeline_terms_are_appended(): void
    {
        try {
            $post_id = wp_insert_post([
                'post_type' => 'agenda',
                'post_status' => 'publish',
                'post_title' => 'Événement classé',
            ]);
        } catch (\Throwable $exception) {
            $this->markTestSkipped(
                'Stockage indisponible : la création de contenus demande MySQL ou SQLite.'
            );
        }
        if (!isset($post_id) || $post_id <= 0 || null === get_post($post_id)) {
            $this->markTestSkipped(
                'Stockage indisponible : la création de contenus demande MySQL ou SQLite.'
            );
        }

        wp_insert_term('Première timeline', 'agenda_timeline');
        wp_set_object_terms($post_id, ['premiere-timeline'], 'agenda_timeline', false);

        $sync_term = new ReflectionMethod(Association_Directory::class, 'sync_agenda_timeline_term');
        $sync_term->setAccessible(true);
        $sync_term->invoke(null, $post_id, 'Deuxième timeline');

        $slugs = wp_get_post_terms($post_id, 'agenda_timeline', ['fields' => 'slugs']);
        $this->assertContains('premiere-timeline', $slugs);
        $this->assertContains('deuxieme-timeline', $slugs);
    }

    public function test_timeline_template_and_assets_exist(): void
    {
        $this->assertFileExists(PLAIDACT_CORE_PATH . 'templates/timeline.php');
        $this->assertFileExists(PLAIDACT_CORE_PATH . 'assets/js/plaidact-blocks.js');

        $css = (string) file_get_contents(PLAIDACT_CORE_PATH . 'assets/campaign-shortcodes.css');
        $this->assertStringContainsString('.pa-timeline', $css);
    }

    public function test_incomplete_modules_have_no_public_render(): void
    {
        // Out/sorties, articles et rapport PDF restent configurables mais sans
        // rendu public : documenté dans ARCHITECTURE.md, sans nouveau shortcode.
        $this->assertFalse(shortcode_exists('plaid_articles'));
        $this->assertFalse(shortcode_exists('plaid_out'));
        $this->assertFalse(shortcode_exists('plaid_report_highlight'));

        $defaults = Shortcodes::get_default_settings();
        $this->assertArrayHasKey('enable_out', $defaults);
        $this->assertArrayHasKey('enable_articles', $defaults);
        $this->assertArrayHasKey('enable_report_highlight', $defaults);
    }
}
