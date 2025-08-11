<?php
/**
 * The Loyalty Module
 */
class WP_Affiliate_Loyalty_Loyalty_Module {

    protected $module_name;
    const POINT_REDEMPTION_RATE = 10; // 1 point = 10 IRR

    public function __construct() {
        $this->module_name = 'loyalty';
        $this->add_hooks();
    }

    private function add_hooks() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'award_points_for_purchase' ), 20, 1 );
        add_action( 'user_register', array( $this, 'award_points_for_registration' ), 10, 1 );
        add_action( 'woocommerce_before_cart', array( $this, 'display_redeem_points_form' ) );
        add_action( 'wp_loaded', array( $this, 'handle_points_actions' ) );
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_points_discount' ) );
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'save_redeemed_points_to_order' ), 10, 2 );
        add_action( 'woocommerce_order_status_completed', array( $this, 'deduct_redeemed_points' ), 10, 1 );
        add_action( 'wp_affiliate_loyalty_dashboard_sections', array( $this, 'render_loyalty_dashboard_section' ), 20, 1 );
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

    public function display_redeem_points_form() {
        if ( !is_user_logged_in() || !is_cart() ) return;
        if ( WC()->session->get('points_to_redeem') ) return;
        $user_id = get_current_user_id();
        $total_points = $this->get_total_points_balance( $user_id );
        if ( $total_points <= 0 ) return;
        wc_print_notices();
        ?>
        <div class="loyalty-redeem-form coupon">
            <h3><?php esc_html_e( 'Redeem Your Points', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
            <p><?php printf( esc_html__( 'You have %s points available.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), '<strong>' . number_format_i18n( $total_points ) . '</strong>' ); ?></p>
            <form action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post" class="woocommerce-form-coupon"><input type="hidden" name="action" value="apply_points"><?php wp_nonce_field( 'loyalty_redeem_points', 'loyalty_redeem_nonce' ); ?><label for="redeem_points_amount"><?php esc_html_e( 'Points to use:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label><input type="number" name="redeem_points_amount" class="input-text" id="redeem_points_amount" min="1" max="<?php echo esc_attr( $total_points ); ?>"><button type="submit" class="button"><?php esc_html_e( 'Apply Points', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></button></form>
        </div>
        <?php
    }

    public function handle_points_actions() {
        if ( isset( $_GET['remove_points_discount'] ) ) { WC()->session->set( 'points_to_redeem', null ); wc_add_notice( __( 'Points discount removed.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'success' ); wp_safe_redirect( wc_get_cart_url() ); exit; }
        if ( !isset( $_POST['action'] ) || 'apply_points' !== $_POST['action'] || !isset($_POST['loyalty_redeem_nonce']) || !wp_verify_nonce( $_POST['loyalty_redeem_nonce'], 'loyalty_redeem_points' ) || !is_user_logged_in() ) return;
        $user_id = get_current_user_id();
        $points_to_redeem = isset( $_POST['redeem_points_amount'] ) ? absint( $_POST['redeem_points_amount'] ) : 0;
        $user_balance = $this->get_total_points_balance( $user_id );
        if ( $points_to_redeem <= 0 ) { wc_add_notice( __( 'Please enter a valid number of points.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'error' ); return; }
        if ( $points_to_redeem > $user_balance ) { wc_add_notice( __( 'You do not have enough points to redeem.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'error' ); return; }
        WC()->session->set( 'points_to_redeem', $points_to_redeem );
        wc_add_notice( __( 'Points applied successfully.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'success' );
    }

    public function apply_points_discount( $cart ) {
        if ( is_admin() && !defined('DOING_AJAX') ) return;
        $points_to_redeem = WC()->session->get( 'points_to_redeem' );
        if ( empty( $points_to_redeem ) ) return;
        $discount_amount = $points_to_redeem * self::POINT_REDEMPTION_RATE;
        if ( $discount_amount > $cart->get_subtotal() ) $discount_amount = $cart->get_subtotal();
        $remove_url = esc_url( add_query_arg( 'remove_points_discount', '1', wc_get_cart_url() ) );
        $cart->add_fee( sprintf( '%s <a href="%s" class="remove-points-discount" title="%s">%s</a>', __('Points Discount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN), $remove_url, __('Remove discount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN), __('(Remove)', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN) ), -$discount_amount );
    }

    public function save_redeemed_points_to_order( $order_id, $posted_data ) {
        $points_to_redeem = WC()->session->get( 'points_to_redeem' );
        if ( !empty( $points_to_redeem ) ) {
            $order = wc_get_order( $order_id );
            $order->update_meta_data( '_redeemed_points', $points_to_redeem );
            $order->save();
            WC()->session->set( 'points_to_redeem', null );
        }
    }

    public function deduct_redeemed_points( $order_id ) {
        $order = wc_get_order( $order_id );
        $points_to_deduct = (int) $order->get_meta( '_redeemed_points' );
        $user_id = $order->get_customer_id();
        if ( $points_to_deduct > 0 && $user_id ) $this->spend_points( $user_id, $points_to_deduct, sprintf( 'Redeemed for order #%d', $order_id ), $order_id );
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
        ?>
        <div class="dashboard-section loyalty-section">
            <h3><?php esc_html_e( 'Loyalty Points', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
            <p><strong><?php esc_html_e( 'Your Current Points Balance:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( number_format_i18n( $total_points ) ); ?></p>
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
