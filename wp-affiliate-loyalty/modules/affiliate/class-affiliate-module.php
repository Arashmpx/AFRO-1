<?php
/**
 * The Affiliate Module
 */
class WP_Affiliate_Loyalty_Affiliate_Module {

    protected $module_name;
    public $ref_key = 'ref';
    private $cookie_name = 'wp_aff_loyalty_ref_id';
    public $payout_request_cpt = 'aff_payout_request';

    public function __construct() {
        $this->module_name = 'affiliate';
        $this->add_hooks();
    }

    private function add_hooks() {
        add_action( 'init', array( $this, 'track_visitor' ) );
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'init', array( $this, 'register_payout_request_cpt' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'register_commission' ), 10, 1 );
        add_action( 'wp_affiliate_loyalty_dashboard_sections', array( $this, 'render_affiliate_dashboard_sections' ), 10, 1 );
        add_action( 'admin_post_request_payout', array( $this, 'handle_payout_request_submission' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
        add_action( 'wp_ajax_get_affiliate_chart_data', array( $this, 'get_chart_data_ajax_handler' ) );
    }

    public function enqueue_styles_and_scripts() {
        // Only load on pages with our shortcode
        if ( is_singular() && has_shortcode( get_post()->post_content, 'affiliate_dashboard' ) ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
            wp_enqueue_script(
                'wp-affiliate-loyalty-frontend',
                WP_AFFILIATE_LOYALTY_PLUGIN_URL . 'assets/js/frontend.js',
                array( 'jquery', 'chartjs' ),
                WP_AFFILIATE_LOYALTY_VERSION,
                true
            );
            wp_localize_script(
                'wp-affiliate-loyalty-frontend',
                'affiliateDashboard',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'affiliate_dashboard_nonce' ),
                )
            );
        }
    }

    public function get_chart_data_ajax_handler() {
        check_ajax_referer( 'affiliate_dashboard_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Not logged in' );
        }

        global $wpdb;
        $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
        $user_id = get_current_user_id();

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE(created_at) as date, SUM(amount) as total_amount
             FROM {$commissions_table}
             WHERE affiliate_id = %d AND status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at) ASC",
            $user_id
        ) );

        $labels = array();
        $data = array();
        foreach ( $results as $row ) {
            $labels[] = $row->date;
            $data[] = $row->total_amount;
        }

        wp_send_json_success( array( 'labels' => $labels, 'data' => $data ) );
    }

    public function register_payout_request_cpt() {
        // ... (existing code)
    }

    public function handle_payout_request_submission() {
        // ... (existing code)
    }

    public function register_shortcodes() {
        add_shortcode( 'affiliate_dashboard', array( $this, 'render_dashboard_shortcode' ) );
    }

    public function render_dashboard_shortcode( $atts ) {
        // ... (existing code)
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
            <p><strong><?php esc_html_e( 'Wallet Balance:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( wc_price( $wallet_balance ) ); ?></p>

            <h4><?php esc_html_e( 'Earnings Over Last 30 Days', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h4>
            <div class="chart-container" style="position: relative; height:40vh; width:80vw">
                <canvas id="commission-chart"></canvas>
            </div>

            <h4><?php esc_html_e( 'Recent Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h4>
            <table class="commission-table">
                <thead><tr><th><?php esc_html_e( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><th><?php esc_html_e( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th></tr></thead>
                <tbody>
                    <?php if ( ! empty( $recent_commissions ) ) : foreach ( $recent_commissions as $commission ) : ?>
                        <tr>
                            <td data-label="<?php esc_attr_e( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>"><?php echo esc_html( wc_price( $commission->amount ) ); ?></td>
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
        // Add Payout Request Form
        // ... (rest of the function is the same)
    }

    // ... (All other methods remain the same)
}
// NOTE: I am omitting the rest of the file content for brevity, as it is unchanged.
// The full file content will be used in the overwrite call.
