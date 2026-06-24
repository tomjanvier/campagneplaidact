<?php
/**
 * Shared Petitioner side effects for campaign signatures.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Centralizes shared campaign petition side effects.
 */
final class Petition_Workflows
{
    /**
     * Returns the best redirect URL for frontend form handlers.
     *
     * @param string|null $language Optional language slug.
     * @return string
     */
    public static function get_redirect_url(?string $language = null): string
    {
        $referer = wp_get_referer();

        if (is_string($referer) && "" !== $referer) {
            return $referer;
        }

        return Polylang::home_url($language);
    }

    /**
     * Sends the admin notification email for a petition signature when configured.
     *
     * @param array       $settings Campaign settings.
     * @param string      $name Signer display name.
     * @param string      $email Signer email.
     * @param string      $postcode Signer postal code.
     * @param string      $phone Signer phone number.
     * @param string|null $language Optional language slug.
     * @param int|null    $form_id Optional bundled Petitioner form ID.
     * @return void
     */
    public static function maybe_notify_admin(
        array $settings,
        string $name,
        string $email,
        string $postcode = "",
        string $phone = "",
        ?string $language = null,
        ?int $form_id = null
    ): void {
        $notification_email = sanitize_email(
            (string) ($settings["notification_email"] ?? "")
        );

        if (!$notification_email) {
            return;
        }

        $message = sprintf(
            "Nom: %s\nEmail: %s\nCode postal: %s\nTéléphone: %s\nCampagne: %s",
            "" !== trim($name)
                ? $name
                : __("Nom indisponible", "plaidact-campaign-core"),
            sanitize_email($email),
            sanitize_text_field($postcode),
            sanitize_text_field($phone),
            self::get_campaign_url($language)
        );

        if ($form_id && $form_id > 0) {
            $message .= "\nFormulaire: " . $form_id;
        }

        wp_mail(
            $notification_email,
            __("Nouvelle signature pétition", "plaidact-campaign-core"),
            $message
        );
    }

    /**
     * Sends the decision-maker email for a petition signature when configured.
     *
     * @param array       $settings Campaign settings.
     * @param string      $name Signer display name.
     * @param string      $email Signer email.
     * @param string      $postcode Signer postal code.
     * @param string|null $language Optional language slug.
     * @return void
     */
    public static function maybe_send_decision_maker_email(
        array $settings,
        string $name,
        string $email,
        string $postcode = "",
        ?string $language = null
    ): void {
        $target_email = sanitize_email(
            (string) ($settings["decision_maker_email"] ?? "")
        );
        $letter = trim((string) ($settings["petition_letter"] ?? ""));

        if (!$target_email || "" === $letter) {
            return;
        }

        $sender_email = sanitize_email($email);
        $sender_name = sanitize_text_field($name);

        wp_mail(
            $target_email,
            (string) ($settings["decision_mail_subject"] ??
                __("Nouvelle signature de pétition", "plaidact-campaign-core")),
            sprintf(
                __(
                    "%1$s\n\n---\nSignature : %2$s <%3$s>\nCode postal : %4$s\nCampagne : %5$s",
                    "plaidact-campaign-core"
                ),
                $letter,
                "" !== trim($sender_name)
                    ? $sender_name
                    : __("Nom indisponible", "plaidact-campaign-core"),
                $sender_email,
                sanitize_text_field($postcode),
                self::get_campaign_url($language)
            ),
            self::build_reply_to_headers($sender_name, $sender_email)
        );
    }

    /**
     * Returns a language-aware campaign URL for notifications.
     *
     * @param string|null $language Optional language slug.
     * @return string
     */
    private static function get_campaign_url(?string $language = null): string
    {
        return Polylang::home_url($language);
    }

    /**
     * Builds optional reply-to headers for a signer.
     *
     * @param string $name Signer display name.
     * @param string $email Signer email.
     * @return array<int, string>
     */
    private static function build_reply_to_headers(
        string $name,
        string $email
    ): array {
        $sender_email = sanitize_email($email);
        $sender_name = sanitize_text_field($name);

        if (!$sender_email || "" === trim($sender_name)) {
            return [];
        }

        return ["Reply-To: " . $sender_name . " <" . $sender_email . ">"];
    }
}
