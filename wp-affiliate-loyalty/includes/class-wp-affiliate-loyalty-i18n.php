<?php

/**
 * The file that defines the internationalization class.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 */

/**
 * The internationalization class.
 *
 * This class is responsible for loading the plugin's text domain for translation.
 *
 * @since      1.0.0
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 * @author     Your Name <email@example.com>
 */
class WP_Affiliate_Loyalty_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain(
            'wp-affiliate-loyalty',
            false,
            dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
        );

    }

}
