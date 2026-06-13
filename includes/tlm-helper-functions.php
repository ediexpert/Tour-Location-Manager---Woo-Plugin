<?php
/**
 * Public helper functions for theme/plugin developers.
 *
 * These functions are intentionally procedural (not class methods) so
 * theme developers can call them easily from template files, e.g.:
 *
 *   if ( function_exists( 'tlm_get_location_breadcrumb' ) ) {
 *       echo tlm_get_location_breadcrumb();
 *   }
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the depth (level) of a location term: 0 = Country, 1 = State, 2 = City.
 *
 * @param int $term_id Term ID.
 * @return int Depth, 0-indexed.
 */
function tlm_get_term_depth( $term_id ) {
	$depth   = 0;
	$term_id = (int) $term_id;
	$guard   = 0; // Prevent infinite loops on corrupted data.

	$term = get_term( $term_id, TLM_TAXONOMY );

	while ( $term && ! is_wp_error( $term ) && $term->parent && $guard < 20 ) {
		$depth++;
		$term = get_term( $term->parent, TLM_TAXONOMY );
		$guard++;
	}

	return $depth;
}

/**
 * Get a human readable label for a location's level.
 *
 * @param int $term_id Term ID.
 * @return string
 */
function tlm_get_level_label( $term_id ) {
	switch ( tlm_get_term_depth( $term_id ) ) {
		case 0:
			return __( 'Country', 'tour-location-manager' );
		case 1:
			return __( 'State/Province', 'tour-location-manager' );
		case 2:
			return __( 'City', 'tour-location-manager' );
		default:
			return __( 'Location', 'tour-location-manager' );
	}
}

/**
 * Get direct child location terms for a given parent term ID.
 *
 * @param int  $parent_id    Parent term ID (0 for top-level countries).
 * @param bool $hide_empty   Whether to hide terms with no products.
 * @return WP_Term[]
 */
function tlm_get_child_locations( $parent_id = 0, $hide_empty = false ) {
	$terms = get_terms(
		array(
			'taxonomy'   => TLM_TAXONOMY,
			'parent'     => (int) $parent_id,
			'hide_empty' => (bool) $hide_empty,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	return $terms;
}

/**
 * Get the full ancestor chain (Country > State > City) for a term,
 * ordered from the top-most ancestor down to (but not including) the term itself.
 *
 * @param int $term_id Term ID.
 * @return WP_Term[]
 */
function tlm_get_location_ancestors( $term_id ) {
	$ancestor_ids = get_ancestors( $term_id, TLM_TAXONOMY, 'taxonomy' );
	$ancestor_ids = array_reverse( $ancestor_ids ); // get_ancestors() returns closest-first.

	$terms = array();
	foreach ( $ancestor_ids as $ancestor_id ) {
		$term = get_term( $ancestor_id, TLM_TAXONOMY );
		if ( $term && ! is_wp_error( $term ) ) {
			$terms[] = $term;
		}
	}

	return $terms;
}

/**
 * Render a breadcrumb-style string for a location term, e.g.
 * "USA > California > Los Angeles", with each segment linked.
 *
 * @param int|null $term_id Term ID. Defaults to the current queried term.
 * @param string   $sep     Separator between items.
 * @return string HTML breadcrumb (escaped).
 */
function tlm_get_location_breadcrumb( $term_id = null, $sep = ' &raquo; ' ) {
	if ( null === $term_id ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term && TLM_TAXONOMY === $queried->taxonomy ) {
			$term_id = $queried->term_id;
		}
	}

	if ( empty( $term_id ) ) {
		return '';
	}

	$ancestors = tlm_get_location_ancestors( $term_id );
	$current   = get_term( $term_id, TLM_TAXONOMY );

	if ( ! $current || is_wp_error( $current ) ) {
		return '';
	}

	$links = array();

	foreach ( $ancestors as $ancestor ) {
		$links[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( get_term_link( $ancestor ) ),
			esc_html( $ancestor->name )
		);
	}

	// Current term is plain text (not linked) — standard breadcrumb pattern.
	$links[] = '<span class="tlm-breadcrumb-current">' . esc_html( $current->name ) . '</span>';

	return implode( esc_html( $sep ), $links );
}

/**
 * Get all WooCommerce products assigned to a given location term
 * (and optionally its descendant terms — useful for "Country" archives
 * to show products tagged directly to states/cities below it too).
 *
 * @param int   $term_id          Location term ID.
 * @param bool  $include_children Whether to include products from child locations.
 * @param array $args             Additional WP_Query args to merge/override.
 * @return WP_Query
 */
function tlm_get_products_for_location( $term_id, $include_children = false, $args = array() ) {
	$term_ids = array( (int) $term_id );

	if ( $include_children ) {
		$children = get_term_children( (int) $term_id, TLM_TAXONOMY );
		if ( ! is_wp_error( $children ) ) {
			$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
		}
	}

	$default_args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'paged'          => max( 1, get_query_var( 'paged' ) ),
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => TLM_TAXONOMY,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			),
		),
	);

	$query_args = wp_parse_args( $args, $default_args );

	return new WP_Query( $query_args );
}

/**
 * Get the location terms assigned to a given product.
 *
 * @param int $product_id Product ID.
 * @return WP_Term[]
 */
function tlm_get_product_locations( $product_id ) {
	$terms = get_the_terms( $product_id, TLM_TAXONOMY );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	return $terms;
}

/**
 * Echo the rendered location tree (shortcut wrapper around the shortcode).
 *
 * @param array $atts Shortcode-style attributes (parent, depth, show_counts).
 */
function tlm_the_location_menu( $atts = array() ) {
	echo do_shortcode( '[tour_location_menu' . tlm_build_atts_string( $atts ) . ']' );
}

/**
 * Internal: turn an associative array into a shortcode attribute string.
 *
 * @param array $atts Attributes.
 * @return string
 */
function tlm_build_atts_string( $atts ) {
	$out = '';
	foreach ( $atts as $key => $value ) {
		$out .= sprintf( ' %s="%s"', sanitize_key( $key ), esc_attr( $value ) );
	}
	return $out;
}
