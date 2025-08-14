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
            wp_send_json_error( array('message' => 'Not logged in') );
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

        $this->spend_points( $user_id, $points_needed, sprintf( 'Redeemed for coupon %s', $coupon_code ), $coupon->get_id() );

        wp_send_json_success( array( 'coupon_code' => $coupon_code, 'message' => 'Your coupon has been generated!' ) );
    }

    public function process_tier_updates() {
        $settings = get_option( 'wp_aff_loyalty_settings' );
        $tiers = isset($settings['loyalty_tiers']) ? (array) $settings['loyalty_tiers'] : array();
        if ( empty( $tiers ) ) return;
        uasort($tiers, function($a, $b) { return (int)($b['points'] ?? 0) <=> (int)($a['points'] ?? 0); });
        $users = get_users();
        foreach ( $users as $user ) {
            $current_points = $this->get_total_points_balance( $user->ID );
            $new_tier_id = 0;
            foreach ( $tiers as $tier_id => $tier_data ) {
                if ( !empty($tier_data['name']) && !empty($tier_data['points']) && $current_points >= (int) $tier_data['points'] ) {
                    $new_tier_id = $tier_id;
                    break;
                }
            }
            update_user_meta( $user->ID, '_loyalty_tier_id', $new_tier_id );
        }
    }

    public function on_comment_status_change( $comment_id, $comment_status ) {
        if ( 'approve' !== $comment_status ) return;
        $comment = get_comment( $comment_id );
        if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) ) return;
        $user_id = (int) $comment->user_id;
        if ( ! $user_id || get_comment_meta( $comment_id, '_points_awarded', true ) ) return;
        $settings = get_option( 'wp_aff_loyalty_settings' );
        $points_for_review = isset( $settings['points_for_review'] ) ? (int) $settings['points_for_review'] : 0;
        if ( $points_for_review > 0 ) {
            $this->add_points( $user_id, $points_for_review, 'review', sprintf( 'Points for reviewing product #%d', $comment->comment_post_ID ), $comment_id );
            update_comment_meta( $comment_id, '_points_awarded', true );
        }
    }

    public function award_points_for_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( !$order || !$order->get_customer_id() ) return;
        $user_id = $order->get_customer_id();
        global $wpdb;
        $logs_table = $wpdb->prefix . 'aff_loyalty_transaction_logs';
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$logs_table} WHERE entity_type = 'points_accrual_purchase' AND entity_id = %d", $order_id ) ) ) return;
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/services/class-rule-processor.php';
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rules = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$rules_table} WHERE module = %s AND active = 1 ORDER BY precedence ASC", 'loyalty' ) );
        if ( empty( $rules ) ) return;
        $rule_processor = new Rule_Processor( $order, $rules );
        $points_to_add = $rule_processor->evaluate();
        if ( $points_to_add > 0 ) $this->add_points( $user_id, $points_to_add, 'purchase', sprintf( 'Points for order #%d', $order_id ), $order_id );
    }

    public function award_points_for_registration( $user_id ) {
        $points_to_add = 100;
        if ( $points_to_add > 0 ) $this->add_points( $user_id, $points_to_add, 'registration', 'Points for signing up' );
    }

    private function add_points( $user_id, $points, $source, $log_description, $entity_id = null ) {
        global $wpdb;
        $points_table = $wpdb->prefix . 'aff_loyalty_points';
        $logs_table   = $wpdb->prefix . 'aff_loyalty_transaction_logs';
        $wpdb->insert( $points_table, array('user_id' => $user_id, 'points' => $points, 'source' => $source, 'status' => 'active'), array('%d', '%d', '%s', '%s') );
        $wpdb->insert( $logs_table, array('entity_type' => 'points_accrual_' . $source, 'entity_id' => $entity_id, 'change_amount' => $points, 'description' => $log_description, 'meta' => wp_json_encode(array('user_id' => $user_id))), array('%s', '%d', '%d', '%s', '%s') );
    }

    private function spend_points( $user_id, $points_to_spend, $log_description, $entity_id = null ) {
        global $wpdb; $points_table = $wpdb->prefix . 'aff_loyalty_points';
        $point_records = $wpdb->get_results( $wpdb->prepare( "SELECT id, points FROM {$points_table} WHERE user_id = %d AND status = 'active' ORDER BY created_at ASC", $user_id ) );
        $points_left_to_spend = $points_to_spend;
        foreach ( $point_records as $record ) {
            if ( $points_left_to_spend <= 0 ) break;
            $points_in_record = (int) $record->points;
            if ( $points_in_record <= $points_left_to_spend ) {
                $wpdb->update( $points_table, array( 'status' => 'redeemed' ), array( 'id' => $record->id ), array('%s'), array('%d') );
                $points_left_to_spend -= $points_in_record;
            } else {
                $remaining_points = $points_in_record - $points_left_to_spend;
                $wpdb->update( $points_table, array( 'points' => $remaining_points ), array( 'id' => $record->id ), array('%d'), array('%d') );
                $original_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$points_table} WHERE id = %d", $record->id ) );
                $wpdb->insert( $points_table, array('user_id'=>$user_id, 'points'=>$points_left_to_spend, 'source'=>$original_record->source, 'status'=>'redeemed', 'expiry_date'=>$original_record->expiry_date, 'created_at'=>$original_record->created_at) );
                $points_left_to_spend = 0;
            }
        }
        $logs_table = $wpdb->prefix . 'aff_loyalty_transaction_logs';
        $wpdb->insert( $logs_table, array('entity_type' => 'points_redemption', 'entity_id' => $entity_id, 'change_amount' => -$points_to_spend, 'description' => $log_description, 'meta' => wp_json_encode(array('user_id' => $user_id))), array('%s', '%d', '%d', '%s', '%s') );
    }

    private function get_total_points_balance( $user_id ) {
        global $wpdb; $points_table = $wpdb->prefix . 'aff_loyalty_points';
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(points) FROM {$points_table} WHERE user_id = %d AND status = 'active'", $user_id ) );
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
                 <thead><tr><th><?php esc_html_e( 'Points', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Source', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th></tr></thead>
                 <tbody>
                    <?php if ( ! empty( $points_history ) ) : foreach ( $points_history as $record ) : ?>
                        <tr>
                            <td data-label="<?php esc_attr_e( 'Points', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( number_format_i18n( $record->points ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Source', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( ucfirst( $record->source ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( ucfirst( $record->status ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $record->created_at ) ) ); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="4" style="text-align: center;"><?php esc_html_e( 'You have no points history yet.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></td></tr>
                    <?php endif; ?>
                 </tbody>
            </table>
        </div>
        <?php
    }

    private function get_points_history( $user_id, $limit = 10 ) {
        global $wpdb;
        $points_table = $wpdb->prefix . 'aff_loyalty_points';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT points, source, status, created_at FROM {$points_table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ) );
    }
}
