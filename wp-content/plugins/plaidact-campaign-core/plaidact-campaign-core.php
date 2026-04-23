<?php
/**
 * Plugin Name: PLAID·ACT Campaign Core
 * Description: Noyau mutualisé pour le réseau Multisite PLAID·ACT (CPT, taxonomies, métadonnées et shortcodes de campagne).
 * Version: 1.1.0
 * Author: PLAID·ACT
 * Network: true
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: plaidact-campaign-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PLAIDACT_CORE_VERSION', '1.1.0' );
define( 'PLAIDACT_CORE_PATH', plugin_dir_path( __FILE__ ) );

require_once PLAIDACT_CORE_PATH . 'includes/class-plaidact-campaign-cpt.php';
require_once PLAIDACT_CORE_PATH . 'includes/class-plaidact-campaign-shortcodes.php';

/**
 * Bootstraps plugin modules.
 *
 * @return void
 */
function plaidact_campaign_core_init(): void {
	\Plaidact\CampaignCore\CPT::boot();
	\Plaidact\CampaignCore\Shortcodes::boot();
}
add_action( 'plugins_loaded', 'plaidact_campaign_core_init' );
