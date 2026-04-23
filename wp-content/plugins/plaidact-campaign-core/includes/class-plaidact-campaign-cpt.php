<?php
/**
 * CPT registration for campaign sites.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers campaign post types and partner metadata.
 */
final class CPT {

	/**
	 * Hooks WordPress actions.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_partner_metabox' ) );
		add_action( 'save_post_plaid_partner', array( __CLASS__, 'save_partner_url' ) );
	}

	/**
	 * Registers campaign post types.
	 *
	 * @return void
	 */
	public static function register_post_types(): void {
		register_post_type(
			'plaid_breve',
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
				'menu_position'=> 21,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'breves' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			)
		);

		register_post_type(
			'plaid_partner',
			array(
				'labels'       => array(
					'name'               => __( 'Partenaires', 'plaidact-campaign-core' ),
					'singular_name'      => __( 'Partenaire', 'plaidact-campaign-core' ),
					'add_new'            => __( 'Ajouter', 'plaidact-campaign-core' ),
					'add_new_item'       => __( 'Ajouter un partenaire', 'plaidact-campaign-core' ),
					'edit_item'          => __( 'Modifier le partenaire', 'plaidact-campaign-core' ),
					'new_item'           => __( 'Nouveau partenaire', 'plaidact-campaign-core' ),
					'view_item'          => __( 'Voir le partenaire', 'plaidact-campaign-core' ),
					'search_items'       => __( 'Rechercher un partenaire', 'plaidact-campaign-core' ),
					'not_found'          => __( 'Aucun partenaire trouvé', 'plaidact-campaign-core' ),
					'not_found_in_trash' => __( 'Aucun partenaire dans la corbeille', 'plaidact-campaign-core' ),
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-groups',
				'menu_position'=> 22,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'partenaires' ),
				'supports'     => array( 'title', 'thumbnail' ),
			)
		);
	}

	/**
	 * Adds URL metabox for partners.
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
	 * Renders partner URL field.
	 *
	 * @param \WP_Post $post Current post object.
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
	 * Saves partner URL field.
	 *
	 * @param int $post_id Current post ID.
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
}
