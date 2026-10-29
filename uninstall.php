<?php
/**
 * Uninstall handler for FBS Secure Optimize.
 *
 * Removes plugin options, scheduled events, and custom login lockout table when uninstalled.
 *
 * @package FBS_Secure_Optimize
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'fbsseop_settings' );
delete_option( 'fbsseop_db_version' );
delete_option( 'fbsseop_last_scan_summary' );

wp_clear_scheduled_hook( 'fbsseop_cleanup_database' );

global $wpdb;
$fbsseop_table = $wpdb->prefix . 'fbsseop_login_attempts';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall cleanup of custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$fbsseop_table}" );
