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

    public $id;
    public $name;
    public $description;

    public function __construct() {
        // Can be used by child classes.
    }

    abstract public function send_sms( $recipient_phone, $message );

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
