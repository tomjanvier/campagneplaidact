<?php
/**
 * Breves section.
 *
 * @package PLAIDACT\CampaignTheme
 */

$breves = new WP_Query(
	array(
		'post_type'      => 'plaid_breve',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
	)
);
?>
<section class="section" id="breves" style="background:#fff;">
	<div class="wrap">
		<h2><?php esc_html_e( 'Les brèves', 'plaidact-campaign' ); ?></h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;">
			<?php while ( $breves->have_posts() ) : $breves->the_post(); ?>
				<article style="padding:1rem;border:1px solid #e5e7eb;border-radius:12px;">
					<h3><?php the_title(); ?></h3>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</div>
</section>
