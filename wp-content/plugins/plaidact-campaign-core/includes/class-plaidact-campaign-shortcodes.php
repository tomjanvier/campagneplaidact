<?php
/**
 * Campaign shortcodes for petition and social wall placeholders.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers campaign shortcodes.
 */
final class Shortcodes {

	/**
	 * Hooks WordPress actions.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_shortcode( 'petition_form', array( __CLASS__, 'render_petition_form' ) );
		add_shortcode( 'plaid_social_wall', array( __CLASS__, 'render_social_wall' ) );
	}

	/**
	 * Renders the petition section placeholder.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_petition_form( array $atts = array() ): string {
		$attributes = shortcode_atts(
			array(
				'embed_url'   => '',
				'button_text' => __( 'Signer maintenant', 'plaidact-campaign-core' ),
			),
			$atts,
			'petition_form'
		);

		$embed_url = esc_url( (string) $attributes['embed_url'] );
		$button    = esc_html( (string) $attributes['button_text'] );

		ob_start();
		?>
		<div class="plaidact-card plaidact-card--petition">
			<p><?php esc_html_e( 'Connectez votre outil de pétition via un embed ou un shortcode dédié.', 'plaidact-campaign-core' ); ?></p>
			<?php if ( ! empty( $embed_url ) ) : ?>
				<p><a class="plaidact-button" href="<?php echo $embed_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo $button; ?></a></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the social wall placeholder.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_social_wall( array $atts = array() ): string {
		$attributes = shortcode_atts(
			array(
				'sources' => 'Instagram, Bluesky, YouTube',
			),
			$atts,
			'plaid_social_wall'
		);

		$sources = array_filter( array_map( 'trim', explode( ',', (string) $attributes['sources'] ) ) );

		ob_start();
		?>
		<div class="plaidact-card plaidact-card--social">
			<p><?php esc_html_e( 'Social wall connecté prochainement. Sources prévues :', 'plaidact-campaign-core' ); ?></p>
			<ul>
				<?php foreach ( $sources as $source ) : ?>
					<li><?php echo esc_html( $source ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
