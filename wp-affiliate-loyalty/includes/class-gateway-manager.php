<?php
/**
 * Gateway Manager
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 * @author     Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Gateway_Manager {

    /**
     * Get all available payout gateways.
     *
     * @return Payout_Gateway[]
     */
    public static function get_payout_gateways() {
        $gateways = array();

        // Include our placeholder gateways
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/gateways/class-zarinpal-gateway.php';

        $gateways['zarinpal'] = new ZarinPal_Gateway();

        // In the future, other gateways could be added via a filter
        return apply_filters( 'wp_affiliate_loyalty_payout_gateways', $gateways );
    }

    /**
     * Get all available SMS gateways.
     *
     * @return SMS_Gateway[]
     */
    public static function get_sms_gateways() {
        $gateways = array();

        // Include our placeholder gateways
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/gateways/class-kavehnegar-gateway.php';

        $gateways['kavehnegar'] = new KavehNegar_Gateway();

        return apply_filters( 'wp_affiliate_loyalty_sms_gateways', $gateways );
    }

    /**
     * Get the currently active payout gateway.
     *
     * @return Payout_Gateway|null
     */
    public static function get_active_payout_gateway() {
        $settings = get_option( 'wp_aff_loyalty_settings', array() );
        $active_gateway_id = $settings['payout']['active_gateway'] ?? null;

        if ( ! $active_gateway_id ) {
            return null;
        }

        $gateways = self::get_payout_gateways();
        return $gateways[ $active_gateway_id ] ?? null;
    }
}
