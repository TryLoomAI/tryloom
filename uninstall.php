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

    // Delete WordPress transients for TryLoom
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_tryloom\_%' OR option_name LIKE '\_transient\_timeout\_tryloom\_%'");

    // 3. Delete User Meta
    // Delete 'tryloom_last_login' from usermeta
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'tryloom_%'");

    // 4. Delete tryloom attachments to prevent media library ghosts
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $attachments = $wpdb->get_col("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tryloom_image'");
    if ( ! empty( $attachments ) ) {
        foreach ( $attachments as $attachment_id ) {
            wp_delete_attachment( $attachment_id, true );
        }
    }

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
        if (is_object($wp_filesystem)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
            $wp_filesystem->delete($tryloom_dir, true);
        }
    }
}
