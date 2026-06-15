<?php
/**
 * Campaign shortcodes for petition and social wall placeholders.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers campaign shortcodes.
 */
final class Shortcodes {

	/**
	 * Hooks WordPress actions.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_shortcode( 'petition_form', array( __CLASS__, 'render_petition_form' ) );
		add_shortcode( 'plaid_social_wall', array( __CLASS__, 'render_social_wall' ) );
		add_shortcode( 'plaid_newsletter_form', array( __CLASS__, 'render_newsletter_form' ) );
		add_shortcode( 'plaid_send_campaign', array( __CLASS__, 'render_send_campaign_form' ) );
		add_action( 'init', array( __CLASS__, 'register_options' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_nopriv_plaidact_petition_submit', array( __CLASS__, 'handle_petition_submit' ) );
		add_action( 'admin_post_plaidact_petition_submit', array( __CLASS__, 'handle_petition_submit' ) );
		add_action( 'admin_post_nopriv_plaidact_newsletter_submit', array( __CLASS__, 'handle_newsletter_submit' ) );
		add_action( 'admin_post_plaidact_newsletter_submit', array( __CLASS__, 'handle_newsletter_submit' ) );
		add_action( 'admin_post_nopriv_plaidact_send_campaign_mail', array( __CLASS__, 'handle_send_campaign_mail' ) );
		add_action( 'admin_post_plaidact_send_campaign_mail', array( __CLASS__, 'handle_send_campaign_mail' ) );
	}

	public static function register_options(): void {
		add_option( 'plaidact_petition_signatures_count', 0 );
		add_option( 'plaidact_petition_signed_emails', array() );
	}

	public static function register_settings_page(): void {
		add_options_page(
			__( 'PLAID·ACT Campagne', 'plaidact-campaign-core' ),
			__( 'PLAID·ACT Campagne', 'plaidact-campaign-core' ),
			'manage_options',
			'plaidact-campaign-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_settings(): void {
		register_setting( 'plaidact_campaign_settings', 'plaidact_campaign_settings', array( __CLASS__, 'sanitize_settings' ) );
	}

	public static function sanitize_settings( array $input ): array {
		return array(
			'petition_goal'             => absint( $input['petition_goal'] ?? 10000 ),
			'notification_email'        => sanitize_email( $input['notification_email'] ?? get_option( 'admin_email' ) ),
			'brevo_api_key'             => sanitize_text_field( (string) ( $input['brevo_api_key'] ?? '' ) ),
			'brevo_list_plaidact'       => absint( $input['brevo_list_plaidact'] ?? 0 ),
			'brevo_list_campaign'       => absint( $input['brevo_list_campaign'] ?? 0 ),
			'petition_intro'            => sanitize_text_field( (string) ( $input['petition_intro'] ?? '' ) ),
			'campaign_share_mail_title' => sanitize_text_field( (string) ( $input['campaign_share_mail_title'] ?? '' ) ),
			'petition_title'            => sanitize_text_field( (string) ( $input['petition_title'] ?? '' ) ),
			'petition_button_label'     => sanitize_text_field( (string) ( $input['petition_button_label'] ?? '' ) ),
			'petition_optin_label'      => sanitize_text_field( (string) ( $input['petition_optin_label'] ?? '' ) ),
			'send_mail_intro'           => sanitize_text_field( (string) ( $input['send_mail_intro'] ?? '' ) ),
			'send_mail_button_label'    => sanitize_text_field( (string) ( $input['send_mail_button_label'] ?? '' ) ),
			'petition_description'      => sanitize_textarea_field( (string) ( $input['petition_description'] ?? '' ) ),
			'decision_maker_name'       => sanitize_text_field( (string) ( $input['decision_maker_name'] ?? '' ) ),
			'decision_maker_email'      => sanitize_email( $input['decision_maker_email'] ?? '' ),
			'decision_mail_subject'     => sanitize_text_field( (string) ( $input['decision_mail_subject'] ?? '' ) ),
			'decision_mail_placeholder' => sanitize_textarea_field( (string) ( $input['decision_mail_placeholder'] ?? '' ) ),
			'decision_mail_button_label'=> sanitize_text_field( (string) ( $input['decision_mail_button_label'] ?? '' ) ),
			'social_share_text'         => sanitize_textarea_field( (string) ( $input['social_share_text'] ?? '' ) ),
			'brevo_doi_enabled'         => ! empty( $input['brevo_doi_enabled'] ) ? '1' : '0',
			'brevo_doi_template_id'     => absint( $input['brevo_doi_template_id'] ?? 0 ),
			'brevo_redirection_url'     => esc_url_raw( (string) ( $input['brevo_redirection_url'] ?? '' ) ),
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = wp_parse_args(
			(array) get_option( 'plaidact_campaign_settings', array() ),
			array(
				'petition_goal'             => 10000,
				'notification_email'        => get_option( 'admin_email' ),
				'brevo_api_key'             => '',
				'brevo_list_plaidact'       => 0,
				'brevo_list_campaign'       => 0,
				'petition_intro'            => __( 'Signez pour soutenir la campagne.', 'plaidact-campaign-core' ),
				'campaign_share_mail_title' => __( 'Découvre cette campagne PLAID·ACT', 'plaidact-campaign-core' ),
				'petition_title'            => __( 'Signer la pétition', 'plaidact-campaign-core' ),
				'petition_button_label'     => __( 'Signer maintenant', 'plaidact-campaign-core' ),
				'petition_optin_label'      => __( 'M’inscrire aux newsletters PLAID·ACT et de cette campagne', 'plaidact-campaign-core' ),
				'send_mail_intro'           => __( 'Partagez la campagne à votre réseau en un clic.', 'plaidact-campaign-core' ),
				'send_mail_button_label'    => __( 'Envoyer le message', 'plaidact-campaign-core' ),
				'petition_description'      => __( 'Expliquez ici les objectifs et revendications de la pétition.', 'plaidact-campaign-core' ),
				'decision_maker_name'       => '',
				'decision_maker_email'      => '',
				'decision_mail_subject'     => __( 'Message citoyen depuis la campagne PLAID·ACT', 'plaidact-campaign-core' ),
				'decision_mail_placeholder' => __( 'Madame, Monsieur,

Je vous écris pour...', 'plaidact-campaign-core' ),
				'decision_mail_button_label'=> __( 'Envoyer au décideur', 'plaidact-campaign-core' ),
				'social_share_text'         => __( 'Je soutiens cette campagne citoyenne. Rejoignez-nous !', 'plaidact-campaign-core' ),
				'brevo_doi_enabled'         => '0',
				'brevo_doi_template_id'     => 0,
				'brevo_redirection_url'     => '',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Réglages campagne', 'plaidact-campaign-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'plaidact_campaign_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Objectif signatures', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[petition_goal]" type="number" value="<?php echo esc_attr( (string) $settings['petition_goal'] ); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Email notification', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[notification_email]" type="email" value="<?php echo esc_attr( (string) $settings['notification_email'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Brevo API key', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[brevo_api_key]" type="text" value="<?php echo esc_attr( (string) $settings['brevo_api_key'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'ID liste newsletter PLAID·ACT', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[brevo_list_plaidact]" type="number" value="<?php echo esc_attr( (string) $settings['brevo_list_plaidact'] ); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'ID liste newsletter campagne', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[brevo_list_campaign]" type="number" value="<?php echo esc_attr( (string) $settings['brevo_list_campaign'] ); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Double opt-in Brevo', 'plaidact-campaign-core' ); ?></th><td><label><input name="plaidact_campaign_settings[brevo_doi_enabled]" type="checkbox" value="1" <?php checked( (string) $settings['brevo_doi_enabled'], '1' ); ?> /> <?php esc_html_e( 'Utiliser /contacts/doubleOptinConfirmation au lieu de créer directement le contact.', 'plaidact-campaign-core' ); ?></label></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'ID template double opt-in', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[brevo_doi_template_id]" type="number" value="<?php echo esc_attr( (string) $settings['brevo_doi_template_id'] ); ?>" class="small-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'URL de retour double opt-in', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[brevo_redirection_url]" type="url" value="<?php echo esc_attr( (string) $settings['brevo_redirection_url'] ); ?>" class="regular-text" placeholder="https://example.org/merci" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Titre bloc pétition', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[petition_title]" type="text" value="<?php echo esc_attr( (string) $settings['petition_title'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Texte intro pétition', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[petition_intro]" type="text" value="<?php echo esc_attr( (string) $settings['petition_intro'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Libellé bouton pétition', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[petition_button_label]" type="text" value="<?php echo esc_attr( (string) $settings['petition_button_label'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Texte consentement newsletter', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[petition_optin_label]" type="text" value="<?php echo esc_attr( (string) $settings['petition_optin_label'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Titre email de partage', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[campaign_share_mail_title]" type="text" value="<?php echo esc_attr( (string) $settings['campaign_share_mail_title'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Texte bloc partage email', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[send_mail_intro]" type="text" value="<?php echo esc_attr( (string) $settings['send_mail_intro'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Description pétition', 'plaidact-campaign-core' ); ?></th><td><textarea name="plaidact_campaign_settings[petition_description]" class="large-text" rows="4"><?php echo esc_textarea( (string) $settings['petition_description'] ); ?></textarea></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Nom du décideur', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[decision_maker_name]" type="text" value="<?php echo esc_attr( (string) $settings['decision_maker_name'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Email du décideur', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[decision_maker_email]" type="email" value="<?php echo esc_attr( (string) $settings['decision_maker_email'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Sujet email décideur', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[decision_mail_subject]" type="text" value="<?php echo esc_attr( (string) $settings['decision_mail_subject'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Texte pré-rempli email décideur', 'plaidact-campaign-core' ); ?></th><td><textarea name="plaidact_campaign_settings[decision_mail_placeholder]" class="large-text" rows="5"><?php echo esc_textarea( (string) $settings['decision_mail_placeholder'] ); ?></textarea></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Texte par défaut pour les partages sociaux', 'plaidact-campaign-core' ); ?></th><td><textarea name="plaidact_campaign_settings[social_share_text]" class="large-text" rows="4"><?php echo esc_textarea( (string) $settings['social_share_text'] ); ?></textarea></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Libellé bouton partage email', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[send_mail_button_label]" type="text" value="<?php echo esc_attr( (string) $settings['send_mail_button_label'] ); ?>" class="regular-text" /></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Libellé bouton décideur', 'plaidact-campaign-core' ); ?></th><td><input name="plaidact_campaign_settings[decision_mail_button_label]" type="text" value="<?php echo esc_attr( (string) $settings['decision_mail_button_label'] ); ?>" class="regular-text" /></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function render_petition_form( array $atts = array() ): string {
		$settings = (array) get_option( 'plaidact_campaign_settings', array() );
		$count    = (int) get_option( 'plaidact_petition_signatures_count', 0 );
		$goal     = max( 1, (int) ( $settings['petition_goal'] ?? 10000 ) );
		$progress = min( 100, (int) round( ( $count / $goal ) * 100 ) );
		$action   = esc_url( admin_url( 'admin-post.php' ) );

		ob_start();
		?>
		<div class="plaidact-card plaidact-card--petition">
			<h3 class="plaidact-card__title"><?php echo esc_html( (string) ( $settings['petition_title'] ?? __( 'Signer la pétition', 'plaidact-campaign-core' ) ) ); ?></h3>
			<p><?php echo esc_html( (string) ( $settings['petition_intro'] ?? __( 'Signez la pétition. Chaque signature fait monter le compteur en direct.', 'plaidact-campaign-core' ) ) ); ?></p>
			<p><?php echo nl2br( esc_html( (string) ( $settings['petition_description'] ?? __( 'Expliquez ici les objectifs et revendications de la pétition.', 'plaidact-campaign-core' ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php if ( isset( $_GET['petition_signed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['petition_signed'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Merci, votre signature a bien été enregistrée.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php elseif ( isset( $_GET['petition_signed'] ) && 'already' === sanitize_text_field( wp_unslash( $_GET['petition_signed'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Cette adresse email a déjà signé la pétition.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php elseif ( isset( $_GET['petition_signed'] ) && '0' === sanitize_text_field( wp_unslash( $_GET['petition_signed'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Signature impossible pour le moment. Réessayez plus tard.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php endif; ?>
			<p class="plaidact-petition__count"><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong> / <?php echo esc_html( number_format_i18n( $goal ) ); ?></p>
			<div class="plaidact-progress"><span style="width:<?php echo esc_attr( (string) $progress ); ?>%;"></span></div>
			<form method="post" action="<?php echo $action; ?>" class="plaidact-form-grid">
				<input type="hidden" name="action" value="plaidact_petition_submit" />
				<?php wp_nonce_field( 'plaidact_petition_submit_action', 'plaidact_petition_nonce' ); ?>
				<input type="text" name="full_name" required autocomplete="name" placeholder="<?php esc_attr_e( 'Nom complet', 'plaidact-campaign-core' ); ?>" />
				<input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true" />
				<input type="email" name="email" required autocomplete="email" placeholder="<?php esc_attr_e( 'Adresse email', 'plaidact-campaign-core' ); ?>" />
				<label><input type="checkbox" name="newsletter_optin" value="1" checked /> <?php echo esc_html( (string) ( $settings['petition_optin_label'] ?? __( 'M’inscrire aux newsletters PLAID·ACT et de cette campagne', 'plaidact-campaign-core' ) ) ); ?></label>
				<button class="plaidact-button" type="submit"><?php echo esc_html( (string) ( $settings['petition_button_label'] ?? __( 'Signer maintenant', 'plaidact-campaign-core' ) ) ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_petition_submit(): void {
		if ( ! isset( $_POST['plaidact_petition_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaidact_petition_nonce'] ) ), 'plaidact_petition_submit_action' ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}

		if ( ! empty( $_POST['website'] ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		if ( empty( $email ) || empty( $name ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}

		$email_key     = md5( strtolower( $email ) );
		$signed_emails = (array) get_option( 'plaidact_petition_signed_emails', array() );
		$existing      = get_posts(
			array(
				'post_type'      => 'plaid_signature',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_plaid_signature_email',
				'meta_value'     => $email,
			)
		);
		if ( isset( $signed_emails[ $email_key ] ) || ! empty( $existing ) ) {
			wp_safe_redirect( add_query_arg( 'petition_signed', 'already', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}

		$signature_id = wp_insert_post(
			array(
				'post_type'    => 'plaid_signature',
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => sprintf( __( 'Signature de %1$s (%2$s) sur %3$s.', 'plaidact-campaign-core' ), $name, $email, wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
			)
		);

		if ( ! is_wp_error( $signature_id ) && $signature_id > 0 ) {
			$count = (int) get_option( 'plaidact_petition_signatures_count', 0 );
			update_option( 'plaidact_petition_signatures_count', $count + 1 );
			$signed_emails[ $email_key ] = time();
			update_option( 'plaidact_petition_signed_emails', $signed_emails, false );

			update_post_meta( $signature_id, '_plaid_signature_full_name', $name );
			update_post_meta( $signature_id, '_plaid_signature_email', $email );
			update_post_meta( $signature_id, '_plaid_signature_optin', isset( $_POST['newsletter_optin'] ) ? '1' : '0' );
			update_post_meta( $signature_id, '_plaid_signature_signed_at', current_time( 'mysql' ) );
			update_post_meta( $signature_id, '_plaid_signature_ip_hash', wp_hash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		} else {
			wp_safe_redirect( add_query_arg( 'petition_signed', '0', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}

		$settings = (array) get_option( 'plaidact_campaign_settings', array() );
		if ( ! empty( $settings['notification_email'] ) ) {
			wp_mail(
				sanitize_email( (string) $settings['notification_email'] ),
				__( 'Nouvelle signature pétition', 'plaidact-campaign-core' ),
				sprintf( "Nom: %s\nEmail: %s\nCampagne: %s", $name, $email, home_url( '/' ) )
			);
		}

		if ( isset( $_POST['newsletter_optin'] ) ) {
			$brevo_result = self::subscribe_to_brevo_lists( $email, $name );
			if ( ! is_wp_error( $brevo_result ) && ! is_wp_error( $signature_id ) && $signature_id > 0 ) {
				update_post_meta( $signature_id, '_plaid_signature_brevo_status', $brevo_result );
			}
		}

		wp_safe_redirect( add_query_arg( 'petition_signed', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	public static function render_newsletter_form(): string {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		ob_start();
		?>
		<div class="plaidact-card plaidact-card--newsletter" id="newsletter">
			<h3><?php esc_html_e( 'Newsletter', 'plaidact-campaign-core' ); ?></h3>
			<p><?php esc_html_e( 'Inscription aux newsletters PLAID·ACT + campagne via Brevo.', 'plaidact-campaign-core' ); ?></p>
			<?php if ( isset( $_GET['newsletter_subscribed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['newsletter_subscribed'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Merci, votre inscription a bien été prise en compte.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php elseif ( isset( $_GET['newsletter_subscribed'] ) && '0' === sanitize_text_field( wp_unslash( $_GET['newsletter_subscribed'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Inscription impossible pour le moment. Réessayez plus tard.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php endif; ?>
			<form method="post" action="<?php echo $action; ?>" style="display:grid;gap:.6rem;grid-template-columns:1fr auto;">
				<input type="hidden" name="action" value="plaidact_newsletter_submit" />
				<?php wp_nonce_field( 'plaidact_newsletter_submit_action', 'plaidact_newsletter_nonce' ); ?>
				<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Votre email', 'plaidact-campaign-core' ); ?>" />
				<button class="plaidact-button" type="submit"><?php esc_html_e( 'S’inscrire', 'plaidact-campaign-core' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_newsletter_submit(): void {
		if ( ! isset( $_POST['plaidact_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaidact_newsletter_nonce'] ) ), 'plaidact_newsletter_submit_action' ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$status = '0';
		if ( $email ) {
			$status = is_wp_error( self::subscribe_to_brevo_lists( $email, '' ) ) ? '0' : '1';
		}
		wp_safe_redirect( add_query_arg( 'newsletter_subscribed', $status, wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	private static function subscribe_to_brevo_lists( string $email, string $name ) {
		$settings = (array) get_option( 'plaidact_campaign_settings', array() );
		$api_key  = (string) ( $settings['brevo_api_key'] ?? '' );
		$lists    = array_filter(
			array(
				absint( $settings['brevo_list_plaidact'] ?? 0 ),
				absint( $settings['brevo_list_campaign'] ?? 0 ),
			)
		);

		if ( empty( $api_key ) || empty( $lists ) ) {
			return new \WP_Error( 'plaidact_brevo_not_configured', __( 'Brevo n’est pas configuré.', 'plaidact-campaign-core' ) );
		}

		$attributes = array();
		if ( $name ) {
			$attributes['FULLNAME'] = $name;
		}

		$payload = array(
			'email'         => $email,
			'attributes'    => (object) $attributes,
			'listIds'       => array_values( $lists ),
			'updateEnabled' => true,
		);

		$endpoint = 'https://api.brevo.com/v3/contacts';
		if ( '1' === (string) ( $settings['brevo_doi_enabled'] ?? '0' ) && ! empty( $settings['brevo_doi_template_id'] ) ) {
			$endpoint = 'https://api.brevo.com/v3/contacts/doubleOptinConfirmation';
			$payload  = array(
				'email'          => $email,
				'attributes'     => (object) $attributes,
				'includeListIds' => array_values( $lists ),
				'templateId'     => absint( $settings['brevo_doi_template_id'] ),
				'redirectionUrl' => esc_url_raw( (string) ( $settings['brevo_redirection_url'] ?: home_url( '/' ) ) ),
			);
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'api-key'      => $api_key,
					'accept'       => 'application/json',
					'content-type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return new \WP_Error( 'plaidact_brevo_http_error', sprintf( 'Brevo HTTP %d: %s', $status, wp_remote_retrieve_body( $response ) ) );
		}

		return '1' === (string) ( $settings['brevo_doi_enabled'] ?? '0' ) ? 'double_optin_sent' : 'subscribed';
	}

	public static function render_send_campaign_form(): string {
		$action   = esc_url( admin_url( 'admin-post.php' ) );
		$settings = (array) get_option( 'plaidact_campaign_settings', array() );
		$target   = sanitize_email( (string) ( $settings['decision_maker_email'] ?? '' ) );
		$name     = (string) ( $settings['decision_maker_name'] ?? '' );

		ob_start();
		?>
		<div class="plaidact-card plaidact-card--send-mail">
			<h3 class="plaidact-card__title"><?php echo esc_html( (string) ( $settings['campaign_share_mail_title'] ?? __( 'Écrire au décideur', 'plaidact-campaign-core' ) ) ); ?></h3>
			<p><?php echo esc_html( (string) ( $settings['send_mail_intro'] ?? __( 'Utilisez ce formulaire pour envoyer directement votre message au décideur.', 'plaidact-campaign-core' ) ) ); ?></p>
			<?php if ( isset( $_GET['campaign_sent'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['campaign_sent'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Votre message a été envoyé au décideur.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php elseif ( isset( $_GET['campaign_sent'] ) && '0' === sanitize_text_field( wp_unslash( $_GET['campaign_sent'] ) ) ) : ?>
				<p><strong><?php esc_html_e( 'Envoi impossible : vérifiez les informations du formulaire.', 'plaidact-campaign-core' ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $name ) : ?>
				<p><strong><?php echo esc_html( sprintf( __( 'Destinataire : %s', 'plaidact-campaign-core' ), $name ) ); ?></strong></p>
			<?php endif; ?>
			<?php if ( $target ) : ?>
				<form method="post" action="<?php echo $action; ?>" class="plaidact-form-grid">
					<input type="hidden" name="action" value="plaidact_send_campaign_mail" />
					<?php wp_nonce_field( 'plaidact_send_campaign_mail_action', 'plaidact_send_campaign_mail_nonce' ); ?>
					<input type="text" name="sender_name" required placeholder="<?php esc_attr_e( 'Votre nom', 'plaidact-campaign-core' ); ?>" />
					<input type="email" name="sender_email" required placeholder="<?php esc_attr_e( 'Votre email', 'plaidact-campaign-core' ); ?>" />
					<textarea name="mail_body" rows="6" required placeholder="<?php echo esc_attr( (string) ( $settings['decision_mail_placeholder'] ?? __( 'Madame, Monsieur,\n\nJe vous écris pour...', 'plaidact-campaign-core' ) ) ); ?>"></textarea>
					<button class="plaidact-button" type="submit"><?php echo esc_html( (string) ( $settings['decision_mail_button_label'] ?? __( 'Envoyer au décideur', 'plaidact-campaign-core' ) ) ); ?></button>
				</form>
			<?php else : ?>
				<p><?php esc_html_e( 'Le formulaire est indisponible : ajoutez l’email du décideur dans Réglages > PLAID·ACT Campagne.', 'plaidact-campaign-core' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_send_campaign_mail(): void {
		if ( ! isset( $_POST['plaidact_send_campaign_mail_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaidact_send_campaign_mail_nonce'] ) ), 'plaidact_send_campaign_mail_action' ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}

		$settings     = (array) get_option( 'plaidact_campaign_settings', array() );
		$target_email = sanitize_email( (string) ( $settings['decision_maker_email'] ?? '' ) );
		$sender_email = sanitize_email( wp_unslash( $_POST['sender_email'] ?? '' ) );
		$sender_name  = sanitize_text_field( wp_unslash( $_POST['sender_name'] ?? '' ) );
		$mail_body    = sanitize_textarea_field( wp_unslash( $_POST['mail_body'] ?? '' ) );

		if ( ! $target_email || ! $sender_email || ! $sender_name || ! $mail_body ) {
			wp_safe_redirect( add_query_arg( 'campaign_sent', '0', wp_get_referer() ?: home_url( '/' ) ) );
			exit;
		}

		$subject = (string) ( $settings['decision_mail_subject'] ?? __( 'Message citoyen depuis la campagne PLAID·ACT', 'plaidact-campaign-core' ) );
		$body    = sprintf(
			__( "Message envoyé depuis le site %1$s\n\nNom: %2$s\nEmail: %3$s\n\n%4$s", 'plaidact-campaign-core' ),
			home_url( '/' ),
			$sender_name,
			$sender_email,
			$mail_body
		);

		wp_mail(
			$target_email,
			$subject,
			$body,
			array( 'Reply-To: ' . $sender_name . ' <' . $sender_email . '>' )
		);

		wp_safe_redirect( add_query_arg( 'campaign_sent', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	public static function render_social_wall( array $atts = array() ): string {
		$embeds = get_posts(
			array(
				'post_type'      => 'plaid_social_embed',
				'post_status'    => 'publish',
				'posts_per_page' => 18,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
				'meta_key'       => '_plaid_social_enabled',
				'meta_value'     => '1',
			)
		);

		ob_start();
		?>
		<div class="plaidact-card plaidact-card--social">
			<?php if ( ! empty( $embeds ) ) : ?>
				<div class="plaidact-social-grid">
					<?php foreach ( $embeds as $embed ) : ?>
						<?php
						$platform = (string) get_post_meta( $embed->ID, '_plaid_social_platform', true );
						$code     = (string) get_post_meta( $embed->ID, '_plaid_social_embed_code', true );
						?>
						<article class="plaidact-social-card">
							<p class="plaidact-social-card__platform"><?php echo esc_html( $platform ?: __( 'Réseau social', 'plaidact-campaign-core' ) ); ?></p>
							<div class="plaidact-social-card__embed"><?php echo wp_kses_post( $code ); ?></div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Ajoutez des posts dans "Social wall embeds" puis activez-les pour les afficher ici.', 'plaidact-campaign-core' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
