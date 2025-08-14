<?php
/**
 * The admin-specific functionality of the plugin.
 */
class WP_Affiliate_Loyalty_Admin {

    private $plugin_name;
    private $version;
    private $settings_option_name = 'wp_aff_loyalty_settings';

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_init', array( $this, 'process_actions' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        add_action( 'admin_post_save_affiliate_loyalty_rule', array( $this, 'handle_save_rule_form' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Genealogy CPT
        add_action( 'init', array( $this, 'register_genealogy_cpt' ) );
    }

    public function register_genealogy_cpt() {
        register_post_type( 'aff_genealogy_node',
            array(
                'labels' => array('name' => 'Genealogy Nodes'),
                'public' => false,
                'show_ui' => false,
                'supports' => array('title', 'page-attributes'),
            )
        );
    }

    public function add_admin_menu() {
        add_menu_page( 'Affiliate & Loyalty', 'Affiliate & Loyalty', 'manage_options', $this->plugin_name, array( $this, 'display_dashboard_page' ), 'dashicons-groups', 58 );
        add_submenu_page( $this->plugin_name, 'Commissions', 'Commissions', 'manage_options', $this->plugin_name . '-commissions', array( $this, 'display_commissions_page' ) );
        add_submenu_page( $this->plugin_name, 'Rules', 'Rules', 'manage_options', $this->plugin_name . '-rules', array( $this, 'display_rules_page' ) );
        add_submenu_page( $this->plugin_name, 'Reports', 'Reports', 'manage_options', $this->plugin_name . '-reports', array( $this, 'display_reports_page' ) );
        add_submenu_page( $this->plugin_name, 'Payouts', 'Payouts', 'manage_options', $this->plugin_name . '-payouts', array( $this, 'display_payouts_page' ) );
        add_submenu_page( $this->plugin_name, 'Settings', 'Settings', 'manage_options', $this->plugin_name . '-settings', array( $this, 'display_settings_page' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'affiliate-loyalty_page_wp-affiliate-loyalty-reports' !== $hook ) return;
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
    }

    public function register_plugin_settings() {
        register_setting( $this->settings_option_name, $this->settings_option_name );
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-gateway-manager.php';

        add_settings_section( 'payout_gateway_section', 'Payout Gateway Settings', null, $this->plugin_name . '-settings' );
        $payout_gateways = Gateway_Manager::get_payout_gateways();
        add_settings_field( 'active_payout_gateway', 'Active Payout Gateway', array( $this, 'render_gateway_select_field'), $this->plugin_name . '-settings', 'payout_gateway_section', ['gateways' => $payout_gateways, 'type' => 'payout'] );
        foreach ($payout_gateways as $id => $gateway) {
            $section_id = 'payout_gateway_' . $id . '_section';
            add_settings_section( $section_id, $gateway->name . ' Settings', null, $this->plugin_name . '-settings' );
            foreach ($gateway->get_settings_fields() as $field_id => $field) {
                add_settings_field( "payout_{$id}_{$field_id}", $field['title'], array($this, 'render_gateway_field'), $this->plugin_name . '-settings', $section_id, ['gateway_id' => $id, 'field_id' => $field_id, 'field' => $field, 'type' => 'payout'] );
            }
        }
    }

    public function render_gateway_select_field($args) {
        $options = get_option($this->settings_option_name);
        $active_gateway = $options[$args['type']]['active_gateway'] ?? '';
        echo "<select name='{$this->settings_option_name}[{$args['type']}][active_gateway]'>";
        foreach ($args['gateways'] as $id => $gateway) echo "<option value='{$id}' ".selected($active_gateway, $id, false).">{$gateway->name}</option>";
        echo "</select>";
    }

    public function render_gateway_field($args) {
        $options = get_option($this->settings_option_name);
        $value = $options[$args['type']][$args['gateway_id']][$args['field_id']] ?? $args['field']['default'] ?? '';
        echo "<input type='text' name='{$this->settings_option_name}[{$args['type']}][{$args['gateway_id']}][{$args['field_id']}]' value='" . esc_attr($value) . "' class='regular-text' />";
        if (!empty($args['field']['description'])) echo "<p class='description'>{$args['field']['description']}</p>";
    }

    public function display_settings_page() {
        echo '<div class="wrap"><h1>'.get_admin_page_title().'</h1><form action="options.php" method="post">';
        settings_fields( $this->settings_option_name );
        do_settings_sections( $this->plugin_name . '-settings' );
        submit_button();
        echo '</form></div>';
    }

    public function process_actions() {
        // Handle marking single commission as paid
        if ( isset($_GET['page']) && $_GET['page'] === $this->plugin_name . '-commissions' && isset($_GET['action']) && 'mark_paid' === sanitize_key($_GET['action']) ) {
            if ( !wp_verify_nonce( $_GET['_wpnonce'], 'aff_loyalty_mark_paid' ) ) wp_die( 'Security check failed.' );
            $commission_id = isset( $_GET['commission_id'] ) ? absint( $_GET['commission_id'] ) : 0;
            if ( !$commission_id ) return;
            global $wpdb;
            $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
            $commission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$commissions_table} WHERE id = %d", $commission_id ) );
            if ( !$commission || 'pending' !== $commission->status ) return;
            $wpdb->update( $commissions_table, array( 'status' => 'paid' ), array( 'id' => $commission_id ), array( '%s' ), array( '%d' ) );
            $this->update_wallet_balance( $commission->affiliate_id, $commission->amount );
            $this->log_transaction( 'commission_payout', $commission->id, $commission->affiliate_id, $commission->amount, sprintf( 'Commission for Order #%d paid.', $commission->order_id ) );
            wp_safe_redirect( add_query_arg( array( 'message' => 'commission-paid' ), remove_query_arg( array( 'action', 'commission_id', '_wpnonce' ) ) ) );
            exit;
        }

        // Handle bulk payout processing
        if ( isset($_POST['action']) && 'process_payouts' === $_POST['action'] ) {
            $user_ids = isset($_POST['user_ids']) ? array_map('absint', $_POST['user_ids']) : array();
            if (empty($user_ids)) return;

            $gateway = Gateway_Manager::get_active_payout_gateway();
            if (!$gateway) return;

            foreach($user_ids as $user_id) {
                $wallet_balance = $this->get_wallet_balance_for_payout($user_id);
                if ($wallet_balance > 0) {
                    $payout_result = $gateway->process_payout($wallet_balance, ['user_id' => $user_id]);
                    if ($payout_result['success']) {
                        $this->update_wallet_balance($user_id, -$wallet_balance);
                        $this->log_transaction('manual_payout', 0, $user_id, -$wallet_balance, 'Manual payout processed via ' . $gateway->name);
                    }
                }
            }
            wp_safe_redirect( add_query_arg( array( 'page' => $this->plugin_name . '-payouts', 'message' => 'payouts-processed' ) ) );
            exit;
        }
    }

    private function get_wallet_balance_for_payout($user_id) {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'aff_loyalty_wallets';
        $balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$wallets_table} WHERE user_id = %d", $user_id ) );
        return $balance ? (float) $balance : 0;
    }

    public function handle_save_rule_form() {
        if ( !isset($_POST['save_rule_nonce']) || !wp_verify_nonce( $_POST['save_rule_nonce'], 'save_rule_nonce' ) ) wp_die( 'Security check failed.' );
        if ( !current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to save rules.' );
        global $wpdb;
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
        $data = array( 'name' => sanitize_text_field($_POST['name']), 'module' => sanitize_key($_POST['module']), 'conditions_json' => wp_unslash(trim($_POST['conditions_json'])), 'actions_json' => wp_unslash(trim($_POST['actions_json'])), 'precedence' => absint($_POST['precedence']), 'active' => isset($_POST['active']) ? 1 : 0 );
        if ( json_decode($data['conditions_json']) === null || json_decode($data['actions_json']) === null ) wp_die( 'Invalid JSON format.' );
        if ( $rule_id > 0 ) $wpdb->update( $rules_table, $data, array( 'id' => $rule_id ), $this->get_rule_data_formats(), array( '%d' ) );
        else { $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $rules_table, $data, $this->get_rule_data_formats() ); }
        wp_safe_redirect( add_query_arg( array( 'page' => $this->plugin_name . '-rules', 'message' => 'rule-saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function update_wallet_balance( $user_id, $amount ) {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'aff_loyalty_wallets';
        $wallet = $wpdb->get_row( $wpdb->prepare( "SELECT id, balance FROM {$wallets_table} WHERE user_id = %d", $user_id ) );
        if ($wallet) $wpdb->update( $wallets_table, array( 'balance' => $wallet->balance + $amount ), array( 'id' => $wallet->id ), array( '%f' ), array( '%d' ) );
        else $wpdb->insert( $wallets_table, array( 'user_id' => $user_id, 'balance' => $amount, 'currency' => 'IRR' ), array( '%d', '%f', '%s' ) );
    }

    private function log_transaction( $type, $entity_id, $user_id, $amount, $description ) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'aff_loyalty_transaction_logs';
        $wpdb->insert( $logs_table, array('entity_type' => $type, 'entity_id' => $entity_id, 'change_amount' => $amount, 'description' => $description, 'meta' => wp_json_encode(array('user_id' => $user_id)), 'created_at' => current_time('mysql')), array('%s', '%d', '%f', '%s', '%s', '%s') );
    }

    private function get_rule_data_formats() { return array('%s', '%s', '%s', '%s', '%d', '%d'); }

    public function display_admin_notices() {
        $page = isset($_GET['page']) ? $_GET['page'] : ''; $msg = isset($_GET['message']) ? $_GET['message'] : '';
        if ($page === $this->plugin_name . '-commissions' && $msg === 'commission-paid') echo '<div class="notice notice-success is-dismissible"><p>'.__('Commission marked as paid and wallet updated.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</p></div>';
        if ($page === $this->plugin_name . '-rules' && $msg === 'rule-saved') echo '<div class="notice notice-success is-dismissible"><p>'.__('Rule saved successfully.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</p></div>';
        if ($page === $this->plugin_name . '-payouts' && $msg === 'payouts-processed') echo '<div class="notice notice-success is-dismissible"><p>'.__('Selected payouts processed.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</p></div>';
    }

    public function display_dashboard_page() { echo '<div class="wrap"><h1>'.get_admin_page_title().'</h1><p>Welcome to the main dashboard.</p></div>'; }

    public function display_commissions_page() {
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-commissions-list-table.php';
        $lt = new Commissions_List_Table(); $lt->prepare_items();
        echo '<div class="wrap"><h1 class="wp-heading-inline">'.__('Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</h1><form method="post">'; $lt->display(); echo '</form></div>';
    }

    public function display_rules_page() {
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        if ( in_array($action, ['add', 'edit']) ) $this->render_rule_form_page(); else $this->render_rules_list_page();
    }

    private function render_rules_list_page() {
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-rules-list-table.php';
        $lt = new Rules_List_Table(); $lt->prepare_items();
        echo '<div class="wrap"><h1 class="wp-heading-inline">'.__('Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</h1><a href="?page='.$_REQUEST['page'].'&action=add" class="page-title-action">'.__('Add New', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</a><form method="post">'; $lt->display(); echo '</form></div>';
    }

    private function render_rule_form_page() {
        global $wpdb;
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;
        $is_edit = $rule_id > 0;
        if ($is_edit) $rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$rules_table} WHERE id = %d", $rule_id ) );
        else $rule = (object) ['id'=>0, 'name'=>'', 'module'=>'affiliate', 'conditions_json'=>'{}', 'actions_json'=>'{}', 'precedence'=>10, 'active'=>1];
        if (!$rule) { echo '<div class="wrap"><div class="error"><p>'.__('Rule not found.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</p></div></div>'; return; }
        require WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/views/view-rule-edit-form.php';
    }

    public function display_reports_page() {
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-reports-data.php';
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'affiliate_reports';
        echo '<div class="wrap"><h1>'.__('Reports', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</h1>';
        echo '<h2 class="nav-tab-wrapper"><a href="?page='.$this->plugin_name.'-reports&tab=affiliate_reports" class="nav-tab '.($active_tab=='affiliate_reports'?'nav-tab-active':'').'">'.__('Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</a><a href="?page='.$this->plugin_name.'-reports&tab=loyalty_reports" class="nav-tab '.($active_tab=='loyalty_reports'?'nav-tab-active':'').'">'.__('Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</a></h2>';
        echo '<div class="tab-content" style="padding-top: 20px;">';
        if ( $active_tab == 'affiliate_reports' ) {
            $stats = Reports_Data::get_affiliate_stats();
            echo '<h3>'.__('Affiliate Stats Overview', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</h3><table class="form-table">...</table><canvas id="commission-chart"></canvas>';
            $chart_data = Reports_Data::get_commissions_by_day();
            $labels = wp_json_encode(wp_list_pluck($chart_data, 'date'));
            $values = wp_json_encode(wp_list_pluck($chart_data, 'total'));
            echo "<script>document.addEventListener('DOMContentLoaded', function(){ const ctx = document.getElementById('commission-chart'); new Chart(ctx, {type: 'line', data: {labels: {$labels}, datasets: [{label: 'Commissions', data: {$values}}]}}); });</script>";
        } else {
            $stats = Reports_Data::get_loyalty_stats();
            echo '<h3>'.__('Loyalty Stats Overview', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</h3><table class="form-table">...</table><canvas id="points-chart"></canvas>';
            $chart_data = Reports_Data::get_points_by_day();
            $labels = wp_json_encode(wp_list_pluck($chart_data, 'date'));
            $values = wp_json_encode(wp_list_pluck($chart_data, 'total'));
            echo "<script>document.addEventListener('DOMContentLoaded', function(){ const ctx = document.getElementById('points-chart'); new Chart(ctx, {type: 'bar', data: {labels: {$labels}, datasets: [{label: 'Points', data: {$values}}]}}); });</script>";
        }
        echo '</div></div>';
    }

    public function display_payouts_page() {
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-payouts-list-table.php';
        $lt = new Payouts_List_Table();
        $lt->prepare_items();
        echo '<div class="wrap"><h1 class="wp-heading-inline">'.__('Process Payouts', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</h1><form method="post">';
        $lt->display();
        echo '</form></div>';
    }
}
