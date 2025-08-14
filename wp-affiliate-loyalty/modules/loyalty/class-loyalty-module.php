<?php
/**
 * Loyalty Module
 *
 * This class handles the loyalty features of the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WP_Affiliate_Loyalty_Loyalty_Module {
    public function __construct() {
        // Actions and filters for the loyalty module
        add_action('woocommerce_order_status_completed', array($this, 'award_points_for_purchase'));
        add_action('wp_footer', array($this, 'display_points_redemption_form'));
        add_action('wp_ajax_wal_redeem_points', array($this, 'handle_points_redemption'));
        add_action('wp_ajax_nopriv_wal_redeem_points', array($this, 'handle_points_redemption'));

        // Action to award points for product reviews
        add_action('comment_post', array($this, 'award_points_for_review'), 10, 2);

        // Schedule daily event for tier updates
        if (!wp_next_scheduled('wal_daily_tier_update_event')) {
            wp_schedule_event(time(), 'daily', 'wal_daily_tier_update_event');
        }
        add_action('wal_daily_tier_update_event', array($this, 'update_user_tiers_daily'));
    }

    /**
     * Award points to the user after a purchase.
     *
     * @param int $order_id The ID of the completed order.
     */
    public function award_points_for_purchase($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        $points_per_unit = get_option('wal_loyalty_points_per_currency_unit', 1);
        $total = $order->get_total();
        $points_earned = floor($total * $points_per_unit);

        if ($points_earned > 0) {
            $current_points = get_user_meta($user_id, 'wal_loyalty_points', true);
            $new_total_points = (int)$current_points + $points_earned;
            update_user_meta($user_id, 'wal_loyalty_points', $new_total_points);

            // Add a note to the order
            $order->add_order_note(sprintf(__('%d loyalty points awarded to the customer.', 'wp-affiliate-loyalty'), $points_earned));
        }
    }

    /**
     * Display the points redemption form on the cart page.
     */
    public function display_points_redemption_form() {
        if (is_user_logged_in() && is_cart()) {
            $current_user = wp_get_current_user();
            $points = get_user_meta($current_user->ID, 'wal_loyalty_points', true);
            $points_to_value_rate = get_option('wal_loyalty_points_to_value_rate', 100); // e.g., 100 points = 1 unit of currency

            if ($points > 0) {
                ?>
                <div class="wal-redeem-points-container">
                    <h3><?php _e('Redeem Your Loyalty Points', 'wp-affiliate-loyalty'); ?></h3>
                    <p><?php printf(__('You have %s points.', 'wp-affiliate-loyalty'), '<strong>' . $points . '</strong>'); ?></p>
                    <p><?php printf(__('Each %d points can be redeemed for a %s coupon.', 'wp-affiliate-loyalty'), $points_to_value_rate, wc_price(1)); ?></p>
                    <form id="wal-redeem-points-form">
                        <input type="number" name="points_to_redeem" id="points_to_redeem" min="1" max="<?php echo esc_attr($points); ?>" required>
                        <button type="submit"><?php _e('Redeem for Coupon', 'wp-affiliate-loyalty'); ?></button>
                        <?php wp_nonce_field('wal_redeem_points_nonce', 'wal_redeem_nonce'); ?>
                    </form>
                    <div id="wal-redeem-message"></div>
                </div>
                <script type="text/javascript">
                    jQuery(function($) {
                        $('#wal-redeem-points-form').on('submit', function(e) {
                            e.preventDefault();
                            var form = $(this);
                            var points = $('#points_to_redeem').val();
                            var nonce = $('#wal_redeem_nonce').val();

                            $.ajax({
                                type: 'POST',
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                data: {
                                    action: 'wal_redeem_points',
                                    points: points,
                                    nonce: nonce
                                },
                                success: function(response) {
                                    $('#wal-redeem-message').html(response.data.message);
                                    if (response.success) {
                                        setTimeout(function() {
                                            window.location.reload();
                                        }, 2000);
                                    }
                                }
                            });
                        });
                    });
                </script>
                <?php
            }
        }
    }

    /**
     * Handle the AJAX request for points redemption.
     */
    public function handle_points_redemption() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wal_redeem_points_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-affiliate-loyalty')));
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to redeem points.', 'wp-affiliate-loyalty')));
            return;
        }

        $user_id = get_current_user_id();
        $points_to_redeem = isset($_POST['points']) ? intval($_POST['points']) : 0;
        $current_points = get_user_meta($user_id, 'wal_loyalty_points', true);

        if ($points_to_redeem <= 0) {
            wp_send_json_error(array('message' => __('Please enter a valid number of points to redeem.', 'wp-affiliate-loyalty')));
            return;
        }

        if ($points_to_redeem > $current_points) {
            wp_send_json_error(array('message' => __('You do not have enough points to redeem.', 'wp-affiliate-loyalty')));
            return;
        }

        $points_to_value_rate = get_option('wal_loyalty_points_to_value_rate', 100);
        if ($points_to_value_rate <= 0) {
             wp_send_json_error(array('message' => __('Invalid point redemption rate configured.', 'wp-affiliate-loyalty')));
            return;
        }
        $coupon_amount = $points_to_redeem / $points_to_value_rate;

        // Create a new WooCommerce coupon
        $coupon_code = 'REDEEM_' . $user_id . '_' . time();
        $coupon = array(
            'post_title' => $coupon_code,
            'post_content' => '',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'shop_coupon'
        );

        $new_coupon_id = wp_insert_post($coupon);

        update_post_meta($new_coupon_id, 'discount_type', 'fixed_cart');
        update_post_meta($new_coupon_id, 'coupon_amount', $coupon_amount);
        update_post_meta($new_coupon_id, 'individual_use', 'yes');
        update_post_meta($new_coupon_id, 'usage_limit', '1');
        update_post_meta($new_coupon_id, 'usage_limit_per_user', '1');
        update_post_meta($new_coupon_id, 'expiry_date', ''); // No expiry
        update_post_meta($new_coupon_id, 'apply_before_tax', 'yes');
        update_post_meta($new_coupon_id, 'free_shipping', 'no');

        // Deduct points from user
        $new_points_total = $current_points - $points_to_redeem;
        update_user_meta($user_id, 'wal_loyalty_points', $new_points_total);

        // Apply the coupon to the cart automatically
        if (!wc_coupons_enabled()) {
             wp_send_json_success(array('message' => sprintf(__('Coupon %s created successfully! Please apply it manually.', 'wp-affiliate-loyalty'), $coupon_code)));
             return;
        }
        WC()->cart->apply_coupon($coupon_code);

        wp_send_json_success(array(
            'message' => sprintf(__('Success! %s coupon for %s has been created and applied to your cart.', 'wp-affiliate-loyalty'), $coupon_code, wc_price($coupon_amount))
        ));
    }


    /**
     * Award points for submitting an approved product review.
     */
    public function award_points_for_review($comment_id, $comment_approved) {
        if ($comment_approved === 1) { // Check if the comment is approved
            $comment = get_comment($comment_id);

            // Check if it's a product review
            if ($comment && get_post_type($comment->comment_post_ID) == 'product') {
                $user_id = $comment->user_id;

                if ($user_id) {
                    $points_for_review = get_option('wal_loyalty_points_for_review', 0);
                    if ($points_for_review > 0) {
                        $current_points = get_user_meta($user_id, 'wal_loyalty_points', true);
                        $new_total_points = (int)$current_points + $points_for_review;
                        update_user_meta($user_id, 'wal_loyalty_points', $new_total_points);

                        // Optional: Add a note to the user or admin
                        // For example, you could add a comment meta
                        add_comment_meta($comment_id, '_awarded_loyalty_points', $points_for_review);
                    }
                }
            }
        }
    }

    /**
     * Daily cron job to update user loyalty tiers.
     */
    public function update_user_tiers_daily() {
        $tiers = get_option('wal_loyalty_tiers', array());
        if (empty($tiers)) {
            return;
        }

        // Sort tiers by points required, descending
        usort($tiers, function ($a, $b) {
            return $b['points_required'] <=> $a['points_required'];
        });

        $users = get_users(array('fields' => array('ID')));

        foreach ($users as $user) {
            $user_id = $user->ID;
            $points = get_user_meta($user_id, 'wal_loyalty_points', true);
            $current_tier_id = get_user_meta($user_id, 'wal_loyalty_tier_id', true);
            $new_tier_id = 0; // Default to no tier

            foreach ($tiers as $tier) {
                if ($points >= $tier['points_required']) {
                    $new_tier_id = $tier['id'];
                    break; // Since tiers are sorted, the first match is the correct one
                }
            }

            if ($new_tier_id != $current_tier_id) {
                update_user_meta($user_id, 'wal_loyalty_tier_id', $new_tier_id);
            }
        }
    }
}
