<?php
/**
 * Plugin Name: PLAID·ACT Campaign Core
 * Description: Noyau mutualisé pour le réseau Multisite PLAID·ACT (campagnes, pétitions, newsletter et shortcodes).
 * Version: 2.0.0
 * Author: PLAID·ACT
 * Network: true
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: plaidact-campaign-core
 */

if (!defined("ABSPATH")) {
    exit();
}

define("PLAIDACT_CORE_VERSION", "2.0.0");
define("PLAIDACT_CORE_PATH", plugin_dir_path(__FILE__));
define("PLAIDACT_CORE_URL", plugin_dir_url(__FILE__));
define(
    "PLAIDACT_CORE_BUNDLED_PETITIONER_PATH",
    PLAIDACT_CORE_PATH . "vendor/petitioner/petitioner.php"
);

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

require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-cpt.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-polylang.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-petition-workflows.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-petitioner-integration.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-shortcodes.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-demo.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-blocks.php";

/**
 * Activates bundled campaign modules.
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
 * Deactivates bundled campaign modules.
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
 * Uninstalls bundled campaign modules.
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
    \Plaidact\CampaignCore\Demo::boot();
}
add_action("plugins_loaded", "plaidact_campaign_core_init");
