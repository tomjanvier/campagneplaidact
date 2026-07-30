<?php

use Plaidact\CampaignCore\CPT;
use Plaidact\CampaignCore\Polylang;
use WorDBless\BaseTestCase;

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-cpt.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-polylang.php';

final class Test_Plaidact_Content_Types extends BaseTestCase
{
    public function set_up()
    {
        parent::set_up();

        delete_option('plaidact_core_content_schema_version');
        delete_option('plaidact_core_rewrite_schema_version');

        CPT::register_post_types();
        CPT::register_taxonomies();
    }

    public function tear_down()
    {
        delete_option('plaidact_core_content_schema_version');
        delete_option('plaidact_core_rewrite_schema_version');

        foreach (['breves', 'plaid_newsletter', 'plaid_breve', 'plaid_agenda_event', 'plaid_partner', 'plaid_social_embed'] as $post_type) {
            if (post_type_exists($post_type)) {
                unregister_post_type($post_type);
            }
        }

        parent::tear_down();
    }

    public function test_newsletter_is_public_and_gutenberg_ready(): void
    {
        $post_type = get_post_type_object('plaid_newsletter');

        $this->assertNotNull($post_type);
        $this->assertTrue($post_type->public);
        $this->assertTrue($post_type->show_in_rest);
        $this->assertSame('newsletters', $post_type->rest_base);
        $this->assertSame('newsletters', $post_type->has_archive);
        $this->assertSame('newsletter', $post_type->rewrite['slug']);
        $this->assertTrue(post_type_supports('plaid_newsletter', 'editor'));
        $this->assertTrue(post_type_supports('plaid_newsletter', 'thumbnail'));
        $this->assertTrue(post_type_supports('plaid_newsletter', 'revisions'));
    }

    public function test_legacy_breve_menu_is_removed(): void
    {
        register_post_type('breves', ['show_ui' => true]);

        CPT::unregister_legacy_breve_post_type();
        $this->assertTrue(post_type_exists('breves'));

        CPT::migrate_legacy_breves();

        $this->assertTrue(post_type_exists('breves'));

        CPT::unregister_legacy_breve_post_type();

        $this->assertFalse(post_type_exists('breves'));
        $this->assertTrue(post_type_exists('plaid_breve'));
    }

    public function test_legacy_breve_queries_use_the_canonical_type(): void
    {
        $single_query = new WP_Query();
        $single_query->set('post_type', 'breves');

        CPT::map_legacy_breve_query($single_query);

        $this->assertSame('plaid_breve', $single_query->get('post_type'));

        $query = new WP_Query();
        $query->set('post_type', ['post', 'breves', 'plaid_breve']);

        CPT::map_legacy_breve_query($query);

        $this->assertSame(['post', 'plaid_breve'], $query->get('post_type'));
    }

    public function test_editorial_content_is_registered_with_polylang(): void
    {
        $post_types = Polylang::register_translatable_post_types([], false);

        $this->assertArrayHasKey('petitioner-petition', $post_types);
        $this->assertArrayHasKey('plaid_breve', $post_types);
        $this->assertArrayHasKey('plaid_newsletter', $post_types);
    }
}
