<?php

use Plaidact\CampaignCore\Association_Directory as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts_per_page = isset( $posts_per_page ) ? max( 1, absint( $posts_per_page ) ) : 9;
$asso_taxonomy = Plugin::get_asso_taxonomy();
$filters = Plugin::get_asso_filters_from_request();
$query_args = Plugin::get_asso_query_args( $filters, $posts_per_page, isset( $fixed_cause ) ? (string) $fixed_cause : '' );
add_filter( 'posts_where', [ Plugin::class, 'filter_asso_title_like' ], 10, 2 );
$query   = new WP_Query( $query_args );
$causes  = get_terms( [ 'taxonomy' => $asso_taxonomy, 'hide_empty' => false ] );
$fixed_cause = isset( $fixed_cause ) ? sanitize_title( (string) $fixed_cause ) : '';
$pagination_key = isset( $pagination_key ) && '' !== $pagination_key ? sanitize_key( (string) $pagination_key ) : 'asso_page';
?>
<section class="plaidact-asso-directory" aria-label="<?php esc_attr_e( 'Répertoire des associations', 'plaidact-campaign-core' ); ?>">
	<div class="plaidact-asso-directory__lead">
		<h2><?php esc_html_e( 'Répertoire des associations', 'plaidact-campaign-core' ); ?></h2>
		<p><?php esc_html_e( 'Trouvez une association par nom et par cause en quelques secondes.', 'plaidact-campaign-core' ); ?></p>
	</div>
	<form method="get" class="plaidact-asso-filters">
		<div class="plaidact-asso-filter-grid">
			<label>
				<span><?php esc_html_e( 'Recherche', 'plaidact-campaign-core' ); ?></span>
				<input type="search" name="asso_s" value="<?php echo esc_attr( (string) $filters['s'] ); ?>" placeholder="<?php esc_attr_e( 'Nom, mot-clé…', 'plaidact-campaign-core' ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'Cause', 'plaidact-campaign-core' ); ?></span>
				<select name="asso_cause" <?php disabled( '' !== $fixed_cause ); ?>>
					<option value=""><?php esc_html_e( 'Toutes les causes', 'plaidact-campaign-core' ); ?></option>
					<?php foreach ( $causes as $cause ) : ?>
						<option value="<?php echo esc_attr( $cause->slug ); ?>" <?php selected( $filters['cause'], $cause->slug ); ?>><?php echo esc_html( $cause->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php if ( '' !== $fixed_cause ) : ?>
				<input type="hidden" name="asso_cause" value="<?php echo esc_attr( $fixed_cause ); ?>" />
			<?php endif; ?>
		</div>
		<div class="plaidact-asso-alpha" aria-label="<?php esc_attr_e( 'Filtrer par lettre', 'plaidact-campaign-core' ); ?>">
			<?php
			$active_cause = '' !== $fixed_cause ? $fixed_cause : (string) $filters['cause'];
			$available_letters = Plugin::get_available_asso_letters( $active_cause );
			foreach ( range( 'A', 'Z' ) as $letter ) :
				$letter_url = add_query_arg( [
					'asso_letter' => $letter,
					'asso_s'      => (string) $filters['s'],
					'asso_cause'  => $active_cause,
				] );
				?>
				<?php if ( in_array( $letter, $available_letters, true ) ) : ?>
					<a class="<?php echo ( (string) $filters['letter'] === $letter ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( $letter_url ); ?>"><?php echo esc_html( $letter ); ?></a>
				<?php else : ?>
					<span class="is-disabled"><?php echo esc_html( $letter ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php remove_filter( 'posts_where', [ Plugin::class, 'filter_asso_title_like' ], 10 ); ?>

		<div class="plaidact-asso-filter-actions">
			<button type="submit"><?php esc_html_e( 'Filtrer', 'plaidact-campaign-core' ); ?></button>
			<a href="<?php echo esc_url( remove_query_arg( [ 'asso_s', 'asso_cause', 'asso_letter', $pagination_key, 'paged' ] ) ); ?>"><?php esc_html_e( 'Réinitialiser', 'plaidact-campaign-core' ); ?></a>
		</div>
	</form>

	<?php if ( $query->have_posts() ) : ?>
		<div class="plaidact-asso-grid">
			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<?php Plugin::render_template( 'parts/asso-card.php', [ 'card' => Plugin::get_asso_card_data( get_the_ID() ) ] ); ?>
			<?php endwhile; ?>
		</div>
		<div class="plaidact-asso-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					[
						'base'      => esc_url( add_query_arg( $pagination_key, '%#%' ) ),
						'format'    => '',
						'current'   => max( 1, (int) $filters['paged'] ),
						'total'     => $query->max_num_pages,
						'add_args'  => [
							'asso_s' => (string) $filters['s'],
							'asso_cause' => '' !== $fixed_cause ? $fixed_cause : (string) $filters['cause'],
							'asso_letter' => (string) ( $filters['letter'] ?? '' ),
						],
					]
				)
			);
			?>
		</div>
	<?php else : ?>
		<p class="plaidact-asso-empty"><?php esc_html_e( 'Aucune association ne correspond à votre recherche.', 'plaidact-campaign-core' ); ?></p>
	<?php endif; ?>
	<?php wp_reset_postdata(); ?>
</section>
