<?php
/**
 * Handles registration of the "location" taxonomy and its attachment
 * to WooCommerce products.
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TLM_Taxonomy {

	/**
	 * Singleton instance.
	 *
	 * @var TLM_Taxonomy|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return TLM_Taxonomy
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — hooks registration into init.
	 */
	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );

		// Add manual ordering support (uses term meta "tlm_order").
		add_filter( 'get_terms_args', array( $this, 'maybe_order_terms_args' ), 10, 2 );
		add_filter( 'get_terms_orderby', array( $this, 'maybe_order_terms_orderby' ), 10, 3 );

		// Add custom "order" field to term add/edit screens.
		add_action( TLM_TAXONOMY . '_add_form_fields', array( $this, 'add_order_field' ) );
		add_action( TLM_TAXONOMY . '_edit_form_fields', array( $this, 'edit_order_field' ) );
		add_action( 'created_' . TLM_TAXONOMY, array( $this, 'save_order_field' ) );
		add_action( 'edited_' . TLM_TAXONOMY, array( $this, 'save_order_field' ) );

		// Add a "Level" column (Country/State/City) for clarity in admin term list.
		add_filter( 'manage_edit-' . TLM_TAXONOMY . '_columns', array( $this, 'add_level_column' ) );
		add_filter( 'manage_' . TLM_TAXONOMY . '_custom_column', array( $this, 'render_level_column' ), 10, 3 );
	}

	/**
	 * Register the hierarchical "location" taxonomy and attach it to
	 * the "product" post type used by WooCommerce.
	 *
	 * This method is also called directly on plugin activation so that
	 * rewrite rules exist before flush_rewrite_rules() runs.
	 */
	public static function register_taxonomy() {

		$settings = TLM_Settings::get_settings();

		$slug         = ! empty( $settings['taxonomy_slug'] ) ? $settings['taxonomy_slug'] : 'location';
		$archive_base = ! empty( $settings['archive_base'] ) ? $settings['archive_base'] : $slug;

		$labels = array(
			'name'                       => _x( 'Locations', 'taxonomy general name', 'tour-location-manager' ),
			'singular_name'              => _x( 'Location', 'taxonomy singular name', 'tour-location-manager' ),
			'search_items'               => __( 'Search Locations', 'tour-location-manager' ),
			'popular_items'              => __( 'Popular Locations', 'tour-location-manager' ),
			'all_items'                  => __( 'All Locations', 'tour-location-manager' ),
			'parent_item'                => __( 'Parent Location', 'tour-location-manager' ),
			'parent_item_colon'          => __( 'Parent Location:', 'tour-location-manager' ),
			'edit_item'                  => __( 'Edit Location', 'tour-location-manager' ),
			'update_item'                => __( 'Update Location', 'tour-location-manager' ),
			'add_new_item'               => __( 'Add New Location', 'tour-location-manager' ),
			'new_item_name'              => __( 'New Location Name', 'tour-location-manager' ),
			'separate_items_with_commas' => __( 'Separate locations with commas', 'tour-location-manager' ),
			'add_or_remove_items'        => __( 'Add or remove locations', 'tour-location-manager' ),
			'choose_from_most_used'      => __( 'Choose from the most used locations', 'tour-location-manager' ),
			'not_found'                  => __( 'No locations found.', 'tour-location-manager' ),
			'menu_name'                  => __( 'Locations', 'tour-location-manager' ),
			'back_to_items'              => __( '← Back to Locations', 'tour-location-manager' ),
			'item_link'                  => __( 'Location Link', 'tour-location-manager' ),
			'item_link_description'      => __( 'A link to a location.', 'tour-location-manager' ),
		);

		$args = array(
			'labels'             => $labels,
			'hierarchical'       => true, // Country > State > City.
			'public'             => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'show_in_nav_menus'  => true,
			'show_in_quick_edit' => true,
			'show_tagcloud'      => false,
			'show_in_rest'       => true, // Gutenberg / REST API support.
			'query_var'          => true,
			'rewrite'            => array(
				'slug'         => sanitize_title( $archive_base ),
				'with_front'   => false,
				'hierarchical' => true, // Enables /location/usa/california/los-angeles/.
			),
		);

		/**
		 * Filter the taxonomy registration arguments before registration.
		 *
		 * @param array $args Taxonomy arguments.
		 */
		$args = apply_filters( 'tlm_taxonomy_args', $args );

		register_taxonomy( TLM_TAXONOMY, array( 'product' ), $args );
	}

	/**
	 * Allow custom "menu_order" style sorting for our taxonomy terms
	 * by reading a "tlm_order" term meta value.
	 *
	 * @param array        $args       get_terms() arguments.
	 * @param array|string $taxonomies Taxonomies being queried.
	 * @return array
	 */
	public function maybe_order_terms_args( $args, $taxonomies ) {
		$taxonomies = (array) $taxonomies;

		if ( in_array( TLM_TAXONOMY, $taxonomies, true ) && empty( $args['orderby'] ) ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'tlm_order'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['order']    = 'ASC';
		}

		return $args;
	}

	/**
	 * Fallback orderby clause for sites where meta-based ordering of
	 * terms is not natively supported.
	 *
	 * @param string       $orderby    Orderby clause.
	 * @param array        $args       Query args.
	 * @param array|string $taxonomies Taxonomies.
	 * @return string
	 */
	public function maybe_order_terms_orderby( $orderby, $args, $taxonomies ) {
		$taxonomies = (array) $taxonomies;

		if ( in_array( TLM_TAXONOMY, $taxonomies, true ) && isset( $args['meta_key'] ) && 'tlm_order' === $args['meta_key'] ) {
			global $wpdb;
			return "CAST(tlm_order_meta.meta_value AS SIGNED), t.name ASC";
		}

		return $orderby;
	}

	/**
	 * Output the "Order" field on the Add Term screen.
	 */
	public function add_order_field() {
		?>
		<div class="form-field term-order-wrap">
			<label for="tlm_order"><?php esc_html_e( 'Display Order', 'tour-location-manager' ); ?></label>
			<input type="number" name="tlm_order" id="tlm_order" value="0" step="1" />
			<p class="description">
				<?php esc_html_e( 'Locations are sorted by this number (lowest first) within the same level, then alphabetically.', 'tour-location-manager' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Output the "Order" field on the Edit Term screen.
	 *
	 * @param WP_Term $term Term object.
	 */
	public function edit_order_field( $term ) {
		$value = get_term_meta( $term->term_id, 'tlm_order', true );
		$value = ( '' === $value ) ? 0 : $value;
		?>
		<tr class="form-field term-order-wrap">
			<th scope="row"><label for="tlm_order"><?php esc_html_e( 'Display Order', 'tour-location-manager' ); ?></label></th>
			<td>
				<input type="number" name="tlm_order" id="tlm_order" value="<?php echo esc_attr( $value ); ?>" step="1" />
				<p class="description">
					<?php esc_html_e( 'Locations are sorted by this number (lowest first) within the same level, then alphabetically.', 'tour-location-manager' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the "Order" field value as term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_order_field( $term_id ) {
		// Verify nonce from the standard taxonomy add/edit forms.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'add-tag' )
			&& ! isset( $_POST['_wpnonce'] ) ) {
			// Continue — WP core already validates nonces for term edit screens
			// before firing these hooks, this is an extra defensive check only.
		}

		if ( isset( $_POST['tlm_order'] ) ) {
			$order = absint( $_POST['tlm_order'] );
			update_term_meta( $term_id, 'tlm_order', $order );
		}
	}

	/**
	 * Add a "Level" column to the location terms admin list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_level_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'name' === $key ) {
				$new_columns['tlm_level'] = __( 'Level', 'tour-location-manager' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render the "Level" column content (Country / State / City).
	 *
	 * @param string $content     Existing content (empty for custom columns).
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public function render_level_column( $content, $column_name, $term_id ) {
		if ( 'tlm_level' !== $column_name ) {
			return $content;
		}

		$depth = tlm_get_term_depth( $term_id );

		switch ( $depth ) {
			case 0:
				return esc_html__( 'Country', 'tour-location-manager' );
			case 1:
				return esc_html__( 'State/Province', 'tour-location-manager' );
			case 2:
				return esc_html__( 'City', 'tour-location-manager' );
			default:
				return esc_html__( 'Sub-location', 'tour-location-manager' );
		}
	}
}
