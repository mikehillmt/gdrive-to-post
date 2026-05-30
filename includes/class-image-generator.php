<?php
/**
 * AI Image Generator for GDrive to Post
 *
 * Generates featured images for posts using OpenAI DALL-E 3.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Image_Generator {

    /**
     * OpenAI API endpoint for image generation
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/images/generations';

    /**
     * Available image styles
     */
    public static function get_image_styles() {
        return array(
            'photographic' => __('Photographic', 'gdrive-to-post'),
            'illustration' => __('Illustration', 'gdrive-to-post'),
            'digital-art'  => __('Digital Art', 'gdrive-to-post'),
            '3d-render'    => __('3D Render', 'gdrive-to-post'),
            'watercolor'   => __('Watercolor', 'gdrive-to-post'),
            'flat-design'  => __('Flat Design', 'gdrive-to-post'),
            'minimalist'   => __('Minimalist', 'gdrive-to-post'),
        );
    }

    /**
     * Generate and set a featured image for a post
     *
     * @param int    $post_id  The post ID.
     * @param string $content  Post HTML content.
     * @param string $title    Post title.
     * @return int|WP_Error Attachment ID on success.
     */
    public function generate_featured_image($post_id, $content, $title) {
        if (!get_option('gdtp_ai_image_enabled', false)) {
            return new WP_Error('disabled', __('AI image generation is disabled.', 'gdrive-to-post'));
        }

        $api_key = $this->get_api_key();
        if (!$api_key) {
            return new WP_Error('no_api_key', __('No OpenAI API key configured.', 'gdrive-to-post'));
        }

        // Build the prompt
        $summary = $this->extract_summary($content, $title);
        $prompt = $this->build_prompt($summary, $title);

        // Call DALL-E 3
        $image_url = $this->call_dalle_api($prompt, $api_key);
        if (is_wp_error($image_url)) {
            return $image_url;
        }

        // Download and upload to Media Library
        $attachment_id = $this->download_and_upload($image_url, $title, $post_id);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Set as featured image
        set_post_thumbnail($post_id, $attachment_id);

        // Store metadata
        update_post_meta($post_id, '_gdtp_ai_image_generated', true);
        update_post_meta($post_id, '_gdtp_ai_image_prompt', $prompt);

        gdtp_log('AI featured image generated for post ' . $post_id . ' (attachment: ' . $attachment_id . ')');

        return $attachment_id;
    }

    /**
     * Extract a summary from article content
     *
     * @param string $content HTML content.
     * @param string $title   Post title.
     * @return string Summary text.
     */
    private function extract_summary($content, $title) {
        // Strip HTML tags
        $text = wp_strip_all_tags($content);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Take first ~500 characters for the summary
        if (strlen($text) > 500) {
            $text = substr($text, 0, 500);
            // Cut at last complete sentence or word
            $last_period = strrpos($text, '.');
            $last_space = strrpos($text, ' ');
            if ($last_period !== false && $last_period > 300) {
                $text = substr($text, 0, $last_period + 1);
            } elseif ($last_space !== false) {
                $text = substr($text, 0, $last_space);
            }
        }

        return $text;
    }

    /**
     * Build a DALL-E 3 prompt from the summary and title
     *
     * @param string $summary Article summary.
     * @param string $title   Post title.
     * @return string The prompt.
     */
    private function build_prompt($summary, $title) {
        $style = get_option('gdtp_ai_image_style', 'photographic');
        $template = get_option('gdtp_ai_image_prompt_template', '');

        // Use custom template or default
        if (empty($template)) {
            $template = "Create a {style} style image for a blog post titled '{title}'. The article is about: {summary}. The image should be visually compelling and suitable as a featured blog image. Do not include any text or words in the image.";
        }

        $prompt = str_replace(
            array('{title}', '{summary}', '{style}'),
            array($title, $summary, $style),
            $template
        );

        // DALL-E 3 has a 4000 character limit
        if (strlen($prompt) > 4000) {
            $prompt = substr($prompt, 0, 4000);
        }

        return $prompt;
    }

    /**
     * Call the OpenAI DALL-E 3 API
     *
     * @param string $prompt  The image generation prompt.
     * @param string $api_key The API key.
     * @return string|WP_Error Image URL on success.
     */
    private function call_dalle_api($prompt, $api_key) {
        $response = wp_remote_post(self::API_ENDPOINT, array(
            'timeout' => 120,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'model'           => 'dall-e-3',
                'prompt'          => $prompt,
                'n'               => 1,
                'size'            => '1792x1024',
                'quality'         => 'standard',
                'response_format' => 'url',
            )),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown API error.', 'gdrive-to-post');
            return new WP_Error('api_error', $error_msg);
        }

        if (empty($body['data'][0]['url'])) {
            return new WP_Error('no_image_url', __('No image URL in API response.', 'gdrive-to-post'));
        }

        return $body['data'][0]['url'];
    }

    /**
     * Download a generated image and upload to WordPress Media Library
     *
     * @param string $image_url The image URL to download.
     * @param string $title     Title for alt text.
     * @param int    $post_id   Post to attach the image to.
     * @return int|WP_Error Attachment ID on success.
     */
    private function download_and_upload($image_url, $title, $post_id) {
        // Download the image
        $response = wp_remote_get($image_url, array('timeout' => 60));

        if (is_wp_error($response)) {
            return new WP_Error('download_failed', $response->get_error_message());
        }

        $image_data = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        if (empty($image_data)) {
            return new WP_Error('empty_image', __('Downloaded image is empty.', 'gdrive-to-post'));
        }

        // Determine file extension
        $ext = 'png';
        if (strpos($content_type, 'jpeg') !== false || strpos($content_type, 'jpg') !== false) {
            $ext = 'jpg';
        } elseif (strpos($content_type, 'webp') !== false) {
            $ext = 'webp';
        }

        // Generate filename
        $safe_title = sanitize_title($title);
        $safe_title = substr($safe_title, 0, 50);
        $filename = sprintf('gdtp-ai-%s-%s.%s', $safe_title, substr(md5(uniqid()), 0, 8), $ext);

        // Write to temp file
        $tmp_file = wp_tempnam($filename);
        if (!$tmp_file) {
            return new WP_Error('temp_file', __('Could not create temporary file.', 'gdrive-to-post'));
        }

        file_put_contents($tmp_file, $image_data);

        // Prepare file array for media_handle_sideload
        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        );

        // Need media functions
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $attachment_id = media_handle_sideload($file_array, $post_id, $title);

        // Clean up temp file if still exists
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Set alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($title));

        return $attachment_id;
    }

    /**
     * Get the decrypted OpenAI API key
     *
     * @return string|false The API key or false if not set.
     */
    public function get_api_key() {
        $encrypted = get_option('gdtp_openai_api_key', '');

        if (empty($encrypted)) {
            return false;
        }

        $decrypted = gdtp_decrypt($encrypted);

        return !empty($decrypted) ? $decrypted : false;
    }

    /**
     * Test the OpenAI API connection
     *
     * @return true|WP_Error
     */
    public function test_connection() {
        $api_key = $this->get_api_key();

        if (!$api_key) {
            return new WP_Error('no_api_key', __('No OpenAI API key configured.', 'gdrive-to-post'));
        }

        // Lightweight check: verify the key has access to DALL-E 3
        $response = wp_remote_get('https://api.openai.com/v1/models/dall-e-3', array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_request_failed', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 401) {
            return new WP_Error('invalid_key', __('Invalid API key.', 'gdrive-to-post'));
        }

        if ($code !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : __('API returned error code: ', 'gdrive-to-post') . $code;
            return new WP_Error('api_error', $error_msg);
        }

        return true;
    }
}
