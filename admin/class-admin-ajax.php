<?php
/**
 * AJAX handlers for GDrive to Post admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Admin_Ajax {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_ajax_gdtp_upload_key', array($this, 'upload_key'));
        add_action('wp_ajax_gdtp_remove_key', array($this, 'remove_key'));
        add_action('wp_ajax_gdtp_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_gdtp_browse_folders', array($this, 'browse_folders'));
        add_action('wp_ajax_gdtp_select_folder', array($this, 'select_folder'));
        add_action('wp_ajax_gdtp_run_sync', array($this, 'run_sync'));
        add_action('wp_ajax_gdtp_sync_status', array($this, 'sync_status'));
        add_action('wp_ajax_gdtp_send_test_email', array($this, 'send_test_email'));
    }

    /**
     * Upload service account key
     */
    public function upload_key() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $key_json = isset($_POST['key_json']) ? wp_unslash($_POST['key_json']) : '';

        if (empty($key_json)) {
            wp_send_json_error(array('message' => __('No key data provided.', 'gdrive-to-post')));
        }

        // Validate JSON
        $data = json_decode($key_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid JSON format.', 'gdrive-to-post')));
        }

        // Validate required fields
        $required_fields = array('type', 'project_id', 'private_key', 'client_email');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                wp_send_json_error(array('message' => sprintf(
                    __('Missing required field: %s', 'gdrive-to-post'),
                    $field
                )));
            }
        }

        if ($data['type'] !== 'service_account') {
            wp_send_json_error(array('message' => __('Key must be a service account type.', 'gdrive-to-post')));
        }

        // Check OpenSSL
        if (!function_exists('openssl_sign')) {
            wp_send_json_error(array('message' => __('OpenSSL extension is required but not available.', 'gdrive-to-post')));
        }

        // Validate private key can be parsed
        $pk = openssl_pkey_get_private($data['private_key']);
        if (!$pk) {
            wp_send_json_error(array('message' => __('Invalid private key in the service account file.', 'gdrive-to-post')));
        }

        // Store the key
        update_option('gdtp_service_account_key', $key_json);

        // Clear any cached tokens
        delete_transient('gdtp_access_token');

        wp_send_json_success(array(
            'message' => __('Service account key uploaded successfully.', 'gdrive-to-post'),
            'email'   => $data['client_email'],
        ));
    }

    /**
     * Remove service account key
     */
    public function remove_key() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        update_option('gdtp_service_account_key', '');
        update_option('gdtp_folder_id', '');
        update_option('gdtp_folder_name', '');
        delete_transient('gdtp_access_token');

        wp_send_json_success(array('message' => __('Service account key removed.', 'gdrive-to-post')));
    }

    /**
     * Test Google Drive connection
     */
    public function test_connection() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $result = gdtp()->google_drive->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => sprintf(
                __('Connected successfully as %s', 'gdrive-to-post'),
                $result['email']
            ),
            'email' => $result['email'],
            'name'  => $result['name'],
        ));
    }

    /**
     * Browse Google Drive folders
     */
    public function browse_folders() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $parent_id = isset($_POST['parent_id']) ? sanitize_text_field($_POST['parent_id']) : 'root';

        $folders = gdtp()->google_drive->list_folders($parent_id);

        if (is_wp_error($folders)) {
            wp_send_json_error(array('message' => $folders->get_error_message()));
        }

        wp_send_json_success(array(
            'folders'   => $folders,
            'parent_id' => $parent_id,
        ));
    }

    /**
     * Select a folder for sync
     */
    public function select_folder() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $folder_id = isset($_POST['folder_id']) ? sanitize_text_field($_POST['folder_id']) : '';
        $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';

        if (empty($folder_id)) {
            wp_send_json_error(array('message' => __('No folder selected.', 'gdrive-to-post')));
        }

        update_option('gdtp_folder_id', $folder_id);
        update_option('gdtp_folder_name', $folder_name);

        wp_send_json_success(array(
            'message'     => sprintf(__('Folder "%s" selected.', 'gdrive-to-post'), $folder_name),
            'folder_id'   => $folder_id,
            'folder_name' => $folder_name,
        ));
    }

    /**
     * Run sync manually
     */
    public function run_sync() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $results = gdtp()->sync->run_sync();

        if ($results['success']) {
            wp_send_json_success($results);
        } else {
            wp_send_json_error($results);
        }
    }

    /**
     * Get current sync status
     */
    public function sync_status() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $status = gdtp()->sync->get_sync_status();

        wp_send_json_success($status);
    }

    /**
     * Send test email
     */
    public function send_test_email() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $result = gdtp()->notifier->send_test_email();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Test email sent successfully.', 'gdrive-to-post')));
    }
}
