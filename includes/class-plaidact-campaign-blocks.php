<?php
/**
 * Blocs Gutenberg associés aux shortcodes PLAID·ACT.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistre les blocs Gutenberg dynamiques appuyés sur les shortcodes.
 */
final class Blocks {

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Enregistre les ressources de l'éditeur et les blocs dynamiques.
	 *
	 * @return void
	 */
	public static function register_blocks(): void {
		wp_register_script(
			'plaidact-campaign-blocks',
			PLAIDACT_CORE_URL . 'assets/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n' ),
			plaidact_campaign_core_asset_version( 'assets/blocks.js' ),
			true
		);

		register_block_type(
			'plaidact/newsletter',
			array(
				'api_version'     => 2,
				'editor_script'   => 'plaidact-campaign-blocks',
				'render_callback' => static function ( array $attributes ): string {
					return Shortcodes::render_newsletter_form( $attributes );
				},
				'supports'        => array(
					'className' => true,
				),
				'attributes'      => array(
					'title'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'intro'        => array(
						'type'    => 'string',
						'default' => '',
					),
					'buttonLabel'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'hideName'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'className'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_block_type(
			'plaidact/petition-gauge',
			array(
				'api_version'     => 2,
				'editor_script'   => 'plaidact-campaign-blocks',
				'render_callback' => static function ( array $attributes ): string {
					return Shortcodes::render_petition_gauge( $attributes );
				},
				'attributes'      => array(
					'id'    => array(
						'type'    => 'number',
						'default' => 0,
					),
					'title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'width' => array(
						'type'    => 'number',
						'default' => 34,
					),
					'height' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
			)
		);

		register_block_type(
			'plaidact/partners',
			array(
				'api_version'     => 2,
				'editor_script'   => 'plaidact-campaign-blocks',
				'render_callback' => static function ( array $attributes ): string {
					return Shortcodes::render_partners( $attributes );
				},
				'attributes'      => array(
					'title' => array(
						'type'    => 'string',
						'default' => '',
					),
					'limit' => array(
						'type'    => 'number',
						'default' => -1,
					),
				),
			)
		);
	}
}
