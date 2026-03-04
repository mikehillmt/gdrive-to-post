<?php
/**
 * Helper functions for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if plugin is fully configured
 */
function gdtp_is_configured() {
    $key = get_option('gdtp_service_account_key', '');
    $folder = get_option('gdtp_folder_id', '');

    return !empty($key) && !empty($folder);
}

/**
 * Get service account email from stored key
 */
function gdtp_get_service_account_email() {
    $data = gdtp_get_service_account_data();

    if ($data && isset($data['client_email'])) {
        return $data['client_email'];
    }

    return '';
}

/**
 * Get decoded service account data
 */
function gdtp_get_service_account_data() {
    $key_json = get_option('gdtp_service_account_key', '');

    if (empty($key_json)) {
        return null;
    }

    $data = json_decode($key_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $data;
}

/**
 * Generate a random token
 */
function gdtp_generate_token($length = 64) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    }
    return bin2hex(openssl_random_pseudo_bytes($length / 2));
}

/**
 * Format datetime for display
 */
function gdtp_format_datetime($datetime) {
    if (empty($datetime)) {
        return __('Never', 'gdrive-to-post');
    }

    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($datetime));
}

/**
 * Get human-readable time ago string
 */
function gdtp_time_ago($datetime) {
    if (empty($datetime)) {
        return __('Never', 'gdrive-to-post');
    }

    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return __('Just now', 'gdrive-to-post');
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return sprintf(_n('%d minute ago', '%d minutes ago', $mins, 'gdrive-to-post'), $mins);
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'gdrive-to-post'), $hours);
    } elseif ($diff < 604800) {
        $days = round($diff / 86400);
        return sprintf(_n('%d day ago', '%d days ago', $days, 'gdrive-to-post'), $days);
    }

    return gdtp_format_datetime($datetime);
}

/**
 * Get available sync frequency options
 */
function gdtp_get_sync_frequencies() {
    return array(
        'hourly'     => __('Hourly', 'gdrive-to-post'),
        'twicedaily' => __('Twice Daily', 'gdrive-to-post'),
        'daily'      => __('Daily', 'gdrive-to-post'),
        'weekly'     => __('Weekly', 'gdrive-to-post'),
    );
}

/**
 * Sanitize HTML content from Google Docs
 */
function gdtp_sanitize_doc_html($html) {
    $allowed_tags = array(
        'h1'         => array(),
        'h2'         => array(),
        'h3'         => array(),
        'h4'         => array(),
        'h5'         => array(),
        'h6'         => array(),
        'p'          => array(),
        'br'         => array(),
        'strong'     => array(),
        'b'          => array(),
        'em'         => array(),
        'i'          => array(),
        'u'          => array(),
        's'          => array(),
        'a'          => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array()),
        'img'        => array('src' => array(), 'alt' => array(), 'width' => array(), 'height' => array()),
        'ul'         => array(),
        'ol'         => array('start' => array()),
        'li'         => array(),
        'blockquote' => array(),
        'table'      => array(),
        'thead'      => array(),
        'tbody'      => array(),
        'tr'         => array(),
        'th'         => array(),
        'td'         => array('colspan' => array(), 'rowspan' => array()),
        'hr'         => array(),
        'sup'        => array(),
        'sub'        => array(),
        'code'       => array(),
        'pre'        => array(),
    );

    return wp_kses($html, $allowed_tags);
}

/**
 * Log a message for debugging
 */
function gdtp_log($message, $level = 'info') {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    error_log(sprintf('[GDrive to Post][%s] %s', strtoupper($level), $message));
}
