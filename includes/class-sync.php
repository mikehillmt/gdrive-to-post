<?php
/**
 * Sync engine for GDrive to Post
 *
 * Handles WP-Cron scheduling and import orchestration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Sync {

    /**
     * Sync lock transient name
     */
    const LOCK_KEY = 'gdtp_sync_lock';

    /**
     * Sync lock TTL in seconds (10 minutes)
     */
    const LOCK_TTL = 600;

    /**
     * Schedule the sync cron event
     */
    public function schedule_cron() {
        $frequency = get_option('gdtp_sync_frequency', 'daily');

        if (!wp_next_scheduled('gdtp_sync_cron')) {
            wp_schedule_event(time(), $frequency, 'gdtp_sync_cron');
        }
    }

    /**
     * Reschedule cron with a new frequency
     */
    public function reschedule_cron() {
        wp_clear_scheduled_hook('gdtp_sync_cron');
        $this->schedule_cron();
    }

    /**
     * Run the sync process
     */
    public function run_sync() {
        // Check if configured
        if (!gdtp_is_configured()) {
            gdtp_log('Sync skipped: plugin not fully configured.', 'warning');
            return array(
                'success'  => false,
                'message'  => __('Plugin is not fully configured. Please add a service account key and select a folder.', 'gdrive-to-post'),
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => 0,
            );
        }

        // Acquire lock to prevent concurrent syncs
        if (get_transient(self::LOCK_KEY)) {
            gdtp_log('Sync skipped: another sync is already running.', 'warning');
            return array(
                'success'  => false,
                'message'  => __('A sync is already in progress. Please wait.', 'gdrive-to-post'),
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => 0,
            );
        }

        set_transient(self::LOCK_KEY, true, self::LOCK_TTL);

        $folder_id = get_option('gdtp_folder_id');
        $database = gdtp()->database;
        $drive = gdtp()->google_drive;

        $results = array(
            'success'        => true,
            'message'        => '',
            'imported'       => 0,
            'skipped'        => 0,
            'errors'         => 0,
            'imported_posts' => array(),
        );

        gdtp_log('Starting sync for folder: ' . $folder_id);

        // Get all docs recursively
        $docs = $drive->list_docs_recursive($folder_id);

        if (is_wp_error($docs)) {
            delete_transient(self::LOCK_KEY);
            gdtp_log('Sync failed: ' . $docs->get_error_message(), 'error');
            return array(
                'success'  => false,
                'message'  => $docs->get_error_message(),
                'imported' => 0,
                'skipped'  => 0,
                'errors'   => 1,
            );
        }

        gdtp_log(sprintf('Found %d docs in folder.', count($docs)));

        foreach ($docs as $doc) {
            // Skip already imported docs
            if ($database->is_doc_imported($doc['id'])) {
                $results['skipped']++;
                continue;
            }

            // Import the doc
            $import_result = $this->import_doc($doc);

            if (is_wp_error($import_result)) {
                $results['errors']++;

                // Record failed import
                $database->record_import(array(
                    'google_doc_id'    => $doc['id'],
                    'google_doc_title' => $doc['name'],
                    'folder_id'        => $doc['folder_id'] ?? $folder_id,
                    'post_id'          => 0,
                    'status'           => 'error',
                    'error_message'    => $import_result->get_error_message(),
                ));

                gdtp_log('Failed to import doc ' . $doc['name'] . ': ' . $import_result->get_error_message(), 'error');
            } else {
                $results['imported']++;
                $results['imported_posts'][] = $import_result;
            }
        }

        // Update last sync time
        update_option('gdtp_last_sync', current_time('mysql'));

        // Send notification if we imported anything
        if ($results['imported'] > 0 && get_option('gdtp_email_notifications', true)) {
            gdtp()->notifier->send_sync_notification($results['imported_posts']);
        }

        $results['message'] = sprintf(
            __('Sync complete: %d imported, %d skipped, %d errors.', 'gdrive-to-post'),
            $results['imported'],
            $results['skipped'],
            $results['errors']
        );

        gdtp_log($results['message']);

        // Release lock
        delete_transient(self::LOCK_KEY);

        return $results;
    }

    /**
     * Import a single Google Doc as a WordPress draft post
     */
    public function import_doc($doc) {
        $drive = gdtp()->google_drive;
        $processor = gdtp()->content_processor;
        $database = gdtp()->database;

        // Export as HTML
        $html = $drive->export_doc_as_html($doc['id']);
        if (is_wp_error($html)) {
            return $html;
        }

        // Process content (clean HTML, upload images)
        $processed = $processor->process($html, $doc['name']);

        if (empty($processed['content'])) {
            return new WP_Error('empty_content', __('Document produced empty content after processing.', 'gdrive-to-post'));
        }

        // Create post
        $post_data = array(
            'post_title'   => sanitize_text_field($doc['name']),
            'post_content' => $processed['content'],
            'post_status'  => get_option('gdtp_default_status', 'draft'),
            'post_author'  => (int) get_option('gdtp_default_author', 1),
            'post_category' => array((int) get_option('gdtp_default_category', 1)),
        );

        $post_data = apply_filters('gdtp_before_insert_post', $post_data, $doc);

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Store Google Doc ID as meta
        update_post_meta($post_id, '_gdtp_google_doc_id', $doc['id']);
        update_post_meta($post_id, '_gdtp_google_doc_title', $doc['name']);
        update_post_meta($post_id, '_gdtp_imported_at', current_time('mysql'));

        // Set first image as featured image
        if (!empty($processed['images'])) {
            set_post_thumbnail($post_id, $processed['images'][0]);
        }

        // Generate publish token for one-click publishing
        $publish_token = gdtp()->notifier->create_publish_token($post_id);

        // Record import
        $database->record_import(array(
            'google_doc_id'    => $doc['id'],
            'google_doc_title' => $doc['name'],
            'folder_id'        => $doc['folder_id'] ?? get_option('gdtp_folder_id'),
            'post_id'          => $post_id,
            'status'           => 'success',
        ));

        do_action('gdtp_after_import_doc', $post_id, $doc);

        return array(
            'post_id'       => $post_id,
            'post_title'    => $doc['name'],
            'publish_token' => $publish_token,
        );
    }

    /**
     * Get current sync status
     */
    public function get_sync_status() {
        $is_syncing = (bool) get_transient(self::LOCK_KEY);
        $last_sync = get_option('gdtp_last_sync', '');
        $next_sync = wp_next_scheduled('gdtp_sync_cron');

        return array(
            'is_syncing' => $is_syncing,
            'last_sync'  => $last_sync,
            'next_sync'  => $next_sync ? date('Y-m-d H:i:s', $next_sync) : '',
            'stats'      => gdtp()->database->get_import_stats(),
        );
    }
}
