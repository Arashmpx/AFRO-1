<?php
/**
 * The Loyalty Module
 */
class WP_Affiliate_Loyalty_Loyalty_Module {

    protected $module_name;

    public function __construct() {
        $this->module_name = 'loyalty';
        $this->add_hooks();
    }

    private function add_hooks() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'award_points_for_purchase' ), 20, 1 );
        add_action( 'user_register', array( $this, 'award_points_for_registration' ), 10, 1 );
        add_action( 'wp_affiliate_loyalty_daily_tier_update', array( $this, 'process_tier_updates' ) );
        add_action( 'wp_set_comment_status', array( $this, 'on_comment_status_change' ), 10, 2 );
        add_action( 'wp_affiliate_loyalty_dashboard_sections', array( $this, 'render_loyalty_dashboard_section' ), 20, 1 );
        add_action( 'wp_ajax_redeem_points_for_coupon', array( $this, 'redeem_points_for_coupon_handler' ) );
    }

    public function redeem_points_for_coupon_handler() {
        check_ajax_referer( 'affiliate_dashboard_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Not logged in' );
        }

        $user_id = get_current_user_id();
        $settings = get_option( 'wp_aff_loyalty_settings' );
        $points_needed = isset( $settings['points_to_coupon_points'] ) ? (int) $settings['points_to_coupon_points'] : 0;
        $coupon_value = isset( $settings['points_to_coupon_value'] ) ? (float) $settings['points_to_coupon_value'] : 0;

        if ( $points_needed <= 0 || $coupon_value <= 0 ) {
            wp_send_json_error( array('message' => 'Coupon redemption is not configured correctly.') );
        }

        $user_balance = $this->get_total_points_balance( $user_id );
        if ( $user_balance < $points_needed ) {
            wp_send_json_error( array('message' => 'You do not have enough points.') );
        }

        // Create Coupon
        $coupon_code = 'POINTS-' . strtoupper( wp_generate_password( 8, false ) );
        $coupon = new WC_Coupon();
        $coupon->set_code( $coupon_code );
        $coupon->set_discount_type( 'fixed_cart' );
        $coupon->set_amount( $coupon_value );
        $coupon->set_individual_use( true );
        $coupon->set_usage_limit( 1 );
        $coupon->set_usage_limit_per_user( 1 );
        $coupon->set_email_restrictions( array( wp_get_current_user()->user_email ) );
        $coupon->save();

        // Deduct points
        $this->spend_points( $user_id, $points_needed, sprintf( 'Redeemed for coupon %s', $coupon_code ), $coupon->get_id() );

        wp_send_json_success( array( 'coupon_code' => $coupon_code, 'message' => 'Your coupon has been generated!' ) );
    }

    public function process_tier_updates() {
        // ... (this method is unchanged)
    }

    public function on_comment_status_change( $comment_id, $comment_status ) {
        // ... (this method is unchanged)
    }

    public function award_points_for_purchase( $order_id ) {
        // ... (this method is unchanged)
    }

    public function award_points_for_registration( $user_id ) {
        // ... (this method is unchanged)
    }

    private function add_points( $user_id, $points, $source, $log_description, $entity_id = null ) {
        // ... (this method is unchanged)
    }

    private function spend_points( $user_id, $points_to_spend, $log_description, $entity_id = null ) {
        // ... (this method is unchanged)
    }

    private function get_total_points_balance( $user_id ) {
        // ... (this method is unchanged)
    }

    public function render_loyalty_dashboard_section( $user_id ) {
        $total_points = $this->get_total_points_balance( $user_id );
        $points_history = $this->get_points_history( $user_id );
        $settings = get_option( 'wp_aff_loyalty_settings' );
        $points_needed = isset( $settings['points_to_coupon_points'] ) ? (int) $settings['points_to_coupon_points'] : 0;
        $coupon_value = isset( $settings['points_to_coupon_value'] ) ? (float) $settings['points_to_coupon_value'] : 0;
        ?>
        <div class="dashboard-section loyalty-section">
            <h3><?php esc_html_e( 'Loyalty Points', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
            <p><strong><?php esc_html_e( 'Your Current Points Balance:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( number_format_i18n( $total_points ) ); ?></p>

            <div class="redeem-for-coupon-section">
                <h4><?php esc_html_e( 'Redeem Points for a Coupon', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h4>
                <?php if ( $points_needed > 0 && $coupon_value > 0 ) : ?>
                    <?php if ( $total_points >= $points_needed ) : ?>
                        <p><?php printf( 'Redeem %s points for a %s coupon!', '<strong>' . number_format_i18n($points_needed) . '</strong>', '<strong>' . wc_price($coupon_value) . '</strong>' ); ?></p>
                        <button id="redeem-for-coupon-btn" class="button"><?php esc_html_e( 'Get Coupon', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></button>
                        <div id="coupon-result" style="margin-top: 10px;"></div>
                    <?php else: ?>
                        <p><?php printf( 'You need %s more points to get a coupon.', '<strong>' . number_format_i18n($points_needed - $total_points) . '</strong>' ); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php esc_html_e( 'Coupon redemption is not currently available.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                <?php endif; ?>
            </div>

            <h4><?php esc_html_e( 'Points History', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h4>
            <table class="points-history-table commission-table">
                 <!-- ... table content ... -->
            </table>
        </div>
        <?php
    }

    private function get_points_history( $user_id, $limit = 10 ) {
        // ... (this method is unchanged)
    }
}
// NOTE: I am omitting the full content of unchanged methods for brevity.
// The full file will be used in the overwrite call.
