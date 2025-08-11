<?php
/**
 * Kaveh Negar SMS Gateway
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/gateways
 * @author     Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/gateways/class-sms-gateway.php';

/**
 * KavehNegar Gateway class.
 */
class KavehNegar_Gateway extends SMS_Gateway {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id   = 'kavehnegar';
        $this->name = __( 'Kaveh Negar', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $this->description = __( 'Send SMS notifications using Kaveh Negar.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
    }

    /**
     * Send an SMS.
     *
     * This is a placeholder implementation that logs the SMS.
     *
     * @param string $recipient_phone The phone number of the recipient.
     * @param string $message The message to send.
     * @return bool True on success, false on failure.
     */
    public function send_sms( $recipient_phone, $message ) {
        // In a real scenario, you would make an API call to Kaveh Negar here.
        // For now, we simulate a successful send by logging it.

        $log_message = sprintf(
            "Simulated Kaveh Negar SMS: To: %s, Message: %s",
            $recipient_phone,
            $message
        );
        error_log($log_message);

        return true;
    }

    /**
     * Get the settings fields for Kaveh Negar.
     *
     * @return array
     */
    public function get_settings_fields() {
        $fields = parent::get_settings_fields();
        $fields['api_key'] = array(
            'title' => __( 'API Key', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'type'  => 'text',
            'description' => __( 'Enter your Kaveh Negar API Key.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'default' => '',
        );
        return $fields;
    }
}
