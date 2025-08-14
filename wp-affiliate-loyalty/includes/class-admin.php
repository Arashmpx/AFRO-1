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
                'supports' => array('title', 'page-attributes'),
            )
        );
    }

    private function get_or_create_genealogy_node( $user_id ) {
        $args = array(
            'post_type' => 'aff_genealogy_node',
            'meta_query' => array(
                array('key' => '_user_id', 'value' => $user_id, 'compare' => '='),
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
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        if ( isset( $_POST['parent_affiliate'] ) ) {
            $parent_id = absint( $_POST['parent_affiliate'] );
            if ( $parent_id > 0 ) {
                update_user_meta( $user_id, '_aff_loyalty_parent_affiliate_id', $parent_id );
            } else {
                delete_user_meta( $user_id, '_aff_loyalty_parent_affiliate_id' );
            }
            $child_node_id = $this->get_or_create_genealogy_node( $user_id );
            $parent_node_id = ( $parent_id > 0 ) ? $this->get_or_create_genealogy_node( $parent_id ) : 0;
            wp_update_post(array('ID' => $child_node_id, 'post_parent' => $parent_node_id));
        }
    }

    public function add_parent_affiliate_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $parent_id = get_user_meta( $user->ID, '_aff_loyalty_parent_affiliate_id', true );
        ?>
        <h3><?php esc_html_e( 'Affiliate & Loyalty Settings', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="parent_affiliate"><?php esc_html_e( 'Parent Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <select name="parent_affiliate" id="parent_affiliate" style="width: 25em;">
                        <option value="0"><?php esc_html_e( '-- None --', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        <?php
                        $users = get_users( array( 'exclude' => array( $user->ID ) ) );
                        foreach ( $users as $u ) {
                            echo '<option value="' . esc_attr( $u->ID ) . '"' . selected( $parent_id, $u->ID, false ) . '>' . esc_html( $u->display_name ) . ' (#' . esc_html( $u->ID ) . ')</option>';
                        }
                        ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Select a parent for this affiliate to enable multi-level commissions.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_payout_request_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['payout_details_nonce'] ) || ! wp_verify_nonce( $_POST['payout_details_nonce'], 'save_payout_details' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $old_status = get_post_meta( $post_id, '_payout_status', true );
        $new_status = isset( $_POST['payout_status'] ) ? sanitize_key( $_POST['payout_status'] ) : 'pending';
        update_post_meta( $post_id, '_payout_status', $new_status );

        if ( isset( $_POST['transaction_ref'] ) ) {
            update_post_meta( $post_id, '_transaction_ref', sanitize_text_field( $_POST['transaction_ref'] ) );
        }

        if ( $new_status === 'rejected' && $old_status !== 'rejected' ) {
            $amount = (float) get_post_meta( $post_id, '_payout_amount', true );
            $affiliate_id = get_post_meta( $post_id, '_affiliate_id', true );
            if ( $affiliate_id && $amount > 0 ) $this->update_wallet_balance( $affiliate_id, $amount );
        }

        if ( $new_status === 'completed' && $old_status !== 'completed' ) {
            $sms_gateway = Gateway_Manager::get_active_sms_gateway();
            if ( $sms_gateway ) {
                $affiliate_id = get_post_meta( $post_id, '_affiliate_id', true );
                $phone = get_user_meta( $affiliate_id, 'billing_phone', true );
                if( $phone ) {
                    $amount = wc_price( get_post_meta( $post_id, '_payout_amount', true ) );
                    $ref = get_post_meta( $post_id, '_transaction_ref', true );
                    $message = sprintf( "درخواست تسویه حساب شما به مبلغ %s با موفقیت انجام شد. شماره پیگیری: %s", $amount, $ref );
                    $sms_gateway->send_sms( $phone, $message );
                }
            }
        }
    }

    public function add_payout_request_meta_box() {
        add_meta_box('payout_request_details', __( 'Payout Details', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), array( $this, 'render_payout_request_meta_box_content' ), 'aff_payout_request', 'normal', 'high');
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
            <tr><th><?php esc_html_e( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><td><?php echo esc_html( $user->display_name ); ?> (ID: <?php echo esc_html($affiliate_id); ?>)</td></tr>
            <tr><th><?php esc_html_e( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></th><td><strong><?php echo esc_html( wc_price( $amount ) ); ?></strong></td></tr>
            <tr><th><label for="payout_status"><?php esc_html_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label></th>
                <td>
                    <select name="payout_status" id="payout_status">
                        <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        <option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                    </select>
                </td>
            </tr>
            <tr><th><label for="transaction_ref"><?php esc_html_e( 'Transaction Reference', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label></th>
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
            case 'payout_amount': echo esc_html( wc_price( get_post_meta( $post_id, '_payout_amount', true ) ) ); break;
            case 'affiliate':
                $user_id = get_post_meta( $post_id, '_affiliate_id', true );
                if ( $user_id ) { $user = get_userdata( $user_id ); echo esc_html( $user->display_name ); }
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
        // The Payouts CPT will create its own menu item. No need for this manual one.
        // add_submenu_page( $this->plugin_name, 'Payouts', 'Payouts', 'manage_options', $this->plugin_name . '-payouts', array( $this, 'display_payouts_page' ) );
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
        for ($i = 1; $i <= 10; $i++) {
            add_settings_field( 'level_' . $i . '_commission_rate', 'Level ' . $i . ' Commission Rate (%)', array( $this, 'render_basic_text_field'), $this->plugin_name . '-settings', 'mlm_settings_section', ['id' => 'level_' . $i . '_commission_rate'] );
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

        add_settings_section( 'loyalty_tier_settings_section', 'Loyalty Tier Settings', null, $this->plugin_name . '-settings' );
        for ($i = 1; $i <= 5; $i++) {
            add_settings_field( 'loyalty_tier_' . $i, 'Tier ' . $i, array( $this, 'render_tier_setting_fields'), $this->plugin_name . '-settings', 'loyalty_tier_settings_section', ['tier_id' => $i] );
        }

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
        <input type="text" name="<?php echo esc_attr($this->settings_option_name); ?>[loyalty_tiers][<?php echo esc_attr($tier_id); ?>][name]" value="<?php echo esc_attr($name); ?>" placeholder="<?php esc_attr_e('Tier Name', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?>" />
        <input type="number" name="<?php echo esc_attr($this->settings_option_name); ?>[loyalty_tiers][<?php echo esc_attr($tier_id); ?>][points]" value="<?php echo esc_attr($points); ?>" placeholder="<?php esc_attr_e('Points Required', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?>" />
        <input type="number" name="<?php echo esc_attr($this->settings_option_name); ?>[loyalty_tiers][<?php echo esc_attr($tier_id); ?>][bonus]" value="<?php echo esc_attr($bonus); ?>" placeholder="<?php esc_attr_e('Commission Bonus %', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?>" />
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

    // ... (rest of file)
}
