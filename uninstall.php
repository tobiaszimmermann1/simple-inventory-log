<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes the inventory-log database table and any transients created by
 * the plugin.  User data in other tables is never touched.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the log table.
$table_name = $wpdb->prefix . 'inventory_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Remove any leftover transients (best-effort; they expire on their own too).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sil_%' OR option_name LIKE '_transient_timeout_sil_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
