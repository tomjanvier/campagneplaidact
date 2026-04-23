<?php
/**
 * Partners logos section.
 *
 * @package PLAIDACT\CampaignTheme
 */

$partners = new WP_Query(
	array(
		'post_type'      => 'plaid_partner',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	)
);
?>
<section class="section" id="partenaires">
	<div class="wrap">
		<h2><?php esc_html_e( 'Nos partenaires', 'plaidact-campaign' ); ?></h2>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;align-items:center;">
			<?php if ( $partners->have_posts() ) : ?>
				<?php while ( $partners->have_posts() ) : $partners->the_post(); ?>
					<?php $url = esc_url( (string) get_post_meta( get_the_ID(), '_plaid_partner_url', true ) ); ?>
					<a href="<?php echo $url ?: '#'; ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php the_title_attribute(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<span><?php the_title(); ?></span>
						<?php endif; ?>
					</a>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php endif; ?>
		</div>
	</div>
</section>
