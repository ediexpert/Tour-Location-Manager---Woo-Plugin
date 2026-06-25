<?php
/**
 * Admin settings page for Tour Location Manager.
 *
 * Provides options for the taxonomy slug, archive base, menu title,
 * and other display preferences. Stored as a single array option
 * "tlm_settings".
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TLM_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var TLM_Settings|null
	 */
	private static $instance = null;

	/**
	 * Option name used to store settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'tlm_settings';

	/**
	 * Get singleton instance.
	 *
	 * @return TLM_Settings
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'taxonomy_slug'    => 'location',
			'archive_base'     => 'location',
			'menu_title'       => __( 'Destinations', 'ints-tour-location-manager' ),
			'show_counts'      => 1,
			'expand_all'       => 0,
			'breadcrumb_label' => __( 'Browse by Destination', 'ints-tour-location-manager' ),
		);
	}

	/**
	 * Get current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = self::get_default_settings();
		$saved    = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Register the settings page under WooCommerce/Settings menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Location Manager Settings', 'ints-tour-location-manager' ),
			__( 'Location Settings', 'ints-tour-location-manager' ),
			'manage_woocommerce',
			'tlm-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections and fields using the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'tlm_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'tlm_general_section',
			__( 'General Settings', 'ints-tour-location-manager' ),
			'__return_false',
			'tlm-settings'
		);

		add_settings_field(
			'taxonomy_slug',
			__( 'Taxonomy Slug', 'ints-tour-location-manager' ),
			array( $this, 'field_taxonomy_slug' ),
			'tlm-settings',
			'tlm_general_section'
		);

		add_settings_field(
			'archive_base',
			__( 'Archive URL Base', 'ints-tour-location-manager' ),
			array( $this, 'field_archive_base' ),
			'tlm-settings',
			'tlm_general_section'
		);

		add_settings_field(
			'menu_title',
			__( 'Frontend Menu Title', 'ints-tour-location-manager' ),
			array( $this, 'field_menu_title' ),
			'tlm-settings',
			'tlm_general_section'
		);

		add_settings_field(
			'breadcrumb_label',
			__( 'Tree Heading Label', 'ints-tour-location-manager' ),
			array( $this, 'field_breadcrumb_label' ),
			'tlm-settings',
			'tlm_general_section'
		);

		add_settings_field(
			'show_counts',
			__( 'Show Product Counts', 'ints-tour-location-manager' ),
			array( $this, 'field_show_counts' ),
			'tlm-settings',
			'tlm_general_section'
		);

		add_settings_field(
			'expand_all',
			__( 'Expand Tree by Default', 'ints-tour-location-manager' ),
			array( $this, 'field_expand_all' ),
			'tlm-settings',
			'tlm_general_section'
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = self::get_settings();

		if ( isset( $input['taxonomy_slug'] ) ) {
			$slug = sanitize_title( $input['taxonomy_slug'] );
			$output['taxonomy_slug'] = $slug ? $slug : 'location';
		}

		if ( isset( $input['archive_base'] ) ) {
			$base = sanitize_title( $input['archive_base'] );
			$output['archive_base'] = $base ? $base : 'location';
		}

		if ( isset( $input['menu_title'] ) ) {
			$output['menu_title'] = sanitize_text_field( $input['menu_title'] );
		}

		if ( isset( $input['breadcrumb_label'] ) ) {
			$output['breadcrumb_label'] = sanitize_text_field( $input['breadcrumb_label'] );
		}

		$output['show_counts'] = isset( $input['show_counts'] ) ? 1 : 0;
		$output['expand_all']  = isset( $input['expand_all'] ) ? 1 : 0;

		// Defer rewrite rule flush to shutdown so it only runs once per request,
		// after all settings are committed — avoids flushing on every sanitize call.
		add_action( 'shutdown', 'flush_rewrite_rules' );

		return $output;
	}

	/**
	 * Render: taxonomy slug field.
	 */
	public function field_taxonomy_slug() {
		$settings = self::get_settings();
		printf(
			'<input type="text" name="%1$s[taxonomy_slug]" value="%2$s" class="regular-text" />
			<p class="description">%3$s</p>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['taxonomy_slug'] ),
			esc_html__( 'Internal taxonomy slug (advanced — changing this requires re-saving permalinks).', 'ints-tour-location-manager' )
		);
	}

	/**
	 * Render: archive base field.
	 */
	public function field_archive_base() {
		$settings = self::get_settings();
		printf(
			'<code>%1$s/</code> <input type="text" name="%2$s[archive_base]" value="%3$s" class="regular-text" />
			<p class="description">%4$s</p>',
			esc_html( home_url( '' ) ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['archive_base'] ),
			esc_html__( 'Example: example.com/location/usa/california/los-angeles/', 'ints-tour-location-manager' )
		);
	}

	/**
	 * Render: menu title field.
	 */
	public function field_menu_title() {
		$settings = self::get_settings();
		printf(
			'<input type="text" name="%1$s[menu_title]" value="%2$s" class="regular-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['menu_title'] )
		);
	}

	/**
	 * Render: breadcrumb / tree heading label field.
	 */
	public function field_breadcrumb_label() {
		$settings = self::get_settings();
		printf(
			'<input type="text" name="%1$s[breadcrumb_label]" value="%2$s" class="regular-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['breadcrumb_label'] )
		);
	}

	/**
	 * Render: show counts checkbox.
	 */
	public function field_show_counts() {
		$settings = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%1$s[show_counts]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( 1, $settings['show_counts'], false ),
			esc_html__( 'Display the number of tours/services next to each location.', 'ints-tour-location-manager' )
		);
	}

	/**
	 * Render: expand all checkbox.
	 */
	public function field_expand_all() {
		$settings = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%1$s[expand_all]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( 1, $settings['expand_all'], false ),
			esc_html__( 'Render the full tree expanded (otherwise it expands via JavaScript on click).', 'ints-tour-location-manager' )
		);
	}

	/**
	 * Render the settings page wrapper.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Location Manager Settings', 'ints-tour-location-manager' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'tlm_settings_group' );
				do_settings_sections( 'tlm-settings' );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Shortcode Usage', 'ints-tour-location-manager' ); ?></h2>

			<h3><?php esc_html_e( 'Location Tree Menu', 'ints-tour-location-manager' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: shortcode tag */
					esc_html__( 'Use %s anywhere (pages, posts, widgets) to display the full collapsible location tree.', 'ints-tour-location-manager' ),
					'<code>[tour_location_menu]</code>'
				);
				?>
			</p>
			<ul>
				<li><code>parent="0"</code> — <?php esc_html_e( 'Start the tree from a specific term ID (0 = top level / countries).', 'ints-tour-location-manager' ); ?></li>
				<li><code>depth="3"</code> — <?php esc_html_e( 'Maximum levels to render (1-3).', 'ints-tour-location-manager' ); ?></li>
				<li><code>show_counts="yes"</code> — <?php esc_html_e( 'Override the global "show counts" setting.', 'ints-tour-location-manager' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Location Grid (like WooCommerce Categories)', 'ints-tour-location-manager' ); ?></h3>
			<p>
				<?php
				printf(
					/* translators: %s: shortcode tag */
					esc_html__( 'Use %s to display locations as a thumbnail grid — identical markup to WooCommerce\'s [product_categories] shortcode.', 'ints-tour-location-manager' ),
					'<code>[tour_locations]</code>'
				);
				?>
			</p>
			<ul>
				<li><code>ids="1,2,3"</code> — <?php esc_html_e( 'Comma-separated term IDs to display.', 'ints-tour-location-manager' ); ?></li>
				<li><code>orderby="name"</code> — <?php esc_html_e( 'Order by: name, count, slug, id, include.', 'ints-tour-location-manager' ); ?></li>
				<li><code>order="ASC"</code> — <?php esc_html_e( 'ASC or DESC.', 'ints-tour-location-manager' ); ?></li>
				<li><code>columns="3"</code> — <?php esc_html_e( 'Number of columns (1–6).', 'ints-tour-location-manager' ); ?></li>
				<li><code>hide_empty="1"</code> — <?php esc_html_e( 'Hide locations with no products.', 'ints-tour-location-manager' ); ?></li>
				<li><code>parent="0"</code> — <?php esc_html_e( 'Show only direct children of this term ID.', 'ints-tour-location-manager' ); ?></li>
				<li><code>show_counts="yes"</code> — <?php esc_html_e( 'Show product count badge.', 'ints-tour-location-manager' ); ?></li>
			</ul>
			<p><em><?php esc_html_e( 'Example:', 'ints-tour-location-manager' ); ?></em> <code>[tour_locations ids="5,6,7,8" orderby="include" columns="4" show_counts="yes"]</code></p>
		</div>
		<?php
	}
}
