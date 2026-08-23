<?php

use Plaidact\CampaignCore\Association_Directory as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$asso_taxonomy = Plugin::get_asso_taxonomy();
?>
<main id="site-content" class="plaidact-asso-single-wrap">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'plaidact-asso-single' ); ?>>
			<a class="plaidact-asso-back" href="<?php echo esc_url( 'https://plaidact.org/repertoire/' ); ?>">← <?php esc_html_e( 'Retour au répertoire', 'plaidact-campaign-core' ); ?></a>
			<header class="plaidact-asso-single__header">
				<div class="plaidact-asso-single__media">
					<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large' ); } ?>
				</div>
				<div class="plaidact-asso-single__hero">
					<h1><?php the_title(); ?></h1>
					<div class="plaidact-asso-tags">
						<?php foreach ( ( get_the_terms( get_the_ID(), $asso_taxonomy ) ?: [] ) as $term ) : ?><a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a><?php endforeach; ?>
					</div>
					<div class="plaidact-asso-card__actions">
						<?php if ( \plaidact_campaign_core_get_field( 'url_web' ) ) : ?><a class="plaidact-btn" href="<?php echo esc_url( (string) \plaidact_campaign_core_get_field( 'url_web' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visiter le site web', 'plaidact-campaign-core' ); ?></a><?php endif; ?>
						<?php if ( \plaidact_campaign_core_get_field( 'url_don' ) ) : ?><a class="plaidact-btn plaidact-btn--ghost" href="<?php echo esc_url( (string) \plaidact_campaign_core_get_field( 'url_don' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Faire un don', 'plaidact-campaign-core' ); ?></a><?php endif; ?>
						<?php if ( \plaidact_campaign_core_get_field( 'url_contact' ) ) : ?><a class="plaidact-btn plaidact-btn--ghost" href="<?php echo esc_url( (string) \plaidact_campaign_core_get_field( 'url_contact' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contacter', 'plaidact-campaign-core' ); ?></a><?php endif; ?>
					</div>
					<?php $social_links = Plugin::get_asso_social_links( get_the_ID() ); ?>
					<?php if ( ! empty( $social_links ) ) : ?>
						<div class="plaidact-asso-socials" aria-label="<?php esc_attr_e( 'Réseaux sociaux', 'plaidact-campaign-core' ); ?>">
							<?php foreach ( $social_links as $network ) : ?>
								<a class="plaidact-asso-socials__link" href="<?php echo esc_url( (string) $network['url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<img src="<?php echo esc_url( (string) $network['icon'] ); ?>" alt="" width="16" height="16" loading="lazy" decoding="async" />
									<span><?php echo esc_html( (string) $network['label'] ); ?></span>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</header>

			<section class="plaidact-asso-single__meta-grid">
				<div>
					<h2><?php esc_html_e( 'Présentation', 'plaidact-campaign-core' ); ?></h2>
					<div class="plaidact-asso-single__content"><?php the_content(); ?></div>
					<?php if ( \plaidact_campaign_core_get_field( 'resume_court' ) ) : ?>
						<p class="plaidact-asso-single__summary"><?php echo esc_html( (string) \plaidact_campaign_core_get_field( 'resume_court' ) ); ?></p>
					<?php endif; ?>
					<p class="plaidact-asso-single__edit-note">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s association name. */
								__( 'Si vous êtes représentant·e de %s, n’hésitez pas à <a href="%s">nous contacter</a> pour modifier votre fiche.', 'plaidact-campaign-core' ),
								'<strong>' . esc_html( get_the_title() ) . '</strong>',
								esc_url( 'https://plaidact.org/nous-contacter/' )
							),
							[
								'a'      => [ 'href' => [] ],
								'strong' => [],
							]
						);
						?>
					</p>
				</div>
			</section>

			<?php $similar = Plugin::get_similar_asso( get_the_ID(), 3 ); ?>
			<?php if ( ! empty( $similar ) ) : ?>
			<section class="plaidact-asso-related">
				<h2><?php esc_html_e( 'Associations qui travaillent sur la même cause', 'plaidact-campaign-core' ); ?></h2>
				<div class="plaidact-asso-grid">
					<?php foreach ( $similar as $item ) : ?>
						<?php Plugin::render_template( 'parts/asso-card.php', [ 'card' => Plugin::get_asso_card_data( (int) $item['post_id'] ) ] ); ?>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
