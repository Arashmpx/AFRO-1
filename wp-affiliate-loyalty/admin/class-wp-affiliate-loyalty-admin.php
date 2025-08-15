<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WP_Affiliate_Loyalty_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        // Hooks for the affiliate parent meta box on user profile pages
        add_action( 'show_user_profile', array( $this, 'render_user_affiliate_parent_meta_box' ) );
        add_action( 'edit_user_profile', array( $this, 'render_user_affiliate_parent_meta_box' ) );
        add_action( 'personal_options_update', array( $this, 'save_user_affiliate_parent' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_affiliate_parent' ) );
        add_action( 'init', array( $this, 'register_payout_request_cpt' ) );
        add_action( 'init', array( $this, 'register_genealogy_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_payout_request_meta_box' ) );
        add_action( 'save_post_payout_request', array( $this, 'save_payout_request_meta_box_data' ) );
    }

    /**
     * Register the Custom Post Type for Payout Requests.
     */
    public function register_payout_request_cpt() {
        $labels = array(
            'name'                  => _x( 'Payout Requests', 'Post Type General Name', 'wp-affiliate-loyalty' ),
            'singular_name'         => _x( 'Payout Request', 'Post Type Singular Name', 'wp-affiliate-loyalty' ),
            'menu_name'             => __( 'Payout Requests', 'wp-affiliate-loyalty' ),
            'name_admin_bar'        => __( 'Payout Request', 'wp-affiliate-loyalty' ),
        );
        $args = array(
            'label'                 => __( 'Payout Request', 'wp-affiliate-loyalty' ),
            'description'           => __( 'Manual payout requests from affiliates', 'wp-affiliate-loyalty' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'author' ),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'wp-affiliate-loyalty',
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow', // Prevents manual creation
            ),
            'map_meta_cap' => true,
        );
        register_post_type( 'payout_request', $args );
    }

    /**
     * Register the Custom Post Type for Genealogy.
     */
    public function register_genealogy_cpt() {
        // This is a dummy CPT, mainly to house the admin menu page
        $labels = array(
            'name' => _x('Genealogy Tree', 'post type general name', 'wp-affiliate-loyalty'),
            'singular_name' => _x('Genealogy Tree', 'post type singular name', 'wp-affiliate-loyalty'),
        );

        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'wp-affiliate-loyalty',
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap' => true,
            'supports' => false,
        );

        register_post_type('wal_genealogy', $args);
    }


    /**
     * Add the meta box for managing payout requests.
     */
    public function add_payout_request_meta_box() {
        add_meta_box(
            'wal_payout_request_details',
            __( 'Payout Request Details', 'wp-affiliate-loyalty' ),
            array( $this, 'render_payout_request_meta_box' ),
            'payout_request',
            'normal',
            'high'
        );
    }

    /**
     * Render the payout request meta box content.
     */
    public function render_payout_request_meta_box( $post ) {
        wp_nonce_field( 'wal_save_payout_request_meta_box_data', 'wal_payout_request_nonce' );

        $amount = get_post_meta( $post->ID, '_amount', true );
        $status = get_post_meta( $post->ID, '_status', true );
        $transaction_id = get_post_meta( $post->ID, '_transaction_id', true );
        $user_id = $post->post_author;
        $user_balance = get_user_meta($user_id, 'wal_wallet_balance', true);

        ?>
        <p>
            <strong><?php _e( 'Affiliate:', 'wp-affiliate-loyalty' ); ?></strong>
            <a href="<?php echo get_edit_user_link($user_id); ?>"><?php echo esc_html(get_the_author_meta('display_name', $user_id)); ?></a>
        </p>
        <p>
            <strong><?php _e( 'Current Wallet Balance:', 'wp-affiliate-loyalty' ); ?></strong>
            <?php echo wc_price($user_balance); ?>
        </p>
        <p>
            <label for="wal_payout_amount"><strong><?php _e( 'Requested Amount:', 'wp-affiliate-loyalty' ); ?></strong></label>
            <input type="text" id="wal_payout_amount" name="wal_payout_amount" value="<?php echo esc_attr( $amount ); ?>" readonly />
        </p>
        <p>
            <label for="wal_payout_status"><strong><?php _e( 'Status:', 'wp-affiliate-loyalty' ); ?></strong></label>
            <select name="wal_payout_status" id="wal_payout_status">
                <option value="pending" <?php selected( $status, 'pending' ); ?>><?php _e( 'Pending', 'wp-affiliate-loyalty' ); ?></option>
                <option value="approved" <?php selected( $status, 'approved' ); ?>><?php _e( 'Approved', 'wp-affiliate-loyalty' ); ?></option>
                <option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php _e( 'Rejected', 'wp-affiliate-loyalty' ); ?></option>
            </select>
        </p>
        <p>
            <label for="wal_transaction_id"><strong><?php _e( 'Transaction ID / Reference:', 'wp-affiliate-loyalty' ); ?></strong></label>
            <input type="text" id="wal_transaction_id" name="wal_transaction_id" value="<?php echo esc_attr( $transaction_id ); ?>" />
            <span class="description"><?php _e('Enter the transaction ID after manually sending the payment.', 'wp-affiliate-loyalty'); ?></span>
        </p>
        <?php
    }

    /**
     * Save the payout request meta box data.
     */
    public function save_payout_request_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['wal_payout_request_nonce'] ) || ! wp_verify_nonce( $_POST['wal_payout_request_nonce'], 'wal_save_payout_request_meta_box_data' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $old_status = get_post_meta( $post_id, '_status', true );
        $new_status = sanitize_text_field( $_POST['wal_payout_status'] );
        $transaction_id = sanitize_text_field( $_POST['wal_transaction_id'] );

        // If the status hasn't changed, or if it's already in a final state, do nothing.
        if ( $new_status === $old_status || in_array($old_status, ['approved', 'rejected']) ) {
            // Still update the transaction ID if it has been changed on an approved request
            if ($old_status === 'approved') {
                 update_post_meta( $post_id, '_transaction_id', $transaction_id );
            }
            return;
        }

        $amount = floatval(get_post_meta( $post_id, '_amount', true ));
        $user_id = get_post_field( 'post_author', $post_id );
        $original_title = get_the_title($post_id);
        // Remove existing status from title if present
        $original_title = preg_replace('/\s\((Pending|Approved|Rejected)\)$/', '', $original_title);

        // Update meta first
        update_post_meta( $post_id, '_status', $new_status );
        update_post_meta( $post_id, '_transaction_id', $transaction_id );

        // Handle logic based on the new status
        if ( $new_status === 'rejected' ) {
            // Refund the money to the user's wallet ONLY when moving from pending to rejected
            $current_balance = floatval(get_user_meta( $user_id, 'wal_wallet_balance', true ));
            $new_balance = $current_balance + $amount;
            update_user_meta( $user_id, 'wal_wallet_balance', $new_balance );
            update_post( array('ID' => $post_id, 'post_title' => $original_title . ' (Rejected)') );

        } else if ( $new_status === 'approved' ) {
            // No change to wallet balance needed, as the money was already deducted upon request.
            // We just mark as approved and add the transaction ID.
            update_post( array('ID' => $post_id, 'post_title' => $original_title . ' (Approved)') );

        } else if ( $new_status === 'pending' ) {
            // This case would only be hit if an admin moves it back to pending from an invalid state.
            // We'll just update the title.
             update_post( array('ID' => $post_id, 'post_title' => $original_title . ' (Pending)') );
        }
    }


    /**
     * Add the main menu page for the plugin.
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Affiliate & Loyalty', 'wp-affiliate-loyalty' ),
            __( 'Affiliate & Loyalty', 'wp-affiliate-loyalty' ),
            'manage_options',
            'wp-affiliate-loyalty',
            array( $this, 'display_settings_page' ),
            'dashicons-groups',
            50
        );
    }

    /**
     * Display the main settings page.
     */
    public function display_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-affiliate-loyalty&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'wp-affiliate-loyalty'); ?></a>
                <a href="?page=wp-affiliate-loyalty&tab=affiliate" class="nav-tab <?php echo $active_tab == 'affiliate' ? 'nav-tab-active' : ''; ?>"><?php _e('Affiliate', 'wp-affiliate-loyalty'); ?></a>
                <a href="?page=wp-affiliate-loyalty&tab=loyalty" class="nav-tab <?php echo $active_tab == 'loyalty' ? 'nav-tab-active' : ''; ?>"><?php _e('Loyalty', 'wp-affiliate-loyalty'); ?></a>
            </h2>
            <form action="options.php" method="post">
                <?php
                settings_fields('wal_settings');
                if ($active_tab == 'general') {
                    do_settings_sections('wal_general_settings');
                } elseif ($active_tab == 'affiliate') {
                    do_settings_sections('wal_affiliate_settings');
                } else {
                    do_settings_sections('wal_loyalty_settings');
                }
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register all settings for the plugin.
     */
    public function register_settings() {
        // General Settings
        register_setting('wal_settings', 'wal_general_settings');

        // Affiliate Settings
        register_setting('wal_settings', 'wal_affiliate_rules', array('sanitize_callback' => array($this, 'sanitize_affiliate_rules')));
        register_setting('wal_settings', 'wal_mlm_levels');

        // Loyalty Settings
        register_setting('wal_settings', 'wal_loyalty_points_per_currency_unit');
        register_setting('wal_settings', 'wal_loyalty_points_to_value_rate');
        register_setting('wal_settings', 'wal_loyalty_points_for_review');
        register_setting('wal_settings', 'wal_loyalty_tiers', array('sanitize_callback' => array($this, 'sanitize_loyalty_tiers')));


        // Sections and Fields
        $this->add_affiliate_settings_fields();
        $this->add_loyalty_settings_fields();
    }

    /**
     * Add settings fields for the Affiliate module.
     */
    private function add_affiliate_settings_fields() {
        add_settings_section(
            'wal_affiliate_rules_section',
            __('Affiliate Commission Rules', 'wp-affiliate-loyalty'),
            null,
            'wal_affiliate_settings'
        );

        add_settings_field(
            'wal_affiliate_rules',
            __('Rules', 'wp-affiliate-loyalty'),
            array($this, 'render_affiliate_rules_field'),
            'wal_affiliate_settings',
            'wal_affiliate_rules_section'
        );

        add_settings_section(
            'wal_mlm_settings_section',
            __('Multi-Level Marketing (MLM) Settings', 'wp-affiliate-loyalty'),
            null,
            'wal_affiliate_settings'
        );

        add_settings_field(
            'wal_mlm_levels',
            __('MLM Commission Levels (%)', 'wp-affiliate-loyalty'),
            array($this, 'render_mlm_levels_field'),
            'wal_affiliate_settings',
            'wal_mlm_settings_section'
        );
    }

    /**
     * Add settings fields for the Loyalty module.
     */
    private function add_loyalty_settings_fields() {
        add_settings_section(
            'wal_loyalty_points_section',
            __('Loyalty Points Settings', 'wp-affiliate-loyalty'),
            null,
            'wal_loyalty_settings'
        );

        add_settings_field(
            'wal_loyalty_points_per_currency_unit',
            __('Points per Currency Unit', 'wp-affiliate-loyalty'),
            array($this, 'render_loyalty_points_per_currency_unit_field'),
            'wal_loyalty_settings',
            'wal_loyalty_points_section'
        );

        add_settings_field(
            'wal_loyalty_points_to_value_rate',
            __('Points to Value Rate', 'wp-affiliate-loyalty'),
            array($this, 'render_loyalty_points_to_value_rate_field'),
            'wal_loyalty_settings',
            'wal_loyalty_points_section'
        );

        add_settings_field(
            'wal_loyalty_points_for_review',
            __('Points for Approved Review', 'wp-affiliate-loyalty'),
            array($this, 'render_loyalty_points_for_review_field'),
            'wal_loyalty_settings',
            'wal_loyalty_points_section'
        );

        add_settings_section(
            'wal_loyalty_tiers_section',
            __('Loyalty Tiers', 'wp-affiliate-loyalty'),
            null,
            'wal_loyalty_settings'
        );

        add_settings_field(
            'wal_loyalty_tiers',
            __('Tiers', 'wp-affiliate-loyalty'),
            array($this, 'render_loyalty_tiers_field'),
            'wal_loyalty_settings',
            'wal_loyalty_tiers_section'
        );

    }

    /**
     * Render fields for MLM levels.
     */
    public function render_mlm_levels_field() {
        $levels = get_option('wal_mlm_levels', array_fill(0, 10, 0));
        for ($i = 0; $i < 10; $i++) {
            ?>
            <p>
                <label for="wal_mlm_level_<?php echo $i + 1; ?>"><?php printf(__('Level %d Commission', 'wp-affiliate-loyalty'), $i + 1); ?></label>
                <input type="number" step="0.01" min="0" id="wal_mlm_level_<?php echo $i + 1; ?>" name="wal_mlm_levels[<?php echo $i; ?>]" value="<?php echo esc_attr($levels[$i]); ?>" /> %
            </p>
            <?php
        }
    }

    /**
     * Render fields for loyalty tiers.
     */
    public function render_loyalty_tiers_field() {
        $tiers = get_option('wal_loyalty_tiers', array());
        ?>
        <div id="wal-loyalty-tiers-container">
            <?php if (!empty($tiers)) : foreach ($tiers as $index => $tier) : ?>
                <div class="wal-tier-group" data-index="<?php echo $index; ?>">
                    <h4><?php _e('Tier', 'wp-affiliate-loyalty'); ?> <?php echo $index + 1; ?></h4>
                    <p>
                        <label><?php _e('Tier Name:', 'wp-affiliate-loyalty'); ?></label>
                        <input type="text" name="wal_loyalty_tiers[<?php echo $index; ?>][name]" value="<?php echo esc_attr($tier['name']); ?>">
                    </p>
                    <p>
                        <label><?php _e('Points Required:', 'wp-affiliate-loyalty'); ?></label>
                        <input type="number" name="wal_loyalty_tiers[<?php echo $index; ?>][points_required]" value="<?php echo esc_attr($tier['points_required']); ?>">
                    </p>
                    <p>
                        <label><?php _e('Commission Bonus (%):', 'wp-affiliate-loyalty'); ?></label>
                        <input type="number" step="0.01" name="wal_loyalty_tiers[<?php echo $index; ?>][commission_bonus]" value="<?php echo esc_attr($tier['commission_bonus']); ?>">
                    </p>
                     <input type="hidden" name="wal_loyalty_tiers[<?php echo $index; ?>][id]" value="<?php echo esc_attr($tier['id']); ?>">
                    <button type="button" class="button wal-remove-tier"><?php _e('Remove Tier', 'wp-affiliate-loyalty'); ?></button>
                    <hr>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <button type="button" id="wal-add-tier" class="button"><?php _e('Add Tier', 'wp-affiliate-loyalty'); ?></button>

        <script type="text/template" id="wal-tier-template">
            <div class="wal-tier-group" data-index="{{index}}">
                <h4><?php _e('Tier', 'wp-affiliate-loyalty'); ?> {{index_plus_1}}</h4>
                <p>
                    <label><?php _e('Tier Name:', 'wp-affiliate-loyalty'); ?></label>
                    <input type="text" name="wal_loyalty_tiers[{{index}}][name]" value="">
                </p>
                <p>
                    <label><?php _e('Points Required:', 'wp-affiliate-loyalty'); ?></label>
                    <input type="number" name="wal_loyalty_tiers[{{index}}][points_required]" value="">
                </p>
                <p>
                    <label><?php _e('Commission Bonus (%):', 'wp-affiliate-loyalty'); ?></label>
                    <input type="number" step="0.01" name="wal_loyalty_tiers[{{index}}][commission_bonus]" value="">
                </p>
                <input type="hidden" name="wal_loyalty_tiers[{{index}}][id]" value="{{id}}">
                <button type="button" class="button wal-remove-tier"><?php _e('Remove Tier', 'wp-affiliate-loyalty'); ?></button>
                <hr>
            </div>
        </script>

        <script>
        jQuery(document).ready(function($) {
            var max_tiers = 5;
            $('#wal-add-tier').on('click', function() {
                var container = $('#wal-loyalty-tiers-container');
                var tierCount = container.find('.wal-tier-group').length;
                if(tierCount >= max_tiers) {
                    alert('<?php _e('You can add a maximum of 5 tiers.', 'wp-affiliate-loyalty'); ?>');
                    return;
                }

                var index = tierCount;
                var template = $('#wal-tier-template').html();
                template = template.replace(/{{index}}/g, index);
                template = template.replace(/{{index_plus_1}}/g, index + 1);
                template = template.replace(/{{id}}/g, Date.now()); // Unique ID for new tier
                container.append(template);
            });

            $('#wal-loyalty-tiers-container').on('click', '.wal-remove-tier', function() {
                $(this).closest('.wal-tier-group').remove();
            });
        });
        </script>
        <?php
    }

    /**
     * Sanitize loyalty tiers settings.
     */
    public function sanitize_loyalty_tiers($input) {
        $sanitized_input = array();
        if (is_array($input)) {
            foreach ($input as $index => $tier) {
                 if (empty($tier['name']) && empty($tier['points_required'])) {
                    continue; // Skip empty entries
                }
                $sanitized_tier = array();
                $sanitized_tier['id'] = isset($tier['id']) ? sanitize_text_field($tier['id']) : 'tier_' . ($index + 1);
                $sanitized_tier['name'] = sanitize_text_field($tier['name']);
                $sanitized_tier['points_required'] = absint($tier['points_required']);
                $sanitized_tier['commission_bonus'] = floatval($tier['commission_bonus']);
                $sanitized_input[] = $sanitized_tier;
            }
        }
        return $sanitized_input;
    }


    /**
     * Render the field for points awarded per currency unit.
     */
    public function render_loyalty_points_per_currency_unit_field() {
        $value = get_option('wal_loyalty_points_per_currency_unit', 1);
        ?>
        <input type="number" step="0.01" name="wal_loyalty_points_per_currency_unit" value="<?php echo esc_attr($value); ?>" />
        <p class="description"><?php printf(__('How many points are awarded for each %s spent.', 'wp-affiliate-loyalty'), get_woocommerce_currency_symbol()); ?></p>
        <?php
    }

    /**
     * Render the field for points to value rate.
     */
    public function render_loyalty_points_to_value_rate_field() {
        $value = get_option('wal_loyalty_points_to_value_rate', 100);
        ?>
        <input type="number" name="wal_loyalty_points_to_value_rate" value="<?php echo esc_attr($value); ?>" />
        <p class="description"><?php printf(__('How many points are needed to redeem %s.', 'wp-affiliate-loyalty'), '1 ' . get_woocommerce_currency()); ?></p>
        <?php
    }

    /**
     * Render the field for points awarded for a review.
     */
    public function render_loyalty_points_for_review_field() {
        $value = get_option('wal_loyalty_points_for_review', 0);
        ?>
        <input type="number" step="1" min="0" name="wal_loyalty_points_for_review" value="<?php echo esc_attr($value); ?>" />
        <p class="description"><?php _e('How many points to award a user when they submit an approved product review. Set to 0 to disable.', 'wp-affiliate-loyalty'); ?></p>
        <?php
    }


    /**
     * Render the form for managing affiliate rules.
     */
    public function render_affiliate_rules_field() {
        $rules = get_option('wal_affiliate_rules', array());
        ?>
        <div id="wal-rules-container">
            <?php if (!empty($rules)) : foreach ($rules as $index => $rule) : ?>
                <div class="wal-rule-group" data-index="<?php echo $index; ?>">
                    <?php $this->render_rule_form_group($index, $rule); ?>
                </div>
            <?php endforeach; endif; ?>
        </div>
        <button type="button" id="wal-add-rule" class="button"><?php _e('Add Rule', 'wp-affiliate-loyalty'); ?></button>

        <script type="text/template" id="wal-rule-template">
            <div class="wal-rule-group" data-index="{{index}}">
                <?php $this->render_rule_form_group('{{index}}'); ?>
            </div>
        </script>

        <script>
            jQuery(document).ready(function($) {
                $('#wal-add-rule').on('click', function() {
                    var container = $('#wal-rules-container');
                    var index = container.find('.wal-rule-group').length;
                    var template = $('#wal-rule-template').html().replace(/{{index}}/g, index);
                    container.append(template);
                });

                $('#wal-rules-container').on('click', '.wal-remove-rule', function() {
                    $(this).closest('.wal-rule-group').remove();
                });
            });
        </script>
        <?php
    }

    /**
     * Helper to render a single rule form group.
     */
    private function render_rule_form_group($index, $rule = array()) {
        $conditions = isset($rule['conditions']) ? $rule['conditions'] : array();
        $actions = isset($rule['actions']) ? $rule['actions'] : array();
        ?>
        <h4><?php printf(__('Rule %s', 'wp-affiliate-loyalty'), is_numeric($index) ? $index + 1 : $index); ?></h4>

        <!-- Conditions -->
        <div class="wal-conditions">
            <h5><?php _e('Conditions (IF)', 'wp-affiliate-loyalty'); ?></h5>
            <select name="wal_affiliate_rules[<?php echo $index; ?>][conditions][type]">
                <option value="min_total" <?php selected(isset($conditions['type']) ? $conditions['type'] : '', 'min_total'); ?>><?php _e('Minimum Order Total', 'wp-affiliate-loyalty'); ?></option>
                <option value="product" <?php selected(isset($conditions['type']) ? $conditions['type'] : '', 'product'); ?>><?php _e('Specific Product(s)', 'wp-affiliate-loyalty'); ?></option>
                <option value="category" <?php selected(isset($conditions['type']) ? $conditions['type'] : '', 'category'); ?>><?php _e('Specific Category(ies)', 'wp-affiliate-loyalty'); ?></option>
                <option value="first_purchase" <?php selected(isset($conditions['type']) ? $conditions['type'] : '', 'first_purchase'); ?>><?php _e('First Purchase Only', 'wp-affiliate-loyalty'); ?></option>
                 <option value="user_role" <?php selected(isset($conditions['type']) ? $conditions['type'] : '', 'user_role'); ?>><?php _e('Specific User Role', 'wp-affiliate-loyalty'); ?></option>
            </select>
            <input type="text" name="wal_affiliate_rules[<?php echo $index; ?>][conditions][value]" value="<?php echo esc_attr(isset($conditions['value']) ? $conditions['value'] : ''); ?>" placeholder="Value (e.g., 100, 12, 15, customer)">
        </div>

        <!-- Actions -->
        <div class="wal-actions">
            <h5><?php _e('Actions (THEN)', 'wp-affiliate-loyalty'); ?></h5>
            <select name="wal_affiliate_rules[<?php echo $index; ?>][actions][type]">
                <option value="percentage_total" <?php selected(isset($actions['type']) ? $actions['type'] : '', 'percentage_total'); ?>><?php _e('Percentage of Order Total', 'wp-affiliate-loyalty'); ?></option>
                <option value="percentage_profit" <?php selected(isset($actions['type']) ? $actions['type'] : '', 'percentage_profit'); ?>><?php _e('Percentage of Order Profit', 'wp-affiliate-loyalty'); ?></option>
                <option value="fixed_amount" <?php selected(isset($actions['type']) ? $actions['type'] : '', 'fixed_amount'); ?>><?php _e('Fixed Amount', 'wp-affiliate-loyalty'); ?></option>
                 <option value="points_per_currency" <?php selected(isset($actions['type']) ? $actions['type'] : '', 'points_per_currency'); ?>><?php _e('Points per Currency Unit', 'wp-affiliate-loyalty'); ?></option>
            </select>
            <input type="number" step="0.01" name="wal_affiliate_rules[<?php echo $index; ?>][actions][value]" value="<?php echo esc_attr(isset($actions['value']) ? $actions['value'] : ''); ?>" placeholder="Value">
        </div>

        <button type="button" class="button wal-remove-rule"><?php _e('Remove Rule', 'wp-affiliate-loyalty'); ?></button>
        <hr>
        <?php
    }

    /**
     * Sanitize affiliate rules settings.
     */
    public function sanitize_affiliate_rules($input) {
        $sanitized_input = array();
        if (is_array($input)) {
            foreach ($input as $rule) {
                if (empty($rule['conditions']['type']) && empty($rule['actions']['type'])) {
                    continue; // Skip empty entries
                }
                $sanitized_rule = array(
                    'conditions' => array(
                        'type' => sanitize_text_field($rule['conditions']['type']),
                        'value' => sanitize_text_field($rule['conditions']['value']),
                    ),
                    'actions' => array(
                        'type' => sanitize_text_field($rule['actions']['type']),
                        'value' => sanitize_text_field($rule['actions']['value']),
                    ),
                );
                $sanitized_input[] = $sanitized_rule;
            }
        }
        return $sanitized_input;
    }

    /**
     * Render the meta box for setting the affiliate parent on user profile pages.
     */
    public function render_user_affiliate_parent_meta_box($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        ?>
        <h3><?php _e('Affiliate & Loyalty', 'wp-affiliate-loyalty'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="wal_affiliate_parent"><?php _e('Affiliate Parent', 'wp-affiliate-loyalty'); ?></label></th>
                <td>
                    <input type="number" name="wal_affiliate_parent" id="wal_affiliate_parent" value="<?php echo esc_attr(get_user_meta($user->ID, 'wal_affiliate_parent', true)); ?>" class="regular-text" />
                    <p class="description"><?php _e('Enter the User ID of the parent affiliate.', 'wp-affiliate-loyalty'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the affiliate parent when the user profile is updated.
     */
    public function save_user_affiliate_parent($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        if (isset($_POST['wal_affiliate_parent'])) {
            $parent_id = intval($_POST['wal_affiliate_parent']);
            if ($parent_id > 0 && get_userdata($parent_id) && $parent_id != $user_id) { // Also check user cannot be their own parent
                update_user_meta($user_id, 'wal_affiliate_parent', $parent_id);
            } else {
                delete_user_meta($user_id, 'wal_affiliate_parent');
            }
        }
    }
}
