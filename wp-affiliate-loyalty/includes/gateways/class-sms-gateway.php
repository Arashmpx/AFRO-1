<?php
/**
 * Abstract SMS Gateway Class
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class SMS_Gateway {

    public $id;
    public $name;
    public $description;

    public function __construct() {}

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
