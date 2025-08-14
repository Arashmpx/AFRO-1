<?php
/**
 * Kavehnegar SMS Gateway
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/gateways
 * @author     Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/gateways/class-sms-gateway.php';

class Kavehnegar_Gateway extends SMS_Gateway {

    private $api_endpoint = 'https://api.kavenegar.com/v1/';

    public function __construct() {
        $this->id   = 'kavehnegar';
        $this->name = __( 'Kavehnegar', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $this->description = __( 'Send SMS notifications using Kavehnegar.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
    }

    public function send_sms( $recipient, $message ) {
        $settings = get_option('wp_aff_loyalty_settings');
        $api_key = $settings['sms']['kavehnegar']['api_key'] ?? '';
        $sender = $settings['sms']['kavehnegar']['sender_number'] ?? '';

        if ( empty( $api_key ) || empty( $sender ) || empty( $recipient ) || empty( $message ) ) {
            return false;
        }

        $url = $this->api_endpoint . $api_key . '/sms/send.json';

        $body = array(
            'receptor' => $recipient,
            'sender'   => $sender,
            'message'  => $message,
        );

        $response = wp_remote_post( $url, array(
            'method'    => 'POST',
            'body'      => $body,
            'timeout'   => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Kavehnegar API Error: ' . $response->get_error_message() );
            return false;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $response_body['return']['status'] ) && $response_body['return']['status'] == 200 ) {
            return true;
        } else {
            $error_message = $response_body['return']['message'] ?? 'Unknown error';
            error_log( 'Kavehnegar SMS Failed: ' . $error_message );
            return false;
        }
    }

    public function get_settings_fields() {
        $fields = parent::get_settings_fields();
        $fields['api_key'] = array(
            'title' => __( 'API Key', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'type'  => 'text',
            'description' => __( 'Enter your Kavehnegar API Key.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
        $fields['sender_number'] = array(
            'title' => __( 'Sender Number', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'type'  => 'text',
            'description' => __( 'Enter your Kavehnegar sender number.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
        return $fields;
    }
}
