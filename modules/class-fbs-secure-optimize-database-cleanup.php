<?php
/**
 * Database & Autoload Cleanup Module for FBS Secure Optimize.
 *
 * @package FBS_Secure_Optimize
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles manual and WP-Cron scheduled database bloat cleanup and table optimization.
 *
 * @since 1.0.0
 */
class FBS_Secure_Optimize_Database_Cleanup {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var FBS_Secure_Optimize_Database_Cleanup|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 * @return FBS_Secure_Optimize_Database_Cleanup
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register cron schedules and cleanup hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'cron_schedules', array( $this, 'register_custom_schedules' ) );
		add_action( 'fbsseop_cleanup_database', array( $this, 'perform_cleanup' ) );
		add_action( 'init', array( $this, 'sync_cron_schedule' ) );
	}

	/**
	 * Ensure weekly and monthly intervals exist across all WordPress versions.
	 *
	 * @since 2.0.0
	 * @param array<string, array<string, mixed>> $schedules Existing cron schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_custom_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'fbs-secure-optimize' ),
			);
		}
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => 30 * DAY_IN_SECONDS,
				'display'  => __( 'Once Monthly', 'fbs-secure-optimize' ),
			);
		}
		return $schedules;
	}

	/**
	 * Keep WP-Cron event synchronized with the auto_cleanup setting.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function sync_cron_schedule() {
		$auto_enabled = FBS_Secure_Optimize_Controller::is_feature_enabled( 'database_cleanup', 'auto_cleanup' );
		$next_run     = wp_next_scheduled( 'fbsseop_cleanup_database' );

		if ( ! $auto_enabled ) {
			if ( $next_run ) {
				wp_clear_scheduled_hook( 'fbsseop_cleanup_database' );
			}
			return;
		}

		if ( ! $next_run ) {
			$frequency = (string) FBS_Secure_Optimize_Controller::get_setting( 'database_cleanup', 'cleanup_frequency', 'weekly' );
			$allowed   = array( 'daily', 'weekly', 'monthly' );
			if ( ! in_array( $frequency, $allowed, true ) ) {
				$frequency = 'weekly';
			}
			wp_schedule_event( time() + HOUR_IN_SECONDS, $frequency, 'fbsseop_cleanup_database' );
		}
	}

	/**
	 * Execute database cleanup according to active options.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|null $options Optional override settings.
	 * @return array<string, int>
	 */
	public function perform_cleanup( $options = null ) {
		global $wpdb;

		$results = array(
			'post_revisions'   => 0,
			'auto_drafts'      => 0,
			'spam_comments'    => 0,
			'transients'       => 0,
			'orphaned_meta'    => 0,
			'optimized_tables' => 0,
		);

		if ( null === $options ) {
			$all     = FBS_Secure_Optimize_Controller::get_all_settings();
			$options = isset( $all['database_cleanup'] ) ? $all['database_cleanup'] : array();
		}

		// 1. Post Revisions (batched up to 500 per run for safe memory footprint).
		if ( ! empty( $options['cleanup_revisions'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance query.
			$revision_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' LIMIT 500" );
			if ( is_array( $revision_ids ) ) {
				foreach ( $revision_ids as $rev_id ) {
					if ( wp_delete_post_revision( (int) $rev_id ) ) {
						$results['post_revisions']++;
					}
				}
			}
		}

		// 2. Old Auto-Drafts (> 7 days).
		if ( ! empty( $options['cleanup_autodrafts'] ) ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance query.
			$draft_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date < %s LIMIT 300",
					$cutoff
				)
			);
			if ( is_array( $draft_ids ) ) {
				foreach ( $draft_ids as $draft_id ) {
					if ( wp_delete_post( (int) $draft_id, true ) ) {
						$results['auto_drafts']++;
					}
				}
			}
		}

		// 3. Spam & Trashed Comments.
		if ( ! empty( $options['cleanup_spam_comments'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance query.
			$comment_ids = $wpdb->get_col(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash') LIMIT 500"
			);
			if ( is_array( $comment_ids ) ) {
				foreach ( $comment_ids as $cid ) {
					if ( wp_delete_comment( (int) $cid, true ) ) {
						$results['spam_comments']++;
					}
				}
			}
		}

		// 4. Expired Transients (uses native WP 4.9+ delete_expired_transients when available).
		if ( ! empty( $options['cleanup_transients'] ) ) {
			if ( function_exists( 'delete_expired_transients' ) ) {
				delete_expired_transients( true );
			}
			$now = time();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Expired transient cleanup.
			$expired = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options}
					 WHERE option_name LIKE %s
					   AND CAST(option_value AS UNSIGNED) > 0
					   AND CAST(option_value AS UNSIGNED) < %d
					 LIMIT 500",
					$wpdb->esc_like( '_transient_timeout_' ) . '%',
					$now
				)
			);
			if ( is_array( $expired ) ) {
				foreach ( $expired as $timeout_opt ) {
					$transient_key = str_replace( '_transient_timeout_', '', (string) $timeout_opt );
					if ( delete_transient( $transient_key ) ) {
						$results['transients']++;
					}
				}
			}
		}

		// 5. Orphaned Postmeta.
		if ( ! empty( $options['cleanup_orphaned_meta'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Orphaned meta cleanup.
			$deleted_meta = $wpdb->query(
				"DELETE pm FROM {$wpdb->postmeta} pm
				 LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.ID IS NULL"
			);
			$results['orphaned_meta'] = max( 0, (int) $deleted_meta );
		}

		// 6. Optimize core WordPress tables.
		if ( ! empty( $options['optimize_tables'] ) ) {
			$core_tables = array(
				$wpdb->posts,
				$wpdb->postmeta,
				$wpdb->options,
				$wpdb->comments,
				$wpdb->commentmeta,
				$wpdb->usermeta,
			);
			foreach ( $core_tables as $table ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core $wpdb table property.
				$wpdb->query( "OPTIMIZE TABLE {$table}" );
				$results['optimized_tables']++;
			}
		}

		FBS_Secure_Optimize_Controller::clear_stats_cache();

		return $results;
	}
}
