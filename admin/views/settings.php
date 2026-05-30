<?php
/**
 * Settings page view for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_connected = gdtp()->google_drive->is_connected();
$has_credentials = gdtp_has_oauth_credentials();
$connected_email = gdtp_get_connected_email();
$client_id = get_option('gdtp_oauth_client_id', '');
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
$ai_image_enabled = get_option('gdtp_ai_image_enabled', false);
$has_openai_key = !empty(get_option('gdtp_openai_api_key', ''));
$openai_key_hint = get_option('gdtp_openai_api_key_hint', '');
$ai_image_style = get_option('gdtp_ai_image_style', 'photographic');
$ai_image_prompt_template = get_option('gdtp_ai_image_prompt_template', '');
$ai_image_styles = GDTP_Image_Generator::get_image_styles();
?>

<div class="wrap gdtp-wrap">
    <h1><?php esc_html_e('GDrive to Post Settings', 'gdrive-to-post'); ?></h1>

    <?php settings_errors('gdtp_settings'); ?>

    <!-- Google Drive Connection -->
    <div class="gdtp-section">
        <h2><?php esc_html_e('Google Drive Connection', 'gdrive-to-post'); ?></h2>

        <?php if ($is_connected) : ?>
            <!-- Connected state -->
            <div class="gdtp-key-info">
                <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Connected', 'gdrive-to-post'); ?></span>
                <code><?php echo esc_html($connected_email); ?></code>
                <button type="button" id="gdtp-test-connection" class="button">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span>
                    <?php esc_html_e('Test Connection', 'gdrive-to-post'); ?>
                </button>
                <button type="button" id="gdtp-disconnect-google" class="button gdtp-delete-btn">
                    <span class="dashicons dashicons-dismiss" style="vertical-align: middle; margin-top: -2px;"></span>
                    <?php esc_html_e('Disconnect', 'gdrive-to-post'); ?>
                </button>
                <span id="gdtp-connection-message" class="gdtp-inline-message"></span>
            </div>

        <?php elseif ($has_credentials) : ?>
            <!-- Credentials saved but not connected yet -->
            <div>
                <p>
                    <span class="gdtp-status gdtp-status-draft"><?php esc_html_e('Not Connected', 'gdrive-to-post'); ?></span>
                    <?php esc_html_e('OAuth credentials saved. Click below to connect your Google account.', 'gdrive-to-post'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(gdtp()->google_drive->get_auth_url()); ?>" class="button button-primary">
                        <span class="dashicons dashicons-google" style="vertical-align: middle; margin-top: -2px;"></span>
                        <?php esc_html_e('Connect with Google', 'gdrive-to-post'); ?>
                    </a>
                    <button type="button" id="gdtp-remove-oauth-creds" class="button gdtp-delete-btn">
                        <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
                        <?php esc_html_e('Remove Credentials', 'gdrive-to-post'); ?>
                    </button>
                </p>
            </div>

        <?php else : ?>
            <!-- No credentials - initial setup -->
            <p class="description">
                <?php esc_html_e('Connect your Google account to access Google Drive. Enter your OAuth credentials below, then click "Connect with Google".', 'gdrive-to-post'); ?>
            </p>
            <table class="form-table" style="margin-bottom: 0;">
                <tr>
                    <th><label for="gdtp-oauth-client-id"><?php esc_html_e('Client ID', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <input type="text" id="gdtp-oauth-client-id" class="large-text" placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxx.apps.googleusercontent.com" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th><label for="gdtp-oauth-client-secret"><?php esc_html_e('Client Secret', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <input type="password" id="gdtp-oauth-client-secret" class="regular-text" placeholder="GOCSPX-..." autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="button" id="gdtp-save-oauth-creds" class="button button-primary">
                            <span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: -2px;"></span>
                            <?php esc_html_e('Save & Connect with Google', 'gdrive-to-post'); ?>
                        </button>
                        <span id="gdtp-oauth-message" class="gdtp-inline-message"></span>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if (isset($_GET['gdtp_oauth_done'])) : ?>
            <!-- Show success message after OAuth redirect -->
        <?php endif; ?>

        <div class="gdtp-info-box" style="margin-top: 15px;">
            <h4><?php esc_html_e('Setup Instructions', 'gdrive-to-post'); ?></h4>
            <ol>
                <li><?php esc_html_e('Go to Google Cloud Console and create a new project (or use an existing one)', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Enable the Google Drive API for your project', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Go to Credentials > Create Credentials > OAuth client ID', 'gdrive-to-post'); ?></li>
                <li><?php esc_html_e('Set application type to "Web application"', 'gdrive-to-post'); ?></li>
                <li><?php printf(
                    esc_html__('Add this as an Authorized redirect URI: %s', 'gdrive-to-post'),
                    '<br><code>' . esc_html(gdtp()->google_drive->get_redirect_uri()) . '</code>'
                ); ?></li>
                <li><?php esc_html_e('Copy the Client ID and Client Secret into the fields above', 'gdrive-to-post'); ?></li>
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

        <?php if ($is_connected) : ?>
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
            <p class="description"><?php esc_html_e('Connect to Google Drive first to browse folders.', 'gdrive-to-post'); ?></p>
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

        <!-- AI Image Generation -->
        <div class="gdtp-section">
            <h2><?php esc_html_e('AI Image Generation', 'gdrive-to-post'); ?></h2>
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e('Automatically generate featured images for imported posts that have no images using OpenAI DALL-E 3.', 'gdrive-to-post'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('OpenAI API Key', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <div id="gdtp-openai-key-status">
                            <?php if ($has_openai_key) : ?>
                                <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Key Saved', 'gdrive-to-post'); ?></span>
                                <code>sk-...<?php echo esc_html($openai_key_hint); ?></code>
                                <button type="button" id="gdtp-test-openai" class="button">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: -2px;"></span>
                                    <?php esc_html_e('Test Connection', 'gdrive-to-post'); ?>
                                </button>
                                <button type="button" id="gdtp-remove-openai-key" class="button gdtp-delete-btn">
                                    <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
                                    <?php esc_html_e('Remove Key', 'gdrive-to-post'); ?>
                                </button>
                                <span id="gdtp-openai-message" class="gdtp-inline-message"></span>
                            <?php else : ?>
                                <input type="password" id="gdtp-openai-key-input" class="regular-text" placeholder="sk-..." autocomplete="off" style="width: 350px;">
                                <button type="button" id="gdtp-save-openai-key" class="button button-primary">
                                    <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: -2px;"></span>
                                    <?php esc_html_e('Save API Key', 'gdrive-to-post'); ?>
                                </button>
                                <span id="gdtp-openai-message" class="gdtp-inline-message"></span>
                                <p class="description">
                                    <?php esc_html_e('Enter your OpenAI API key. It will be stored encrypted. Get one at platform.openai.com/api-keys', 'gdrive-to-post'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="ai_image_enabled"><?php esc_html_e('Enable AI Images', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <label class="gdtp-toggle">
                            <input type="checkbox" name="ai_image_enabled" id="ai_image_enabled" <?php checked($ai_image_enabled); ?> <?php disabled(!$has_openai_key); ?>>
                            <span class="gdtp-toggle-slider"></span>
                            <?php esc_html_e('Generate featured images for posts with no images', 'gdrive-to-post'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="ai_image_style"><?php esc_html_e('Image Style', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <select name="ai_image_style" id="ai_image_style">
                            <?php foreach ($ai_image_styles as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($ai_image_style, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ai_image_prompt_template"><?php esc_html_e('Custom Prompt Template', 'gdrive-to-post'); ?></label></th>
                    <td>
                        <textarea name="ai_image_prompt_template" id="ai_image_prompt_template" class="large-text" rows="4" placeholder="<?php esc_attr_e('Leave blank to use default. Available placeholders: {title}, {summary}, {style}', 'gdrive-to-post'); ?>"><?php echo esc_textarea($ai_image_prompt_template); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Customize the DALL-E prompt. Placeholders: {title} (post title), {summary} (article summary), {style} (selected style). Leave blank for the default prompt.', 'gdrive-to-post'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Test Generation', 'gdrive-to-post'); ?></th>
                    <td>
                        <button type="button" id="gdtp-test-image-gen" class="button" <?php disabled(!$has_openai_key); ?>>
                            <span class="dashicons dashicons-format-image" style="vertical-align: middle; margin-top: -2px;"></span>
                            <?php esc_html_e('Generate Test Image', 'gdrive-to-post'); ?>
                        </button>
                        <span id="gdtp-test-image-message" class="gdtp-inline-message"></span>
                        <div id="gdtp-test-image-preview" style="margin-top: 10px; display: none;">
                            <img src="" alt="" style="max-width: 400px; border-radius: 4px; border: 1px solid #c3c4c7; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        </div>
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
