<?php

class Test_AV_Petitioner_Akismet extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        delete_option('petitioner_enable_akismet');
        delete_option('wordpress_api_key');
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    public function test_remote_failure_does_not_block_submission(): void
    {
        update_option('petitioner_enable_akismet', '1');
        update_option('wordpress_api_key', 'test-key');

        add_filter('pre_http_request', static function () {
            return new WP_Error('timeout', 'Akismet timed out');
        });

        $this->assertFalse(
            AV_Petitioner_Akismet::check_with_akismet(
                'person@example.com',
                'Test',
                'Person',
                'France',
                58
            )
        );
    }

    public function test_positive_akismet_response_is_spam(): void
    {
        update_option('petitioner_enable_akismet', '1');
        update_option('wordpress_api_key', 'test-key');

        add_filter('pre_http_request', static function () {
            return [
                'headers' => [],
                'body' => 'true',
                'response' => ['code' => 200, 'message' => 'OK'],
                'cookies' => [],
                'filename' => null,
            ];
        });

        $this->assertTrue(
            AV_Petitioner_Akismet::check_with_akismet(
                'spam@example.com',
                'Spam',
                'Test',
                'France',
                58
            )
        );
    }
}
