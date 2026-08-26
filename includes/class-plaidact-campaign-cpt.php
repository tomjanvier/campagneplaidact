<?php
/**
 * Enregistrement des types de contenus PLAID·ACT.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enregistre les types de contenus et les métadonnées des partenaires.
 */
final class CPT {

	/**
	 * Version du schéma utilisée pour les migrations et règles de réécriture.
	 */
	private const CONTENT_SCHEMA_VERSION = '2.1.0';

	/**
	 * Option stockant la version de schéma entièrement appliquée.
	 */
	private const CONTENT_SCHEMA_OPTION = 'plaidact_core_content_schema_version';

	/**
	 * Ancien type de brève enregistré par le thème PLAID·ACT historique.
	 */
	private const LEGACY_BREVE_POST_TYPE = 'breves';

	/**
	 * Type canonique des brèves géré par cette extension.
	 */
	private const BREVE_POST_TYPE = 'plaid_breve';

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( __CLASS__, 'migrate_legacy_breves' ), 90 );
		add_action( 'init', array( __CLASS__, 'unregister_legacy_breve_post_type' ), 1000 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 1100 );
		add_action( 'pre_get_posts', array( __CLASS__, 'map_legacy_breve_query' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_breve_export_fields' ) );
		add_action( 'init', array( __CLASS__, 'register_partner_meta' ) );
		add_action( 'init', array( __CLASS__, 'register_social_embed_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_partner_metabox' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_social_embed_metabox' ) );
		add_action( 'save_post_plaid_partner', array( __CLASS__, 'save_partner_url' ) );
		add_action( 'save_post_plaid_social_embed', array( __CLASS__, 'save_social_embed_meta' ) );
	}

	/**
	 * Ajoute aux exports REST des champs de brève directement lisibles.
	 *
	 * WordPress expose par défaut le contenu sous forme d'objet imbriqué et les
	 * thématiques comme identifiants de termes. Les exports ont besoin de
	 * valeurs simples et immédiatement exploitables.
	 *
	 * @return void
	 */
	public static function register_breve_export_fields(): void {
		register_rest_field(
			'plaid_breve',
			'texte',
			array(
				'get_callback' => static function ( array $post ): string {
					return (string) get_post_field( 'post_content', (int) $post['id'], 'raw' );
				},
				'schema'       => array(
					'description' => __( 'Texte brut de la brève.', 'plaidact-campaign-core' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);

		register_rest_field(
			'plaid_breve',
			'thematiques',
			array(
				'get_callback' => static function ( array $post ): array {
					$terms = wp_get_post_terms(
						(int) $post['id'],
						'plaid_breve_topic',
						array( 'fields' => 'names' )
					);

					return is_wp_error( $terms ) ? array() : $terms;
				},
				'schema'       => array(
					'description' => __( 'Noms des thématiques de la brève.', 'plaidact-campaign-core' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * Enregistre les types de contenus PLAID·ACT.
	 *
	 * @return void
	 */
	public static function register_post_types(): void {
		register_post_type(
			self::BREVE_POST_TYPE,
			array(
				'labels'       => array(
					'name'               => __( 'Brèves', 'plaidact-campaign-core' ),
					'singular_name'      => __( 'Brève', 'plaidact-campaign-core' ),
					'add_new'            => __( 'Ajouter', 'plaidact-campaign-core' ),
					'add_new_item'       => __( 'Ajouter une brève', 'plaidact-campaign-core' ),
					'edit_item'          => __( 'Modifier la brève', 'plaidact-campaign-core' ),
					'new_item'           => __( 'Nouvelle brève', 'plaidact-campaign-core' ),
					'view_item'          => __( 'Voir la brève', 'plaidact-campaign-core' ),
					'search_items'       => __( 'Rechercher une brève', 'plaidact-campaign-core' ),
					'not_found'          => __( 'Aucune brève trouvée', 'plaidact-campaign-core' ),
					'not_found_in_trash' => __( 'Aucune brève dans la corbeille', 'plaidact-campaign-core' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-megaphone',
				'menu_position'=> 22,
				'has_archive'  => 'breves',
				'rewrite'      => array(
					'slug'       => 'breves',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			)
		);

		register_post_type(
			'plaid_newsletter',
			array(
				'labels'       => array(
					'name'               => __( 'Newsletters', 'plaidact-campaign-core' ),
					'singular_name'      => __( 'Newsletter', 'plaidact-campaign-core' ),
					'add_new'            => __( 'Ajouter', 'plaidact-campaign-core' ),
					'add_new_item'       => __( 'Ajouter une newsletter', 'plaidact-campaign-core' ),
					'edit_item'          => __( 'Modifier la newsletter', 'plaidact-campaign-core' ),
					'new_item'           => __( 'Nouvelle newsletter', 'plaidact-campaign-core' ),
					'view_item'          => __( 'Voir la newsletter', 'plaidact-campaign-core' ),
					'view_items'         => __( 'Voir les newsletters', 'plaidact-campaign-core' ),
					'search_items'       => __( 'Rechercher une newsletter', 'plaidact-campaign-core' ),
					'not_found'          => __( 'Aucune newsletter trouvée', 'plaidact-campaign-core' ),
					'not_found_in_trash' => __( 'Aucune newsletter dans la corbeille', 'plaidact-campaign-core' ),
					'all_items'          => __( 'Toutes les newsletters', 'plaidact-campaign-core' ),
					'archives'           => __( 'Archives des newsletters', 'plaidact-campaign-core' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'newsletters',
				'menu_icon'    => 'dashicons-email-alt2',
				'menu_position'=> 21,
				'has_archive'  => 'newsletters',
				'rewrite'      => array(
					'slug'       => 'newsletter',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			)
		);

		register_post_type(
			'plaid_agenda_event',
			array(
				'labels'       => array(
					'name'               => __( 'Agenda', 'plaidact-campaign-core' ),
					'singular_name'      => __( 'Événement', 'plaidact-campaign-core' ),
					'add_new'            => __( 'Ajouter', 'plaidact-campaign-core' ),
					'add_new_item'       => __( 'Ajouter un événement', 'plaidact-campaign-core' ),
					'edit_item'          => __( 'Modifier l’événement', 'plaidact-campaign-core' ),
					'new_item'           => __( 'Nouvel événement', 'plaidact-campaign-core' ),
					'view_item'          => __( 'Voir l’événement', 'plaidact-campaign-core' ),
					'search_items'       => __( 'Rechercher un événement', 'plaidact-campaign-core' ),
					'not_found'          => __( 'Aucun événement trouvé', 'plaidact-campaign-core' ),
					'not_found_in_trash' => __( 'Aucun événement dans la corbeille', 'plaidact-campaign-core' ),
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-calendar-alt',
				'menu_position'=> 23,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'agenda' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			)
		);

		register_post_type(
			'plaid_partner',
			array(
				'labels'       => array(
					'name'               => __( 'Organisations porteuses', 'plaidact-campaign-core' ),
					'singular_name'      => __( 'Organisation porteuse', 'plaidact-campaign-core' ),
					'add_new'            => __( 'Ajouter', 'plaidact-campaign-core' ),
					'add_new_item'       => __( 'Ajouter une organisation porteuse', 'plaidact-campaign-core' ),
					'edit_item'          => __( 'Modifier l’organisation porteuse', 'plaidact-campaign-core' ),
					'new_item'           => __( 'Nouvelle organisation porteuse', 'plaidact-campaign-core' ),
					'view_item'          => __( 'Voir le partenaire', 'plaidact-campaign-core' ),
					'search_items'       => __( 'Rechercher une organisation porteuse', 'plaidact-campaign-core' ),
					'not_found'          => __( 'Aucune organisation porteuse trouvée', 'plaidact-campaign-core' ),
					'not_found_in_trash' => __( 'Aucune organisation porteuse dans la corbeille', 'plaidact-campaign-core' ),
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => 'plaidact-campaign-admin',
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-groups',
				'menu_position'=> 22,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'partenaires' ),
				'supports'     => array( 'title', 'thumbnail', 'page-attributes' ),
			)
		);

		register_post_type(
			'plaid_social_embed',
			array(
				'labels'       => array(
					'name'               => __( 'Social wall embeds', 'plaidact-campaign-core' ),
					'singular_name'      => __( 'Social embed', 'plaidact-campaign-core' ),
					'add_new'            => __( 'Ajouter', 'plaidact-campaign-core' ),
					'add_new_item'       => __( 'Ajouter un post social', 'plaidact-campaign-core' ),
					'edit_item'          => __( 'Modifier le post social', 'plaidact-campaign-core' ),
					'new_item'           => __( 'Nouveau post social', 'plaidact-campaign-core' ),
					'view_item'          => __( 'Voir le post social', 'plaidact-campaign-core' ),
					'search_items'       => __( 'Rechercher un post social', 'plaidact-campaign-core' ),
					'not_found'          => __( 'Aucun post social trouvé', 'plaidact-campaign-core' ),
					'not_found_in_trash' => __( 'Aucun post social dans la corbeille', 'plaidact-campaign-core' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-share',
				'menu_position'=> 24,
				'supports'     => array( 'title', 'page-attributes' ),
			)
		);

	}

	/**
	 * Migrates legacy `breves` posts to the canonical `plaid_breve` type.
	 *
	 * Post IDs, publication dates, content, media, metadata and Polylang term
	 * relationships are preserved. Using wp_update_post() also resolves a rare
	 * slug collision safely instead of overwriting an existing brief.
	 *
	 * @return void
	 */
	public static function migrate_legacy_breves(): void {
		if ( self::CONTENT_SCHEMA_VERSION === get_option( self::CONTENT_SCHEMA_OPTION ) ) {
			return;
		}

		global $wpdb;

		$legacy_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC",
				self::LEGACY_BREVE_POST_TYPE
			)
		);

		$migration_complete = true;

		foreach ( array_map( 'absint', $legacy_ids ) as $post_id ) {
			$result = wp_update_post(
				array(
					'ID'        => $post_id,
					'post_type' => self::BREVE_POST_TYPE,
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				$migration_complete = false;
			}
		}

		if ( $migration_complete ) {
			update_option( self::CONTENT_SCHEMA_OPTION, self::CONTENT_SCHEMA_VERSION, false );
		}
	}

	/**
	 * Retire le menu en double après la migration des contenus historiques.
	 *
	 * L'ancien thème peut encore enregistrer `breves` pendant `init`. Une
	 * exécution tardive conserve uniquement le type canonique dans l'éditeur,
	 * sans modifier les URL ni l'archive `/breves/`.
	 *
	 * @return void
	 */
	public static function unregister_legacy_breve_post_type(): void {
		if ( self::CONTENT_SCHEMA_VERSION !== get_option( self::CONTENT_SCHEMA_OPTION ) ) {
			return;
		}

		if ( post_type_exists( self::LEGACY_BREVE_POST_TYPE ) ) {
			unregister_post_type( self::LEGACY_BREVE_POST_TYPE );
		}
	}

	/**
	 * Maintient les requêtes de l'ancien thème après la migration du type.
	 *
	 * @param \WP_Query $query Requête en cours de préparation.
	 * @return void
	 */
	public static function map_legacy_breve_query( \WP_Query $query ): void {
		$post_type = $query->get( 'post_type' );

		if ( self::LEGACY_BREVE_POST_TYPE === $post_type ) {
			$query->set( 'post_type', self::BREVE_POST_TYPE );
			return;
		}

		if ( ! is_array( $post_type ) || ! in_array( self::LEGACY_BREVE_POST_TYPE, $post_type, true ) ) {
			return;
		}

		$post_types = array_map(
			static function ( $type ): string {
				$type = (string) $type;

				return self::LEGACY_BREVE_POST_TYPE === $type ? self::BREVE_POST_TYPE : $type;
			},
			$post_type
		);

		$query->set( 'post_type', array_values( array_unique( $post_types ) ) );
	}

	/**
	 * Actualise une fois les permaliens après l'installation du schéma.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( self::CONTENT_SCHEMA_VERSION !== get_option( self::CONTENT_SCHEMA_OPTION ) ) {
			return;
		}

		$rewrite_version = get_option( 'plaidact_core_rewrite_schema_version' );

		if ( self::CONTENT_SCHEMA_VERSION === $rewrite_version ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'plaidact_core_rewrite_schema_version', self::CONTENT_SCHEMA_VERSION, false );
	}

	/**
	 * Enregistre les taxonomies PLAID·ACT.
	 *
	 * @return void
	 */
	public static function register_taxonomies(): void {
		register_taxonomy(
			'plaid_breve_topic',
			'plaid_breve',
			array(
				'label'             => __( 'Thématiques', 'plaidact-campaign-core' ),
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
			)
		);

		register_taxonomy(
			'plaid_partner_type',
			'plaid_partner',
			array(
				'label'             => __( 'Type de partenaire', 'plaidact-campaign-core' ),
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
			)
		);
	}

	/**
	 * Enregistre les métadonnées partenaires pour REST et l'administration.
	 *
	 * @return void
	 */
	public static function register_partner_meta(): void {
		register_post_meta(
			'plaid_partner',
			'_plaid_partner_url',
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'esc_url_raw',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Enregistre les métadonnées des intégrations sociales.
	 *
	 * @return void
	 */
	public static function register_social_embed_meta(): void {
		register_post_meta(
			'plaid_social_embed',
			'_plaid_social_platform',
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'plaid_social_embed',
			'_plaid_social_enabled',
			array(
				'single'            => true,
				'type'              => 'boolean',
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			'plaid_social_embed',
			'_plaid_social_embed_code',
			array(
				'single'            => true,
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'wp_kses_post',
				'auth_callback'     => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Ajoute la métabox d'URL des partenaires.
	 *
	 * @return void
	 */
	public static function register_partner_metabox(): void {
		add_meta_box(
			'plaid_partner_url',
			__( 'Lien du partenaire', 'plaidact-campaign-core' ),
			array( __CLASS__, 'render_partner_metabox' ),
			'plaid_partner',
			'normal',
			'default'
		);
	}

	/**
	 * Enregistre la métabox du mur social.
	 *
	 * @return void
	 */
	public static function register_social_embed_metabox(): void {
		add_meta_box(
			'plaid_social_embed_data',
			__( 'Configuration de l’embed', 'plaidact-campaign-core' ),
			array( __CLASS__, 'render_social_embed_metabox' ),
			'plaid_social_embed',
			'normal',
			'default'
		);
	}

	/**
	 * Affiche le champ URL du partenaire.
	 *
	 * @param \WP_Post $post Contenu en cours d'édition.
	 * @return void
	 */
	public static function render_partner_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'plaid_partner_url_nonce_action', 'plaid_partner_url_nonce' );
		$url = get_post_meta( $post->ID, '_plaid_partner_url', true );
		?>
		<p>
			<label for="plaid_partner_url"><strong><?php esc_html_e( 'URL du site partenaire', 'plaidact-campaign-core' ); ?></strong></label>
			<input
				type="url"
				class="widefat"
				id="plaid_partner_url"
				name="plaid_partner_url"
				value="<?php echo esc_attr( (string) $url ); ?>"
				placeholder="https://example.org"
			/>
		</p>
		<?php
	}

	/**
	 * Affiche les champs d'intégration sociale.
	 *
	 * @param \WP_Post $post Contenu en cours d'édition.
	 * @return void
	 */
	public static function render_social_embed_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'plaid_social_embed_nonce_action', 'plaid_social_embed_nonce' );
		$platform   = (string) get_post_meta( $post->ID, '_plaid_social_platform', true );
		$enabled    = (bool) get_post_meta( $post->ID, '_plaid_social_enabled', true );
		$embed_code = (string) get_post_meta( $post->ID, '_plaid_social_embed_code', true );
		?>
		<p>
			<label for="plaid_social_platform"><strong><?php esc_html_e( 'Plateforme', 'plaidact-campaign-core' ); ?></strong></label>
			<select id="plaid_social_platform" name="plaid_social_platform" class="widefat">
				<?php foreach ( array( 'Bluesky', 'Instagram', 'X', 'YouTube', 'TikTok', 'LinkedIn' ) as $item ) : ?>
					<option value="<?php echo esc_attr( $item ); ?>" <?php selected( $platform, $item ); ?>><?php echo esc_html( $item ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label><input type="checkbox" name="plaid_social_enabled" value="1" <?php checked( $enabled ); ?> /> <?php esc_html_e( 'Afficher ce post dans le social wall', 'plaidact-campaign-core' ); ?></label>
		</p>
		<p>
			<label for="plaid_social_embed_code"><strong><?php esc_html_e( 'Code embed', 'plaidact-campaign-core' ); ?></strong></label>
			<textarea id="plaid_social_embed_code" name="plaid_social_embed_code" class="widefat" rows="8" placeholder="<blockquote>...</blockquote>"><?php echo esc_textarea( $embed_code ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Enregistre le champ URL du partenaire.
	 *
	 * @param int $post_id Identifiant du contenu courant.
	 * @return void
	 */
	public static function save_partner_url( int $post_id ): void {
		if ( ! isset( $_POST['plaid_partner_url_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaid_partner_url_nonce'] ) ), 'plaid_partner_url_nonce_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['plaid_partner_url'] ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['plaid_partner_url'] ) );

			if ( ! empty( $url ) ) {
				update_post_meta( $post_id, '_plaid_partner_url', $url );
			} else {
				delete_post_meta( $post_id, '_plaid_partner_url' );
			}
		}
	}

	/**
	 * Enregistre les champs de la métabox sociale.
	 *
	 * @param int $post_id Identifiant du contenu courant.
	 * @return void
	 */
	public static function save_social_embed_meta( int $post_id ): void {
		if ( ! isset( $_POST['plaid_social_embed_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaid_social_embed_nonce'] ) ), 'plaid_social_embed_nonce_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_plaid_social_platform', sanitize_text_field( (string) wp_unslash( $_POST['plaid_social_platform'] ?? 'Bluesky' ) ) );
		update_post_meta( $post_id, '_plaid_social_enabled', isset( $_POST['plaid_social_enabled'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_plaid_social_embed_code', wp_kses_post( (string) wp_unslash( $_POST['plaid_social_embed_code'] ?? '' ) ) );
	}
}
