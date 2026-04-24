<?php
/**
 * Social wall section.
 *
 * @package PLAIDACT\CampaignTheme
 */
?>
<section class="section" id="social-wall" style="background:#fff;">
	<div class="wrap">
		<h2><?php echo esc_html( (string) get_theme_mod( 'social_wall_title', __( 'Social Wall', 'plaidact-campaign' ) ) ); ?></h2>
		<p><?php echo esc_html( (string) get_theme_mod( 'social_wall_description', __( 'Sélectionnez vos posts Bluesky, Instagram et autres depuis le back office pour les afficher ici.', 'plaidact-campaign' ) ) ); ?></p>
		<div>
			<?php echo do_shortcode( '[plaid_social_wall]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
