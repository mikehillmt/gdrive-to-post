<?php
/**
 * Settings page view for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

$sa_email = gdtp_get_service_account_email();
$has_key = !empty(get_option('gdtp_service_account_key', ''));
$folder_id = get_option('gdtp_folder_id', '');
$folder_name = get_option('gdtp_folder_name', '');
$sync_frequency = get_option('gdtp_sync_frequency', 'daily');
$default_author = (int) get_option('gdtp_default_author', 1);
$default_category = (int) get_option('gdtp_default_category', 1);
$default_status = get_option('gdtp_default_status', 'draft');
$email_notifications = get_option('gdtp_email_notifications', true);
$notification_email = get_option('gdtp_notification_email', get_option('admin_email'));
$publish_token_expiry = (int) get_option('gdtp_publish_token_expiry', 7);
$frequencies = gdtp_get_sync_frequencies();
?>

<div class="wrap gdtp-wrap">
    <h1><?php esc_html_e('GDrive to Post Settings', 'gdrive-to-post'); ?></h1>

    <?php settings_errors('gdtp_settings'); ?>

    <!-- Google Drive Connection -->
    <div class="gdtp-section">
        <h2><?php esc_html_e('Google Drive Connection', 'gdrive-to-post'); ?></h2>

        <div id="gdtp-key-status">
            <?php if ($has_key) : ?>
                <div class="gdtp-key-info">
                    <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Key Uploaded', 'gdrive-to-post'); ?></span>
                    <code><?php echo esc_html($sa_email); ?></code>
                    <button type="button" id="gdtp-test-connection" class="button">
                        <span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span>
                        <?php esc_html_e('Test Connection', 'gdrive-to-post'); ?>
                    </button>
                    <button type="button" id="gdtp-remove-key" class="button gdtp-delete-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
                        <?php esc_html_e('Remove Key', 'gdrive-to-post'); ?>
                    </button>
                    <span id="gdtp-connection-message" class="gdtp-inline-message"></span>
                </div>
            <?php else : ?>
                <div class="gdtp-key-upload">
                    <p class="description">
                        <?php esc_html_e('Upload your Google Cloud Service Account JSON key file. The service account needs access to the Google Drive API.', 'gdrive-to-post'); ?>
                    </p>
                    <div class="gdtp-upload-area" id="gdtp-upload-area">
                        <input type="file" id="gdtp-key-file" accept=".json" style="display: none;">
                        <p>
                            <button type="button" id="gdtp-upload-key" class="button button-primary">
                                <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span>
                                <?php esc_html_e('Upload JSON Key File', 'gdrive-to-post'); ?>
                            </button>
                        </p>
                        <span id="gdtp-upload-message" class="gdtp-inline-message"></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="gdtp-info-box" style="margin-top: 15px;">
            <h4><?php esc_html_e('Setup Instructions', 'gdrive-to-post'); ?></h4>
            <ol>
                <li><?php esc_html_e('Go to Google Cloud Console and create a Service Account', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Enable the Google Drive API for your project', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Create and download a JSON key for the Service Account', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Share your Google Drive folder with the Service Account email address', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Upload the JSON key file above', 'gdrive-to-post'); ?></li>
            </ol>
        </div>
    </div>

    <!-- Source Folder -->
    <div class="gdtp-section">
        <h2><?php esc_html_e('Source Folder', 'gdrive-to-post'); ?></h2>

        <div id="gdtp-folder-status">
            <?php if ($folder_id) : ?>
                <p>
                    <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Selected', 'gdrive-to-post'); ?></span>
                    <strong><?php echo esc_html($folder_name); ?></strong>
                    <code style="font-size: 11px;"><?php echo esc_html($folder_id); ?></code>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($has_key) : ?>
            <div id="gdtp-folder-browser">
                <div id="gdtp-folder-breadcrumbs" class="gdtp-breadcrumbs">
                    <a href="#" data-folder-id="root" class="gdtp-breadcrumb"><?php esc_html_e('Shared Drives / My Drive', 'gdrive-to-post'); ?></a>
                </div>
                <div id="gdtp-folder-list" class="gdtp-folder-list">
                    <p class="description"><?php esc_html_e('Click "Browse Folders" to load your Google Drive folders.', 'gdrive-to-post'); ?></p>
                </div>
                <p>
                    <button type="button" id="gdtp-browse-folders" class="button" data-parent-id="root">
                        <span class="dashicons dashicons-portfolio" style="vertical-align: middle; margin-top: -2px;"></span>
                        <?php esc_html_e('Browse Folders', 'gdrive-to-post'); ?>
                    </button>
                </p>
            </div>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Upload a service account key first to browse folders.', 'gdrive-to-post'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Sync & Post Settings -->
    <form method="post">
        <?php wp_nonce_field('gdtp_settings'); ?>

        <div class="gdtp-section">
            <h2><?php esc_html_e('Sync Settings', 'gdrive-to-post'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="sync_frequency"><?php esc_html_e('Sync Frequency', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <select name="sync_frequency" id="sync_frequency">
                            <?php foreach ($frequencies as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($sync_frequency, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_author"><?php esc_html_e('Default Author', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <?php wp_dropdown_users(array(
                            'name'     => 'default_author',
                            'id'       => 'default_author',
                            'selected' => $default_author,
                            'role__in' => array('administrator', 'editor', 'author'),
                        )); ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_category"><?php esc_html_e('Default Category', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <?php wp_dropdown_categories(array(
                            'name'             => 'default_category',
                            'id'               => 'default_category',
                            'selected'         => $default_category,
                            'hide_empty'       => false,
                            'show_option_none' => '',
                        )); ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_status"><?php esc_html_e('Default Post Status', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <select name="default_status" id="default_status">
                            <option value="draft" <?php selected($default_status, 'draft'); ?>><?php esc_html_e('Draft', 'gdrive-to-post'); ?></option>
                            <option value="pending" <?php selected($default_status, 'pending'); ?>><?php esc_html_e('Pending Review', 'gdrive-to-post'); ?></option>
                            <option value="private" <?php selected($default_status, 'private'); ?>><?php esc_html_e('Private', 'gdrive-to-post'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email Notifications -->
        <div class="gdtp-section">
            <h2><?php esc_html_e('Notifications', 'gdrive-to-post'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="email_notifications"><?php esc_html_e('Email Notifications', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <label class="gdtp-toggle">
                            <input type="checkbox" name="email_notifications" id="email_notifications" <?php checked($email_notifications); ?>>
                            <span class="gdtp-toggle-slider"></span>
                            <?php esc_html_e('Send email when new posts are imported', 'gdrive-to-post'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="notification_email"><?php esc_html_e('Notification Email', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr($notification_email); ?>">
                        <button type="button" id="gdtp-test-email" class="button">
                            <?php esc_html_e('Send Test Email', 'gdrive-to-post'); ?>
                        </button>
                        <span id="gdtp-email-message" class="gdtp-inline-message"></span>
                    </td>
                </tr>
                <tr>
                    <th><label for="publish_token_expiry"><?php esc_html_e('Publish Link Expiry', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <input type="number" name="publish_token_expiry" id="publish_token_expiry" class="small-text" value="<?php echo esc_attr($publish_token_expiry); ?>" min="1" max="30">
                        <?php esc_html_e('days', 'gdrive-to-post'); ?>
                        <p class="description"><?php esc_html_e('One-click publish links in notification emails will expire after this many days.', 'gdrive-to-post'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Plugin Info -->
        <div class="gdtp-section gdtp-info-section">
            <h2><?php esc_html_e('Plugin Information', 'gdrive-to-post'); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Version', 'gdrive-to-post'); ?></th>
                    <td><?php echo esc_html(GDTP_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('PHP OpenSSL', 'gdrive-to-post'); ?></th>
                    <td>
                        <?php if (function_exists('openssl_sign')) : ?>
                            <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Available', 'gdrive-to-post'); ?></span>
                        <?php else : ?>
                            <span class="gdtp-status gdtp-status-error"><?php esc_html_e('Not Available (Required)', 'gdrive-to-post'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="gdtp_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'gdrive-to-post'); ?>">
        </p>
    </form>
</div>
