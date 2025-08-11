<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class to display rules in a WP_List_Table.
 *
 * @since 1.0.0
 */
class Rules_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Rule', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'plural'   => __( 'Rules', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox" />',
            'name'       => __( 'Rule Name', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'module'     => __( 'Module', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'precedence' => __( 'Precedence', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'active'     => __( 'Status', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
            'created_at' => __( 'Date', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ),
        );
    }

    public function get_sortable_columns() {
        return array(
            'name'       => array( 'name', false ),
            'module'     => array( 'module', false ),
            'precedence' => array( 'precedence', false ),
            'active'     => array( 'active', false ),
            'created_at' => array( 'created_at', true ),
        );
    }

    public function prepare_items() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aff_loyalty_rules';
        $per_page   = 20;

        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'precedence';
        $order   = ( ! empty( $_GET['order'] ) ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'ASC';

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

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'module':
            case 'precedence':
                return ucfirst( $item->$column_name );
            case 'created_at':
                return date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) );
            default:
                return '';
        }
    }

    public function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="rule_id[]" value="%s" />', $item->id );
    }

    public function column_active( $item ) {
        return $item->active ? __( 'Active', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) : __( 'Inactive', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN );
    }

    public function column_name( $item ) {
        $page = $_REQUEST['page'];
        $edit_url = add_query_arg( array( 'action' => 'edit', 'rule_id' => $item->id ), admin_url( 'admin.php?page=' . $page ) );
        $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'rule_id' => $item->id ), admin_url( 'admin.php?page=' . $page ) ), 'aff_loyalty_delete_rule' );

        $actions = array(
            'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), __( 'Edit', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) ),
            'delete' => sprintf( '<a href="%s" style="color: #a00;">%s</a>', esc_url( $delete_url ), __( 'Delete', WP_AFFILIATE_LOYALTY_TEXT_DOMAIN ) ),
        );

        return sprintf( '<strong>%s</strong>%s', esc_html( $item->name ), $this->row_actions( $actions ) );
    }
}
