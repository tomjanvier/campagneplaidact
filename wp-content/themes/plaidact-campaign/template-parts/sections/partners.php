<?php
/**
 * Partners logos section.
 *
 * @package PLAIDACT\CampaignTheme
 */

$in_hero  = isset( $args['in_hero'] ) ? (bool) $args['in_hero'] : false;
$partners = new WP_Query(
	array(
		'post_type'      => 'plaid_partner',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
	)
);
?>
<section class="<?php echo $in_hero ? 'hero-partners' : 'section partners-section'; ?>" id="partenaires">
	<div class="<?php echo $in_hero ? 'hero-partners__inner' : 'wrap'; ?>">
		<h2 class="<?php echo $in_hero ? 'hero-partners__title' : 'partners-section__title'; ?>"><?php esc_html_e( 'Nos partenaires', 'plaidact-campaign' ); ?></h2>
		<div class="<?php echo $in_hero ? 'hero-partners__grid' : 'partners-section__grid'; ?>">
			<?php if ( $partners->have_posts() ) : ?>
				<?php while ( $partners->have_posts() ) : $partners->the_post(); ?>
					<?php $url = esc_url( (string) get_post_meta( get_the_ID(), '_plaid_partner_url', true ) ); ?>
					<a class="<?php echo $in_hero ? 'partner-logo-card partner-logo-card--hero' : 'partner-logo-card'; ?>" href="<?php echo $url ?: '#'; ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php the_title_attribute(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<span><?php the_title(); ?></span>
						<?php endif; ?>
					</a>
				<?php endwhile; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Ajoutez des partenaires dans l’administration pour afficher leurs logos ici.', 'plaidact-campaign' ); ?></p>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</div>
</section>
