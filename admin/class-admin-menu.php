<?php
/**
 * Admin menu and pages for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Admin_Menu {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __('GDrive to Post', 'gdrive-to-post'),
            __('GDrive to Post', 'gdrive-to-post'),
            'manage_options',
            'gdrive-to-post',
            array($this, 'render_dashboard_page'),
            'dashicons-cloud-upload',
            30
        );

        add_submenu_page(
            'gdrive-to-post',
            __('Dashboard', 'gdrive-to-post'),
            __('Dashboard', 'gdrive-to-post'),
            'manage_options',
            'gdrive-to-post',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'gdrive-to-post',
            __('Import Log', 'gdrive-to-post'),
            __('Import Log', 'gdrive-to-post'),
            'manage_options',
            'gdrive-to-post-log',
            array($this, 'render_import_log_page')
        );

        add_submenu_page(
            'gdrive-to-post',
            __('Settings', 'gdrive-to-post'),
            __('Settings', 'gdrive-to-post'),
            'manage_options',
            'gdrive-to-post-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'gdrive-to-post') === false) {
            return;
        }

        wp_enqueue_style(
            'gdtp-admin',
            GDTP_PLUGIN_URL . 'admin/assets/css/admin-styles.css',
            array(),
            GDTP_VERSION
        );

        wp_enqueue_script(
            'gdtp-admin',
            GDTP_PLUGIN_URL . 'admin/assets/js/admin-scripts.js',
            array('jquery'),
            GDTP_VERSION,
            true
        );

        wp_localize_script('gdtp-admin', 'gdtpAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('gdtp_admin_nonce'),
            'strings' => array(
                'syncing'          => __('Syncing...', 'gdrive-to-post'),
                'syncComplete'     => __('Sync complete!', 'gdrive-to-post'),
                'testing'          => __('Testing...', 'gdrive-to-post'),
                'uploading'        => __('Uploading...', 'gdrive-to-post'),
                'sending'          => __('Sending...', 'gdrive-to-post'),
                'confirmDisconnect' => __('Are you sure you want to disconnect from Google Drive?', 'gdrive-to-post'),
                'error'            => __('An error occurred. Please try again.', 'gdrive-to-post'),
                'selectFolder'     => __('Select this folder', 'gdrive-to-post'),
                'generatingImage'  => __('Generating image...', 'gdrive-to-post'),
                'testingOpenAI'    => __('Testing...', 'gdrive-to-post'),
                'savingKey'        => __('Saving...', 'gdrive-to-post'),
                'confirmRemoveOpenAIKey' => __('Are you sure you want to remove the OpenAI API key?', 'gdrive-to-post'),
            ),
        ));
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle settings save
        if (isset($_POST['gdtp_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'gdtp_settings')) {
            $this->save_settings();
        }
    }

    /**
     * Save settings
     */
    private function save_settings() {
        // Sync frequency
        $old_frequency = get_option('gdtp_sync_frequency', 'daily');
        $new_frequency = sanitize_text_field($_POST['sync_frequency'] ?? 'daily');
        $valid_frequencies = array_keys(gdtp_get_sync_frequencies());
        if (!in_array($new_frequency, $valid_frequencies)) {
            $new_frequency = 'daily';
        }
        update_option('gdtp_sync_frequency', $new_frequency);

        // Reschedule cron if frequency changed
        if ($old_frequency !== $new_frequency) {
            gdtp()->sync->reschedule_cron();
        }

        // Default author
        $author_id = (int) ($_POST['default_author'] ?? 1);
        if (get_userdata($author_id)) {
            update_option('gdtp_default_author', $author_id);
        }

        // Default category
        update_option('gdtp_default_category', (int) ($_POST['default_category'] ?? 1));

        // Default post status
        $status = sanitize_text_field($_POST['default_status'] ?? 'draft');
        if (in_array($status, array('draft', 'pending', 'private'))) {
            update_option('gdtp_default_status', $status);
        }

        // Email notifications
        update_option('gdtp_email_notifications', isset($_POST['email_notifications']));
        update_option('gdtp_notification_email', sanitize_email($_POST['notification_email'] ?? ''));

        // Publish token expiry
        $expiry = (int) ($_POST['publish_token_expiry'] ?? 7);
        $expiry = max(1, min(30, $expiry));
        update_option('gdtp_publish_token_expiry', $expiry);

        // AI Image Generation settings
        update_option('gdtp_ai_image_enabled', isset($_POST['ai_image_enabled']));

        $ai_style = sanitize_text_field($_POST['ai_image_style'] ?? 'photographic');
        $valid_styles = array_keys(GDTP_Image_Generator::get_image_styles());
        if (!in_array($ai_style, $valid_styles)) {
            $ai_style = 'photographic';
        }
        update_option('gdtp_ai_image_style', $ai_style);

        $prompt_template = sanitize_textarea_field($_POST['ai_image_prompt_template'] ?? '');
        update_option('gdtp_ai_image_prompt_template', $prompt_template);

        add_settings_error('gdtp_settings', 'settings_saved', __('Settings saved.', 'gdrive-to-post'), 'success');
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'gdrive-to-post'));
        }

        include GDTP_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render import log page
     */
    public function render_import_log_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'gdrive-to-post'));
        }

        include GDTP_PLUGIN_DIR . 'admin/views/import-log.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'gdrive-to-post'));
        }

        include GDTP_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
