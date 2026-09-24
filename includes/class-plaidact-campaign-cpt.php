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
	private const CONTENT_SCHEMA_VERSION = '2.2.0';

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
	 * Métadonnées des brèves — lien externe et source.
	 */
	private const BREVE_META_LINK = '_plaid_breve_link';
	private const BREVE_META_SOURCE = '_plaid_breve_source';
	private const BREVE_META_SOURCE_URL = '_plaid_breve_source_url';

	/**
	 * Marqueur de génération automatique de vignette.
	 */
	private const BREVE_COVER_META = '_plaid_breve_cover_generated';

	/**
	 * Clés historiques pouvant contenir un lien externe de brève.
	 *
	 * @var array<int,string>
	 */
	private const LEGACY_BREVE_LINK_KEYS = array(
		'_plaid_breve_link',
		'_plaid_breve_url',
		'_plaid_breve_external_link',
		'_breve_link',
		'breve_link',
		'lien',
		'lien_breve',
		'lien_externe',
		'url',
		'url_source',
		'external_url',
		'breve_url',
		'source_url',
		'link',
		'_plaid_breve_source_url',
	);

	/**
	 * Clés historiques pouvant contenir la source d'une brève.
	 *
	 * @var array<int,string>
	 */
	private const LEGACY_BREVE_SOURCE_KEYS = array(
		'_plaid_breve_source',
		'breve_source',
		'source',
		'source_name',
		'nom_source',
		'_breve_source',
		'source_breve',
		'origine',
	);

	/**
	 * Clés historiques pouvant contenir les thématiques sous forme textuelle.
	 *
	 * @var array<int,string>
	 */
	private const LEGACY_BREVE_TOPIC_KEYS = array(
		'thematique',
		'thematiques',
		'thematique_breve',
		'thematiques_breve',
		'topic',
		'topics',
		'categorie',
		'categories',
		'theme',
		'themes',
		'plaid_breve_topic',
		'_plaid_breve_topic',
	);

	/**
	 * Enregistre les actions WordPress.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( __CLASS__, 'register_breve_meta' ) );
		add_action( 'init', array( __CLASS__, 'migrate_legacy_breves' ), 90 );
		add_action( 'init', array( __CLASS__, 'maybe_repair_legacy_breve_data' ), 95 );
		add_action( 'init', array( __CLASS__, 'unregister_legacy_breve_post_type' ), 1000 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 1100 );
		add_action( 'pre_get_posts', array( __CLASS__, 'map_legacy_breve_query' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_breve_export_fields' ) );
		add_action( 'init', array( __CLASS__, 'register_partner_meta' ) );
		add_action( 'init', array( __CLASS__, 'register_social_embed_meta' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_partner_metabox' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_social_embed_metabox' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_breve_metabox' ) );
		add_action( 'save_post_plaid_partner', array( __CLASS__, 'save_partner_url' ) );
		add_action( 'save_post_plaid_social_embed', array( __CLASS__, 'save_social_embed_meta' ) );
		add_action( 'save_post_plaid_breve', array( __CLASS__, 'save_breve_meta' ), 10, 2 );
		add_action( 'save_post_plaid_breve', array( __CLASS__, 'maybe_generate_breve_cover_on_save' ), 20, 3 );
		add_action( 'admin_post_plaidact_generate_breve_covers', array( __CLASS__, 'handle_bulk_generate_covers' ) );
		add_action( 'admin_post_plaidact_repair_breves', array( __CLASS__, 'handle_repair_breves' ) );
		add_action( 'admin_notices', array( __CLASS__, 'display_breve_admin_notices' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_breve_content' ) );
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
					$post_id = (int) $post['id'];
					// Thématiques via taxonomie, avec repli sur les métadonnées historiques.
					$terms = wp_get_post_terms(
						$post_id,
						'plaid_breve_topic',
						array( 'fields' => 'names' )
					);

					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						return $terms;
					}

					$fallback = CPT::get_breve_topics_fallback( $post_id );
					return $fallback;
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

		register_rest_field(
			'plaid_breve',
			'lien',
			array(
				'get_callback' => static function ( array $post ): string {
					return (string) CPT::get_breve_link( (int) $post['id'] );
				},
				'schema'       => array(
					'description' => __( 'URL externe de la brève.', 'plaidact-campaign-core' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);

		register_rest_field(
			'plaid_breve',
			'source',
			array(
				'get_callback' => static function ( array $post ): string {
					return (string) CPT::get_breve_source( (int) $post['id'] );
				},
				'schema'       => array(
					'description' => __( 'Source de la brève.', 'plaidact-campaign-core' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);

		register_rest_field(
			'plaid_breve',
			'source_url',
			array(
				'get_callback' => static function ( array $post ): string {
					return (string) CPT::get_breve_source_url( (int) $post['id'] );
				},
				'schema'       => array(
					'description' => __( 'URL de la source.', 'plaidact-campaign-core' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * Enregistre les métadonnées des brèves (lien externe et source).
	 *
	 * @return void
	 */
	public static function register_breve_meta(): void {
		register_post_meta(
			self::BREVE_POST_TYPE,
			self::BREVE_META_LINK,
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

		register_post_meta(
			self::BREVE_POST_TYPE,
			self::BREVE_META_SOURCE,
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
			self::BREVE_POST_TYPE,
			self::BREVE_META_SOURCE_URL,
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
	 * Retourne l'URL externe d'une brève avec repli sur les anciennes clés.
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return string
	 */
	public static function get_breve_link( int $post_id ): string {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$canonical = (string) get_post_meta( $post_id, self::BREVE_META_LINK, true );
		if ( '' !== trim( $canonical ) ) {
			return esc_url_raw( $canonical );
		}

		// Recherche dans les clés historiques (données à récupérer).
		foreach ( self::LEGACY_BREVE_LINK_KEYS as $key ) {
			if ( $key === self::BREVE_META_LINK ) {
				continue;
			}
			$value = get_post_meta( $post_id, $key, true );

			// ACF peut stocker des tableaux ; on ignore.
			if ( is_array( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			// Vérifie que c'est une URL.
			$sanitized = esc_url_raw( $value );
			if ( '' !== $sanitized && filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
				// Migration non destructive : on conserve la valeur historique
				// et on la copie vers la clé canonique pour les prochaines lectures.
				update_post_meta( $post_id, self::BREVE_META_LINK, $sanitized );
				return $sanitized;
			}
		}

		// Dernier repli : champ ACF sans préfixe underscore.
		$acf_link = plaidact_campaign_core_get_field( 'lien', $post_id );
		if ( is_string( $acf_link ) && '' !== trim( $acf_link ) ) {
			$sanitized = esc_url_raw( trim( $acf_link ) );
			if ( '' !== $sanitized && filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
				update_post_meta( $post_id, self::BREVE_META_LINK, $sanitized );
				return $sanitized;
			}
		}

		return '';
	}

	/**
	 * Retourne la source d'une brève avec repli sur les anciennes clés.
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return string
	 */
	public static function get_breve_source( int $post_id ): string {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$canonical = (string) get_post_meta( $post_id, self::BREVE_META_SOURCE, true );
		if ( '' !== trim( $canonical ) ) {
			return sanitize_text_field( $canonical );
		}

		foreach ( self::LEGACY_BREVE_SOURCE_KEYS as $key ) {
			if ( $key === self::BREVE_META_SOURCE ) {
				continue;
			}
			$value = get_post_meta( $post_id, $key, true );
			if ( is_array( $value ) ) {
				continue;
			}
			$value = trim( (string) $value );
			if ( '' !== $value ) {
				$sanitized = sanitize_text_field( $value );
				update_post_meta( $post_id, self::BREVE_META_SOURCE, $sanitized );
				return $sanitized;
			}
		}

		$acf_source = plaidact_campaign_core_get_field( 'source', $post_id );
		if ( is_string( $acf_source ) && '' !== trim( $acf_source ) ) {
			$sanitized = sanitize_text_field( trim( $acf_source ) );
			update_post_meta( $post_id, self::BREVE_META_SOURCE, $sanitized );
			return $sanitized;
		}

		return '';
	}

	/**
	 * Retourne l'URL de la source (si distincte du lien principal).
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return string
	 */
	public static function get_breve_source_url( int $post_id ): string {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$canonical = (string) get_post_meta( $post_id, self::BREVE_META_SOURCE_URL, true );
		if ( '' !== trim( $canonical ) ) {
			return esc_url_raw( $canonical );
		}

		// Si la source est une URL, on la considère aussi comme source_url.
		$source = self::get_breve_source( $post_id );
		if ( filter_var( $source, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $source );
		}

		return '';
	}

	/**
	 * Retourne les thématiques d'une brève avec repli sur les métadonnées historiques.
	 *
	 * Utilisé pour l'affichage et l'export REST quand la taxonomie est vide
	 * (données anciennes stockées en texte).
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return array<int,string>
	 */
	public static function get_breve_topics_fallback( int $post_id ): array {
		foreach ( self::LEGACY_BREVE_TOPIC_KEYS as $key ) {
			$value = get_post_meta( $post_id, $key, true );

			if ( is_array( $value ) ) {
				$names = array_filter( array_map( 'sanitize_text_field', $value ) );
				if ( ! empty( $names ) ) {
					return array_values( $names );
				}
				continue;
			}

			// ACF fallback.
			if ( '' === trim( (string) $value ) ) {
				$acf = plaidact_campaign_core_get_field( $key, $post_id );
				if ( is_array( $acf ) ) {
					$names = array_filter( array_map( 'sanitize_text_field', $acf ) );
					if ( ! empty( $names ) ) {
						return array_values( $names );
					}
				} elseif ( is_string( $acf ) && '' !== trim( $acf ) ) {
					$value = $acf;
				} else {
					continue;
				}
			}

			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			// Peut être sérialisé ou liste séparée par virgules/points-virgules.
			$maybe = maybe_unserialize( $value );
			if ( is_array( $maybe ) ) {
				$names = array_filter( array_map( 'sanitize_text_field', $maybe ) );
				if ( ! empty( $names ) ) {
					return array_values( $names );
				}
				continue;
			}

			// Séparation par virgules, points-virgules ou retours à la ligne.
			$parts = preg_split( '/[,\;\n\|]+/', $value );
			if ( is_array( $parts ) ) {
				$names = array_filter( array_map( 'trim', $parts ) );
				$names = array_filter( array_map( 'sanitize_text_field', $names ) );
				if ( ! empty( $names ) ) {
					return array_values( $names );
				}
			}

			// Valeur unique.
			return array( sanitize_text_field( $value ) );
		}

		return array();
	}

	/**
	 * Retourne les thématiques structurées d'une brève (termes ou fallback).
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return array<int,array{name:string,slug:string,link:string|bool}>
	 */
	public static function get_breve_topics( int $post_id ): array {
		$post_id = absint( $post_id );
		$terms = get_the_terms( $post_id, 'plaid_breve_topic' );

		if ( ! is_wp_error( $terms ) && is_array( $terms ) && ! empty( $terms ) ) {
			$result = array();
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term ) {
					$result[] = array(
						'name' => (string) $term->name,
						'slug' => (string) $term->slug,
						'link' => get_term_link( $term ),
					);
				}
			}
			if ( ! empty( $result ) ) {
				return $result;
			}
		}

		// Repli sur les métadonnées historiques : on synthétise des tags non liés.
		$fallback_names = self::get_breve_topics_fallback( $post_id );
		$synthetic = array();
		foreach ( $fallback_names as $name ) {
			$slug = sanitize_title( $name );
			$term = get_term_by( 'slug', $slug, 'plaid_breve_topic' );
			$link = $term ? get_term_link( $term ) : false;
			$synthetic[] = array(
				'name' => $name,
				'slug' => $slug,
				'link' => $link,
			);
		}

		return $synthetic;
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
				'labels'            => array(
					'name'          => __( 'Thématiques', 'plaidact-campaign-core' ),
					'singular_name' => __( 'Thématique', 'plaidact-campaign-core' ),
				),
				'label'             => __( 'Thématiques', 'plaidact-campaign-core' ),
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'rewrite'           => array(
					'slug'         => 'thematiques',
					'with_front'   => false,
					'hierarchical' => true,
				),
				'query_var'         => true,
			)
		);

		// Compatibilité : l'ancien slug de la taxonomie (basé sur son nom)
		// peut encore être présent dans des URLs indexées ou des liens externes ;
		// on s'assure que les requêtes contenant plaid_breve_topic restent résolues.
		add_rewrite_rule( '^plaid_breve_topic/(.+?)/?$', 'index.php?plaid_breve_topic=$matches[1]', 'top' );
		add_rewrite_rule( '^plaid_breve_topic/(.+?)/page/?([0-9]{1,})/?$', 'index.php?plaid_breve_topic=$matches[1]&paged=$matches[2]', 'top' );
		// Anciens slugs francisés éventuels.
		add_rewrite_rule( '^thematique/(.+?)/?$', 'index.php?plaid_breve_topic=$matches[1]', 'top' );
		add_rewrite_rule( '^thematiques/(.+?)/?$', 'index.php?plaid_breve_topic=$matches[1]', 'top' );

		if ( ! get_taxonomy( 'plaid_breve_topic' ) ) {
			return;
		}

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

	/**
	 * Ajoute la métabox des brèves (lien externe + source).
	 *
	 * @return void
	 */
	public static function register_breve_metabox(): void {
		add_meta_box(
			'plaid_breve_link',
			__( 'Lien et source de la brève', 'plaidact-campaign-core' ),
			array( __CLASS__, 'render_breve_metabox' ),
			self::BREVE_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'plaid_breve_cover',
			__( 'Image mise en avant — génération automatique', 'plaidact-campaign-core' ),
			array( __CLASS__, 'render_breve_cover_metabox' ),
			self::BREVE_POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Affiche la métabox lien/source des brèves.
	 *
	 * @param \WP_Post $post Contenu en cours d'édition.
	 * @return void
	 */
	public static function render_breve_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'plaid_breve_meta_nonce_action', 'plaid_breve_meta_nonce' );

		$link       = self::get_breve_link( (int) $post->ID );
		$source     = self::get_breve_source( (int) $post->ID );
		$source_url = (string) get_post_meta( $post->ID, self::BREVE_META_SOURCE_URL, true );

		// Si la source n'est pas encore migrée, on affiche la valeur récupérée via fallback.
		if ( '' === $link ) {
			$link = (string) get_post_meta( $post->ID, self::BREVE_META_LINK, true );
		}
		if ( '' === $source ) {
			$source = (string) get_post_meta( $post->ID, self::BREVE_META_SOURCE, true );
		}
		?>
		<p>
			<label for="plaid_breve_link"><strong><?php esc_html_e( 'Lien externe (URL complète)', 'plaidact-campaign-core' ); ?></strong></label>
			<input
				type="url"
				class="widefat"
				id="plaid_breve_link"
				name="plaid_breve_link"
				value="<?php echo esc_attr( $link ); ?>"
				placeholder="https://exemple.org/article-source"
			/>
			<span class="description"><?php esc_html_e( 'Si renseigné, le titre de la brève pointera vers ce lien (ouverture dans un nouvel onglet). Laisser vide pour lier vers la page interne de la brève.', 'plaidact-campaign-core' ); ?></span>
		</p>
		<p>
			<label for="plaid_breve_source"><strong><?php esc_html_e( 'Source (nom du média / origine)', 'plaidact-campaign-core' ); ?></strong></label>
			<input
				type="text"
				class="widefat"
				id="plaid_breve_source"
				name="plaid_breve_source"
				value="<?php echo esc_attr( $source ); ?>"
				placeholder="<?php esc_attr_e( 'Ex. Le Monde, AFP, Médiapart', 'plaidact-campaign-core' ); ?>"
			/>
			<span class="description"><?php esc_html_e( 'Affichée sous le titre et reprise dans l’image mise en avant auto-générée. Peut aussi être une URL si la source n’a pas de nom distinct.', 'plaidact-campaign-core' ); ?></span>
		</p>
		<p>
			<label for="plaid_breve_source_url"><strong><?php esc_html_e( 'URL de la source (optionnel)', 'plaidact-campaign-core' ); ?></strong></label>
			<input
				type="url"
				class="widefat"
				id="plaid_breve_source_url"
				name="plaid_breve_source_url"
				value="<?php echo esc_attr( $source_url ); ?>"
				placeholder="https://source.example.org"
			/>
			<span class="description"><?php esc_html_e( 'Si distinct du lien principal, permet de lier la mention « Source » séparément.', 'plaidact-campaign-core' ); ?></span>
		</p>
		<?php
		// Diagnostic discret pour les données historiques non migrées.
		$fallback_topics = self::get_breve_topics_fallback( (int) $post->ID );
		$has_taxo        = has_term( '', 'plaid_breve_topic', $post->ID );
		if ( ! $has_taxo && ! empty( $fallback_topics ) ) {
			echo '<div class="notice notice-alt notice-info" style="margin-top:1rem;padding:0.6rem 0.8rem;"><p style="margin:0;">';
			esc_html_e( 'Thématiques historiques détectées :', 'plaidact-campaign-core' );
			echo ' <strong>' . esc_html( implode( ', ', $fallback_topics ) ) . '</strong> — ';
			esc_html_e( 'elles seront migrées vers la taxonomie « Thématiques » à l’enregistrement.', 'plaidact-campaign-core' );
			echo '</p></div>';
		}
	}

	/**
	 * Affiche la métabox de génération de vignette.
	 *
	 * @param \WP_Post $post Contenu en cours d'édition.
	 * @return void
	 */
	public static function render_breve_cover_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'plaid_breve_cover_nonce_action', 'plaid_breve_cover_nonce' );

		$has_thumbnail = has_post_thumbnail( $post->ID );
		$generated     = (string) get_post_meta( $post->ID, self::BREVE_COVER_META, true );
		?>
		<p>
			<?php if ( $has_thumbnail ) : ?>
				<?php echo get_the_post_thumbnail( $post->ID, 'medium', array( 'style' => 'max-width:100%;height:auto;border:1px solid #e5e7eb;border-radius:6px;' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<br />
				<span class="description"><?php esc_html_e( 'Image mise en avant actuelle.', 'plaidact-campaign-core' ); ?></span>
			<?php else : ?>
				<span class="description"><?php esc_html_e( 'Aucune image mise en avant. Une vignette simple (logo, titre, thématique, source) peut être générée automatiquement.', 'plaidact-campaign-core' ); ?></span>
			<?php endif; ?>
		</p>
		<p>
			<label>
				<input type="checkbox" name="plaid_breve_generate_cover" value="1" <?php checked( ! $has_thumbnail ); ?> />
				<?php esc_html_e( 'Générer / régénérer l’image mise en avant à l’enregistrement', 'plaidact-campaign-core' ); ?>
			</label>
		</p>
		<?php if ( '' !== $generated ) : ?>
			<p class="description"><?php echo esc_html( sprintf( __( 'Dernière génération : %s', 'plaidact-campaign-core' ), $generated ) ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'L’image reprend : logo PLAID·ACT, titre, première thématique et source. Elle est créée en 1200×630 px (format partage réseaux) et définie comme vignette du billet.', 'plaidact-campaign-core' ); ?></p>
		<?php
	}

	/**
	 * Enregistre les métadonnées des brèves.
	 *
	 * @param int      $post_id Identifiant du contenu.
	 * @param \WP_Post $post Objet de la brève.
	 * @return void
	 */
	public static function save_breve_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['plaid_breve_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaid_breve_meta_nonce'] ) ), 'plaid_breve_meta_nonce_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Lien externe.
		if ( isset( $_POST['plaid_breve_link'] ) ) {
			$raw_link = trim( (string) wp_unslash( $_POST['plaid_breve_link'] ) );
			if ( '' === $raw_link ) {
				delete_post_meta( $post_id, self::BREVE_META_LINK );
			} else {
				$sanitized = esc_url_raw( $raw_link );
				if ( '' !== $sanitized ) {
					update_post_meta( $post_id, self::BREVE_META_LINK, $sanitized );
				}
			}
		}

		// Source.
		if ( isset( $_POST['plaid_breve_source'] ) ) {
			$raw_source = trim( (string) wp_unslash( $_POST['plaid_breve_source'] ) );
			if ( '' === $raw_source ) {
				delete_post_meta( $post_id, self::BREVE_META_SOURCE );
			} else {
				update_post_meta( $post_id, self::BREVE_META_SOURCE, sanitize_text_field( $raw_source ) );
			}
		}

		// URL source.
		if ( isset( $_POST['plaid_breve_source_url'] ) ) {
			$raw_url = trim( (string) wp_unslash( $_POST['plaid_breve_source_url'] ) );
			if ( '' === $raw_url ) {
				delete_post_meta( $post_id, self::BREVE_META_SOURCE_URL );
			} else {
				$sanitized = esc_url_raw( $raw_url );
				if ( '' !== $sanitized ) {
					update_post_meta( $post_id, self::BREVE_META_SOURCE_URL, $sanitized );
				}
			}
		}

		// Migration des thématiques textuelles vers la taxonomie.
		$fallback = self::get_breve_topics_fallback( $post_id );
		$has_taxo = has_term( '', 'plaid_breve_topic', $post_id );
		if ( ! $has_taxo && ! empty( $fallback ) ) {
			// On crée/associe les termes sans écraser d'éventuels termes existants.
			$term_ids = array();
			foreach ( $fallback as $name ) {
				$term = term_exists( $name, 'plaid_breve_topic' );
				if ( ! $term ) {
					$created = wp_insert_term( $name, 'plaid_breve_topic' );
					if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
						$term_ids[] = (int) $created['term_id'];
					}
				} else {
					$term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
				}
			}
			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, 'plaid_breve_topic', true );
			}
		}

		// Nettoyage léger : si le lien pointe vers la même URL que la source_url, on évite la duplication.
		$link       = (string) get_post_meta( $post_id, self::BREVE_META_LINK, true );
		$source_url = (string) get_post_meta( $post_id, self::BREVE_META_SOURCE_URL, true );
		if ( '' !== $link && $link === $source_url ) {
			delete_post_meta( $post_id, self::BREVE_META_SOURCE_URL );
		}
	}

	/**
	 * Tente de réparer les données historiques des brèves (liens, sources, thématiques).
	 *
	 * S'exécute une fois par requête init (95) tant que le marqueur de version
	 * indique une migration incomplète, ou à la demande via l'action admin_post.
	 * Aucune donnée existante n'est écrasée : seules les brèves sans lien/source/thématique
	 * canonique et disposant d'une valeur historique sont complétées.
	 *
	 * @return void
	 */
	public static function maybe_repair_legacy_breve_data(): void {
		// On ne lance la réparation automatique que si aucune brève n'a encore été migrée
		// ou si l'option de suivi l'indique. On évite une boucle lourde à chaque requête
		// en posant un transient d'une heure après un passage sans rien à faire.
		if ( get_transient( 'plaidact_breves_repair_done' ) ) {
			return;
		}

		// Limite : on traite au plus 50 brèves par passage pour ne pas peser sur le TTFB.
		$posts = get_posts(
			array(
				'post_type'              => self::BREVE_POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $posts ) ) {
			set_transient( 'plaidact_breves_repair_done', 1, HOUR_IN_SECONDS );
			return;
		}

		$repaired = 0;
		foreach ( $posts as $post_id ) {
			if ( self::repair_legacy_breve_for_post( (int) $post_id ) ) {
				$repaired++;
			}
		}

		// Si moins de 50 brèves et aucune réparation, on considère que le lot est à jour.
		if ( 0 === $repaired && count( $posts ) < 50 ) {
			set_transient( 'plaidact_breves_repair_done', 1, HOUR_IN_SECONDS );
		}

		// On tente aussi de récupérer les anciennes brèves encore en post_type 'breves'
		// qui n'auraient pas été migrées à cause d'une option de schéma déjà posée
		// mais avec des métadonnées orphelines (cas d'import manuel post-migration).
		global $wpdb;
		$orphan_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC LIMIT 20",
				self::LEGACY_BREVE_POST_TYPE
			)
		);
		foreach ( array_map( 'absint', $orphan_ids ) as $orphan_id ) {
			// On force la migration du type + la réparation des métas.
			wp_update_post(
				array(
					'ID'        => $orphan_id,
					'post_type' => self::BREVE_POST_TYPE,
				)
			);
			self::repair_legacy_breve_for_post( $orphan_id );
		}
	}

	/**
	 * Répare une brève donnée en copiant les valeurs historiques vers les clés canoniques.
	 *
	 * @param int $post_id Identifiant du contenu.
	 * @return bool True si une donnée a été migrée.
	 */
	public static function repair_legacy_breve_for_post( int $post_id ): bool {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post || self::BREVE_POST_TYPE !== $post->post_type ) {
			// On accepte aussi de réparer une brève orpheline en 'breves'.
			if ( ! $post || self::LEGACY_BREVE_POST_TYPE !== $post->post_type ) {
				return false;
			}
		}

		$did_something = false;

		// Lien externe : s'il n'existe pas en canonique, on cherche une valeur historique.
		$has_link = '' !== trim( (string) get_post_meta( $post_id, self::BREVE_META_LINK, true ) );
		if ( ! $has_link ) {
			$legacy_link = self::get_breve_link( $post_id );
			// get_breve_link() migre déjà si trouvé, mais on compte la réparation.
			if ( '' !== $legacy_link ) {
				$did_something = true;
			} else {
				// Dernier essai : extraire le premier lien du contenu si celui-ci
				// est vide d'éditeur mais contient une URL brute (cas d'import CSV minimal).
				$content = (string) get_post_field( 'post_content', $post_id, 'raw' );
				if ( '' !== trim( $content ) && preg_match( '#https?://[^\s"<]+#i', $content, $m ) ) {
					// On ne récupère pas automatiquement tout le contenu comme lien ;
					// on ne le fait que si le titre est vide (brève importée sans éditeur).
					$title = trim( (string) get_post_field( 'post_title', $post_id, 'raw' ) );
					if ( '' === $title ) {
						$found = esc_url_raw( $m[0] );
						if ( '' !== $found ) {
							update_post_meta( $post_id, self::BREVE_META_LINK, $found );
							$did_something = true;
						}
					}
				}
			}
		}

		// Source.
		$has_source = '' !== trim( (string) get_post_meta( $post_id, self::BREVE_META_SOURCE, true ) );
		if ( ! $has_source ) {
			$legacy_source = self::get_breve_source( $post_id );
			if ( '' !== $legacy_source ) {
				$did_something = true;
			}
		}

		// Thématiques : si aucune taxonomie n'est liée mais qu'une valeur textuelle existe.
		$has_terms = has_term( '', 'plaid_breve_topic', $post_id );
		if ( ! $has_terms ) {
			$fallback = self::get_breve_topics_fallback( $post_id );
			if ( ! empty( $fallback ) ) {
				$term_ids = array();
				foreach ( $fallback as $name ) {
					// On normalise le nom avant création pour éviter les doublons de casse.
					$name = sanitize_text_field( $name );
					if ( '' === $name ) {
						continue;
					}
					$existing = term_exists( $name, 'plaid_breve_topic' );
					if ( ! $existing ) {
						$created = wp_insert_term( $name, 'plaid_breve_topic' );
						if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
							$term_ids[] = (int) $created['term_id'];
						}
					} else {
						$term_ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
					}
				}
				if ( ! empty( $term_ids ) ) {
					wp_set_object_terms( $post_id, $term_ids, 'plaid_breve_topic', true );
					$did_something = true;
				}
			}
		}

		// Si on a réparé quelque chose, on invalide le transient pour laisser le temps
		// au prochain passage de continuer le balayage.
		if ( $did_something ) {
			delete_transient( 'plaidact_breves_repair_done' );
		}

		return $did_something;
	}

	/**
	 * Génère l'image mise en avant d'une brève à l'enregistrement si demandé.
	 *
	 * @param int      $post_id Identifiant du contenu.
	 * @param \WP_Post $post Objet du contenu.
	 * @param bool     $update Indique si c'est une mise à jour.
	 * @return void
	 */
	public static function maybe_generate_breve_cover_on_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Vérifie le nonce de la métabox de couverture.
		$has_cover_nonce = isset( $_POST['plaid_breve_cover_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plaid_breve_cover_nonce'] ) ), 'plaid_breve_cover_nonce_action' );
		$wants_generate  = ! empty( $_POST['plaid_breve_generate_cover'] );

		// Génération automatique si pas de vignette et pas de demande explicite de refus.
		// On ne génère que si l'utilisateur a coché la case ou si la brève n'a aucune vignette
		// et que le formulaire provient bien de l'édition de la brève (nonce présent).
		if ( $has_cover_nonce ) {
			if ( ! $wants_generate ) {
				return;
			}
		} else {
			// Sauvegarde programmatique (ex. import) : on ne génère que si aucune vignette n'existe
			// et que la brève est publiée.
			if ( has_post_thumbnail( $post_id ) || 'publish' !== $post->post_status ) {
				return;
			}
			// On évite de générer en masse lors d'un import rapide : on génère seulement
			// si le titre est renseigné et que le contenu n'est pas vide.
			if ( '' === trim( (string) $post->post_title ) ) {
				return;
			}
		}

		// On génère la vignette.
		$attachment_id = self::generate_breve_cover( $post_id, true );

		if ( is_wp_error( $attachment_id ) ) {
			// On ne bloque pas la sauvegarde sur erreur de génération.
			return;
		}
	}

	/**
	 * Génère une image mise en avant simple pour une brève.
	 *
	 * L'image reprend : logo PLAID·ACT, titre, première thématique et source.
	 * Elle est créée en 1200×630 px (ratio 1.91:1, optimal pour les réseaux sociaux)
	 * avec GD si disponible, sinon via SVG converti si Imagick est présent.
	 *
	 * @param int  $post_id Identifiant de la brève.
	 * @param bool $force Régénère même si une vignette existe déjà.
	 * @return int|\WP_Error Identifiant de l'attachement créé ou erreur.
	 */
	public static function generate_breve_cover( int $post_id, bool $force = false ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'plaidact_invalid_breve', __( 'Brève invalide.', 'plaidact-campaign-core' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( self::BREVE_POST_TYPE, self::LEGACY_BREVE_POST_TYPE ), true ) ) {
			return new \WP_Error( 'plaidact_invalid_breve_type', __( 'Le contenu n’est pas une brève.', 'plaidact-campaign-core' ) );
		}

		if ( ! $force && has_post_thumbnail( $post_id ) ) {
			return (int) get_post_thumbnail_id( $post_id );
		}

		// Données d'affichage.
		$title = trim( (string) get_the_title( $post_id ) );
		if ( '' === $title ) {
			$title = sprintf( __( 'Brève #%d', 'plaidact-campaign-core' ), $post_id );
		}

		$topics = self::get_breve_topics( $post_id );
		$topic_name = '';
		if ( ! empty( $topics ) ) {
			$topic_name = (string) ( $topics[0]['name'] ?? '' );
		}
		if ( '' === $topic_name ) {
			// Repli sur la première valeur textuelle.
			$fallback = self::get_breve_topics_fallback( $post_id );
			$topic_name = $fallback[0] ?? '';
		}

		$source = self::get_breve_source( $post_id );
		if ( '' === $source ) {
			// Si la source est vide mais qu'un lien externe existe, on affiche son domaine.
			$link = self::get_breve_link( $post_id );
			if ( '' !== $link ) {
				$host = wp_parse_url( $link, PHP_URL_HOST );
				if ( is_string( $host ) && '' !== $host ) {
					$source = $host;
				}
			}
		}

		// Vérifie les capacités GD.
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagepng' ) ) {
			return new \WP_Error( 'plaidact_gd_missing', __( 'La bibliothèque GD n’est pas disponible pour générer l’image.', 'plaidact-campaign-core' ) );
		}

		$width  = 1200;
		$height = 630;

		$image = imagecreatetruecolor( $width, $height );
		if ( ! $image ) {
			return new \WP_Error( 'plaidact_image_create_failed', __( 'Impossible de créer l’image.', 'plaidact-campaign-core' ) );
		}

		// Palette PLAID·ACT.
		$color_bg       = imagecolorallocate( $image, 248, 250, 251 ); // #F8FAFB
		$color_banner   = imagecolorallocate( $image, 47, 20, 53 );   // #2f1435
		$color_accent   = imagecolorallocate( $image, 139, 53, 158 ); // #8b359e
		$color_text     = imagecolorallocate( $image, 15, 26, 23 );   // #0f1a17
		$color_muted    = imagecolorallocate( $image, 71, 85, 105 );  // #475569
		$color_tag_bg   = imagecolorallocate( $image, 240, 242, 243 ); // #F0F2F3
		$color_white    = imagecolorallocate( $image, 255, 255, 255 );
		$color_border   = imagecolorallocate( $image, 226, 232, 240 ); // #e2e8f0

		imagefilledrectangle( $image, 0, 0, $width, $height, $color_bg );
		// Bannière haute.
		imagefilledrectangle( $image, 0, 0, $width, 110, $color_banner );

		// Bordure fine.
		imagerectangle( $image, 0, 0, $width - 1, $height - 1, $color_border );

		// Recherche d'une police TTF pour un rendu net.
		$font_bold    = self::locate_breve_font( 'bold' );
		$font_regular = self::locate_breve_font( 'regular' );

		$use_ttf = ( null !== $font_bold && file_exists( $font_bold ) );

		// Logo PLAID·ACT dans la bannière.
		$logo_text = 'PLAID·ACT';
		if ( $use_ttf ) {
			// Centré dans la bannière.
			$logo_size = 36;
			$bbox      = imagettfbbox( $logo_size, 0, $font_bold, $logo_text );
			$logo_w    = $bbox[2] - $bbox[0];
			$logo_x    = (int) ( ( $width - $logo_w ) / 2 );
			$logo_y    = 72;
			imagettftext( $image, $logo_size, 0, $logo_x, $logo_y, $color_white, $font_bold, $logo_text );
		} else {
			// Repli GD natif.
			$font = 5;
			$logo_w = imagefontwidth( $font ) * strlen( $logo_text );
			$logo_x = (int) ( ( $width - $logo_w ) / 2 );
			imagestring( $image, $font, $logo_x, 38, $logo_text, $color_white );
		}

		// Titre — centré, multi-lignes, wrap intelligent.
		$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Nettoyage des retours et limitation.
		$title = trim( preg_replace( '/\s+/', ' ', $title ) );
		if ( mb_strlen( $title ) > 140 ) {
			$title = mb_substr( $title, 0, 137 ) . '…';
		}

		$margin_x = 60;
		$available_w = $width - 2 * $margin_x;

		if ( $use_ttf ) {
			$title_size = 42;
			// Ajuste la taille si le titre est très long.
			if ( mb_strlen( $title ) > 80 ) {
				$title_size = 36;
			}
			if ( mb_strlen( $title ) > 110 ) {
				$title_size = 32;
			}
			$lines = self::wrap_text_ttf( $title, $font_bold, $title_size, $available_w );
			// Limite à 4 lignes.
			if ( count( $lines ) > 4 ) {
				$lines = array_slice( $lines, 0, 4 );
				$lines[3] = rtrim( $lines[3], " \t\n\r\0\x0B…" ) . '…';
			}
			$line_h = (int) ( $title_size * 1.25 );
			$total_h = count( $lines ) * $line_h;
			// Zone verticale disponible : sous la bannière (130) jusqu'au dessus des tags (500).
			$area_top = 150;
			$area_bottom = 500;
			$area_h = $area_bottom - $area_top;
			$start_y = $area_top + (int) ( ( $area_h - $total_h ) / 2 ) + $title_size;

			foreach ( $lines as $i => $line ) {
				$bbox = imagettfbbox( $title_size, 0, $font_bold, $line );
				$line_w = $bbox[2] - $bbox[0];
				$x = (int) ( ( $width - $line_w ) / 2 );
				$y = $start_y + $i * $line_h;
				imagettftext( $image, $title_size, 0, $x, $y, $color_text, $font_bold, $line );
			}
		} else {
			// Repli GD natif — titre sur 2-3 lignes avec wordwrap.
			$font = 5;
			$char_w = imagefontwidth( $font );
			$chars_per_line = (int) ( $available_w / $char_w );
			$wrapped = wordwrap( $title, $chars_per_line, "\n", true );
			$lines = explode( "\n", $wrapped );
			if ( count( $lines ) > 3 ) {
				$lines = array_slice( $lines, 0, 3 );
				$lines[2] .= '…';
			}
			$line_h = imagefontheight( $font ) + 6;
			$total_h = count( $lines ) * $line_h;
			$start_y = 200 + (int) ( ( 300 - $total_h ) / 2 );
			foreach ( $lines as $i => $line ) {
				$line_w = $char_w * strlen( $line );
				$x = (int) ( ( $width - $line_w ) / 2 );
				$y = $start_y + $i * $line_h;
				imagestring( $image, $font, $x, $y, $line, $color_text );
			}
		}

		// Thématique — badge en bas à gauche.
		if ( '' !== trim( $topic_name ) ) {
			$tag_text = '#' . ltrim( trim( $topic_name ), '#' );
			$tag_text = mb_strtoupper( $tag_text, 'UTF-8' );
			if ( mb_strlen( $tag_text ) > 28 ) {
				$tag_text = mb_substr( $tag_text, 0, 27 ) . '…';
			}

			if ( $use_ttf ) {
				$tag_size = 16;
				$bbox = imagettfbbox( $tag_size, 0, $font_bold, $tag_text );
				$text_w = $bbox[2] - $bbox[0];
				$text_h = $bbox[1] - $bbox[7];
				$pad_x = 18;
				$pad_y = 10;
				$tag_w = $text_w + 2 * $pad_x;
				$tag_h = $text_h + 2 * $pad_y;
				$tag_x = $margin_x;
				$tag_y = $height - 70;
				// Fond arrondi simulé par rectangle + cercles.
				imagefilledrectangle( $image, $tag_x, $tag_y, $tag_x + $tag_w, $tag_y + $tag_h, $color_tag_bg );
				imagerectangle( $image, $tag_x, $tag_y, $tag_x + $tag_w, $tag_y + $tag_h, $color_border );
				$text_x = $tag_x + $pad_x;
				$text_y = $tag_y + $tag_h - $pad_y - 2;
				imagettftext( $image, $tag_size, 0, $text_x, $text_y, $color_banner, $font_bold, $tag_text );
			} else {
				$font = 3;
				$text_w = imagefontwidth( $font ) * strlen( $tag_text );
				$pad_x = 10;
				$tag_w = $text_w + 2 * $pad_x;
				$tag_h = imagefontheight( $font ) + 8;
				$tag_x = $margin_x;
				$tag_y = $height - 60;
				imagefilledrectangle( $image, $tag_x, $tag_y, $tag_x + $tag_w, $tag_y + $tag_h, $color_tag_bg );
				imagerectangle( $image, $tag_x, $tag_y, $tag_x + $tag_w, $tag_y + $tag_h, $color_border );
				imagestring( $image, $font, $tag_x + $pad_x, $tag_y + 4, $tag_text, $color_banner );
			}
		}

		// Source — en bas à droite.
		if ( '' !== trim( $source ) ) {
			$source_display = trim( $source );
			// Si c'est une URL, on n'affiche que le domaine pour rester lisible.
			if ( filter_var( $source_display, FILTER_VALIDATE_URL ) ) {
				$host = wp_parse_url( $source_display, PHP_URL_HOST );
				if ( is_string( $host ) && '' !== $host ) {
					$source_display = $host;
				}
			}
			if ( mb_strlen( $source_display ) > 36 ) {
				$source_display = mb_substr( $source_display, 0, 35 ) . '…';
			}
			$source_label = 'Source : ' . $source_display;

			if ( $use_ttf && null !== $font_regular ) {
				$src_size = 15;
				$bbox = imagettfbbox( $src_size, 0, $font_regular, $source_label );
				$src_w = $bbox[2] - $bbox[0];
				$src_x = $width - $margin_x - $src_w;
				$src_y = $height - 32;
				imagettftext( $image, $src_size, 0, $src_x, $src_y, $color_muted, $font_regular, $source_label );
			} else {
				$font = 3;
				$src_w = imagefontwidth( $font ) * strlen( $source_label );
				$src_x = $width - $margin_x - $src_w;
				$src_y = $height - 28;
				imagestring( $image, $font, $src_x, $src_y, $source_label, $color_muted );
			}
		}

		// Génération du fichier temporaire.
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			imagedestroy( $image );
			return new \WP_Error( 'plaidact_upload_dir_error', $upload_dir['error'] );
		}

		$filename = sprintf( 'breve-%d-%s.png', $post_id, wp_generate_password( 8, false, false ) );
		$filepath = trailingslashit( $upload_dir['path'] ) . $filename;

		// S'assure que le répertoire existe.
		if ( ! file_exists( $upload_dir['path'] ) ) {
			wp_mkdir_p( $upload_dir['path'] );
		}

		$saved = imagepng( $image, $filepath );
		imagedestroy( $image );

		if ( ! $saved ) {
			return new \WP_Error( 'plaidact_image_save_failed', __( 'Échec de l’enregistrement de l’image.', 'plaidact-campaign-core' ) );
		}

		// Insertion en médiathèque.
		$filetype = wp_check_filetype( $filename, null );
		$attachment = array(
			'post_mime_type' => $filetype['type'] ?: 'image/png',
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment( $attachment, $filepath, $post_id );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $filepath );
			return $attach_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attach_id, $filepath );
		wp_update_attachment_metadata( $attach_id, $attach_data );

		// Définit comme vignette — on conserve la vignette existante si force=false a déjà été géré.
		set_post_thumbnail( $post_id, $attach_id );
		update_post_meta( $post_id, self::BREVE_COVER_META, current_time( 'mysql' ) );

		/**
		 * Action après génération de la vignette de brève.
		 *
		 * @param int $post_id Identifiant de la brève.
		 * @param int $attach_id Identifiant de l'attachement créé.
		 */
		do_action( 'plaidact_breve_cover_generated', $post_id, $attach_id );

		return $attach_id;
	}

	/**
	 * Localise une police TTF pour la génération des vignettes.
	 *
	 * @param string $weight Poids demandé : bold|regular.
	 * @return string|null Chemin vers la police ou null si indisponible.
	 */
	private static function locate_breve_font( string $weight ): ?string {
		$candidates = array();

		if ( 'bold' === $weight ) {
			$candidates = array(
				PLAIDACT_CORE_PATH . 'assets/fonts/Rubik-Bold.ttf',
				PLAIDACT_CORE_PATH . 'assets/fonts/Inter-Bold.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
				'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
				'/System/Library/Fonts/Supplemental/Arial Bold.ttf',
				'/System/Library/Fonts/Helvetica.ttc',
			);
		} else {
			$candidates = array(
				PLAIDACT_CORE_PATH . 'assets/fonts/Rubik-Regular.ttf',
				PLAIDACT_CORE_PATH . 'assets/fonts/Inter-Regular.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
				'/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
				'/System/Library/Fonts/Supplemental/Arial.ttf',
			);
		}

		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Découpe un texte en lignes tenant dans une largeur donnée (TTF).
	 *
	 * @param string $text Texte à découper.
	 * @param string $font Chemin de la police.
	 * @param int    $size Taille de police.
	 * @param int    $max_width Largeur maximale en pixels.
	 * @return array<int,string>
	 */
	private static function wrap_text_ttf( string $text, string $font, int $size, int $max_width ): array {
		$words = preg_split( '/\s+/', $text );
		if ( ! is_array( $words ) || empty( $words ) ) {
			return array( $text );
		}

		$lines = array();
		$current = '';

		foreach ( $words as $word ) {
			$test = '' === $current ? $word : $current . ' ' . $word;
			$bbox = imagettfbbox( $size, 0, $font, $test );
			$w = $bbox[2] - $bbox[0];
			if ( $w <= $max_width ) {
				$current = $test;
			} else {
				if ( '' !== $current ) {
					$lines[] = $current;
				}
				// Si un mot seul dépasse, on le coupe brutalement.
				$bbox2 = imagettfbbox( $size, 0, $font, $word );
				if ( ( $bbox2[2] - $bbox2[0] ) > $max_width ) {
					$cut = '';
					$chars = function_exists( 'mb_str_split' ) ? mb_str_split( $word ) : preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
					if ( ! is_array( $chars ) ) {
						$chars = array();
					}
					foreach ( $chars as $char ) {
						$test2 = $cut . $char;
						$b = imagettfbbox( $size, 0, $font, $test2 );
						if ( ( $b[2] - $b[0] ) > $max_width ) {
							$lines[] = $cut;
							$cut = $char;
						} else {
							$cut = $test2;
						}
					}
					if ( '' !== $cut ) {
						$current = $cut;
					} else {
						$current = '';
					}
				} else {
					$current = $word;
				}
			}
		}

		if ( '' !== $current ) {
			$lines[] = $current;
		}

		return $lines;
	}

	/**
	 * Traitement bulk de génération des vignettes.
	 *
	 * @return void
	 */
	public static function handle_bulk_generate_covers(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}

		check_admin_referer( 'plaidact_generate_breve_covers' );

		$force = ! empty( $_REQUEST['force'] );
		$limit = isset( $_REQUEST['limit'] ) ? max( 1, min( 200, absint( $_REQUEST['limit'] ) ) ) : 50;

		$posts = get_posts(
			array(
				'post_type'              => self::BREVE_POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'meta_query'             => $force ? array() : array(
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$generated = 0;
		$errors    = 0;
		foreach ( $posts as $post_id ) {
			$result = self::generate_breve_cover( (int) $post_id, $force );
			if ( is_wp_error( $result ) ) {
				$errors++;
			} else {
				$generated++;
			}
		}

		$redirect = add_query_arg(
			array(
				'plaidact_breves_covers' => $generated,
				'plaidact_breves_errors' => $errors,
			),
			wp_get_referer() ?: admin_url( 'edit.php?post_type=' . self::BREVE_POST_TYPE )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Réparation manuelle des brèves depuis l'admin.
	 *
	 * @return void
	 */
	public static function handle_repair_breves(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'plaidact-campaign-core' ) );
		}

		check_admin_referer( 'plaidact_repair_breves' );

		$posts = get_posts(
			array(
				'post_type'      => self::BREVE_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$repaired = 0;
		foreach ( $posts as $post_id ) {
			if ( self::repair_legacy_breve_for_post( (int) $post_id ) ) {
				$repaired++;
			}
		}

		// On traite aussi les éventuelles brèves orphelines en 'breves'.
		global $wpdb;
		$orphans = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
				self::LEGACY_BREVE_POST_TYPE
			)
		);
		foreach ( array_map( 'absint', $orphans ) as $oid ) {
			wp_update_post(
				array(
					'ID'        => $oid,
					'post_type' => self::BREVE_POST_TYPE,
				)
			);
			if ( self::repair_legacy_breve_for_post( $oid ) ) {
				$repaired++;
			}
		}

		delete_transient( 'plaidact_breves_repair_done' );

		$redirect = add_query_arg(
			array(
				'plaidact_breves_repaired' => $repaired,
			),
			wp_get_referer() ?: admin_url( 'edit.php?post_type=' . self::BREVE_POST_TYPE )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Affiche les notices et outils d'administration pour les brèves.
	 *
	 * @return void
	 */
	public static function display_breve_admin_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		// Page liste des brèves et édition d'une brève.
		$is_breve_screen = in_array( $screen->id, array( 'edit-plaid_breve', 'plaid_breve', 'edit-breves' ), true )
			|| ( isset( $screen->post_type ) && self::BREVE_POST_TYPE === $screen->post_type );

		if ( ! $is_breve_screen ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Messages de retour après actions bulk.
		if ( isset( $_GET['plaidact_breves_repaired'] ) ) {
			$count = absint( $_GET['plaidact_breves_repaired'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( _n( '%d brève réparée (liens, sources, thématiques).', '%d brèves réparées (liens, sources, thématiques).', $count, 'plaidact-campaign-core' ), $count ) )
			);
		}

		if ( isset( $_GET['plaidact_breves_covers'] ) ) {
			$generated = absint( $_GET['plaidact_breves_covers'] );
			$errors    = absint( $_GET['plaidact_breves_errors'] ?? 0 );
			if ( $generated > 0 || $errors > 0 ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html( sprintf( __( '%1$d vignette(s) générée(s), %2$d erreur(s).', 'plaidact-campaign-core' ), $generated, $errors ) )
				);
			}
		}

		// Outils : réparation et génération.
		$repair_url = wp_nonce_url( admin_url( 'admin-post.php?action=plaidact_repair_breves' ), 'plaidact_repair_breves' );
		$cover_url  = wp_nonce_url( admin_url( 'admin-post.php?action=plaidact_generate_breve_covers' ), 'plaidact_generate_breve_covers' );
		$cover_force_url = wp_nonce_url( add_query_arg( 'force', '1', admin_url( 'admin-post.php?action=plaidact_generate_breve_covers' ) ), 'plaidact_generate_breve_covers' );

		// Compteurs informatifs.
		$without_thumb = 0;
		$without_topic = 0;
		$without_link  = 0;
		$count_posts = wp_count_posts( self::BREVE_POST_TYPE );
		$total = $count_posts ? (int) ( $count_posts->publish + $count_posts->draft + $count_posts->pending + $count_posts->future ) : 0;

		if ( $total > 0 ) {
			$ids = get_posts(
				array(
					'post_type'      => self::BREVE_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 200,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $pid ) {
				if ( ! has_post_thumbnail( $pid ) ) {
					$without_thumb++;
				}
				if ( ! has_term( '', 'plaid_breve_topic', $pid ) && ! empty( self::get_breve_topics_fallback( (int) $pid ) ) ) {
					$without_topic++;
				}
				if ( '' === trim( (string) get_post_meta( $pid, self::BREVE_META_LINK, true ) ) && '' !== self::get_breve_link( (int) $pid ) ) {
					$without_link++;
				}
			}
		}

		?>
		<div class="notice notice-info plaidact-breves-tools" style="padding:1rem;">
			<p style="margin:0 0 0.6rem;"><strong><?php esc_html_e( 'Outils Brèves — liens, thématiques et vignettes', 'plaidact-campaign-core' ); ?></strong></p>
			<p style="margin:0 0 0.8rem;color:#475569;">
				<?php
				echo esc_html(
					sprintf(
						__( '%1$d brève(s) au total. %2$d sans vignette, %3$d avec thématiques historiques à migrer.', 'plaidact-campaign-core' ),
						$total,
						$without_thumb,
						$without_topic
					)
				);
				?>
				<br />
				<span class="description"><?php esc_html_e( 'Le système restaure automatiquement les anciens liens/sources/thématiques à l’affichage, mais la migration explicite les fige en base (taxonomie + métas canoniques).', 'plaidact-campaign-core' ); ?></span>
			</p>
			<p style="margin:0;display:flex;gap:0.6rem;flex-wrap:wrap;">
				<a class="button button-primary" href="<?php echo esc_url( $repair_url ); ?>"><?php esc_html_e( 'Réparer les anciennes données (liens + thématiques)', 'plaidact-campaign-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( $cover_url ); ?>"><?php esc_html_e( 'Générer les vignettes manquantes', 'plaidact-campaign-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( $cover_force_url ); ?>"><?php esc_html_e( 'Régénérer toutes les vignettes', 'plaidact-campaign-core' ); ?></a>
			</p>
			<p class="description" style="margin:0.6rem 0 0;"><?php esc_html_e( 'Vignette : 1200×630 px, logo PLAID·ACT, titre, thématique et source. Génération automatique à l’enregistrement si la case est cochée.', 'plaidact-campaign-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Enrichit l'affichage d'une brève isolée (single et archive) avec source et lien.
	 *
	 * @param string $content Contenu filtré.
	 * @return string
	 */
	public static function filter_breve_content( string $content ): string {
		if ( is_admin() || is_feed() ) {
			return $content;
		}

		if ( ! is_singular( self::BREVE_POST_TYPE ) && ! is_post_type_archive( self::BREVE_POST_TYPE ) && ! is_tax( 'plaid_breve_topic' ) ) {
			// On limite au front et au type brève pour ne pas polluer d'autres contenus.
			global $post;
			if ( ! $post instanceof \WP_Post || self::BREVE_POST_TYPE !== $post->post_type ) {
				return $content;
			}
			// Hors boucle principale, on ne touche pas.
			if ( ! in_the_loop() || ! is_main_query() ) {
				return $content;
			}
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}
		if ( self::BREVE_POST_TYPE !== get_post_type( $post_id ) ) {
			return $content;
		}

		$link       = self::get_breve_link( (int) $post_id );
		$source     = self::get_breve_source( (int) $post_id );
		$source_url = self::get_breve_source_url( (int) $post_id );
		$topics     = self::get_breve_topics( (int) $post_id );

		$extra = '';

		if ( ! empty( $topics ) ) {
			$tags_html = '';
			foreach ( $topics as $t ) {
				$name = (string) ( $t['name'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				$href = $t['link'] ?? '';
				$is_link = is_string( $href ) && '' !== $href && ! is_wp_error( $href );
				if ( $is_link ) {
					$tags_html .= sprintf( '<a href="%s" class="plaidact-breve__tag">#%s</a> ', esc_url( $href ), esc_html( ltrim( $name, '#' ) ) );
				} else {
					$tags_html .= sprintf( '<span class="plaidact-breve__tag">#%s</span> ', esc_html( ltrim( $name, '#' ) ) );
				}
			}
			if ( '' !== $tags_html ) {
				$extra .= '<div class="plaidact-breve__tags plaidact-breve__tags--single" aria-label="' . esc_attr__( 'Thématiques', 'plaidact-campaign-core' ) . '">' . $tags_html . '</div>';
			}
		}

		if ( '' !== $source ) {
			$source_html = '';
			if ( '' !== $source_url && filter_var( $source_url, FILTER_VALIDATE_URL ) ) {
				$source_html = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $source_url ), esc_html( $source ) );
			} else {
				$source_html = esc_html( $source );
			}
			$extra .= '<p class="plaidact-breve__source-line"><strong>' . esc_html__( 'Source :', 'plaidact-campaign-core' ) . '</strong> ' . $source_html . '</p>';
		}

		if ( '' !== $link ) {
			$extra .= '<p class="plaidact-breve__source-cta"><a class="plaidact-breve__link" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Lire la source', 'plaidact-campaign-core' ) . ' <span aria-hidden="true">↗</span></a></p>';
		}

		if ( '' === $extra ) {
			return $content;
		}

		return $content . '<footer class="plaidact-breve__footer" style="margin-top:1.2rem;padding-top:1rem;border-top:1px solid #e5e7eb;">' . $extra . '</footer>';
	}
}
