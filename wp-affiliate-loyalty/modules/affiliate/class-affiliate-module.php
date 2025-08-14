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

    public function enqueue_styles_and_scripts() {
        if ( is_singular() && has_shortcode( get_post()->post_content, 'affiliate_dashboard' ) ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
            wp_enqueue_script( 'd3js', 'https://d3js.org/d3.v7.min.js', array(), '7.0.0', true );
            wp_enqueue_script('wp-affiliate-loyalty-frontend', WP_AFFILIATE_LOYALTY_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery', 'chartjs' ), WP_AFFILIATE_LOYALTY_VERSION, true);
            wp_enqueue_script('wp-affiliate-loyalty-genealogy', WP_AFFILIATE_LOYALTY_PLUGIN_URL . 'assets/js/genealogy-tree.js', array( 'd3js' ), WP_AFFILIATE_LOYALTY_VERSION, true);
            wp_localize_script('wp-affiliate-loyalty-frontend', 'affiliateDashboard', array('ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'affiliate_dashboard_nonce' )));
        }
    }

    public function get_chart_data_ajax_handler() {
        check_ajax_referer( 'affiliate_dashboard_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );
        global $wpdb;
        $results = $wpdb->get_results( $wpdb->prepare("SELECT DATE(created_at) as date, SUM(amount) as total_amount FROM {$wpdb->prefix}aff_loyalty_commissions WHERE affiliate_id = %d AND status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC", get_current_user_id()));
        $labels = array_column($results, 'date');
        $data = array_column($results, 'total_amount');
        wp_send_json_success( array( 'labels' => $labels, 'data' => $data ) );
    }

    public function get_genealogy_data_ajax_handler() {
        check_ajax_referer( 'affiliate_dashboard_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in' );
        $tree_data = $this->build_downline_tree( get_current_user_id() );
        wp_send_json_success( $tree_data );
    }

    private function build_downline_tree( $user_id, $max_depth = 5, $current_depth = 0 ) {
        if ( $current_depth >= $max_depth ) return null;
        $user = get_userdata( $user_id );
        $node = array('name' => $user->display_name, 'children' => array());
        $children_query = new WP_User_Query( array('meta_key' => '_aff_loyalty_parent_affiliate_id', 'meta_value' => $user_id));
        $children = $children_query->get_results();
        if ( ! empty( $children ) ) {
            foreach ( $children as $child ) {
                $child_node = $this->build_downline_tree( $child->ID, $max_depth, $current_depth + 1 );
                if ($child_node) $node['children'][] = $child_node;
            }
        }
        return $node;
    }

    public function register_payout_request_cpt() {
        register_post_type($this->payout_request_cpt, array('labels' => array('name' => 'Payout Requests'), 'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'supports' => array('title')));
    }

    public function handle_payout_request_submission() {
        if ( ! isset( $_POST['request_payout_nonce'] ) || ! wp_verify_nonce( $_POST['request_payout_nonce'], 'request_payout' ) ) wp_die('Security check failed.');
        if ( ! is_user_logged_in() ) wp_die('You must be logged in.');
        $user_id = get_current_user_id();
        $balance = $this->get_wallet_balance( $user_id );
        $amount = isset($_POST['payout_amount']) ? (float) $_POST['payout_amount'] : 0;
        if ( $amount <= 0 || $amount > $balance ) wp_die('Invalid payout amount.');
        $post_id = wp_insert_post( array('post_title' => sprintf('Payout Request by %s for %s', wp_get_current_user()->display_name, wc_price($amount)), 'post_status' => 'publish', 'post_type' => $this->payout_request_cpt, 'post_author' => $user_id), true );
        if ( !is_wp_error($post_id) ) {
            add_post_meta( $post_id, '_payout_amount', $amount );
            add_post_meta( $post_id, '_payout_status', 'pending' );
            add_post_meta( $post_id, '_affiliate_id', $user_id );
            if (!class_exists('WP_Affiliate_Loyalty_Admin')) require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-admin.php';
            $admin_instance = new WP_Affiliate_Loyalty_Admin('','');
            $admin_instance->update_wallet_balance( $user_id, -$amount );
        }
        wp_redirect( add_query_arg( 'payout_request', 'success', $_POST['_wp_http_referer'] ) );
        exit;
    }

    public function register_shortcodes() {
        add_shortcode( 'affiliate_dashboard', array( $this, 'render_dashboard_shortcode' ) );
    }

    public function render_dashboard_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) return '<p>Please log in</p>';
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';
        ob_start();
        ?>
        <div class="wp-affiliate-loyalty-dashboard">
            <nav class="nav-tab-wrapper"><a href="?tab=main" class="nav-tab <?php if($current_tab === 'main') echo 'nav-tab-active'; ?>">Dashboard</a><a href="?tab=network" class="nav-tab <?php if($current_tab === 'network') echo 'nav-tab-active'; ?>">My Network</a></nav>
            <div class="tab-content" style="padding-top:1rem;">
                <?php if ( $current_tab === 'main' ) do_action( 'wp_affiliate_loyalty_dashboard_main_tab', get_current_user_id() ); ?>
                <?php if ( $current_tab === 'network' ) do_action( 'wp_affiliate_loyalty_dashboard_network_tab', get_current_user_id() ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_affiliate_dashboard_main_tab( $user_id ) {
        $affiliate_link = $this->get_or_create_affiliate_link( $user_id );
        $wallet_balance = $this->get_wallet_balance( $user_id );
        $recent_commissions = $this->get_recent_commissions( $user_id );
        ?>
        <div class="dashboard-section affiliate-section">
            <h3>Affiliate Stats</h3>
            <p><strong>Your Referral Link:</strong> <input type="text" value="<?php echo esc_url( $affiliate_link ); ?>" readonly style="width:100%;"></p>
            <p><strong>Wallet Balance:</strong> <?php echo esc_html( wc_price( $wallet_balance ) ); ?></p>
            <h4>Earnings Over Last 30 Days</h4>
            <div class="chart-container" style="position: relative; height:250px; width:100%;"><canvas id="commission-chart"></canvas></div>
            <h4>Recent Commissions</h4>
            <table>...</table>
        </div>
        <div class="dashboard-section payout-request-section">
            <h3>Request Payout</h3>
            <?php if (isset($_GET['payout_request']) && $_GET['payout_request'] === 'success') echo '<p style="color: green;">Your request was submitted.</p>'; ?>
            <?php if ( $wallet_balance > 0 ) : ?>
                <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
                    <input type="hidden" name="action" value="request_payout">
                    <?php wp_nonce_field( 'request_payout', 'request_payout_nonce' ); ?>
                    <p><label for="payout_amount">Amount to withdraw:</label><input type="number" name="payout_amount" id="payout_amount" value="<?php echo esc_attr($wallet_balance); ?>" max="<?php echo esc_attr($wallet_balance); ?>" step="any" required></p>
                    <p><button type="submit" class="button">Submit Request</button></p>
                </form>
            <?php else: ?>
                <p>You do not have enough balance.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_affiliate_dashboard_network_tab( $user_id ) {
        ?>
        <h3>Your Affiliate Network</h3>
        <div id="genealogy-tree-container" style="width: 100%; height: 600px; border: 1px solid #ccc;"></div>
        <?php
    }

    public function get_or_create_affiliate_link( $user_id ) {
        // ... (code unchanged)
    }
    public function get_wallet_balance( $user_id ) {
        // ... (code unchanged)
    }
    public function get_recent_commissions( $user_id, $limit = 10 ) {
        // ... (code unchanged)
    }

    public function track_visitor() {
        if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || is_robots() || !isset( $_GET[ $this->ref_key ] ) ) return;
        $token = sanitize_text_field( wp_unslash( $_GET[ $this->ref_key ] ) );
        if ( empty( $token ) ) return;
        global $wpdb;
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, affiliate_id FROM {$wpdb->prefix}aff_loyalty_links WHERE token = %s", $token ) );
        if ( ! $link_data ) return;
        $wpdb->insert("{$wpdb->prefix}aff_loyalty_clicks", array('link_id' => $link_data->id, 'affiliate_id' => $link_data->affiliate_id, 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '', 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''), array('%d', '%d', '%s', '%s'));
        setcookie( $this->cookie_name, absint( $link_data->affiliate_id ), time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
        wp_safe_redirect( remove_query_arg( $this->ref_key ) );
        exit;
    }

    private function get_ancestors( $user_id, $max_levels = 10 ) {
        $ancestors = [];
        $current_user_id = $user_id;
        for ( $i = 0; $i < $max_levels; $i++ ) {
            $parent_id = get_user_meta( $current_user_id, '_aff_loyalty_parent_affiliate_id', true );
            if ( ! $parent_id ) break;
            $ancestors[] = absint($parent_id);
            $current_user_id = $parent_id;
        }
        return $ancestors;
    }

    public function register_commission( $order_id ) {
        if ( ! $order_id || ! isset( $_COOKIE[ $this->cookie_name ] ) ) return;
        $direct_affiliate_id = absint( $_COOKIE[ $this->cookie_name ] );
        if ( ! $direct_affiliate_id ) return;
        $order = wc_get_order( $order_id );
        if ( ! $order || ( $order->get_customer_id() && $order->get_customer_id() === $direct_affiliate_id ) ) return;
        global $wpdb;
        $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$commissions_table} WHERE order_id = %d AND affiliate_id = %d", $order_id, $direct_affiliate_id ) ) ) return;
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/services/class-rule-processor.php';
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rules = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$rules_table} WHERE module = %s AND active = 1 ORDER BY precedence ASC", 'affiliate' ) );
        if ( empty( $rules ) ) return;
        $rule_processor = new Rule_Processor( $order, $rules );
        $primary_commission_amount = $rule_processor->evaluate();
        if ( $primary_commission_amount > 0 ) {
            $settings = get_option('wp_aff_loyalty_settings');
            $tier_id = get_user_meta( $direct_affiliate_id, '_loyalty_tier_id', true );
            if ( $tier_id && isset($settings['loyalty_tiers'][$tier_id]['bonus']) ) {
                $bonus_rate = (float) $settings['loyalty_tiers'][$tier_id]['bonus'];
                if ( $bonus_rate > 0 ) {
                    $primary_commission_amount += $primary_commission_amount * ( $bonus_rate / 100 );
                }
            }
            $wpdb->insert( $commissions_table, array('order_id' => $order_id, 'affiliate_id' => $direct_affiliate_id, 'amount' => $primary_commission_amount, 'status' => 'pending', 'created_at' => current_time('mysql')), array('%d', '%d', '%f', '%s', '%s') );
            $ancestors = $this->get_ancestors( $direct_affiliate_id, 10 );
            if ( ! empty( $ancestors ) ) {
                foreach ( $ancestors as $level => $ancestor_id ) {
                    $level_number = $level + 1;
                    $rate = isset($settings['level_' . $level_number . '_commission_rate']) ? (float) $settings['level_' . $level_number . '_commission_rate'] : 0;
                    if ( $rate > 0 ) {
                        $level_commission_amount = $primary_commission_amount * ( $rate / 100 );
                        if ( $level_commission_amount > 0 ) {
                            $wpdb->insert( $commissions_table, array('order_id' => $order_id, 'affiliate_id' => $ancestor_id, 'amount' => $level_commission_amount, 'status' => 'pending', 'created_at' => current_time('mysql')), array('%d', '%d', '%f', '%s', '%s') );
                        }
                    }
                }
            }
        }
    }
}
