<?php
/**
 * Send campaign by email section.
 *
 * @package PLAIDACT\CampaignTheme
 */

$title       = (string) get_theme_mod( 'send_mail_section_title', __( 'Partager la campagne par email', 'plaidact-campaign' ) );
$description = (string) get_theme_mod( 'send_mail_section_description', __( 'Envoyez la campagne à vos proches depuis ce formulaire.', 'plaidact-campaign' ) );
?>
<section class="section" id="envoyer-campagne">
	<div class="wrap">
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $description ); ?></p>
		<?php echo do_shortcode( '[plaid_send_campaign]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</section>
