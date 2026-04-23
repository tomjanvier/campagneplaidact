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
$share_url     = rawurlencode( home_url( '/' ) );
$share_text    = rawurlencode( $hero_title );
?>

<section class="section hero" id="accueil">
	<?php if ( $hero_video ) : ?>
		<video class="hero__media" autoplay muted loop playsinline>
			<source src="<?php echo $hero_video; ?>" type="video/mp4" />
		</video>
	<?php elseif ( $hero_image ) : ?>
		<div class="hero__media" style="background-image:url('<?php echo $hero_image; ?>');"></div>
	<?php endif; ?>

	<a class="hero__share" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr( $share_url ); ?>&quote=<?php echo esc_attr( $share_text ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Partager', 'plaidact-campaign' ); ?></a>

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
		<div class="hero__mail-share"><?php echo do_shortcode( '[plaid_send_campaign]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php get_template_part( 'template-parts/sections/partners', null, array( 'in_hero' => true ) ); ?>
	</div>
</section>

<section class="section hero-newsletter">
	<div class="wrap">
		<?php echo do_shortcode( '[plaid_newsletter_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>

<?php if ( plaidact_is_enabled( 'enable_petition', true ) ) : ?>
	<?php get_template_part( 'template-parts/sections/petition' ); ?>
<?php endif; ?>

<?php get_template_part( 'template-parts/sections/breves' ); ?>

<?php if ( plaidact_is_enabled( 'enable_articles', true ) ) : ?>
	<?php get_template_part( 'template-parts/sections/articles' ); ?>
<?php endif; ?>

<?php if ( plaidact_is_enabled( 'enable_report_highlight', false ) ) : ?>
	<?php get_template_part( 'template-parts/sections/report-highlight' ); ?>
<?php endif; ?>

<?php if ( plaidact_is_enabled( 'enable_socialwall', true ) ) : ?>
	<?php get_template_part( 'template-parts/sections/social-wall' ); ?>
<?php endif; ?>

<?php get_footer(); ?>
