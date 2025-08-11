<?php
/**
 * Abstract SMS Gateway Class
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/gateways
 * @author     Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract class for SMS gateways.
 */
abstract class SMS_Gateway {

    /**
     * The unique ID for this gateway.
     *
     * @var string
     */
    public $id;

    /**
     * The name of the gateway to be displayed to the user.
     *
     * @var string
     */
    public $name;

    /**
     * Constructor.
     */
    public function __construct() {
        // Can be used by child classes.
    }

    /**
     * Send an SMS.
     *
     * This method must be implemented by concrete gateway classes.
     *
     * @param string $recipient_phone The phone number of the recipient.
     * @param string $message The message to send.
     * @return bool True on success, false on failure.
     */
    abstract public function send_sms( $recipient_phone, $message );

    /**
     * Get the settings fields for this gateway for the admin settings page.
     *
     * @return array
     */
    public function get_settings_fields() {
        return array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'label'   => sprintf( __( 'Enable %s', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), $this->name ),
                'default' => 'no',
            ),
        );
    }
}
