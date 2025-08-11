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
                    <th scope="row">
                        <label for="conditions_json"><?php esc_html_e( 'Conditions (JSON)', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <textarea name="conditions_json" id="conditions_json" rows="5" style="width: 100%; max-width: 600px;"><?php echo esc_textarea( $rule->conditions_json ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Conditions for the rule to be applied, in JSON format.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                        <code><?php esc_html_e( 'Example: {"min_total": 100000, "user_role": "customer"}', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></code>
                    </td>
                </tr>

                <!-- Actions -->
                <tr class="form-field">
                    <th scope="row">
                        <label for="actions_json"><?php esc_html_e( 'Actions (JSON)', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></label>
                    </th>
                    <td>
                        <textarea name="actions_json" id="actions_json" rows="5" style="width: 100%; max-width: 600px;"><?php echo esc_textarea( $rule->actions_json ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Actions to perform if conditions are met, in JSON format.', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></p>
                        <code><?php esc_html_e( 'Example (Affiliate): {"type": "percentage", "value": 10}', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></code><br>
                        <code><?php esc_html_e( 'Example (Loyalty): {"type": "points", "value": 100}', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ); ?></code>
                    </td>
                </tr>

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
