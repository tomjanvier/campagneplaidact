<?php
/**
 * Social wall section.
 *
 * @package PLAIDACT\CampaignTheme
 */
?>
<section class="section" id="social-wall" style="background:#fff;">
	<div class="wrap">
		<h2><?php esc_html_e( 'Social Wall', 'plaidact-campaign' ); ?></h2>
		<p><?php esc_html_e( 'Connectez ici vos flux Instagram / Bluesky via un connecteur social.', 'plaidact-campaign' ); ?></p>
		<div>
			<?php echo do_shortcode( '[plaid_social_wall]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
