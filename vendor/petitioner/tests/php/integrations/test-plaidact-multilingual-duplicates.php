<?php

namespace Plaidact\CampaignCore {
    final class Shortcodes
    {
        public static function get_linked_petitioner_form_ids(int $form_id): array
        {
            return [58, 59, 60];
        }
    }

    require_once dirname(__DIR__, 5) . '/includes/class-plaidact-campaign-petitioner-integration.php';
}

namespace {
    use Plaidact\CampaignCore\Petitioner_Integration;
    use WorDBless\BaseTestCase;

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
    }
}
