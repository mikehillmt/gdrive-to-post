<?php
/**
 * Google Drive API integration for GDrive to Post
 *
 * Uses Service Account JWT auth with wp_remote_get/post - no Google SDK dependency.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Google_Drive {

    /**
     * Google OAuth2 token URL
     */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Google Drive API base URL
     */
    const API_BASE = 'https://www.googleapis.com/drive/v3';

    /**
     * Max recursion depth for folder scanning
     */
    const MAX_DEPTH = 10;

    /**
     * Get an access token using JWT auth
     */
    public function get_access_token() {
        // Check transient cache first
        $cached_token = get_transient('gdtp_access_token');
        if ($cached_token) {
            return $cached_token;
        }

        $sa_data = gdtp_get_service_account_data();
        if (!$sa_data) {
            return new WP_Error('no_credentials', __('Service account key not configured.', 'gdrive-to-post'));
        }

        if (!isset($sa_data['client_email']) || !isset($sa_data['private_key'])) {
            return new WP_Error('invalid_credentials', __('Service account key is missing required fields.', 'gdrive-to-post'));
        }

        // Build JWT
        $header = $this->base64url_encode(json_encode(array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        )));

        $now = time();
        $claims = $this->base64url_encode(json_encode(array(
            'iss'   => $sa_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.readonly',
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        )));

        $signature_input = $header . '.' . $claims;

        $private_key = openssl_pkey_get_private($sa_data['private_key']);
        if (!$private_key) {
            return new WP_Error('invalid_key', __('Could not parse private key. Ensure the key file is valid.', 'gdrive-to-post'));
        }

        $signature = '';
        $signed = openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            return new WP_Error('sign_failed', __('Failed to sign JWT token.', 'gdrive-to-post'));
        }

        $jwt = $signature_input . '.' . $this->base64url_encode($signature);

        // Exchange JWT for access token
        $response = wp_remote_post(self::TOKEN_URL, array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('token_error', sprintf(
                __('Google auth error: %s', 'gdrive-to-post'),
                $body['error_description'] ?? $body['error']
            ));
        }

        if (!isset($body['access_token'])) {
            return new WP_Error('no_token', __('No access token received from Google.', 'gdrive-to-post'));
        }

        // Cache for 50 minutes (tokens expire after 60)
        set_transient('gdtp_access_token', $body['access_token'], 50 * MINUTE_IN_SECONDS);

        return $body['access_token'];
    }

    /**
     * Make an authenticated API request to Google Drive
     */
    public function make_api_request($url, $args = array(), $retry = true) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $defaults = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 30,
        );

        $args = wp_parse_args($args, $defaults);

        // Ensure auth header is always set
        $args['headers']['Authorization'] = 'Bearer ' . $token;

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle 401 - token expired, retry once
        if ($code === 401 && $retry) {
            delete_transient('gdtp_access_token');
            return $this->make_api_request($url, $args, false);
        }

        // Handle 403 - forbidden
        if ($code === 403) {
            $error_data = json_decode($body, true);
            $message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Access denied.', 'gdrive-to-post');
            return new WP_Error('forbidden', $message);
        }

        // Handle 429 - rate limited
        if ($code === 429) {
            return new WP_Error('rate_limited', __('Google API rate limit exceeded. Please try again later.', 'gdrive-to-post'));
        }

        // Handle other errors
        if ($code >= 400) {
            $error_data = json_decode($body, true);
            $message = isset($error_data['error']['message']) ? $error_data['error']['message'] : sprintf(__('API error: HTTP %d', 'gdrive-to-post'), $code);
            return new WP_Error('api_error', $message);
        }

        return $body;
    }

    /**
     * Test connection to Google Drive API
     */
    public function test_connection() {
        $url = self::API_BASE . '/about?fields=user';
        $result = $this->make_api_request($url);

        if (is_wp_error($result)) {
            return $result;
        }

        $data = json_decode($result, true);

        if (isset($data['user'])) {
            return array(
                'success' => true,
                'email'   => $data['user']['emailAddress'] ?? '',
                'name'    => $data['user']['displayName'] ?? '',
            );
        }

        return new WP_Error('unknown_error', __('Unexpected response from Google Drive API.', 'gdrive-to-post'));
    }

    /**
     * List subfolders in a parent folder
     */
    public function list_folders($parent_id = 'root') {
        $query = sprintf(
            "'%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            $parent_id
        );

        $url = add_query_arg(array(
            'q'       => $query,
            'fields'  => 'files(id,name)',
            'orderBy' => 'name',
            'pageSize' => 100,
        ), self::API_BASE . '/files');

        $result = $this->make_api_request($url);

        if (is_wp_error($result)) {
            return $result;
        }

        $data = json_decode($result, true);

        return isset($data['files']) ? $data['files'] : array();
    }

    /**
     * List Google Docs in a folder
     */
    public function list_docs_in_folder($folder_id) {
        $query = sprintf(
            "'%s' in parents and mimeType = 'application/vnd.google-apps.document' and trashed = false",
            $folder_id
        );

        $url = add_query_arg(array(
            'q'        => $query,
            'fields'   => 'files(id,name,modifiedTime,createdTime)',
            'orderBy'  => 'createdTime desc',
            'pageSize' => 100,
        ), self::API_BASE . '/files');

        $result = $this->make_api_request($url);

        if (is_wp_error($result)) {
            return $result;
        }

        $data = json_decode($result, true);

        return isset($data['files']) ? $data['files'] : array();
    }

    /**
     * Recursively list all Google Docs in a folder and subfolders
     */
    public function list_docs_recursive($folder_id, $depth = 0) {
        if ($depth > self::MAX_DEPTH) {
            return array();
        }

        $all_docs = array();

        // Get docs in this folder
        $docs = $this->list_docs_in_folder($folder_id);
        if (is_wp_error($docs)) {
            gdtp_log('Error listing docs in folder ' . $folder_id . ': ' . $docs->get_error_message(), 'error');
            return $all_docs;
        }

        foreach ($docs as $doc) {
            $doc['folder_id'] = $folder_id;
            $all_docs[] = $doc;
        }

        // Get subfolders and recurse
        $subfolders = $this->list_folders($folder_id);
        if (is_wp_error($subfolders)) {
            gdtp_log('Error listing subfolders in ' . $folder_id . ': ' . $subfolders->get_error_message(), 'error');
            return $all_docs;
        }

        foreach ($subfolders as $subfolder) {
            // Rate limit protection
            if ($depth > 0) {
                usleep(100000); // 100ms delay between subfolder requests
            }

            $sub_docs = $this->list_docs_recursive($subfolder['id'], $depth + 1);
            $all_docs = array_merge($all_docs, $sub_docs);
        }

        return $all_docs;
    }

    /**
     * Export a Google Doc as HTML
     */
    public function export_doc_as_html($doc_id) {
        $url = self::API_BASE . '/files/' . urlencode($doc_id) . '/export?mimeType=text/html';

        $result = $this->make_api_request($url);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Download a file by URL (for images in exported HTML)
     */
    public function download_file($url) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('download_failed', sprintf(__('Failed to download file: HTTP %d', 'gdrive-to-post'), $code));
        }

        return array(
            'body'         => wp_remote_retrieve_body($response),
            'content_type' => wp_remote_retrieve_header($response, 'content-type'),
        );
    }

    /**
     * Base64url encode (no padding, URL-safe)
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
