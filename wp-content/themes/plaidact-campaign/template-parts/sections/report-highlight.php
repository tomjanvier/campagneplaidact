<?php
/**
 * Report highlight section.
 *
 * @package PLAIDACT\CampaignTheme
 */

$report_pdf_url = esc_url( (string) get_theme_mod( 'report_pdf_url', '' ) );
$report_title   = (string) get_theme_mod( 'report_title', __( 'Rapport de campagne 2026', 'plaidact-campaign' ) );
$report_excerpt = (string) get_theme_mod( 'report_excerpt', __( 'Consultez notre note stratégique complète en PDF : constats, recommandations et plan d’action.', 'plaidact-campaign' ) );
$report_cta     = (string) get_theme_mod( 'report_button_label', __( 'Lire le rapport PDF', 'plaidact-campaign' ) );
$report_tagline = (string) get_theme_mod( 'report_eyebrow', __( 'À la une', 'plaidact-campaign' ) );
$report_hint    = (string) get_theme_mod( 'report_empty_hint', __( 'Ajoutez l’URL du PDF dans Apparence → Personnaliser → Rapport PDF mis en avant.', 'plaidact-campaign' ) );
?>
<section class="section report-highlight" id="rapport">
	<div class="wrap">
		<div class="report-highlight__card">
			<p class="report-highlight__eyebrow"><?php echo esc_html( $report_tagline ); ?></p>
			<h2><?php echo esc_html( $report_title ); ?></h2>
			<p><?php echo esc_html( $report_excerpt ); ?></p>
			<?php if ( $report_pdf_url ) : ?>
				<p>
					<a class="plaidact-button report-highlight__button" href="<?php echo $report_pdf_url; ?>" target="_blank" rel="noopener noreferrer" type="application/pdf">
						<?php echo esc_html( $report_cta ); ?>
					</a>
				</p>
			<?php else : ?>
				<p class="report-highlight__hint"><?php echo esc_html( $report_hint ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
