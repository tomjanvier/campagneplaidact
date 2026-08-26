<?php
/**
 * Effets de bord partagés des signatures Petitioner.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Centralise les effets de bord partagés des pétitions.
 */
final class Petition_Workflows
{
    /**
     * Retourne la meilleure URL de redirection pour les formulaires publics.
     *
     * @param string|null $language Slug de langue facultatif.
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
     * Envoie la notification d'une signature à l'administrateur configuré.
     *
     * @param array       $settings Réglages PLAID·ACT.
     * @param string      $name Nom affiché du signataire.
     * @param string      $email Adresse du signataire.
     * @param string      $postcode Code postal du signataire.
     * @param string      $phone Téléphone du signataire.
     * @param string|null $language Slug de langue facultatif.
     * @param int|null    $form_id Identifiant facultatif du formulaire Petitioner.
     * @param array<int,string> $context_lines Lignes facultatives ajoutées au message,
     *                                          par exemple les informations d'organisation.
     * @return void
     */
    public static function maybe_notify_admin(
        array $settings,
        string $name,
        string $email,
        string $postcode = "",
        string $phone = "",
        ?string $language = null,
        ?int $form_id = null,
        array $context_lines = []
    ): void {
        $notification_email = sanitize_email(
            (string) ($settings["notification_email"] ?? "")
        );

        if (!$notification_email) {
            return;
        }

        $message = sprintf(
            "Nom: %s\nEmail: %s\nCode postal: %s\nTéléphone: %s\nSite: %s",
            "" !== trim($name)
                ? $name
                : __("Nom indisponible", "plaidact-campaign-core"),
            sanitize_email($email),
            sanitize_text_field($postcode),
            sanitize_text_field($phone),
            self::get_site_url_for_language($language)
        );

        if ($form_id && $form_id > 0) {
            $message .= "\nFormulaire: " . $form_id;
        }

        foreach ($context_lines as $line) {
            $line = trim((string) $line);

            if ("" !== $line) {
                $message .= "\n" . $line;
            }
        }

        wp_mail(
            $notification_email,
            __("Nouvelle signature pétition", "plaidact-campaign-core"),
            $message
        );
    }

    /**
     * Envoie la signature au décideur lorsque son adresse est configurée.
     *
     * @param array       $settings Réglages PLAID·ACT.
     * @param string      $name Nom affiché du signataire.
     * @param string      $email Adresse du signataire.
     * @param string      $postcode Code postal du signataire.
     * @param string|null $language Slug de langue facultatif.
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
                    "%1$s\n\n---\nSignature : %2$s <%3$s>\nCode postal : %4$s\nSite : %5$s",
                    "plaidact-campaign-core"
                ),
                $letter,
                "" !== trim($sender_name)
                    ? $sender_name
                    : __("Nom indisponible", "plaidact-campaign-core"),
                $sender_email,
                sanitize_text_field($postcode),
                self::get_site_url_for_language($language)
            ),
            self::build_reply_to_headers($sender_name, $sender_email)
        );
    }

    /**
     * Retourne l'URL du site adaptée à la langue des notifications.
     *
     * @param string|null $language Slug de langue facultatif.
     * @return string
     */
    private static function get_site_url_for_language(?string $language = null): string
    {
        return Polylang::home_url($language);
    }

    /**
     * Construit les en-têtes de réponse facultatifs du signataire.
     *
     * @param string $name Nom affiché du signataire.
     * @param string $email Adresse du signataire.
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
