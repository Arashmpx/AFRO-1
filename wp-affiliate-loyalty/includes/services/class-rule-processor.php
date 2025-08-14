<?php
/**
 * The Rule Processor Service
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/modules/affiliate
 */

/**
 * The class responsible for evaluating rules against an object (e.g., a WC_Order).
 *
 * @author     Jules
 */
class Rule_Processor {

    private $order;
    private $rules;

    public function __construct( $order, $rules ) {
        $this->order = $order;
        $this->rules = $rules;
    }

    public function evaluate() {
        foreach ( $this->rules as $rule ) {
            if ( $this->check_conditions( $rule ) ) {
                return $this->apply_actions( $rule );
            }
        }
        return 0.0;
    }

    private function check_conditions( $rule ) {
        $conditions = json_decode( $rule->conditions_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }

        foreach ( $conditions as $condition => $value ) {
            switch ( $condition ) {
                case 'min_total':
                    if ( $this->order->get_total() < (float) $value ) {
                        return false;
                    }
                    break;
                case 'products_in_cart':
                    $product_ids_in_order = array_map( function( $item ) {
                        return $item->get_product_id();
                    }, $this->order->get_items() );

                    $required_product_ids = array_map( 'absint', (array) $value );

                    if ( count( array_intersect( $product_ids_in_order, $required_product_ids ) ) === 0 ) {
                        return false;
                    }
                    break;
                case 'categories_in_cart':
                    $category_ids_in_order = array();
                    foreach( $this->order->get_items() as $item ) {
                        $product_id = $item->get_product_id();
                        $term_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
                        if ( ! is_wp_error( $term_ids ) ) {
                            $category_ids_in_order = array_merge( $category_ids_in_order, $term_ids );
                        }
                    }

                    $required_category_ids = array_map( 'absint', (array) $value );

                    if ( count( array_intersect( array_unique( $category_ids_in_order ), $required_category_ids ) ) === 0 ) {
                        return false;
                    }
                    break;
                case 'is_first_purchase':
                    if ( empty( $value ) ) {
                        break;
                    }
                    $customer_id = $this->order->get_customer_id();
                    if ( ! $customer_id ) {
                        break; // Guest checkout, treat as a first purchase.
                    }
                    $order_query = new WC_Order_Query( array(
                        'customer_id' => $customer_id,
                        'status' => 'completed',
                        'limit' => 1,
                        'exclude' => array( $this->order->get_id() ),
                    ) );
                    if ( ! empty( $order_query->get_orders() ) ) {
                        return false; // Found another completed order, so this isn't the first.
                    }
                    break;
                case 'user_role':
                    $customer_id = $this->order->get_customer_id();
                    if ( ! $customer_id ) {
                        return false; // Condition requires a specific role, so guest users fail.
                    }
                    $user = get_userdata( $customer_id );
                    if ( ! $user || empty( $user->roles ) ) {
                        return false; // User not found or has no roles.
                    }
                    if ( ! in_array( (string) $value, $user->roles, true ) ) {
                        return false;
                    }
                    break;
                // Add other conditions here in the future.
            }
        }
        return true;
    }

    /**
     * Apply the actions of a rule to calculate the commission.
     *
     * @param object $rule The rule object.
     * @return float The calculated commission amount.
     */
    private function apply_actions( $rule ) {
        $actions = json_decode( $rule->actions_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $actions['type'] ) || ! isset( $actions['value'] ) ) {
            return 0.0;
        }

        $result = 0.0;
        $action_type = $actions['type'];
        $action_value = $actions['value'];

        switch ( $action_type ) {
            case 'percentage':
                $result = $this->order->get_total() * ( (float) $action_value / 100 );
                break;
            case 'percentage_profit':
                $total_profit = 0;
                foreach ( $this->order->get_items() as $item ) {
                    $product_or_variation_id = $item->get_variation_id() ?: $item->get_product_id();
                    $cost = get_post_meta( $product_or_variation_id, '_wc_cog_cost', true );

                    if ( '' !== $cost ) {
                        $total_profit += $item->get_total() - ( (float) $cost * $item->get_quantity() );
                    } else {
                        $total_profit += $item->get_total();
                    }
                }
                $result = $total_profit * ( (float) $action_value / 100 );
                break;
            case 'fixed':
                $result = (float) $action_value;
                break;
            case 'points':
                $result = (float) $action_value;
                break;
            case 'points_per_currency_unit':
                if ( ! is_array( $action_value ) || ! isset( $action_value['points'] ) || ! isset( $action_value['per_amount'] ) ) {
                    break;
                }
                $points_to_award = (float) $action_value['points'];
                $per_amount = (float) $action_value['per_amount'];
                if ( $per_amount <= 0 ) {
                    break;
                }
                $result = floor( $this->order->get_total() / $per_amount ) * $points_to_award;
                break;
            default:
                $result = 0.0;
                break;
        }

        return round( $result );
    }
}
