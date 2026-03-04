<?php
/**
 * Plugin Name: GDrive to Post
 * Plugin URI: https://yoursite.com/gdrive-to-post
 * Description: Automatically sync Google Docs from a shared Drive folder into WordPress draft posts with clean HTML, images, and one-click publish links
 * Version: 1.0.0
 * Author: MMA
 * Author URI: https://yoursite.com
 * License: GPL-2.0+
 * Text Domain: gdrive-to-post
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('GDTP_VERSION', '1.0.0');
define('GDTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GDTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GDTP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
final class GDrive_To_Post {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    public $database;
    public $google_drive;
    public $sync;
    public $content_processor;
    public $notifier;

    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once GDTP_PLUGIN_DIR . 'includes/functions.php';
        require_once GDTP_PLUGIN_DIR . 'includes/class-database.php';
        require_once GDTP_PLUGIN_DIR . 'includes/class-google-drive.php';
        require_once GDTP_PLUGIN_DIR . 'includes/class-content-processor.php';
        require_once GDTP_PLUGIN_DIR . 'includes/class-sync.php';
        require_once GDTP_PLUGIN_DIR . 'includes/class-notifier.php';

        if (is_admin()) {
            require_once GDTP_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once GDTP_PLUGIN_DIR . 'admin/class-admin-ajax.php';
        }
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->database = new GDTP_Database();
        $this->google_drive = new GDTP_Google_Drive();
        $this->content_processor = new GDTP_Content_Processor();
        $this->sync = new GDTP_Sync();
        $this->notifier = new GDTP_Notifier();

        if (is_admin()) {
            new GDTP_Admin_Menu();
            new GDTP_Admin_Ajax();
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'handle_publish_token'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));

        // Schedule sync cron
        $this->sync->schedule_cron();
        add_action('gdtp_sync_cron', array($this->sync, 'run_sync'));
    }

    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('gdrive-to-post', false, dirname(GDTP_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Handle one-click publish token from email links
     */
    public function handle_publish_token() {
        if (isset($_GET['gdtp_publish']) && !empty($_GET['gdtp_publish'])) {
            $token = sanitize_text_field($_GET['gdtp_publish']);
            $this->notifier->process_publish_token($token);
        }
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'gdtp_dashboard_widget',
                __('GDrive to Post - Quick Stats', 'gdrive-to-post'),
                array($this, 'render_dashboard_widget')
            );
        }
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        include GDTP_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
    }
}

/**
 * Activation hook
 */
function gdtp_activate() {
    require_once GDTP_PLUGIN_DIR . 'includes/class-database.php';
    $database = new GDTP_Database();
    $database->create_tables();

    // Set default options
    add_option('gdtp_service_account_key', '');
    add_option('gdtp_folder_id', '');
    add_option('gdtp_folder_name', '');
    add_option('gdtp_sync_frequency', 'daily');
    add_option('gdtp_default_author', get_current_user_id());
    add_option('gdtp_default_category', get_option('default_category', 1));
    add_option('gdtp_default_status', 'draft');
    add_option('gdtp_notification_email', get_option('admin_email'));
    add_option('gdtp_email_notifications', true);
    add_option('gdtp_publish_token_expiry', 7);

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gdtp_activate');

/**
 * Deactivation hook
 */
function gdtp_deactivate() {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook('gdtp_sync_cron');

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'gdtp_deactivate');

/**
 * Initialize plugin
 */
function gdtp() {
    return GDrive_To_Post::instance();
}

// Start the plugin
add_action('plugins_loaded', 'gdtp');
