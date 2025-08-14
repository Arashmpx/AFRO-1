<?php
/**
 * View for the Add/Edit Rule form.
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/admin/views
 * @author     Jules
 */

// The $rule object and $is_edit boolean are expected to be set by the calling method.
?>
<div class="wrap">
    <h1><?php echo $is_edit ? esc_html__( 'Edit Rule', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) : esc_html__( 'Add New Rule', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="save_affiliate_loyalty_rule" />
        <input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule->id ); ?>" />
        <?php wp_nonce_field( 'save_rule_nonce' ); ?>

        <table class="form-table">
            <tbody>
                <!-- Rule Name -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="name"><?php esc_html_e( 'Rule Name', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="name" id="name" value="<?php echo esc_attr( $rule->name ); ?>" required="required" style="width: 100%; max-width: 400px;"/>
                        <p class="description"><?php esc_html_e( 'A descriptive name for the rule.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Module -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="module"><?php esc_html_e( 'Module', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <select name="module" id="module">
                            <option value="affiliate" <?php selected( $rule->module, 'affiliate' ); ?>><?php esc_html_e( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                            <option value="loyalty" <?php selected( $rule->module, 'loyalty' ); ?>><?php esc_html_e( 'Loyalty', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select which system this rule applies to.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Conditions -->
                <tr class="form-field">
                    <th scope="row" colspan="2">
                        <h3><?php esc_html_e( 'Conditions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
                        <p class="description"><?php esc_html_e( 'The rule will only apply if all of the following conditions are met. Leave a field blank to ignore that condition.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </th>
                </tr>

                <?php $conditions = json_decode( $rule->conditions_json, true ); ?>

                <!-- Condition: Minimum Total -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="condition_min_total"><?php esc_html_e( 'Minimum Order Total', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="conditions[min_total]" id="condition_min_total" value="<?php echo esc_attr( $conditions['min_total'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., 100000', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>" />
                        <p class="description"><?php esc_html_e( 'The minimum total amount of the order for this rule to apply.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Condition: Specific Products -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="condition_products_in_cart"><?php esc_html_e( 'Specific Products', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="conditions[products_in_cart]" id="condition_products_in_cart" value="<?php echo esc_attr( isset($conditions['products_in_cart']) ? implode(', ', (array)$conditions['products_in_cart']) : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., 123, 456', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>" style="width: 100%; max-width: 400px;"/>
                        <p class="description"><?php esc_html_e( 'Enter a comma-separated list of product IDs. The rule will only apply if at least one of these products is in the cart.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Condition: Specific Categories -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="condition_categories_in_cart"><?php esc_html_e( 'Specific Categories', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="conditions[categories_in_cart]" id="condition_categories_in_cart" value="<?php echo esc_attr( isset($conditions['categories_in_cart']) ? implode(', ', (array)$conditions['categories_in_cart']) : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g., 15, 20', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>" style="width: 100%; max-width: 400px;"/>
                        <p class="description"><?php esc_html_e( 'Enter a comma-separated list of category IDs. The rule will only apply if a product from at least one of these categories is in the cart.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Condition: First Purchase -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="condition_is_first_purchase"><?php esc_html_e( 'First Purchase Only', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="conditions[is_first_purchase]" id="condition_is_first_purchase" value="1" <?php checked( $conditions['is_first_purchase'] ?? 0, 1 ); ?> />
                            <?php esc_html_e( 'Apply this rule only if it is the customer\'s first completed order.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>
                        </label>
                    </td>
                </tr>

                <!-- Condition: User Role -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="condition_user_role"><?php esc_html_e( 'User Role', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <select name="conditions[user_role]" id="condition_user_role" style="width: 100%; max-width: 400px;">
                            <option value=""><?php esc_html_e( '-- Any Role --', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                            <?php
                            $roles = get_editable_roles();
                            foreach ( $roles as $role_id => $role_info ) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr( $role_id ),
                                    selected( $conditions['user_role'] ?? '', $role_id, false ),
                                    esc_html( $role_info['name'] )
                                );
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Apply this rule only to users with the selected role.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Actions -->
                <tr class="form-field">
                    <th scope="row" colspan="2">
                        <h3><?php esc_html_e( 'Action', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></h3>
                        <p class="description"><?php esc_html_e( 'The action to perform when the conditions are met.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </th>
                </tr>

                <?php $actions = json_decode( $rule->actions_json, true ); ?>

                <tr class="form-field">
                    <th scope="row">
                        <label for="action_type"><?php esc_html_e( 'Action Type', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <select name="actions[type]" id="action_type" style="width: 100%; max-width: 400px;">
                            <option value="percentage" <?php selected( $actions['type'] ?? '', 'percentage' ); ?>><?php esc_html_e( 'Percentage of Order Total', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                            <option value="percentage_profit" <?php selected( $actions['type'] ?? '', 'percentage_profit' ); ?>><?php esc_html_e( 'Percentage of Order Profit', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                            <option value="fixed" <?php selected( $actions['type'] ?? '', 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                            <option value="points" <?php selected( $actions['type'] ?? '', 'points' ); ?>><?php esc_html_e( 'Fixed Points (for Loyalty)', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                            <option value="points_per_currency_unit" <?php selected( $actions['type'] ?? '', 'points_per_currency_unit' ); ?>><?php esc_html_e( 'Points per Currency Unit Spent (for Loyalty)', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></option>
                        </select>
                    </td>
                </tr>

                <!-- Simple Value Field -->
                <tr class="form-field" id="action_value_field">
                    <th scope="row">
                        <label for="action_value"><?php esc_html_e( 'Value', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="number" step="any" name="actions[value]" id="action_value" value="<?php echo esc_attr( is_array($actions['value']) ? '' : ($actions['value'] ?? '') ); ?>" style="width: 100px;"/>
                        <p class="description"><?php esc_html_e( 'The numeric value for the action.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Complex Value Fields for Points per Currency -->
                <tr class="form-field" id="action_points_per_currency_fields" style="display:none;">
                    <th scope="row">
                        <label><?php esc_html_e( 'Value', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <p>
                            <?php esc_html_e( 'Award', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>
                            <input type="number" step="any" name="actions[value_complex][points]" value="<?php echo esc_attr( $actions['value']['points'] ?? '' ); ?>" style="width: 80px;" />
                            <?php esc_html_e( 'points for every', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>
                            <input type="number" step="any" name="actions[value_complex][per_amount]" value="<?php echo esc_attr( $actions['value']['per_amount'] ?? '' ); ?>" style="width: 80px;" />
                            <?php esc_html_e( 'spent.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?>
                        </p>
                    </td>
                </tr>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const actionTypeSelect = document.getElementById('action_type');
                    const simpleValueField = document.getElementById('action_value_field');
                    const complexFields = document.getElementById('action_points_per_currency_fields');

                    function toggleActionFields() {
                        if (actionTypeSelect.value === 'points_per_currency_unit') {
                            simpleValueField.style.display = 'none';
                            complexFields.style.display = 'table-row';
                        } else {
                            simpleValueField.style.display = 'table-row';
                            complexFields.style.display = 'none';
                        }
                    }

                    actionTypeSelect.addEventListener('change', toggleActionFields);
                    toggleActionFields(); // Run on page load
                });
                </script>

                <!-- Precedence -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="precedence"><?php esc_html_e( 'Precedence', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="precedence" id="precedence" value="<?php echo esc_attr( $rule->precedence ); ?>" style="width: 100px;" />
                        <p class="description"><?php esc_html_e( 'Lower numbers run first. Rules are checked in order of precedence.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

                <!-- Active -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="active"><?php esc_html_e( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <label><input type="checkbox" name="active" id="active" value="1" <?php checked( $rule->active, 1 ); ?> /> <?php esc_html_e( 'Active', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                        <p class="description"><?php esc_html_e( 'Only active rules will be processed.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                    </td>
                </tr>

            </tbody>
        </table>

        <?php submit_button( $is_edit ? __( 'Update Rule', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) : __( 'Add Rule', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) ); ?>
    </form>
</div>
