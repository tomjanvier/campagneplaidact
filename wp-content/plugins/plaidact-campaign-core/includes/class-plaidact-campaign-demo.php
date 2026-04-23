<?php
/**
 * Demo pack import/export tooling.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a simple ZIP-based demo import/export utility.
 */
final class Demo {

	/**
	 * Hooks actions.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_plaidact_export_demo_zip', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_plaidact_import_demo_zip', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Registers tools page.
	 *
	 * @return void
	 */
	public static function register_page(): void {
		add_management_page(
			__( 'PLAID·ACT Démo', 'plaidact-campaign-core' ),
			__( 'PLAID·ACT Démo', 'plaidact-campaign-core' ),
			'manage_options',
			'plaidact-demo-tools',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Renders admin UI.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$notice = isset( $_GET['plaidact_demo_notice'] ) ? sanitize_key( wp_unslash( $_GET['plaidact_demo_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pack démo PLAID·ACT', 'plaidact-campaign-core' ); ?></h1>
			<p><?php esc_html_e( 'Exportez un ZIP prêt à être partagé, puis importez-le sur un autre site en quelques clics.', 'plaidact-campaign-core' ); ?></p>
			<?php if ( 'imported' === $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Démo importée avec succès.', 'plaidact-campaign-core' ); ?></p></div>
			<?php elseif ( 'error' === $notice ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Import impossible. Vérifiez que le ZIP contient bien plaidact-demo.json.', 'plaidact-campaign-core' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1.5rem;">
				<input type="hidden" name="action" value="plaidact_export_demo_zip" />
				<?php wp_nonce_field( 'plaidact_export_demo_zip_action', 'plaidact_export_demo_zip_nonce' ); ?>
				<?php submit_button( __( 'Télécharger le ZIP de démo', 'plaidact-campaign-core' ), 'primary', 'submit', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="plaidact_import_demo_zip" />
				<?php wp_nonce_field( 'plaidact_import_demo_zip_action', 'plaidact_import_demo_zip_nonce' ); ?>
				<input type="file" name="plaidact_demo_zip" accept=".zip" required />
				<?php submit_button( __( 'Importer un ZIP de démo', 'plaidact-campaign-core' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Exports zip.
	 *
	 * @return void
	 */
	public static function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}

		check_admin_referer( 'plaidact_export_demo_zip_action', 'plaidact_export_demo_zip_nonce' );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'L’extension PHP ZipArchive est requise.', 'plaidact-campaign-core' ) );
		}

		$tmp_file = wp_tempnam( 'plaidact-demo-export.zip' );
		if ( ! $tmp_file ) {
			wp_die( esc_html__( 'Impossible de créer un fichier temporaire.', 'plaidact-campaign-core' ) );
		}

		$zip = new \ZipArchive();
		$ok  = $zip->open( $tmp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		if ( true !== $ok ) {
			wp_die( esc_html__( 'Impossible de créer le ZIP.', 'plaidact-campaign-core' ) );
		}

		$zip->addFromString( 'plaidact-demo.json', wp_json_encode( self::get_demo_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ?: '{}' );
		$zip->addFromString( 'README.txt', "PLAID·ACT demo\n1) Activez le thème plaidact-campaign\n2) Outils > PLAID·ACT Démo > Importer un ZIP\n" );
		$zip->close();

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename=plaidact-campaign-demo.zip' );
		header( 'Content-Length: ' . (string) filesize( $tmp_file ) );

		readfile( $tmp_file );
		@unlink( $tmp_file );
		exit;
	}

	/**
	 * Imports zip.
	 *
	 * @return void
	 */
	public static function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}
		check_admin_referer( 'plaidact_import_demo_zip_action', 'plaidact_import_demo_zip_nonce' );

		if ( empty( $_FILES['plaidact_demo_zip']['tmp_name'] ) || ! class_exists( 'ZipArchive' ) ) {
			self::redirect_notice( 'error' );
		}

		$tmp_name = (string) $_FILES['plaidact_demo_zip']['tmp_name'];
		$zip      = new \ZipArchive();

		if ( true !== $zip->open( $tmp_name ) ) {
			self::redirect_notice( 'error' );
		}

		$json = $zip->getFromName( 'plaidact-demo.json' );
		$zip->close();

		if ( ! is_string( $json ) || '' === $json ) {
			self::redirect_notice( 'error' );
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			self::redirect_notice( 'error' );
		}

		self::import_payload( $payload );
		self::redirect_notice( 'imported' );
	}

	/**
	 * Redirects to notice.
	 *
	 * @param string $notice Notice key.
	 * @return never
	 */
	private static function redirect_notice( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				'plaidact_demo_notice',
				$notice,
				admin_url( 'tools.php?page=plaidact-demo-tools' )
			)
		);
		exit;
	}

	/**
	 * Gets demo payload.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_demo_payload(): array {
		return array(
			'theme_mods' => array(
				'hero_title'             => __( 'Mobilisons-nous pour une Europe plus juste', 'plaidact-campaign-core' ),
				'hero_subtitle'          => __( 'Une campagne citoyenne prête à l’emploi, multisite et orientée impact.', 'plaidact-campaign-core' ),
				'enable_report_highlight'=> true,
				'report_title'           => __( 'Note stratégique – Printemps 2026', 'plaidact-campaign-core' ),
				'report_excerpt'         => __( 'Téléchargez notre note pour comprendre les enjeux et relayer des arguments solides.', 'plaidact-campaign-core' ),
				'report_button_label'    => __( 'Télécharger la note (PDF)', 'plaidact-campaign-core' ),
			),
			'posts'      => array(
				array(
					'post_type'    => 'post',
					'post_title'   => __( 'Pourquoi cette campagne est décisive', 'plaidact-campaign-core' ),
					'post_content' => __( 'Contenu de démo : exposez les objectifs, les risques et les solutions concrètes.', 'plaidact-campaign-core' ),
				),
				array(
					'post_type'    => 'plaid_breve',
					'post_title'   => __( 'Point presse hebdomadaire', 'plaidact-campaign-core' ),
					'post_content' => __( 'Contenu de démo pour la section brèves.', 'plaidact-campaign-core' ),
				),
				array(
					'post_type'    => 'plaid_partner',
					'post_title'   => __( 'Collectif Alliés Europe', 'plaidact-campaign-core' ),
					'post_content' => '',
					'meta'         => array(
						'_plaid_partner_url' => 'https://example.org',
					),
				),
			),
		);
	}

	/**
	 * Imports payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	private static function import_payload( array $payload ): void {
		if ( ! empty( $payload['theme_mods'] ) && is_array( $payload['theme_mods'] ) ) {
			foreach ( $payload['theme_mods'] as $key => $value ) {
				if ( is_string( $key ) ) {
					set_theme_mod( $key, is_scalar( $value ) ? $value : '' );
				}
			}
		}

		if ( empty( $payload['posts'] ) || ! is_array( $payload['posts'] ) ) {
			return;
		}

		foreach ( $payload['posts'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['post_type'] ) || empty( $item['post_title'] ) ) {
				continue;
			}
			$post_type = sanitize_key( (string) $item['post_type'] );
			$title     = sanitize_text_field( (string) $item['post_title'] );
			$content   = isset( $item['post_content'] ) ? wp_kses_post( (string) $item['post_content'] ) : '';

			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$existing = get_page_by_title( $title, OBJECT, $post_type );
			$post_id  = $existing instanceof \WP_Post ? (int) $existing->ID : 0;

			if ( $post_id > 0 ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $content,
					)
				);
			} else {
				$post_id = (int) wp_insert_post(
					array(
						'post_type'    => $post_type,
						'post_title'   => $title,
						'post_content' => $content,
						'post_status'  => 'publish',
					)
				);
			}

			if ( $post_id < 1 || empty( $item['meta'] ) || ! is_array( $item['meta'] ) ) {
				continue;
			}

			foreach ( $item['meta'] as $meta_key => $meta_value ) {
				if ( is_string( $meta_key ) && is_scalar( $meta_value ) ) {
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
		}
	}
}
