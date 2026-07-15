<?php
/**
 * Bundled Petitioner integration for PLAID·ACT Core.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Synchronizes bundled Petitioner submissions with PLAID·ACT features.
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
        add_filter(
            "av_petitioner_check_duplicate_email",
            [__CLASS__, "check_duplicate_email_across_translations"],
            10,
            3
        );
        add_filter("av_petitioner_form_fields", [__CLASS__, "add_organization_signature_fields"], 10, 2);
        add_filter("av_petitioner_field_order", [__CLASS__, "add_organization_signature_field_order"], 10, 2);
        add_filter("av_petitioner_get_custom_property_types", [__CLASS__, "register_organization_signature_properties"]);
        add_filter("av_petitioner_submission_data_pre_save", [__CLASS__, "normalize_organization_signature_submission"], 5, 2);
    }


    /**
     * Adds organization signature fields to every Petitioner form.
     *
     * @param array $form_fields Existing form fields.
     * @param int   $form_id Form ID.
     * @return array
     */
    public static function add_organization_signature_fields(array $form_fields, int $form_id): array
    {
        $form_fields["sign_as_organization"] = [
            "fieldKey" => "sign_as_organization",
            "type" => "checkbox",
            "fieldName" => __("Signature d'organisation", "plaidact-campaign-core"),
            "label" => __("Je souhaite signer en tant qu'organisation", "plaidact-campaign-core"),
            "defaultValue" => false,
            "required" => false,
            "removable" => false,
        ];
        $form_fields["organization_name"] = [
            "fieldKey" => "organization_name",
            "type" => "text",
            "fieldName" => __("Nom de l'organisation", "plaidact-campaign-core"),
            "label" => __("Nom de l'organisation", "plaidact-campaign-core"),
            "placeholder" => __("Ex. Association locale", "plaidact-campaign-core"),
            "required" => true,
            "removable" => false,
        ];
        $form_fields["organization_logo"] = [
            "fieldKey" => "organization_logo",
            "type" => "url",
            "fieldName" => __("Logo de l'organisation", "plaidact-campaign-core"),
            "label" => __("Logo de l'organisation (URL)", "plaidact-campaign-core"),
            "placeholder" => __("https://exemple.org/logo.png", "plaidact-campaign-core"),
            "required" => false,
            "removable" => false,
        ];
        $form_fields["organization_public"] = [
            "fieldKey" => "organization_public",
            "type" => "checkbox",
            "fieldName" => __("Visibilité de l'organisation", "plaidact-campaign-core"),
            "label" => __("J'accepte de rendre visible le nom/logo de mon organisation sur le site", "plaidact-campaign-core"),
            "defaultValue" => false,
            "required" => false,
            "removable" => false,
        ];

        return $form_fields;
    }

    /**
     * Places organization fields consistently in every Petitioner form.
     *
     * @param array $field_order Existing field order.
     * @param int   $form_id Form ID.
     * @return array
     */
    public static function add_organization_signature_field_order(array $field_order, int $form_id): array
    {
        $organization_fields = [
            "sign_as_organization",
            "organization_name",
            "organization_logo",
            "organization_public",
        ];
        $field_order = array_values(array_diff($field_order, $organization_fields));
        $insert_after = array_search("email", $field_order, true);
        $offset = false === $insert_after ? 0 : $insert_after + 1;

        array_splice($field_order, $offset, 0, $organization_fields);

        return $field_order;
    }

    /**
     * Registers organization fields as Petitioner custom properties.
     *
     * @param array $property_types Existing custom property definitions.
     * @return array
     */
    public static function register_organization_signature_properties(array $property_types): array
    {
        return array_merge($property_types, [
            "sign_as_organization" => ["sanitize_callback" => "sanitize_text_field"],
            "organization_name" => ["sanitize_callback" => "sanitize_text_field"],
            "organization_logo" => ["sanitize_callback" => "esc_url_raw"],
            "organization_public" => ["sanitize_callback" => "sanitize_text_field"],
        ]);
    }

    /**
     * Keeps required Petitioner core name columns populated for organization signatures.
     *
     * @param array $data Submission data.
     * @param array $post_data Raw POST data.
     * @return array
     */
    public static function normalize_organization_signature_submission(array $data, array $post_data): array
    {
        $sign_as_organization = !empty($post_data["petitioner_sign_as_organization"]);
        $organization_name = isset($post_data["petitioner_organization_name"])
            ? sanitize_text_field(wp_unslash($post_data["petitioner_organization_name"]))
            : "";

        if ($sign_as_organization && "" !== $organization_name) {
            $data["fname"] = $organization_name;
            $data["lname"] = "";
        }

        return $data;
    }


    /**
     * Treats translated Petitioner forms as one petition for duplicate checks.
     *
     * @param bool   $is_duplicate Existing duplicate status.
     * @param string $email Submitted email.
     * @param int    $form_id Current form ID.
     * @return bool
     */
    public static function check_duplicate_email_across_translations(
        bool $is_duplicate,
        string $email,
        int $form_id
    ): bool {
        if ($is_duplicate || !class_exists("AV_Petitioner_Submissions_Model")) {
            return $is_duplicate;
        }

        foreach (Shortcodes::get_linked_petitioner_form_ids($form_id) as $linked_form_id) {
            if ($linked_form_id === $form_id) {
                continue;
            }

            if (\AV_Petitioner_Submissions_Model::check_duplicate_email($email, $linked_form_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies PLAID·ACT integrations to a finalized Petitioner submission.
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
     * Sends the decision-maker email when Petitioner is not already doing it.
     *
     * @param array  $settings PLAID·ACT settings.
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
