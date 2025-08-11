<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 * @author     Jules
 */
class WP_Affiliate_Loyalty_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'process_actions' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
    }

    public function add_admin_menu() {
        // ... (menu registration code is unchanged)
        add_menu_page(
            __( 'Affiliate & Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            __( 'Affiliate & Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_dashboard_page' ),
            'dashicons-groups', 58
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            __( 'Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'manage_options',
            $this->plugin_name . '-commissions',
            array( $this, 'display_commissions_page' )
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            __( 'Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'manage_options',
            $this->plugin_name . '-rules',
            array( $this, 'display_rules_page' )
        );
    }

    public function process_actions() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->plugin_name . '-commissions' ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : false;
        if ( 'mark_paid' !== $action ) {
            return;
        }

        if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'aff_loyalty_mark_paid' ) ) {
            wp_die( __( 'Security check failed.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) );
        }

        $commission_id = isset( $_GET['commission_id'] ) ? absint( $_GET['commission_id'] ) : 0;
        if ( ! $commission_id ) {
            return;
        }

        global $wpdb;
        $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
        $commission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$commissions_table} WHERE id = %d", $commission_id ) );

        if ( ! $commission || 'pending' !== $commission->status ) {
            return;
        }

        $wpdb->update(
            $commissions_table,
            array( 'status' => 'paid' ),
            array( 'id' => $commission_id ),
            array( '%s' ), array( '%d' )
        );

        $this->update_wallet_balance( $commission->affiliate_id, $commission->amount );

        $this->log_transaction(
            'commission_payout',
            $commission->id,
            $commission->affiliate_id,
            $commission->amount,
            sprintf( 'Commission for Order #%d paid.', $commission->order_id )
        );

        $redirect_url = add_query_arg( array( 'message' => 'commission-paid' ), remove_query_arg( array( 'action', 'commission_id', '_wpnonce' ) ) );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    private function update_wallet_balance( $user_id, $amount ) {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'aff_loyalty_wallets';
        $wallet = $wpdb->get_row( $wpdb->prepare( "SELECT id, balance FROM {$wallets_table} WHERE user_id = %d", $user_id ) );

        if ( $wallet ) {
            $new_balance = $wallet->balance + $amount;
            $wpdb->update( $wallets_table, array( 'balance' => $new_balance ), array( 'id' => $wallet->id ), array( '%f' ), array( '%d' ) );
        } else {
            $wpdb->insert( $wallets_table, array( 'user_id' => $user_id, 'balance' => $amount, 'currency' => 'IRR' ), array( '%d', '%f', '%s' ) );
        }
    }

    private function log_transaction( $type, $entity_id, $user_id, $amount, $description ) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'aff_loyalty_transaction_logs';
        $wpdb->insert(
            $logs_table,
            array(
                'entity_type'   => $type,
                'entity_id'     => $entity_id,
                'change_amount' => $amount,
                'description'   => $description,
                'meta'          => wp_json_encode(array('user_id' => $user_id)),
                'created_at'    => current_time( 'mysql' ),
            ),
            array( '%s', '%d', '%f', '%s', '%s', '%s' )
        );
    }

    public function display_admin_notices() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $this->plugin_name . '-commissions' ) {
            return;
        }
        if ( isset( $_GET['message'] ) && 'commission-paid' === $_GET['message'] ) {
            ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Commission marked as paid and wallet updated.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p></div>
            <?php
        }
    }

    public function display_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Welcome to the main dashboard. Reports and general settings will be available here in the future.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
        </div>
        <?php
    }

    public function display_commissions_page() {
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-commissions-list-table.php';
        $list_table = new Commissions_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h1>
            <form method="post"><?php $list_table->display(); ?></form>
        </div>
        <?php
    }

    /**
     * Callback for the rules admin page.
     *
     * @since    1.0.0
     */
    public function display_rules_page() {
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-rules-list-table.php';

        $list_table = new Rules_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h1>
            <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add' ), admin_url( 'admin.php?page=' . $_REQUEST['page'] ) ) ); ?>" class="page-title-action">
                <?php esc_html_e( 'Add New', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>
            </a>

            <form method="post">
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }
}
