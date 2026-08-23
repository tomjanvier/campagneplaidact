<?php

use Plaidact\CampaignCore\Association_Directory;
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

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-association-directory.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-contact-directory.php';

final class Test_Plaidact_Directories extends BaseTestCase
{
    public function set_up()
    {
        parent::set_up();
        Association_Directory::register_asso_cpt_and_taxonomies();
        Association_Directory::init();
        PlaidAct_Contact_Directory::init();
    }

    public function tear_down()
    {
        delete_option('plaidact_contact_directory_lists');
        delete_option('plaidact_contact_directory_visible_columns');
        delete_option('plaidact_contact_directory_export_branding');

        if (post_type_exists('associations')) {
            unregister_post_type('associations');
        }
        if (taxonomy_exists('associations')) {
            unregister_taxonomy('associations');
        }

        parent::tear_down();
    }

    public function test_historical_association_type_is_public_and_queryable(): void
    {
        $post_type = get_post_type_object('associations');

        $this->assertNotNull($post_type);
        $this->assertTrue($post_type->public);
        $this->assertTrue($post_type->show_in_rest);
        $this->assertSame('association', $post_type->has_archive);
        $this->assertSame('association', $post_type->rewrite['slug']);
        $this->assertTrue(taxonomy_exists('associations'));
    }

    public function test_directory_shortcodes_are_restored_with_legacy_alias(): void
    {
        $this->assertTrue(shortcode_exists('plaidact_asso_directory'));
        $this->assertTrue(shortcode_exists('plaidact_contact_directory'));
        $this->assertTrue(shortcode_exists('plaidact_fluentcrm_directory'));
    }
}
