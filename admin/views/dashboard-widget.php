<?php
/**
 * Dashboard widget view for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = gdtp()->database->get_import_stats();
$recent = gdtp()->database->get_recent_imports(3);
$is_configured = gdtp_is_configured();
?>

<div class="gdtp-dashboard-widget">
    <div class="gdtp-widget-stats">
        <div>
            <span class="gdtp-widget-stat-value"><?php echo esc_html($stats['success']); ?></span>
            <span class="gdtp-widget-stat-label"><?php esc_html_e('Imported', 'gdrive-to-post'); ?></span>
        </div>
        <div>
            <span class="gdtp-widget-stat-value"><?php echo esc_html($stats['today']); ?></span>
            <span class="gdtp-widget-stat-label"><?php esc_html_e('Today', 'gdrive-to-post'); ?></span>
        </div>
        <div>
            <span class="gdtp-widget-stat-value"><?php echo esc_html($stats['failed']); ?></span>
            <span class="gdtp-widget-stat-label"><?php esc_html_e('Errors', 'gdrive-to-post'); ?></span>
        </div>
    </div>

    <?php if (!empty($recent)) : ?>
        <ul class="gdtp-widget-imports">
            <?php foreach ($recent as $import) : ?>
                <li>
                    <?php if ($import->post_id && get_post($import->post_id)) : ?>
                        <a href="<?php echo esc_url(get_edit_post_link($import->post_id)); ?>">
                            <?php echo esc_html($import->google_doc_title); ?>
                        </a>
                    <?php else : ?>
                        <?php echo esc_html($import->google_doc_title); ?>
                    <?php endif; ?>
                    <span class="gdtp-widget-import-time"><?php echo esc_html(gdtp_time_ago($import->imported_at)); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif (!$is_configured) : ?>
        <p class="gdtp-widget-empty"><?php esc_html_e('Configure your Google Drive connection to start importing.', 'gdrive-to-post'); ?></p>
    <?php else : ?>
        <p class="gdtp-widget-empty"><?php esc_html_e('No imports yet. Run a sync to get started.', 'gdrive-to-post'); ?></p>
    <?php endif; ?>

    <div class="gdtp-widget-links">
        <a href="<?php echo esc_url(admin_url('admin.php?page=gdrive-to-post')); ?>" class="button"><?php esc_html_e('Dashboard', 'gdrive-to-post'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=gdrive-to-post-settings')); ?>" class="button"><?php esc_html_e('Settings', 'gdrive-to-post'); ?></a>
    </div>
</div>
