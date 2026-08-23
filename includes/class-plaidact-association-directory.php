<?php

namespace Plaidact\CampaignCore;

use DateTimeImmutable;
use WP_Post;
use WP_Query;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Association_Directory {
	private const ASSO_POST_TYPE = 'associations';
	private const ASSO_TAXONOMY  = 'associations';

	/** @var array<string,string> */
	private const SOCIAL_NETWORKS = [
		'facebook'  => 'Facebook',
		'x'         => 'X',
		'instagram' => 'Instagram',
		'linkedin'  => 'LinkedIn',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
		'twitch'    => 'Twitch',
		'whatsapp'  => 'WhatsApp',
		'telegram'  => 'Telegram',
		'discord'   => 'Discord',
		'bluesky'   => 'Bluesky',
	];

	private const SOCIAL_ICON_FALLBACK = 'share';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_asso_cpt_and_taxonomies' ], 2 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_shortcode( 'plaidact_asso_directory', [ __CLASS__, 'asso_directory_shortcode' ] );
		add_filter( 'template_include', [ __CLASS__, 'maybe_use_plugin_templates' ] );
		add_filter( 'theme_page_templates', [ __CLASS__, 'register_page_templates' ] );
		add_filter( 'template_include', [ __CLASS__, 'handle_page_template' ], 99 );
		add_action( 'admin_menu', [ __CLASS__, 'register_asso_import_page' ] );
		add_action( 'admin_post_plaidact_import_asso', [ __CLASS__, 'handle_asso_import' ] );
		add_action( 'admin_post_plaidact_export_asso_csv', [ __CLASS__, 'handle_asso_export_csv' ] );
		add_action( 'admin_post_plaidact_migrate_asso_taxonomies', [ __CLASS__, 'handle_asso_taxonomy_migration' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'include_associations_in_site_search' ] );
	}

	public static function register_agenda_post_type(): void {
		register_post_type(
			'agenda',
			[
				'labels' => [
					'name'          => __( 'Événements', 'plaidact-campaign-core' ),
					'singular_name' => __( 'Événement', 'plaidact-campaign-core' ),
					'menu_name'     => __( 'Agenda', 'plaidact-campaign-core' ),
					'all_items'     => __( 'Tous les événements', 'plaidact-campaign-core' ),
					'add_new_item'  => __( 'Ajouter un événement', 'plaidact-campaign-core' ),
					'edit_item'     => __( 'Modifier l’événement', 'plaidact-campaign-core' ),
					'new_item'      => __( 'Nouvel événement', 'plaidact-campaign-core' ),
					'view_item'     => __( 'Voir l’événement', 'plaidact-campaign-core' ),
					'search_items'  => __( 'Rechercher des événements', 'plaidact-campaign-core' ),
				],
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => 'agenda',
				'rewrite'      => [ 'slug' => 'agenda', 'with_front' => false ],
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail' ],
			]
		);
	}

	public static function register_taxonomy(): void {
		register_taxonomy(
			'agenda_timeline',
			[ 'agenda' ],
			[
				'labels' => [
					'name'          => _x( 'Timelines Agenda', 'taxonomy general name', 'plaidact-campaign-core' ),
					'singular_name' => _x( 'Timeline Agenda', 'taxonomy singular name', 'plaidact-campaign-core' ),
					'menu_name'     => __( 'Timelines', 'plaidact-campaign-core' ),
				],
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [
					'slug'       => 'agenda-timeline',
					'with_front' => false,
				],
			]
		);
	}

	public static function register_asso_cpt_and_taxonomies(): void {
		register_post_type(
			self::ASSO_POST_TYPE,
			[
				'labels' => [
					'name'          => __( 'Associations', 'plaidact-campaign-core' ),
					'singular_name' => __( 'Association', 'plaidact-campaign-core' ),
					'menu_name'     => __( 'Répertoire Asso', 'plaidact-campaign-core' ),
				],
				'public'             => true,
				'has_archive'        => 'association',
				'rewrite'            => [ 'slug' => 'association', 'with_front' => false ],
				'taxonomies'         => [ self::ASSO_TAXONOMY ],
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-groups',
				'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				'publicly_queryable' => true,
				'exclude_from_search' => false,
			]
		);

		register_taxonomy(
			self::ASSO_TAXONOMY,
			[ self::ASSO_POST_TYPE ],
			[
				'labels' => [
					'name'              => __( 'Catégories d’associations', 'plaidact-campaign-core' ),
					'singular_name'     => __( 'Catégorie d’association', 'plaidact-campaign-core' ),
					'menu_name'         => __( 'Catégories', 'plaidact-campaign-core' ),
					'all_items'         => __( 'Toutes les catégories', 'plaidact-campaign-core' ),
					'edit_item'         => __( 'Modifier la catégorie', 'plaidact-campaign-core' ),
					'view_item'         => __( 'Voir la catégorie', 'plaidact-campaign-core' ),
					'update_item'       => __( 'Mettre à jour la catégorie', 'plaidact-campaign-core' ),
					'add_new_item'      => __( 'Ajouter une catégorie', 'plaidact-campaign-core' ),
					'new_item_name'     => __( 'Nouvelle catégorie', 'plaidact-campaign-core' ),
					'parent_item'       => __( 'Catégorie parente', 'plaidact-campaign-core' ),
					'parent_item_colon' => __( 'Catégorie parente :', 'plaidact-campaign-core' ),
					'search_items'      => __( 'Rechercher des catégories', 'plaidact-campaign-core' ),
				],
				'public'            => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'rest_base'         => 'association-categories',
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => 'association-categorie', 'with_front' => false ],
			]
		);
	}

	public static function include_associations_in_site_search( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$post_types = $query->get( 'post_type' );
		if ( empty( $post_types ) ) {
			$query->set( 'post_type', [ 'post', 'page', self::ASSO_POST_TYPE ] );
			return;
		}

		if ( is_string( $post_types ) ) {
			$post_types = [ $post_types ];
		}

		if ( is_array( $post_types ) && ! in_array( self::ASSO_POST_TYPE, $post_types, true ) ) {
			$post_types[] = self::ASSO_POST_TYPE;
			$query->set( 'post_type', $post_types );
		}
	}

	public static function register_hover_definitions(): void {
		register_taxonomy(
			'pa_def_category',
			[ 'pa_definition' ],
			[
				'labels'       => [
					'name'          => __( 'Catégories de définitions', 'plaidact-campaign-core' ),
					'singular_name' => __( 'Catégorie de définition', 'plaidact-campaign-core' ),
				],
				'public'       => false,
				'show_ui'      => true,
				'hierarchical' => true,
				'show_in_rest' => true,
			]
		);

		register_post_type(
			'pa_definition',
			[
				'labels'             => [
					'name'          => __( 'Définitions', 'plaidact-campaign-core' ),
					'singular_name' => __( 'Définition', 'plaidact-campaign-core' ),
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'menu_position'      => 28,
				'menu_icon'          => 'dashicons-editor-help',
				'show_in_rest'       => true,
				'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				'taxonomies'         => [ 'pa_def_category' ],
			]
		);
	}

	public static function register_blocks(): void {
		wp_register_script(
			'plaidact-blocks',
			PLAIDACT_CORE_URL . 'assets/js/plaidact-blocks.js',
			[ 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-server-side-render', 'wp-block-editor' ],
			PLAIDACT_CORE_VERSION,
			true
		);

		register_block_type(
			'plaidact/timeline',
			[
				'api_version'     => 2,
				'editor_script'   => 'plaidact-blocks',
				'render_callback' => [ __CLASS__, 'render_timeline_block' ],
				'attributes'      => [
					'term' => [ 'type' => 'string', 'default' => '' ],
					'title' => [ 'type' => 'string', 'default' => '' ],
					'showTitle' => [ 'type' => 'boolean', 'default' => true ],
					'showDownload' => [ 'type' => 'boolean', 'default' => true ],
					'layout' => [ 'type' => 'string', 'default' => 'vertical' ],
					'columns' => [ 'type' => 'number', 'default' => 3 ],
					'fillEmptyMonths' => [ 'type' => 'boolean', 'default' => false ],
					'eventsPerColumn' => [ 'type' => 'number', 'default' => 0 ],
				],
			]
		);

		register_block_type(
			'plaidact/asso-cause-list',
			[
				'api_version'     => 2,
				'editor_script'   => 'plaidact-blocks',
				'render_callback' => [ __CLASS__, 'render_asso_block' ],
				'attributes'      => [
					'cause'       => [ 'type' => 'string', 'default' => '' ],
					'postsToShow' => [ 'type' => 'number', 'default' => 9 ],
				],
			]
		);
	}

	public static function render_timeline_block( array $attributes ): string {
		$term = isset( $attributes['term'] ) ? sanitize_title( (string) $attributes['term'] ) : '';
		$layout = isset( $attributes['layout'] ) && in_array( (string) $attributes['layout'], [ 'vertical', 'horizontal' ], true ) ? (string) $attributes['layout'] : 'vertical';
		$fill = isset( $attributes['fillEmptyMonths'] ) && $attributes['fillEmptyMonths'] ? '1' : '0';
		return self::timeline_shortcode(
			[
				'term'              => $term,
					'title'             => isset( $attributes['title'] ) ? sanitize_text_field( (string) $attributes['title'] ) : '',
					'show_title'        => ( isset( $attributes['showTitle'] ) && false === $attributes['showTitle'] ) ? '0' : '1',
					'show_download'     => ( isset( $attributes['showDownload'] ) && false === $attributes['showDownload'] ) ? '0' : '1',
					'layout'            => $layout,
				'columns'           => isset( $attributes['columns'] ) ? (string) max( 1, absint( (int) $attributes['columns'] ) ) : '3',
				'fill_empty_months' => $fill,
				'events_per_column' => isset( $attributes['eventsPerColumn'] ) ? (string) absint( (int) $attributes['eventsPerColumn'] ) : '0',
			]
		);
	}

	public static function render_asso_block( array $attributes ): string {
		return self::asso_directory_shortcode(
			[
				'cause'          => isset( $attributes['cause'] ) ? sanitize_title( (string) $attributes['cause'] ) : '',
				'posts_per_page' => isset( $attributes['postsToShow'] ) ? (string) absint( $attributes['postsToShow'] ) : '9',
				'pagination_key' => 'asso_page',
			]
		);
	}

	public static function register_asso_import_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . self::ASSO_POST_TYPE,
			__( 'Import associations', 'plaidact-campaign-core' ),
			__( 'Import CSV', 'plaidact-campaign-core' ),
			'manage_options',
			'plaidact-asso-import',
			[ __CLASS__, 'render_asso_import_page' ]
		);
	}

	public static function register_agenda_import_page(): void {
		add_submenu_page(
			'edit.php?post_type=agenda',
			__( 'Import agenda', 'plaidact-campaign-core' ),
			__( 'Import CSV', 'plaidact-campaign-core' ),
			'manage_options',
			'plaidact-agenda-import',
			[ __CLASS__, 'render_agenda_import_page' ]
		);
	}

	public static function render_asso_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : '';
		$count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		$error  = isset( $_GET['error'] ) ? sanitize_text_field( (string) $_GET['error'] ) : '';
		$migrated_posts = isset( $_GET['migrated_posts'] ) ? absint( $_GET['migrated_posts'] ) : 0;
		$migrated_terms = isset( $_GET['migrated_terms'] ) ? absint( $_GET['migrated_terms'] ) : 0;
		$template_headers = implode( ',', self::get_asso_import_headers() );
		$template_row = implode( ',', [
			'ACAT France',
			'acat-france',
			'https://www.acatfrance.fr/logo.png',
			'',
			'https://www.acatfrance.fr',
			'https://www.acatfrance.fr/faire-un-don',
			'https://www.acatfrance.fr/contact',
			'"Droits humains|Justice"',
			'Texte court de présentation',
			'https://facebook.com/acat',
			'https://x.com/acat',
			'https://instagram.com/acat',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
			'"Mastodon|https://mastodon.social/@acat
Linktree|https://linktr.ee/acat"',
		] );
		$template_csv = $template_headers . "\n" . $template_row;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import des associations', 'plaidact-campaign-core' ); ?></h1>
			<?php if ( 'ok' === $status ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( '%d associations importées/mises à jour.', 'plaidact-campaign-core' ), $count ) ); ?></p></div>
			<?php elseif ( 'taxonomy_migrated' === $status ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( 'Migration terminée : %1$d associations mises à jour, %2$d catégories migrées.', 'plaidact-campaign-core' ), $migrated_posts, $migrated_terms ) ); ?></p></div>
			<?php elseif ( 'error' === $status && '' !== $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Importe un fichier CSV UTF-8. Un logo peut être fourni via une URL (logo_url) ou un ZIP de logos (colonne logo_file).', 'plaidact-campaign-core' ); ?></p>
			<p>
				<a class="button" href="data:text/csv;charset=utf-8,<?php echo rawurlencode( $template_csv ); ?>" download="modele-import-associations.csv">
					<?php esc_html_e( 'Télécharger un modèle CSV', 'plaidact-campaign-core' ); ?>
				</a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'plaidact_import_asso' ); ?>
				<input type="hidden" name="action" value="plaidact_import_asso" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="plaidact_asso_csv"><?php esc_html_e( 'Fichier CSV', 'plaidact-campaign-core' ); ?></label></th>
						<td><input id="plaidact_asso_csv" type="file" name="asso_csv" accept=".csv,text/csv" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="plaidact_asso_zip"><?php esc_html_e( 'ZIP des logos (optionnel)', 'plaidact-campaign-core' ); ?></label></th>
						<td><input id="plaidact_asso_zip" type="file" name="asso_logos_zip" accept=".zip,application/zip" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Importer', 'plaidact-campaign-core' ) ); ?>
			</form>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=plaidact_export_asso_csv' ), 'plaidact_export_asso_csv' ) ); ?>">
					<?php esc_html_e( 'Exporter les entrées en CSV', 'plaidact-campaign-core' ); ?>
				</a>
			</p>

			<hr />
			<h2><?php esc_html_e( 'Migration des taxonomies', 'plaidact-campaign-core' ); ?></h2>
			<p><?php esc_html_e( 'Ce bouton copie les catégories WordPress existantes (taxonomy "category") des fiches associations vers la taxonomy "Catégories d’associations".', 'plaidact-campaign-core' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'plaidact_migrate_asso_taxonomies' ); ?>
				<input type="hidden" name="action" value="plaidact_migrate_asso_taxonomies" />
				<?php submit_button( __( 'Migrer les taxonomies existantes', 'plaidact-campaign-core' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	public static function render_agenda_import_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status = isset( $_GET['status'] ) ? sanitize_key( (string) $_GET['status'] ) : '';
		$count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		$dupes  = isset( $_GET['dupes'] ) ? absint( $_GET['dupes'] ) : 0;
		$template_headers = implode( ',', [ 'title', 'slug', 'timeline', 'date_debut', 'date_fin', 'type_evenement', 'lieu', 'nom_organisation', 'lien_evenement' ] );
		$template_row     = 'Réunion G7,reunion-g7,geopolitique,2026-06-02,2026-06-02,ponctuels,Ottawa,PLAID·ACT,https://example.org/evenement';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import des événements Agenda', 'plaidact-campaign-core' ); ?></h1>
			<?php if ( 'ok' === $status ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( __( '%d événements importés/mis à jour (%d doublons ignorés).', 'plaidact-campaign-core' ), $count, $dupes ) ); ?></p></div>
			<?php endif; ?>
			<p><a class="button" href="data:text/csv;charset=utf-8,<?php echo rawurlencode( $template_headers . "\n" . $template_row ); ?>" download="modele-import-agenda.csv"><?php esc_html_e( 'Télécharger un modèle CSV', 'plaidact-campaign-core' ); ?></a></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'plaidact_import_agenda' ); ?>
				<input type="hidden" name="action" value="plaidact_import_agenda" />
				<input type="file" name="agenda_csv" accept=".csv,text/csv" required />
				<?php submit_button( __( 'Importer', 'plaidact-campaign-core' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function enqueue_assets(): void {
		global $post;
		$load_asso     = is_post_type_archive( self::ASSO_POST_TYPE ) || is_singular( self::ASSO_POST_TYPE );

		if ( $post instanceof WP_Post ) {
			$load_asso     = $load_asso || has_shortcode( $post->post_content, 'plaidact_asso_directory' );
		}

		if ( $load_asso ) {
			wp_enqueue_style( 'plaidact-asso-directory', PLAIDACT_CORE_URL . 'assets/css/asso-directory.css', [], PLAIDACT_CORE_VERSION );
		}
	}

	public static function register_page_templates( array $templates ): array {
		$templates['plaidact-asso-directory-template.php'] = __( 'Répertoire des associations (PlaidAct)', 'plaidact-campaign-core' );
		return $templates;
	}

	public static function handle_page_template( string $template ): string {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}
		if ( 'plaidact-asso-directory-template.php' === get_page_template_slug() ) {
			return PLAIDACT_CORE_PATH . 'templates/page-repertoire-asso.php';
		}

		return $template;
	}

	public static function maybe_use_plugin_templates( string $template ): string {
		if ( is_tax( self::ASSO_TAXONOMY ) ) {
			return PLAIDACT_CORE_PATH . 'templates/archive-asso.php';
		}
		if ( is_post_type_archive( self::ASSO_POST_TYPE ) ) {
			return PLAIDACT_CORE_PATH . 'templates/archive-asso.php';
		}
		if ( is_singular( self::ASSO_POST_TYPE ) ) {
			return PLAIDACT_CORE_PATH . 'templates/single-asso.php';
		}

		return $template;
	}

	public static function asso_directory_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			[
				'posts_per_page' => 9,
				'cause'          => '',
				'pagination_key' => 'asso_page',
			],
			$atts,
			'plaidact_asso_directory'
		);

		ob_start();
		self::render_template(
			'asso-directory-loop.php',
			[
				'posts_per_page' => max( 1, absint( $atts['posts_per_page'] ) ),
				'is_shortcode'   => true,
				'fixed_cause'    => sanitize_title( (string) $atts['cause'] ),
				'pagination_key' => sanitize_key( (string) $atts['pagination_key'] ),
			]
		);
		return (string) ob_get_clean();
	}

	public static function timeline_shortcode( array $atts ): string {
		$atts = shortcode_atts(
				[
					'term'  => '',
					'title' => '',
					'show_title' => '1',
					'show_download' => '1',
					'fill_empty_months' => '0',
				'layout' => 'vertical',
				'columns' => '3',
				'events_per_column' => '0',
			],
			$atts,
			'plaidact_timeline'
		);

		$term_slug = sanitize_title( (string) $atts['term'] );
		if ( '' === $term_slug ) {
			return '<p class="pa-timeline-error">' . esc_html__( 'Shortcode [plaidact_timeline] : paramètre "term" manquant.', 'plaidact-campaign-core' ) . '</p>';
		}

		$payload = self::build_timeline_data( $term_slug, '1' === (string) $atts['fill_empty_months'] );
		if ( empty( $payload['years'] ) ) {
			return '<p class="pa-timeline-empty">' . esc_html__( 'Aucun événement à afficher pour cette timeline.', 'plaidact-campaign-core' ) . '</p>';
		}

		ob_start();
		self::render_template(
			'timeline.php',
				[
					'data'           => $payload,
					'title_override' => sanitize_text_field( (string) $atts['title'] ),
					'show_title'     => '0' !== (string) $atts['show_title'],
					'show_download'  => '0' !== (string) $atts['show_download'],
					'layout'         => in_array( (string) $atts['layout'], [ 'vertical', 'horizontal' ], true ) ? (string) $atts['layout'] : 'vertical',
				'columns'        => max( 1, absint( (string) $atts['columns'] ) ),
				'events_per_column' => absint( (string) $atts['events_per_column'] ),
			]
		);
		return (string) ob_get_clean();
	}

	/** @return array<string,mixed> */
	public static function get_asso_filters_from_request(): array {
		$paged_from_query = get_query_var( 'paged' ) ? absint( (string) get_query_var( 'paged' ) ) : 0;
		$paged_from_page  = get_query_var( 'page' ) ? absint( (string) get_query_var( 'page' ) ) : 0;
		$paged_from_get   = isset( $_GET['paged'] ) ? absint( wp_unslash( (string) $_GET['paged'] ) ) : 0;
		$asso_page        = isset( $_GET['asso_page'] ) ? absint( wp_unslash( (string) $_GET['asso_page'] ) ) : 0;

		return [
			's'      => isset( $_GET['asso_s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['asso_s'] ) ) : '',
			'cause'  => isset( $_GET['asso_cause'] ) ? sanitize_title( wp_unslash( (string) $_GET['asso_cause'] ) ) : '',
			'letter' => isset( $_GET['asso_letter'] ) ? strtoupper( substr( sanitize_text_field( wp_unslash( (string) $_GET['asso_letter'] ) ), 0, 1 ) ) : '',
			'paged'  => max( 1, $asso_page, $paged_from_get, $paged_from_query, $paged_from_page ),
		];
	}

	/** @param array<string,mixed> $filters */
	public static function get_asso_query_args( array $filters, int $posts_per_page = 9, string $fixed_cause = '' ): array {
		$asso_taxonomy = self::ASSO_TAXONOMY;
		$args = [
			'post_type'      => [ self::ASSO_POST_TYPE ],
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'paged'          => (int) $filters['paged'],
			's'              => (string) $filters['s'],
		];

		if ( '' === trim( (string) $filters['s'] ) && '' === (string) $filters['cause'] && '' === $fixed_cause && '' === (string) ( $filters['letter'] ?? '' ) ) {
			$args['orderby'] = 'rand';
		}

		if ( ! empty( $filters['letter'] ) ) {
			$args['post_title_like'] = sanitize_text_field( (string) $filters['letter'] ) . '%';
		}

		$target_cause = '' !== $fixed_cause ? $fixed_cause : (string) $filters['cause'];
		if ( '' !== $target_cause ) {
			$args['tax_query'] = [
				[
					'taxonomy' => $asso_taxonomy,
					'field'    => 'slug',
					'terms'    => $target_cause,
				],
			];
		}

		return $args;
	}

	public static function filter_asso_title_like( string $where, WP_Query $query ): string {
		global $wpdb;
		$title_like = $query->get( 'post_title_like' );
		if ( ! is_string( $title_like ) || '' === $title_like ) {
			return $where;
		}

		return $where . $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $title_like );
	}

	/** @return array<string,mixed> */
	public static function get_asso_card_data( int $post_id ): array {
		$site_url = trim( (string) \plaidact_campaign_core_get_field( 'url_web', $post_id ) );
		$excerpt  = trim( (string) \plaidact_campaign_core_get_field( 'resume_court', $post_id ) );
		if ( '' === $excerpt ) {
			$excerpt = wp_strip_all_tags( get_the_excerpt( $post_id ) );
		}
		return [
			'post_id'          => $post_id,
			'title'            => get_the_title( $post_id ),
			'permalink'        => get_permalink( $post_id ),
			'excerpt'          => wp_trim_words( $excerpt, 24, '…' ),
			'site_url'         => $site_url,
			'cause_terms'      => get_the_terms( $post_id, self::ASSO_TAXONOMY ) ?: [],
		];
	}

	/** @return array<string,array{label:string,url:string,icon:string}> */
	public static function get_asso_social_links( int $post_id ): array {
		$links = [];
		foreach ( self::SOCIAL_NETWORKS as $slug => $label ) {
			$url = trim( (string) \plaidact_campaign_core_get_field( 'social_' . $slug, $post_id ) );
			if ( '' === $url ) {
				continue;
			}
			$links[ $slug ] = [
				'label' => $label,
				'url'   => $url,
				'icon'  => self::get_simple_icon_url( $slug, self::SOCIAL_ICON_FALLBACK ),
			];
		}
		$custom_socials = self::parse_social_links_csv( (string) \plaidact_campaign_core_get_field( 'social_links_csv', $post_id ) );
		foreach ( $custom_socials as $index => $custom ) {
			$key = 'custom_' . $index;
			$links[ $key ] = [
				'label' => (string) $custom['label'],
				'url'   => (string) $custom['url'],
				'icon'  => self::get_simple_icon_url( sanitize_title( (string) $custom['label'] ), self::SOCIAL_ICON_FALLBACK ),
			];
		}
		return $links;
	}

	private static function get_simple_icon_url( string $slug, string $fallback = '' ): string {
		$slug = sanitize_title( $slug );
		$map  = [
			'linkedin' => 'linkedin',
			'x'        => 'x',
		];
		$candidate = $map[ $slug ] ?? $slug;
		if ( '' === $candidate ) {
			$candidate = $fallback;
		}
		if ( '' === $candidate ) {
			return '';
		}
		return 'https://cdn.simpleicons.org/' . rawurlencode( $candidate ) . '/2A1738';
	}

	/** @return array<int,array{post_id:int,title:string,permalink:string}> */
	public static function get_similar_asso( int $post_id, int $limit = 3 ): array {
		$asso_taxonomy = self::ASSO_TAXONOMY;
		$terms = get_the_terms( $post_id, $asso_taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return [];
		}
		$term_ids = wp_list_pluck( $terms, 'term_id' );
		$query    = new WP_Query(
			[
				'post_type'      => [ self::ASSO_POST_TYPE ],
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'post__not_in'   => [ $post_id ],
				'orderby'        => 'rand',
				'tax_query'      => [
					[
						'taxonomy' => $asso_taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					],
				],
			]
		);
		$items    = [];
		while ( $query->have_posts() ) {
			$query->the_post();
			$items[] = [
				'post_id'   => get_the_ID(),
				'title'     => get_the_title(),
				'permalink' => get_the_permalink(),
			];
		}
		wp_reset_postdata();

		return $items;
	}

	/** @return array{years: array<int,array{year:int,months:array<int,array{month:int,month_name:string,events:array}>}>, term: WP_Term|null} */
	public static function build_timeline_data( string $term_slug, bool $fill_empty_months = false ): array {
		$term = get_term_by( 'slug', $term_slug, 'agenda_timeline' );

		$query = new WP_Query(
			[
				'post_type'      => 'agenda',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => 'date_debut',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'tax_query'      => [
					[
						'taxonomy' => 'agenda_timeline',
						'field'    => 'slug',
						'terms'    => $term_slug,
					],
				],
			]
		);

		$events = [];
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();

			$start = self::parse_acf_date( (string) get_post_meta( $post_id, 'date_debut', true ) );
			$start_raw = (string) get_post_meta( $post_id, 'date_debut', true );
			if ( ! $start ) {
				continue;
			}
			$end = self::parse_acf_date( (string) get_post_meta( $post_id, 'date_fin', true ) );
			$config = \plaidact_campaign_core_get_field( 'configuration', $post_id );

			$external_link = (string) \plaidact_campaign_core_get_field( 'lien_evenement', $post_id );

			$events[] = [
				'id'          => $post_id,
				'title'       => get_the_title(),
				'date_debut'  => $start,
				'date_fin'    => $end,
				'has_day'     => self::has_day_precision( $start_raw ),
				'type'        => (string) get_post_meta( $post_id, 'type_evenement', true ),
				'config'      => is_array( $config ) ? $config : [],
				'lieu'        => (string) get_post_meta( $post_id, 'lieu', true ),
				'organisation_name' => (string) get_post_meta( $post_id, 'nom_organisation', true ),
				'mini_logo'   => (string) \plaidact_campaign_core_get_field( 'mini_logo', $post_id ),
				'url'         => '' !== $external_link ? $external_link : get_permalink( $post_id ),
				'is_external' => '' !== $external_link,
			];
		}
		wp_reset_postdata();

		$grouped = [];
		foreach ( $events as $event ) {
			if ( 'mensuels' === $event['type'] && $event['date_fin'] instanceof DateTimeImmutable ) {
				$cursor    = $event['date_debut']->modify( 'first day of this month' );
				$end_month = $event['date_fin']->modify( 'first day of this month' );
				$first     = $cursor;

				while ( $cursor <= $end_month ) {
					$slot                    = $event;
					$slot['is_continuation'] = $cursor > $first;
					$grouped[ (int) $cursor->format( 'Y' ) ][ (int) $cursor->format( 'n' ) ][] = $slot;
					$cursor = $cursor->modify( '+1 month' );
				}
			} else {
				$event['is_continuation'] = false;
				$grouped[ (int) $event['date_debut']->format( 'Y' ) ][ (int) $event['date_debut']->format( 'n' ) ][] = $event;
			}
		}

		if ( $fill_empty_months && ! empty( $grouped ) ) {
			$years_keys = array_keys( $grouped );
			$min_year = (int) min( $years_keys );
			$max_year = (int) max( $years_keys );
			for ( $y = $min_year; $y <= $max_year; $y++ ) {
				if ( ! isset( $grouped[ $y ] ) ) {
					$grouped[ $y ] = [];
				}
				for ( $m = 1; $m <= 12; $m++ ) {
					if ( ! isset( $grouped[ $y ][ $m ] ) ) {
						$grouped[ $y ][ $m ] = [];
					}
				}
			}
		}

		ksort( $grouped );
		$years = [];
		foreach ( $grouped as $year => $months ) {
			ksort( $months );
			$month_data = [];
			foreach ( $months as $month => $month_events ) {
				if ( empty( $month_events ) && ! $fill_empty_months ) {
					continue;
				}
				usort(
					$month_events,
					static fn( array $a, array $b ) => $a['date_debut']->getTimestamp() <=> $b['date_debut']->getTimestamp()
				);
				$month_data[] = [
					'month'      => (int) $month,
					'month_name' => self::month_name( (int) $month ),
					'events'     => $month_events,
				];
			}
			if ( empty( $month_data ) ) {
				continue;
			}
			$years[] = [
				'year'   => (int) $year,
				'months' => $month_data,
			];
		}

		return [
			'years' => $years,
			'term'  => $term instanceof WP_Term ? $term : null,
		];
	}


	public static function maybe_serve_timeline_ical(): void {
		$timeline = isset( $_GET['plaidact_timeline_ical'] ) ? sanitize_title( wp_unslash( (string) $_GET['plaidact_timeline_ical'] ) ) : '';
		if ( '' === $timeline ) {
			return;
		}

		self::serve_timeline_ical( $timeline );
	}

	private static function serve_timeline_ical( string $timeline_slug ): void {
		$payload = self::build_timeline_data( $timeline_slug, false );
		$term    = $payload['term'] ?? null;
		if ( ! $term instanceof WP_Term ) {
			status_header( 404 );
			exit;
		}

		$ical = self::build_timeline_ical_content( $payload );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="agenda-' . sanitize_file_name( $timeline_slug ) . '.ics"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		echo $ical; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/** @param array{years: array<int,array{year:int,months:array<int,array{month:int,month_name:string,events:array}>}>, term: WP_Term|null} $payload */
	private static function build_timeline_ical_content( array $payload ): string {
		$term = $payload['term'];
		$events = [];
		foreach ( $payload['years'] as $year_data ) {
			foreach ( $year_data['months'] as $month_data ) {
				foreach ( $month_data['events'] as $event ) {
					$events[ (int) $event['id'] ] = $event;
				}
			}
		}

		$now = gmdate( 'Ymd\THis\Z' );
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//PlaidAct//Agenda//FR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape_ical_text( $term instanceof WP_Term ? $term->name : __( 'Agenda PlaidAct', 'plaidact-campaign-core' ) ),
		];

		foreach ( $events as $event ) {
			$start = $event['date_debut'] instanceof DateTimeImmutable ? $event['date_debut'] : null;
			if ( ! $start ) {
				continue;
			}
			$end = $event['date_fin'] instanceof DateTimeImmutable ? $event['date_fin'] : null;
			if ( ! $end || $end < $start ) {
				$end = $start;
			}
			$end_exclusive = $end->modify( '+1 day' );
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:agenda-' . (int) $event['id'] . '-' . $start->format( 'Ymd' ) . '@plaidact.org';
			$lines[] = 'DTSTAMP:' . $now;
			$lines[] = 'DTSTART;VALUE=DATE:' . $start->format( 'Ymd' );
			$lines[] = 'DTEND;VALUE=DATE:' . $end_exclusive->format( 'Ymd' );
			$lines[] = 'SUMMARY:' . self::escape_ical_text( (string) ( $event['title'] ?? '' ) );
			if ( ! empty( $event['lieu'] ) ) {
				$lines[] = 'LOCATION:' . self::escape_ical_text( (string) $event['lieu'] );
			}
			if ( ! empty( $event['url'] ) ) {
				$lines[] = 'URL:' . esc_url_raw( (string) $event['url'] );
			}
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", $lines ) . "\r\n";
	}

	private static function escape_ical_text( string $value ): string {
		$value = str_replace( [ '\\', ';', ',', "\r\n", "\n", "\r" ], [ '\\\\', '\;', '\,', '\n', '\n', '\n' ], $value );
		return trim( $value );
	}

	public static function parse_acf_date( string $raw ): ?DateTimeImmutable {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		foreach ( [ 'Ymd', 'd/m/Y', 'Y-m-d' ] as $format ) {
			$date = DateTimeImmutable::createFromFormat( $format, $raw );
			if ( false !== $date ) {
				return $date->setTime( 0, 0, 0 );
			}
		}
		return null;
	}

	public static function month_name( int $month ): string {
		$months = [
			1 => __( 'Janvier', 'plaidact-campaign-core' ),
			2 => __( 'Février', 'plaidact-campaign-core' ),
			3 => __( 'Mars', 'plaidact-campaign-core' ),
			4 => __( 'Avril', 'plaidact-campaign-core' ),
			5 => __( 'Mai', 'plaidact-campaign-core' ),
			6 => __( 'Juin', 'plaidact-campaign-core' ),
			7 => __( 'Juillet', 'plaidact-campaign-core' ),
			8 => __( 'Août', 'plaidact-campaign-core' ),
			9 => __( 'Septembre', 'plaidact-campaign-core' ),
			10 => __( 'Octobre', 'plaidact-campaign-core' ),
			11 => __( 'Novembre', 'plaidact-campaign-core' ),
			12 => __( 'Décembre', 'plaidact-campaign-core' ),
		];
		return $months[ $month ] ?? '';
	}

	public static function month_abbr( int $month ): string {
		$months = [ 1 => 'jan.', 2 => 'fév.', 3 => 'mars', 4 => 'avr.', 5 => 'mai', 6 => 'juin', 7 => 'juil.', 8 => 'août', 9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.' ];
		return $months[ $month ] ?? '';
	}

	public static function format_date_short( DateTimeImmutable $date ): string {
		return $date->format( 'j' ) . ' ' . self::month_abbr( (int) $date->format( 'n' ) ) . ' ' . $date->format( 'Y' );
	}

	private static function has_day_precision( string $raw ): bool {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return true;
		}
		if ( 1 === preg_match( '/^\d{4}(0[1-9]|1[0-2])$/', $raw ) ) {
			return false;
		}
		if ( 1 === preg_match( '/^\d{4}\-(0[1-9]|1[0-2])$/', $raw ) ) {
			return false;
		}
		if ( 1 === preg_match( '/^(0[1-9]|1[0-2])\/\d{4}$/', $raw ) ) {
			return false;
		}
		return true;
	}

	public static function hover_term_shortcode( array $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'type' => 'definition',
				'id'   => '',
				'text' => '',
			],
			$atts,
			'plaidact_hover_term'
		);

		$type = sanitize_key( (string) $atts['type'] );
		$id   = sanitize_title( (string) $atts['id'] );
		$text = sanitize_text_field( (string) $atts['text'] );
		if ( '' === $id ) {
			return $text;
		}
		return self::render_hover_token( $type, $id, $text );
	}

	public static function replace_hover_tokens_in_content( string $content ): string {
		if ( false === strpos( $content, '[[' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'/\[\[(definition|asso):([a-zA-Z0-9\-_]+)\|([^\]]+)\]\]/',
			static function ( array $matches ): string {
				return self::render_hover_token(
					sanitize_key( (string) $matches[1] ),
					sanitize_title( (string) $matches[2] ),
					sanitize_text_field( (string) $matches[3] )
				);
			},
			$content
		);
	}

	public static function auto_inject_hover_terms_in_content( string $content ): string {
		if ( '' === trim( $content ) ) {
			return $content;
		}

		$terms_map = get_transient( 'plaidact_hover_terms_map_v1' );
		if ( ! is_array( $terms_map ) ) {
			$terms_map = [];

			$definitions = get_posts(
				[
					'post_type'      => 'pa_definition',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				]
			);
			$associations = get_posts(
				[
					'post_type'      => [ self::ASSO_POST_TYPE ],
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				]
			);

			foreach ( $definitions as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$title = trim( (string) $post->post_title );
				$slug  = (string) $post->post_name;
				if ( '' === $title || '' === $slug ) {
					continue;
				}
				$terms_map[ mb_strtolower( $title ) ] = [
					'text'   => $title,
					'type'   => 'definition',
					'id'     => $slug,
					'needle' => preg_quote( $title, '/' ),
				];
			}
			foreach ( $associations as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$title = trim( (string) $post->post_title );
				$slug  = (string) $post->post_name;
				if ( '' === $title || '' === $slug ) {
					continue;
				}
				$terms_map[ mb_strtolower( $title ) ] = [
					'text'   => $title,
					'type'   => 'asso',
					'id'     => $slug,
					'needle' => preg_quote( $title, '/' ),
				];
			}
		}

		if ( empty( $terms_map ) ) {
			return $content;
		}

		uasort(
			$terms_map,
			static fn( array $a, array $b ): int => mb_strlen( (string) $b['text'] ) <=> mb_strlen( (string) $a['text'] )
		);
		set_transient( 'plaidact_hover_terms_map_v1', $terms_map, HOUR_IN_SECONDS );

		$parts = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $content;
		}
		$is_inside_link = false;
		foreach ( $parts as $index => $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( str_starts_with( $part, '<' ) ) {
				if ( 1 === preg_match( '/^<a\b/i', $part ) ) {
					$is_inside_link = true;
				}
				if ( 1 === preg_match( '/^<\/a\b/i', $part ) ) {
					$is_inside_link = false;
				}
				continue;
			}
			if ( $is_inside_link ) {
				continue;
			}
			foreach ( $terms_map as $item ) {
				$needle = self::build_hover_needle_pattern( (string) ( $item['text'] ?? '' ) );
				if ( '' === $needle ) {
					continue;
				}

				$parts[ $index ] = (string) preg_replace_callback(
					'/(?<![\p{L}\p{N}])(' . $needle . ')(?![\p{L}\p{N}])/ui',
					static function ( array $matches ) use ( $item ): string {
						return self::render_hover_token( (string) $item['type'], (string) $item['id'], (string) $matches[1] );
					},
					(string) $parts[ $index ]
				);
			}
		}

		return implode( '', $parts );
	}


	private static function build_hover_needle_pattern( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		$pattern = preg_quote( $text, '/' );
		$pattern = str_replace( '\ ', '[\s\x{00A0}]+', $pattern );
		$pattern = str_replace( [ "\'", '’' ], "[’']", $pattern );
		$pattern = str_replace( '\-', '[\-\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}]', $pattern );

		return $pattern;
	}

	private static function render_hover_token( string $type, string $id, string $text ): string {
		$card = self::get_hover_card_data( $type, $id );
		if ( empty( $card ) ) {
			return esc_html( $text );
		}

		return sprintf(
			'<span class="plaidact-hover-term" tabindex="0">%1$s<span class="plaidact-hover-card"><span class="plaidact-hover-card__inner">%2$s</span></span></span>',
			esc_html( $text ),
			self::render_hover_card_inner( $card )
		);
	}

	/** @return array<string,string> */
	private static function get_hover_card_data( string $type, string $id ): array {
		if ( 'asso' === $type ) {
			$post = get_page_by_path( $id, OBJECT, [ self::ASSO_POST_TYPE ] );
			if ( ! $post instanceof WP_Post ) {
				return [];
			}
			return [
				'title'       => get_the_title( $post ),
				'description' => wp_trim_words( (string) \plaidact_campaign_core_get_field( 'resume_court', $post->ID ), 20, '…' ),
				'logo'        => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
				'url'         => get_permalink( $post ),
				'cta'         => __( 'En savoir plus', 'plaidact-campaign-core' ),
			];
		}
		$post = get_page_by_path( $id, OBJECT, 'pa_definition' );
		if ( ! $post instanceof WP_Post ) {
			return [];
		}
		return [
			'title'       => get_the_title( $post ),
			'description' => wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 20, '…' ),
			'logo'        => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
			'url'         => '',
			'cta'         => __( 'Définition', 'plaidact-campaign-core' ),
		];
	}

	/** @param array<string,string> $card */
	private static function render_hover_card_inner( array $card ): string {
		$html = '';
		if ( ! empty( $card['logo'] ) ) {
			$html .= '<img class="plaidact-hover-card__logo" src="' . esc_url( (string) $card['logo'] ) . '" alt="" loading="lazy" decoding="async" />';
		}
		$html .= '<strong class="plaidact-hover-card__title">' . esc_html( (string) $card['title'] ) . '</strong>';
		$html .= '<span class="plaidact-hover-card__desc">' . esc_html( (string) $card['description'] ) . '</span>';
		if ( ! empty( $card['url'] ) ) {
			$html .= '<a class="plaidact-hover-card__btn" href="' . esc_url( (string) $card['url'] ) . '">' . esc_html( (string) $card['cta'] ) . '</a>';
		}
		return $html;
	}

	/** @param array<string,mixed> $vars */
	public static function render_template( string $template, array $vars = [] ): void {
		$file = PLAIDACT_CORE_PATH . 'templates/' . ltrim( $template, '/' );
		if ( ! file_exists( $file ) ) {
			return;
		}
		extract( $vars, EXTR_SKIP );
		require $file;
	}

	public static function get_asso_taxonomy(): string {
		return self::ASSO_TAXONOMY;
	}

	/** @return string[] */
	private static function get_asso_import_headers(): array {
		return [
			'title',
			'slug',
			'logo_url',
			'logo_file',
			'url_web',
			'url_don',
			'url_contact',
			'causes',
			'resume_court',
			'social_facebook',
			'social_x',
			'social_instagram',
			'social_linkedin',
			'social_youtube',
			'social_tiktok',
			'social_twitch',
			'social_whatsapp',
			'social_telegram',
			'social_discord',
			'social_bluesky',
			'social_links_csv',
		];
	}

	public static function handle_asso_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}
		check_admin_referer( 'plaidact_import_asso' );
		if ( empty( $_FILES['asso_csv']['tmp_name'] ) ) {
			self::redirect_import_error( __( 'CSV manquant.', 'plaidact-campaign-core' ) );
		}

		$logo_import = self::extract_logos_zip( $_FILES['asso_logos_zip'] ?? null );
		$logos_map   = $logo_import['map'];
		$handle = fopen( (string) $_FILES['asso_csv']['tmp_name'], 'rb' );
		if ( false === $handle ) {
			self::redirect_import_error( __( 'Impossible de lire le CSV.', 'plaidact-campaign-core' ) );
		}

		$first_line = (string) fgets( $handle );
		rewind( $handle );
		$delimiter = substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ? ';' : ',';
		$headers = fgetcsv( $handle, 0, $delimiter );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			self::redirect_import_error( __( 'CSV invalide.', 'plaidact-campaign-core' ) );
		}
		$headers = array_map( static fn( $h ) => sanitize_key( (string) $h ), $headers );

		$count = 0;
		$duplicate_count = 0;
		$dedupe_map = [];
		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$data = [];
			foreach ( $headers as $index => $header ) {
				$data[ $header ] = isset( $row[ $index ] ) ? self::sanitize_import_value( $header, (string) $row[ $index ] ) : '';
			}
			$title = $data['title'] ?? '';
			if ( '' === $title ) {
				continue;
			}
			$signature = self::normalize_dedupe_key( implode( '|', [ (string) $title, (string) ( $data['slug'] ?? '' ) ] ) );
			if ( isset( $dedupe_map[ $signature ] ) ) {
				$duplicate_count++;
				continue;
			}
			$dedupe_map[ $signature ] = true;

			$post_id = self::upsert_asso_post( $data );
			if ( $post_id <= 0 ) {
				continue;
			}
			self::sync_asso_meta( $post_id, $data );
			self::sync_asso_causes( $post_id, (string) ( $data['causes'] ?? '' ) );
			self::sync_asso_logo( $post_id, $data, $logos_map );
			$count++;
		}
		fclose( $handle );
		self::cleanup_import_directory( $logo_import['dir'] );

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => self::ASSO_POST_TYPE,
					'page'      => 'plaidact-asso-import',
					'status'    => 'ok',
					'count'     => $count,
					'dupes'     => $duplicate_count,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	public static function handle_asso_export_csv(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}
		check_admin_referer( 'plaidact_export_asso_csv' );

		$query = new WP_Query(
			[
				'post_type'      => self::ASSO_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="export-associations.csv"' );

		$output = fopen( 'php://output', 'wb' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Impossible de générer le fichier CSV.', 'plaidact-campaign-core' ) );
		}

		fputcsv( $output, self::get_asso_import_headers() );

		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$terms   = wp_get_post_terms( $post_id, self::ASSO_TAXONOMY, [ 'fields' => 'names' ] );
			$social_links_rows = \plaidact_campaign_core_get_field( 'social_links', $post_id );
			$social_links_lines = [];
			if ( is_array( $social_links_rows ) ) {
				foreach ( $social_links_rows as $row ) {
					$label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
					$url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
					if ( '' !== $label && '' !== $url ) {
						$social_links_lines[] = $label . '|' . $url;
					}
				}
			}

			fputcsv(
				$output,
				[
					get_the_title(),
					get_post_field( 'post_name', $post_id ),
					'',
					'',
					(string) \plaidact_campaign_core_get_field( 'url_web', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'url_don', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'url_contact', $post_id ),
					implode( '|', is_array( $terms ) ? $terms : [] ),
					(string) \plaidact_campaign_core_get_field( 'resume_court', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_facebook', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_x', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_instagram', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_linkedin', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_youtube', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_tiktok', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_twitch', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_whatsapp', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_telegram', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_discord', $post_id ),
					(string) \plaidact_campaign_core_get_field( 'social_bluesky', $post_id ),
					implode( "\n", $social_links_lines ),
				]
			);
		}

		wp_reset_postdata();
		fclose( $output );
		exit;
	}

	public static function handle_agenda_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}
		check_admin_referer( 'plaidact_import_agenda' );
		if ( empty( $_FILES['agenda_csv']['tmp_name'] ) ) {
			self::redirect_agenda_import( 0, 0 );
		}

		$handle = fopen( (string) $_FILES['agenda_csv']['tmp_name'], 'rb' );
		if ( false === $handle ) {
			self::redirect_agenda_import( 0, 0 );
		}
		$first_line = (string) fgets( $handle );
		rewind( $handle );
		$delimiter = substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ? ';' : ',';
		$headers = fgetcsv( $handle, 0, $delimiter );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			self::redirect_agenda_import( 0, 0 );
		}
		$headers = array_map( static fn( $h ) => sanitize_key( (string) $h ), $headers );

		$count = 0;
		$dupes = 0;
		$seen  = [];
		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$data = [];
			foreach ( $headers as $i => $header ) {
				$data[ $header ] = isset( $row[ $i ] ) ? self::sanitize_import_value( $header, (string) $row[ $i ] ) : '';
			}

			$title = (string) ( $data['title'] ?? '' );
			if ( '' === $title ) {
				continue;
			}
			$signature = self::normalize_dedupe_key( $title . '|' . (string) ( $data['date_debut'] ?? '' ) . '|' . (string) ( $data['timeline'] ?? '' ) );
			if ( isset( $seen[ $signature ] ) ) {
				$dupes++;
				continue;
			}
			$seen[ $signature ] = true;

			$post_id = self::upsert_agenda_post( $data );
			if ( $post_id <= 0 ) {
				continue;
			}
			update_post_meta( $post_id, 'date_debut', (string) ( $data['date_debut'] ?? '' ) );
			update_post_meta( $post_id, 'date_fin', (string) ( $data['date_fin'] ?? '' ) );
			update_post_meta( $post_id, 'type_evenement', (string) ( $data['type_evenement'] ?? '' ) );
			update_post_meta( $post_id, 'lieu', (string) ( $data['lieu'] ?? '' ) );
			update_post_meta( $post_id, 'nom_organisation', (string) ( $data['nom_organisation'] ?? '' ) );
			\plaidact_campaign_core_update_field( 'lien_evenement', (string) ( $data['lien_evenement'] ?? '' ), $post_id );
			self::sync_agenda_timeline_term( $post_id, (string) ( $data['timeline'] ?? '' ) );
			$count++;
		}
		fclose( $handle );
		self::redirect_agenda_import( $count, $dupes );
	}

	public static function handle_asso_taxonomy_migration(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}
		check_admin_referer( 'plaidact_migrate_asso_taxonomies' );

		$posts = get_posts(
			[
				'post_type'      => self::ASSO_POST_TYPE,
				'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		$migrated_posts = 0;
		$migrated_terms = 0;

		foreach ( $posts as $post_id ) {
			$legacy_terms = wp_get_object_terms(
				(int) $post_id,
				'category',
				[
					'fields' => 'names',
				]
			);
			if ( is_wp_error( $legacy_terms ) || ! is_array( $legacy_terms ) || [] === $legacy_terms ) {
				continue;
			}

			$target_term_ids = [];
			foreach ( $legacy_terms as $legacy_term_name ) {
				$legacy_term_name = sanitize_text_field( (string) $legacy_term_name );
				if ( '' === $legacy_term_name ) {
					continue;
				}
				$term = term_exists( $legacy_term_name, self::ASSO_TAXONOMY );
				if ( 0 === $term || null === $term ) {
					$term = wp_insert_term( $legacy_term_name, self::ASSO_TAXONOMY );
				}
				if ( is_wp_error( $term ) ) {
					continue;
				}
				$term_id = (int) ( is_array( $term ) ? ( $term['term_id'] ?? 0 ) : $term );
				if ( $term_id > 0 ) {
					$target_term_ids[] = $term_id;
				}
			}

			if ( [] === $target_term_ids ) {
				continue;
			}

			$assigned = wp_set_object_terms( (int) $post_id, array_values( array_unique( $target_term_ids ) ), self::ASSO_TAXONOMY, true );
			if ( is_wp_error( $assigned ) ) {
				continue;
			}
			$migrated_posts++;
			$migrated_terms += count( $target_term_ids );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'      => self::ASSO_POST_TYPE,
					'page'           => 'plaidact-asso-import',
					'status'         => 'taxonomy_migrated',
					'migrated_posts' => $migrated_posts,
					'migrated_terms' => $migrated_terms,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	private static function redirect_import_error( string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => self::ASSO_POST_TYPE,
					'page'      => 'plaidact-asso-import',
					'status'    => 'error',
					'error'     => $message,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/** @param array<string,string> $data */
	private static function upsert_asso_post( array $data ): int {
		$slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
		$post = null;
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, self::ASSO_POST_TYPE );
		}

		$postarr = [
			'post_type'   => self::ASSO_POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( (string) $data['title'] ),
			'post_content'=> '',
		];
		if ( $post instanceof WP_Post ) {
			$postarr['ID'] = $post->ID;
			return (int) wp_update_post( $postarr );
		}
		if ( '' !== $slug ) {
			$postarr['post_name'] = $slug;
		}
		return (int) wp_insert_post( $postarr );
	}

	/** @param array<string,string> $data */
	private static function sync_asso_meta( int $post_id, array $data ): void {
		$meta_keys = [ 'url_web', 'url_don', 'url_contact', 'resume_court', 'social_links_csv' ];
		foreach ( $meta_keys as $key ) {
			if ( isset( $data[ $key ] ) ) {
				\plaidact_campaign_core_update_field( $key, $data[ $key ], $post_id );
			}
		}

		foreach ( self::SOCIAL_NETWORKS as $slug => $_label ) {
			$key = 'social_' . $slug;
			if ( isset( $data[ $key ] ) ) {
				\plaidact_campaign_core_update_field( $key, $data[ $key ], $post_id );
			}
		}
	}

	/** @return array<int,array{label:string,url:string}> */
	private static function parse_social_links_csv( string $raw ): array {
		$entries = preg_split( '/\r\n|\r|\n/', trim( $raw ) );
		if ( ! is_array( $entries ) ) {
			return [];
		}
		$parsed = [];
		foreach ( $entries as $entry ) {
			if ( '' === trim( $entry ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $entry, 2 ) );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			if ( '' === $parts[0] || '' === $parts[1] ) {
				continue;
			}
			$parsed[] = [
				'label' => $parts[0],
				'url'   => $parts[1],
			];
		}
		return $parsed;
	}

	private static function sync_asso_causes( int $post_id, string $causes_raw ): void {
		if ( '' === $causes_raw ) {
			return;
		}
		$names = array_filter( array_map( 'trim', explode( '|', $causes_raw ) ) );
		if ( empty( $names ) ) {
			return;
		}
		$term_ids = [];
		foreach ( $names as $name ) {
			$term = term_exists( $name, self::ASSO_TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $name, self::ASSO_TAXONOMY );
			}
			if ( is_array( $term ) && isset( $term['term_id'] ) ) {
				$term_ids[] = (int) $term['term_id'];
			}
		}
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, self::ASSO_TAXONOMY, false );
		}
	}

	/** @param array<string,string> $data @param array<string,string> $logos_map */
	private static function sync_asso_logo( int $post_id, array $data, array $logos_map ): void {
		$logo_url = esc_url_raw( trim( (string) ( $data['logo_url'] ?? '' ) ) );
		$logo_file = trim( (string) ( $data['logo_file'] ?? '' ) );
		$source = $logo_url;
		if ( '' === $source && '' !== $logo_file && isset( $logos_map[ $logo_file ] ) ) {
			$source = $logos_map[ $logo_file ];
		}
		if ( '' === $source ) {
			return;
		}
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( self::is_local_file_path( $source ) ) {
			$attachment_id = self::insert_attachment_from_local_file( $source, $post_id );
		} else {
			$attachment_id = media_sideload_image( esc_url_raw( $source ), $post_id, null, 'id' );
		}
		if ( ! is_wp_error( $attachment_id ) && is_int( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	private static function is_local_file_path( string $path ): bool {
		return '' !== $path && ( str_starts_with( $path, '/' ) || preg_match( '/^[A-Za-z]:[\/\\\\]/', $path ) );
	}

	private static function insert_attachment_from_local_file( string $file_path, int $post_id ): int|\WP_Error {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new \WP_Error( 'missing_logo_file', __( 'Fichier logo introuvable.', 'plaidact-campaign-core' ) );
		}

		$wp_filetype = wp_check_filetype( basename( $file_path ), null );
		$attachment  = [
			'guid'           => wp_upload_dir()['url'] . '/' . basename( $file_path ),
			'post_mime_type' => $wp_filetype['type'] ?? '',
			'post_title'     => sanitize_file_name( pathinfo( $file_path, PATHINFO_FILENAME ) ),
			'post_status'    => 'inherit',
		];

		$attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id );
		if ( ! is_int( $attachment_id ) || $attachment_id <= 0 ) {
			return new \WP_Error( 'attachment_failed', __( 'Impossible d’ajouter le logo.', 'plaidact-campaign-core' ) );
		}

		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		if ( is_array( $attachment_data ) ) {
			wp_update_attachment_metadata( $attachment_id, $attachment_data );
		}
		return $attachment_id;
	}

	/** @param array<string,mixed>|null $zip_file @return array{map:array<string,string>,dir:string} */
	private static function extract_logos_zip( ?array $zip_file ): array {
		if ( empty( $zip_file['tmp_name'] ) || ! class_exists( 'ZipArchive' ) ) {
			return [ 'map' => [], 'dir' => '' ];
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( (string) $zip_file['tmp_name'] ) ) {
			return [ 'map' => [], 'dir' => '' ];
		}
		$upload = wp_upload_dir();
		$base_dir = trailingslashit( $upload['basedir'] ) . 'plaidact-import-logos-' . uniqid( '', true );
		wp_mkdir_p( $base_dir );
		$zip->extractTo( $base_dir );
		$zip->close();

		$map = [];
		$files = glob( $base_dir . '/*' );
		if ( ! is_array( $files ) ) {
			return [ 'map' => [], 'dir' => $base_dir ];
		}
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$map[ basename( $file ) ] = $file;
			}
		}
		return [ 'map' => $map, 'dir' => $base_dir ];
	}

	private static function cleanup_import_directory( string $directory ): void {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}
		$items = glob( trailingslashit( $directory ) . '*' );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( is_file( $item ) ) {
					wp_delete_file( $item );
				}
			}
		}
		@rmdir( $directory );
	}

	private static function sanitize_import_value( string $header, string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$url_fields = [
			'logo_url',
			'url_web',
			'url_don',
			'url_contact',
			'social_facebook',
			'social_x',
			'social_instagram',
			'social_linkedin',
			'social_youtube',
			'social_tiktok',
			'social_twitch',
			'social_whatsapp',
			'social_telegram',
			'social_discord',
			'social_bluesky',
			'lien_evenement',
		];
		if ( in_array( $header, $url_fields, true ) ) {
			return esc_url_raw( $value );
		}
		if ( in_array( $header, [ 'resume_court' ], true ) ) {
			return sanitize_textarea_field( $value );
		}
		if ( in_array( $header, [ 'social_links_csv' ], true ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $value );
			if ( ! is_array( $lines ) ) {
				return '';
			}
			$clean = [];
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = explode( '|', $line, 2 );
				if ( 2 !== count( $parts ) ) {
					continue;
				}
				$label = sanitize_text_field( trim( $parts[0] ) );
				$url   = esc_url_raw( trim( $parts[1] ) );
				if ( '' === $label || '' === $url ) {
					continue;
				}
				$clean[] = $label . '|' . $url;
			}
			return implode( "\n", $clean );
		}
		return sanitize_text_field( $value );
	}

	private static function redirect_agenda_import( int $count, int $dupes ): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => 'agenda',
					'page'      => 'plaidact-agenda-import',
					'status'    => 'ok',
					'count'     => $count,
					'dupes'     => $dupes,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/** @param array<string,string> $data */
	private static function upsert_agenda_post( array $data ): int {
		$slug = sanitize_title( (string) ( $data['slug'] ?? '' ) );
		$post = '' !== $slug ? get_page_by_path( $slug, OBJECT, 'agenda' ) : null;
		$postarr = [
			'post_type'    => 'agenda',
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'post_content' => '',
		];
		if ( $post instanceof WP_Post ) {
			$postarr['ID'] = $post->ID;
			return (int) wp_update_post( $postarr );
		}
		if ( '' !== $slug ) {
			$postarr['post_name'] = $slug;
		}
		return (int) wp_insert_post( $postarr );
	}

	private static function sync_agenda_timeline_term( int $post_id, string $timeline_name ): void {
		$timeline_name = trim( $timeline_name );
		if ( '' === $timeline_name ) {
			return;
		}
		$term = term_exists( $timeline_name, 'agenda_timeline' );
		if ( ! $term ) {
			$term = wp_insert_term( $timeline_name, 'agenda_timeline' );
		}
		if ( is_array( $term ) && isset( $term['term_id'] ) ) {
			wp_set_object_terms( $post_id, [ (int) $term['term_id'] ], 'agenda_timeline', false );
		}
	}

	private static function normalize_dedupe_key( string $value ): string {
		$normalized = remove_accents( mb_strtolower( trim( $value ) ) );
		$normalized = preg_replace( '/\s+/', ' ', $normalized );
		return is_string( $normalized ) ? $normalized : '';
	}
}
