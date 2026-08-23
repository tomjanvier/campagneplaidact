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

    public function test_boot_does_not_change_existing_contact_data(): void
    {
        $saved = [
            [
                'id' => 42,
                'name' => 'Liste existante',
                'contacts' => [['nom' => 'Durand', 'prenom' => 'Alice', 'email' => 'alice@example.org']],
            ],
        ];
        update_option('plaidact_contact_directory_lists', $saved, false);

        PlaidAct_Contact_Directory::init();

        $this->assertSame($saved, get_option('plaidact_contact_directory_lists'));
    }

    public function test_contact_import_merge_preserves_existing_rows(): void
    {
        $directory = PlaidAct_Contact_Directory::init();
        $method = new ReflectionMethod($directory, 'merge_contacts_preserving_existing');
        $method->setAccessible(true);

        $existing = [['nom' => 'Durand', 'prenom' => 'Alice', 'email' => 'alice@example.org', 'notes' => 'À garder']];
        $incoming = [
            ['nom' => 'Durand', 'prenom' => 'Alice', 'email' => 'alice@example.org', 'notes' => 'Ne remplace pas'],
            ['nom' => 'Martin', 'prenom' => 'Sam', 'email' => 'sam@example.org'],
        ];

        $merged = $method->invoke($directory, $existing, $incoming);

        $this->assertCount(2, $merged);
        $this->assertSame('À garder', $merged[0]['notes']);
        $this->assertSame('sam@example.org', $merged[1]['email']);
    }
}
