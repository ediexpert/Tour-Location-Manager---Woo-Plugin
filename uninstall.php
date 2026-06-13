<?php
/**
 * Uninstall handler.
 *
 * Fired only when the plugin is deleted via the WordPress admin (not on
 * simple deactivation). Removes plugin options. Location terms and their
 * product assignments are left intact by default to avoid accidental data
 * loss — uncomment the term-deletion block below if you want a full purge.
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'tlm_settings' );

/*
 * Optional: fully remove all "location" terms and their meta.
 * Uncomment to enable a complete data purge on uninstall.
 *
 * $terms = get_terms(
 *     array(
 *         'taxonomy'   => 'location',
 *         'hide_empty' => false,
 *     )
 * );
 *
 * if ( ! is_wp_error( $terms ) ) {
 *     foreach ( $terms as $term ) {
 *         wp_delete_term( $term->term_id, 'location' );
 *     }
 * }
 */
