<?php
/**
 * Notification handler for GDrive to Post
 *
 * Sends email notifications and manages one-click publish tokens.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Notifier {

    /**
     * Create a one-click publish token for a post
     */
    public function create_publish_token($post_id) {
        $token = gdtp_generate_token(64);
        $token_hash = wp_hash($token);
        $expiry_days = (int) get_option('gdtp_publish_token_expiry', 7);

        update_post_meta($post_id, '_gdtp_publish_token', $token_hash);
        update_post_meta($post_id, '_gdtp_publish_token_expiry', time() + ($expiry_days * DAY_IN_SECONDS));

        return $token;
    }

    /**
     * Process and validate a publish token
     */
    public function process_publish_token($token) {
        global $wpdb;

        $token_hash = wp_hash($token);

        // Find the post with this token
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_gdtp_publish_token' AND meta_value = %s LIMIT 1",
            $token_hash
        ));

        if (!$post_id) {
            wp_die(
                __('Invalid or expired publish link. The token may have already been used.', 'gdrive-to-post'),
                __('Invalid Link', 'gdrive-to-post'),
                array('response' => 403)
            );
            return;
        }

        // Check expiry
        $expiry = get_post_meta($post_id, '_gdtp_publish_token_expiry', true);
        if ($expiry && time() > (int) $expiry) {
            // Clean up expired token
            delete_post_meta($post_id, '_gdtp_publish_token');
            delete_post_meta($post_id, '_gdtp_publish_token_expiry');

            wp_die(
                __('This publish link has expired. Please log in to WordPress to publish the post.', 'gdrive-to-post'),
                __('Link Expired', 'gdrive-to-post'),
                array('response' => 403)
            );
            return;
        }

        // Check post exists and is a draft
        $post = get_post($post_id);
        if (!$post) {
            wp_die(
                __('The associated post could not be found.', 'gdrive-to-post'),
                __('Post Not Found', 'gdrive-to-post'),
                array('response' => 404)
            );
            return;
        }

        if ($post->post_status === 'publish') {
            // Already published - clean up token and redirect
            delete_post_meta($post_id, '_gdtp_publish_token');
            delete_post_meta($post_id, '_gdtp_publish_token_expiry');

            wp_redirect(get_permalink($post_id));
            exit;
        }

        // Publish the post
        wp_update_post(array(
            'ID'          => $post_id,
            'post_status' => 'publish',
        ));

        // Delete the token (single-use)
        delete_post_meta($post_id, '_gdtp_publish_token');
        delete_post_meta($post_id, '_gdtp_publish_token_expiry');

        do_action('gdtp_post_published_via_token', $post_id);

        // Redirect to the published post
        wp_redirect(get_permalink($post_id));
        exit;
    }

    /**
     * Send sync notification email
     */
    public function send_sync_notification($imported_posts) {
        $to = get_option('gdtp_notification_email', get_option('admin_email'));

        if (empty($to)) {
            return false;
        }

        $count = count($imported_posts);
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            __('[%s] %d new post(s) imported from Google Drive', 'gdrive-to-post'),
            $site_name,
            $count
        );

        // Build HTML email body
        $body = $this->build_email_body($imported_posts, $site_name);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Send a test email
     */
    public function send_test_email() {
        $to = get_option('gdtp_notification_email', get_option('admin_email'));

        if (empty($to)) {
            return new WP_Error('no_email', __('No notification email configured.', 'gdrive-to-post'));
        }

        $site_name = get_bloginfo('name');
        $subject = sprintf(__('[%s] GDrive to Post - Test Email', 'gdrive-to-post'), $site_name);

        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto;">';
        $body .= '<h2 style="color: #1d2327;">' . esc_html__('Test Email', 'gdrive-to-post') . '</h2>';
        $body .= '<p>' . esc_html__('This is a test email from GDrive to Post. If you received this, email notifications are working correctly.', 'gdrive-to-post') . '</p>';
        $body .= '<p style="color: #50575e; font-size: 12px;">' . esc_html(sprintf(__('Sent from %s', 'gdrive-to-post'), $site_name)) . '</p>';
        $body .= '</div>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        );

        $sent = wp_mail($to, $subject, $body, $headers);

        if ($sent) {
            return true;
        }

        return new WP_Error('send_failed', __('Failed to send test email. Check your WordPress mail configuration.', 'gdrive-to-post'));
    }

    /**
     * Build the notification email HTML body
     */
    private function build_email_body($imported_posts, $site_name) {
        $body = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">';

        $body .= '<h2 style="color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px;">';
        $body .= esc_html(sprintf(__('%d New Post(s) Imported', 'gdrive-to-post'), count($imported_posts)));
        $body .= '</h2>';

        $body .= '<p style="color: #50575e;">';
        $body .= esc_html__('The following Google Docs have been imported as draft posts:', 'gdrive-to-post');
        $body .= '</p>';

        $body .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $body .= '<thead><tr>';
        $body .= '<th style="text-align: left; padding: 10px; border-bottom: 2px solid #c3c4c7; color: #1d2327;">' . esc_html__('Post Title', 'gdrive-to-post') . '</th>';
        $body .= '<th style="text-align: center; padding: 10px; border-bottom: 2px solid #c3c4c7; color: #1d2327;">' . esc_html__('Actions', 'gdrive-to-post') . '</th>';
        $body .= '</tr></thead><tbody>';

        foreach ($imported_posts as $post_info) {
            $edit_url = admin_url('post.php?post=' . $post_info['post_id'] . '&action=edit');
            $publish_url = add_query_arg('gdtp_publish', $post_info['publish_token'], home_url('/'));

            $body .= '<tr>';
            $body .= '<td style="padding: 12px 10px; border-bottom: 1px solid #f0f0f1;">';
            $body .= '<strong>' . esc_html($post_info['post_title']) . '</strong>';
            $body .= '</td>';
            $body .= '<td style="padding: 12px 10px; border-bottom: 1px solid #f0f0f1; text-align: center; white-space: nowrap;">';
            $body .= '<a href="' . esc_url($edit_url) . '" style="display: inline-block; padding: 6px 12px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 3px; font-size: 13px; margin-right: 5px;">' . esc_html__('Edit', 'gdrive-to-post') . '</a>';
            $body .= '<a href="' . esc_url($publish_url) . '" style="display: inline-block; padding: 6px 12px; background: #00a32a; color: #fff; text-decoration: none; border-radius: 3px; font-size: 13px;">' . esc_html__('Publish Now', 'gdrive-to-post') . '</a>';
            $body .= '</td>';
            $body .= '</tr>';
        }

        $body .= '</tbody></table>';

        $body .= '<p style="color: #50575e; font-size: 12px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #c3c4c7;">';
        $expiry_days = get_option('gdtp_publish_token_expiry', 7);
        $body .= esc_html(sprintf(__('Publish links expire after %d days. You can also edit and publish posts from the WordPress admin.', 'gdrive-to-post'), $expiry_days));
        $body .= '<br>';
        $body .= esc_html(sprintf(__('Sent from %s - GDrive to Post', 'gdrive-to-post'), $site_name));
        $body .= '</p>';

        $body .= '</div>';

        return $body;
    }
}
