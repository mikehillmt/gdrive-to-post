<?php
/**
 * Dashboard page view for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_configured = gdtp_is_configured();
$sa_email = gdtp_get_service_account_email();
$folder_id = get_option('gdtp_folder_id', '');
$folder_name = get_option('gdtp_folder_name', '');
$sync_status = gdtp()->sync->get_sync_status();
$stats = $sync_status['stats'];
$recent_imports = gdtp()->database->get_recent_imports(10);
?>

<div class="wrap gdtp-wrap">
    <h1><?php esc_html_e('GDrive to Post', 'gdrive-to-post'); ?></h1>

    <?php settings_errors('gdtp_settings'); ?>

    <!-- Quick Stats -->
    <div class="gdtp-stats-summary">
        <div class="gdtp-stat-card">
            <span class="gdtp-stat-value"><?php echo esc_html($stats['total']); ?></span>
            <span class="gdtp-stat-label"><?php esc_html_e('Total Imports', 'gdrive-to-post'); ?></span>
        </div>
        <div class="gdtp-stat-card">
            <span class="gdtp-stat-value"><?php echo esc_html($stats['success']); ?></span>
            <span class="gdtp-stat-label"><?php esc_html_e('Successful', 'gdrive-to-post'); ?></span>
        </div>
        <div class="gdtp-stat-card">
            <span class="gdtp-stat-value"><?php echo esc_html($stats['failed']); ?></span>
            <span class="gdtp-stat-label"><?php esc_html_e('Failed', 'gdrive-to-post'); ?></span>
        </div>
        <div class="gdtp-stat-card">
            <span class="gdtp-stat-value"><?php echo esc_html($stats['today']); ?></span>
            <span class="gdtp-stat-label"><?php esc_html_e('Today', 'gdrive-to-post'); ?></span>
        </div>
    </div>

    <!-- Connection Status -->
    <div class="gdtp-section">
        <h2><?php esc_html_e('Connection Status', 'gdrive-to-post'); ?></h2>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Service Account', 'gdrive-to-post'); ?></th>
                <td>
                    <?php if ($sa_email) : ?>
                        <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Connected', 'gdrive-to-post'); ?></span>
                        <code><?php echo esc_html($sa_email); ?></code>
                    <?php else : ?>
                        <span class="gdtp-status gdtp-status-error"><?php esc_html_e('Not configured', 'gdrive-to-post'); ?></span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gdrive-to-post-settings')); ?>"><?php esc_html_e('Configure', 'gdrive-to-post'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Source Folder', 'gdrive-to-post'); ?></th>
                <td>
                    <?php if ($folder_id) : ?>
                        <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Selected', 'gdrive-to-post'); ?></span>
                        <strong><?php echo esc_html($folder_name); ?></strong>
                        <code style="font-size: 11px; color: #50575e;"><?php echo esc_html($folder_id); ?></code>
                    <?php else : ?>
                        <span class="gdtp-status gdtp-status-error"><?php esc_html_e('Not selected', 'gdrive-to-post'); ?></span>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gdrive-to-post-settings')); ?>"><?php esc_html_e('Select folder', 'gdrive-to-post'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Sync Status -->
    <div class="gdtp-section">
        <h2><?php esc_html_e('Sync Status', 'gdrive-to-post'); ?></h2>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Status', 'gdrive-to-post'); ?></th>
                <td id="gdtp-sync-status-display">
                    <?php if ($sync_status['is_syncing']) : ?>
                        <span class="gdtp-status gdtp-status-syncing"><?php esc_html_e('Syncing...', 'gdrive-to-post'); ?></span>
                    <?php else : ?>
                        <span class="gdtp-status gdtp-status-idle"><?php esc_html_e('Idle', 'gdrive-to-post'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Last Sync', 'gdrive-to-post'); ?></th>
                <td><?php echo esc_html($sync_status['last_sync'] ? gdtp_time_ago($sync_status['last_sync']) : __('Never', 'gdrive-to-post')); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Next Sync', 'gdrive-to-post'); ?></th>
                <td><?php echo esc_html($sync_status['next_sync'] ? gdtp_format_datetime($sync_status['next_sync']) : __('Not scheduled', 'gdrive-to-post')); ?></td>
            </tr>
        </table>

        <p>
            <button type="button" id="gdtp-sync-now" class="button button-primary" <?php echo !$is_configured ? 'disabled' : ''; ?>>
                <span class="dashicons dashicons-update" style="vertical-align: middle; margin-top: -2px;"></span>
                <?php esc_html_e('Sync Now', 'gdrive-to-post'); ?>
            </button>
            <span id="gdtp-sync-message" class="gdtp-inline-message"></span>
        </p>
    </div>

    <!-- Recent Imports -->
    <div class="gdtp-section">
        <h2>
            <?php esc_html_e('Recent Imports', 'gdrive-to-post'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=gdrive-to-post-log')); ?>" class="page-title-action"><?php esc_html_e('View All', 'gdrive-to-post'); ?></a>
        </h2>

        <?php if (empty($recent_imports)) : ?>
            <p class="gdtp-no-data"><?php esc_html_e('No imports yet. Configure your Google Drive connection and run a sync to get started.', 'gdrive-to-post'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Document Title', 'gdrive-to-post'); ?></th>
                        <th><?php esc_html_e('Post', 'gdrive-to-post'); ?></th>
                        <th><?php esc_html_e('Imported', 'gdrive-to-post'); ?></th>
                        <th><?php esc_html_e('Status', 'gdrive-to-post'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_imports as $import) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($import->google_doc_title); ?></strong></td>
                            <td>
                                <?php if ($import->post_id && get_post($import->post_id)) : ?>
                                    <a href="<?php echo esc_url(get_edit_post_link($import->post_id)); ?>">
                                        <?php echo esc_html(get_the_title($import->post_id)); ?>
                                    </a>
                                    <span class="gdtp-status gdtp-status-<?php echo esc_attr(get_post_status($import->post_id)); ?>">
                                        <?php echo esc_html(get_post_status($import->post_id)); ?>
                                    </span>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(gdtp_time_ago($import->imported_at)); ?></td>
                            <td>
                                <?php if ($import->status === 'success') : ?>
                                    <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Success', 'gdrive-to-post'); ?></span>
                                <?php else : ?>
                                    <span class="gdtp-status gdtp-status-error"><?php esc_html_e('Error', 'gdrive-to-post'); ?></span>
                                    <?php if ($import->error_message) : ?>
                                        <span class="gdtp-error-hint" title="<?php echo esc_attr($import->error_message); ?>">(?)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
