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

// Remove static CSS & page cache files inside uploads/fbs-optimize-cache.
$fbsseop_uploads = wp_upload_dir( null, false );
if ( is_array( $fbsseop_uploads ) && empty( $fbsseop_uploads['error'] ) && ! empty( $fbsseop_uploads['basedir'] ) ) {
	$fbsseop_cache_root = trailingslashit( $fbsseop_uploads['basedir'] ) . 'fbs-optimize-cache';
	if ( is_dir( $fbsseop_cache_root ) ) {
		$fbsseop_iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $fbsseop_cache_root, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $fbsseop_iter as $fbsseop_item ) {
			if ( $fbsseop_item->isDir() ) {
				@rmdir( $fbsseop_item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
			} else {
				wp_delete_file( $fbsseop_item->getPathname() );
			}
		}
		@rmdir( $fbsseop_cache_root ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Remove all plugin-prefixed transients on uninstall.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_fbsseop_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_fbsseop_' ) . '%'
	)
);
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup of custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fbsseop_login_attempts" );
