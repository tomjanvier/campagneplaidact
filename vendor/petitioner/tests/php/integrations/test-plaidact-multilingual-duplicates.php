<?php

/**
 * Vérifie que la détection de doublons traverse les traductions d'une même
 * pétition sans provoquer de récursion infinie entre les filtres.
 */

use Plaidact\CampaignCore\Petitioner_Integration;
use WorDBless\BaseTestCase;

require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-polylang.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-petition-workflows.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-shortcodes.php';
require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-petitioner-integration.php';

final class Test_Plaidact_Multilingual_Duplicates extends BaseTestCase
{
    public function set_up()
    {
        parent::set_up();

        AV_Petitioner_Submissions_Model::create_db_table();

        add_filter(
            'av_petitioner_check_duplicate_email',
            [Petitioner_Integration::class, 'check_duplicate_email_across_translations'],
            10,
            3
        );
    }

    public function tear_down()
    {
        remove_filter(
            'av_petitioner_check_duplicate_email',
            [Petitioner_Integration::class, 'check_duplicate_email_across_translations'],
            10
        );

        parent::tear_down();
    }

    public function test_checking_translated_forms_does_not_recurse(): void
    {
        $this->assertFalse(
            AV_Petitioner_Submissions_Model::check_duplicate_email(
                'new-signature@example.org',
                58
            )
        );
    }

    public function test_duplicate_is_detected_on_the_source_form_itself(): void
    {
        AV_Petitioner_Submissions_Model::create_submission([
            'form_id' => 58,
            'email' => 'already-signed@example.org',
            'fname' => 'Camille',
            'approval_status' => 'Confirmed',
        ]);

        // Le moteur WorDBless « dbless » simule l'insertion sans persister :
        // on vérifie une relecture effective avant d'affirmer le doublon.
        if (AV_Petitioner_Submissions_Model::get_submission_count(58) < 1) {
            $this->markTestSkipped(
                'Stockage indisponible : le moteur WorDBless « dbless » ne prend pas en charge les tables personnalisées.'
            );
        }

        $this->assertTrue(
            AV_Petitioner_Submissions_Model::check_duplicate_email(
                'already-signed@example.org',
                58
            )
        );
    }
}
