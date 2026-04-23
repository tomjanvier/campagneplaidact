<?php
/**
 * Theme footer.
 *
 * @package PLAIDACT\CampaignTheme
 */
?>
<footer class="section campaign-footer" id="footer">
	<div class="wrap campaign-footer__inner">
		<nav class="campaign-footer__menu" aria-label="Menu de pied de page">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'onepage',
					'container'      => false,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
		<div class="campaign-footer__meta">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> PLAID·ACT — Tous droits réservés.</p>
			<p><a href="https://plaid-act.org" target="_blank" rel="noopener noreferrer">Écosystème PLAID·ACT</a></p>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
