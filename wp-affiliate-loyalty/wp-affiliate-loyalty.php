<?php
/**
 * Plugin Name:       Affiliate & Loyalty System
 * Plugin URI:        https://example.com/
 * Description:       A comprehensive affiliate and loyalty system for WordPress and WooCommerce, designed for the Iranian market.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-affiliate-loyalty
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to: 8.9
 * Requires PHP:      8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die( 'Hi there! I am just a plugin, not much I can do when called directly.' );
}

/**
 * Define plugin constants.
 */
define( 'WP_AFFILIATE_LOYALTY_VERSION', '1.0.0' );
define( 'WP_AFFILIATE_LOYALTY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_AFFILIATE_LOYALTY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_AFFILIATE_LOYALTY_TEXT_DOMAIN', 'wp-affiliate-loyalty' );


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-installer.php
 */
function activate_wp_affiliate_loyalty() {
	require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-installer.php';
	WP_Affiliate_Loyalty_Installer::install();
}

register_activation_hook( __FILE__, 'activate_wp_affiliate_loyalty' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-main.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wp_affiliate_loyalty() {
    $plugin = new WP_Affiliate_Loyalty_Main();
    $plugin->run();
}
run_wp_affiliate_loyalty();

/**
 * Declare compatibility with High-Performance Order Storage (HPOS).
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
