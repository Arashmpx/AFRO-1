<?php
/**
 * ZarinPal Payout Gateway
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/gateways
 * @author     Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/gateways/class-payout-gateway.php';

/**
 * ZarinPal Gateway class.
 */
class ZarinPal_Gateway extends Payout_Gateway {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id   = 'zarinpal';
        $this->name = __( 'ZarinPal', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $this->description = __( 'Process payouts using ZarinPal.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
    }

    /**
     * Process a payout.
     *
     * This is a placeholder implementation.
     *
     * @param float $amount The amount to be paid out.
     * @param array $recipient_data Data about the recipient.
     * @return array An array with 'success' (boolean) and 'message' (string).
     */
    public function process_payout( $amount, $recipient_data ) {
        // In a real scenario, you would make an API call to ZarinPal here.
        // For now, we simulate a successful payout.

        // $api_key = get_option('wp_aff_loyalty_settings')['payout']['zarinpal']['api_key'] ?? '';
        // if (empty($api_key)) {
        //     return array('success' => false, 'message' => __('ZarinPal API key is not set.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN));
        // }

        $log_message = sprintf(
            "Simulated ZarinPal Payout: Amount: %s, Recipient: %s",
            $amount,
            print_r($recipient_data, true)
        );
        error_log($log_message);

        return array(
            'success' => true,
            'message' => __( 'Payout processed successfully (Simulated).', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'transaction_id' => 'simulated_' . uniqid()
        );
    }

    /**
     * Get the settings fields for ZarinPal.
     *
     * @return array
     */
    public function get_settings_fields() {
        $fields = parent::get_settings_fields();
        $fields['api_key'] = array(
            'title' => __( 'API Key', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'type'  => 'text',
            'description' => __( 'Enter your ZarinPal Merchant Code or API Key.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'default' => '',
        );
        return $fields;
    }
}
