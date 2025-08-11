<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Payouts_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Payout', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'plural'   => __( 'Payouts', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'cb'        => '<input type="checkbox" />',
            'user_id'   => __( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'balance'   => __( 'Balance', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'last_paid' => __( 'Last Payout', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
    }

    protected function get_bulk_actions() {
        return array(
            'process_payouts' => __( 'Process Selected Payouts', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
    }

    public function prepare_items() {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'aff_loyalty_wallets';

        $this->_column_headers = array( $this->get_columns(), array(), array() );

        // For this example, we'll get all users with a balance > 0
        // A minimum payout threshold could be added from settings.
        $this->items = $wpdb->get_results( "SELECT user_id, balance, last_updated FROM {$wallets_table} WHERE balance > 0" );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'balance':
                return number_format_i18n( $item->balance, 0 ) . ' ' . __( 'IRR', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
            case 'last_paid':
                 // This is just last_updated for now. A real last_paid date would need another field.
                return date_i18n( get_option( 'date_format' ), strtotime( $item->last_updated ) );
            default:
                return '';
        }
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="user_ids[]" value="%s" />', $item->user_id );
    }

    public function column_user_id( $item ) {
        $user = get_userdata( $item->user_id );
        if ( ! $user ) return __( 'Unknown User', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        $actions = array(
            'pay' => sprintf( '<a href="#">%s</a>', __( 'Pay Now', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) )
        );
        return sprintf( '<strong>%s</strong>%s', esc_html( $user->display_name ), $this->row_actions( $actions ) );
    }
}
