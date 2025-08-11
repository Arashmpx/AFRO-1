<?php
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
        add_action( 'admin_post_save_affiliate_loyalty_rule', array( $this, 'handle_save_rule_form' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_wp-affiliate-loyalty' !== $hook && 'affiliate-loyalty_page_wp-affiliate-loyalty-reports' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
    }

    public function add_admin_menu() {
        add_menu_page( __( 'Affiliate & Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), __( 'Affiliate & Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'manage_options', $this->plugin_name, array( $this, 'display_dashboard_page' ), 'dashicons-groups', 58 );
        add_submenu_page( $this->plugin_name, __( 'Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), __( 'Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'manage_options', $this->plugin_name . '-commissions', array( $this, 'display_commissions_page' ) );
        add_submenu_page( $this->plugin_name, __( 'Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), __( 'Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'manage_options', $this->plugin_name . '-rules', array( $this, 'display_rules_page' ) );
        add_submenu_page( $this->plugin_name, __( 'Reports', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), __( 'Reports', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ), 'manage_options', $this->plugin_name . '-reports', array( $this, 'display_reports_page' ) );
    }

    public function process_actions() {
        if ( !isset($_GET['page']) || $_GET['page'] !== $this->plugin_name . '-commissions' || !isset($_GET['action']) || 'mark_paid' !== sanitize_key($_GET['action']) ) return;
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

    public function handle_save_rule_form() {
        if ( !isset($_POST['save_rule_nonce']) || !wp_verify_nonce( $_POST['save_rule_nonce'], 'save_rule_nonce' ) ) wp_die( 'Security check failed.' );
        if ( !current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to save rules.' );
        global $wpdb;
        $rules_table = $wpdb->prefix . 'aff_loyalty_rules';
        $rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'module' => sanitize_key($_POST['module']),
            'conditions_json' => wp_unslash(trim($_POST['conditions_json'])),
            'actions_json' => wp_unslash(trim($_POST['actions_json'])),
            'precedence' => absint($_POST['precedence']),
            'active' => isset($_POST['active']) ? 1 : 0,
        );
        if ( json_decode($data['conditions_json']) === null || json_decode($data['actions_json']) === null ) wp_die( 'Invalid JSON format.' );
        if ( $rule_id > 0 ) $wpdb->update( $rules_table, $data, array( 'id' => $rule_id ), $this->get_rule_data_formats(), array( '%d' ) );
        else {
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
    }

    public function display_dashboard_page() { echo '<div class="wrap"><h1>'.get_admin_page_title().'</h1></div>'; }

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
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Reports', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h1>
            <?php
            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'affiliate_reports';
            ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo esc_attr( $this->plugin_name ); ?>-reports&tab=affiliate_reports" class="nav-tab <?php echo $active_tab == 'affiliate_reports' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></a>
                <a href="?page=<?php echo esc_attr( $this->plugin_name ); ?>-reports&tab=loyalty_reports" class="nav-tab <?php echo $active_tab == 'loyalty_reports' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></a>
            </h2>
            <div class="tab-content" style="padding-top: 20px;">
                <?php if ( $active_tab == 'affiliate_reports' ) :
                    $stats = Reports_Data::get_affiliate_stats();
                ?>
                    <h3><?php esc_html_e( 'Affiliate Stats Overview', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
                    <table class="form-table">
                        <tr><th scope="row"><?php esc_html_e('Total Clicks', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['total_clicks'])); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Total Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['total_commissions'])); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Conversion Rate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['conversion_rate'], 2)); ?>%</td></tr>
                        <tr><th scope="row"><?php esc_html_e('Paid Commissions Value', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['paid_commissions_value'], 0)); ?> <?php esc_html_e('IRR'); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Unpaid Commissions Value', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['unpaid_commissions_value'], 0)); ?> <?php esc_html_e('IRR'); ?></td></tr>
                    </table>
                    <canvas id="commission-chart" width="400" height="200"></canvas>
                    <?php
                        $commission_chart_data = Reports_Data::get_commissions_by_day();
                        $commission_labels = wp_json_encode( wp_list_pluck( $commission_chart_data, 'date' ) );
                        $commission_values = wp_json_encode( wp_list_pluck( $commission_chart_data, 'total' ) );
                    ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const ctx = document.getElementById('commission-chart');
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: <?php echo $commission_labels; ?>,
                                    datasets: [{
                                        label: '<?php esc_html_e( "Commissions per Day", WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>',
                                        data: <?php echo $commission_values; ?>,
                                        borderWidth: 1
                                    }]
                                },
                                options: { scales: { y: { beginAtZero: true } } }
                            });
                        });
                    </script>
                <?php else :
                    $stats = Reports_Data::get_loyalty_stats();
                ?>
                    <h3><?php esc_html_e( 'Loyalty Stats Overview', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
                    <table class="form-table">
                        <tr><th scope="row"><?php esc_html_e('Total Points Awarded', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['points_awarded'])); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Total Points Redeemed', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['points_redeemed'])); ?></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Redemption Rate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN); ?></th><td><?php echo esc_html(number_format_i18n($stats['redemption_rate'], 2)); ?>%</td></tr>
                    </table>
                    <canvas id="points-chart" width="400" height="200"></canvas>
                     <?php
                        $points_chart_data = Reports_Data::get_points_by_day();
                        $points_labels = wp_json_encode( wp_list_pluck( $points_chart_data, 'date' ) );
                        $points_values = wp_json_encode( wp_list_pluck( $points_chart_data, 'total' ) );
                    ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const ctx = document.getElementById('points-chart');
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo $points_labels; ?>,
                                    datasets: [{
                                        label: '<?php esc_html_e( "Points Awarded per Day", WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>',
                                        data: <?php echo $points_values; ?>,
                                        borderWidth: 1
                                    }]
                                },
                                options: { scales: { y: { beginAtZero: true } } }
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
