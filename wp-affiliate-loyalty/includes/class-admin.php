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

        // Payout Requests CPT Columns
        add_filter( 'manage_aff_payout_request_posts_columns', array( $this, 'add_payout_request_columns' ) );
        add_action( 'manage_aff_payout_request_posts_custom_column', array( $this, 'render_payout_request_columns' ), 10, 2 );

        // Payout Request Meta Box
        add_action( 'add_meta_boxes', array( $this, 'add_payout_request_meta_box' ) );
        add_action( 'save_post_aff_payout_request', array( $this, 'save_payout_request_meta_box_data' ) );

        // Parent affiliate field on user profile
        add_action( 'edit_user_profile', array( $this, 'add_parent_affiliate_field' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_parent_affiliate_field' ) );

        // Genealogy CPT
        add_action( 'init', array( $this, 'register_genealogy_cpt' ) );
    }

    public function register_genealogy_cpt() {
        register_post_type( 'aff_genealogy_node',
            array(
                'labels' => array('name' => 'Genealogy Nodes'),
                'public' => false,
                'show_ui' => false,
                'supports' => array('title', 'page-attributes'), // Need page-attributes for parent selection
            )
        );
    }

    private function get_or_create_genealogy_node( $user_id ) {
        $args = array(
            'post_type' => 'aff_genealogy_node',
            'meta_query' => array(
                array(
                    'key' => '_user_id',
                    'value' => $user_id,
                    'compare' => '=',
                ),
            ),
        );
        $query = new WP_Query( $args );
        if ( $query->have_posts() ) {
            $node_id = $query->posts[0]->ID;
        } else {
            $user = get_userdata($user_id);
            $node_id = wp_insert_post(array(
                'post_title' => 'Node for ' . $user->user_login,
                'post_type' => 'aff_genealogy_node',
                'post_status' => 'publish',
            ));
            if ($node_id) {
                update_post_meta( $node_id, '_user_id', $user_id );
            }
        }
        wp_reset_postdata();
        return $node_id;
    }

    public function save_parent_affiliate_field( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }
        if ( isset( $_POST['parent_affiliate'] ) ) {
            $parent_id = absint( $_POST['parent_affiliate'] );

            // Save the simple user meta for direct parent reference
            if ( $parent_id > 0 ) {
                update_user_meta( $user_id, '_aff_loyalty_parent_affiliate_id', $parent_id );
            } else {
                delete_user_meta( $user_id, '_aff_loyalty_parent_affiliate_id' );
            }

            // Update the genealogy tree
            $child_node_id = $this->get_or_create_genealogy_node( $user_id );
            $parent_node_id = 0;
            if ( $parent_id > 0 ) {
                $parent_node_id = $this->get_or_create_genealogy_node( $parent_id );
            }

            wp_update_post(array(
                'ID' => $child_node_id,
                'post_parent' => $parent_node_id,
            ));
        }
    }

    public function add_parent_affiliate_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $parent_id = get_user_meta( $user->ID, '_aff_loyalty_parent_affiliate_id', true );
        ?>
        <h3><?php esc_html_e( 'Affiliate & Loyalty Settings', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="parent_affiliate"><?php esc_html_e( 'Parent Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <select name="parent_affiliate" id="parent_affiliate">
                        <option value="0"><?php esc_html_e( '-- None --', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        <?php
                        $users = get_users( array( 'exclude' => array( $user->ID ) ) ); // Exclude self
                        foreach ( $users as $u ) {
                            echo '<option value="' . esc_attr( $u->ID ) . '"' . selected( $parent_id, $u->ID, false ) . '>' . esc_html( $u->display_name ) . ' (#' . esc_html( $u->ID ) . ')</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select a parent for this affiliate to enable Level 2 commissions.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_payout_request_meta_box_data( $post_id ) {
        // Check if our nonce is set.
        if ( ! isset( $_POST['payout_details_nonce'] ) ) {
            return;
        }
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $_POST['payout_details_nonce'], 'save_payout_details' ) ) {
            return;
        }
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $old_status = get_post_meta( $post_id, '_payout_status', true );
        $new_status = isset( $_POST['payout_status'] ) ? sanitize_key( $_POST['payout_status'] ) : 'pending';

        // Update status
        update_post_meta( $post_id, '_payout_status', $new_status );

        // Update transaction reference
        if ( isset( $_POST['transaction_ref'] ) ) {
            update_post_meta( $post_id, '_transaction_ref', sanitize_text_field( $_POST['transaction_ref'] ) );
        }

        // If status changed to rejected, refund the money to the user's wallet
        if ( $new_status === 'rejected' && $old_status !== 'rejected' ) {
            $amount = (float) get_post_meta( $post_id, '_payout_amount', true );
            $affiliate_id = get_post_meta( $post_id, '_affiliate_id', true );
            if ( $affiliate_id && $amount > 0 ) {
                $this->update_wallet_balance( $affiliate_id, $amount ); // Add the amount back
            }
        }

        // If status changed to completed, send an SMS
        if ( $new_status === 'completed' && $old_status !== 'completed' ) {
            $sms_gateway = Gateway_Manager::get_active_sms_gateway();
            if ( $sms_gateway ) {
                $affiliate_id = get_post_meta( $post_id, '_affiliate_id', true );
                $phone = get_user_meta( $affiliate_id, 'billing_phone', true );
                if( $phone ) {
                    $amount = wc_price( get_post_meta( $post_id, '_payout_amount', true ) );
                    $ref = get_post_meta( $post_id, '_transaction_ref', true );
                    $message = sprintf(
                        "درخواست تسویه حساب شما به مبلغ %s با موفقیت انجام شد. شماره پیگیری: %s",
                        $amount,
                        $ref
                    );
                    $sms_gateway->send_sms( $phone, $message );
                }
            }
        }
    }

    public function add_payout_request_meta_box() {
        add_meta_box(
            'payout_request_details',
            __( 'Payout Details', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            array( $this, 'render_payout_request_meta_box_content' ),
            'aff_payout_request',
            'normal',
            'high'
        );
    }

    public function render_payout_request_meta_box_content( $post ) {
        wp_nonce_field( 'save_payout_details', 'payout_details_nonce' );

        $amount = get_post_meta( $post->ID, '_payout_amount', true );
        $status = get_post_meta( $post->ID, '_payout_status', true );
        $affiliate_id = get_post_meta( $post->ID, '_affiliate_id', true );
        $transaction_ref = get_post_meta( $post->ID, '_transaction_ref', true );
        $user = get_userdata( $affiliate_id );
        ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th>
                <td><?php echo esc_html( $user->display_name ); ?> (ID: <?php echo esc_html($affiliate_id); ?>)</td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th>
                <td><strong><?php echo esc_html( wc_price( $amount ) ); ?></strong></td>
            </tr>
            <tr>
                <th><label for="payout_status"><?php esc_html_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <select name="payout_status" id="payout_status">
                        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        <option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="transaction_ref"><?php esc_html_e( 'Transaction Reference', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <input type="text" id="transaction_ref" name="transaction_ref" value="<?php echo esc_attr( $transaction_ref ); ?>" class="widefat" />
                    <p class="description"><?php esc_html_e( 'Enter the transaction ID or reference number from your manual payment.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function add_payout_request_columns( $columns ) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = __( 'Request Details', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $new_columns['payout_amount'] = __( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $new_columns['affiliate'] = __( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $new_columns['payout_status'] = __( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function render_payout_request_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'payout_amount':
                echo esc_html( wc_price( get_post_meta( $post_id, '_payout_amount', true ) ) );
                break;
            case 'affiliate':
                $user_id = get_post_meta( $post_id, '_affiliate_id', true );
                if ( $user_id ) {
                    $user = get_userdata( $user_id );
                    echo esc_html( $user->display_name );
                }
                break;
            case 'payout_status':
                $status = get_post_meta( $post_id, '_payout_status', true );
                echo '<span class="payout-status-' . esc_attr($status) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
                break;
        }
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

        add_settings_section( 'affiliate_settings_section', 'Affiliate Settings', null, $this->plugin_name . '-settings' );

        add_settings_section( 'mlm_settings_section', 'Multi-Level Commission Settings', null, $this->plugin_name . '-settings' );
        for ($i = 1; $i <= 10; $i++) {
            add_settings_field(
                'level_' . $i . '_commission_rate',
                'Level ' . $i . ' Commission Rate (%)',
                array( $this, 'render_basic_text_field'),
                $this->plugin_name . '-settings',
                'mlm_settings_section',
                ['id' => 'level_' . $i . '_commission_rate', 'description' => 'Commission rate for level ' . $i . ' ancestors.']
            );
        }

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

        add_settings_section( 'sms_gateway_section', 'SMS Gateway Settings', null, $this->plugin_name . '-settings' );
        $sms_gateways = Gateway_Manager::get_sms_gateways();
        add_settings_field( 'active_sms_gateway', 'Active SMS Gateway', array( $this, 'render_gateway_select_field'), $this->plugin_name . '-settings', 'sms_gateway_section', ['gateways' => $sms_gateways, 'type' => 'sms'] );
        foreach ($sms_gateways as $id => $gateway) {
            $section_id = 'sms_gateway_' . $id . '_section';
            add_settings_section( $section_id, $gateway->name . ' Settings', null, $this->plugin_name . '-settings' );
            foreach ($gateway->get_settings_fields() as $field_id => $field) {
                add_settings_field( "sms_{$id}_{$field_id}", $field['title'], array($this, 'render_gateway_field'), $this->plugin_name . '-settings', $section_id, ['gateway_id' => $id, 'field_id' => $field_id, 'field' => $field, 'type' => 'sms'] );
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

    public function render_basic_text_field($args) {
        $options = get_option($this->settings_option_name);
        $value = $options[$args['id']] ?? '';
        echo "<input type='text' name='{$this->settings_option_name}[{$args['id']}]' value='" . esc_attr($value) . "' class='regular-text' />";
        if (!empty($args['description'])) {
            echo "<p class='description'>{$args['description']}</p>";
        }
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

        // Build conditions from form inputs
        $conditions = array();
        if ( ! empty( $_POST['conditions']['min_total'] ) ) {
            $conditions['min_total'] = (float) $_POST['conditions']['min_total'];
        }
        if ( ! empty( $_POST['conditions']['products_in_cart'] ) ) {
            $conditions['products_in_cart'] = array_map( 'absint', explode( ',', sanitize_text_field( $_POST['conditions']['products_in_cart'] ) ) );
        }
        if ( ! empty( $_POST['conditions']['categories_in_cart'] ) ) {
            $conditions['categories_in_cart'] = array_map( 'absint', explode( ',', sanitize_text_field( $_POST['conditions']['categories_in_cart'] ) ) );
        }
        if ( isset( $_POST['conditions']['is_first_purchase'] ) ) {
            $conditions['is_first_purchase'] = 1;
        }
        if ( ! empty( $_POST['conditions']['user_role'] ) ) {
            $conditions['user_role'] = sanitize_key( $_POST['conditions']['user_role'] );
        }

        global $wpdb;
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

        // Build actions from form inputs
        $actions = array();
        $action_type = sanitize_key( $_POST['actions']['type'] ?? '' );

        if ( ! empty( $action_type ) ) {
            $actions['type'] = $action_type;

            if ( 'points_per_currency_unit' === $action_type ) {
                $actions['value'] = array(
                    'points'     => (float) ( $_POST['actions']['value_complex']['points'] ?? 0 ),
                    'per_amount' => (float) ( $_POST['actions']['value_complex']['per_amount'] ?? 0 ),
                );
            } else {
                $actions['value'] = (float) ( $_POST['actions']['value'] ?? 0 );
            }
        }

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'module' => sanitize_key($_POST['module']),
            'conditions_json' => wp_json_encode( $conditions ),
            'actions_json' => wp_json_encode( $actions ),
            'precedence' => absint($_POST['precedence']),
            'active' => isset($_POST['active']) ? 1 : 0
        );

        if ( empty( $actions ) || ( 'points_per_currency_unit' === $actions['type'] && ( !isset($actions['value']['points']) || $actions['value']['points'] <= 0 || !isset($actions['value']['per_amount']) || $actions['value']['per_amount'] <= 0 ) ) ) {
            wp_die( 'Invalid or incomplete action details provided.' );
        }

        if ( $rule_id > 0 ) {
            $wpdb->update( $rules_table, $data, array( 'id' => $rule_id ), $this->get_rule_data_formats(), array( '%d' ) );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $rules_table, $data, $this->get_rule_data_formats() );
        }

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
