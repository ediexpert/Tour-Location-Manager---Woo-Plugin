<?php
/**
 * Plugin Name:       INTS Tour Location Manager
 * Description:        Adds a hierarchical "Location" taxonomy (Country > State/Province > City) for WooCommerce products, with SEO-friendly archives, a navigable location tree shortcode, and admin settings.
 * Version:            1.0.0
 * Author:             Imran Bajwa
 * Author URI:         https://profiles.wordpress.org/imbajwa/
 * Text Domain:        ints-tour-location-manager
 * Domain Path:        /languages
 * Requires at least:  5.8
 * Tested up to:       7.0
 * Requires PHP:       7.4
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 5.0
 * WC tested up to:    9.4
 *
 * @package Tour_Location_Manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'INTSTLM_VERSION', '1.0.0' );
define( 'INTSTLM_PLUGIN_FILE', __FILE__ );
define( 'INTSTLM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INTSTLM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INTSTLM_TAXONOMY', 'intstlm_location' );

/**
 * Composer-free autoloader for our class files.
 *
 * Classes follow the naming convention:
 *   Tour_Location_Manager_XxxYyy  ->  includes/class-intstlm-xxx-yyy.php
 *   INTSTLM_XxxYyy                    ->  includes/class-intstlm-xxx-yyy.php
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'INTSTLM_' ) ) {
			return;
		}

		$file_part = str_replace( 'INTSTLM_', '', $class_name );
		$file_part = strtolower( str_replace( '_', '-', $file_part ) );
		$file_name = 'class-intstlm-' . $file_part . '.php';

		$paths = array(
			INTSTLM_PLUGIN_DIR . 'includes/' . $file_name,
			INTSTLM_PLUGIN_DIR . 'admin/' . $file_name,
			INTSTLM_PLUGIN_DIR . 'public/' . $file_name,
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

/**
 * Declare compatibility with WooCommerce features.
 *
 * High Performance Order Storage (HPOS): this plugin does not interact
 * with orders, so it is fully compatible.
 * Cart & Checkout Blocks: no custom checkout steps, so compatible.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', INTSTLM_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', INTSTLM_PLUGIN_FILE, true );
		}
	}
);

/**
 * Activation hook.
 *
 * Registers the taxonomy (so its rewrite rules exist) and flushes
 * rewrite rules so the SEO-friendly archive permalinks work immediately.
 */
function intstlm_activate_plugin() {
	// Make sure WooCommerce is active; if not, deactivate gracefully.
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( INTSTLM_PLUGIN_FILE ) );
		wp_die(
			esc_html__( 'Tour Location Manager requires WooCommerce to be installed and active.', 'ints-tour-location-manager' ),
			esc_html__( 'Plugin Activation Error', 'ints-tour-location-manager' ),
			array( 'back_link' => true )
		);
	}

	// Register taxonomy & post type connections before flushing.
	INTSTLM_Taxonomy::register_taxonomy();

	// Set default options on first activation only.
	if ( false === get_option( 'intstlm_settings' ) ) {
		add_option( 'intstlm_settings', INTSTLM_Settings::get_default_settings() );
	}

	flush_rewrite_rules();
}
register_activation_hook( INTSTLM_PLUGIN_FILE, 'intstlm_activate_plugin' );

/**
 * Deactivation hook.
 *
 * Flushes rewrite rules to remove our custom permalink structures cleanly.
 * Does NOT delete terms/options — that is left to an optional uninstall.php
 * so users don't lose data on a simple deactivation.
 */
function intstlm_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( INTSTLM_PLUGIN_FILE, 'intstlm_deactivate_plugin' );

/**
 * Initialize the plugin once all plugins are loaded.
 */
function intstlm_run_plugin() {

	// Bail early with an admin notice if WooCommerce is missing.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				if ( current_user_can( 'activate_plugins' ) ) {
					echo '<div class="notice notice-error"><p>' .
						esc_html__( 'Tour Location Manager requires WooCommerce to be installed and active.', 'ints-tour-location-manager' ) .
						'</p></div>';
				}
			}
		);
		return;
	}

	// Core taxonomy registration.
	INTSTLM_Taxonomy::instance();

	// Admin product screen integration.
	INTSTLM_Admin_Product::instance();

	// Settings page.
	INTSTLM_Settings::instance();

	// Frontend shortcodes & archive templates.
	INTSTLM_Frontend::instance();

	// Helper functions are file-based (procedural), loaded directly.
	require_once INTSTLM_PLUGIN_DIR . 'includes/intstlm-helper-functions.php';
}
add_action( 'plugins_loaded', 'intstlm_run_plugin' );
