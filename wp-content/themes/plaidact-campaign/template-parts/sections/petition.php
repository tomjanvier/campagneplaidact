<?php
/**
 * Petition section.
 *
 * @package PLAIDACT\CampaignTheme
 */
?>
<section class="section" id="petition">
	<div class="wrap">
		<h2><?php esc_html_e( 'Signer la pétition', 'plaidact-campaign' ); ?></h2>
		<p><?php esc_html_e( 'Collectez les signatures, activez l’email transactionnel via votre plugin SMTP WordPress et synchronisez Brevo automatiquement.', 'plaidact-campaign' ); ?></p>
		<div>
			<?php echo do_shortcode( '[petition_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</section>
