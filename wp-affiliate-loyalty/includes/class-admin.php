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

        add_action( 'init', array( $this, 'register_genealogy_cpt' ) );

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_init', array( $this, 'process_actions' ) );
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

        add_action( 'admin_post_save_affiliate_loyalty_rule', array( $this, 'handle_save_rule_form' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        add_filter( 'manage_aff_payout_request_posts_columns', array( $this, 'add_payout_request_columns' ) );
        add_action( 'manage_aff_payout_request_posts_custom_column', array( $this, 'render_payout_request_columns' ), 10, 2 );

        add_action( 'add_meta_boxes', array( $this, 'add_payout_request_meta_box' ) );
        add_action( 'save_post_aff_payout_request', array( $this, 'save_payout_request_meta_box_data' ) );

        add_action( 'edit_user_profile', array( $this, 'add_parent_affiliate_field' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_parent_affiliate_field' ) );
    }

    public function add_admin_menu() {
        add_menu_page( 'Affiliate & Loyalty', 'Affiliate & Loyalty', 'manage_options', $this->plugin_name, array( $this, 'display_dashboard_page' ), 'dashicons-groups', 58 );
        add_submenu_page( $this->plugin_name, 'Commissions', 'Commissions', 'manage_options', $this->plugin_name . '-commissions', array( $this, 'display_commissions_page' ) );
        add_submenu_page( $this->plugin_name, 'Rules', 'Rules', 'manage_options', $this->plugin_name . '-rules', array( $this, 'display_rules_page' ) );
        add_submenu_page( $this->plugin_name, 'Reports', 'Reports', 'manage_options', $this->plugin_name . '-reports', array( $this, 'display_reports_page' ) );
        add_submenu_page( $this->plugin_name, 'Settings', 'Settings', 'manage_options', $this->plugin_name . '-settings', array( $this, 'display_settings_page' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos($hook, 'wp-affiliate-loyalty') === false && $hook !== 'profile.php' && $hook !== 'user-edit.php') return;
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
    }

    public function register_plugin_settings() {
        register_setting( $this->settings_option_name, $this->settings_option_name, array( 'sanitize_callback' => array( $this, 'validate_mlm_settings' ) ) );
        require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/class-gateway-manager.php';

        add_settings_section( 'mlm_settings_section', 'Multi-Level Commission Settings', null, $this->plugin_name . '-settings' );
        for ($i = 1; $i <= 10; $i++) { add_settings_field( 'level_' . $i . '_commission_rate', 'Level ' . $i . ' Commission Rate (%)', array( $this, 'render_basic_text_field'), $this->plugin_name . '-settings', 'mlm_settings_section', ['id' => 'level_' . $i . '_commission_rate'] ); }

        add_settings_section( 'payout_gateway_section', 'Payout Gateway Settings', null, $this->plugin_name . '-settings' );
        $payout_gateways = Gateway_Manager::get_payout_gateways();
        add_settings_field( 'active_payout_gateway', 'Active Payout Gateway', array( $this, 'render_gateway_select_field'), $this->plugin_name . '-settings', 'payout_gateway_section', ['gateways' => $payout_gateways, 'type' => 'payout'] );
        foreach ($payout_gateways as $id => $gateway) {
            $section_id = 'payout_gateway_' . $id . '_section';
            add_settings_section( $section_id, $gateway->name . ' Settings', null, $this->plugin_name . '-settings' );
            foreach ($gateway->get_settings_fields() as $field_id => $field) { add_settings_field( "payout_{$id}_{$field_id}", $field['title'], array($this, 'render_gateway_field'), $this->plugin_name . '-settings', $section_id, ['gateway_id' => $id, 'field_id' => $field_id, 'field' => $field, 'type' => 'payout'] ); }
        }

        add_settings_section( 'sms_gateway_section', 'SMS Gateway Settings', null, $this->plugin_name . '-settings' );
        $sms_gateways = Gateway_Manager::get_sms_gateways();
        add_settings_field( 'active_sms_gateway', 'Active SMS Gateway', array( $this, 'render_gateway_select_field'), $this->plugin_name . '-settings', 'sms_gateway_section', ['gateways' => $sms_gateways, 'type' => 'sms'] );
        foreach ($sms_gateways as $id => $gateway) {
            $section_id = 'sms_gateway_' . $id . '_section';
            add_settings_section( $section_id, $gateway->name . ' Settings', null, $this->plugin_name . '-settings' );
            foreach ($gateway->get_settings_fields() as $field_id => $field) { add_settings_field( "sms_{$id}_{$field_id}", $field['title'], array($this, 'render_gateway_field'), $this->plugin_name . '-settings', $section_id, ['gateway_id' => $id, 'field_id' => $field_id, 'field' => $field, 'type' => 'sms'] ); }
        }

        add_settings_section( 'loyalty_tier_settings_section', 'Loyalty Tier Settings', null, $this->plugin_name . '-settings' );
        for ($i = 1; $i <= 5; $i++) { add_settings_field( 'loyalty_tier_' . $i, 'Tier ' . $i, array( $this, 'render_tier_setting_fields'), $this->plugin_name . '-settings', 'loyalty_tier_settings_section', ['tier_id' => $i] ); }

        add_settings_section( 'loyalty_point_settings_section', 'Loyalty Point Settings', null, $this->plugin_name . '-settings' );
        add_settings_field( 'points_for_review', 'Points for Product Review', array( $this, 'render_basic_text_field'), $this->plugin_name . '-settings', 'loyalty_point_settings_section', ['id' => 'points_for_review', 'description' => 'Number of points to award for a review.'] );
        add_settings_field( 'points_to_coupon_points', 'Points to Redeem for Coupon', array( $this, 'render_basic_text_field'), $this->plugin_name . '-settings', 'loyalty_point_settings_section', ['id' => 'points_to_coupon_points'] );
        add_settings_field( 'points_to_coupon_value', 'Value of Generated Coupon (IRR)', array( $this, 'render_basic_text_field'), $this->plugin_name . '-settings', 'loyalty_point_settings_section', ['id' => 'points_to_coupon_value'] );
    }

    public function validate_mlm_settings( $input ) {
        for ( $i = 2; $i <= 10; $i++ ) {
            $current_level_rate = isset($input['level_' . $i . '_commission_rate']) ? (float) $input['level_' . $i . '_commission_rate'] : 0;
            $previous_level_rate = isset($input['level_' . ($i - 1) . '_commission_rate']) ? (float) $input['level_' . ($i - 1) . '_commission_rate'] : 0;
            if ( $current_level_rate > 0 && $previous_level_rate <= 0 ) {
                add_settings_error('mlm_settings', 'mlm_level_gap', sprintf( 'You cannot set a commission for Level %d without setting one for Level %d.', $i, $i - 1 ), 'error');
                return get_option( $this->settings_option_name );
            }
        }
        return $input;
    }

    public function render_tier_setting_fields($args) {
        $options = get_option($this->settings_option_name);
        $tier_id = $args['tier_id'];
        $name = $options['loyalty_tiers'][$tier_id]['name'] ?? '';
        $points = $options['loyalty_tiers'][$tier_id]['points'] ?? '';
        $bonus = $options['loyalty_tiers'][$tier_id]['bonus'] ?? '';
        ?>
        <input type="text" name="<?php echo esc_attr($this->settings_option_name . "[loyalty_tiers][{$tier_id}][name]"); ?>" value="<?php echo esc_attr($name); ?>" placeholder="Tier Name" />
        <input type="number" name="<?php echo esc_attr($this->settings_option_name . "[loyalty_tiers][{$tier_id}][points]"); ?>" value="<?php echo esc_attr($points); ?>" placeholder="Points Required" />
        <input type="number" name="<?php echo esc_attr($this->settings_option_name . "[loyalty_tiers][{$tier_id}][bonus]"); ?>" value="<?php echo esc_attr($bonus); ?>" placeholder="Commission Bonus %" />
        <?php
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

    public function render_basic_text_field($args) {
        $options = get_option($this->settings_option_name);
        $value = $options[$args['id']] ?? '';
        echo "<input type='text' name='{$this->settings_option_name}[{$args['id']}]' value='" . esc_attr($value) . "' class='regular-text' />";
        if (!empty($args['description'])) echo "<p class='description'>{$args['description']}</p>";
    }

    public function display_settings_page() {
        echo '<div class="wrap"><h1>'.get_admin_page_title().'</h1><form action="options.php" method="post">';
        settings_fields( $this->settings_option_name );
        do_settings_sections( $this->plugin_name . '-settings' );
        submit_button();
        echo '</form></div>';
    }

    public function process_actions() {
        if ( isset($_GET['page']) && $_GET['page'] === $this->plugin_name . '-commissions' && isset($_GET['action']) && 'mark_paid' === sanitize_key($_GET['action']) ) {
            if ( !wp_verify_nonce( $_GET['_wpnonce'], 'aff_loyalty_mark_paid' ) ) wp_die( 'Security check failed.' );
            $commission_id = isset( $_GET['commission_id'] ) ? absint( $_GET['commission_id'] ) : 0;
            if ( !$commission_id ) return;
            global $wpdb;
            $commission = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aff_loyalty_commissions WHERE id = %d", $commission_id ) );
            if ( !$commission || 'pending' !== $commission->status ) return;
            $wpdb->update( "{$wpdb->prefix}aff_loyalty_commissions", array( 'status' => 'paid' ), array( 'id' => $commission_id ), array( '%s' ), array( '%d' ) );
            $this->update_wallet_balance( $commission->affiliate_id, $commission->amount );
            $this->log_transaction( 'commission_payout', $commission->id, $commission->affiliate_id, $commission->amount, sprintf( 'Commission for Order #%d paid.', $commission->order_id ) );
            wp_safe_redirect( add_query_arg( array( 'message' => 'commission-paid' ), remove_query_arg( array( 'action', 'commission_id', '_wpnonce' ) ) ) );
            exit;
        }
    }

    public function handle_save_rule_form() {
        if ( !isset($_POST['save_rule_nonce']) || !wp_verify_nonce( $_POST['save_rule_nonce'], 'save_rule_nonce' ) ) wp_die( 'Security check failed.' );
        if ( !current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to save rules.' );

        $conditions = array();
        if ( ! empty( $_POST['conditions']['min_total'] ) ) $conditions['min_total'] = (float) $_POST['conditions']['min_total'];
        if ( ! empty( $_POST['conditions']['products_in_cart'] ) ) $conditions['products_in_cart'] = array_map( 'absint', explode( ',', sanitize_text_field( $_POST['conditions']['products_in_cart'] ) ) );
        if ( ! empty( $_POST['conditions']['categories_in_cart'] ) ) $conditions['categories_in_cart'] = array_map( 'absint', explode( ',', sanitize_text_field( $_POST['conditions']['categories_in_cart'] ) ) );
        if ( isset( $_POST['conditions']['is_first_purchase'] ) ) $conditions['is_first_purchase'] = 1;
        if ( ! empty( $_POST['conditions']['user_role'] ) ) $conditions['user_role'] = sanitize_key( $_POST['conditions']['user_role'] );

        $actions = array();
        $action_type = sanitize_key( $_POST['actions']['type'] ?? '' );
        if ( ! empty( $action_type ) ) {
            $actions['type'] = $action_type;
            if ( 'points_per_currency_unit' === $action_type ) {
                $actions['value'] = array('points' => (float) ( $_POST['actions']['value_complex']['points'] ?? 0 ), 'per_amount' => (float) ( $_POST['actions']['value_complex']['per_amount'] ?? 0 ));
            } else {
                $actions['value'] = (float) ( $_POST['actions']['value'] ?? 0 );
            }
        }

        $data = array( 'name' => sanitize_text_field($_POST['name']), 'module' => sanitize_key($_POST['module']), 'conditions_json' => wp_json_encode( $conditions ), 'actions_json' => wp_json_encode( $actions ), 'precedence' => absint($_POST['precedence']), 'active' => isset($_POST['active']) ? 1 : 0 );
        if ( empty( $actions ) || ( 'points_per_currency_unit' === $actions['type'] && ( !isset($actions['value']['points']) || $actions['value']['points'] <= 0 || !isset($actions['value']['per_amount']) || $actions['value']['per_amount'] <= 0 ) ) ) {
            wp_die( 'Invalid or incomplete action details provided.' );
        }
        global $wpdb;
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
        if ( $rule_id > 0 ) {
            $wpdb->update( $rules_table, $data, array( 'id' => $rule_id ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $rules_table, $data );
        }
        wp_safe_redirect( add_query_arg( array( 'page' => $this->plugin_name . '-rules', 'message' => 'rule-saved' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private function update_wallet_balance( $user_id, $amount ) {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'aff_loyalty_wallets';
        $wallet = $wpdb->get_row( $wpdb->prepare( "SELECT id, balance FROM {$wallets_table} WHERE user_id = %d", $user_id ) );
        if ($wallet) $wpdb->update( $wallets_table, array( 'balance' => $wallet->balance + $amount ), array( 'id' => $wallet->id ) );
        else $wpdb->insert( $wallets_table, array( 'user_id' => $user_id, 'balance' => $amount, 'currency' => 'IRR' ) );
    }

    private function log_transaction( $type, $entity_id, $user_id, $amount, $description ) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'aff_loyalty_transaction_logs';
        $wpdb->insert( $logs_table, array('entity_type' => $type, 'entity_id' => $entity_id, 'change_amount' => $amount, 'description' => $description, 'meta' => wp_json_encode(array('user_id' => $user_id)), 'created_at' => current_time('mysql')) );
    }

    public function display_admin_notices() {
        if (isset($_GET['message']) && $_GET['message'] === 'rule-saved') echo '<div class="notice notice-success is-dismissible"><p>'.__('Rule saved successfully.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN).'</p></div>';
    }

    public function display_dashboard_page() { echo '<div class="wrap"><h1>Welcome to the main dashboard.</h1></div>'; }
    public function display_commissions_page() { require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-commissions-list-table.php'; $lt = new Commissions_List_Table(); $lt->prepare_items(); echo '<div class="wrap"><h1 class="wp-heading-inline">Commissions</h1><form method="post">'; $lt->display(); echo '</form></div>'; }
    public function display_rules_page() { if (isset($_GET['action']) && in_array($_GET['action'], ['add', 'edit'])) $this->render_rule_form_page(); else $this->render_rules_list_page(); }
    private function render_rules_list_page() { require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-rules-list-table.php'; $lt = new Rules_List_Table(); $lt->prepare_items(); echo '<div class="wrap"><h1 class="wp-heading-inline">Rules</h1><a href="?page='.$_REQUEST['page'].'&action=add" class="page-title-action">Add New</a><form method="post">'; $lt->display(); echo '</form></div>'; }
    private function render_rule_form_page() { global $wpdb; $rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0; $is_edit = $rule_id > 0; if ($is_edit) $rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aff_loyalty_rules WHERE id = %d", $rule_id ) ); else $rule = (object) ['id'=>0, 'name'=>'', 'module'=>'affiliate', 'conditions_json'=>'{}', 'actions_json'=>'{}', 'precedence'=>10, 'active'=>1]; if (!$rule) { echo '<div class="wrap"><div class="error"><p>Rule not found.</p></div></div>'; return; } require WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/views/view-rule-edit-form.php'; }
    public function display_reports_page() { require_once WP_AFFILIATE_LOYALTY_PLUGIN_DIR . 'includes/admin/class-reports-data.php'; $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'affiliate_reports'; echo '<div class="wrap"><h1>Reports</h1>'; echo '<h2 class="nav-tab-wrapper"><a href="?page='.$this->plugin_name.'-reports&tab=affiliate_reports" class="nav-tab '.($active_tab=='affiliate_reports'?'nav-tab-active':'').'">Affiliate</a><a href="?page='.$this->plugin_name.'-reports&tab=loyalty_reports" class="nav-tab '.($active_tab=='loyalty_reports'?'nav-tab-active':'').'">Loyalty</a></h2>'; echo '<div class="tab-content" style="padding-top: 20px;">'; if ( $active_tab == 'affiliate_reports' ) { $stats = Reports_Data::get_affiliate_stats(); echo '<h3>Affiliate Stats Overview</h3><table class="form-table">...</table><canvas id="commission-chart"></canvas>'; $chart_data = Reports_Data::get_commissions_by_day(); $labels = wp_json_encode(wp_list_pluck($chart_data, 'date')); $values = wp_json_encode(wp_list_pluck($chart_data, 'total')); echo "<script>document.addEventListener('DOMContentLoaded', function(){ const ctx = document.getElementById('commission-chart'); new Chart(ctx, {type: 'line', data: {labels: {$labels}, datasets: [{label: 'Commissions', data: {$values}}]}}); });</script>"; } else { $stats = Reports_Data::get_loyalty_stats(); echo '<h3>Loyalty Stats Overview</h3><table class="form-table">...</table><canvas id="points-chart"></canvas>'; $chart_data = Reports_Data::get_points_by_day(); $labels = wp_json_encode(wp_list_pluck($chart_data, 'date')); $values = wp_json_encode(wp_list_pluck($chart_data, 'total')); echo "<script>document.addEventListener('DOMContentLoaded', function(){ const ctx = document.getElementById('points-chart'); new Chart(ctx, {type: 'bar', data: {labels: {$labels}, datasets: [{label: 'Points', data: {$values}}]}}); });</script>"; } echo '</div></div>'; }
    public function display_payouts_page() { echo "This page is handled by the Payout Requests CPT now."; }
}
