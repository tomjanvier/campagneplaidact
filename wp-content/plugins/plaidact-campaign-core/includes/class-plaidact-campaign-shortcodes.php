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
			<p><?php esc_html_e( 'Signez la pétition. Chaque signature fait monter le compteur en direct.', 'plaidact-campaign-core' ); ?></p>
			<p><strong><?php echo esc_html( number_format_i18n( $count ) ); ?></strong> / <?php echo esc_html( number_format_i18n( $goal ) ); ?></p>
			<div style="height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden"><span style="display:block;height:100%;width:<?php echo esc_attr( (string) $progress ); ?>%;background:var(--plaid-accent,#2f6d4b);"></span></div>
			<form method="post" action="<?php echo $action; ?>" style="margin-top:1rem;display:grid;gap:.6rem;">
				<input type="hidden" name="action" value="plaidact_petition_submit" />
				<?php wp_nonce_field( 'plaidact_petition_submit_action', 'plaidact_petition_nonce' ); ?>
				<input type="text" name="full_name" required placeholder="<?php esc_attr_e( 'Nom complet', 'plaidact-campaign-core' ); ?>" />
				<input type="email" name="email" required placeholder="<?php esc_attr_e( 'Adresse email', 'plaidact-campaign-core' ); ?>" />
				<label><input type="checkbox" name="newsletter_optin" value="1" checked /> <?php esc_html_e( 'M’inscrire aux newsletters PLAID·ACT et de cette campagne', 'plaidact-campaign-core' ); ?></label>
				<button class="plaidact-button" type="submit"><?php esc_html_e( 'Signer maintenant', 'plaidact-campaign-core' ); ?></button>
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

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		if ( empty( $email ) || empty( $name ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}

		$count = (int) get_option( 'plaidact_petition_signatures_count', 0 );
		update_option( 'plaidact_petition_signatures_count', $count + 1 );

		wp_insert_post(
			array(
				'post_type'   => 'plaid_signature',
				'post_status' => 'publish',
				'post_title'  => $name . ' <' . $email . '>',
			)
		);

		$settings = (array) get_option( 'plaidact_campaign_settings', array() );
		if ( ! empty( $settings['notification_email'] ) ) {
			wp_mail(
				sanitize_email( (string) $settings['notification_email'] ),
				__( 'Nouvelle signature pétition', 'plaidact-campaign-core' ),
				sprintf( "Nom: %s\nEmail: %s\nCampagne: %s", $name, $email, home_url( '/' ) )
			);
		}

		if ( isset( $_POST['newsletter_optin'] ) ) {
			self::subscribe_to_brevo_lists( $email, $name );
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
			<p><?php esc_html_e( 'Inscription automatique aux newsletters PLAID·ACT + campagne (Brevo).', 'plaidact-campaign-core' ); ?></p>
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
		if ( $email ) {
			self::subscribe_to_brevo_lists( $email, '' );
		}
		wp_safe_redirect( add_query_arg( 'newsletter_subscribed', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	private static function subscribe_to_brevo_lists( string $email, string $name ): void {
		$settings = (array) get_option( 'plaidact_campaign_settings', array() );
		$api_key  = (string) ( $settings['brevo_api_key'] ?? '' );
		$lists    = array_filter(
			array(
				absint( $settings['brevo_list_plaidact'] ?? 0 ),
				absint( $settings['brevo_list_campaign'] ?? 0 ),
			)
		);

		if ( empty( $api_key ) || empty( $lists ) ) {
			return;
		}

		$attributes = array();
		if ( $name ) {
			$attributes['FULLNAME'] = $name;
		}

		wp_remote_post(
			'https://api.brevo.com/v3/contacts',
			array(
				'timeout' => 15,
				'headers' => array(
					'api-key'      => $api_key,
					'accept'       => 'application/json',
					'content-type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'email'         => $email,
						'attributes'    => $attributes,
						'listIds'       => array_values( $lists ),
						'updateEnabled' => true,
					)
				),
			)
		);
	}

	public static function render_send_campaign_form(): string {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		ob_start();
		?>
		<div class="plaidact-card plaidact-card--send-mail">
			<form method="post" action="<?php echo $action; ?>" style="display:flex;gap:.6rem;flex-wrap:wrap;">
				<input type="hidden" name="action" value="plaidact_send_campaign_mail" />
				<?php wp_nonce_field( 'plaidact_send_campaign_mail_action', 'plaidact_send_campaign_mail_nonce' ); ?>
				<input type="email" name="to_email" required placeholder="<?php esc_attr_e( 'Email du destinataire', 'plaidact-campaign-core' ); ?>" />
				<button class="plaidact-button" type="submit"><?php esc_html_e( 'Envoyer la campagne', 'plaidact-campaign-core' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_send_campaign_mail(): void {
		if ( ! isset( $_POST['plaidact_send_campaign_mail_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaidact_send_campaign_mail_nonce'] ) ), 'plaidact_send_campaign_mail_action' ) ) {
			wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
			exit;
		}

		$to = sanitize_email( wp_unslash( $_POST['to_email'] ?? '' ) );
		if ( $to ) {
			$subject = sprintf( __( 'Campagne à découvrir : %s', 'plaidact-campaign-core' ), get_bloginfo( 'name' ) );
			$body    = sprintf( __( "Bonjour,\n\nJe te partage cette campagne : %s\n\nÀ bientôt.", 'plaidact-campaign-core' ), home_url( '/' ) );
			wp_mail( $to, $subject, $body );
		}

		wp_safe_redirect( add_query_arg( 'campaign_sent', '1', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	public static function render_social_wall( array $atts = array() ): string {
		$attributes = shortcode_atts(
			array(
				'sources' => 'Instagram, Bluesky, YouTube',
			),
			$atts,
			'plaid_social_wall'
		);

		$sources = array_filter( array_map( 'trim', explode( ',', (string) $attributes['sources'] ) ) );

		ob_start();
		?>
		<div class="plaidact-card plaidact-card--social">
			<p><?php esc_html_e( 'Social wall connecté prochainement. Sources prévues :', 'plaidact-campaign-core' ); ?></p>
			<ul>
				<?php foreach ( $sources as $source ) : ?>
					<li><?php echo esc_html( $source ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
