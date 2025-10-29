<?php
/**
 * Database Cleanup module for FBS Secure Optimize plugin
 *
 * @package FBS_Optimize
 * @since 1.0.0
 * @author Fazle Bari <fazlebarisn@gmail.com>
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Cleanup class
 */
class FBS_Secure_Optimize_Database_Cleanup {

    /**
     * Single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @var FBS_Secure_Optimize_Database_Cleanup
     */
    private static $instance = null;

    /**
     * Get single instance of the class
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return FBS_Secure_Optimize_Database_Cleanup
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    private function init_hooks() {
        add_action('wp_scheduled_delete', array($this, 'scheduled_cleanup'));
        add_action('fbsseop_cleanup_database', array($this, 'perform_cleanup'));
    }

    /**
     * Perform database cleanup based on settings
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param array $options Cleanup options (optional)
     * @return array Cleanup results
     */
    public function perform_cleanup($options = null) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        $results = array(
            'post_revisions' => 0,
            'auto_drafts' => 0,
            'spam_comments' => 0,
            'transients' => 0,
            'orphaned_meta' => 0,
            'orphaned_relationships' => 0,
            'duplicate_meta' => 0,
            'optimized_tables' => 0,
        );

        // Get settings if options not provided
        if ($options === null) {
            $settings = get_option('fbsseop_settings', array());
            $cleanup_settings = isset($settings['database_cleanup']) ? $settings['database_cleanup'] : array();
        } else {
            $cleanup_settings = $options;
        }

        try {
            // Clean post revisions
            if (isset($cleanup_settings['cleanup_revisions']) && $cleanup_settings['cleanup_revisions']) {
                $results['post_revisions'] = $this->cleanup_post_revisions();
            }

            // Clean auto-drafts
            if (isset($cleanup_settings['cleanup_autodrafts']) && $cleanup_settings['cleanup_autodrafts']) {
                $results['auto_drafts'] = $this->cleanup_auto_drafts();
            }

            // Clean spam comments
            if (isset($cleanup_settings['cleanup_spam_comments']) && $cleanup_settings['cleanup_spam_comments']) {
                $results['spam_comments'] = $this->cleanup_spam_comments();
            }

            // Clean transients
            if (isset($cleanup_settings['cleanup_transients']) && $cleanup_settings['cleanup_transients']) {
                $results['transients'] = $this->cleanup_transients();
            }

            // Clean orphaned post meta
            $results['orphaned_meta'] = $this->cleanup_orphaned_post_meta();

            // Clean orphaned relationships
            $results['orphaned_relationships'] = $this->cleanup_orphaned_relationships();

            // Clean duplicate meta
            $results['duplicate_meta'] = $this->cleanup_duplicate_meta();

            // Optimize database tables
            $results['optimized_tables'] = $this->optimize_database_tables();

            // Log cleanup results
            FBS_Secure_Optimize_Controller::log(
                sprintf(
                    'Database cleanup completed: %d revisions, %d auto-drafts, %d spam comments, %d transients, %d orphaned meta, %d orphaned relationships, %d duplicate meta, %d optimized tables',
                    $results['post_revisions'],
                    $results['auto_drafts'],
                    $results['spam_comments'],
                    $results['transients'],
                    $results['orphaned_meta'],
                    $results['orphaned_relationships'],
                    $results['duplicate_meta'],
                    $results['optimized_tables']
                ),
                'info'
            );

            // Clear statistics cache since database has been modified
            FBS_Secure_Optimize_Controller::clear_stats_cache();

            return $results;

        } catch (Exception $e) {
            FBS_Secure_Optimize_Controller::log('Database cleanup failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Scheduled cleanup handler
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function scheduled_cleanup() {
        $settings = get_option('fbsseop_settings', array());
        $cleanup_settings = isset($settings['database_cleanup']) ? $settings['database_cleanup'] : array();
        
        // Only run if auto cleanup is enabled
        if (isset($cleanup_settings['auto_cleanup']) && $cleanup_settings['auto_cleanup']) {
            $this->perform_cleanup($cleanup_settings);
        }
    }

    /**
     * Clean up post revisions
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of revisions deleted
     */
    private function cleanup_post_revisions() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // Get all revisions
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $revisions = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );
        
        $deleted_count = 0;
        
        foreach ($revisions as $revision) {
            if (wp_delete_post($revision->ID, true)) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }

    /**
     * Clean up auto-draft posts
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of auto-drafts deleted
     */
    private function cleanup_auto_drafts() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // Get auto-drafts older than 7 days
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $auto_drafts = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_status = 'auto-draft' 
             AND post_date < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        $deleted_count = 0;
        
        foreach ($auto_drafts as $draft) {
            if (wp_delete_post($draft->ID, true)) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }

    /**
     * Clean up spam comments
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of spam comments deleted
     */
    private function cleanup_spam_comments() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // Get spam comments older than 30 days
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $spam_comments = $wpdb->get_results(
            "SELECT comment_ID FROM {$wpdb->comments} 
             WHERE comment_approved = 'spam' 
             AND comment_date < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $deleted_count = 0;
        
        foreach ($spam_comments as $comment) {
            if (wp_delete_comment($comment->comment_ID, true)) {
                $deleted_count++;
            }
        }
        
        return $deleted_count;
    }

    /**
     * Clean up expired transients
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of transients deleted
     */
    private function cleanup_transients() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // Delete expired transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $deleted_count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < UNIX_TIMESTAMP()",
                '_transient_timeout_%'
            )
        );
        
        // Delete the corresponding transient values (using a different approach to avoid MySQL restriction)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $transient_timeouts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_timeout_%'
            )
        );
        
        if (!empty($transient_timeouts)) {
            $valid_transients = array();
            foreach ($transient_timeouts as $timeout_name) {
                $transient_name = '_transient_' . substr($timeout_name, 19);
                $valid_transients[] = $transient_name;
            }
            
            if (!empty($valid_transients)) {
                // Delete each valid transient individually to avoid variable interpolation
                foreach ($valid_transients as $transient_name) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$wpdb->options} WHERE option_name = %s",
                            $transient_name
                        )
                    );
                }
                
                // Delete remaining transients that are not in our valid list
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} 
                         WHERE option_name LIKE %s 
                         AND option_name NOT LIKE %s",
                        '_transient_%',
                        '_transient_timeout_%'
                    )
                );
            }
        }
        
        // Delete expired site transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < UNIX_TIMESTAMP()",
                '_site_transient_timeout_%'
            )
        );
        
        // Delete site transient values (using a different approach to avoid MySQL restriction)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $site_transient_timeouts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_site_transient_timeout_%'
            )
        );
        
        if (!empty($site_transient_timeouts)) {
            $valid_site_transients = array();
            foreach ($site_transient_timeouts as $timeout_name) {
                $transient_name = '_site_transient_' . substr($timeout_name, 24);
                $valid_site_transients[] = $transient_name;
            }
            
            if (!empty($valid_site_transients)) {
                // Delete each valid site transient individually to avoid variable interpolation
                foreach ($valid_site_transients as $transient_name) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$wpdb->options} WHERE option_name = %s",
                            $transient_name
                        )
                    );
                }
                
                // Delete remaining site transients that are not in our valid list
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} 
                         WHERE option_name LIKE %s 
                         AND option_name NOT LIKE %s",
                        '_site_transient_%',
                        '_site_transient_timeout_%'
                    )
                );
            }
        }
        
        return $deleted_count;
    }

    /**
     * Clean up orphaned post meta
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of orphaned meta deleted
     */
    private function cleanup_orphaned_post_meta() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        return $wpdb->query(
            "DELETE pm FROM {$wpdb->postmeta} pm 
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );
    }

    /**
     * Clean up orphaned relationships
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of orphaned relationships deleted
     */
    private function cleanup_orphaned_relationships() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        return $wpdb->query(
            "DELETE tr FROM {$wpdb->term_relationships} tr 
             LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
             WHERE p.ID IS NULL"
        );
    }

    /**
     * Clean up duplicate meta
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of duplicate meta deleted
     */
    private function cleanup_duplicate_meta() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        return $wpdb->query(
            "DELETE pm1 FROM {$wpdb->postmeta} pm1 
             INNER JOIN {$wpdb->postmeta} pm2 
             WHERE pm1.meta_id > pm2.meta_id 
             AND pm1.post_id = pm2.post_id 
             AND pm1.meta_key = pm2.meta_key 
             AND pm1.meta_value = pm2.meta_value"
        );
    }

    /**
     * Optimize database tables
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return int Number of tables optimized
     */
    private function optimize_database_tables() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        $tables = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->comments,
            $wpdb->commentmeta,
            $wpdb->terms,
            $wpdb->term_taxonomy,
            $wpdb->term_relationships,
            $wpdb->options,
            $wpdb->users,
            $wpdb->usermeta,
        );
        
        $optimized_count = 0;
        
        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
            if ($wpdb->query("OPTIMIZE TABLE " . esc_sql($table))) {
                $optimized_count++;
            }
        }
        
        return $optimized_count;
    }

    /**
     * Schedule automatic cleanup
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     */
    public function schedule_cleanup() {
        $settings = get_option('fbsseop_settings', array());
        $cleanup_settings = isset($settings['database_cleanup']) ? $settings['database_cleanup'] : array();
        
        if (isset($cleanup_settings['auto_cleanup']) && $cleanup_settings['auto_cleanup']) {
            $frequency = isset($cleanup_settings['cleanup_frequency']) ? $cleanup_settings['cleanup_frequency'] : 'weekly';
            
            // Clear existing scheduled event
            wp_clear_scheduled_hook('fbsseop_cleanup_database');
            
            // Schedule new event
            switch ($frequency) {
                case 'daily':
                    wp_schedule_event(time(), 'daily', 'fbsseop_cleanup_database');
                    break;
                case 'weekly':
                    wp_schedule_event(time(), 'weekly', 'fbsseop_cleanup_database');
                    break;
                case 'monthly':
                    wp_schedule_event(time(), 'monthly', 'fbsseop_cleanup_database');
                    break;
            }
        } else {
            // Clear scheduled event if auto cleanup is disabled
            wp_clear_scheduled_hook('fbsseop_cleanup_database');
        }
    }

    /**
     * Get database statistics
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Database statistics
     */
    public function get_database_stats() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        global $wpdb;
        
        $stats = array();
        
        // Post revisions
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $stats['post_revisions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
        );
        
        // Auto-drafts
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $stats['auto_drafts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"
        );
        
        // Spam comments
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $stats['spam_comments'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"
        );
        
        // Transients
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $stats['transients'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_%',
                '_site_transient_%'
            )
        );
        
        // Orphaned post meta
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $stats['orphaned_meta'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
             LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.ID IS NULL"
        );
        
        // Orphaned relationships
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $stats['orphaned_relationships'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr 
             LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID 
             WHERE p.ID IS NULL"
        );
        
        // Database size
        $stats['database_size'] = $this->get_database_size();
        
        return $stats;
    }

    /**
     * Get database size
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return string Database size in human readable format
     */
    private function get_database_size() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb'
                 FROM information_schema.tables 
                 WHERE table_schema = %s",
                DB_NAME
            )
        );
        
        return $result ? $result->size_mb . ' MB' : 'Unknown';
    }

    /**
     * Get table sizes
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @return array Table sizes
     */
    public function get_table_sizes() {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Statistics gathering operation
        $tables = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb',
                    table_rows
                 FROM information_schema.tables 
                 WHERE table_schema = %s
                 ORDER BY (data_length + index_length) DESC",
                DB_NAME
            )
        );
        
        return $tables;
    }

    /**
     * Clean specific table
     * @since 1.0.0
     * @author Fazle Bari <fazlebarisn@gmail.com>
     * @param string $table_name Table name
     * @return bool True if successful
     */
    public function clean_table($table_name) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        global $wpdb;
        
        // Sanitize table name
        $table_name = sanitize_text_field($table_name);
        
        // Only allow specific tables
        $allowed_tables = array(
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->comments,
            $wpdb->commentmeta,
            $wpdb->options,
        );
        
        if (!in_array($table_name, $allowed_tables)) {
            return false;
        }
        
        // Clean table based on type
        switch ($table_name) {
            case $wpdb->posts:
                $this->cleanup_post_revisions();
                $this->cleanup_auto_drafts();
                break;
                
            case $wpdb->comments:
                $this->cleanup_spam_comments();
                break;
                
            case $wpdb->options:
                $this->cleanup_transients();
                break;
                
            case $wpdb->postmeta:
                $this->cleanup_orphaned_post_meta();
                $this->cleanup_duplicate_meta();
                break;
                
            case $wpdb->commentmeta:
                // Clean orphaned comment meta
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
                $wpdb->query(
                    "DELETE cm FROM {$wpdb->commentmeta} cm 
                     LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID 
                     WHERE c.comment_ID IS NULL"
                );
                break;
        }
        
        // Optimize the table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Database maintenance operation
        $wpdb->query("OPTIMIZE TABLE " . esc_sql($table_name));
        
        return true;
    }
}
