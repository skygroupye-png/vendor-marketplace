<?php
namespace VMP\Database\Migrations;

defined('ABSPATH') || exit;

/**
 * Migration: Add vendor approval columns and history table.
 * - Idempotent: safe to run multiple times.
 */
class V2_AddVendorApprovalColumns
{
    public static function up(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $vendors_table = $wpdb->prefix . 'vmp_vendors';
        $history_table = $wpdb->prefix . 'vmp_vendor_history';

        // 1) Add columns to vendors table if not exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$vendors_table}", ARRAY_A);
        $existing = array_column($columns, 'Field');

        $queries = [];
        if (!in_array('status', $existing, true)) {
            $queries[] = "ALTER TABLE {$vendors_table} ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending'";
        }
        if (!in_array('approved_at', $existing, true)) {
            $queries[] = "ALTER TABLE {$vendors_table} ADD COLUMN approved_at DATETIME NULL";
        }
        if (!in_array('approved_by', $existing, true)) {
            $queries[] = "ALTER TABLE {$vendors_table} ADD COLUMN approved_by BIGINT NULL";
        }
        if (!in_array('rejected_at', $existing, true)) {
            $queries[] = "ALTER TABLE {$vendors_table} ADD COLUMN rejected_at DATETIME NULL";
        }
        if (!in_array('rejected_by', $existing, true)) {
            $queries[] = "ALTER TABLE {$vendors_table} ADD COLUMN rejected_by BIGINT NULL";
        }
        if (!in_array('reject_reason', $existing, true)) {
            $queries[] = "ALTER TABLE {$vendors_table} ADD COLUMN reject_reason TEXT NULL";
        }

        foreach ($queries as $q) {
            $wpdb->query($q);
        }

        // 2) Create history table if not exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$history_table}'") !== $history_table) {
            $create = "CREATE TABLE {$history_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                vendor_id BIGINT(20) NOT NULL,
                action VARCHAR(64) NOT NULL,
                performed_by BIGINT(20) NULL,
                reason TEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX (vendor_id)
            ) {$charset_collate};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($create);
        }
    }

    public static function down(): void
    {
        // Not implementing down migration to avoid accidental data loss.
    }
}
