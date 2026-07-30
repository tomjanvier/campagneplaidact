<?php
/**
 * Polylang integration helpers.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Handles Polylang compatibility for PLAID·ACT tools.
 */
final class Polylang
{
    /**
     * Boots Polylang hooks.
     *
     * @return void
     */
    public static function boot(): void
    {
        add_action("init", [__CLASS__, "register_strings"]);
        add_filter(
            "pll_get_post_types",
            [__CLASS__, "register_translatable_post_types"],
            10,
            2
        );
    }

    /**
     * Registers core settings as translatable strings in Polylang.
     *
     * @return void
     */
    public static function register_strings(): void
    {
        if (!function_exists("pll_register_string")) {
            return;
        }

        $settings = Shortcodes::get_settings(false);

        foreach (Shortcodes::get_translatable_setting_keys() as $key) {
            $value = isset($settings[$key]) ? (string) $settings[$key] : "";

            if ("" === trim($value)) {
                continue;
            }

            pll_register_string(
                "plaidact_campaign_" . $key,
                $value,
                "PLAID·ACT Core",
                str_contains($value, "\n")
            );
        }
    }

    /**
     * Makes PLAID·ACT editorial content translatable in Polylang.
     *
     * @param array $post_types Registered Polylang post types.
     * @param bool  $is_settings Whether Polylang is building the settings screen.
     * @return array
     */
    public static function register_translatable_post_types(
        array $post_types,
        bool $is_settings
    ): array {
        $post_types["petitioner-petition"] = "petitioner-petition";
        $post_types["plaid_breve"] = "plaid_breve";
        $post_types["plaid_newsletter"] = "plaid_newsletter";

        return $post_types;
    }

    /**
     * Backward-compatible alias for integrations using the former callback.
     *
     * @param array $post_types Registered Polylang post types.
     * @param bool  $is_settings Whether Polylang is building the settings screen.
     * @return array
     */
    public static function register_petitioner_post_type(
        array $post_types,
        bool $is_settings
    ): array {
        return self::register_translatable_post_types(
            $post_types,
            $is_settings
        );
    }

    /**
     * Gets the current Polylang language slug when available.
     *
     * @return string|null
     */
    public static function current_language(): ?string
    {
        if (!function_exists("pll_current_language")) {
            return null;
        }

        $language = pll_current_language("slug");

        return is_string($language) && "" !== $language ? $language : null;
    }

    /**
     * Gets the Polylang language slug for a specific post when available.
     *
     * @param int $post_id Post ID.
     * @return string|null
     */
    public static function post_language(
        int $post_id,
        bool $fallback_to_current = true
    ): ?string {
        if ($post_id <= 0 || !function_exists("pll_get_post_language")) {
            return $fallback_to_current ? self::current_language() : null;
        }

        $language = pll_get_post_language($post_id, "slug");

        if (is_string($language) && "" !== $language) {
            return $language;
        }

        return $fallback_to_current ? self::current_language() : null;
    }

    /**
     * Translates a registered string for the current or provided language.
     *
     * @param string      $value Raw string value.
     * @param string|null $language Optional language slug.
     * @return string
     */
    public static function translate_string(
        string $value,
        ?string $language = null
    ): string {
        if ("" === $value) {
            return $value;
        }

        if ($language && function_exists("pll_translate_string")) {
            $translated = pll_translate_string($value, $language);

            if (is_string($translated) && "" !== $translated) {
                return $translated;
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
     * Resolves a translated post ID in the requested language.
     *
     * @param int         $post_id Source post ID.
     * @param string|null $language Optional language slug.
     * @return int
     */
    public static function resolve_post_translation(
        int $post_id,
        ?string $language = null
    ): int {
        if ($post_id <= 0 || !function_exists("pll_get_post")) {
            return $post_id > 0 ? $post_id : 0;
        }

        $language = $language ?: self::current_language();
        if (!$language) {
            return $post_id;
        }

        $translated_id = pll_get_post($post_id, $language);

        if ($translated_id) {
            return (int) $translated_id;
        }

        $post_language = self::post_language($post_id, false);

        if ($post_language && $post_language !== $language) {
            return 0;
        }

        return $post_id;
    }

    /**
     * Returns a language-aware home URL when Polylang exposes one.
     *
     * @param string|null $language Optional language slug.
     * @return string
     */
    public static function home_url(?string $language = null): string
    {
        $language = $language ?: self::current_language();

        if ($language && function_exists("pll_home_url")) {
            $url = pll_home_url($language);

            if (is_string($url) && "" !== $url) {
                return $url;
            }
        }

        return home_url("/");
    }
}
