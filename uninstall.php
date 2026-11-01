<?php
/**
 * Uninstall handler for FBS Secure Optimize.
 *
 * Removes plugin options, cached transients, scheduled events, and custom login lockout table when uninstalled.
 *
 * @package FBS_Secure_Optimize
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$fbsseop_salt = get_option( 'fbsseop_cache_salt', 'v1' );
delete_transient( 'fbsseop_img_dims_' . $fbsseop_salt );
delete_transient( 'fbsseop_wp_checksums_' . get_bloginfo( 'version' ) . '_' . get_locale() );

delete_option( 'fbsseop_settings' );
delete_option( 'fbsseop_db_version' );
delete_option( 'fbsseop_cache_salt' );
delete_option( 'fbsseop_last_scan_summary' );

wp_clear_scheduled_hook( 'fbsseop_cleanup_database' );

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup of custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fbsseop_login_attempts" );
