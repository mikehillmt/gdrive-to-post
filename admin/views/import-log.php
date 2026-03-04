<?php
/**
 * Import log page view for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$offset = ($current_page - 1) * $per_page;

// Filter
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

$imports = gdtp()->database->get_imports(array(
    'status' => $status_filter,
    'limit'  => $per_page,
    'offset' => $offset,
));

$total_items = gdtp()->database->get_imports_count($status_filter);
$total_pages = ceil($total_items / $per_page);

$total_all = gdtp()->database->get_imports_count();
$total_success = gdtp()->database->get_imports_count('success');
$total_error = gdtp()->database->get_imports_count('error');

$base_url = admin_url('admin.php?page=gdrive-to-post-log');
?>

<div class="wrap gdtp-wrap">
    <h1><?php esc_html_e('Import Log', 'gdrive-to-post'); ?></h1>

    <!-- Status filter tabs -->
    <ul class="subsubsub">
        <li>
            <a href="<?php echo esc_url($base_url); ?>" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">
                <?php esc_html_e('All', 'gdrive-to-post'); ?> <span class="count">(<?php echo esc_html($total_all); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('status', 'success', $base_url)); ?>" class="<?php echo $status_filter === 'success' ? 'current' : ''; ?>">
                <?php esc_html_e('Success', 'gdrive-to-post'); ?> <span class="count">(<?php echo esc_html($total_success); ?>)</span>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(add_query_arg('status', 'error', $base_url)); ?>" class="<?php echo $status_filter === 'error' ? 'current' : ''; ?>">
                <?php esc_html_e('Errors', 'gdrive-to-post'); ?> <span class="count">(<?php echo esc_html($total_error); ?>)</span>
            </a>
        </li>
    </ul>

    <table class="wp-list-table widefat fixed striped gdtp-imports-table">
        <thead>
            <tr>
                <th class="column-title"><?php esc_html_e('Document Title', 'gdrive-to-post'); ?></th>
                <th class="column-post"><?php esc_html_e('Post', 'gdrive-to-post'); ?></th>
                <th class="column-imported"><?php esc_html_e('Imported At', 'gdrive-to-post'); ?></th>
                <th class="column-status"><?php esc_html_e('Status', 'gdrive-to-post'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($imports)) : ?>
                <tr>
                    <td colspan="4" class="gdtp-no-data"><?php esc_html_e('No imports found.', 'gdrive-to-post'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($imports as $import) : ?>
                    <tr>
                        <td class="column-title">
                            <strong><?php echo esc_html($import->google_doc_title); ?></strong>
                            <br>
                            <span class="gdtp-doc-id"><?php echo esc_html($import->google_doc_id); ?></span>
                        </td>
                        <td class="column-post">
                            <?php if ($import->post_id && get_post($import->post_id)) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($import->post_id)); ?>">
                                    <?php echo esc_html(get_the_title($import->post_id)); ?>
                                </a>
                                <br>
                                <span class="gdtp-status gdtp-status-<?php echo esc_attr(get_post_status($import->post_id)); ?>">
                                    <?php echo esc_html(get_post_status($import->post_id)); ?>
                                </span>
                            <?php else : ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td class="column-imported">
                            <?php echo esc_html(gdtp_format_datetime($import->imported_at)); ?>
                            <br>
                            <span class="gdtp-time-ago"><?php echo esc_html(gdtp_time_ago($import->imported_at)); ?></span>
                        </td>
                        <td class="column-status">
                            <?php if ($import->status === 'success') : ?>
                                <span class="gdtp-status gdtp-status-active"><?php esc_html_e('Success', 'gdrive-to-post'); ?></span>
                            <?php else : ?>
                                <span class="gdtp-status gdtp-status-error"><?php esc_html_e('Error', 'gdrive-to-post'); ?></span>
                                <?php if ($import->error_message) : ?>
                                    <br><span class="gdtp-error-message"><?php echo esc_html($import->error_message); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php echo esc_html(sprintf(
                        _n('%s item', '%s items', $total_items, 'gdrive-to-post'),
                        number_format_i18n($total_items)
                    )); ?>
                </span>
                <span class="pagination-links">
                    <?php
                    $pagination_args = array(
                        'base'    => add_query_arg('paged', '%#%'),
                        'format'  => '',
                        'current' => $current_page,
                        'total'   => $total_pages,
                        'type'    => 'plain',
                    );

                    if ($status_filter) {
                        $pagination_args['add_args'] = array('status' => $status_filter);
                    }

                    echo paginate_links($pagination_args);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>
