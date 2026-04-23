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
		<h2><?php esc_html_e( 'Les articles de fond', 'plaidact-campaign' ); ?></h2>
		<?php while ( $articles->have_posts() ) : $articles->the_post(); ?>
			<article style="margin-bottom:1.5rem;">
				<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</section>
