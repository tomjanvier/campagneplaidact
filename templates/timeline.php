<?php

use Plaidact\CampaignCore\Association_Directory as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Données produites par Association_Directory::build_timeline_data() : années
// triées contenant des mois triés, chaque mois listant ses événements dans
// l'ordre chronologique. Les mois vides ne sont présents que lorsque
// l'option « fill_empty_months » est activée.
$payload           = isset( $data ) && is_array( $data ) ? $data : [ 'years' => [], 'term' => null ];
$years             = isset( $payload['years'] ) && is_array( $payload['years'] ) ? $payload['years'] : [];
$term              = $payload['term'] ?? null;
$term_name         = $term instanceof WP_Term ? $term->name : '';
$term_slug         = $term instanceof WP_Term ? $term->slug : '';
$title             = isset( $title_override ) && '' !== trim( (string) $title_override ) ? trim( (string) $title_override ) : $term_name;
$show_title        = ! isset( $show_title ) || $show_title;
$show_download     = ! isset( $show_download ) || $show_download;
$layout            = isset( $layout ) && 'horizontal' === $layout ? 'horizontal' : 'vertical';
$columns           = isset( $columns ) ? max( 1, absint( $columns ) ) : 3;
$events_per_column = isset( $events_per_column ) ? absint( $events_per_column ) : 0;
$label             = '' !== $title ? $title : $term_name;
?>
<section class="pa-timeline pa-timeline--<?php echo esc_attr( $layout ); ?>"<?php echo '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : ''; ?>>
	<?php if ( $show_title && '' !== $title ) : ?>
		<h2 class="pa-timeline__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<?php if ( $show_download && '' !== $term_slug ) : ?>
		<p class="pa-timeline__actions">
			<a class="pa-timeline__download" href="<?php echo esc_url( add_query_arg( 'plaidact_timeline_ical', $term_slug ) ); ?>">
				<?php esc_html_e( 'Télécharger au format iCal', 'plaidact-campaign-core' ); ?>
			</a>
		</p>
	<?php endif; ?>
	<?php foreach ( $years as $year_data ) : ?>
		<?php
		$year   = isset( $year_data['year'] ) ? absint( $year_data['year'] ) : 0;
		$months = isset( $year_data['months'] ) && is_array( $year_data['months'] ) ? $year_data['months'] : [];
		if ( 0 === $year || [] === $months ) {
			continue;
		}
		?>
		<h3 class="pa-timeline__year"><?php echo esc_html( (string) $year ); ?></h3>
		<div class="pa-timeline__months" style="--pa-timeline-columns:<?php echo esc_attr( (string) $columns ); ?>">
			<?php foreach ( $months as $month_data ) : ?>
				<?php
				$month      = isset( $month_data['month'] ) ? absint( $month_data['month'] ) : 0;
				$month_name = isset( $month_data['month_name'] ) ? (string) $month_data['month_name'] : Plugin::month_name( $month );
				$events     = isset( $month_data['events'] ) && is_array( $month_data['events'] ) ? array_values( $month_data['events'] ) : [];
				$total      = count( $events );
				// Limite d'affichage par colonne : le compteur restant reste
				// annoncé aux technologies d'assistance.
				$visible = $events_per_column > 0 ? array_slice( $events, 0, $events_per_column ) : $events;
				$hidden  = $total - count( $visible );
				?>
				<section class="pa-timeline__month" aria-label="<?php echo esc_attr( trim( $month_name . ' ' . $year ) ); ?>">
					<h4 class="pa-timeline__month-name"><?php echo esc_html( $month_name ); ?></h4>
					<?php if ( [] === $visible ) : ?>
						<p class="pa-timeline__empty"><?php esc_html_e( 'Aucun événement ce mois-ci.', 'plaidact-campaign-core' ); ?></p>
					<?php else : ?>
						<ul class="pa-timeline__events">
							<?php foreach ( $visible as $event ) : ?>
								<?php
								$event_id    = isset( $event['id'] ) ? absint( $event['id'] ) : 0;
								$event_title = isset( $event['title'] ) ? trim( (string) $event['title'] ) : '';
								$start       = $event['date_debut'] ?? null;
								$end         = $event['date_fin'] ?? null;
								$has_day     = ! isset( $event['has_day'] ) || $event['has_day'];
								$lieu        = isset( $event['lieu'] ) ? trim( (string) $event['lieu'] ) : '';
								$org         = isset( $event['organisation_name'] ) ? trim( (string) $event['organisation_name'] ) : '';
								$logo        = isset( $event['mini_logo'] ) ? trim( (string) $event['mini_logo'] ) : '';
								$url         = isset( $event['url'] ) ? trim( (string) $event['url'] ) : '';
								$is_external = ! empty( $event['is_external'] );
								$is_suite    = ! empty( $event['is_continuation'] );
								if ( '' === $event_title ) {
									$event_title = __( 'Événement', 'plaidact-campaign-core' );
								}
								if ( $start instanceof DateTimeImmutable ) {
									$date_label = $has_day ? Plugin::format_date_short( $start ) : $month_name . ' ' . $start->format( 'Y' );
									$datetime   = $start->format( 'Y-m-d' );
									if ( $end instanceof DateTimeImmutable && $end != $start ) {
										$end_label = $has_day ? Plugin::format_date_short( $end ) : Plugin::month_name( (int) $end->format( 'n' ) ) . ' ' . $end->format( 'Y' );
										// Intervalle lisible sans répéter le contexte du mois.
										$date_label = sprintf(
											/* translators: 1: date de début, 2: date de fin. */
											__( 'Du %1$s au %2$s', 'plaidact-campaign-core' ),
											$date_label,
											$end_label
										);
										$datetime = $start->format( 'Y-m-d' ) . '/' . $end->format( 'Y-m-d' );
									}
								} else {
									$date_label = $month_name . ' ' . $year;
									$datetime   = sprintf( '%04d-%02d', $year, max( 1, $month ) );
								}
								?>
								<li class="pa-timeline__event<?php echo $is_suite ? ' pa-timeline__event--suite' : ''; ?>">
									<?php if ( '' !== $logo ) : ?>
										<img class="pa-timeline__logo" src="<?php echo esc_url( $logo ); ?>" alt="" loading="lazy" decoding="async" />
									<?php endif; ?>
									<p class="pa-timeline__date">
										<time datetime="<?php echo esc_attr( $datetime ); ?>"><?php echo esc_html( $date_label ); ?></time>
										<?php if ( $is_suite ) : ?>
											<span class="pa-timeline__badge"><?php esc_html_e( 'Suite', 'plaidact-campaign-core' ); ?></span>
										<?php endif; ?>
									</p>
									<p class="pa-timeline__event-title">
										<?php if ( '' !== $url ) : ?>
											<a href="<?php echo esc_url( $url ); ?>"<?php echo $is_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
												<?php echo esc_html( $event_title ); ?>
												<?php if ( $is_external ) : ?>
													<span class="screen-reader-text"><?php esc_html_e( '(lien externe)', 'plaidact-campaign-core' ); ?></span>
												<?php endif; ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $event_title ); ?>
										<?php endif; ?>
									</p>
									<?php if ( '' !== $lieu || '' !== $org ) : ?>
										<p class="pa-timeline__meta">
											<?php if ( '' !== $lieu ) : ?>
												<span class="pa-timeline__lieu"><?php echo esc_html( $lieu ); ?></span>
											<?php endif; ?>
											<?php if ( '' !== $lieu && '' !== $org ) : ?>
												<span aria-hidden="true"> — </span>
											<?php endif; ?>
											<?php if ( '' !== $org ) : ?>
												<span class="pa-timeline__org"><?php echo esc_html( $org ); ?></span>
											<?php endif; ?>
										</p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $hidden > 0 ) : ?>
							<p class="pa-timeline__more" role="status">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: nombre d'événements supplémentaires non affichés. */
										_n( '+ %d autre événement', '+ %d autres événements', $hidden, 'plaidact-campaign-core' ),
										$hidden
									)
								);
								?>
							</p>
						<?php endif; ?>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</section>
