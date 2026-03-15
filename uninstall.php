<?php
/**
 * TryLoom Uninstall
 *
 * Uninstalling TryLoom deletes user tables and options.
 *
 * @package TryLoom
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if the user has opted to remove data on deletion
if ('yes' === get_option('tryloom_remove_data_on_delete', 'no')) {
    global $wpdb;

    // 1. Delete Custom Tables
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tryloom_history");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tryloom_user_photos");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tryloom_cart_conversions");

    // 2. Delete Options
    // Delete all options that start with 'tryloom_'
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tryloom_%'");

    // 3. Delete User Meta
    // Delete 'tryloom_last_login' from usermeta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'tryloom_%'");

    // 4. Clean up custom directory
    // Note: We do not delete the physical files in /uploads/tryloom/ to avoid
    // accidental data loss of user files unless strictly necessary, 
    // or we could iterate and delete them.
    // Given the "Remove all data" prompt, we should probably delete the files too.

    $tryloom_upload_dir = wp_upload_dir();
    $tryloom_dir = $tryloom_upload_dir['basedir'] . '/tryloom';

    if (is_dir($tryloom_dir)) {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        $wp_filesystem->delete($tryloom_dir, true);
    }
}
