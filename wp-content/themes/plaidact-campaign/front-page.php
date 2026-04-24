<?php
/**
 * One-page campaign template.
 *
 * @package PLAIDACT\CampaignTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$hero_image    = esc_url( (string) get_theme_mod( 'hero_background_image', '' ) );
$hero_video    = esc_url( (string) get_theme_mod( 'hero_background_video', '' ) );
$hero_title    = (string) get_theme_mod( 'hero_title', get_bloginfo( 'name' ) );
$hero_subtitle = (string) get_theme_mod( 'hero_subtitle', get_bloginfo( 'description' ) );
$petition_url  = esc_url( (string) get_theme_mod( 'hero_primary_cta_url', '#petition' ) );
$petition_text = (string) get_theme_mod( 'hero_primary_cta_label', __( 'Signer la pétition', 'plaidact-campaign' ) );
$learn_url     = esc_url( (string) get_theme_mod( 'hero_secondary_cta_url', '#breves' ) );
$learn_text    = (string) get_theme_mod( 'hero_secondary_cta_label', __( 'En savoir plus', 'plaidact-campaign' ) );
$share_page    = home_url( '/' );
$share_url     = rawurlencode( $share_page );
$share_text    = rawurlencode( $hero_title );

$share_links = array(
	array(
		'label' => __( 'Facebook', 'plaidact-campaign' ),
		'icon'  => 'f',
		'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url,
	),
	array(
		'label' => __( 'X / Twitter', 'plaidact-campaign' ),
		'icon'  => '𝕏',
		'url'   => 'https://twitter.com/intent/tweet?url=' . $share_url . '&text=' . $share_text,
	),
	array(
		'label' => __( 'LinkedIn', 'plaidact-campaign' ),
		'icon'  => 'in',
		'url'   => 'https://www.linkedin.com/shareArticle?mini=true&url=' . $share_url,
	),
	array(
		'label' => __( 'WhatsApp', 'plaidact-campaign' ),
		'icon'  => 'wa',
		'url'   => 'https://api.whatsapp.com/send?text=' . rawurlencode( $hero_title . ' ' . $share_page ),
	),
	array(
		'label' => __( 'Telegram', 'plaidact-campaign' ),
		'icon'  => 'tg',
		'url'   => 'https://t.me/share/url?url=' . $share_url . '&text=' . $share_text,
	),
	array(
		'label' => __( 'Bluesky', 'plaidact-campaign' ),
		'icon'  => 'b',
		'url'   => 'https://bsky.app/intent/compose?text=' . rawurlencode( $hero_title . ' ' . $share_page ),
	),
	array(
		'label' => __( 'Email', 'plaidact-campaign' ),
		'icon'  => '@',
		'url'   => 'mailto:?subject=' . $share_text . '&body=' . rawurlencode( $hero_title . "\n\n" . $share_page ),
	),
	array(
		'label' => __( 'Message', 'plaidact-campaign' ),
		'icon'  => 'sms',
		'url'   => 'sms:?body=' . rawurlencode( $hero_title . ' ' . $share_page ),
	),
);
?>

<section class="section hero" id="accueil">
	<?php if ( $hero_video ) : ?>
		<video class="hero__media" autoplay muted loop playsinline preload="metadata" poster="<?php echo $hero_image ? $hero_image : ''; ?>">
			<source src="<?php echo $hero_video; ?>" type="video/mp4" />
		</video>
	<?php elseif ( $hero_image ) : ?>
		<div class="hero__media" style="background-image:url('<?php echo $hero_image; ?>');"></div>
	<?php endif; ?>

	<div class="hero__share-list" aria-label="<?php esc_attr_e( 'Partage sur les réseaux', 'plaidact-campaign' ); ?>">
		<?php foreach ( $share_links as $share ) : ?>
			<a class="hero__share-link" href="<?php echo esc_url( $share['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $share['label'] ); ?>">
				<span aria-hidden="true"><?php echo esc_html( $share['icon'] ); ?></span>
				<span class="hero__share-tooltip"><?php echo esc_html( $share['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<div class="hero__overlay"></div>
	<div class="wrap hero__content">
		<?php if ( has_custom_logo() ) : ?>
			<div class="hero__logo"><?php the_custom_logo(); ?></div>
		<?php endif; ?>
		<h1 class="hero-title"><?php echo esc_html( $hero_title ); ?></h1>
		<p><?php echo esc_html( $hero_subtitle ); ?></p>
		<div class="hero__actions">
			<a class="plaidact-button" href="<?php echo $petition_url; ?>"><?php echo esc_html( $petition_text ); ?></a>
			<a class="hero__link" href="<?php echo $learn_url; ?>"><?php echo esc_html( $learn_text ); ?> <span aria-hidden="true">→</span></a>
		</div>
		<?php get_template_part( 'template-parts/sections/partners', null, array( 'in_hero' => true ) ); ?>
	</div>
</section>

<section class="section hero-newsletter">
	<div class="wrap">
		<?php echo do_shortcode( '[plaid_newsletter_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>

<?php
$sections = array(
	'petition' => static function (): void {
		if ( plaidact_is_enabled( 'enable_petition', true ) ) {
			get_template_part( 'template-parts/sections/petition' );
		}
	},
	'breves' => static function (): void {
		get_template_part( 'template-parts/sections/breves' );
	},
	'articles' => static function (): void {
		if ( plaidact_is_enabled( 'enable_articles', true ) ) {
			get_template_part( 'template-parts/sections/articles' );
		}
	},
	'rapport' => static function (): void {
		if ( plaidact_is_enabled( 'enable_report_highlight', false ) ) {
			get_template_part( 'template-parts/sections/report-highlight' );
		}
	},
	'social_wall' => static function (): void {
		if ( plaidact_is_enabled( 'enable_socialwall', true ) ) {
			get_template_part( 'template-parts/sections/social-wall' );
		}
	},
	'send_mail' => static function (): void {
		if ( plaidact_is_enabled( 'enable_send_campaign', true ) ) {
			get_template_part( 'template-parts/sections/send-mail' );
		}
	},
);

$order_string = (string) get_theme_mod( 'campaign_section_order', 'petition,breves,articles,rapport,social_wall,send_mail' );
$order        = array_filter( array_map( 'trim', explode( ',', strtolower( $order_string ) ) ) );
$already_done = array();

foreach ( $order as $section_key ) {
	if ( isset( $sections[ $section_key ] ) && ! isset( $already_done[ $section_key ] ) ) {
		$sections[ $section_key ]();
		$already_done[ $section_key ] = true;
	}
}

foreach ( $sections as $section_key => $renderer ) {
	if ( ! isset( $already_done[ $section_key ] ) ) {
		$renderer();
	}
}
?>

<?php get_footer(); ?>
