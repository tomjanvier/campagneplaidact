<?php
/**
 * Public contacts directory (no FluentCRM dependency).
 *
 * @package PlaidAct_Breves_Feed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlaidAct_Contact_Directory {
	private const SHORTCODE             = 'plaidact_contact_directory';
	private const LEGACY_SHORTCODE      = 'plaidact_fluentcrm_directory';
	private const OPTION_CONTACT_LISTS  = 'plaidact_contact_directory_lists';
	private const NONCE_IMPORT          = 'plaidact_contact_directory_import';
	private const NONCE_DOWNLOAD_PREFIX = 'plaidact_contact_directory_download_';
	private const DOWNLOAD_ACTION       = 'plaidact_contact_directory_download';
	private const OPTION_VISIBLE_COLUMNS = 'plaidact_contact_directory_visible_columns';
	private const OPTION_EXPORT_BRANDING = 'plaidact_contact_directory_export_branding';

	private static ?PlaidAct_Contact_Directory $instance = null;

	public static function init(): PlaidAct_Contact_Directory {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_shortcode( self::LEGACY_SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_csv_download' ) );
	}

	public function register_assets(): void {
		wp_register_style( 'plaidact-fluentcrm-directory', PLAIDACT_CORE_URL . 'assets/css/fluentcrm-directory.css', array(), PLAIDACT_CORE_VERSION );
		wp_register_script( 'plaidact-contact-directory', PLAIDACT_CORE_URL . 'assets/js/contact-directory.js', array(), PLAIDACT_CORE_VERSION, true );
	}

	public function register_admin_page(): void {
		add_menu_page(
			esc_html__( 'Répertoire contacts', 'plaidact-campaign-core' ),
			esc_html__( 'Répertoire contacts', 'plaidact-campaign-core' ),
			'manage_options',
			'plaidact-contact-directory',
			array( $this, 'render_admin_page' ),
			'dashicons-id-alt',
			58
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->handle_admin_postbacks();
		$lists = $this->get_lists();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Répertoire de contacts', 'plaidact-campaign-core' ); ?></h1>
			<p><?php echo esc_html__( 'Créez des listes, importez vos CSV et affichez-les sur le site avec le shortcode [plaidact_contact_directory].', 'plaidact-campaign-core' ); ?></p>
			<?php $visible_columns = $this->get_visible_columns(); ?>
			<h2><?php echo esc_html__( 'Colonnes à afficher (front office)', 'plaidact-campaign-core' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_IMPORT ); ?>
				<input type="hidden" name="plaidact_contact_action" value="update_visible_columns" />
				<p>
					<label><input type="checkbox" name="visible_columns[]" value="groupe" <?php checked( in_array( 'groupe', $visible_columns, true ) ); ?> /> <?php echo esc_html__( 'Groupe politique', 'plaidact-campaign-core' ); ?></label><br />
					<label><input type="checkbox" name="visible_columns[]" value="commission" <?php checked( in_array( 'commission', $visible_columns, true ) ); ?> /> <?php echo esc_html__( 'Commission', 'plaidact-campaign-core' ); ?></label><br />
					<label><input type="checkbox" name="visible_columns[]" value="custom" <?php checked( in_array( 'custom', $visible_columns, true ) ); ?> /> <?php echo esc_html__( 'Fonction', 'plaidact-campaign-core' ); ?></label><br />
					<label><input type="checkbox" name="visible_columns[]" value="social" <?php checked( in_array( 'social', $visible_columns, true ) ); ?> /> <?php echo esc_html__( 'Réseaux sociaux', 'plaidact-campaign-core' ); ?></label>
				</p>
				<?php submit_button( __( 'Enregistrer les colonnes', 'plaidact-campaign-core' ), 'secondary' ); ?>
			</form>

			<h2><?php echo esc_html__( 'Nouvelle liste', 'plaidact-campaign-core' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_IMPORT ); ?>
				<input type="hidden" name="plaidact_contact_action" value="create_list" />
				<table class="form-table"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Nom de la liste', 'plaidact-campaign-core' ); ?></th><td><input type="text" class="regular-text" name="list_name" required /></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Libellé colonne personnalisée', 'plaidact-campaign-core' ); ?></th><td><input type="text" class="regular-text" name="column_label" placeholder="Fonction ou Groupe politique" required /></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Description', 'plaidact-campaign-core' ); ?></th><td><textarea class="large-text" rows="2" name="description"></textarea></td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Image (URL)', 'plaidact-campaign-core' ); ?></th><td><input type="url" class="regular-text" name="image_url" placeholder="https://..." /></td></tr>
				</tbody></table>
				<?php submit_button( __( 'Créer la liste', 'plaidact-campaign-core' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Listes existantes', 'plaidact-campaign-core' ); ?></h2>
			<?php if ( empty( $lists ) ) : ?>
				<p><?php echo esc_html__( 'Aucune liste pour le moment.', 'plaidact-campaign-core' ); ?></p>
			<?php else : ?>
				<?php foreach ( $lists as $list ) : ?>
					<div style="background:#fff;border:1px solid #ddd;padding:16px;margin-bottom:14px;">
						<h3><?php echo esc_html( $list['name'] ); ?></h3>
						<p><strong><?php echo esc_html__( 'Colonne personnalisée :', 'plaidact-campaign-core' ); ?></strong> <?php echo esc_html( $list['column_label'] ); ?></p>
						<p><?php echo esc_html( $list['description'] ); ?></p>
						<p><strong><?php echo esc_html__( 'Contacts :', 'plaidact-campaign-core' ); ?></strong> <?php echo esc_html( (string) count( $list['contacts'] ) ); ?></p>
						<p><strong><?php echo esc_html__( 'Dernière mise à jour :', 'plaidact-campaign-core' ); ?></strong> <?php echo esc_html( ! empty( $list['updated_at'] ) ? wp_date( 'd/m/Y H:i', (int) $list['updated_at'] ) : '—' ); ?></p>
						<p><a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'action' => self::DOWNLOAD_ACTION, 'list_id' => (int) $list['id'], 'format' => 'csv', 'nonce' => wp_create_nonce( self::NONCE_DOWNLOAD_PREFIX . $list['id'] ) ), admin_url( 'admin-post.php' ) ) ); ?>"><?php echo esc_html__( 'Télécharger cette liste (CSV)', 'plaidact-campaign-core' ); ?></a></p>
						<form method="post" style="margin-top:8px;">
							<?php wp_nonce_field( self::NONCE_IMPORT ); ?>
							<input type="hidden" name="plaidact_contact_action" value="update_list_meta" />
							<input type="hidden" name="list_id" value="<?php echo esc_attr( (string) $list['id'] ); ?>" />
							<p><label><strong><?php echo esc_html__( 'Nom de la liste', 'plaidact-campaign-core' ); ?></strong><br /><input type="text" class="regular-text" name="list_name" value="<?php echo esc_attr( (string) $list['name'] ); ?>" required /></label></p>
							<p><label><strong><?php echo esc_html__( 'Libellé colonne personnalisée', 'plaidact-campaign-core' ); ?></strong><br /><input type="text" class="regular-text" name="column_label" value="<?php echo esc_attr( (string) $list['column_label'] ); ?>" required /></label></p>
							<p><label><strong><?php echo esc_html__( 'Description', 'plaidact-campaign-core' ); ?></strong><br /><textarea class="large-text" rows="2" name="description"><?php echo esc_textarea( (string) ( $list['description'] ?? '' ) ); ?></textarea></label></p>
							<p><label><strong><?php echo esc_html__( 'Image (URL)', 'plaidact-campaign-core' ); ?></strong><br /><input type="url" class="regular-text" name="image_url" value="<?php echo esc_attr( (string) ( $list['image_url'] ?? '' ) ); ?>" placeholder="https://..." /></label></p>
							<p><label><input type="checkbox" name="show_groupe" value="1" <?php checked( ! isset( $list['show_groupe'] ) || ! empty( $list['show_groupe'] ) ); ?> /> <?php echo esc_html__( 'Afficher la colonne Groupe politique', 'plaidact-campaign-core' ); ?></label></p>
							<p><label><input type="checkbox" name="show_commission" value="1" <?php checked( ! isset( $list['show_commission'] ) || ! empty( $list['show_commission'] ) ); ?> /> <?php echo esc_html__( 'Afficher la colonne Commission', 'plaidact-campaign-core' ); ?></label></p>
							<p><label><input type="checkbox" name="show_institution" value="1" <?php checked( ! empty( $list['show_institution'] ) ); ?> /> <?php echo esc_html__( 'Afficher la colonne Institution', 'plaidact-campaign-core' ); ?></label></p>
							<?php submit_button( __( 'Mettre à jour la liste', 'plaidact-campaign-core' ), 'secondary', 'submit', false ); ?>
						</form>
						<form method="post" enctype="multipart/form-data" style="margin-top:8px;">
							<?php wp_nonce_field( self::NONCE_IMPORT ); ?>
							<input type="hidden" name="plaidact_contact_action" value="import_csv" />
							<input type="hidden" name="list_id" value="<?php echo esc_attr( (string) $list['id'] ); ?>" />
							<input type="file" name="contacts_csv" accept=".csv,text/csv" required />
							<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Importer CSV', 'plaidact-campaign-core' ); ?></button>
						</form>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
			<h2><?php echo esc_html__( 'Branding export', 'plaidact-campaign-core' ); ?></h2>
			<?php $branding = $this->get_export_branding(); ?>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_IMPORT ); ?>
				<input type="hidden" name="plaidact_contact_action" value="update_export_branding" />
				<p><label><strong><?php echo esc_html__( 'Nom de marque', 'plaidact-campaign-core' ); ?></strong><br /><input type="text" class="regular-text" name="brand_name" value="<?php echo esc_attr( $branding['brand_name'] ); ?>" /></label></p>
				<p><label><strong><?php echo esc_html__( 'Logo (URL)', 'plaidact-campaign-core' ); ?></strong><br /><input type="url" class="regular-text" name="logo_url" value="<?php echo esc_attr( $branding['logo_url'] ); ?>" /></label></p>
				<?php submit_button( __( 'Enregistrer le branding export', 'plaidact-campaign-core' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	private function handle_admin_postbacks(): void {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['plaidact_contact_action'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE_IMPORT );
		$action = sanitize_key( wp_unslash( $_POST['plaidact_contact_action'] ) );
		if ( 'create_list' === $action ) {
			$this->create_list_from_request();
		}
		if ( 'import_csv' === $action ) {
			$this->import_csv_from_request();
		}
		if ( 'update_list_meta' === $action ) {
			$this->update_list_meta_from_request();
		}
		if ( 'update_visible_columns' === $action ) {
			$this->update_visible_columns_from_request();
		}
		if ( 'update_export_branding' === $action ) {
			$this->update_export_branding_from_request();
		}
	}

	private function create_list_from_request(): void {
		$name         = isset( $_POST['list_name'] ) ? sanitize_text_field( wp_unslash( $_POST['list_name'] ) ) : '';
		$column_label = isset( $_POST['column_label'] ) ? sanitize_text_field( wp_unslash( $_POST['column_label'] ) ) : '';
		$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$image_url    = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		if ( '' === $name || '' === $column_label ) {
			return;
		}
		$lists   = $this->get_lists();
		$lists[] = array(
			'id'           => time() + wp_rand( 1, 9999 ),
			'name'         => $name,
			'column_label' => $column_label,
			'description'  => $description,
			'image_url'    => $image_url,
			'updated_at'   => time(),
			'contacts'     => array(),
		);
		update_option( self::OPTION_CONTACT_LISTS, $lists, false );
	}


	private function update_list_meta_from_request(): void {
		$list_id      = isset( $_POST['list_id'] ) ? absint( wp_unslash( $_POST['list_id'] ) ) : 0;
		$name         = isset( $_POST['list_name'] ) ? sanitize_text_field( wp_unslash( $_POST['list_name'] ) ) : '';
		$column_label = isset( $_POST['column_label'] ) ? sanitize_text_field( wp_unslash( $_POST['column_label'] ) ) : '';
		$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$image_url    = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		if ( $list_id < 1 || '' === $name || '' === $column_label ) {
			return;
		}
		$lists = $this->get_lists();
		foreach ( $lists as &$list ) {
			if ( $list_id !== (int) $list['id'] ) {
				continue;
			}
			$list['name']         = $name;
			$list['column_label'] = $column_label;
			$list['description']  = $description;
			$list['image_url']    = $image_url;
			$list['updated_at'] = time();
			$list['show_groupe'] = isset( $_POST['show_groupe'] ) ? 1 : 0;
			$list['show_commission'] = isset( $_POST['show_commission'] ) ? 1 : 0;
			$list['show_institution'] = isset( $_POST['show_institution'] ) ? 1 : 0;
			break;
		}
		unset( $list );
		update_option( self::OPTION_CONTACT_LISTS, $lists, false );
	}

	private function import_csv_from_request(): void {
		$list_id = isset( $_POST['list_id'] ) ? absint( wp_unslash( $_POST['list_id'] ) ) : 0;
		if ( $list_id < 1 || empty( $_FILES['contacts_csv']['tmp_name'] ) ) {
			return;
		}

		$tmp_name = (string) $_FILES['contacts_csv']['tmp_name'];
		if ( ! is_uploaded_file( $tmp_name ) ) {
			return;
		}

		$file_name = isset( $_FILES['contacts_csv']['name'] ) ? (string) $_FILES['contacts_csv']['name'] : '';
		$file_type = wp_check_filetype_and_ext( $tmp_name, $file_name );
		if ( empty( $file_type['ext'] ) || 'csv' !== strtolower( (string) $file_type['ext'] ) ) {
			return;
		}

		$rows = $this->parse_csv_contacts( $tmp_name );
		if ( empty( $rows ) ) {
			return;
		}
		$lists = $this->get_lists();
		foreach ( $lists as &$list ) {
			if ( $list_id !== (int) $list['id'] ) {
				continue;
			}
			$list['contacts']   = $rows;
			$list['updated_at'] = time();
			break;
		}
		unset( $list );
		update_option( self::OPTION_CONTACT_LISTS, $lists, false );
	}

	private function parse_csv_contacts( string $tmp_path ): array {
		$handle = fopen( $tmp_path, 'r' );
		if ( false === $handle ) {
			return array();
		}
		$rows    = array();
		$delimiter = $this->detect_csv_delimiter( $handle );
		$line_number = 0;
		$headers = null;
		while ( ( $candidate = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$line_number++;
			if ( $line_number <= 3 ) {
				continue;
			}
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$headers = $candidate;
			break;
		}
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			return array();
		}
		$headers = array_map( static function( $header ) {
			$header = is_string( $header ) ? trim( $header ) : '';
			$header = preg_replace( '/^\xEF\xBB\xBF/u', '', $header );
			return $header;
		}, $headers );
		$max_rows = 5000;
		$row_count = 0;
		while ( ( $line = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$row_count++;
			if ( $row_count > $max_rows ) {
				break;
			}
			$data = array_combine( $headers, $line );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$row_values = array_filter(
				array_map(
					static function( $value ) {
						return trim( (string) $value );
					},
					$line
				),
				static function( $value ) {
					return '' !== $value;
				}
			);
			if ( empty( $row_values ) ) {
				continue;
			}
			$normalized_data = $this->normalize_contact_row_keys( $data );
			$rows[] = array(
				'nom'          => sanitize_text_field( (string) ( $normalized_data['nom'] ?? '' ) ),
				'prenom'       => sanitize_text_field( (string) ( $normalized_data['prenom'] ?? '' ) ),
				'custom'       => sanitize_text_field( (string) ( $normalized_data['fonction'] ?? $normalized_data['groupe politique'] ?? '' ) ),
				'institution'  => sanitize_text_field( (string) ( $normalized_data['institution'] ?? '' ) ),
				'groupe'       => sanitize_text_field( (string) ( $normalized_data['groupe politique'] ?? '' ) ),
				'commission'   => $this->normalize_commission_value( (string) ( $normalized_data['commission'] ?? '' ) ),
				'social_links' => $this->parse_social_links( $normalized_data ),
				'email'        => $this->normalize_email_value( (string) ( $normalized_data['email'] ?? '' ) ),
				'notes'        => sanitize_textarea_field( (string) ( $normalized_data['notes'] ?? '' ) ),
			);
		}
		fclose( $handle );
		return $rows;
	}
	private function normalize_email_value( string $value ): string {
		$parts = preg_split( '/\s*[|;,]\s*/u', $value );
		if ( ! is_array( $parts ) ) {
			return sanitize_email( $value );
		}
		$clean = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email ) {
				$clean[ mb_strtolower( $email ) ] = $email;
			}
		}
		return implode( ' | ', array_values( $clean ) );
	}

	private function detect_csv_delimiter( $handle ): string {
		$position = ftell( $handle );
		$sample   = '';
		for ( $i = 0; $i < 6; $i++ ) {
			$line = fgets( $handle );
			if ( false === $line ) {
				break;
			}
			$sample .= $line;
		}
		fseek( $handle, (int) $position );
		$candidates = array( ',', ';', "\t", '|' );
		$best       = ',';
		$best_count = -1;
		foreach ( $candidates as $candidate ) {
			$count = substr_count( $sample, $candidate );
			if ( $count > $best_count ) {
				$best = $candidate;
				$best_count = $count;
			}
		}
		return $best;
	}

	private function normalize_contact_row_keys( array $data ): array {
		$normalized = array();
		foreach ( $data as $key => $value ) {
			$normalized_key = sanitize_title( remove_accents( (string) $key ) );
			$normalized_key = str_replace( '-', ' ', $normalized_key );
			$normalized[ $normalized_key ] = $value;
		}
		return $normalized;
	}


	private function normalize_commission_value( string $value ): string {
		$parts = preg_split( '/[|;,]+/u', $value );
		if ( ! is_array( $parts ) ) {
			return sanitize_text_field( trim( $value ) );
		}
		$clean = array();
		foreach ( $parts as $part ) {
			$item = sanitize_text_field( trim( (string) $part ) );
			if ( '' === $item ) {
				continue;
			}
			$clean[ mb_strtolower( $item ) ] = $item;
		}
		return implode( ' | ', array_values( $clean ) );
	}


	private function parse_social_links( array $data ): array {
		$platforms = array(
			'x'         => $data['x'] ?? $data['twitter'] ?? '',
			'linkedin'  => $data['linkedin'] ?? '',
			'facebook'  => $data['facebook'] ?? '',
			'instagram' => $data['instagram'] ?? '',
			'youtube'   => $data['youtube'] ?? '',
		);
		$links = array();
		foreach ( $platforms as $key => $value ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $value ) {
				continue;
			}
			$links[ $key ] = esc_url_raw( $value );
		}
		return $links;
	}


	private function get_visible_columns(): array {
		$default = array( 'groupe', 'commission', 'custom', 'social' );
		$visible = get_option( self::OPTION_VISIBLE_COLUMNS, $default );
		if ( ! is_array( $visible ) ) {
			return $default;
		}
		$allowed = array( 'groupe', 'commission', 'custom', 'social' );
		$visible = array_values( array_intersect( $allowed, $visible ) );
		return empty( $visible ) ? $default : $visible;
	}

	private function update_visible_columns_from_request(): void {
		$columns = isset( $_POST['visible_columns'] ) ? (array) wp_unslash( $_POST['visible_columns'] ) : array();
		$columns = array_map( 'sanitize_key', $columns );
		$allowed = array( 'groupe', 'commission', 'custom', 'social' );
		$columns = array_values( array_intersect( $allowed, $columns ) );
		if ( empty( $columns ) ) {
			$columns = $allowed;
		}
		update_option( self::OPTION_VISIBLE_COLUMNS, $columns, false );
	}


	public function render_shortcode(): string {
		wp_enqueue_style( 'plaidact-fluentcrm-directory' );
		wp_enqueue_script( 'plaidact-contact-directory' );
		$lists = $this->get_lists();
		if ( empty( $lists ) ) {
			return '<p class="plaidact-fcd-empty">' . esc_html__( 'Aucune liste de contacts disponible.', 'plaidact-campaign-core' ) . '</p>';
		}
		$current         = null;
		$current_slug    = isset( $_GET['list'] ) ? sanitize_title( wp_unslash( $_GET['list'] ) ) : '';
		$current_list_id = isset( $_GET['list_id'] ) ? absint( wp_unslash( $_GET['list_id'] ) ) : 0;
		$show_all_lists  = '' === $current_slug && 0 === $current_list_id;
		if ( '' !== $current_slug ) {
			$current = $this->get_list_by_slug( $current_slug );
		}
		if ( null === $current && $current_list_id > 0 ) {
			$current = $this->get_list_by_id( $current_list_id );
		}
		if ( ! $show_all_lists && null === $current ) {
			$current = $lists[0];
		}

		$displayed_contacts = array();
		if ( $show_all_lists ) {
			foreach ( $lists as $list ) {
				foreach ( $list['contacts'] as $contact ) {
					$contact['list_name'] = $list['name'];
					$displayed_contacts[] = $contact;
				}
			}
		} elseif ( null !== $current ) {
			foreach ( $current['contacts'] as $contact ) {
				$contact['list_name'] = $current['name'];
				$displayed_contacts[] = $contact;
			}
		}

		$custom_values     = $this->get_unique_contact_values( $displayed_contacts, 'custom' );
		$commission_values = $this->get_unique_contact_values( $displayed_contacts, 'commission' );
		$groupe_values     = $this->get_unique_contact_values( $displayed_contacts, 'groupe' );
		$visible_columns   = $this->get_visible_columns();
		$show_groupe_col = in_array( 'groupe', $visible_columns, true ) && ( $show_all_lists || null === $current || ! isset( $current['show_groupe'] ) || ! empty( $current['show_groupe'] ) );
		$show_commission_col = in_array( 'commission', $visible_columns, true ) && ( $show_all_lists || null === $current || ! isset( $current['show_commission'] ) || ! empty( $current['show_commission'] ) );
		$show_institution_col = $show_all_lists || ( null !== $current && ! empty( $current['show_institution'] ) );

		$download_url = null;
		if ( ! $show_all_lists && null !== $current ) {
			$download_url = add_query_arg(
				array(
					'action'  => self::DOWNLOAD_ACTION,
					'list_id' => (int) $current['id'],
					'nonce'   => wp_create_nonce( self::NONCE_DOWNLOAD_PREFIX . $current['id'] ),
				),
				admin_url( 'admin-post.php' )
			);
		}

		ob_start(); ?>
		<div class="plaidact-fcd">
			<div class="plaidact-fcd-directory__lead">
				<h3><?php echo esc_html__( 'Répertoire de contacts', 'plaidact-campaign-core' ); ?></h3>
				<p><?php echo esc_html__( 'Choisissez une liste, recherchez un contact et exportez le tableau en CSV.', 'plaidact-campaign-core' ); ?></p>
			</div>
			<?php if ( null !== $download_url ) : ?>
				<a class="plaidact-fcd-btn plaidact-fcd-btn--download" href="<?php echo esc_url( add_query_arg( 'format', 'csv', $download_url ) ); ?>"><?php echo esc_html__( 'Télécharger CSV', 'plaidact-campaign-core' ); ?></a>
				<a class="plaidact-fcd-btn plaidact-fcd-btn--ghost" href="<?php echo esc_url( add_query_arg( 'format', 'xls', $download_url ) ); ?>"><?php echo esc_html__( 'Télécharger Excel', 'plaidact-campaign-core' ); ?></a>
				<a class="plaidact-fcd-btn plaidact-fcd-btn--ghost plaidact-fcd-filtered-download" data-base-url="<?php echo esc_url( $download_url ); ?>" href="<?php echo esc_url( add_query_arg( 'filtered', '1', $download_url ) ); ?>"><?php echo esc_html__( 'Télécharger le CSV filtré', 'plaidact-campaign-core' ); ?></a>
			<?php endif; ?>
			<?php if ( ! $show_all_lists ) : ?>
				<a class="plaidact-fcd-btn plaidact-fcd-btn--ghost" href="<?php echo esc_url( remove_query_arg( array( 'list', 'list_id' ) ) ); ?>"><?php echo esc_html__( 'Retour à la liste complète', 'plaidact-campaign-core' ); ?></a>
			<?php endif; ?>
			<div class="plaidact-fcd-list-grid" role="tablist" aria-label="<?php echo esc_attr__( 'Listes de contacts', 'plaidact-campaign-core' ); ?>">
				<?php foreach ( $lists as $list ) : ?>
					<?php $list_is_active = null !== $current && (int) $list['id'] === (int) $current['id']; ?>
					<a class="plaidact-fcd-list-card <?php echo $list_is_active ? 'is-active' : ''; ?>" aria-current="<?php echo $list_is_active ? 'page' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( 'list', sanitize_title( (string) $list['name'] ) ) ); ?>">
						<?php if ( ! empty( $list['image_url'] ) ) : ?>
							<img class="plaidact-fcd-list-image" src="<?php echo esc_url( $list['image_url'] ); ?>" alt="" />
						<?php else : ?>
							<div class="plaidact-fcd-list-image"><?php echo esc_html( mb_substr( (string) $list['name'], 0, 1 ) ); ?></div>
						<?php endif; ?>
						<div class="plaidact-fcd-list-card__body"><h4><?php echo esc_html( $list['name'] ); ?></h4><p><?php echo esc_html( $list['description'] ); ?></p><small><?php echo esc_html__( 'Mise à jour :', 'plaidact-campaign-core' ); ?> <?php echo esc_html( ! empty( $list['updated_at'] ) ? wp_date( 'd/m/Y', (int) $list['updated_at'] ) : '—' ); ?></small></div></a>
				<?php endforeach; ?>
			</div>
			<div class="plaidact-fcd-table-wrap">
			<table class="plaidact-fcd-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Nom', 'plaidact-campaign-core' ); ?></th>
						<th><?php echo esc_html__( 'Prénom', 'plaidact-campaign-core' ); ?></th>
						<th><?php echo esc_html__( 'Liste', 'plaidact-campaign-core' ); ?></th>
						<th><?php echo esc_html__( 'Email', 'plaidact-campaign-core' ); ?></th>
						<?php if ( $show_groupe_col ) : ?><th data-column="groupe"><?php echo esc_html__( 'Groupe politique', 'plaidact-campaign-core' ); ?></th><?php endif; ?>
						<?php if ( $show_commission_col ) : ?><th data-column="commission"><?php echo esc_html__( 'Commission', 'plaidact-campaign-core' ); ?></th><?php endif; ?>
						<?php if ( $show_institution_col ) : ?><th data-column="institution"><?php echo esc_html__( 'Institution', 'plaidact-campaign-core' ); ?></th><?php endif; ?>
						<?php if ( in_array( 'custom', $visible_columns, true ) ) : ?><th data-column="custom"><?php echo esc_html__( 'Fonction', 'plaidact-campaign-core' ); ?></th><?php endif; ?>
						<?php if ( in_array( 'social', $visible_columns, true ) ) : ?><th data-column="social"><?php echo esc_html__( 'Réseaux sociaux', 'plaidact-campaign-core' ); ?></th><?php endif; ?>
						<th><?php echo esc_html__( 'Notes', 'plaidact-campaign-core' ); ?></th>
					</tr>
					<tr class="plaidact-fcd-filter-row">
						<th><input type="search" class="plaidact-fcd-search" placeholder="<?php echo esc_attr__( 'Rechercher…', 'plaidact-campaign-core' ); ?>" /></th>
						<th></th><th></th><th></th>
						<?php if ( $show_groupe_col ) : ?><th><select class="plaidact-fcd-select-filter" data-filter="groupe"><option value=""><?php echo esc_html__( 'Tous', 'plaidact-campaign-core' ); ?></option><?php foreach ( $groupe_values as $value ) : ?><option value="<?php echo esc_attr( strtolower( $value ) ); ?>"><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></th><?php endif; ?>
						<?php if ( $show_commission_col ) : ?><th><select class="plaidact-fcd-select-filter" data-filter="commission"><option value=""><?php echo esc_html__( 'Toutes', 'plaidact-campaign-core' ); ?></option><?php foreach ( $commission_values as $value ) : ?><option value="<?php echo esc_attr( strtolower( $value ) ); ?>"><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></th><?php endif; ?>
						<?php if ( $show_institution_col ) : ?><th></th><?php endif; ?>
						<?php if ( in_array( 'custom', $visible_columns, true ) ) : ?><th><select class="plaidact-fcd-select-filter" data-filter="custom"><option value=""><?php echo esc_html__( 'Toutes', 'plaidact-campaign-core' ); ?></option><?php foreach ( $custom_values as $value ) : ?><option value="<?php echo esc_attr( strtolower( $value ) ); ?>"><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></th><?php endif; ?>
						<?php if ( in_array( 'social', $visible_columns, true ) ) : ?><th></th><?php endif; ?>
						<th></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $displayed_contacts as $contact ) : ?>
					<tr data-custom="<?php echo esc_attr( strtolower( (string) ( $contact['custom'] ?? '' ) ) ); ?>" data-groupe="<?php echo esc_attr( strtolower( (string) ( $contact['groupe'] ?? '' ) ) ); ?>" data-commission="<?php echo esc_attr( strtolower( (string) ( $contact['commission'] ?? '' ) ) ); ?>">
						<td><?php echo esc_html( $contact['nom'] ); ?></td><td><?php echo esc_html( $contact['prenom'] ); ?></td><td><?php echo esc_html( $contact['list_name'] ?? '' ); ?></td><td><?php echo nl2br( esc_html( str_replace( '|', "\n", (string) ( $contact['email'] ?? '' ) ) ) ); ?></td><?php if ( $show_groupe_col ) : ?><td><?php echo esc_html( $contact['groupe'] ?? '' ); ?></td><?php endif; ?><?php if ( $show_commission_col ) : ?><td><?php echo esc_html( $contact['commission'] ?? '' ); ?></td><?php endif; ?><?php if ( $show_institution_col ) : ?><td><?php echo esc_html( $contact['institution'] ?? '' ); ?></td><?php endif; ?><?php if ( in_array( 'custom', $visible_columns, true ) ) : ?><td><?php echo esc_html( $contact['custom'] ); ?></td><?php endif; ?><?php if ( in_array( 'social', $visible_columns, true ) ) : ?><td><?php echo $this->render_social_links( $contact['social_links'] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><?php endif; ?><td><?php echo esc_html( $contact['notes'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			</div>

		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function render_social_links( array $links ): string {
		if ( empty( $links ) ) {
			return '—';
		}
		$html = '<div class="plaidact-fcd-social">';
		foreach ( $links as $platform => $url ) {
			if ( '' === $url ) {
				continue;
			}
			$html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( ucfirst( $platform ) ) . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	public function handle_csv_download(): void {
		$list_id = isset( $_GET['list_id'] ) ? absint( wp_unslash( $_GET['list_id'] ) ) : 0;
		$nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		$format  = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		$list    = $this->get_list_by_id( $list_id );
		if ( null === $list || ! wp_verify_nonce( $nonce, self::NONCE_DOWNLOAD_PREFIX . $list_id ) ) {
			wp_die( esc_html__( 'Lien de téléchargement invalide.', 'plaidact-campaign-core' ) );
		}
		$contacts = $list['contacts'];
		$filtered = isset( $_GET['filtered'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['filtered'] ) );
		if ( $filtered ) {
			$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
			$custom = isset( $_GET['custom'] ) ? sanitize_text_field( wp_unslash( $_GET['custom'] ) ) : '';
			$groupe = isset( $_GET['groupe'] ) ? sanitize_text_field( wp_unslash( $_GET['groupe'] ) ) : '';
			$commission = isset( $_GET['commission'] ) ? sanitize_text_field( wp_unslash( $_GET['commission'] ) ) : '';
			$contacts = $this->filter_contacts_for_download( $contacts, $search, $custom, $groupe, $commission );
		}
		$filename = 'contacts-' . $list_id;
		if ( 'xls' === $format ) {
			header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename . '.xls' );
			$this->render_excel_like_export( $list, $contacts );
			exit;
		}
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename . '.csv' );
		$output = fopen( 'php://output', 'w' );
		$branding = $this->get_export_branding();
		fputcsv( $output, array( 'https://plaidact.org/base-contacts-politiques/' ) );
		fputcsv( $output, array( '' ) );
		if ( '' !== $branding['logo_url'] ) {
			fputcsv( $output, array( '=IMAGE("' . str_replace( '"', '""', $branding['logo_url'] ) . '")' ) );
			fputcsv( $output, array( '' ) );
		}
		fputcsv( $output, array( 'Nom', 'Prénom', $list['column_label'], 'Institution', 'Groupe politique', 'Commission', 'Email', 'Notes' ) );
		foreach ( $contacts as $contact ) {
			fputcsv(
				$output,
				array(
					$this->escape_csv_formula( (string) ( $contact['nom'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['prenom'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['custom'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['institution'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['groupe'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['commission'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['email'] ?? '' ) ),
					$this->escape_csv_formula( (string) ( $contact['notes'] ?? '' ) ),
				)
			);
		}
		fclose( $output );
		exit;
	}
	private function render_excel_like_export( array $list, array $contacts ): void {
		$branding = $this->get_export_branding();
		echo '<table border="1">';
		echo '<tr><td colspan="8" style="background:#2b1533;color:#ffffff;"><a href="https://plaidact.org/base-contacts-politiques/" style="color:#ffffff;text-decoration:none;">https://plaidact.org/base-contacts-politiques/</a></td></tr>';
		echo '<tr><td colspan="8"><strong>' . esc_html( $branding['brand_name'] ) . '</strong></td></tr>';
		if ( '' !== $branding['logo_url'] ) {
			echo '<tr><td colspan="8"><img src="' . esc_url( $branding['logo_url'] ) . '" alt="' . esc_attr( $branding['brand_name'] ) . '" style="max-height:80px;width:auto;" /></td></tr>';
		}
		echo '<tr><th>Nom</th><th>Prénom</th><th>' . esc_html( $list['column_label'] ) . '</th><th>Institution</th><th>Groupe politique</th><th>Commission</th><th>Email</th><th>Notes</th></tr>';
		foreach ( $contacts as $contact ) {
			echo '<tr><td>' . esc_html( (string) ( $contact['nom'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['prenom'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['custom'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['institution'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['groupe'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['commission'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['email'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $contact['notes'] ?? '' ) ) . '</td></tr>';
		}
		echo '</table>';
	}
	private function get_export_branding(): array {
		$value = get_option( self::OPTION_EXPORT_BRANDING, array() );
		return array(
			'brand_name' => sanitize_text_field( (string) ( $value['brand_name'] ?? 'PLAID·ACT' ) ),
			'logo_url' => esc_url_raw( (string) ( $value['logo_url'] ?? '' ) ),
		);
	}
	private function update_export_branding_from_request(): void {
		update_option( self::OPTION_EXPORT_BRANDING, array(
			'brand_name' => isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : 'PLAID·ACT',
			'logo_url' => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
		), false );
	}

	private function filter_contacts_for_download( array $contacts, string $search, string $custom, string $groupe, string $commission ): array {
		$search = mb_strtolower( trim( $search ) );
		$custom = mb_strtolower( trim( $custom ) );
		$groupe = mb_strtolower( trim( $groupe ) );
		$commission = mb_strtolower( trim( $commission ) );
		return array_values( array_filter( $contacts, static function ( array $contact ) use ( $search, $custom, $groupe, $commission ): bool {
			$haystack = mb_strtolower( implode( ' ', array_map( 'strval', array(
				$contact['nom'] ?? '',
				$contact['prenom'] ?? '',
				$contact['custom'] ?? '',
				$contact['institution'] ?? '',
				$contact['groupe'] ?? '',
				$contact['commission'] ?? '',
				$contact['email'] ?? '',
				$contact['notes'] ?? '',
			) ) ) );
			if ( '' !== $search && false === strpos( $haystack, $search ) ) {
				return false;
			}
			if ( '' !== $custom && mb_strtolower( (string) ( $contact['custom'] ?? '' ) ) !== $custom ) {
				return false;
			}
			if ( '' !== $groupe && mb_strtolower( (string) ( $contact['groupe'] ?? '' ) ) !== $groupe ) {
				return false;
			}
			if ( '' !== $commission ) {
				$parts = preg_split( '/\s*[|;,]\s*/u', mb_strtolower( (string) ( $contact['commission'] ?? '' ) ) );
				if ( ! is_array( $parts ) || ! in_array( $commission, $parts, true ) ) {
					return false;
				}
			}
			return true;
		} ) );
	}

	private function get_lists(): array {
		$lists = get_option( self::OPTION_CONTACT_LISTS, array() );
		return is_array( $lists ) ? $lists : array();
	}

	private function get_list_by_id( int $list_id ): ?array {
		foreach ( $this->get_lists() as $list ) {
			if ( $list_id === (int) $list['id'] ) {
				return $list;
			}
		}
		return null;
	}

	private function get_list_by_slug( string $list_slug ): ?array {
		foreach ( $this->get_lists() as $list ) {
			if ( $list_slug === sanitize_title( (string) ( $list['name'] ?? '' ) ) ) {
				return $list;
			}
		}
		return null;
	}

	private function get_unique_contact_values( array $contacts, string $key ): array {
		$values = array();
		foreach ( $contacts as $contact ) {
			$value = trim( (string) ( $contact[ $key ] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			if ( 'commission' === $key ) {
				$parts = preg_split( '/\s*[|;,]\s*/u', $value );
				if ( is_array( $parts ) ) {
					foreach ( $parts as $part ) {
						$part = trim( (string) $part );
						if ( '' !== $part ) {
							$values[ $part ] = $part;
						}
					}
				}
				continue;
			}
			$values[ $value ] = $value;
		}
		return array_values( $values );
	}

	private function escape_csv_formula( string $value ): string {
		$trimmed = ltrim( $value );
		if ( '' === $trimmed ) {
			return $value;
		}

		$first_char = substr( $trimmed, 0, 1 );
		if ( in_array( $first_char, array( '=', '+', '-', '@' ), true ) ) {
			return "'" . $value;
		}

		return $value;
	}
}
