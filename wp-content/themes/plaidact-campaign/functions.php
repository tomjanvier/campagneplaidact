<?php
/**
 * Theme bootstrap.
 *
 * @package PLAIDACT\CampaignTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/customizer.php';

/**
 * Theme setup.
 *
 * @return void
 */
function plaidact_campaign_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );

	register_nav_menus(
		array(
			'onepage' => __( 'One-page anchor menu', 'plaidact-campaign' ),
		)
	);
}
add_action( 'after_setup_theme', 'plaidact_campaign_theme_setup' );

/**
 * Enqueues front assets.
 *
 * @return void
 */
function plaidact_campaign_enqueue_assets(): void {
	wp_enqueue_style( 'plaidact-campaign-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );

	$custom_css = "
	@font-face {
		font-family: 'Gotham Noir';
		src: url('" . esc_url( get_template_directory_uri() . '/assets/fonts/gotham-noir.woff2' ) . "') format('woff2');
		font-display: swap;
	}
	.hero-title { font-family: 'Gotham Noir', Inter, sans-serif; }
	";

	wp_add_inline_style( 'plaidact-campaign-style', $custom_css );
}
add_action( 'wp_enqueue_scripts', 'plaidact_campaign_enqueue_assets' );

/**
 * Reads a boolean theme mod in a strict way.
 *
 * @param string $key Option key.
 * @param bool   $default Default value.
 * @return bool
 */
function plaidact_is_enabled( string $key, bool $default = true ): bool {
	return (bool) get_theme_mod( $key, $default );
}
