<?php

/**
 * Fired during plugin activation
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WP_Affiliate_Loyalty
 * @subpackage WP_Affiliate_Loyalty/includes
 * @author     Jules
 */
class WP_Affiliate_Loyalty_Installer {

    /**
     * The code that runs on plugin activation.
     * Creates the custom database tables.
     *
     * @since    1.0.0
     */
    public static function install() {
        global $wpdb;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $charset_collate = $wpdb->get_charset_collate();

        // Table prefixes from spec, but made more specific
        $tbl_aff_links = $wpdb->prefix . 'aff_loyalty_links';
        $tbl_aff_commissions = $wpdb->prefix . 'aff_loyalty_commissions';
        $tbl_aff_wallets = $wpdb->prefix . 'aff_loyalty_wallets';
        $tbl_loyalty_points = $wpdb->prefix . 'aff_loyalty_points';
        $tbl_rules = $wpdb->prefix . 'aff_loyalty_rules';
        $tbl_transaction_logs = $wpdb->prefix . 'aff_loyalty_transaction_logs';

        $sql = "
        CREATE TABLE $tbl_aff_links (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            url VARCHAR(255) NOT NULL,
            deep_link_target TEXT,
            token VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY affiliate_id (affiliate_id)
        ) $charset_collate;

        CREATE TABLE $tbl_aff_commissions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            affiliate_id BIGINT(20) UNSIGNED NOT NULL,
            level INT UNSIGNED NOT NULL DEFAULT 1,
            amount DECIMAL(15, 4) NOT NULL,
            status ENUM('pending', 'paid', 'held', 'refunded', 'rejected') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY affiliate_id (affiliate_id)
        ) $charset_collate;

        CREATE TABLE $tbl_aff_wallets (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            balance DECIMAL(15, 4) NOT NULL DEFAULT 0.0000,
            currency VARCHAR(10) NOT NULL DEFAULT 'IRR',
            last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;

        CREATE TABLE $tbl_loyalty_points (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            points INT NOT NULL,
            source VARCHAR(255) NOT NULL,
            expiry_date DATE DEFAULT NULL,
            status ENUM('active', 'redeemed', 'expired') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY expiry_date (expiry_date)
        ) $charset_collate;

        CREATE TABLE $tbl_rules (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            module ENUM('affiliate', 'loyalty') NOT NULL,
            conditions_json TEXT NOT NULL,
            actions_json TEXT NOT NULL,
            precedence INT NOT NULL DEFAULT 10,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;

        CREATE TABLE $tbl_transaction_logs (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(50) NOT NULL,
            entity_id BIGINT(20) UNSIGNED NOT NULL,
            change_amount DECIMAL(15, 4) NOT NULL,
            description TEXT,
            meta TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entity_type_id (entity_type, entity_id)
        ) $charset_collate;
        ";

        dbDelta( $sql );
    }
}
