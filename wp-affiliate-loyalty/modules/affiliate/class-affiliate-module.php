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
    }

    public function register_payout_request_cpt() {
        $labels = array(
            'name'               => _x( 'Payout Requests', 'post type general name', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'singular_name'      => _x( 'Payout Request', 'post type singular name', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'menu_name'          => _x( 'Payout Requests', 'admin menu', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'wp-affiliate-loyalty',
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title' ),
        );
        register_post_type( $this->payout_request_cpt, $args );
    }

    public function handle_payout_request_submission() {
        if ( ! isset( $_POST['request_payout_nonce'] ) || ! wp_verify_nonce( $_POST['request_payout_nonce'], 'request_payout' ) ) {
            wp_die( 'Security check failed.' );
        }
        if ( ! is_user_logged_in() ) {
            wp_die( 'You must be logged in to request a payout.' );
        }

        $user_id = get_current_user_id();
        $balance = $this->get_wallet_balance( $user_id );
        $amount = isset($_POST['payout_amount']) ? (float) $_POST['payout_amount'] : 0;

        if ( $amount <= 0 || $amount > $balance ) {
            wp_die( 'Invalid payout amount.' );
        }

        $post_data = array(
            'post_title'  => sprintf( 'Payout Request by %s for %s', wp_get_current_user()->display_name, wc_price($amount) ),
            'post_status' => 'publish',
            'post_type'   => $this->payout_request_cpt,
            'post_author' => $user_id,
        );
        $post_id = wp_insert_post( $post_data, true );

        if ( !is_wp_error($post_id) ) {
            add_post_meta( $post_id, '_payout_amount', $amount );
            add_post_meta( $post_id, '_payout_status', 'pending' );
            add_post_meta( $post_id, '_affiliate_id', $user_id );

            if (!class_exists('WP_Affiliate_Loyalty_Admin')) {
                require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-admin.php';
            }
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
            <p><strong><?php esc_html_e( 'Wallet Balance:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></strong> <?php echo esc_html( wc_price( $wallet_balance ) ); ?></p>
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
        ?>
        <div class="dashboard-section payout-request-section">
            <h3><?php esc_html_e( 'Request Payout', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
            <?php if (isset($_GET['payout_request']) && $_GET['payout_request'] === 'success') : ?>
                <p style="color: green;"><?php esc_html_e( 'Your payout request has been submitted successfully.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
            <?php endif; ?>

            <?php if ( $wallet_balance > 0 ) : ?>
                <form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="post">
                    <input type="hidden" name="action" value="request_payout">
                    <?php wp_nonce_field( 'request_payout', 'request_payout_nonce' ); ?>
                    <p>
                        <label for="payout_amount"><?php esc_html_e( 'Amount to withdraw:', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                        <input type="number" name="payout_amount" id="payout_amount" value="<?php echo esc_attr($wallet_balance); ?>" max="<?php echo esc_attr($wallet_balance); ?>" step="any" required>
                    </p>
                    <p>
                        <button type="submit" class="button"><?php esc_html_e( 'Submit Request', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></button>
                    </p>
                </form>
            <?php else: ?>
                <p><?php esc_html_e( 'You do not have enough balance to request a payout.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
            <?php endif; ?>
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

        global $wpdb;
        $links_table = $wpdb->prefix . 'aff_loyalty_links';
        $link_data = $wpdb->get_row( $wpdb->prepare( "SELECT id, affiliate_id FROM {$links_table} WHERE token = %s", $token ) );

        if ( ! $link_data ) return;

        $clicks_table = $wpdb->prefix . 'aff_loyalty_clicks';
        $wpdb->insert(
            $clicks_table,
            array(
                'link_id'      => $link_data->id,
                'affiliate_id' => $link_data->affiliate_id,
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ),
            array('%d', '%d', '%s', '%s')
        );

        setcookie( $this->cookie_name, absint( $link_data->affiliate_id ), time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN );
        wp_safe_redirect( remove_query_arg( $this->ref_key ) );
        exit;
    }

    private function get_ancestors( $user_id, $max_levels = 10 ) {
        $ancestors = [];
        $current_user_id = $user_id;

        for ( $i = 0; $i < $max_levels; $i++ ) {
            $parent_id = get_user_meta( $current_user_id, '_aff_loyalty_parent_affiliate_id', true );
            if ( ! $parent_id ) {
                break;
            }
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

        // Check for direct commission
        if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$commissions_table} WHERE order_id = %d AND affiliate_id = %d", $order_id, $direct_affiliate_id ) ) ) return;

        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/services/class-rule-processor.php';
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rules = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$rules_table} WHERE module = %s AND active = 1 ORDER BY precedence ASC", 'affiliate' ) );
        if ( empty( $rules ) ) return;

        $rule_processor = new Rule_Processor( $order, $rules );
        $primary_commission_amount = $rule_processor->evaluate();

        if ( $primary_commission_amount > 0 ) {
            // Insert primary commission (Level 0)
            $wpdb->insert( $commissions_table, array('order_id' => $order_id, 'affiliate_id' => $direct_affiliate_id, 'amount' => $primary_commission_amount, 'status' => 'pending', 'created_at' => current_time('mysql')), array('%d', '%d', '%f', '%s', '%s') );

            // Handle Multi-Level Commissions
            $settings = get_option('wp_aff_loyalty_settings');
            $ancestors = $this->get_ancestors( $direct_affiliate_id, 10 );

            if ( ! empty( $ancestors ) ) {
                foreach ( $ancestors as $level => $ancestor_id ) {
                    $level_number = $level + 1; // Level 1, 2, 3...
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
