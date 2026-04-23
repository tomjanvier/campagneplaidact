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

$hero_image = esc_url( (string) get_theme_mod( 'hero_background_image', '' ) );
$hero_video = esc_url( (string) get_theme_mod( 'hero_background_video', '' ) );
?>

<header class="section campaign-header" id="top">
	<div class="wrap" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;">
		<a href="https://plaid-act.org" target="_blank" rel="noopener noreferrer" aria-label="PLAID·ACT">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<strong>PLAID·ACT</strong>
			<?php endif; ?>
		</a>
		<nav aria-label="Navigation de campagne">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'onepage',
					'container'      => false,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
	</div>
</header>

<section class="section hero" id="accueil" style="position:relative;min-height:100vh;display:grid;place-items:center;overflow:hidden;">
	<?php if ( $hero_video ) : ?>
		<video autoplay muted loop playsinline style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
			<source src="<?php echo $hero_video; ?>" type="video/mp4" />
		</video>
	<?php elseif ( $hero_image ) : ?>
		<div style="position:absolute;inset:0;background:url('<?php echo $hero_image; ?>') center / cover no-repeat;"></div>
	<?php endif; ?>

	<div class="wrap" style="position:relative;z-index:1;color:#fff;text-align:center;">
		<h1 class="hero-title" style="font-size:clamp(2rem,6vw,5rem);line-height:1.1;">
			<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
		</h1>
		<p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
	</div>
</section>

<?php get_template_part( 'template-parts/sections/partners' ); ?>
<?php if ( plaidact_is_enabled( 'enable_petition', true ) ) : ?>
	<?php get_template_part( 'template-parts/sections/petition' ); ?>
<?php endif; ?>

<?php get_template_part( 'template-parts/sections/breves' ); ?>

<?php if ( plaidact_is_enabled( 'enable_articles', true ) ) : ?>
	<?php get_template_part( 'template-parts/sections/articles' ); ?>
<?php endif; ?>

<?php if ( plaidact_is_enabled( 'enable_socialwall', true ) ) : ?>
	<?php get_template_part( 'template-parts/sections/social-wall' ); ?>
<?php endif; ?>

<?php get_footer(); ?>
