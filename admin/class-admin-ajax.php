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
        add_action('wp_ajax_gdtp_save_oauth_creds', array($this, 'save_oauth_creds'));
        add_action('wp_ajax_gdtp_remove_oauth_creds', array($this, 'remove_oauth_creds'));
        add_action('wp_ajax_gdtp_disconnect_google', array($this, 'disconnect_google'));
        add_action('wp_ajax_gdtp_oauth_exchange', array($this, 'oauth_exchange'));
        add_action('wp_ajax_gdtp_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_gdtp_browse_folders', array($this, 'browse_folders'));
        add_action('wp_ajax_gdtp_select_folder', array($this, 'select_folder'));
        add_action('wp_ajax_gdtp_run_sync', array($this, 'run_sync'));
        add_action('wp_ajax_gdtp_sync_status', array($this, 'sync_status'));
        add_action('wp_ajax_gdtp_send_test_email', array($this, 'send_test_email'));

        // AI Image Generation
        add_action('wp_ajax_gdtp_save_openai_key', array($this, 'save_openai_key'));
        add_action('wp_ajax_gdtp_remove_openai_key', array($this, 'remove_openai_key'));
        add_action('wp_ajax_gdtp_test_openai', array($this, 'test_openai'));
        add_action('wp_ajax_gdtp_test_image_gen', array($this, 'test_image_gen'));
    }

    /**
     * Save OAuth credentials and return auth URL
     */
    public function save_oauth_creds() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $client_secret = isset($_POST['client_secret']) ? sanitize_text_field(wp_unslash($_POST['client_secret'])) : '';

        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(array('message' => __('Both Client ID and Client Secret are required.', 'gdrive-to-post')));
        }

        if (strpos($client_id, '.apps.googleusercontent.com') === false) {
            wp_send_json_error(array('message' => __('Invalid Client ID format. It should end with ".apps.googleusercontent.com".', 'gdrive-to-post')));
        }

        // Store credentials (secret encrypted)
        update_option('gdtp_oauth_client_id', $client_id);
        update_option('gdtp_oauth_client_secret', gdtp_encrypt($client_secret));

        // Generate auth URL
        $auth_url = gdtp()->google_drive->get_auth_url();

        if (is_wp_error($auth_url)) {
            wp_send_json_error(array('message' => $auth_url->get_error_message()));
        }

        wp_send_json_success(array(
            'message'  => __('Credentials saved. Redirecting to Google...', 'gdrive-to-post'),
            'auth_url' => $auth_url,
        ));
    }

    /**
     * Remove OAuth credentials (before connecting)
     */
    public function remove_oauth_creds() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        delete_option('gdtp_oauth_client_id');
        delete_option('gdtp_oauth_client_secret');

        wp_send_json_success(array('message' => __('OAuth credentials removed.', 'gdrive-to-post')));
    }

    /**
     * Disconnect from Google - revoke tokens and clean up
     */
    public function disconnect_google() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        gdtp()->google_drive->disconnect();

        wp_send_json_success(array('message' => __('Disconnected from Google Drive.', 'gdrive-to-post')));
    }

    /**
     * Exchange OAuth authorization code for tokens (called from static HTML callback)
     */
    public function oauth_exchange() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied. Please log in as an administrator.', 'gdrive-to-post')));
        }

        $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';

        if (empty($code) || empty($state)) {
            wp_send_json_error(array('message' => __('Missing authorization code or state.', 'gdrive-to-post')));
        }

        $result = gdtp()->google_drive->handle_callback($code, $state);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message'  => __('Successfully connected to Google Drive!', 'gdrive-to-post'),
            'redirect' => admin_url('admin.php?page=gdrive-to-post-settings'),
        ));
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

    /**
     * Save OpenAI API key (encrypted)
     */
    public function save_openai_key() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('No API key provided.', 'gdrive-to-post')));
        }

        if (strpos($api_key, 'sk-') !== 0) {
            wp_send_json_error(array('message' => __('Invalid API key format. Key should start with "sk-".', 'gdrive-to-post')));
        }

        // Encrypt and store
        $encrypted = gdtp_encrypt($api_key);
        update_option('gdtp_openai_api_key', $encrypted);
        update_option('gdtp_openai_api_key_hint', substr($api_key, -4));

        wp_send_json_success(array(
            'message' => __('OpenAI API key saved successfully.', 'gdrive-to-post'),
        ));
    }

    /**
     * Remove OpenAI API key
     */
    public function remove_openai_key() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        delete_option('gdtp_openai_api_key');
        delete_option('gdtp_openai_api_key_hint');
        update_option('gdtp_ai_image_enabled', false);

        wp_send_json_success(array('message' => __('OpenAI API key removed.', 'gdrive-to-post')));
    }

    /**
     * Test OpenAI API connection
     */
    public function test_openai() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $result = gdtp()->image_generator->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('OpenAI API connection successful. DALL-E 3 is available.', 'gdrive-to-post'),
        ));
    }

    /**
     * Test image generation with a sample prompt
     */
    public function test_image_gen() {
        check_ajax_referer('gdtp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'gdrive-to-post')));
        }

        $api_key = gdtp()->image_generator->get_api_key();

        if (!$api_key) {
            wp_send_json_error(array('message' => __('No OpenAI API key configured.', 'gdrive-to-post')));
        }

        // Build prompt using current settings
        $test_title = 'Sample Blog Post About Technology';
        $test_content = 'This is a test article about modern technology trends and digital innovation.';
        $style = get_option('gdtp_ai_image_style', 'photographic');
        $template = get_option('gdtp_ai_image_prompt_template', '');

        if (empty($template)) {
            $template = "Create a {style} style image for a blog post titled '{title}'. The article is about: {summary}. The image should be visually compelling and suitable as a featured blog image. Do not include any text or words in the image.";
        }

        $prompt = str_replace(
            array('{title}', '{summary}', '{style}'),
            array($test_title, $test_content, $style),
            $template
        );

        // Call the API (1024x1024 for test to reduce cost)
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model'           => 'dall-e-3',
                'prompt'          => $prompt,
                'n'               => 1,
                'size'            => '1024x1024',
                'quality'         => 'standard',
                'response_format' => 'url',
            )),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error.', 'gdrive-to-post');
            wp_send_json_error(array('message' => $error_msg));
        }

        if (empty($body['data'][0]['url'])) {
            wp_send_json_error(array('message' => __('No image URL in API response.', 'gdrive-to-post')));
        }

        wp_send_json_success(array(
            'message'   => __('Test image generated successfully!', 'gdrive-to-post'),
            'image_url' => $body['data'][0]['url'],
            'prompt'    => $prompt,
        ));
    }
}
