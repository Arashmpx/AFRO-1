<?php
/**
 * The Affiliate Module
 */
class WP_Affiliate_Loyalty_Affiliate_Module {

    protected $module_name;
    public $ref_key = 'ref';
    private $cookie_name = 'wp_aff_loyalty_ref_id';

    public function __construct() {
        $this->module_name = 'affiliate';
        $this->add_hooks();
    }

    private function add_hooks() {
        add_action( 'init', array( $this, 'track_visitor' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'register_commission' ), 10, 1 );
        add_action( 'wp_affiliate_loyalty_dashboard_sections', array( $this, 'render_affiliate_dashboard_sections' ), 10, 1 );
    }

    public function register_shortcodes() {
        add_shortcode( 'affiliate_dashboard', array( $this, 'render_dashboard_shortcode' ) );
    }

    public function render_dashboard_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please log in to view your dashboard.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) . '</p>';
        $user_id = get_current_user_id();
        ob_start();
        ?>
        <div class="wp-affiliate-loyalty-dashboard">
            <h2><?php esc_html_e( 'Your Dashboard', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h2>
            <?php do_action( 'wp_affiliate_loyalty_dashboard_sections', $user_id ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_affiliate_dashboard_sections( $user_id ) {
        $affiliate_link = $this->get_or_create_affiliate_link( $user_id );
        $wallet_balance = $this->get_wallet_balance( $user_id );
        $recent_commissions = $this->get_recent_commissions( $user_id );
        ?>
        <div class="dashboard-section affiliate-section">
            <h3><?php esc_html_e( 'Affiliate Stats', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
            <p><strong><?php esc_html_e( 'Your Referral Link:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></strong></p>
            <input type="text" value="<?php echo esc_url( $affiliate_link ); ?>" readonly>
            <p><strong><?php esc_html_e( 'Wallet Balance:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( number_format_i18n( $wallet_balance, 0 ) ); ?> <?php esc_html_e( 'IRR', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
            <h4><?php esc_html_e( 'Recent Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h4>
            <table class="commission-table">
                <thead><tr><th><?php esc_html_e( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th></tr></thead>
                <tbody>
                    <?php if ( ! empty( $recent_commissions ) ) : foreach ( $recent_commissions as $commission ) : ?>
                        <tr>
                            <td data-label="<?php esc_attr_e( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( number_format_i18n( $commission->amount, 0 ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( ucfirst( $commission->status ) ); ?></td>
                            <td data-label="<?php esc_attr_e( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $commission->created_at ) ) ); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="3" style="text-align: center;"><?php esc_html_e( 'You have no commissions yet.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function get_or_create_affiliate_link( $user_id ) {
        global $wpdb; $links_table = $wpdb->prefix . 'aff_loyalty_links';
        $token = $wpdb->get_var( $wpdb->prepare( "SELECT token FROM {$links_table} WHERE affiliate_id = %d AND url = ''", $user_id ) );
        if ( ! $token ) {
            $user = get_userdata( $user_id );
            $token = ! empty( $user->user_nicename ) ? $user->user_nicename : (string) $user_id;
            if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$links_table} WHERE token = %s", $token ) ) ) $token = $token . '-' . $user_id;
            $wpdb->insert( $links_table, array( 'affiliate_id' => $user_id, 'token' => $token, 'url' => '' ), array( '%d', '%s', '%s' ) );
        }
        return add_query_arg( $this->ref_key, $token, home_url( '/' ) );
    }

    private function get_wallet_balance( $user_id ) {
        global $wpdb; $wallets_table = $wpdb->prefix . 'aff_loyalty_wallets';
        $balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$wallets_table} WHERE user_id = %d", $user_id ) );
        return $balance ? (float) $balance : 0;
    }

    private function get_recent_commissions( $user_id, $limit = 10 ) {
        global $wpdb; $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
        return $wpdb->get_results( $wpdb->prepare( "SELECT amount, status, created_at FROM {$commissions_table} WHERE affiliate_id = %d ORDER BY created_at DESC LIMIT %d", $user_id, $limit ) );
    }

    public function track_visitor() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || is_robots() ) return;
        if ( ! isset( $_GET[ $this->ref_key ] ) ) return;
        $token = sanitize_text_field( wp_unslash( $_GET[ $this->ref_key ] ) );
        if ( empty( $token ) ) return;
        global $wpdb; $links_table = $wpdb->prefix . 'aff_loyalty_links';
        $affiliate_id = $wpdb->get_var( $wpdb->prepare( "SELECT affiliate_id FROM {$links_table} WHERE token = %s", $token ) );
        if ( ! $affiliate_id ) return;
        setcookie( $this->cookie_name, absint( $affiliate_id ), time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
        wp_safe_redirect( remove_query_arg( $this->ref_key ) );
        exit;
    }

    public function register_commission( $order_id ) {
        if ( ! $order_id || ! isset( $_COOKIE[ $this->cookie_name ] ) ) return;
        $affiliate_id = absint( $_COOKIE[ $this->cookie_name ] );
        if ( ! $affiliate_id ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order || ( $order->get_customer_id() && $order->get_customer_id() === $affiliate_id ) ) return;
        global $wpdb;
        $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$commissions_table} WHERE order_id = %d", $order_id ) ) ) return;
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/services/class-rule-processor.php';
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rules = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$rules_table} WHERE module = %s AND active = 1 ORDER BY precedence ASC", 'affiliate' ) );
        if ( empty( $rules ) ) return;
        $rule_processor = new Rule_Processor( $order, $rules );
        $commission_amount = $rule_processor->evaluate();
        if ( $commission_amount > 0 ) {
            $wpdb->insert( $commissions_table, array('order_id' => $order_id, 'affiliate_id' => $affiliate_id, 'amount' => $commission_amount, 'status' => 'pending', 'created_at' => current_time('mysql')), array('%d', '%d', '%f', '%s', '%s') );
        }
    }
}
