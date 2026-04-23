<?php
/**
 * Theme customizer options.
 *
 * @package PLAIDACT\CampaignTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes plain text fields.
 *
 * @param string $value Field value.
 * @return string
 */
function plaidact_sanitize_text( string $value ): string {
	return sanitize_text_field( $value );
}

/**
 * Registers campaign Customizer settings.
 *
 * @param WP_Customize_Manager $wp_customize Manager instance.
 * @return void
 */
function plaidact_campaign_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'plaidact_campaign_sections',
		array(
			'title'       => __( 'Sections de campagne', 'plaidact-campaign' ),
			'description' => __( 'Active ou masque les blocs one-page selon la campagne.', 'plaidact-campaign' ),
			'priority'    => 30,
		)
	);

	$toggle_settings = array(
		'enable_petition'   => __( 'Afficher la section Pétition', 'plaidact-campaign' ),
		'enable_socialwall' => __( 'Afficher la section Réseaux sociaux', 'plaidact-campaign' ),
		'enable_articles'   => __( 'Afficher la section Articles', 'plaidact-campaign' ),
	);

	foreach ( $toggle_settings as $setting_key => $label ) {
		$wp_customize->add_setting(
			$setting_key,
			array(
				'default'           => true,
				'sanitize_callback' => 'wp_validate_boolean',
			)
		);

		$wp_customize->add_control(
			$setting_key,
			array(
				'label'   => $label,
				'section' => 'plaidact_campaign_sections',
				'type'    => 'checkbox',
			)
		);
	}

	$wp_customize->add_section(
		'plaidact_campaign_hero',
		array(
			'title'       => __( 'Hero - contenu principal', 'plaidact-campaign' ),
			'description' => __( 'Définit le message principal et le visuel de fond plein écran.', 'plaidact-campaign' ),
			'priority'    => 31,
		)
	);

	$wp_customize->add_setting(
		'hero_title',
		array(
			'default'           => '',
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'hero_title',
		array(
			'label'   => __( 'Titre hero (optionnel)', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_hero',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'hero_subtitle',
		array(
			'default'           => '',
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'hero_subtitle',
		array(
			'label'   => __( 'Sous-titre hero (optionnel)', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_hero',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'hero_background_image',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Image_Control(
			$wp_customize,
			'hero_background_image',
			array(
				'label'   => __( 'Image de fond (fallback)', 'plaidact-campaign' ),
				'section' => 'plaidact_campaign_hero',
			)
		)
	);

	$wp_customize->add_setting(
		'hero_background_video',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	$wp_customize->add_control(
		'hero_background_video',
		array(
			'label'       => __( 'URL vidéo de fond (mp4/webm)', 'plaidact-campaign' ),
			'section'     => 'plaidact_campaign_hero',
			'type'        => 'url',
			'input_attrs' => array(
				'placeholder' => 'https://cdn.plaid-act.org/campaign-hero.mp4',
			),
		)
	);
}
add_action( 'customize_register', 'plaidact_campaign_customize_register' );
