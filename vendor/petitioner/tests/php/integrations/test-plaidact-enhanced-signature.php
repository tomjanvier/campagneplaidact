<?php

/**
 * Tests de la signature enrichie et de l'API de lecture de
 * Petitioner_Integration (champs, ordre, identité organisation).
 */

use Plaidact\CampaignCore\Petitioner_Integration;
use Plaidact\CampaignCore\Shortcodes;
use WorDBless\BaseTestCase;

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-polylang.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-petition-workflows.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-shortcodes.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-petitioner-integration.php';

final class Test_Plaidact_Enhanced_Signature extends BaseTestCase
{
    /**
     * Clés attendues des champs de signature enrichie, dans l'ordre voulu.
     */
    private const EXPECTED_FIELD_KEYS = [
        'sign_as_organization',
        'organization_name',
        'organization_logo',
        'organization_public',
        'sign_as_personality',
        'signer_title',
        'signer_function',
    ];

    public function set_up()
    {
        parent::set_up();

        delete_option('plaidact_campaign_settings');
        Shortcodes::reset_settings_cache();
        AV_Petitioner_Submissions_Model::create_db_table();
    }

    public function tear_down()
    {
        delete_option('plaidact_campaign_settings');
        Shortcodes::reset_settings_cache();

        parent::tear_down();
    }

    public function test_signature_fields_are_added_to_the_form_by_default(): void
    {
        $fields = Petitioner_Integration::add_signature_fields(['fname' => ['type' => 'text']], 12);

        foreach (self::EXPECTED_FIELD_KEYS as $key) {
            $this->assertArrayHasKey($key, $fields);
        }

        // Le champ obligatoire côté serveur est bien le nom d'organisation.
        $this->assertTrue($fields['organization_name']['required']);
        $this->assertFalse($fields['sign_as_organization']['required']);
        // Les champs existants ne sont jamais écrasés.
        $this->assertSame(['type' => 'text'], $fields['fname']);
    }

    public function test_signature_fields_can_be_disabled_from_settings(): void
    {
        update_option('plaidact_campaign_settings', [
            'petition_org_signature' => '0',
        ]);

        $original = ['fname' => ['type' => 'text']];
        $fields = Petitioner_Integration::add_signature_fields($original, 12);

        $this->assertSame($original, $fields);
    }

    public function test_signature_fields_are_inserted_right_after_email(): void
    {
        $order = Petitioner_Integration::insert_signature_fields_after_email(
            ['fname', 'lname', 'email', 'newsletter', 'submit'],
            12
        );

        $expected = array_merge(
            ['fname', 'lname', 'email'],
            self::EXPECTED_FIELD_KEYS,
            ['newsletter', 'submit']
        );

        $this->assertSame($expected, array_values($order));
        $this->assertCount(count(array_unique($order)), $order);
    }

    public function test_field_order_is_left_untouched_when_disabled(): void
    {
        update_option('plaidact_campaign_settings', [
            'petition_org_signature' => '0',
        ]);

        $order = ['fname', 'email', 'submit'];

        $this->assertSame(
            $order,
            Petitioner_Integration::insert_signature_fields_after_email($order, 12)
        );
    }

    public function test_per_petition_setting_overrides_the_global_one(): void
    {
        // Global désactivé : une pétition peut malgré tout activer la signature enrichie.
        update_option('plaidact_campaign_settings', ['petition_org_signature' => '0']);
        Shortcodes::reset_settings_cache();
        update_post_meta(77, '_plaidact_enhanced_signature', 'on');

        $fields = Petitioner_Integration::add_signature_fields(['email' => []], 77);

        $this->assertArrayHasKey('sign_as_organization', $fields);
        $this->assertArrayNotHasKey('sign_as_organization', Petitioner_Integration::add_signature_fields(['email' => []], 78));

        // Global activé : une pétition peut malgré tout désactiver la fonctionnalité.
        update_option('plaidact_campaign_settings', ['petition_org_signature' => '1']);
        Shortcodes::reset_settings_cache();
        update_post_meta(79, '_plaidact_enhanced_signature', 'off');

        $untouched = ['fname' => [], 'email' => [], 'submit' => []];

        $this->assertSame($untouched, Petitioner_Integration::add_signature_fields($untouched, 79));
        $this->assertTrue(Petitioner_Integration::is_enhanced_signature_enabled(80));
    }

    public function test_form_fields_filter_survives_legacy_null_input(): void
    {
        // Vieille pétition au format illisible : le filtre ne doit jamais
        // provoquer d'erreur fatale et doit retomber sur un tableau vide.
        $result = Petitioner_Integration::add_signature_fields(null, 12);

        $this->assertSame([], array_diff(self::EXPECTED_FIELD_KEYS, array_keys($result)));

        $order = Petitioner_Integration::insert_signature_fields_after_email(null, 12);

        $this->assertSame(self::EXPECTED_FIELD_KEYS, $order);
    }

    public function test_existing_admin_field_is_never_overridden(): void
    {
        $custom = [
            'organization_name' => [
                'type' => 'text',
                'label' => 'Libellé personnalisé par l’administrateur',
                'required' => false,
            ],
        ];

        $fields = Petitioner_Integration::add_signature_fields($custom, 12);

        $this->assertSame($custom['organization_name'], $fields['organization_name']);
    }

    public function test_organization_detection_supports_old_and_new_signatures(): void
    {
        // Ancienne signature sans propriétés personnalisées : aucune organisation.
        $legacy = new stdClass();
        $legacy->fname = 'Camille';
        $legacy->custom_properties = '';

        $this->assertNull(Petitioner_Integration::get_submission_organization($legacy));

        // Colonne totalement absente (très anciennes lignes).
        $bare = new stdClass();
        $bare->fname = 'Camille';

        $this->assertNull(Petitioner_Integration::get_submission_organization($bare));

        // Signature d'organisation complète, case cochée « on ».
        $org = new stdClass();
        $org->fname = 'ACAT France';
        $org->custom_properties = wp_json_encode([
            'sign_as_organization' => 'on',
            'organization_name' => 'ACAT France',
            'organization_logo' => 'https://example.org/logo.png',
            'organization_public' => 'on',
            'signer_title' => '',
            'signer_function' => '',
        ]);

        $detected = Petitioner_Integration::get_submission_organization($org);

        $this->assertNotNull($detected);
        $this->assertSame('ACAT France', $detected['name']);
        $this->assertTrue($detected['is_public']);

        // Variante de case à cocher (« 1 ») et consentement refusé.
        $hidden = new stdClass();
        $hidden->fname = 'Collectif';
        $hidden->custom_properties = wp_json_encode([
            'sign_as_organization' => '1',
            'organization_name' => 'Collectif invisible',
            'organization_public' => '0',
        ]);

        $detected_hidden = Petitioner_Integration::get_submission_organization($hidden);

        $this->assertNotNull($detected_hidden);
        $this->assertFalse($detected_hidden['is_public']);

        // JSON invalide : dégradation silencieuse en « pas d'organisation ».
        $broken = new stdClass();
        $broken->custom_properties = '{not-json';

        $this->assertNull(Petitioner_Integration::get_submission_organization($broken));
    }

    public function test_builder_palette_receives_each_field_only_once(): void
    {
        $builder_fields = Petitioner_Integration::expose_signature_fields_in_builder([
            'defaults' => [
                'fname' => ['fieldKey' => 'fname'],
            ],
            'draggable' => [
                ['fieldKey' => 'phone'],
            ],
        ]);

        // Les 7 champs d'origine du palette (ici « phone ») sont conservés,
        // et les 7 champs de signature enrichie s'y ajoutent sans doublon.
        $dragged_keys = array_map(
            static fn($field) => $field['fieldKey'] ?? null,
            $builder_fields['draggable']
        );

        $this->assertEqualsCanonicalizing(
            array_merge(['phone'], self::EXPECTED_FIELD_KEYS),
            $dragged_keys
        );

        // Deuxième passage : aucun doublon supplémentaire.
        $builder_fields = Petitioner_Integration::expose_signature_fields_in_builder($builder_fields);

        $this->assertCount(count($dragged_keys), $builder_fields['draggable']);
    }

    public function test_organization_name_becomes_the_stored_identity(): void
    {
        $data = Petitioner_Integration::normalize_submission_identity(
            ['fname' => 'Camille', 'lname' => 'Dupont', 'form_id' => 12],
            [
                'petitioner_sign_as_organization' => 'on',
                'petitioner_organization_name' => 'ACAT France',
            ]
        );

        $this->assertSame('ACAT France', $data['fname']);
        $this->assertSame('', $data['lname']);

        // Sans case cochée : l'identité personnelle est conservée telle quelle.
        $personal = Petitioner_Integration::normalize_submission_identity(
            ['fname' => 'Camille', 'lname' => 'Dupont'],
            ['petitioner_organization_name' => 'ACAT France']
        );

        $this->assertSame('Camille', $personal['fname']);
        $this->assertSame('Dupont', $personal['lname']);
    }

    public function test_aggregated_count_matches_confirmed_submissions_of_linked_forms(): void
    {
        $this->require_persistent_storage();

        $this->insert_submission(['form_id' => 58, 'email' => 'b@example.org', 'approval_status' => 'Confirmed']);
        // Non confirmée : ignorée par le compteur.
        $this->insert_submission(['form_id' => 58, 'email' => 'c@example.org', 'approval_status' => 'Pending']);
        // Autre pétition : hors périmètre du formulaire 58 sans Polylang.
        $this->insert_submission(['form_id' => 99, 'email' => 'd@example.org', 'approval_status' => 'Confirmed']);

        $this->assertSame(2, Petitioner_Integration::get_signature_count(58));
        $this->assertSame(1, Petitioner_Integration::get_signature_count(99));
        $this->assertSame(0, Petitioner_Integration::get_signature_count(404));
    }

    public function test_query_submissions_filters_and_paginates_across_forms(): void
    {
        $this->require_persistent_storage();

        $this->insert_submission(['form_id' => 58, 'email' => 'a@example.org', 'submitted_at' => '2026-01-03 09:00:00', 'approval_status' => 'Confirmed']);
        $this->insert_submission(['form_id' => 59, 'email' => 'b@example.org', 'submitted_at' => '2026-01-02 09:00:00', 'approval_status' => 'Confirmed']);
        $this->insert_submission(['form_id' => 59, 'email' => 'c@example.org', 'submitted_at' => '2026-01-04 09:00:00', 'approval_status' => 'Declined']);

        $result = Petitioner_Integration::query_submissions([58, 59], [
            'confirmed_only' => true,
            'per_page' => 1,
            'offset' => 0,
            'fields' => ['email', 'submitted_at'],
        ]);

        $this->assertSame(2, $result['total']);
        $this->assertCount(1, $result['submissions']);
        // Tri décroissant par date : la plus récente confirmée d'abord.
        $this->assertSame('a@example.org', $result['submissions'][0]->email);

        $page_two = Petitioner_Integration::query_submissions([58, 59], [
            'confirmed_only' => true,
            'per_page' => 1,
            'offset' => 1,
        ]);

        $this->assertSame('b@example.org', $page_two['submissions'][0]->email);
    }

    public function test_query_submissions_stays_safe_with_invalid_input(): void
    {
        $empty_ids = Petitioner_Integration::query_submissions([], []);
        $this->assertSame(0, $empty_ids['total']);
        $this->assertSame([], $empty_ids['submissions']);

        // Un champ inconnu ne doit jamais atteindre la clause SELECT :
        // la liste blanche retombe sur "*".
        $result = Petitioner_Integration::query_submissions([58], [
            'fields' => ['evil_column'],
        ]);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['submissions']);
    }

    /**
     * Insère une signature via l'API du modèle (même chemin que la
     * soumission réelle du formulaire).
     *
     * @param array<string,mixed> $overrides Champs à surcharger.
     */
    private function insert_submission(array $overrides): int
    {
        $data = array_merge([
            'form_id' => 58,
            'email' => 'signataire-' . uniqid() . '@example.org',
            'fname' => 'Camille',
            'lname' => 'Dupont',
            'approval_status' => 'Confirmed',
            'submitted_at' => '2026-01-01 10:00:00',
        ], $overrides);

        return (int) AV_Petitioner_Submissions_Model::create_submission($data);
    }

    /**
     * Interrompt le test quand l'environnement ne peut pas lire la table
     * personnalisée du moteur.
     *
     * Le moteur WorDBless « dbless » (par défaut) simule une insertion
     * réussie mais ne persiste aucune table personnalisée : il faut donc
     * vérifier une relecture effective, pas seulement le retour d'insertion.
     * Sur MySQL ou SQLite, les tests s'exécutent intégralement.
     *
     * @return int ID de la signature de contrôle insérée.
     */
    private function require_persistent_storage(): int
    {
        $submission_id = $this->insert_submission(['email' => 'canary-' . uniqid() . '@example.org']);
        $read_back = AV_Petitioner_Submissions_Model::get_submission_count(58);

        if ($submission_id <= 0 || $read_back < 1) {
            $this->markTestSkipped(
                'Stockage indisponible : le moteur WorDBless « dbless » ne prend pas en charge les tables personnalisées.'
            );
        }

        return $submission_id;
    }
}
