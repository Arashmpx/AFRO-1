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

        $commission_amount = 0.0;
        $value = (float) $actions['value'];

        switch ( $actions['type'] ) {
            case 'percentage':
                $commission_amount = $this->order->get_total() * ( $value / 100 );
                break;

            case 'fixed':
                $commission_amount = $value;
                break;
        }

        // The spec uses IRR which has no decimals, so we can round.
        return round( $commission_amount );
    }
}
