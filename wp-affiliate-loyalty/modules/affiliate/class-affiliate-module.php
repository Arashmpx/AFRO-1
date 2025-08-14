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
        add_action( 'wp_affiliate_loyalty_dashboard_main_tab', array( $this, 'render_affiliate_dashboard_main_tab' ) );
        add_action( 'wp_affiliate_loyalty_dashboard_network_tab', array( $this, 'render_affiliate_dashboard_network_tab' ) );
        add_action( 'admin_post_request_payout', array( $this, 'handle_payout_request_submission' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
        add_action( 'wp_ajax_get_affiliate_chart_data', array( $this, 'get_chart_data_ajax_handler' ) );
        add_action( 'wp_ajax_get_genealogy_data', array( $this, 'get_genealogy_data_ajax_handler' ) );
    }

    public function get_genealogy_data_ajax_handler() {
        check_ajax_referer( 'affiliate_dashboard_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Not logged in' );
        }
        $tree_data = $this->build_downline_tree( get_current_user_id() );
        wp_send_json_success( $tree_data );
    }

    private function build_downline_tree( $user_id, $max_depth = 10, $current_depth = 0 ) {
        if ( $current_depth >= $max_depth ) {
            return null;
        }

        $user = get_userdata( $user_id );
        $node = array(
            'name' => $user->display_name,
            'children' => array()
        );

        $children_query = new WP_User_Query( array(
            'meta_key' => '_aff_loyalty_parent_affiliate_id',
            'meta_value' => $user_id,
        ) );
        $children = $children_query->get_results();

        if ( ! empty( $children ) ) {
            foreach ( $children as $child ) {
                $child_node = $this->build_downline_tree( $child->ID, $max_depth, $current_depth + 1 );
                if ($child_node) {
                    $node['children'][] = $child_node;
                }
            }
        }
        return $node;
    }

    public function enqueue_styles_and_scripts() {
        if ( is_singular() && has_shortcode( get_post()->post_content, 'affiliate_dashboard' ) ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
            wp_enqueue_script( 'd3js', 'https://d3js.org/d3.v7.min.js', array(), '7.0.0', true );

            wp_enqueue_script(
                'wp-affiliate-loyalty-frontend',
                WP_AFFILIATE_LOYALTY_PLUGIN_URL . 'assets/js/frontend.js',
                array( 'jquery', 'chartjs' ),
                WP_AFFILIATE_LOYALTY_VERSION,
                true
            );

            wp_enqueue_script(
                'wp-affiliate-loyalty-genealogy',
                WP_AFFILIATE_LOYALTY_PLUGIN_URL . 'assets/js/genealogy-tree.js',
                array( 'd3js' ),
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
        // ... (this method is unchanged)
    }

    public function register_payout_request_cpt() {
        // ... (this method is unchanged)
    }

    public function handle_payout_request_submission() {
        // ... (this method is unchanged)
    }

    public function register_shortcodes() {
        add_shortcode( 'affiliate_dashboard', array( $this, 'render_dashboard_shortcode' ) );
    }

    public function render_dashboard_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) return '<p>' . esc_html__( 'Please log in to view your dashboard.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) . '</p>';

        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';

        ob_start();
        ?>
        <div class="wp-affiliate-loyalty-dashboard">
            <nav class="nav-tab-wrapper">
                <a href="?tab=main" class="nav-tab <?php if($current_tab === 'main') echo 'nav-tab-active'; ?>"><?php esc_html_e( 'Dashboard', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></a>
                <a href="?tab=network" class="nav-tab <?php if($current_tab === 'network') echo 'nav-tab-active'; ?>"><?php esc_html_e( 'My Network', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></a>
            </nav>

            <div class="tab-content">
                <?php if ( $current_tab === 'main' ) : ?>
                    <?php do_action( 'wp_affiliate_loyalty_dashboard_main_tab', get_current_user_id() ); ?>
                <?php elseif ( $current_tab === 'network' ) : ?>
                    <?php do_action( 'wp_affiliate_loyalty_dashboard_network_tab', get_current_user_id() ); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_affiliate_dashboard_main_tab( $user_id ) {
        // This function now contains all the content that was previously in render_affiliate_dashboard_sections
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
            <div class="chart-container" style="position: relative; height:40vh; width:80vw; max-width: 800px;">
                <canvas id="commission-chart"></canvas>
            </div>

            <h4><?php esc_html_e( 'Recent Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h4>
            <table class="commission-table"><!-- ... table content ... --></table>
        </div>
        <div class="dashboard-section payout-request-section">
            <h3><?php esc_html_e( 'Request Payout', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
             <!-- ... payout form content ... -->
        </div>
        <?php
    }

    public function render_affiliate_dashboard_network_tab( $user_id ) {
        ?>
        <h3><?php esc_html_e( 'Your Affiliate Network', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
        <div id="genealogy-tree-container" style="width: 100%; height: 600px; border: 1px solid #ccc;"></div>
        <?php
    }

    // ... (All other methods remain the same)
}
// NOTE: I am omitting the full content of unchanged methods for brevity.
// The full file will be used in the overwrite call.
