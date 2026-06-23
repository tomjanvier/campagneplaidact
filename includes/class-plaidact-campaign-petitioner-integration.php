<?php
/**
 * Bundled Petitioner integration for campaign core.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Synchronizes bundled Petitioner submissions with campaign features.
 */
final class Petitioner_Integration
{
    /**
     * Boots bundled Petitioner hooks when the module is available.
     *
     * @return void
     */
    public static function boot(): void
    {
        if (!class_exists("AV_Petitioner_Setup")) {
            return;
        }

        add_action(
            "petitioner_submission_finalized",
            [__CLASS__, "sync_finalized_submission"],
            10,
            2
        );
    }

    /**
     * Applies campaign integrations to a finalized Petitioner submission.
     *
     * @param object $submission Finalized submission object.
     * @param int    $form_id Form ID.
     * @return void
     */
    public static function sync_finalized_submission(
        object $submission,
        int $form_id
    ): void {
        $language = Polylang::post_language($form_id);
        $settings = Shortcodes::get_settings(true, $language);
        $full_name = self::get_submission_full_name($submission);

        Petition_Workflows::maybe_notify_admin(
            $settings,
            $full_name,
            (string) ($submission->email ?? ""),
            (string) ($submission->postal_code ?? ""),
            (string) ($submission->phone ?? ""),
            $language,
            $form_id
        );
        self::maybe_send_decision_maker_email(
            $settings,
            $submission,
            $full_name,
            $form_id,
            $language
        );
        self::maybe_subscribe_to_newsletter(
            $submission,
            $full_name,
            $language
        );
    }

    /**
     * Builds a display name from a Petitioner submission.
     *
     * @param object $submission Submission object.
     * @return string
     */
    private static function get_submission_full_name(object $submission): string
    {
        $parts = array_filter([
            isset($submission->fname)
                ? sanitize_text_field((string) $submission->fname)
                : "",
            isset($submission->lname)
                ? sanitize_text_field((string) $submission->lname)
                : "",
        ]);

        return trim(implode(" ", $parts));
    }

    /**
     * Sends the campaign decision-maker email when Petitioner is not already doing it.
     *
     * @param array  $settings Campaign settings.
     * @param object $submission Submission object.
     * @param string $full_name Full name.
     * @param int    $form_id Form ID.
     * @param string|null $language Language slug.
     * @return void
     */
    private static function maybe_send_decision_maker_email(
        array $settings,
        object $submission,
        string $full_name,
        int $form_id,
        ?string $language = null
    ): void {
        $is_petitioner_sending = (bool) get_post_meta(
            $form_id,
            "_petitioner_send_to_representative",
            true
        );
        $petitioner_target = sanitize_email(
            (string) get_post_meta($form_id, "_petitioner_email", true)
        );

        if ($is_petitioner_sending && $petitioner_target) {
            return;
        }

        Petition_Workflows::maybe_send_decision_maker_email(
            $settings,
            $full_name,
            (string) ($submission->email ?? ""),
            (string) ($submission->postal_code ?? ""),
            $language
        );
    }

    /**
     * Subscribes Petitioner signers to the petition Brevo list and newsletter flow when configured.
     *
     * @param object      $submission Submission object.
     * @param string      $full_name Full name.
     * @param string|null $language Language slug.
     * @return void
     */
    private static function maybe_subscribe_to_newsletter(
        object $submission,
        string $full_name,
        ?string $language
    ): void {
        $email = sanitize_email((string) ($submission->email ?? ""));

        if (!$email) {
            return;
        }

        $result = Shortcodes::subscribe_to_brevo_lists(
            $email,
            $full_name,
            $language,
            true,
            !empty($submission->newsletter)
        );

        if (!class_exists("AV_Petitioner_Submissions_Model")) {
            return;
        }

        $status_payload = [
            "provider" => "brevo",
            "status" => is_wp_error($result) ? "error" : (string) $result,
            "message" => is_wp_error($result)
                ? $result->get_error_message()
                : "",
            "synced_at" => current_time("mysql"),
            "language" => $language,
        ];

        \AV_Petitioner_Submissions_Model::update_submission($submission->id, [
            "email_status" => wp_json_encode($status_payload),
        ]);
    }
}
