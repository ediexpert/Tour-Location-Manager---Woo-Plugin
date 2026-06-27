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

class INTSTLM_Taxonomy {

	/**
	 * Singleton instance.
	 *
	 * @var INTSTLM_Taxonomy|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return INTSTLM_Taxonomy
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

		// Add manual ordering support (uses term meta "intstlm_order").
		add_filter( 'get_terms_args', array( $this, 'maybe_order_terms_args' ), 10, 2 );
		add_filter( 'get_terms_orderby', array( $this, 'maybe_order_terms_orderby' ), 10, 3 );

		// Add custom "order" field to term add/edit screens.
		add_action( INTSTLM_TAXONOMY . '_add_form_fields', array( $this, 'add_order_field' ) );
		add_action( INTSTLM_TAXONOMY . '_edit_form_fields', array( $this, 'edit_order_field' ) );
		add_action( 'created_' . INTSTLM_TAXONOMY, array( $this, 'save_order_field' ) );
		add_action( 'edited_' . INTSTLM_TAXONOMY, array( $this, 'save_order_field' ) );

		// Add a "Level" column (Country/State/City) for clarity in admin term list.
		add_filter( 'manage_edit-' . INTSTLM_TAXONOMY . '_columns', array( $this, 'add_level_column' ) );
		add_filter( 'manage_' . INTSTLM_TAXONOMY . '_custom_column', array( $this, 'render_level_column' ), 10, 3 );

		// Thumbnail field on add/edit term screens.
		add_action( INTSTLM_TAXONOMY . '_add_form_fields', array( $this, 'add_thumbnail_field' ) );
		add_action( INTSTLM_TAXONOMY . '_edit_form_fields', array( $this, 'edit_thumbnail_field' ) );
		add_action( 'created_' . INTSTLM_TAXONOMY, array( $this, 'save_thumbnail_field' ) );
		add_action( 'edited_' . INTSTLM_TAXONOMY, array( $this, 'save_thumbnail_field' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Thumbnail column in admin term list.
		add_filter( 'manage_edit-' . INTSTLM_TAXONOMY . '_columns', array( $this, 'add_thumbnail_column' ) );
		add_filter( 'manage_' . INTSTLM_TAXONOMY . '_custom_column', array( $this, 'render_thumbnail_column' ), 10, 3 );
	}

	/**
	 * Register the hierarchical "location" taxonomy and attach it to
	 * the "product" post type used by WooCommerce.
	 *
	 * This method is also called directly on plugin activation so that
	 * rewrite rules exist before flush_rewrite_rules() runs.
	 */
	public static function register_taxonomy() {

		$settings = INTSTLM_Settings::get_settings();

		$slug         = ! empty( $settings['taxonomy_slug'] ) ? $settings['taxonomy_slug'] : 'location';
		$archive_base = ! empty( $settings['archive_base'] ) ? $settings['archive_base'] : $slug;

		$labels = array(
			'name'                       => _x( 'Locations', 'taxonomy general name', 'ints-tour-location-manager' ),
			'singular_name'              => _x( 'Location', 'taxonomy singular name', 'ints-tour-location-manager' ),
			'search_items'               => __( 'Search Locations', 'ints-tour-location-manager' ),
			'popular_items'              => __( 'Popular Locations', 'ints-tour-location-manager' ),
			'all_items'                  => __( 'All Locations', 'ints-tour-location-manager' ),
			'parent_item'                => __( 'Parent Location', 'ints-tour-location-manager' ),
			'parent_item_colon'          => __( 'Parent Location:', 'ints-tour-location-manager' ),
			'edit_item'                  => __( 'Edit Location', 'ints-tour-location-manager' ),
			'update_item'                => __( 'Update Location', 'ints-tour-location-manager' ),
			'add_new_item'               => __( 'Add New Location', 'ints-tour-location-manager' ),
			'new_item_name'              => __( 'New Location Name', 'ints-tour-location-manager' ),
			'separate_items_with_commas' => __( 'Separate locations with commas', 'ints-tour-location-manager' ),
			'add_or_remove_items'        => __( 'Add or remove locations', 'ints-tour-location-manager' ),
			'choose_from_most_used'      => __( 'Choose from the most used locations', 'ints-tour-location-manager' ),
			'not_found'                  => __( 'No locations found.', 'ints-tour-location-manager' ),
			'menu_name'                  => __( 'Locations', 'ints-tour-location-manager' ),
			'back_to_items'              => __( '← Back to Locations', 'ints-tour-location-manager' ),
			'item_link'                  => __( 'Location Link', 'ints-tour-location-manager' ),
			'item_link_description'      => __( 'A link to a location.', 'ints-tour-location-manager' ),
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
		$args = apply_filters( 'intstlm_taxonomy_args', $args );

		register_taxonomy( INTSTLM_TAXONOMY, array( 'product' ), $args );
	}

	/**
	 * Allow custom "menu_order" style sorting for our taxonomy terms
	 * by reading a "intstlm_order" term meta value.
	 *
	 * @param array        $args       get_terms() arguments.
	 * @param array|string $taxonomies Taxonomies being queried.
	 * @return array
	 */
	public function maybe_order_terms_args( $args, $taxonomies ) {
		$taxonomies = (array) $taxonomies;

		if ( in_array( INTSTLM_TAXONOMY, $taxonomies, true ) && empty( $args['orderby'] ) ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'intstlm_order'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
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

		if ( in_array( INTSTLM_TAXONOMY, $taxonomies, true ) && isset( $args['meta_key'] ) && 'intstlm_order' === $args['meta_key'] ) {
			return 'CAST(mt1.meta_value AS SIGNED), t.name ASC';
		}

		return $orderby;
	}

	/**
	 * Output the "Order" field on the Add Term screen.
	 */
	public function add_order_field() {
		?>
		<div class="form-field term-order-wrap">
			<label for="intstlm_order"><?php esc_html_e( 'Display Order', 'ints-tour-location-manager' ); ?></label>
			<?php wp_nonce_field( 'intstlm_save_term_meta', 'intstlm_term_nonce' ); ?>
			<input type="number" name="intstlm_order" id="intstlm_order" value="0" step="1" />
			<p class="description">
				<?php esc_html_e( 'Locations are sorted by this number (lowest first) within the same level, then alphabetically.', 'ints-tour-location-manager' ); ?>
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
		$value = get_term_meta( $term->term_id, 'intstlm_order', true );
		$value = ( '' === $value ) ? 0 : $value;
		?>
		<tr class="form-field term-order-wrap">
			<th scope="row"><label for="intstlm_order"><?php esc_html_e( 'Display Order', 'ints-tour-location-manager' ); ?></label></th>
			<td>
				<?php wp_nonce_field( 'intstlm_save_term_meta', 'intstlm_term_nonce' ); ?>
				<input type="number" name="intstlm_order" id="intstlm_order" value="<?php echo esc_attr( $value ); ?>" step="1" />
				<p class="description">
					<?php esc_html_e( 'Locations are sorted by this number (lowest first) within the same level, then alphabetically.', 'ints-tour-location-manager' ); ?>
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
		if ( ! isset( $_POST['intstlm_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['intstlm_term_nonce'] ) ), 'intstlm_save_term_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'edit_terms' ) ) {
			return;
		}

		if ( isset( $_POST['intstlm_order'] ) ) {
			$order = absint( $_POST['intstlm_order'] );
			update_term_meta( $term_id, 'intstlm_order', $order );
		}
	}

	/**
	 * Enqueue the WP media uploader on the location taxonomy screens.
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen = get_current_screen();
		// 'edit-location' = term list/add page; 'location' = edit single term page.
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-' . INTSTLM_TAXONOMY, INTSTLM_TAXONOMY ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'intstlm-admin-thumbnail',
			INTSTLM_PLUGIN_URL . 'assets/js/intstlm-admin-thumbnail.js',
			array( 'jquery' ),
			INTSTLM_VERSION,
			true
		);
	}

	/**
	 * Output the thumbnail upload field on the Add Term screen.
	 */
	public function add_thumbnail_field() {
		?>
		<div class="form-field term-thumbnail-wrap">
			<label><?php esc_html_e( 'Thumbnail', 'ints-tour-location-manager' ); ?></label>
			<div class="intstlm-thumbnail-preview" id="intstlm-thumbnail-preview"></div>
			<input type="hidden" name="intstlm_thumbnail_id" id="intstlm_thumbnail_id" value="" />
			<button type="button" class="button intstlm-upload-thumbnail"><?php esc_html_e( 'Upload / Choose Image', 'ints-tour-location-manager' ); ?></button>
			<button type="button" class="button intstlm-remove-thumbnail" style="display:none;"><?php esc_html_e( 'Remove Image', 'ints-tour-location-manager' ); ?></button>
			<p class="description"><?php esc_html_e( 'Thumbnail image shown when displaying locations in a grid.', 'ints-tour-location-manager' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Output the thumbnail upload field on the Edit Term screen.
	 *
	 * @param WP_Term $term Term object.
	 */
	public function edit_thumbnail_field( $term ) {
		$thumbnail_id = (int) get_term_meta( $term->term_id, 'intstlm_thumbnail_id', true );
		$image_src    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
		?>
		<tr class="form-field term-thumbnail-wrap">
			<th scope="row"><label><?php esc_html_e( 'Thumbnail', 'ints-tour-location-manager' ); ?></label></th>
			<td>
				<div class="intstlm-thumbnail-preview" id="intstlm-thumbnail-preview">
					<?php if ( $image_src ) : ?>
						<img src="<?php echo esc_url( $image_src ); ?>" alt="" style="max-width:150px;display:block;margin-bottom:6px;" />
					<?php endif; ?>
				</div>
				<input type="hidden" name="intstlm_thumbnail_id" id="intstlm_thumbnail_id" value="<?php echo esc_attr( $thumbnail_id ?: '' ); ?>" />
				<button type="button" class="button intstlm-upload-thumbnail"><?php esc_html_e( 'Upload / Choose Image', 'ints-tour-location-manager' ); ?></button>
				<button type="button" class="button intstlm-remove-thumbnail"<?php echo $thumbnail_id ? '' : ' style="display:none;"'; ?>><?php esc_html_e( 'Remove Image', 'ints-tour-location-manager' ); ?></button>
				<p class="description"><?php esc_html_e( 'Thumbnail image shown when displaying locations in a grid.', 'ints-tour-location-manager' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the thumbnail attachment ID as term meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public function save_thumbnail_field( $term_id ) {
		if ( ! isset( $_POST['intstlm_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['intstlm_term_nonce'] ) ), 'intstlm_save_term_meta' ) ) {
			return;
		}

		if ( ! isset( $_POST['intstlm_thumbnail_id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'edit_terms' ) ) {
			return;
		}

		$thumbnail_id = absint( $_POST['intstlm_thumbnail_id'] );
		if ( $thumbnail_id ) {
			update_term_meta( $term_id, 'intstlm_thumbnail_id', $thumbnail_id );
		} else {
			delete_term_meta( $term_id, 'intstlm_thumbnail_id' );
		}
	}

	/**
	 * Add a "Thumbnail" column to the admin term list (before the Level column).
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_thumbnail_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new['intstlm_thumbnail'] = __( 'Thumbnail', 'ints-tour-location-manager' );
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	/**
	 * Render the thumbnail column content.
	 *
	 * @param string $content     Existing content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public function render_thumbnail_column( $content, $column_name, $term_id ) {
		if ( 'intstlm_thumbnail' !== $column_name ) {
			return $content;
		}

		$thumbnail_id = (int) get_term_meta( $term_id, 'intstlm_thumbnail_id', true );
		if ( ! $thumbnail_id ) {
			return '<span aria-hidden="true">—</span>';
		}

		$img = wp_get_attachment_image( $thumbnail_id, array( 44, 44 ), false, array( 'style' => 'border-radius:3px;' ) );
		return $img ?: '<span aria-hidden="true">—</span>';
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
				$new_columns['intstlm_level'] = __( 'Level', 'ints-tour-location-manager' );
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
		if ( 'intstlm_level' !== $column_name ) {
			return $content;
		}

		$depth = intstlm_get_term_depth( $term_id );

		switch ( $depth ) {
			case 0:
				return esc_html__( 'Country', 'ints-tour-location-manager' );
			case 1:
				return esc_html__( 'State/Province', 'ints-tour-location-manager' );
			case 2:
				return esc_html__( 'City', 'ints-tour-location-manager' );
			default:
				return esc_html__( 'Sub-location', 'ints-tour-location-manager' );
		}
	}
}
