<?php
/**
 * Content processor for GDrive to Post
 *
 * Cleans Google Docs HTML export and uploads images to WP Media Library.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Content_Processor {

    /**
     * Process exported HTML from Google Docs
     *
     * @param string $html Raw HTML from Google Docs export
     * @param string $doc_title Document title
     * @return array {content, images, errors}
     */
    public function process($html, $doc_title) {
        $result = array(
            'content' => '',
            'images'  => array(),
            'errors'  => array(),
        );

        if (empty($html)) {
            $result['errors'][] = __('Empty HTML content received.', 'gdrive-to-post');
            return $result;
        }

        // Clean the HTML first
        $html = $this->clean_html($html, $doc_title);

        // Extract and upload images
        $image_result = $this->extract_and_upload_images($html);
        $html = $image_result['html'];
        $result['images'] = $image_result['images'];
        $result['errors'] = array_merge($result['errors'], $image_result['errors']);

        // Final sanitization
        $result['content'] = gdtp_sanitize_doc_html($html);

        return $result;
    }

    /**
     * Extract images from HTML, upload to Media Library, replace URLs
     */
    public function extract_and_upload_images($html) {
        $result = array(
            'html'   => $html,
            'images' => array(),
            'errors' => array(),
        );

        // Use DOMDocument to find images
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $images = $doc->getElementsByTagName('img');
        $replacements = array();

        foreach ($images as $img) {
            $src = $img->getAttribute('src');

            if (empty($src)) {
                continue;
            }

            // Skip already-uploaded images (local URLs)
            if (strpos($src, home_url()) === 0) {
                continue;
            }

            // Download the image
            $downloaded = $this->download_image($src);
            if (is_wp_error($downloaded)) {
                $result['errors'][] = sprintf(
                    __('Failed to download image: %s', 'gdrive-to-post'),
                    $downloaded->get_error_message()
                );
                continue;
            }

            // Upload to Media Library
            $attachment_id = $this->upload_to_media_library($downloaded);
            if (is_wp_error($attachment_id)) {
                $result['errors'][] = sprintf(
                    __('Failed to upload image: %s', 'gdrive-to-post'),
                    $attachment_id->get_error_message()
                );
                continue;
            }

            $new_url = wp_get_attachment_url($attachment_id);
            $replacements[$src] = $new_url;
            $result['images'][] = $attachment_id;
        }

        // Replace image URLs in the HTML string
        foreach ($replacements as $old_url => $new_url) {
            $result['html'] = str_replace($old_url, $new_url, $result['html']);
        }

        return $result;
    }

    /**
     * Clean Google Docs HTML export
     */
    public function clean_html($html, $doc_title = '') {
        // Extract body content if full HTML document
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $matches)) {
            $html = $matches[1];
        }

        // Remove Google Docs style blocks
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Remove all inline styles
        $html = preg_replace('/\s+style="[^"]*"/i', '', $html);

        // Remove all class attributes
        $html = preg_replace('/\s+class="[^"]*"/i', '', $html);

        // Remove all id attributes
        $html = preg_replace('/\s+id="[^"]*"/i', '', $html);

        // Convert style-based bold to <strong>
        // Google Docs uses <span style="font-weight:700">text</span>
        $html = preg_replace('/<span[^>]*font-weight:\s*(?:bold|[6-9]\d{2}|[1-9]\d{3})[^>]*>(.*?)<\/span>/is', '<strong>$1</strong>', $html);

        // Convert style-based italic to <em>
        $html = preg_replace('/<span[^>]*font-style:\s*italic[^>]*>(.*?)<\/span>/is', '<em>$1</em>', $html);

        // Convert style-based underline to <u>
        $html = preg_replace('/<span[^>]*text-decoration:\s*underline[^>]*>(.*?)<\/span>/is', '<u>$1</u>', $html);

        // Convert style-based strikethrough to <s>
        $html = preg_replace('/<span[^>]*text-decoration:\s*line-through[^>]*>(.*?)<\/span>/is', '<s>$1</s>', $html);

        // Collapse empty spans
        $html = preg_replace('/<span[^>]*>\s*<\/span>/i', '', $html);

        // Remove remaining empty spans (wrapping content with no attributes)
        $html = preg_replace('/<span>(.*?)<\/span>/is', '$1', $html);

        // Remove document title duplicate (Google Docs often puts the title as the first element)
        if (!empty($doc_title)) {
            $escaped_title = preg_quote($doc_title, '/');
            // Remove title if it appears as the first <p> or <h1> exactly matching
            $html = preg_replace('/^\s*<(?:p|h1)[^>]*>\s*' . $escaped_title . '\s*<\/(?:p|h1)>/i', '', $html, 1);
        }

        // Clean empty paragraphs
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);
        $html = preg_replace('/<p[^>]*>\s*<br\s*\/?>\s*<\/p>/i', '', $html);

        // Normalize whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        $html = trim($html);

        return $html;
    }

    /**
     * Download an image from a URL
     */
    private function download_image($url) {
        // Try Google Drive API auth for Google-hosted images
        if (strpos($url, 'googleusercontent.com') !== false || strpos($url, 'google.com') !== false) {
            $drive = gdtp()->google_drive;
            $downloaded = $drive->download_file($url);

            if (!is_wp_error($downloaded)) {
                return $downloaded;
            }
        }

        // Fall back to unauthenticated download
        $response = wp_remote_get($url, array('timeout' => 60));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('download_failed', sprintf(__('HTTP %d', 'gdrive-to-post'), $code));
        }

        return array(
            'body'         => wp_remote_retrieve_body($response),
            'content_type' => wp_remote_retrieve_header($response, 'content-type'),
        );
    }

    /**
     * Upload image data to WordPress Media Library
     */
    private function upload_to_media_library($file_data) {
        if (empty($file_data['body'])) {
            return new WP_Error('empty_file', __('Empty file data.', 'gdrive-to-post'));
        }

        // Determine extension from content type
        $ext = $this->get_extension_from_content_type($file_data['content_type']);
        $filename = 'gdtp-' . wp_generate_uuid4() . '.' . $ext;

        // Write to temp file
        $tmp_file = wp_tempnam($filename);
        file_put_contents($tmp_file, $file_data['body']);

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp_file,
        );

        // Use media_handle_sideload
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_sideload($file_array, 0, __('Imported from Google Doc', 'gdrive-to-post'));

        // Clean up temp file if still exists
        if (file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        return $attachment_id;
    }

    /**
     * Get file extension from content type
     */
    private function get_extension_from_content_type($content_type) {
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp'  => 'bmp',
        );

        $content_type = strtolower(trim(explode(';', $content_type)[0]));

        return isset($map[$content_type]) ? $map[$content_type] : 'png';
    }
}
