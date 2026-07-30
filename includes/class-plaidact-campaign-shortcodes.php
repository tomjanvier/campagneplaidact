<?php
/**
 * PLAID·ACT shortcodes for Petitioner petitions and frontend modules.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Registers PLAID·ACT shortcodes.
 */
final class Shortcodes
{
    /**
     * Hooks WordPress actions.
     *
     * @return void
     */
    public static function boot(): void
    {
        add_shortcode("petition_form", [__CLASS__, "render_petition_form"]);
        add_shortcode("plaid_social_wall", [__CLASS__, "render_social_wall"]);
        add_shortcode("plaid_partners", [__CLASS__, "render_partners"]);
        add_shortcode("petition_signers", [__CLASS__, "render_petition_signers"]);
        add_shortcode("plaid_petition_gauge", [__CLASS__, "render_petition_gauge"]);
        add_shortcode("plaid_newsletter_form", [
            __CLASS__,
            "render_newsletter_form",
        ]);
        add_action("plaidact_newsletter_form", [
            __CLASS__,
            "echo_newsletter_form",
        ]);
        add_shortcode("plaid_send_campaign", [
            __CLASS__,
            "render_send_campaign_form",
        ]);
        add_action("admin_menu", [__CLASS__, "register_admin_pages"]);
        add_action("admin_init", [__CLASS__, "register_settings"]);
        add_action("wp_enqueue_scripts", [__CLASS__, "enqueue_assets"]);
        add_filter("av_petitioner_labels_defaults", [__CLASS__, "translate_petitioner_labels"]);
        add_filter("av_petitioner_form_attributes", [__CLASS__, "customize_petitioner_form_attributes"], 10, 2);
        add_action("admin_post_nopriv_plaidact_newsletter_submit", [
            __CLASS__,
            "handle_newsletter_submit",
        ]);
        add_action("admin_post_plaidact_newsletter_submit", [
            __CLASS__,
            "handle_newsletter_submit",
        ]);
        add_action("admin_post_nopriv_plaidact_send_campaign_mail", [
            __CLASS__,
            "handle_send_campaign_mail",
        ]);
        add_action("admin_post_plaidact_send_campaign_mail", [
            __CLASS__,
            "handle_send_campaign_mail",
        ]);
    }


    /**
     * Loads shared frontend styles for shortcode and block output.
     *
     * @return void
     */
    public static function enqueue_assets(): void
    {
        wp_enqueue_style(
            "plaidact-campaign-shortcodes",
            PLAIDACT_CORE_URL . "assets/campaign-shortcodes.css",
            [],
            plaidact_campaign_core_asset_version("assets/campaign-shortcodes.css")
        );

        $settings = self::get_settings();
        $newsletter_custom_css = trim((string) ($settings["newsletter_custom_css"] ?? ""));
        if ("" !== $newsletter_custom_css) {
            wp_add_inline_style("plaidact-campaign-shortcodes", $newsletter_custom_css);
        }
        wp_enqueue_script(
            "plaidact-campaign-givoly",
            PLAIDACT_CORE_URL . "assets/campaign-givoly.js",
            [],
            plaidact_campaign_core_asset_version("assets/campaign-givoly.js"),
            true
        );
        wp_enqueue_script(
            "plaidact-organization-signature",
            PLAIDACT_CORE_URL . "assets/campaign-organization-signature.js",
            [],
            plaidact_campaign_core_asset_version("assets/campaign-organization-signature.js"),
            true
        );
    }

    public static function register_admin_pages(): void
    {
        add_menu_page(
            __("PLAID·ACT", "plaidact-campaign-core"),
            __("PLAID·ACT", "plaidact-campaign-core"),
            "manage_options",
            "plaidact-campaign-admin",
            [__CLASS__, "render_modules_page"],
            "dashicons-megaphone",
            20
        );

        add_submenu_page(
            "plaidact-campaign-admin",
            __("Modules", "plaidact-campaign-core"),
            __("Modules", "plaidact-campaign-core"),
            "manage_options",
            "plaidact-campaign-admin",
            [__CLASS__, "render_modules_page"]
        );

        add_submenu_page(
            "plaidact-campaign-admin",
            __("Signataires", "plaidact-campaign-core"),
            __("Signataires", "plaidact-campaign-core"),
            "manage_options",
            "plaidact-campaign-signers",
            [__CLASS__, "render_signers_admin_page"]
        );

        add_options_page(
            __("PLAID·ACT", "plaidact-campaign-core"),
            __("PLAID·ACT", "plaidact-campaign-core"),
            "manage_options",
            "plaidact-campaign-settings",
            [__CLASS__, "render_settings_page"]
        );
    }

    public static function register_settings(): void
    {
        register_setting(
            "plaidact_campaign_settings",
            "plaidact_campaign_settings",
            [__CLASS__, "sanitize_settings"]
        );
    }

    public static function get_default_settings(): array
    {
        return [
            "petition_goal" => 10000,
            "petition_form_id" => 0,
            "petition_sign_url" => "",
            "notification_email" => get_option("admin_email"),
            "brevo_api_key" => "",
            "brevo_list_plaidact" => 0,
            "brevo_list_petition" => 0,
            "petition_intro" => __(
                "Signez pour soutenir cette action.",
                "plaidact-campaign-core"
            ),
            "campaign_share_mail_title" => __(
                "Découvre cette pétition PLAID·ACT",
                "plaidact-campaign-core"
            ),
            "petition_title" => __(
                "Signer la pétition",
                "plaidact-campaign-core"
            ),
            "petition_show_signers" => "1",
            "petition_optin_label" => __(
                "M’inscrire à la newsletter PLAID·ACT",
                "plaidact-campaign-core"
            ),
            "send_mail_intro" => __(
                "Partagez cette page à votre réseau en un clic.",
                "plaidact-campaign-core"
            ),
            "send_mail_button_label" => __(
                "Envoyer le message",
                "plaidact-campaign-core"
            ),
            "petition_letter" => __(
                'Madame, Monsieur,\n\nNous vous demandons d’agir rapidement pour répondre aux revendications de cette action citoyenne.',
                "plaidact-campaign-core"
            ),
            "decision_maker_name" => "",
            "decision_maker_email" => "",
            "decision_mail_subject" => __(
                "Message citoyen depuis PLAID·ACT",
                "plaidact-campaign-core"
            ),
            "decision_mail_placeholder" => __(
                "Madame, Monsieur,\n\nJe vous écris pour...",
                "plaidact-campaign-core"
            ),
            "decision_mail_button_label" => __(
                "Envoyer au décideur",
                "plaidact-campaign-core"
            ),
            "social_share_text" => __(
                "Je soutiens cette action citoyenne. Rejoignez-nous !",
                "plaidact-campaign-core"
            ),
            "newsletter_title" => __("Newsletter", "plaidact-campaign-core"),
            "newsletter_intro" => __(
                "Inscription à la newsletter PLAID·ACT via Brevo.",
                "plaidact-campaign-core"
            ),
            "newsletter_button_label" => __(
                "S’inscrire",
                "plaidact-campaign-core"
            ),
            "newsletter_custom_css" => "",
            "enable_petition" => "1",
            "enable_newsletter" => "1",
            "enable_send_campaign" => "1",
            "enable_socialwall" => "1",
            "enable_articles" => "1",
            "enable_partners" => "1",
            "enable_report_highlight" => "0",
            "enable_directory" => "1",
            "enable_breves" => "1",
            "enable_out" => "1",
            "enable_agenda" => "1",
            "social_wall_title" => __("Social Wall", "plaidact-campaign-core"),
            "social_wall_description" => __(
                "Suivez ici les publications liées à PLAID·ACT.",
                "plaidact-campaign-core"
            ),
            "articles_section_title" => __(
                "Les articles de fond",
                "plaidact-campaign-core"
            ),
            "report_title" => __("Rapport", "plaidact-campaign-core"),
            "report_excerpt" => __(
                "Consultez notre rapport PDF mis en avant.",
                "plaidact-campaign-core"
            ),
            "report_pdf_url" => "",
            "report_button_label" => __(
                "Lire le rapport PDF",
                "plaidact-campaign-core"
            ),
            "report_empty_hint" => __(
                "Ajoutez une URL de PDF dans les réglages PLAID·ACT.",
                "plaidact-campaign-core"
            ),
            "brevo_doi_enabled" => "0",
            "brevo_doi_template_id" => 0,
            "brevo_redirection_url" => "",
            "givoly_donation_url" => "",
            "givoly_amount" => "",
            "givoly_button_label" => __(
                "Faire un don",
                "plaidact-campaign-core"
            ),
            "givoly_cta_text" => __(
                "Merci pour votre signature. Vous pouvez aller plus loin en soutenant cette action par un don.",
                "plaidact-campaign-core"
            ),
        ];
    }

    public static function get_translatable_setting_keys(): array
    {
        return [
            "petition_intro",
            "campaign_share_mail_title",
            "petition_title",
            "petition_optin_label",
            "send_mail_intro",
            "send_mail_button_label",
            "petition_letter",
            "decision_mail_subject",
            "decision_mail_placeholder",
            "decision_mail_button_label",
            "social_share_text",
            "newsletter_title",
            "newsletter_intro",
            "newsletter_button_label",
            "social_wall_title",
            "social_wall_description",
            "articles_section_title",
            "report_title",
            "report_excerpt",
            "report_button_label",
            "report_empty_hint",
            "givoly_button_label",
            "givoly_cta_text",
        ];
    }

    public static function get_settings(
        bool $translate = true,
        ?string $language = null
    ): array {
        static $settings_cache = [];

        $cache_key = ($translate ? "1" : "0") . "|" . ($language ?? "");

        if (isset($settings_cache[$cache_key])) {
            return $settings_cache[$cache_key];
        }

        $settings = wp_parse_args(
            (array) get_option("plaidact_campaign_settings", []),
            self::get_default_settings()
        );

        if (!$translate) {
            $settings_cache[$cache_key] = $settings;

            return $settings;
        }

        foreach (self::get_translatable_setting_keys() as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = Polylang::translate_string(
                    (string) $settings[$key],
                    $language
                );
            }
        }

        $settings_cache[$cache_key] = $settings;

        return $settings;
    }

    private static function get_current_language(): ?string
    {
        return Polylang::current_language();
    }

    private static function get_request_language(): ?string
    {
        $language = sanitize_key(wp_unslash($_POST["plaidact_language"] ?? ""));

        return "" !== $language ? $language : null;
    }

    private static function get_redirect_url(?string $language = null): string
    {
        return Petition_Workflows::get_redirect_url($language);
    }

    private static function redirect_with_status(
        string $query_key,
        string $query_value,
        ?string $language = null
    ): void {
        wp_safe_redirect(
            add_query_arg(
                $query_key,
                $query_value,
                self::get_redirect_url($language)
            )
        );
        exit();
    }

    private static function maybe_render_petitioner_form(
        array $settings,
        ?string $language = null
    ): string {
        if (!shortcode_exists("petitioner-form")) {
            return "";
        }

        $form_id = self::resolve_petitioner_form_id($settings, $language);
        if ($form_id <= 0) {
            return "";
        }

        return trim(
            (string) do_shortcode(
                sprintf('[petitioner-form id="%d"]', $form_id)
            )
        );
    }

    /**
     * Resolves the Petitioner form ID to render for the current campaign.
     *
     * @param array       $settings PLAID·ACT settings.
     * @param string|null $language Optional language slug.
     * @return int
     */
    public static function resolve_petitioner_form_id(
        array $settings,
        ?string $language = null
    ): int {
        $form_id = absint($settings["petition_form_id"] ?? 0);

        if ($form_id > 0) {
            return Polylang::resolve_post_translation($form_id, $language);
        }

        $candidate_ids = get_posts([
            "post_type" => "petitioner-petition",
            "post_status" => "publish",
            "posts_per_page" => 5,
            "fields" => "ids",
            "orderby" => "date",
            "order" => "DESC",
        ]);

        if (empty($candidate_ids)) {
            return 0;
        }

        if ($language && function_exists("pll_get_post_language")) {
            $language_matches = [];
            $untranslated_matches = [];

            foreach ($candidate_ids as $candidate_id) {
                $candidate_id = (int) $candidate_id;
                $candidate_language = Polylang::post_language(
                    $candidate_id,
                    false
                );

                if ($candidate_language === $language) {
                    $language_matches[] = $candidate_id;
                    continue;
                }

                if (null === $candidate_language) {
                    $untranslated_matches[] = $candidate_id;
                }
            }

            if (1 === count($language_matches)) {
                return (int) $language_matches[0];
            }

            if (!empty($language_matches)) {
                return 0;
            }

            if (1 === count($untranslated_matches)) {
                return (int) $untranslated_matches[0];
            }

            return 0;
        }

        return 1 === count($candidate_ids) ? (int) $candidate_ids[0] : 0;
    }


    /**
     * Returns every Petitioner form ID linked to the same multilingual petition group.
     *
     * @param int $form_id Resolved Petitioner form ID.
     * @return array<int>
     */
    public static function get_linked_petitioner_form_ids(int $form_id): array
    {
        if ($form_id <= 0) {
            return [];
        }

        $ids = [$form_id];

        if (function_exists("pll_get_post_translations")) {
            $translations = pll_get_post_translations($form_id);

            if (is_array($translations)) {
                foreach ($translations as $translated_id) {
                    $translated_id = absint($translated_id);

                    if ($translated_id > 0) {
                        $ids[] = $translated_id;
                    }
                }
            }
        }

        return array_values(array_unique(array_map("absint", $ids)));
    }

    /**
     * Builds the public URL used by the standalone petition counter CTA.
     */
    private static function get_petition_sign_url(int $form_id, array $settings, ?string $language): string
    {
        $custom_url = esc_url_raw((string) ($settings["petition_sign_url"] ?? ""));

        if ("" !== $custom_url) {
            return $custom_url;
        }

        $permalink = get_permalink($form_id);

        if (is_string($permalink) && "" !== $permalink) {
            return $permalink;
        }

        return Polylang::home_url($language);
    }

    public static function sanitize_settings(array $input): array
    {
        $existing = wp_parse_args(
            (array) get_option("plaidact_campaign_settings", []),
            self::get_default_settings()
        );

        if (!empty($input["_plaidact_modules_form"])) {
            foreach (array_keys(self::get_module_labels()) as $module_key) {
                $existing[$module_key] = !empty($input[$module_key]) ? "1" : "0";
            }

            return $existing;
        }

        return [
            "petition_goal" => absint($input["petition_goal"] ?? $existing["petition_goal"] ?? 10000),
            "petition_form_id" => absint($input["petition_form_id"] ?? 0),
            "petition_sign_url" => esc_url_raw((string) ($input["petition_sign_url"] ?? $existing["petition_sign_url"] ?? "")),
            "notification_email" => sanitize_email(
                $input["notification_email"] ?? get_option("admin_email")
            ),
            "brevo_api_key" => sanitize_text_field(
                (string) ($input["brevo_api_key"] ?? "")
            ),
            "brevo_list_plaidact" => absint($input["brevo_list_plaidact"] ?? 0),
            "brevo_list_petition" => absint($input["brevo_list_petition"] ?? 0),
            "petition_intro" => sanitize_text_field(
                (string) ($input["petition_intro"] ?? $existing["petition_intro"] ?? "")
            ),
            "campaign_share_mail_title" => sanitize_text_field(
                (string) ($input["campaign_share_mail_title"] ?? "")
            ),
            "petition_title" => sanitize_text_field(
                (string) ($input["petition_title"] ?? $existing["petition_title"] ?? "")
            ),
            "petition_show_signers" => !empty($input["petition_show_signers"])
                ? "1"
                : "0",
            "petition_optin_label" => sanitize_text_field(
                (string) ($input["petition_optin_label"] ?? $existing["petition_optin_label"] ?? "")
            ),
            "send_mail_intro" => sanitize_text_field(
                (string) ($input["send_mail_intro"] ?? "")
            ),
            "send_mail_button_label" => sanitize_text_field(
                (string) ($input["send_mail_button_label"] ?? "")
            ),
            "petition_letter" => sanitize_textarea_field(
                (string) ($input["petition_letter"] ?? $existing["petition_letter"] ?? "")
            ),
            "decision_maker_name" => sanitize_text_field(
                (string) ($input["decision_maker_name"] ?? "")
            ),
            "decision_maker_email" => sanitize_email(
                $input["decision_maker_email"] ?? ""
            ),
            "decision_mail_subject" => sanitize_text_field(
                (string) ($input["decision_mail_subject"] ?? "")
            ),
            "decision_mail_placeholder" => sanitize_textarea_field(
                (string) ($input["decision_mail_placeholder"] ?? "")
            ),
            "decision_mail_button_label" => sanitize_text_field(
                (string) ($input["decision_mail_button_label"] ?? "")
            ),
            "social_share_text" => sanitize_textarea_field(
                (string) ($input["social_share_text"] ?? "")
            ),
            "newsletter_title" => sanitize_text_field(
                (string) ($input["newsletter_title"] ?? "")
            ),
            "newsletter_intro" => sanitize_text_field(
                (string) ($input["newsletter_intro"] ?? "")
            ),
            "newsletter_button_label" => sanitize_text_field(
                (string) ($input["newsletter_button_label"] ?? "")
            ),
            "newsletter_custom_css" => wp_strip_all_tags(
                (string) ($input["newsletter_custom_css"] ?? "")
            ),
            "enable_petition" => isset($input["enable_petition"]) ? (!empty($input["enable_petition"]) ? "1" : "0") : (string) $existing["enable_petition"],
            "enable_newsletter" => isset($input["enable_newsletter"]) ? (!empty($input["enable_newsletter"]) ? "1" : "0") : (string) $existing["enable_newsletter"],
            "enable_send_campaign" => isset($input["enable_send_campaign"]) ? (!empty($input["enable_send_campaign"]) ? "1" : "0") : (string) $existing["enable_send_campaign"],
            "enable_socialwall" => isset($input["enable_socialwall"]) ? (!empty($input["enable_socialwall"]) ? "1" : "0") : (string) $existing["enable_socialwall"],
            "enable_articles" => isset($input["enable_articles"]) ? (!empty($input["enable_articles"]) ? "1" : "0") : (string) $existing["enable_articles"],
            "enable_partners" => isset($input["enable_partners"]) ? (!empty($input["enable_partners"]) ? "1" : "0") : (string) $existing["enable_partners"],
            "enable_report_highlight" => isset($input["enable_report_highlight"]) ? (!empty($input["enable_report_highlight"]) ? "1" : "0") : (string) $existing["enable_report_highlight"],
            "enable_directory" => isset($input["enable_directory"]) ? (!empty($input["enable_directory"]) ? "1" : "0") : (string) $existing["enable_directory"],
            "enable_breves" => isset($input["enable_breves"]) ? (!empty($input["enable_breves"]) ? "1" : "0") : (string) $existing["enable_breves"],
            "enable_out" => isset($input["enable_out"]) ? (!empty($input["enable_out"]) ? "1" : "0") : (string) $existing["enable_out"],
            "enable_agenda" => isset($input["enable_agenda"]) ? (!empty($input["enable_agenda"]) ? "1" : "0") : (string) $existing["enable_agenda"],
            "social_wall_title" => sanitize_text_field(
                (string) ($input["social_wall_title"] ?? "")
            ),
            "social_wall_description" => sanitize_text_field(
                (string) ($input["social_wall_description"] ?? "")
            ),
            "articles_section_title" => sanitize_text_field(
                (string) ($input["articles_section_title"] ?? "")
            ),
            "report_title" => sanitize_text_field(
                (string) ($input["report_title"] ?? "")
            ),
            "report_excerpt" => sanitize_text_field(
                (string) ($input["report_excerpt"] ?? "")
            ),
            "report_pdf_url" => esc_url_raw(
                (string) ($input["report_pdf_url"] ?? "")
            ),
            "report_button_label" => sanitize_text_field(
                (string) ($input["report_button_label"] ?? "")
            ),
            "report_empty_hint" => sanitize_text_field(
                (string) ($input["report_empty_hint"] ?? "")
            ),
            "brevo_doi_enabled" => !empty($input["brevo_doi_enabled"])
                ? "1"
                : "0",
            "brevo_doi_template_id" => absint(
                $input["brevo_doi_template_id"] ?? 0
            ),
            "brevo_redirection_url" => esc_url_raw(
                (string) ($input["brevo_redirection_url"] ?? "")
            ),
            "givoly_donation_url" => esc_url_raw(
                (string) ($input["givoly_donation_url"] ?? "")
            ),
            "givoly_amount" => absint($input["givoly_amount"] ?? 0),
            "givoly_button_label" => sanitize_text_field(
                (string) ($input["givoly_button_label"] ?? "")
            ),
            "givoly_cta_text" => sanitize_text_field(
                (string) ($input["givoly_cta_text"] ?? "")
            ),
        ];
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $settings = self::get_settings(false);
        ?>
		<div class="wrap">
			<h1><?php esc_html_e("Réglages PLAID·ACT", "plaidact-campaign-core"); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields("plaidact_campaign_settings"); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e(
          "ID formulaire pétition",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[petition_form_id]" type="number" value="<?php echo esc_attr(
    (string) $settings["petition_form_id"]
); ?>" class="small-text" /><p class="description"><?php esc_html_e(
    "Le module pétition embarqué prendra en charge la pétition. Avec Polylang, sa traduction sera résolue automatiquement.",
    "plaidact-campaign-core"
); ?></p></td></tr>
                        <tr><th scope="row"><?php esc_html_e("URL page personnalisée de signature", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[petition_sign_url]" type="url" value="<?php echo esc_attr((string) ($settings["petition_sign_url"] ?? "")); ?>" class="regular-text" placeholder="https://example.org/signer" /><p class="description"><?php esc_html_e("Utilisée par le compteur [plaid_petition_gauge] pour le bouton Signer la pétition. Laissez vide pour utiliser la page de la pétition Petitioner traduite.", "plaidact-campaign-core"); ?></p></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Email notification",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[notification_email]" type="email" value="<?php echo esc_attr(
    (string) $settings["notification_email"]
); ?>" class="regular-text" /></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Brevo API key",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[brevo_api_key]" type="text" value="<?php echo esc_attr(
    (string) $settings["brevo_api_key"]
); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "ID liste newsletter PLAID·ACT",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[brevo_list_plaidact]" type="number" value="<?php echo esc_attr(
    (string) $settings["brevo_list_plaidact"]
); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "ID liste Brevo pétition",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[brevo_list_petition]" type="number" value="<?php echo esc_attr(
    (string) $settings["brevo_list_petition"]
); ?>" class="small-text" /><p class="description"><?php esc_html_e("Les personnes qui signent la pétition, y compris via le module pétition embarqué, sont ajoutées à cette liste en plus de la liste newsletter si l’opt-in est coché.", "plaidact-campaign-core"); ?></p></td></tr>

					<tr><th scope="row"><?php esc_html_e("Titre articles", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[articles_section_title]" type="text" value="<?php echo esc_attr((string) $settings["articles_section_title"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Titre social wall", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[social_wall_title]" type="text" value="<?php echo esc_attr((string) $settings["social_wall_title"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Description social wall", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[social_wall_description]" type="text" value="<?php echo esc_attr((string) $settings["social_wall_description"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Titre rapport", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[report_title]" type="text" value="<?php echo esc_attr((string) $settings["report_title"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Texte rapport", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[report_excerpt]" type="text" value="<?php echo esc_attr((string) $settings["report_excerpt"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("URL PDF rapport", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[report_pdf_url]" type="url" value="<?php echo esc_attr((string) $settings["report_pdf_url"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Bouton rapport", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[report_button_label]" type="text" value="<?php echo esc_attr((string) $settings["report_button_label"]); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "Double opt-in Brevo",
         "plaidact-campaign-core"
     ); ?></th><td><label><input name="plaidact_campaign_settings[brevo_doi_enabled]" type="checkbox" value="1" <?php checked(
    (string) $settings["brevo_doi_enabled"],
    "1"
); ?> /> <?php esc_html_e(
     "Utiliser /contacts/doubleOptinConfirmation au lieu de créer directement le contact.",
     "plaidact-campaign-core"
 ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "ID template double opt-in",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[brevo_doi_template_id]" type="number" value="<?php echo esc_attr(
    (string) $settings["brevo_doi_template_id"]
); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "URL de retour double opt-in",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[brevo_redirection_url]" type="url" value="<?php echo esc_attr(
    (string) $settings["brevo_redirection_url"]
); ?>" class="regular-text" placeholder="https://example.org/merci" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("URL page de don Givoly", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[givoly_donation_url]" type="url" value="<?php echo esc_attr((string) ($settings["givoly_donation_url"] ?? "")); ?>" class="regular-text" placeholder="https://example.org/donner" /><p class="description"><?php esc_html_e("Si renseignée, un bouton de don apparaît après une signature Petitioner réussie et transmet les coordonnées du signataire en paramètres d’URL pour préremplir Givoly.", "plaidact-campaign-core"); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e("Montant suggéré Givoly", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[givoly_amount]" type="number" min="0" value="<?php echo esc_attr((string) ($settings["givoly_amount"] ?? "")); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Texte bouton don", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[givoly_button_label]" type="text" value="<?php echo esc_attr((string) ($settings["givoly_button_label"] ?? "")); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e("Texte invitation au don", "plaidact-campaign-core"); ?></th><td><input name="plaidact_campaign_settings[givoly_cta_text]" type="text" value="<?php echo esc_attr((string) ($settings["givoly_cta_text"] ?? "")); ?>" class="regular-text" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Design pétition Petitioner", "plaidact-campaign-core"); ?></th><td>
                        <p><?php esc_html_e("Les couleurs et le CSS du formulaire se règlent maintenant à un seul endroit : directement dans Petitioner.", "plaidact-campaign-core"); ?></p>
                        <p><a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=petitioner-petition&page=petition-settings')); ?>"><?php esc_html_e("Ouvrir les réglages Petitioner", "plaidact-campaign-core"); ?></a></p>
                        <p class="description"><?php esc_html_e("Le shortcode PLAID·ACT ne duplique plus ces champs : il applique automatiquement les variables Petitioner au rendu public, puis laisse le CSS personnalisé de Petitioner surcharger le tout.", "plaidact-campaign-core"); ?></p>
                    </td></tr>
                    <tr><th scope="row"><?php esc_html_e("Email de confirmation de signature", "plaidact-campaign-core"); ?></th><td>
                        <p><?php esc_html_e("Pour modifier cet email, ouvrez la pétition concernée, puis Réglages avancés. Activez « Remplacer l’email de confirmation ? » et personnalisez son sujet et son contenu.", "plaidact-campaign-core"); ?></p>
                        <p class="description"><?php esc_html_e("Si la confirmation par email est activée, conservez impérativement la variable {{confirmation_link}} dans le message afin que la signature puisse être validée.", "plaidact-campaign-core"); ?></p>
                    </td></tr>
                    <tr><th scope="row"><?php esc_html_e(
         "Signataires publics",
         "plaidact-campaign-core"
     ); ?></th><td><label><input name="plaidact_campaign_settings[petition_show_signers]" type="checkbox" value="1" <?php checked(
    (string) ($settings["petition_show_signers"] ?? "1"),
    "1"
); ?> /> <?php esc_html_e(
     "Afficher la liste publique des signataires sous le formulaire Petitioner.",
     "plaidact-campaign-core"
 ); ?></label><p class="description"><?php esc_html_e(
    "Le titre, le texte, la lettre, les couleurs et les champs de la pétition se modifient directement dans Petitioner. PLAID·ACT n’affiche plus les anciens réglages du formulaire natif.",
    "plaidact-campaign-core"
); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "Titre email de partage",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[campaign_share_mail_title]" type="text" value="<?php echo esc_attr(
    (string) $settings["campaign_share_mail_title"]
); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "Texte bloc partage email",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[send_mail_intro]" type="text" value="<?php echo esc_attr(
    (string) $settings["send_mail_intro"]
); ?>" class="regular-text" /></td></tr>
<tr><th scope="row"><?php esc_html_e(
         "Nom du décideur",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[decision_maker_name]" type="text" value="<?php echo esc_attr(
    (string) $settings["decision_maker_name"]
); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "Email du décideur",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[decision_maker_email]" type="email" value="<?php echo esc_attr(
    (string) $settings["decision_maker_email"]
); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "Sujet email décideur",
         "plaidact-campaign-core"
     ); ?></th><td><input name="plaidact_campaign_settings[decision_mail_subject]" type="text" value="<?php echo esc_attr(
    (string) $settings["decision_mail_subject"]
); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e(
         "Texte pré-rempli email décideur",
         "plaidact-campaign-core"
     ); ?></th><td><textarea name="plaidact_campaign_settings[decision_mail_placeholder]" class="large-text" rows="5"><?php echo esc_textarea(
    (string) $settings["decision_mail_placeholder"]
); ?></textarea></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Texte par défaut pour les partages sociaux",
          "plaidact-campaign-core"
      ); ?></th><td><textarea name="plaidact_campaign_settings[social_share_text]" class="large-text" rows="4"><?php echo esc_textarea(
    (string) $settings["social_share_text"]
); ?></textarea></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Titre bloc newsletter",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[newsletter_title]" type="text" value="<?php echo esc_attr(
    (string) $settings["newsletter_title"]
); ?>" class="regular-text" /></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Texte bloc newsletter",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[newsletter_intro]" type="text" value="<?php echo esc_attr(
    (string) $settings["newsletter_intro"]
); ?>" class="regular-text" /></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Libellé bouton newsletter",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[newsletter_button_label]" type="text" value="<?php echo esc_attr(
    (string) $settings["newsletter_button_label"]
); ?>" class="regular-text" /></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "CSS personnalisé newsletter",
          "plaidact-campaign-core"
      ); ?></th><td><textarea name="plaidact_campaign_settings[newsletter_custom_css]" class="large-text code" rows="10" placeholder=".plaidact-card--newsletter { ... }"><?php echo esc_textarea(
    (string) ($settings["newsletter_custom_css"] ?? "")
); ?></textarea><p class="description"><?php esc_html_e("CSS injecté après les styles PLAID·ACT. Ciblez .plaidact-card--newsletter pour personnaliser uniquement le bloc newsletter.", "plaidact-campaign-core"); ?></p></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Libellé bouton partage email",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[send_mail_button_label]" type="text" value="<?php echo esc_attr(
    (string) $settings["send_mail_button_label"]
); ?>" class="regular-text" /></td></tr>
						<tr><th scope="row"><?php esc_html_e(
          "Libellé bouton décideur",
          "plaidact-campaign-core"
      ); ?></th><td><input name="plaidact_campaign_settings[decision_mail_button_label]" type="text" value="<?php echo esc_attr(
    (string) $settings["decision_mail_button_label"]
); ?>" class="regular-text" /></td></tr>
					</table>
					<p class="description"><?php esc_html_e(
         "Quand Polylang est actif, les champs textuels ci-dessus sont enregistrés comme chaînes traduisibles dans Polylang > Traductions des chaînes.",
         "plaidact-campaign-core"
     ); ?></p>
					<?php submit_button(); ?>
				</form>
		</div>
		<?php
    }

    public static function render_modules_page(): void
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $settings = self::get_settings(false);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e("Modules PLAID·ACT", "plaidact-campaign-core"); ?></h1>
            <p><?php esc_html_e("Activez ici les modules disponibles en shortcodes et en blocs Gutenberg. Les réglages techniques restent dans Réglages > PLAID·ACT.", "plaidact-campaign-core"); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields("plaidact_campaign_settings"); ?>
                <input type="hidden" name="plaidact_campaign_settings[_plaidact_modules_form]" value="1" />
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e("Modules actifs", "plaidact-campaign-core"); ?></th><td>
                        <?php foreach (self::get_module_labels() as $key => $label) : ?>
                            <label><input name="plaidact_campaign_settings[<?php echo esc_attr($key); ?>]" type="checkbox" value="1" <?php checked((string) $settings[$key], "1"); ?> /> <?php echo esc_html($label); ?></label><br />
                        <?php endforeach; ?>
                    </td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function get_module_labels(): array
    {
        return [
            "enable_petition" => __("Pétition unique Petitioner", "plaidact-campaign-core"),
            "enable_newsletter" => __("Bloc newsletter", "plaidact-campaign-core"),
            "enable_send_campaign" => __("Système d’envoi aux décideurs", "plaidact-campaign-core"),
            "enable_directory" => __("Répertoire", "plaidact-campaign-core"),
            "enable_breves" => __("Brèves", "plaidact-campaign-core"),
            "enable_out" => __("Out / sorties", "plaidact-campaign-core"),
            "enable_agenda" => __("Agenda", "plaidact-campaign-core"),
            "enable_socialwall" => __("Social wall", "plaidact-campaign-core"),
            "enable_articles" => __("Articles", "plaidact-campaign-core"),
            "enable_partners" => __("Partenaires", "plaidact-campaign-core"),
            "enable_report_highlight" => __("Rapport PDF", "plaidact-campaign-core"),
        ];
    }

    public static function render_signers_admin_page(): void
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $settings = self::get_settings(false);
        $form_id = self::resolve_petitioner_form_id($settings);
        $page = max(1, absint($_GET["paged"] ?? 1));
        $per_page = 50;
        $result = ["submissions" => [], "total" => 0];

        if ($form_id > 0 && class_exists("AV_Petitioner_Submissions_Model")) {
            $result = self::get_linked_petition_submissions(
                self::get_linked_petitioner_form_ids($form_id),
                [
                    "per_page" => $per_page,
                    "offset" => ($page - 1) * $per_page,
                    "fields" => [
                        "form_id",
                        "fname",
                        "lname",
                        "email",
                        "postal_code",
                        "phone",
                        "newsletter",
                        "approval_status",
                        "submitted_at",
                    ],
                ]
            );
        }

        $total = absint($result["total"] ?? 0);
        $total_pages = max(1, (int) ceil($total / $per_page));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e("Signataires de la pétition", "plaidact-campaign-core"); ?></h1>
            <p><?php esc_html_e("Consultez les signatures Petitioner sans ouvrir ni modifier la pétition.", "plaidact-campaign-core"); ?></p>
            <?php if ($form_id <= 0 || !class_exists("AV_Petitioner_Submissions_Model")) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e("Aucun formulaire Petitioner publié ou module de signatures indisponible.", "plaidact-campaign-core"); ?></p></div>
            <?php else : ?>
                <p><strong><?php esc_html_e("Formulaires liés", "plaidact-campaign-core"); ?> :</strong> #<?php echo esc_html(implode(", #", self::get_linked_petitioner_form_ids($form_id))); ?> — <strong><?php esc_html_e("Total", "plaidact-campaign-core"); ?> :</strong> <?php echo esc_html((string) $total); ?></p>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e("Pétition", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Nom", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Email", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Code postal", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Téléphone", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Newsletter", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Statut", "plaidact-campaign-core"); ?></th>
                        <th><?php esc_html_e("Date", "plaidact-campaign-core"); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ((array) ($result["submissions"] ?? []) as $submission) : ?>
                        <tr>
                            <td>#<?php echo esc_html((string) ($submission->form_id ?? $form_id)); ?></td>
                            <td><?php echo esc_html(trim(($submission->fname ?? "") . " " . ($submission->lname ?? ""))); ?></td>
                            <td><a href="mailto:<?php echo esc_attr((string) ($submission->email ?? "")); ?>"><?php echo esc_html((string) ($submission->email ?? "")); ?></a></td>
                            <td><?php echo esc_html((string) ($submission->postal_code ?? "")); ?></td>
                            <td><?php echo esc_html((string) ($submission->phone ?? "")); ?></td>
                            <td><?php echo !empty($submission->newsletter) ? esc_html__("Oui", "plaidact-campaign-core") : esc_html__("Non", "plaidact-campaign-core"); ?></td>
                            <td><?php echo esc_html((string) ($submission->approval_status ?? "")); ?></td>
                            <td><?php echo esc_html((string) ($submission->submitted_at ?? "")); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1) : ?>
                    <p class="tablenav-pages">
                        <?php for ($i = 1; $i <= $total_pages; $i++) : ?>
                            <a class="button<?php echo $i === $page ? " button-primary" : ""; ?>" href="<?php echo esc_url(add_query_arg(["page" => "plaidact-campaign-signers", "paged" => $i], admin_url("admin.php"))); ?>"><?php echo esc_html((string) $i); ?></a>
                        <?php endfor; ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_petition_form(array $atts = []): string
    {
        $language = self::get_current_language();
        $settings = self::get_settings(true, $language);
        $petitioner_output = self::maybe_render_petitioner_form(
            $settings,
            $language
        );

        if ("" !== $petitioner_output) {
            $signers = "1" === (string) ($settings["petition_show_signers"] ?? "1")
                ? self::render_petition_signers([])
                : "";

            return sprintf(
                '<section class="plaidact-petition-block %4$s %5$s" style="--plaidact-petition-accent:%1$s"><div class="plaidact-petition-block__body">%2$s</div>%3$s</section>',
                esc_attr(self::get_petitioner_accent_color()),
                $petitioner_output,
                $signers . self::render_givoly_donation_cta($settings),
                "plaidact-petition-block--theme",
                self::get_campaign_design_class($settings)
            );
        }

        if (current_user_can("manage_options")) {
            return sprintf(
                '<div class="plaidact-card plaidact-card--petition"><p><strong>%s</strong></p><p>%s</p></div>',
                esc_html__("Pétition non configurée", "plaidact-campaign-core"),
                esc_html__("Créez ou publiez un formulaire dans le module Petitioner embarqué, puis sélectionnez son ID dans Réglages > PLAID·ACT. L’ancien formulaire natif n’est plus rendu afin de garder un seul système de pétition.", "plaidact-campaign-core")
            );
        }

        return "";
    }


    private static function get_campaign_design_class(array $settings): string
    {
        return "plaidact-campaign--theme";
    }

    private static function build_givoly_donation_url(array $settings): string
    {
        $donation_url = esc_url_raw((string) ($settings["givoly_donation_url"] ?? ""));

        if ("" === $donation_url) {
            return "";
        }

        $query_args = [];
        $amount = absint($settings["givoly_amount"] ?? 0);

        if ($amount > 0) {
            $query_args["amount"] = $amount;
            $query_args["givoly_amount"] = $amount;
        }

        if (!empty($query_args)) {
            $donation_url = add_query_arg($query_args, $donation_url);
        }

        return $donation_url;
    }

    private static function render_givoly_donation_cta(array $settings): string
    {
        $donation_url = self::build_givoly_donation_url($settings);

        if ("" === $donation_url) {
            return "";
        }

        return sprintf(
            '<aside class="plaidact-givoly-cta" data-plaidact-givoly-base-url="%2$s" hidden><p>%1$s</p><a class="plaidact-givoly-cta__button wp-element-button" href="%2$s">%3$s</a></aside>',
            esc_html((string) ($settings["givoly_cta_text"] ?? "")),
            esc_url($donation_url),
            esc_html((string) ($settings["givoly_button_label"] ?? __("Faire un don", "plaidact-campaign-core")))
        );
    }


    /**
     * Renders a standalone Petitioner signature gauge for a petition.
     *
     * @param array<string,mixed> $atts Shortcode or block attributes.
     * @return string
     */
    public static function render_petition_gauge(array $atts = []): string
    {
        $language = self::get_current_language();
        $settings = self::get_settings(true, $language);
        $atts = shortcode_atts(
            [
                "id" => 0,
                "petition_id" => 0,
                "title" => __("Progression de la pétition", "plaidact-campaign-core"),
                "width" => 34,
                "height" => 0,
            ],
            $atts,
            "plaid_petition_gauge"
        );

        $requested_id = absint($atts["id"] ?: $atts["petition_id"]);
        $form_id = $requested_id > 0
            ? Polylang::resolve_post_translation($requested_id, $language)
            : self::resolve_petitioner_form_id($settings, $language);

        if ($form_id <= 0) {
            return "";
        }

        $goal = self::get_petition_goal($form_id, $settings);
        $signatures = self::get_petition_signature_count($form_id);
        $sign_url = self::get_petition_sign_url($form_id, $settings, $language);
        $progress = $goal > 0 ? min(100, (int) round(($signatures / $goal) * 100)) : 0;
        $title = trim((string) $atts["title"]);
        $width = max(12, min(96, (float) $atts["width"]));
        $height = max(0, min(6, (float) $atts["height"]));
        $style = sprintf(
            "--plaidact-petition-accent:%s;--plaidact-petition-progress:%d%%;--plaidact-petition-bar-min-width:%s;--plaidact-petition-gauge-width:%srem;%s",
            esc_attr(self::get_petitioner_accent_color()),
            $progress,
            $progress > 0 ? "0.5rem" : "0",
            esc_attr((string) $width),
            $height > 0 ? sprintf("--plaidact-petition-gauge-height:%srem;", esc_attr((string) $height)) : ""
        );

        return sprintf(
            '<aside class="petitioner plaidact-petition-gauge plaidact-petition-gauge--cta %6$s" style="%7$s" aria-label="%1$s">%2$s<div class="plaidact-petition-gauge__stats"><span class="plaidact-petition-gauge__stat"><strong>%3$s</strong> <span>%8$s</span></span><span class="plaidact-petition-gauge__stat plaidact-petition-gauge__stat--end"><strong>%4$s</strong> <span>%9$s</span></span></div><span class="plaidact-petition-gauge__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%5$d"><span class="plaidact-petition-gauge__bar"></span></span><a class="plaidact-petition-gauge__button wp-element-button" href="%10$s">%11$s</a></aside>',
            esc_attr($title ?: __("Progression de la pétition", "plaidact-campaign-core")),
            $title !== "" ? sprintf('<h3 class="petitioner__title plaidact-petition-gauge__title">%s</h3>', esc_html($title)) : "",
            esc_html(number_format_i18n($signatures)),
            esc_html(number_format_i18n($goal)),
            $progress,
            esc_attr(self::get_campaign_design_class($settings)),
            esc_attr($style),
            esc_html__("signataires", "plaidact-campaign-core"),
            esc_html__("objectif", "plaidact-campaign-core"),
            esc_url($sign_url),
            esc_html__("Signer la pétition", "plaidact-campaign-core")
        );
    }

    /**
     * Gets the active Petitioner goal, with PLAID·ACT settings as fallback.
     *
     * @param int          $form_id Petition form ID.
     * @param array<mixed> $settings PLAID·ACT settings.
     * @return int
     */
    private static function get_petition_goal(int $form_id, array $settings): int
    {
        if (class_exists("AV_Petitioner_Goal_Milestones")) {
            return max(0, (int) \AV_Petitioner_Goal_Milestones::get_active_goal($form_id));
        }

        return max(0, absint($settings["petition_goal"] ?? 0));
    }

    /**
     * Gets the confirmed signature count for a Petitioner petition.
     *
     * @param int $form_id Petition form ID.
     * @return int
     */
    private static function get_petition_signature_count(int $form_id): int
    {
        if (class_exists("AV_Petitioner_Submissions_Model")) {
            $count = 0;

            foreach (self::get_linked_petitioner_form_ids($form_id) as $linked_form_id) {
                $count += max(0, (int) \AV_Petitioner_Submissions_Model::get_submission_count($linked_form_id));
            }

            return $count;
        }

        if (shortcode_exists("petitioner-submission-count")) {
            return max(0, absint(do_shortcode(sprintf('[petitioner-submission-count id="%d"]', $form_id))));
        }

        return 0;
    }


    /**
     * Retrieves submissions across all translated Petitioner forms in one paginated list.
     *
     * @param array<int> $form_ids Linked Petitioner form IDs.
     * @param array<string,mixed> $settings Query settings.
     * @return array{submissions:array,total:int}
     */
    private static function get_linked_petition_submissions(array $form_ids, array $settings): array
    {
        global $wpdb;

        if (!class_exists("AV_Petitioner_Submissions_Model")) {
            return ["submissions" => [], "total" => 0];
        }

        $form_ids = array_values(array_filter(array_map("absint", $form_ids)));

        if (empty($form_ids)) {
            return ["submissions" => [], "total" => 0];
        }

        $allowed_fields = \AV_Petitioner_Submissions_Model::$ALLOWED_FIELDS;
        $fields = $settings["fields"] ?? "*";

        if ("*" !== $fields) {
            $fields = implode(", ", array_intersect((array) $fields, $allowed_fields));
            $fields = "" !== $fields ? $fields : "*";
        }

        $per_page = absint($settings["per_page"] ?? 10);
        $offset = absint($settings["offset"] ?? 0);
        $placeholders = implode(",", array_fill(0, count($form_ids), "%d"));
        $table = \AV_Petitioner_Submissions_Model::table_name();
        $where = "form_id IN ($placeholders)";

        if (!empty($settings["confirmed_only"])) {
            $where .= " AND approval_status = %s";
        }

        $params = $form_ids;
        if (!empty($settings["confirmed_only"])) {
            $params[] = "Confirmed";
        }

        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        $rows_sql = "SELECT $fields FROM $table WHERE $where ORDER BY submitted_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($rows_sql, array_merge($params, [$per_page, $offset])));

        return ["submissions" => $rows ?: [], "total" => $total];
    }

    public static function render_petition_signers(array $atts = []): string
    {
        $language = self::get_current_language();
        $settings = self::get_settings(true, $language);
        $form_id = self::resolve_petitioner_form_id($settings, $language);

        if ($form_id <= 0) {
            return "";
        }

        $list = self::render_petition_signers_fallback($form_id);

        if ("" === trim((string) $list)) {
            return "";
        }

        return sprintf(
            '<aside class="plaidact-petition-signers"><div class="plaidact-petition-signers__header"><h3>%s</h3><p>%s</p></div>%s</aside>',
            esc_html__("Ils et elles ont déjà signé", "plaidact-campaign-core"),
            esc_html__("Liste publique consultable sans modifier la pétition.", "plaidact-campaign-core"),
            $list
        );
    }


    private static function render_petition_signers_fallback(int $form_id): string
    {
        if (!class_exists("AV_Petitioner_Submissions_Model")) {
            return "";
        }

        $result = self::get_linked_petition_submissions(self::get_linked_petitioner_form_ids($form_id), [
            "per_page" => 12,
            "fields" => ["fname", "lname", "submitted_at", "hide_name", "approval_status"],
            "confirmed_only" => true,
        ]);

        $submissions = (array) ($result["submissions"] ?? []);
        if (empty($submissions)) {
            return "";
        }

        $items = "";
        foreach ($submissions as $submission) {
            $name = !empty($submission->hide_name)
                ? __("Anonyme", "plaidact-campaign-core")
                : trim((string) ($submission->fname ?? "") . " " . substr((string) ($submission->lname ?? ""), 0, 1));
            if ("" === trim($name)) {
                $name = __("Signataire", "plaidact-campaign-core");
            }

            $items .= sprintf(
                '<li><strong>%1$s</strong><span>%2$s</span></li>',
                esc_html($name),
                esc_html((string) ($submission->submitted_at ?? ""))
            );
        }

        return '<ul class="plaidact-petition-signers__fallback">' . $items . '</ul>';
    }

    public static function translate_petitioner_labels(array $labels): array
    {
        return array_merge($labels, [
            "could_not_submit" => __("Impossible d’envoyer le formulaire.", "plaidact-campaign-core"),
            "error_generic" => __("Une erreur est survenue. Réessayez.", "plaidact-campaign-core"),
            "error_required" => __("Ce champ est obligatoire.", "plaidact-campaign-core"),
            "already_signed" => __("Vous avez déjà signé cette pétition !", "plaidact-campaign-core"),
            "invalid_nonce" => __("La session du formulaire a expiré. Rechargez la page puis réessayez.", "plaidact-campaign-core"),
            "flagged_as_spam" => __("Votre signature a été identifiée comme indésirable. Vérifiez vos informations puis réessayez.", "plaidact-campaign-core"),
            "confirm_email" => __("Confirmer mon adresse email", "plaidact-campaign-core"),
            "email_confirmed_success" => __("Merci, votre adresse email et votre signature sont confirmées !", "plaidact-campaign-core"),
            "email_confirmed_error" => __("Impossible de confirmer votre adresse email. Le lien a peut-être déjà été utilisé ou a expiré.", "plaidact-campaign-core"),
            "ty_email_subject" => __("Merci d’avoir signé la pétition !", "plaidact-campaign-core"),
            "ty_email" => __("<p>Bonjour {{user_name}},</p><p>Merci d’avoir signé la pétition.</p>", "plaidact-campaign-core"),
            "ty_email_subject_confirm" => __("Confirmez votre signature de la pétition", "plaidact-campaign-core"),
            "ty_email_confirm" => __("<p>Bonjour {{user_name}},</p><p>Merci d’avoir signé la pétition.</p><p>Pour valider définitivement votre signature, cliquez sur le lien ci-dessous :</p><p>{{confirmation_link}}</p>", "plaidact-campaign-core"),
            "success_message_title" => __("Merci !", "plaidact-campaign-core"),
            "success_message" => __("Votre signature a bien été prise en compte.", "plaidact-campaign-core"),
            "your_name_here" => __("{Votre nom apparaîtra ici}", "plaidact-campaign-core"),
            "view_the_letter" => __("Voir la lettre", "plaidact-campaign-core"),
            "close_modal" => __("Fermer la fenêtre", "plaidact-campaign-core"),
            "signatures" => __("Signatures", "plaidact-campaign-core"),
            "goal" => __("Objectif", "plaidact-campaign-core"),
            "name" => __("Nom", "plaidact-campaign-core"),
            "anonymous" => __("Anonyme", "plaidact-campaign-core"),
            "fname" => __("Prénom", "plaidact-campaign-core"),
            "fname_placeholder" => __("Camille", "plaidact-campaign-core"),
            "lname" => __("Nom", "plaidact-campaign-core"),
            "lname_placeholder" => __("Dupont", "plaidact-campaign-core"),
            "email" => __("Votre email", "plaidact-campaign-core"),
            "email_placeholder" => __("camille@example.org", "plaidact-campaign-core"),
            "country" => __("Pays", "plaidact-campaign-core"),
            "postal_code" => __("Code postal", "plaidact-campaign-core"),
            "phone" => __("Téléphone", "plaidact-campaign-core"),
            "newsletter" => __("Recevoir les actualités", "plaidact-campaign-core"),
            "hide_name" => __("Ne pas afficher mon nom publiquement", "plaidact-campaign-core"),
            "accept_tos" => __("J’accepte que ma signature soit enregistrée pour cette pétition.", "plaidact-campaign-core"),
            "submit_button_label" => __("Signer la pétition", "plaidact-campaign-core"),
        ]);
    }

    private static function get_petitioner_accent_color(): string
    {
        $petitioner_color = sanitize_hex_color((string) get_option("petitioner_primary_color", ""));

        if ($petitioner_color) {
            return $petitioner_color;
        }

        return "#ff99cc";
    }

    public static function customize_petitioner_form_attributes(array $attrs, $form_id): array
    {
        $settings = self::get_settings();
        $color = self::get_petitioner_accent_color();
        $attrs["class"] = trim(($attrs["class"] ?? "petitioner") . " plaidact-petitioner-form plaidact-petitioner-form--theme");
        $attrs["style"] = trim(($attrs["style"] ?? "") . ";--ptr-color-primary:" . $color . ";--plaidact-petition-accent:" . $color);

        $givoly_url = self::build_givoly_donation_url($settings);
        if ("" !== $givoly_url) {
            $attrs["data-plaidact-givoly-url"] = $givoly_url;
        }

        return $attrs;
    }

    /**
     * Renders partner cards.
     *
     * @param array<string,mixed> $atts Shortcode or block attributes.
     * @return string
     */
    public static function render_partners(array $atts = []): string
    {
        $atts = shortcode_atts(
            [
                "title" => __("Organisations partenaires", "plaidact-campaign-core"),
                "limit" => -1,
            ],
            $atts,
            "plaid_partners"
        );

        $partners = get_posts([
            "post_type" => "plaid_partner",
            "post_status" => "publish",
            "posts_per_page" => (int) $atts["limit"],
            "orderby" => ["menu_order" => "ASC", "title" => "ASC"],
        ]);

        if (empty($partners)) {
            return "";
        }

        $settings = self::get_settings();

        ob_start();
        ?>
        <section class="plaidact-partners plaidact-card plaidact-card--partners <?php echo esc_attr(self::get_campaign_design_class($settings)); ?>">
            <?php if (!empty($atts["title"])) : ?>
                <h2><?php echo esc_html((string) $atts["title"]); ?></h2>
            <?php endif; ?>
            <div class="plaidact-partners-grid">
                <?php foreach ($partners as $partner) : ?>
                    <?php $url = (string) get_post_meta($partner->ID, "_plaid_partner_url", true); ?>
                    <article class="plaidact-partner-card">
                        <?php if ($url) : ?><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php endif; ?>
                            <?php echo get_the_post_thumbnail($partner->ID, "medium", ["loading" => "lazy"]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <h3><?php echo esc_html(get_the_title($partner)); ?></h3>
                        <?php if ($url) : ?></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }


    /**
     * Sanitizes a space-separated CSS class list.
     *
     * @param string $classes Raw class list.
     * @return string
     */
    private static function sanitize_css_classes(string $classes): string
    {
        $sanitized = array_filter(array_map(
            static fn(string $class): string => sanitize_html_class($class),
            preg_split('/\s+/', trim($classes)) ?: []
        ));

        return implode(' ', array_unique($sanitized));
    }

    public static function render_newsletter_form(array $atts = []): string
    {
        $language = self::get_current_language();
        $settings = self::get_settings(true, $language);
        $atts = shortcode_atts(
            [
                "title" => "",
                "intro" => "",
                "buttonLabel" => "",
                "button_label" => "",
                "class" => "",
                "className" => "",
                "formClass" => "",
                "form_class" => "",
                "hideName" => false,
                "hide_name" => false,
            ],
            $atts,
            "plaid_newsletter_form"
        );
        $newsletter_title = trim((string) $atts["title"]);
        $newsletter_intro = trim((string) $atts["intro"]);
        $newsletter_button_label = trim((string) ($atts["buttonLabel"] ?: $atts["button_label"]));
        $hide_name = filter_var($atts["hideName"], FILTER_VALIDATE_BOOLEAN)
            || filter_var($atts["hide_name"], FILTER_VALIDATE_BOOLEAN);
        $extra_class = self::sanitize_css_classes(
            (string) $atts["class"] . " " . (string) $atts["className"]
        );
        $form_extra_class = self::sanitize_css_classes(
            (string) $atts["formClass"] . " " . (string) $atts["form_class"]
        );
        $form_classes = self::sanitize_css_classes(
            "plaidact-newsletter-form stp-newsletter-form " .
            ($hide_name ? "plaidact-newsletter-form--email-only " : "") .
            $form_extra_class
        );
        $action = esc_url(admin_url("admin-post.php"));
        $status = isset($_GET["newsletter_subscribed"])
            ? sanitize_text_field(wp_unslash($_GET["newsletter_subscribed"]))
            : "";
        $message = "";
        $message_type = "";

        if ("1" === $status) {
            $message_type = "success";
            $message = __(
                "Merci, votre inscription a bien été prise en compte.",
                "plaidact-campaign-core"
            );
        } elseif ("confirm" === $status) {
            $message_type = "success";
            $message = __(
                "Merci ! Vérifiez votre boîte mail pour confirmer votre inscription.",
                "plaidact-campaign-core"
            );
        } elseif ("0" === $status) {
            $message_type = "error";
            $message = __(
                "Inscription impossible pour le moment. Réessayez plus tard.",
                "plaidact-campaign-core"
            );
        }

        ob_start();
        ?>
		<div class="plaidact-card plaidact-card--newsletter <?php echo esc_attr(trim(self::get_campaign_design_class($settings) . " " . $extra_class)); ?>" id="newsletter">
			<div class="plaidact-newsletter__content">
				<p class="plaidact-newsletter__eyebrow"><?php esc_html_e("Newsletter PLAID·ACT", "plaidact-campaign-core"); ?></p>
				<h3><?php echo esc_html(
        $newsletter_title !== "" ? $newsletter_title : (string) ($settings["newsletter_title"] ??
            __("Newsletter", "plaidact-campaign-core"))
    ); ?></h3>
				<p><?php echo esc_html(
        $newsletter_intro !== "" ? $newsletter_intro : (string) ($settings["newsletter_intro"] ??
            __(
                "Inscription à la newsletter PLAID·ACT via Brevo.",
                "plaidact-campaign-core"
            ))
    ); ?></p>
			</div>
			<?php if ($message): ?>
				<p class="plaidact-newsletter-message plaidact-newsletter-message--<?php echo esc_attr($message_type); ?>" role="status"><?php echo esc_html($message); ?></p>
			<?php endif; ?>
			<form class="<?php echo esc_attr($form_classes); ?>" method="post" action="<?php echo $action; ?>">
				<input type="hidden" name="action" value="plaidact_newsletter_submit" />
				<input type="hidden" name="plaidact_language" value="<?php echo esc_attr(
        (string) $language
    ); ?>" />
				<?php wp_nonce_field(
        "plaidact_newsletter_submit_action",
        "plaidact_newsletter_nonce"
    ); ?>
				<?php if (!$hide_name): ?>
					<label class="plaidact-newsletter-form__field plaidact-newsletter-form__field--name">
						<span><?php esc_html_e("Prénom / nom", "plaidact-campaign-core"); ?></span>
						<input type="text" name="name" autocomplete="name" placeholder="<?php esc_attr_e("Camille Dupont", "plaidact-campaign-core"); ?>" />
					</label>
				<?php endif; ?>
				<label class="plaidact-newsletter-form__field plaidact-newsletter-form__field--email">
					<span><?php esc_html_e("Email", "plaidact-campaign-core"); ?></span>
					<input type="email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e(
        "camille@example.org",
        "plaidact-campaign-core"
    ); ?>" />
				</label>
				<label class="plaidact-newsletter-form__honeypot" aria-hidden="true" tabindex="-1">
					<span><?php esc_html_e("Laissez ce champ vide", "plaidact-campaign-core"); ?></span>
					<input type="text" name="plaidact_company" autocomplete="off" tabindex="-1" />
				</label>
				<p class="plaidact-newsletter-form__privacy"><?php esc_html_e("En vous inscrivant, vous acceptez de recevoir les actualités de PLAID·ACT. Désinscription possible à tout moment.", "plaidact-campaign-core"); ?></p>
				<button class="plaidact-button plaidact-newsletter-form__button" type="submit"><?php echo esc_html(
        $newsletter_button_label !== "" ? $newsletter_button_label : (string) ($settings["newsletter_button_label"] ??
            __("S’inscrire", "plaidact-campaign-core"))
    ); ?></button>
			</form>
		</div>
		<?php return (string) ob_get_clean();
    }


    /**
     * Echoes the newsletter form for theme templates.
     *
     * Themes can render the extension-managed Brevo form with:
     * do_action('plaidact_newsletter_form', ['class' => 'my-theme-newsletter']);
     *
     * The rendered <form> always includes the stp-newsletter-form class so themes
     * can style their newsletter form while keeping the PLAID·ACT/Brevo submit
     * handler. Additional form classes can be passed with formClass/form_class.
     *
     * @param array<string,mixed> $atts Rendering attributes.
     * @return void
     */
    public static function echo_newsletter_form(array $atts = []): void
    {
        echo self::render_newsletter_form($atts); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function handle_newsletter_submit(): void
    {
        $language = self::get_request_language();

        if (
            !isset($_POST["plaidact_newsletter_nonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash($_POST["plaidact_newsletter_nonce"])
                ),
                "plaidact_newsletter_submit_action"
            )
        ) {
            wp_safe_redirect(self::get_redirect_url($language));
            exit();
        }
        $email = sanitize_email(wp_unslash($_POST["email"] ?? ""));
        $name = sanitize_text_field(wp_unslash($_POST["name"] ?? ""));
        $honeypot = sanitize_text_field(wp_unslash($_POST["plaidact_company"] ?? ""));
        $status = "0";

        if ("" !== $honeypot) {
            self::redirect_with_status("newsletter_subscribed", "1", $language);
        }

        if ($email) {
            $result = self::subscribe_to_brevo_lists($email, $name, $language, false, true);
            $status = is_wp_error($result)
                ? "0"
                : ("double_optin_sent" === $result ? "confirm" : "1");
        }
        self::redirect_with_status("newsletter_subscribed", $status, $language);
    }

    public static function subscribe_to_brevo_lists(
        string $email,
        string $name,
        ?string $language = null,
        bool $include_petition_list = false,
        bool $include_newsletter_lists = true
    ) {
        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        if (!$email) {
            return new \WP_Error(
                "plaidact_invalid_email",
                __("Adresse email invalide.", "plaidact-campaign-core")
            );
        }

        $settings = self::get_settings(true, $language);
        $api_key = (string) ($settings["brevo_api_key"] ?? "");
        $lists = $include_newsletter_lists
            ? array_filter([
                absint($settings["brevo_list_plaidact"] ?? 0),
            ])
            : [];
        if ($include_petition_list) {
            $lists[] = absint($settings["brevo_list_petition"] ?? 0);
        }
        $lists = array_values(
            array_unique(
                array_map(
                    "absint",
                    (array) apply_filters(
                        "plaidact_campaign_brevo_lists",
                        $lists,
                        $language,
                        $settings,
                        $email,
                        $name,
                        $include_petition_list,
                        $include_newsletter_lists
                    )
                )
            )
        );

        if (empty($api_key) || empty($lists)) {
            return new \WP_Error(
                "plaidact_brevo_not_configured",
                __("Brevo n’est pas configuré.", "plaidact-campaign-core")
            );
        }

        $attributes = [];
        if ($name) {
            $attributes["FULLNAME"] = $name;
            $name_parts = preg_split('/\s+/', trim($name), 2);
            if (!empty($name_parts[0])) {
                $attributes["FIRSTNAME"] = $name_parts[0];
            }
            if (!empty($name_parts[1])) {
                $attributes["LASTNAME"] = $name_parts[1];
            }
        }
        if ($language) {
            $attributes["LANGUAGE"] = $language;
        }
        $attributes["SOURCE"] = "plaidact_newsletter";
        $attributes = (array) apply_filters(
            "plaidact_campaign_brevo_attributes",
            $attributes,
            $language,
            $settings,
            $email,
            $name
        );

        $payload = [
            "email" => $email,
            "attributes" => (object) $attributes,
            "listIds" => array_values($lists),
            "updateEnabled" => true,
        ];

        $endpoint = "https://api.brevo.com/v3/contacts";
        if (
            "1" === (string) ($settings["brevo_doi_enabled"] ?? "0") &&
            !empty($settings["brevo_doi_template_id"])
        ) {
            $endpoint =
                "https://api.brevo.com/v3/contacts/doubleOptinConfirmation";
            $payload = [
                "email" => $email,
                "attributes" => (object) $attributes,
                "includeListIds" => array_values($lists),
                "templateId" => absint($settings["brevo_doi_template_id"]),
                "redirectionUrl" => esc_url_raw(
                    (string) ($settings["brevo_redirection_url"] ?:
                    Polylang::home_url($language))
                ),
            ];
        }

        $response = wp_remote_post($endpoint, [
            "timeout" => 15,
            "headers" => [
                "api-key" => $api_key,
                "accept" => "application/json",
                "content-type" => "application/json",
            ],
            "body" => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new \WP_Error(
                "plaidact_brevo_http_error",
                sprintf(
                    "Brevo HTTP %d: %s",
                    $status,
                    wp_remote_retrieve_body($response)
                )
            );
        }

        return "1" === (string) ($settings["brevo_doi_enabled"] ?? "0")
            ? "double_optin_sent"
            : "subscribed";
    }

    public static function render_send_campaign_form(): string
    {
        $language = self::get_current_language();
        $action = esc_url(admin_url("admin-post.php"));
        $settings = self::get_settings(true, $language);
        $target = sanitize_email(
            (string) ($settings["decision_maker_email"] ?? "")
        );
        $name = (string) ($settings["decision_maker_name"] ?? "");

        ob_start();
        ?>
		<div class="plaidact-card plaidact-card--send-mail <?php echo esc_attr(self::get_campaign_design_class($settings)); ?>">
			<h3 class="plaidact-card__title"><?php echo esc_html(
       (string) ($settings["campaign_share_mail_title"] ??
           __("Écrire au décideur", "plaidact-campaign-core"))
   ); ?></h3>
			<p><?php echo esc_html(
       (string) ($settings["send_mail_intro"] ??
           __(
               "Utilisez ce formulaire pour envoyer directement votre message au décideur.",
               "plaidact-campaign-core"
           ))
   ); ?></p>
			<?php if (
       isset($_GET["campaign_sent"]) &&
       "1" === sanitize_text_field(wp_unslash($_GET["campaign_sent"]))
   ): ?>
				<p><strong><?php esc_html_e(
        "Votre message a été envoyé au décideur.",
        "plaidact-campaign-core"
    ); ?></strong></p>
			<?php elseif (
       isset($_GET["campaign_sent"]) &&
       "0" === sanitize_text_field(wp_unslash($_GET["campaign_sent"]))
   ): ?>
				<p><strong><?php esc_html_e(
        "Envoi impossible : vérifiez les informations du formulaire.",
        "plaidact-campaign-core"
    ); ?></strong></p>
			<?php endif; ?>
			<?php if ($name): ?>
				<p><strong><?php echo esc_html(
        sprintf(__("Destinataire : %s", "plaidact-campaign-core"), $name)
    ); ?></strong></p>
			<?php endif; ?>
				<?php if ($target): ?>
					<form method="post" action="<?php echo $action; ?>" class="plaidact-form-grid">
						<input type="hidden" name="action" value="plaidact_send_campaign_mail" />
						<input type="hidden" name="plaidact_language" value="<?php echo esc_attr(
          (string) $language
      ); ?>" />
						<?php wp_nonce_field(
          "plaidact_send_campaign_mail_action",
          "plaidact_send_campaign_mail_nonce"
      ); ?>
						<input type="text" name="sender_name" required placeholder="<?php esc_attr_e(
          "Votre nom",
          "plaidact-campaign-core"
      ); ?>" />
					<input type="email" name="sender_email" required placeholder="<?php esc_attr_e(
         "Votre email",
         "plaidact-campaign-core"
     ); ?>" />
					<textarea name="mail_body" rows="6" required placeholder="<?php echo esc_attr(
         (string) ($settings["decision_mail_placeholder"] ??
             __(
                 'Madame, Monsieur,\n\nJe vous écris pour...',
                 "plaidact-campaign-core"
             ))
     ); ?>"></textarea>
					<button class="plaidact-button" type="submit"><?php echo esc_html(
         (string) ($settings["decision_mail_button_label"] ??
             __("Envoyer au décideur", "plaidact-campaign-core"))
     ); ?></button>
				</form>
			<?php else: ?>
				<p><?php esc_html_e(
        "Le formulaire est indisponible : ajoutez l’email du décideur dans Réglages > PLAID·ACT.",
        "plaidact-campaign-core"
    ); ?></p>
			<?php endif; ?>
		</div>
		<?php return (string) ob_get_clean();
    }

    public static function handle_send_campaign_mail(): void
    {
        $language = self::get_request_language();

        if (
            !isset($_POST["plaidact_send_campaign_mail_nonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash($_POST["plaidact_send_campaign_mail_nonce"])
                ),
                "plaidact_send_campaign_mail_action"
            )
        ) {
            wp_safe_redirect(self::get_redirect_url($language));
            exit();
        }

        $settings = self::get_settings(true, $language);
        $target_email = sanitize_email(
            (string) ($settings["decision_maker_email"] ?? "")
        );
        $sender_email = sanitize_email(
            wp_unslash($_POST["sender_email"] ?? "")
        );
        $sender_name = sanitize_text_field(
            wp_unslash($_POST["sender_name"] ?? "")
        );
        $mail_body = sanitize_textarea_field(
            wp_unslash($_POST["mail_body"] ?? "")
        );

        if (!$target_email || !$sender_email || !$sender_name || !$mail_body) {
            self::redirect_with_status("campaign_sent", "0", $language);
        }

        $subject =
            (string) ($settings["decision_mail_subject"] ??
                __(
                    "Message citoyen depuis PLAID·ACT",
                    "plaidact-campaign-core"
                ));
        $body = sprintf(
            __(
                "Message envoyé depuis le site %1$s\n\nNom: %2$s\nEmail: %3$s\n\n%4$s",
                "plaidact-campaign-core"
            ),
            Polylang::home_url($language),
            $sender_name,
            $sender_email,
            $mail_body
        );

        wp_mail($target_email, $subject, $body, [
            "Reply-To: " . $sender_name . " <" . $sender_email . ">",
        ]);

        self::redirect_with_status("campaign_sent", "1", $language);
    }

    public static function render_social_wall(array $atts = []): string
    {
        $embeds = get_posts([
            "post_type" => "plaid_social_embed",
            "post_status" => "publish",
            "posts_per_page" => 18,
            "orderby" => ["menu_order" => "ASC", "date" => "DESC"],
            "meta_key" => "_plaid_social_enabled",
            "meta_value" => "1",
        ]);

        ob_start();
        ?>
		<div class="plaidact-card plaidact-card--social <?php echo esc_attr(self::get_campaign_design_class($settings)); ?>">
			<?php if (!empty($embeds)): ?>
				<div class="plaidact-social-grid">
					<?php foreach ($embeds as $embed): ?>
						<?php
      $platform = (string) get_post_meta(
          $embed->ID,
          "_plaid_social_platform",
          true
      );
      $code = (string) get_post_meta(
          $embed->ID,
          "_plaid_social_embed_code",
          true
      );
      ?>
						<article class="plaidact-social-card">
							<p class="plaidact-social-card__platform"><?php echo esc_html(
           $platform ?: __("Réseau social", "plaidact-campaign-core")
       ); ?></p>
							<div class="plaidact-social-card__embed"><?php echo wp_kses_post(
           $code
       ); ?></div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else: ?>
				<p><?php esc_html_e(
        'Ajoutez des posts dans "Social wall embeds" puis activez-les pour les afficher ici.',
        "plaidact-campaign-core"
    ); ?></p>
			<?php endif; ?>
		</div>
		<?php return (string) ob_get_clean();
    }
}
