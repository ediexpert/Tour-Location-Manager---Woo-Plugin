<?php
/**
 * Template for displaying "location" taxonomy archives.
 *
 * Behavior depends on the term's position in the hierarchy:
 * - Country / State (has child locations): shows the sub-location tree
 *   for that term, plus any products assigned directly to it.
 * - City (no children): shows only the products assigned to that city.
 *
 * Theme developers can override this entirely by adding a
 * taxonomy-intstlm_location.php file to their theme.
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$intstlm_queried_term = get_queried_object();
?>

<div id="primary" class="content-area intstlm-archive-content">
	<main id="main" class="site-main">

		<?php if ( $intstlm_queried_term instanceof WP_Term ) : ?>

			<header class="intstlm-archive-header page-header">

				<?php
				$intstlm_breadcrumb = intstlm_get_location_breadcrumb( $intstlm_queried_term->term_id );
				if ( $intstlm_breadcrumb ) {
					echo '<nav class="intstlm-breadcrumb" aria-label="' . esc_attr__( 'Location breadcrumb', 'ints-tour-location-manager' ) . '">';
					echo wp_kses_post( $intstlm_breadcrumb );
					echo '</nav>';
				}
				?>

				<h1 class="page-title">
					<?php
					printf(
						/* translators: 1: level label (Country/State/City), 2: location name */
						esc_html__( '%1$s: %2$s', 'ints-tour-location-manager' ),
						esc_html( intstlm_get_level_label( $intstlm_queried_term->term_id ) ),
						esc_html( $intstlm_queried_term->name )
					);
					?>
				</h1>

				<?php if ( ! empty( $intstlm_queried_term->description ) ) : ?>
					<div class="taxonomy-description">
						<?php echo wp_kses_post( wpautop( $intstlm_queried_term->description ) ); ?>
					</div>
				<?php endif; ?>
			</header>

			<?php
			$intstlm_children = intstlm_get_child_locations( $intstlm_queried_term->term_id, false );
			?>

			<?php if ( ! empty( $intstlm_children ) ) : ?>
				<section class="intstlm-sub-locations">
					<h2>
						<?php
						if ( 0 === intstlm_get_term_depth( $intstlm_queried_term->term_id ) ) {
							esc_html_e( 'States &amp; Provinces', 'ints-tour-location-manager' );
						} else {
							esc_html_e( 'Cities', 'ints-tour-location-manager' );
						}
						?>
					</h2>
					<?php echo do_shortcode( '[intstlm_tour_location_menu parent="' . absint( $intstlm_queried_term->term_id ) . '"]' ); ?>
				</section>
			<?php endif; ?>

			<section class="intstlm-location-products">

				<?php
				// On Country/State pages, optionally include products tagged to
				// child locations as well, so e.g. "Germany" can show Munich tours too.
				$intstlm_include_children = ( ! empty( $intstlm_children ) );

				$intstlm_products_query = intstlm_get_products_for_location( $intstlm_queried_term->term_id, $intstlm_include_children );
				?>

				<h2>
					<?php
					printf(
						/* translators: %s: location name */
						esc_html__( 'Tours &amp; Services in %s', 'ints-tour-location-manager' ),
						esc_html( $intstlm_queried_term->name )
					);
					?>
				</h2>

				<?php if ( $intstlm_products_query->have_posts() ) : ?>

					<ul class="products intstlm-products columns-3">
						<?php
						while ( $intstlm_products_query->have_posts() ) :
							$intstlm_products_query->the_post();
							wc_get_template_part( 'content', 'product' );
						endwhile;
						?>
					</ul>

					<?php
					$intstlm_big = 999999999;
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => str_replace( $intstlm_big, '%#%', esc_url( get_pagenum_link( $intstlm_big ) ) ),
								'format'    => '?paged=%#%',
								'current'   => max( 1, get_query_var( 'paged' ) ),
								'total'     => $intstlm_products_query->max_num_pages,
								'prev_text' => __( '&larr; Previous', 'ints-tour-location-manager' ),
								'next_text' => __( 'Next &rarr;', 'ints-tour-location-manager' ),
							)
						)
					);
					?>

				<?php else : ?>
					<p class="intstlm-no-products">
						<?php esc_html_e( 'No tours or services are currently available for this location.', 'ints-tour-location-manager' ); ?>
					</p>
				<?php endif; ?>

				<?php wp_reset_postdata(); ?>
			</section>

		<?php else : ?>
			<p><?php esc_html_e( 'Location not found.', 'ints-tour-location-manager' ); ?></p>
		<?php endif; ?>

	</main>
</div>

<?php
get_sidebar();
get_footer();
