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
		'enable_report_highlight' => __( 'Afficher la section Rapport PDF', 'plaidact-campaign' ),
		'enable_send_campaign' => __( 'Afficher la section Envoi par email', 'plaidact-campaign' ),
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

	$wp_customize->add_setting(
		'campaign_section_order',
		array(
			'default'           => 'petition,breves,articles,rapport,social_wall,send_mail',
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'campaign_section_order',
		array(
			'label'       => __( 'Ordre des sections', 'plaidact-campaign' ),
			'description' => __( 'Séparez par des virgules : petition, breves, articles, rapport, social_wall, send_mail', 'plaidact-campaign' ),
			'section'     => 'plaidact_campaign_sections',
			'type'        => 'text',
		)
	);

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

	$wp_customize->add_setting(
		'hero_primary_cta_label',
		array(
			'default'           => __( 'Signer la pétition', 'plaidact-campaign' ),
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'hero_primary_cta_label',
		array(
			'label'   => __( 'Libellé bouton principal', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_hero',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'hero_primary_cta_url',
		array(
			'default'           => '#petition',
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	$wp_customize->add_control(
		'hero_primary_cta_url',
		array(
			'label'   => __( 'URL bouton principal', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_hero',
			'type'    => 'url',
		)
	);

	$wp_customize->add_setting(
		'hero_secondary_cta_label',
		array(
			'default'           => __( 'En savoir plus', 'plaidact-campaign' ),
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'hero_secondary_cta_label',
		array(
			'label'   => __( 'Libellé lien secondaire', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_hero',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'hero_secondary_cta_url',
		array(
			'default'           => '#breves',
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	$wp_customize->add_control(
		'hero_secondary_cta_url',
		array(
			'label'   => __( 'URL lien secondaire', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_hero',
			'type'    => 'url',
		)
	);

	$wp_customize->add_setting(
		'campaign_primary_color',
		array(
			'default'           => '#2f6d4b',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'campaign_primary_color',
			array(
				'label'   => __( 'Couleur importante de campagne', 'plaidact-campaign' ),
				'section' => 'plaidact_campaign_hero',
			)
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'hero_background_video_media',
			array(
				'label'      => __( 'Vidéo de fond depuis la médiathèque', 'plaidact-campaign' ),
				'section'    => 'plaidact_campaign_hero',
				'mime_type'  => 'video',
				'settings'   => 'hero_background_video',
			)
		)
	);

	$wp_customize->add_section(
		'plaidact_campaign_report',
		array(
			'title'       => __( 'Rapport PDF mis en avant', 'plaidact-campaign' ),
			'description' => __( 'Permet d’afficher une section premium pour un rapport / note téléchargeable.', 'plaidact-campaign' ),
			'priority'    => 32,
		)
	);

	$wp_customize->add_section(
		'plaidact_campaign_texts',
		array(
			'title'       => __( 'Textes des sections', 'plaidact-campaign' ),
			'description' => __( 'Personnalisez les titres et textes de toutes les sections.', 'plaidact-campaign' ),
			'priority'    => 33,
		)
	);

	$text_settings = array(
		'petition_section_title'       => __( 'Signer la pétition', 'plaidact-campaign' ),
		'petition_section_description' => __( 'Collectez les signatures, activez l’email transactionnel via votre plugin SMTP WordPress et synchronisez Brevo automatiquement.', 'plaidact-campaign' ),
		'breves_section_title'         => __( 'Les brèves', 'plaidact-campaign' ),
		'breves_empty_text'            => __( 'Ajoutez des brèves depuis le plugin Campaign Core pour alimenter cette section.', 'plaidact-campaign' ),
		'articles_section_title'       => __( 'Les articles de fond', 'plaidact-campaign' ),
		'articles_empty_text'          => __( 'Aucun article publié pour le moment.', 'plaidact-campaign' ),
		'partners_section_title'       => __( 'Organisations qui portent la campagne', 'plaidact-campaign' ),
		'social_wall_title'            => __( 'Social Wall', 'plaidact-campaign' ),
		'social_wall_description'      => __( 'Sélectionnez vos posts Bluesky, Instagram et autres depuis le back office pour les afficher ici.', 'plaidact-campaign' ),
		'send_mail_section_title'      => __( 'Partager la campagne par email', 'plaidact-campaign' ),
		'send_mail_section_description'=> __( 'Envoyez la campagne à vos proches depuis ce formulaire.', 'plaidact-campaign' ),
		'report_eyebrow'               => __( 'À la une', 'plaidact-campaign' ),
		'report_empty_hint'            => __( 'Ajoutez l’URL du PDF dans Apparence → Personnaliser → Rapport PDF mis en avant.', 'plaidact-campaign' ),
	);

	foreach ( $text_settings as $key => $default ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $default,
				'sanitize_callback' => 'plaidact_sanitize_text',
			)
		);

		$wp_customize->add_control(
			$key,
			array(
				'label'   => ucwords( str_replace( '_', ' ', $key ) ),
				'section' => 'plaidact_campaign_texts',
				'type'    => 'text',
			)
		);
	}

	$wp_customize->add_setting(
		'report_title',
		array(
			'default'           => __( 'Rapport de campagne 2026', 'plaidact-campaign' ),
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'report_title',
		array(
			'label'   => __( 'Titre du rapport', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_report',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'report_excerpt',
		array(
			'default'           => __( 'Consultez notre note stratégique complète en PDF : constats, recommandations et plan d’action.', 'plaidact-campaign' ),
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'report_excerpt',
		array(
			'label'   => __( 'Texte de présentation', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_report',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'report_button_label',
		array(
			'default'           => __( 'Lire le rapport PDF', 'plaidact-campaign' ),
			'sanitize_callback' => 'plaidact_sanitize_text',
		)
	);

	$wp_customize->add_control(
		'report_button_label',
		array(
			'label'   => __( 'Libellé du bouton', 'plaidact-campaign' ),
			'section' => 'plaidact_campaign_report',
			'type'    => 'text',
		)
	);

	$wp_customize->add_setting(
		'report_pdf_url',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);

	$wp_customize->add_control(
		'report_pdf_url',
		array(
			'label'       => __( 'URL du PDF', 'plaidact-campaign' ),
			'section'     => 'plaidact_campaign_report',
			'type'        => 'url',
			'input_attrs' => array(
				'placeholder' => 'https://example.org/rapport.pdf',
			),
		)
	);

}
add_action( 'customize_register', 'plaidact_campaign_customize_register' );
