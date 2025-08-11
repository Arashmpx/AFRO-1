<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 * @author     Jules <your-name@example.com>
 */
class WP_Affiliate_Loyalty_Main {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WP_Affiliate_Loyalty_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if ( defined( 'WP_AFFILIATE_LOYALTY_VERSION' ) ) {
            $this->version = WP_AFFILIATE_LOYALTY_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wp-affiliate-loyalty';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->initialize_modules();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-admin.php';

        // Other dependencies like module initializers will go here.
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        add_action( 'plugins_loaded', function() {
            load_plugin_textdomain(
                WP_AFFILIATE_LOYALTY_TEXT_DOMAIN,
                false,
                dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
            );
        });
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new WP_Affiliate_Loyalty_Admin( $this->plugin_name, $this->version );
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            WP_AFFILIATE_LOYALTY_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Initialize modules.
     *
     * @since    1.0.0
     * @access   private
     */
    private function initialize_modules() {
        // Load the affiliate module
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'modules/affiliate/class-affiliate-module.php';
        new WP_Affiliate_Loyalty_Affiliate_Module();

        // Load the loyalty module
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'modules/loyalty/class-loyalty-module.php';
        new WP_Affiliate_Loyalty_Loyalty_Module();
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        // If we use a loader class, we would run it here.
        // $this->loader->run();
    }
}
