<?php
/**
 * Abstract Payout Gateway Class
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/gateways
 * @author     Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract class for payout gateways.
 */
abstract class Payout_Gateway {

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
     * The description of the gateway.
     *
     * @var string
     */
    public $description;

    /**
     * Constructor.
     */
    public function __construct() {
        // Can be used by child classes.
    }

    /**
     * Process a payout.
     *
     * This method must be implemented by concrete gateway classes.
     *
     * @param float $amount The amount to be paid out.
     * @param array $recipient_data Data about the recipient (e.g., bank info, email).
     * @return array An array with 'success' (boolean) and 'message' (string).
     */
    abstract public function process_payout( $amount, $recipient_data );

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
