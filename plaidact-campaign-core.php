<?php
/**
 * Plugin Name: PLAID·ACT Core
 * Description: Noyau PLAID·ACT (pétitions, newsletters, contenus et shortcodes).
 * Version: 2.1.0
 * Author: PLAID·ACT
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: plaidact-campaign-core
 */

if (!defined("ABSPATH")) {
    exit();
}

define("PLAIDACT_CORE_VERSION", "2.1.0");
define("PLAIDACT_CORE_PATH", plugin_dir_path(__FILE__));
define("PLAIDACT_CORE_URL", plugin_dir_url(__FILE__));
define("PLAIDACT_CORE_BASENAME", plugin_basename(__FILE__));
define(
    "PLAIDACT_CORE_BUNDLED_PETITIONER_PATH",
    PLAIDACT_CORE_PATH . "vendor/petitioner/petitioner.php"
);

/**
 * Returns a cache-busting version for plugin assets.
 *
 * @param string $relative_path Path relative to the plugin root.
 * @return string
 */
function plaidact_campaign_core_asset_version(string $relative_path): string
{
    $asset_path = PLAIDACT_CORE_PATH . ltrim($relative_path, "/");

    if (file_exists($asset_path)) {
        $mtime = filemtime($asset_path);

        if (is_int($mtime) && $mtime > 0) {
            return PLAIDACT_CORE_VERSION . "." . $mtime;
        }
    }

    return PLAIDACT_CORE_VERSION;
}

/**
 * Loads the bundled Petitioner module when it is present.
 *
 * @return void
 */
function plaidact_campaign_core_load_bundled_petitioner(): void
{
    if (
        defined("AV_PETITIONER_PLUGIN_VERSION") ||
        class_exists("AV_Petitioner_Setup", false)
    ) {
        return;
    }

    if (file_exists(PLAIDACT_CORE_BUNDLED_PETITIONER_PATH)) {
        require_once PLAIDACT_CORE_BUNDLED_PETITIONER_PATH;
    }
}

plaidact_campaign_core_load_bundled_petitioner();

/**
 * Loads translations from the plugin languages directory.
 *
 * @return void
 */
function plaidact_campaign_core_load_textdomain(): void
{
    load_plugin_textdomain(
        "plaidact-campaign-core",
        false,
        dirname(PLAIDACT_CORE_BASENAME) . "/languages"
    );
}
add_action("init", "plaidact_campaign_core_load_textdomain", 0);

/**
 * Shows an actionable admin warning when the bundled petition engine is missing.
 *
 * @return void
 */
function plaidact_campaign_core_missing_petitioner_notice(): void
{
    if (
        file_exists(PLAIDACT_CORE_BUNDLED_PETITIONER_PATH) ||
        !current_user_can("activate_plugins")
    ) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__(
        "PLAID·ACT Core est actif, mais le module Petitioner embarqué est absent. Réinstallez le dossier vendor/petitioner pour réactiver les formulaires de pétition.",
        "plaidact-campaign-core"
    );
    echo "</p></div>";
}
add_action("admin_notices", "plaidact_campaign_core_missing_petitioner_notice");

require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-cpt.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-polylang.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-petition-workflows.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-petitioner-integration.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-shortcodes.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-blocks.php";

/**
 * Activates bundled modules.
 *
 * @return void
 */
function plaidact_campaign_core_activate(): void
{
    plaidact_campaign_core_load_bundled_petitioner();

    if (class_exists("AV_Petitioner_Setup")) {
        AV_Petitioner_Setup::plugin_activation();
    }


    flush_rewrite_rules();
}

/**
 * Deactivates bundled modules.
 *
 * @return void
 */
function plaidact_campaign_core_deactivate(): void
{
    if (class_exists("AV_Petitioner_Setup")) {
        AV_Petitioner_Setup::plugin_deactivation();
    }


    flush_rewrite_rules();
}

/**
 * Uninstalls bundled modules.
 *
 * @return void
 */
function plaidact_campaign_core_uninstall(): void
{
    plaidact_campaign_core_load_bundled_petitioner();

    if (class_exists("AV_Petitioner_Setup")) {
        AV_Petitioner_Setup::plugin_uninstall();
    }
}

register_activation_hook(__FILE__, "plaidact_campaign_core_activate");
register_deactivation_hook(__FILE__, "plaidact_campaign_core_deactivate");
register_uninstall_hook(__FILE__, "plaidact_campaign_core_uninstall");

/**
 * Bootstraps plugin modules.
 *
 * @return void
 */
function plaidact_campaign_core_init(): void
{
    \Plaidact\CampaignCore\CPT::boot();
    \Plaidact\CampaignCore\Polylang::boot();
    \Plaidact\CampaignCore\Petitioner_Integration::boot();
    \Plaidact\CampaignCore\Shortcodes::boot();
    \Plaidact\CampaignCore\Blocks::boot();
}
add_action("plugins_loaded", "plaidact_campaign_core_init");
