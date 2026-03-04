<?php
/**
 * GDrive to Post Uninstall
 *
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete database tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gdtp_imports");

// Delete options
delete_option('gdtp_service_account_key');
delete_option('gdtp_folder_id');
delete_option('gdtp_folder_name');
delete_option('gdtp_sync_frequency');
delete_option('gdtp_default_author');
delete_option('gdtp_default_category');
delete_option('gdtp_default_status');
delete_option('gdtp_notification_email');
delete_option('gdtp_email_notifications');
delete_option('gdtp_publish_token_expiry');
delete_option('gdtp_last_sync');

// Delete post meta
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_gdtp_%'");

// Delete transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gdtp_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gdtp_%'");

// Clear scheduled cron jobs
wp_clear_scheduled_hook('gdtp_sync_cron');

// Flush rewrite rules
flush_rewrite_rules();
