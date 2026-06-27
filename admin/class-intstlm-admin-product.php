<?php
/**
 * Integrates the "location" taxonomy with the WooCommerce product
 * edit screen, and tweaks the admin product list table.
 *
 * Note: because the taxonomy is registered with 'show_ui' => true and
 * attached to 'product', WordPress already renders the default
 * hierarchical checkbox meta box automatically. This class adds a few
 * refinements: moving it near "Product data", adding it to quick edit,
 * and showing it as a filter dropdown in the product list.
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INTSTLM_Admin_Product {

	/**
	 * Singleton instance.
	 *
	 * @var INTSTLM_Admin_Product|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return INTSTLM_Admin_Product
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Add a "Location" filter dropdown above the products list table.
		add_action( 'restrict_manage_posts', array( $this, 'render_location_filter_dropdown' ) );
		add_filter( 'parse_query', array( $this, 'filter_products_by_location' ) );

		// Show assigned locations as a column in the products list.
		add_filter( 'manage_edit-product_columns', array( $this, 'add_location_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_location_column' ), 10, 2 );

		// Enqueue minimal admin styling for the metabox.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin CSS only on product edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		global $post_type;

		if ( 'product' !== $post_type ) {
			return;
		}

		if ( in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			wp_enqueue_style(
				'intstlm-admin',
				INTSTLM_PLUGIN_URL . 'assets/css/intstlm-admin.css',
				array(),
				INTSTLM_VERSION
			);
		}
	}

	/**
	 * Render a dropdown filter for the "location" taxonomy on the
	 * product list table (Products > All Products).
	 *
	 * @param string $post_type Current post type.
	 */
	public function render_location_filter_dropdown( $post_type ) {
		if ( 'product' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET param; sets dropdown selected state. No data is modified.
		$selected = isset( $_GET[ INTSTLM_TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ INTSTLM_TAXONOMY ] ) ) : '';

		wp_dropdown_categories(
			array(
				'show_option_all' => __( 'All Locations', 'ints-tour-location-manager' ),
				'taxonomy'        => INTSTLM_TAXONOMY,
				'name'            => INTSTLM_TAXONOMY,
				'orderby'         => 'name',
				'selected'        => $selected,
				'hierarchical'    => true,
				'depth'           => 3,
				'show_count'      => true,
				'hide_empty'      => false,
				'value_field'     => 'slug',
			)
		);
	}

	/**
	 * Apply the location filter to the main product query when set.
	 *
	 * @param WP_Query $query Current query.
	 */
	public function filter_products_by_location( $query ) {
		global $pagenow;

		if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
			return $query;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET filter on admin list table; same pattern used by WP core and WooCommerce.
		if ( empty( $_GET[ INTSTLM_TAXONOMY ] ) ) {
			return $query;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; see comment above.
		$slug = sanitize_title( wp_unslash( $_GET[ INTSTLM_TAXONOMY ] ) );

		if ( '' === $slug ) {
			return $query;
		}

		$query->query_vars['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => INTSTLM_TAXONOMY,
				'field'    => 'slug',
				'terms'    => $slug,
			),
		);

		return $query;
	}

	/**
	 * Add a "Locations" column to the products list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_location_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Insert after the product "Categories"/"Tags" columns if present, else after "name".
			if ( 'product_tag' === $key || 'name' === $key ) {
				$new_columns['intstlm_location'] = __( 'Locations', 'ints-tour-location-manager' );
			}
		}

		if ( ! isset( $new_columns['intstlm_location'] ) ) {
			$new_columns['intstlm_location'] = __( 'Locations', 'ints-tour-location-manager' );
		}

		return $new_columns;
	}

	/**
	 * Render content for the "Locations" column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product/post ID.
	 */
	public function render_location_column( $column, $post_id ) {
		if ( 'intstlm_location' !== $column ) {
			return;
		}

		$terms = get_the_terms( $post_id, INTSTLM_TAXONOMY );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '&#8212;';
			return;
		}

		$links = array();

		foreach ( $terms as $term ) {
			$links[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url(
					add_query_arg(
						array(
							'post_type' => 'product',
							INTSTLM_TAXONOMY => $term->slug,
						),
						admin_url( 'edit.php' )
					)
				),
				esc_html( $term->name )
			);
		}

		echo wp_kses_post( implode( ', ', $links ) );
	}
}
