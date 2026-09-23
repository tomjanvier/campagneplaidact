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

		register_block_type(
			'plaidact/breves',
			array(
				'api_version'     => 2,
				'editor_script'   => 'plaidact-campaign-blocks',
				'render_callback' => static function ( array $attributes ): string {
					// Respect du toggle module comme pour le shortcode.
					if ( ! Shortcodes::is_module_enabled( 'enable_breves' ) ) {
						return '';
					}
					return Shortcodes::render_breves(
						array(
							'title'       => isset( $attributes['title'] ) ? (string) $attributes['title'] : '',
							'description' => isset( $attributes['description'] ) ? (string) $attributes['description'] : '',
							'limit'       => isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 8,
							'topic'       => isset( $attributes['topic'] ) ? (string) $attributes['topic'] : '',
							'layout'      => isset( $attributes['layout'] ) ? (string) $attributes['layout'] : 'scroll',
						)
					);
				},
				'attributes'      => array(
					'title'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'description' => array(
						'type'    => 'string',
						'default' => '',
					),
					'limit'       => array(
						'type'    => 'number',
						'default' => 8,
					),
					'topic'       => array(
						'type'    => 'string',
						'default' => '',
					),
					'layout'      => array(
						'type'    => 'string',
						'default' => 'scroll',
					),
				),
				'supports'        => array(
					'className' => true,
					'anchor'    => true,
				),
			)
		);
	}
}
