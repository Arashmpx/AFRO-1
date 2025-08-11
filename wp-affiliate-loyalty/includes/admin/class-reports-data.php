<?php
/**
 * Handles fetching and calculating data for reports.
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes/admin
 * @author     Jules
 */
class Reports_Data {

    /**
     * Get key stats for the affiliate module.
     *
     * @return array
     */
    public static function get_affiliate_stats() {
        global $wpdb;
        $stats = array(
            'total_clicks' => 0,
            'total_commissions' => 0,
            'paid_commissions_value' => 0,
            'unpaid_commissions_value' => 0,
            'conversion_rate' => 0,
        );

        $clicks_table = $wpdb->prefix . 'aff_loyalty_clicks';
        $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';

        $stats['total_clicks'] = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$clicks_table}" );
        $stats['total_commissions'] = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$commissions_table}" );

        $stats['paid_commissions_value'] = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$commissions_table} WHERE status = 'paid'" );
        $stats['unpaid_commissions_value'] = (float) $wpdb->get_var( "SELECT SUM(amount) FROM {$commissions_table} WHERE status = 'pending'" );

        if ( $stats['total_clicks'] > 0 ) {
            $stats['conversion_rate'] = ( $stats['total_commissions'] / $stats['total_clicks'] ) * 100;
        }

        return $stats;
    }

    /**
     * Get key stats for the loyalty module.
     *
     * @return array
     */
    public static function get_loyalty_stats() {
        global $wpdb;
        $stats = array(
            'points_awarded' => 0,
            'points_redeemed' => 0,
            'redemption_rate' => 0,
        );

        $points_table = $wpdb->prefix . 'aff_loyalty_points';

        $stats['points_awarded'] = (int) $wpdb->get_var( "SELECT SUM(points) FROM {$points_table} WHERE status IN ('active', 'redeemed', 'expired')" );
        $stats['points_redeemed'] = (int) $wpdb->get_var( "SELECT SUM(points) FROM {$points_table} WHERE status = 'redeemed'" );

        if ( $stats['points_awarded'] > 0 ) {
            $stats['redemption_rate'] = ( $stats['points_redeemed'] / $stats['points_awarded'] ) * 100;
        }

        return $stats;
    }

    /**
     * Get commission data for the last 30 days for charting.
     */
    public static function get_commissions_by_day() {
        global $wpdb;
        $commissions_table = $wpdb->prefix . 'aff_loyalty_commissions';
        $data = $wpdb->get_results(
            "SELECT DATE(created_at) as date, SUM(amount) as total
            FROM {$commissions_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC"
        );
        return $data;
    }

    /**
     * Get points data for the last 30 days for charting.
     */
    public static function get_points_by_day() {
        global $wpdb;
        $points_table = $wpdb->prefix . 'aff_loyalty_points';
        $data = $wpdb->get_results(
            "SELECT DATE(created_at) as date, SUM(points) as total
            FROM {$points_table}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC"
        );
        return $data;
    }
}
