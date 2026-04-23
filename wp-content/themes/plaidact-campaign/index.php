<?php
/**
 * Fallback template required for WordPress theme installation.
 *
 * @package PLAIDACT\CampaignTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_front_page() ) {
	get_template_part( 'front-page' );
	return;
}

get_header();
?>
<main class="section" id="contenu">
	<div class="wrap">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'plaidact-card' ); ?>>
					<h1><?php the_title(); ?></h1>
					<?php the_content(); ?>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Aucun contenu trouvé.', 'plaidact-campaign' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
