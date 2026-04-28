<?php
/**
 * Plugin Name: PLAID·ACT Campagne Onepage
 * Description: Déclare une taxonomie campagne et génère automatiquement des pages one-page pilotées depuis un plugin.
 * Version: 0.2.1
 * Author: PLAID·ACT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plaidact_Campagne_Onepage {
	const TAXONOMY            = 'campagne';
	const PAGE_TERM_META_KEY  = '_plaidact_campagne_term_id';
	const TERM_PAGE_META_KEY  = '_plaidact_campagne_page_id';
	const SHORTCODE_TAG       = 'plaidact_campagne_onepage';
	const DEFAULT_PAGE_STATUS = 'publish';
	const ASSET_VERSION       = '0.2.1';

	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'create_onepage_for_term' ), 10, 2 );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'sync_onepage_title_with_term' ), 10, 2 );
		add_action( 'delete_' . self::TAXONOMY, array( $this, 'trash_onepage_for_term' ), 10, 4 );
		add_filter( 'template_include', array( $this, 'load_plugin_template_for_campaign_pages' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public static function activate() {
		$plugin = new self();
		$plugin->register_taxonomy();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function register_taxonomy() {
		$labels = array(
			'name'              => __( 'Campagnes', 'plaidact-campagne-onepage' ),
			'singular_name'     => __( 'Campagne', 'plaidact-campagne-onepage' ),
			'search_items'      => __( 'Rechercher des campagnes', 'plaidact-campagne-onepage' ),
			'all_items'         => __( 'Toutes les campagnes', 'plaidact-campagne-onepage' ),
			'edit_item'         => __( 'Modifier la campagne', 'plaidact-campagne-onepage' ),
			'update_item'       => __( 'Mettre à jour la campagne', 'plaidact-campagne-onepage' ),
			'add_new_item'      => __( 'Ajouter une campagne', 'plaidact-campagne-onepage' ),
			'new_item_name'     => __( 'Nom de la nouvelle campagne', 'plaidact-campagne-onepage' ),
			'menu_name'         => __( 'Campagnes', 'plaidact-campagne-onepage' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( 'post', 'page' ),
			array(
				'labels'            => $labels,
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'campagne' ),
			)
		);
	}

	public function register_shortcode() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_onepage_shortcode' ) );
	}

	public function create_onepage_for_term( $term_id ) {
		$term = get_term( $term_id, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$existing_page_id = (int) get_term_meta( $term_id, self::TERM_PAGE_META_KEY, true );
		if ( $existing_page_id > 0 && 'trash' !== get_post_status( $existing_page_id ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => sprintf( __( 'Campagne : %s', 'plaidact-campagne-onepage' ), $term->name ),
				'post_name'    => 'campagne-' . $term->slug,
				'post_status'  => self::DEFAULT_PAGE_STATUS,
				'post_content' => sprintf( '[%s term_slug="%s"]', self::SHORTCODE_TAG, esc_attr( $term->slug ) ),
			)
		);

		if ( is_wp_error( $page_id ) ) {
			return;
		}

		update_post_meta( $page_id, self::PAGE_TERM_META_KEY, (int) $term_id );
		update_term_meta( $term_id, self::TERM_PAGE_META_KEY, (int) $page_id );
	}

	public function sync_onepage_title_with_term( $term_id ) {
		$page_id = (int) get_term_meta( $term_id, self::TERM_PAGE_META_KEY, true );

		if ( $page_id <= 0 ) {
			return;
		}

		$term = get_term( $term_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $page_id,
				'post_title' => sprintf( __( 'Campagne : %s', 'plaidact-campagne-onepage' ), $term->name ),
			)
		);
	}

	public function trash_onepage_for_term( $term_id ) {
		$page_id = (int) get_term_meta( $term_id, self::TERM_PAGE_META_KEY, true );

		if ( $page_id > 0 ) {
			wp_trash_post( $page_id );
		}
	}

	public function load_plugin_template_for_campaign_pages( $template ) {
		if ( ! is_page() ) {
			return $template;
		}

		$page_id = get_queried_object_id();
		$term_id = (int) get_post_meta( $page_id, self::PAGE_TERM_META_KEY, true );

		if ( $term_id <= 0 ) {
			return $template;
		}

		$plugin_template = plugin_dir_path( __FILE__ ) . 'templates/campagne-onepage.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	public function enqueue_assets() {
		if ( ! is_page() ) {
			return;
		}

		$page_id = get_queried_object_id();
		$term_id = (int) get_post_meta( $page_id, self::PAGE_TERM_META_KEY, true );
		if ( $term_id <= 0 ) {
			return;
		}

		wp_enqueue_style(
			'plaidact-campagne-onepage',
			plugin_dir_url( __FILE__ ) . 'assets/campagne-onepage.css',
			array(),
			self::ASSET_VERSION
		);

		wp_enqueue_script(
			'plaidact-campagne-onepage',
			plugin_dir_url( __FILE__ ) . 'assets/campagne-onepage.js',
			array(),
			self::ASSET_VERSION,
			true
		);
	}

	private function get_main_site_url(): string {
		if ( is_multisite() ) {
			return (string) get_site_url( 1, '/' );
		}

		return (string) home_url( '/' );
	}

	private function is_section_enabled( string $key, bool $default = true ): bool {
		if ( function_exists( 'plaidact_is_enabled' ) ) {
			return (bool) plaidact_is_enabled( $key, $default );
		}

		return $default;
	}

	public function render_onepage_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'term_slug' => '',
			),
			$atts,
			self::SHORTCODE_TAG
		);

		$term = null;
		if ( ! empty( $atts['term_slug'] ) ) {
			$term = get_term_by( 'slug', sanitize_title( $atts['term_slug'] ), self::TAXONOMY );
		}

		if ( ! $term ) {
			$page_id = get_queried_object_id();
			$term_id = (int) get_post_meta( $page_id, self::PAGE_TERM_META_KEY, true );
			if ( $term_id > 0 ) {
				$term = get_term( $term_id, self::TAXONOMY );
			}
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$settings           = (array) get_option( 'plaidact_campaign_settings', array() );
		$share_default_text = (string) ( $settings['social_share_text'] ?? __( 'Je soutiens cette campagne citoyenne. Rejoignez-nous !', 'plaidact-campagne-onepage' ) );
		$share_page         = get_permalink( get_queried_object_id() ) ?: home_url( '/' );
		$share_page_encoded = rawurlencode( $share_page );
		$main_site_url      = $this->get_main_site_url();
		$partners           = get_posts(
			array(
				'post_type'      => 'plaid_partner',
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			)
		);

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
			)
		);

		ob_start();
		?>
		<section class="plaidact-campagne-hero">
			<div class="plaidact-wrap">
				<a class="plaidact-return-link" href="<?php echo esc_url( $main_site_url ); ?>"><?php esc_html_e( 'Revenir sur le site de PLAID·ACT', 'plaidact-campagne-onepage' ); ?></a>
				<p class="plaidact-kicker"><?php esc_html_e( 'Campagne', 'plaidact-campagne-onepage' ); ?></p>
				<h1><?php echo esc_html( $term->name ); ?></h1>
				<?php if ( ! empty( $term->description ) ) : ?>
					<p class="plaidact-lead"><?php echo esc_html( $term->description ); ?></p>
				<?php endif; ?>
				<div class="plaidact-share-box">
					<label for="plaidact-share-text"><strong><?php esc_html_e( 'Texte du post à partager', 'plaidact-campagne-onepage' ); ?></strong></label>
					<textarea id="plaidact-share-text" class="plaidact-share-box__input" rows="3"><?php echo esc_textarea( $share_default_text ); ?></textarea>
					<div class="plaidact-share-links">
						<a class="plaidact-share-link" data-share-target="whatsapp" href="<?php echo esc_url( 'https://api.whatsapp.com/send?text=' . rawurlencode( $share_default_text . ' ' . $share_page ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Partager sur WhatsApp', 'plaidact-campagne-onepage' ); ?>">🟢 <span>WhatsApp</span></a>
						<a class="plaidact-share-link" data-share-target="instagram" href="<?php echo esc_url( 'https://www.instagram.com/' ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Partager sur Instagram', 'plaidact-campagne-onepage' ); ?>">📸 <span>Instagram</span></a>
						<a class="plaidact-share-link" data-share-target="x" href="<?php echo esc_url( 'https://twitter.com/intent/tweet?url=' . $share_page_encoded . '&text=' . rawurlencode( $share_default_text ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Partager sur X', 'plaidact-campagne-onepage' ); ?>">𝕏 <span>X</span></a>
					</div>
				</div>
			</div>
		</section>

			<section class="plaidact-campagne-actions">
				<div class="plaidact-wrap plaidact-grid plaidact-grid--actions">
					<div><?php echo do_shortcode( '[petition_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div><?php echo do_shortcode( '[plaid_send_campaign]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
			</section>

			<section class="plaidact-campagne-actions">
				<div class="plaidact-wrap">
					<?php echo do_shortcode( '[plaid_newsletter_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</section>

		<?php if ( ! empty( $partners ) ) : ?>
			<section class="plaidact-campagne-partners">
				<div class="plaidact-wrap">
					<h2><?php esc_html_e( 'Organisations de la campagne', 'plaidact-campagne-onepage' ); ?></h2>
					<div class="plaidact-partners-grid">
						<?php foreach ( $partners as $partner ) : ?>
							<div class="plaidact-partner-card">
								<?php echo get_the_post_thumbnail( $partner->ID, 'medium', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $this->is_section_enabled( 'enable_socialwall', true ) ) : ?>
				<section class="plaidact-campagne-content">
					<div class="plaidact-wrap">
						<h2><?php echo esc_html( (string) get_theme_mod( 'social_wall_title', __( 'Social Wall', 'plaidact-campagne-onepage' ) ) ); ?></h2>
						<p><?php echo esc_html( (string) get_theme_mod( 'social_wall_description', __( 'Suivez ici les publications liées à la campagne.', 'plaidact-campagne-onepage' ) ) ); ?></p>
						<?php echo do_shortcode( '[plaid_social_wall]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $this->is_section_enabled( 'enable_report_highlight', false ) ) : ?>
				<section class="plaidact-campagne-content">
					<div class="plaidact-wrap">
						<h2><?php echo esc_html( (string) get_theme_mod( 'report_title', __( 'Rapport de campagne', 'plaidact-campagne-onepage' ) ) ); ?></h2>
						<p><?php echo esc_html( (string) get_theme_mod( 'report_excerpt', __( 'Consultez notre rapport PDF mis en avant.', 'plaidact-campagne-onepage' ) ) ); ?></p>
						<?php
						$report_pdf_url = esc_url( (string) get_theme_mod( 'report_pdf_url', '' ) );
						if ( $report_pdf_url ) :
							?>
							<p><a class="plaidact-share-link" href="<?php echo esc_url( $report_pdf_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) get_theme_mod( 'report_button_label', __( 'Lire le rapport PDF', 'plaidact-campagne-onepage' ) ) ); ?></a></p>
						<?php else : ?>
							<p><?php echo esc_html( (string) get_theme_mod( 'report_empty_hint', __( 'Ajoutez une URL de PDF dans le customizer du thème.', 'plaidact-campagne-onepage' ) ) ); ?></p>
						<?php endif; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="plaidact-campagne-content">
				<div class="plaidact-wrap">
					<h2><?php echo esc_html( (string) get_theme_mod( 'articles_section_title', __( 'Les articles de fond', 'plaidact-campagne-onepage' ) ) ); ?></h2>
					<?php if ( empty( $posts ) ) : ?>
						<p><?php esc_html_e( 'Aucun article associé à cette campagne pour le moment.', 'plaidact-campagne-onepage' ); ?></p>
					<?php else : ?>
					<div class="plaidact-grid">
						<?php foreach ( $posts as $post ) : ?>
							<article class="plaidact-card">
								<h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $post->post_content ), 26 ) ); ?></p>
							</article>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}

new Plaidact_Campagne_Onepage();

register_activation_hook( __FILE__, array( 'Plaidact_Campagne_Onepage', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Plaidact_Campagne_Onepage', 'deactivate' ) );
