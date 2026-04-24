<?php
/**
 * Articles section.
 *
 * @package PLAIDACT\CampaignTheme
 */

$articles = new WP_Query(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
	)
);
?>
<section class="section" id="articles">
	<div class="wrap">
		<h2><?php echo esc_html( (string) get_theme_mod( 'articles_section_title', __( 'Les articles de fond', 'plaidact-campaign' ) ) ); ?></h2>
		<?php if ( $articles->have_posts() ) : ?>
			<?php while ( $articles->have_posts() ) : $articles->the_post(); ?>
				<article class="plaidact-card" style="margin-bottom:1rem;">
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p><?php echo esc_html( (string) get_theme_mod( 'articles_empty_text', __( 'Aucun article publié pour le moment.', 'plaidact-campaign' ) ) ); ?></p>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</section>
