<?php
/**
 * Theme bootstrap.
 *
 * @package PLAIDACT\CampaignTheme
 */

if (!defined("ABSPATH")) {
    exit();
}

require_once get_template_directory() . "/inc/customizer.php";

/**
 * Returns theme mods that should be translatable with Polylang.
 *
 * @return array<string, string>
 */
function plaidact_polylang_theme_mod_defaults(): array
{
    return [
        "hero_title" => "",
        "hero_subtitle" => "",
        "hero_primary_cta_label" => __(
            "Signer la pétition",
            "plaidact-campaign"
        ),
        "hero_primary_cta_url" => "#petition",
        "hero_secondary_cta_label" => __("En savoir plus", "plaidact-campaign"),
        "hero_secondary_cta_url" => "#breves",
        "petition_section_title" => __(
            "Signer la pétition",
            "plaidact-campaign"
        ),
        "petition_section_description" => __(
            "Collectez les signatures, activez l’email transactionnel via votre plugin SMTP WordPress et synchronisez Brevo automatiquement.",
            "plaidact-campaign"
        ),
        "send_mail_section_title" => __(
            "Partager la campagne par email",
            "plaidact-campaign"
        ),
        "send_mail_section_description" => __(
            "Envoyez la campagne à vos proches depuis ce formulaire.",
            "plaidact-campaign"
        ),
    ];
}

/**
 * Registers selected theme strings in Polylang.
 *
 * @return void
 */
function plaidact_register_polylang_theme_strings(): void
{
    if (!function_exists("pll_register_string")) {
        return;
    }

    foreach (plaidact_polylang_theme_mod_defaults() as $key => $default) {
        $value = (string) get_theme_mod($key, $default);

        if ("" === trim($value)) {
            continue;
        }

        pll_register_string(
            "plaidact_theme_" . $key,
            $value,
            "PLAID·ACT Campaign Theme",
            false
        );
    }
}
add_action("init", "plaidact_register_polylang_theme_strings");

/**
 * Reads a theme mod and translates it with Polylang when available.
 *
 * @param string $key Theme mod key.
 * @param string $default Default value.
 * @return string
 */
function plaidact_get_theme_text(string $key, string $default = ""): string
{
    $value = (string) get_theme_mod($key, $default);

    if ("" === $value) {
        return $value;
    }

    if (function_exists("pll_current_language")) {
        $language = pll_current_language("slug");

        if (
            is_string($language) &&
            "" !== $language &&
            function_exists("pll_translate_string")
        ) {
            $translated = pll_translate_string($value, $language);

            if (is_string($translated) && "" !== $translated) {
                return $translated;
            }
        }
    }

    if (function_exists("pll__")) {
        $translated = pll__($value);

        if (is_string($translated) && "" !== $translated) {
            return $translated;
        }
    }

    return $value;
}

/**
 * Theme setup.
 *
 * @return void
 */
function plaidact_campaign_theme_setup(): void
{
    add_theme_support("title-tag");
    add_theme_support("post-thumbnails");
    add_theme_support("editor-styles");
    add_theme_support("wp-block-styles");
    add_theme_support("responsive-embeds");
    add_theme_support("custom-logo");

    register_nav_menus([
        "onepage" => __("One-page anchor menu", "plaidact-campaign"),
    ]);
}
add_action("after_setup_theme", "plaidact_campaign_theme_setup");

/**
 * Enqueues front assets.
 *
 * @return void
 */
function plaidact_campaign_enqueue_assets(): void
{
    wp_enqueue_style(
        "plaidact-campaign-style",
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get("Version")
    );

    $custom_css = ".hero-title { font-family: Inter, sans-serif; }";
    $font_file = get_template_directory() . "/assets/fonts/gotham-noir.woff2";

    if (file_exists($font_file)) {
        $custom_css =
            "
		@font-face {
			font-family: 'Gotham Noir';
			src: url('" .
            esc_url(
                get_template_directory_uri() . "/assets/fonts/gotham-noir.woff2"
            ) .
            "') format('woff2');
			font-display: swap;
		}
		.hero-title { font-family: 'Gotham Noir', Inter, sans-serif; }
		";
    }

    $primary_color = (string) get_theme_mod(
        "campaign_primary_color",
        "#2f6d4b"
    );
    if (!sanitize_hex_color($primary_color)) {
        $primary_color = "#2f6d4b";
    }

    $custom_css .=
        ":root{--plaid-accent:" .
        $primary_color .
        ";--plaid-accent-soft:" .
        $primary_color .
        ";}";

    wp_add_inline_style("plaidact-campaign-style", $custom_css);
}
add_action("wp_enqueue_scripts", "plaidact_campaign_enqueue_assets");

/**
 * Reads a boolean theme mod in a strict way.
 *
 * @param string $key Option key.
 * @param bool   $default Default value.
 * @return bool
 */
function plaidact_is_enabled(string $key, bool $default = true): bool
{
    return (bool) get_theme_mod($key, $default);
}
