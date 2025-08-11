<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class to display commissions in a WP_List_Table.
 *
 * @since 1.0.0
 */
class Commissions_List_Table extends WP_List_Table {

    /**
     * Commissions_List_Table constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Commission', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'plural'   => __( 'Commissions', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'ajax'     => false,
        ) );
    }

    /**
     * Get the list of columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'           => '<input type="checkbox" />',
            'order_id'     => __( 'Order ID', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'affiliate_id' => __( 'Affiliate', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'amount'       => __( 'Amount', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'status'       => __( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'created_at'   => __( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
    }

    /**
     * Get the list of sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'order_id'     => array( 'order_id', false ),
            'affiliate_id' => array( 'affiliate_id', false ),
            'amount'       => array( 'amount', false ),
            'status'       => array( 'status', false ),
            'created_at'   => array( 'created_at', true ), // Default sort
        );
    }

    /**
     * Prepare the items for the table.
     */
    public function prepare_items() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aff_loyalty_commissions';
        $per_page   = 20;

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'created_at';
        $order   = ( ! empty( $_GET['order'] ) ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'DESC';

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name}" );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ) );

        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );
    }

    /**
     * Default column rendering.
     *
     * @param object $item
     * @param string $column_name
     * @return mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'amount':
                return number_format_i18n( $item->amount, 0 ) . ' ' . __( 'IRR', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
            case 'status':
                return ucfirst( $item->status );
            case 'created_at':
                return date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) );
            default:
                return print_r( $item, true ); // For debugging
        }
    }

    /**
     * Render the checkbox column.
     *
     * @param object $item
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="commission_id[]" value="%s" />', $item->id );
    }

    /**
     * Render the order ID column with a link to the order.
     *
     * @param object $item
     * @return string
     */
    public function column_order_id( $item ) {
        $url = admin_url( 'post.php?post=' . $item->order_id . '&action=edit' );
        $output = sprintf( '<a href="%s">#%d</a>', esc_url( $url ), $item->order_id );

        // Add row actions
        $actions = array();
        if ( 'pending' === $item->status ) {
            $mark_paid_url = wp_nonce_url( add_query_arg( array(
                'page'   => $_REQUEST['page'],
                'action' => 'mark_paid',
                'commission_id' => $item->id,
            ) ), 'aff_loyalty_mark_paid' );

            $actions['mark_paid'] = sprintf( '<a href="%s">%s</a>', esc_url( $mark_paid_url ), __( 'Mark as Paid', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) );
        }

        return $output . $this->row_actions( $actions );
    }

    /**
     * Render the affiliate column with a link to the user's profile.
     *
     * @param object $item
     * @return string
     */
    public function column_affiliate_id( $item ) {
        $user = get_userdata( $item->affiliate_id );
        if ( ! $user ) {
            return __( 'Unknown User', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
        }
        $url = admin_url( 'user-edit.php?user_id=' . $item->affiliate_id );
        return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $user->display_name ) );
    }
}
