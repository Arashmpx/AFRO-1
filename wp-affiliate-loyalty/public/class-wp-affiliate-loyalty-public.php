<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/public
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WP_Affiliate_Loyalty_Public {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_shortcode( 'wal_dashboard', array( $this, 'render_dashboard_shortcode' ) );
        add_action( 'wp_ajax_wal_request_payout', array( $this, 'handle_payout_request' ) );
        add_action( 'wp_ajax_wal_get_genealogy_data', array( $this, 'get_genealogy_data_ajax' ) );
        add_action( 'wp_ajax_wal_get_earnings_chart_data', array( $this, 'get_earnings_chart_data_ajax' ) );
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-affiliate-loyalty-public.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-affiliate-loyalty-public.js', array( 'jquery' ), $this->version, true );

        // Enqueue Chart.js and D3.js only when the dashboard shortcode is present
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wal_dashboard' ) ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.2', true );
            wp_enqueue_script( 'd3-js', 'https://d3js.org/d3.v7.min.js', array(), '7.0.0', true );
        }

        wp_localize_script( $this->plugin_name, 'wal_public_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wal_public_nonce' )
        ) );
    }

    public function render_dashboard_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'You must be logged in to view the affiliate dashboard.', 'wp-affiliate-loyalty' ) . '</p>';
        }

        $user_id = get_current_user_id();
        ob_start();

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';

        ?>
        <div class="wal-dashboard">
            <ul class="wal-dashboard-tabs">
                <li><a href="?tab=dashboard" class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>"><?php _e('Dashboard', 'wp-affiliate-loyalty'); ?></a></li>
                <li><a href="?tab=payouts" class="<?php echo $active_tab === 'payouts' ? 'active' : ''; ?>"><?php _e('Payouts', 'wp-affiliate-loyalty'); ?></a></li>
                <li><a href="?tab=network" class="<?php echo $active_tab === 'network' ? 'active' : ''; ?>"><?php _e('My Network', 'wp-affiliate-loyalty'); ?></a></li>
            </ul>

            <div class="wal-dashboard-content">
                <?php
                switch ( $active_tab ) {
                    case 'payouts':
                        $this->render_payouts_tab($user_id);
                        break;
                    case 'network':
                        $this->render_network_tab($user_id);
                        break;
                    case 'dashboard':
                    default:
                        $this->render_main_dashboard_tab($user_id);
                        break;
                }
                ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_main_dashboard_tab($user_id) {
        $balance = get_user_meta( $user_id, 'wal_wallet_balance', true );
        $referral_link = add_query_arg( 'ref', $user_id, home_url( '/' ) );
        ?>
        <h2><?php _e('Main Dashboard', 'wp-affiliate-loyalty'); ?></h2>

        <div class="wal-stats-overview">
            <div>
                <strong><?php _e('Current Balance:', 'wp-affiliate-loyalty'); ?></strong>
                <span><?php echo wc_price( $balance ? $balance : 0 ); ?></span>
            </div>
            <div>
                <strong><?php _e('Your Referral Link:', 'wp-affiliate-loyalty'); ?></strong>
                <input type="text" value="<?php echo esc_url($referral_link); ?>" readonly onfocus="this.select();" />
            </div>
        </div>

        <h3><?php _e('Earnings Last 30 Days', 'wp-affiliate-loyalty'); ?></h3>
        <div class="wal-chart-container">
            <canvas id="walEarningsChart"></canvas>
        </div>
        <?php
    }

    private function render_payouts_tab($user_id) {
        $balance = get_user_meta( $user_id, 'wal_wallet_balance', true );
        $min_payout = 20; // Example minimum payout
        ?>
        <h2><?php _e('Payouts', 'wp-affiliate-loyalty'); ?></h2>

        <h3><?php _e('Request a Payout', 'wp-affiliate-loyalty'); ?></h3>
        <?php if ($balance >= $min_payout) : ?>
            <form id="wal-request-payout-form">
                <p>
                    <label for="payout_amount"><?php _e('Amount to withdraw:', 'wp-affiliate-loyalty'); ?></label>
                    <input type="number" id="payout_amount" name="payout_amount" min="<?php echo esc_attr($min_payout); ?>" max="<?php echo esc_attr($balance); ?>" step="0.01" required>
                </p>
                <p>
                    <label for="payout_method"><?php _e('Payout Method:', 'wp-affiliate-loyalty'); ?></label>
                    <textarea id="payout_method" name="payout_method" required placeholder="<?php _e('Please enter your bank account details (Sheba number, etc.)', 'wp-affiliate-loyalty'); ?>"></textarea>
                </p>
                <button type="submit"><?php _e('Submit Request', 'wp-affiliate-loyalty'); ?></button>
            </form>
            <div id="wal-payout-message"></div>
        <?php else: ?>
            <p><?php printf(__('You need a minimum balance of %s to request a payout.', 'wp-affiliate-loyalty'), wc_price($min_payout)); ?></p>
        <?php endif; ?>

        <h3><?php _e('Payout History', 'wp-affiliate-loyalty'); ?></h3>
        <?php
        $requests = get_posts(array(
            'post_type' => 'payout_request',
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if ($requests) {
            echo '<ul>';
            foreach ($requests as $request) {
                $status = get_post_meta($request->ID, '_status', true);
                $amount = get_post_meta($request->ID, '_amount', true);
                echo '<li>' . esc_html($request->post_title) . ' - ' . wc_price($amount) . ' (' . ucfirst($status) . ') - ' . get_the_date('', $request->ID) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No payout requests found.', 'wp-affiliate-loyalty') . '</p>';
        }
    }

    private function render_network_tab($user_id) {
        ?>
        <h2><?php _e('My Network', 'wp-affiliate-loyalty'); ?></h2>
        <div id="wal-genealogy-tree"></div>
        <?php
    }

    public function handle_payout_request() {
        check_ajax_referer( 'wal_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Not logged in.' ) );
        }

        $user_id = get_current_user_id();
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $method_details = isset($_POST['method']) ? sanitize_textarea_field($_POST['method']) : '';
        $balance = floatval(get_user_meta($user_id, 'wal_wallet_balance', true));
        $min_payout = 20;

        if ($amount < $min_payout || $amount > $balance) {
            wp_send_json_error( array( 'message' => __('Invalid payout amount.', 'wp-affiliate-loyalty') ) );
        }

        // Deduct from balance immediately
        $new_balance = $balance - $amount;
        update_user_meta($user_id, 'wal_wallet_balance', $new_balance);

        // Create the CPT entry
        $post_id = wp_insert_post(array(
            'post_type' => 'payout_request',
            'post_title' => sprintf('Payout Request for %s - %s', get_userdata($user_id)->display_name, wc_price($amount)),
            'post_content' => 'Payout Method Details: ' . $method_details,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));

        if ($post_id) {
            update_post_meta($post_id, '_amount', $amount);
            update_post_meta($post_id, '_status', 'pending');
            wp_send_json_success( array( 'message' => __('Payout request submitted successfully.', 'wp-affiliate-loyalty') ) );
        } else {
            // Refund if post creation fails
            update_user_meta($user_id, 'wal_wallet_balance', $balance);
            wp_send_json_error( array( 'message' => __('Failed to create payout request.', 'wp-affiliate-loyalty') ) );
        }
    }

    public function get_genealogy_data_ajax() {
        check_ajax_referer('wal_public_nonce', 'nonce');
        $user_id = get_current_user_id();
        $users = get_users(array('fields' => array('ID', 'display_name', 'user_email')));
        $nodes = array();
        $links = array();

        foreach ($users as $user) {
            $nodes[] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            );
            $parent_id = get_user_meta($user->ID, 'wal_affiliate_parent', true);
            if ($parent_id) {
                $links[] = array(
                    'source' => $parent_id,
                    'target' => $user->ID
                );
            }
        }
        wp_send_json_success(array('nodes' => $nodes, 'links' => $links));
    }

    public function get_earnings_chart_data_ajax() {
        check_ajax_referer('wal_public_nonce', 'nonce');
        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . 'wal_commissions';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(commission_date) as date, SUM(amount) as total
            FROM {$table_name}
            WHERE user_id = %d AND commission_date >= %s
            GROUP BY DATE(commission_date)
            ORDER BY date ASC",
            $user_id,
            date('Y-m-d', strtotime('-30 days'))
        ));

        $labels = [];
        $data = [];
        $period = new DatePeriod(
            new DateTime('-30 days'),
            new DateInterval('P1D'),
            new DateTime('+1 day')
        );

        $earnings_by_date = [];
        foreach ($results as $result) {
            $earnings_by_date[$result->date] = $result->total;
        }

        foreach ($period as $value) {
            $date_key = $value->format('Y-m-d');
            $labels[] = $value->format('M d');
            $data[] = isset($earnings_by_date[$date_key]) ? $earnings_by_date[$date_key] : 0;
        }

        wp_send_json_success(array('labels' => $labels, 'data' => $data));
    }
}
