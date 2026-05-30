<?php
/**
 * Google Drive API integration for GDrive to Post
 *
 * Uses OAuth 2.0 auth with wp_remote_get/post - no Google SDK dependency.
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Google_Drive {

    /**
     * Google OAuth2 endpoints
     */
    const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /**
     * Google Drive API base URL
     */
    const API_BASE = 'https://www.googleapis.com/drive/v3';

    /**
     * OAuth scope
     */
    const SCOPE = 'https://www.googleapis.com/auth/drive';

    /**
     * Max recursion depth for folder scanning
     */
    const MAX_DEPTH = 10;

    /**
     * Get the OAuth redirect URI
     *
     * Uses a static HTML callback page to avoid Wordfence WAF blocking
     * Google's iss/scope URL parameters in the callback query string.
     */
    public function get_redirect_uri() {
        return plugins_url('oauth-callback.html', dirname(__FILE__));
    }

    /**
     * Generate the Google OAuth authorization URL
     */
    public function get_auth_url() {
        $client_id = get_option('gdtp_oauth_client_id', '');

        if (empty($client_id)) {
            return new WP_Error('no_client_id', __('OAuth Client ID not configured.', 'gdrive-to-post'));
        }

        // Use WP nonce as state - deterministic per user/time, no transient needed
        $state = wp_create_nonce('gdtp_oauth_state');

        $params = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $this->get_redirect_uri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        );

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle the OAuth callback - exchange auth code for tokens
     */
    public function handle_callback($code, $state) {
        // Verify state using WP nonce
        if (!wp_verify_nonce($state, 'gdtp_oauth_state')) {
            error_log('[GDTP] Nonce verification failed for state: ' . $state);
            return new WP_Error('invalid_state', __('Invalid OAuth state. Please try connecting again.', 'gdrive-to-post'));
        }

        $client_id = get_option('gdtp_oauth_client_id', '');
        $client_secret = gdtp_decrypt(get_option('gdtp_oauth_client_secret', ''));

        if (empty($client_id) || empty($client_secret)) {
            error_log('[GDTP] OAuth credentials missing. client_id empty: ' . (empty($client_id) ? 'yes' : 'no') . ', client_secret decrypt empty: ' . (empty($client_secret) ? 'yes' : 'no'));
            return new WP_Error('no_credentials', __('OAuth credentials not configured.', 'gdrive-to-post'));
        }

        $redirect_uri = $this->get_redirect_uri();
        error_log('[GDTP] Token exchange - redirect_uri: ' . $redirect_uri);

        $response = wp_remote_post(self::TOKEN_URL, array(
            'body' => array(
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            error_log('[GDTP] Token exchange HTTP error: ' . $response->get_error_message());
            return $response;
        }

        $raw_body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        error_log('[GDTP] Token exchange response HTTP ' . $http_code . ': ' . substr($raw_body, 0, 500));

        $body = json_decode($raw_body, true);

        if (isset($body['error'])) {
            return new WP_Error('token_error', sprintf(
                __('Google auth error: %s', 'gdrive-to-post'),
                $body['error_description'] ?? $body['error']
            ));
        }

        if (!isset($body['access_token'])) {
            return new WP_Error('no_token', __('No access token received from Google.', 'gdrive-to-post'));
        }

        // Store tokens encrypted
        update_option('gdtp_oauth_access_token', gdtp_encrypt($body['access_token']));

        if (isset($body['refresh_token'])) {
            update_option('gdtp_oauth_refresh_token', gdtp_encrypt($body['refresh_token']));
        }

        $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;
        update_option('gdtp_oauth_token_expiry', time() + $expires_in - 60);

        // Cache access token in transient too
        set_transient('gdtp_access_token', $body['access_token'], $expires_in - 60);

        // Get connected user info
        $user_info = $this->test_connection();
        if (!is_wp_error($user_info)) {
            update_option('gdtp_oauth_user_email', $user_info['email']);
            update_option('gdtp_oauth_user_name', $user_info['name']);
            error_log('[GDTP] Connected as: ' . $user_info['email']);
        } else {
            error_log('[GDTP] test_connection after token exchange failed: ' . $user_info->get_error_message());
        }

        return true;
    }

    /**
     * Get a valid access token, refreshing if needed
     */
    public function get_access_token() {
        // Check transient cache first
        $cached_token = get_transient('gdtp_access_token');
        if ($cached_token) {
            return $cached_token;
        }

        // Try to decrypt stored token if not expired
        $expiry = (int) get_option('gdtp_oauth_token_expiry', 0);
        if ($expiry > time()) {
            $token = gdtp_decrypt(get_option('gdtp_oauth_access_token', ''));
            if (!empty($token)) {
                $ttl = $expiry - time();
                set_transient('gdtp_access_token', $token, $ttl);
                return $token;
            }
        }

        // Token expired - refresh it
        return $this->refresh_access_token();
    }

    /**
     * Refresh the access token using the refresh token
     */
    private function refresh_access_token() {
        $refresh_token = gdtp_decrypt(get_option('gdtp_oauth_refresh_token', ''));

        if (empty($refresh_token)) {
            return new WP_Error('no_refresh_token', __('No refresh token available. Please reconnect to Google.', 'gdrive-to-post'));
        }

        $client_id = get_option('gdtp_oauth_client_id', '');
        $client_secret = gdtp_decrypt(get_option('gdtp_oauth_client_secret', ''));

        $response = wp_remote_post(self::TOKEN_URL, array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'grant_type'    => 'refresh_token',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            // If refresh token is invalid, clear everything
            if ($body['error'] === 'invalid_grant') {
                $this->disconnect();
                return new WP_Error('token_revoked', __('Google authorization has been revoked. Please reconnect.', 'gdrive-to-post'));
            }

            return new WP_Error('refresh_error', sprintf(
                __('Token refresh error: %s', 'gdrive-to-post'),
                $body['error_description'] ?? $body['error']
            ));
        }

        if (!isset($body['access_token'])) {
            return new WP_Error('no_token', __('No access token received during refresh.', 'gdrive-to-post'));
        }

        // Store new access token
        $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;
        $ttl = $expires_in - 60;

        update_option('gdtp_oauth_access_token', gdtp_encrypt($body['access_token']));
        update_option('gdtp_oauth_token_expiry', time() + $ttl);
        set_transient('gdtp_access_token', $body['access_token'], $ttl);

        // If a new refresh token was issued, store it
        if (isset($body['refresh_token'])) {
            update_option('gdtp_oauth_refresh_token', gdtp_encrypt($body['refresh_token']));
        }

        return $body['access_token'];
    }

    /**
     * Disconnect from Google - revoke tokens and clean up
     */
    public function disconnect() {
        // Try to revoke the token
        $refresh_token = gdtp_decrypt(get_option('gdtp_oauth_refresh_token', ''));
        if (!empty($refresh_token)) {
            wp_remote_post(self::REVOKE_URL, array(
                'body'    => array('token' => $refresh_token),
                'timeout' => 10,
            ));
        }

        // Clean up all OAuth data
        delete_option('gdtp_oauth_access_token');
        delete_option('gdtp_oauth_refresh_token');
        delete_option('gdtp_oauth_token_expiry');
        delete_option('gdtp_oauth_user_email');
        delete_option('gdtp_oauth_user_name');
        delete_option('gdtp_folder_id');
        delete_option('gdtp_folder_name');
        delete_transient('gdtp_access_token');
    }

    /**
     * Check if we have a valid Google connection
     */
    public function is_connected() {
        $refresh_token = get_option('gdtp_oauth_refresh_token', '');
        return !empty($refresh_token);
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
        $args['headers']['Authorization'] = 'Bearer ' . $token;

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle 401 - token expired, refresh and retry once
        if ($code === 401 && $retry) {
            delete_transient('gdtp_access_token');
            update_option('gdtp_oauth_token_expiry', 0);
            return $this->make_api_request($url, $args, false);
        }

        if ($code === 403) {
            $error_data = json_decode($body, true);
            $message = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Access denied.', 'gdrive-to-post');
            return new WP_Error('forbidden', $message);
        }

        if ($code === 429) {
            return new WP_Error('rate_limited', __('Google API rate limit exceeded. Please try again later.', 'gdrive-to-post'));
        }

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

        $docs = $this->list_docs_in_folder($folder_id);
        if (is_wp_error($docs)) {
            gdtp_log('Error listing docs in folder ' . $folder_id . ': ' . $docs->get_error_message(), 'error');
            return $all_docs;
        }

        foreach ($docs as $doc) {
            $doc['folder_id'] = $folder_id;
            $all_docs[] = $doc;
        }

        $subfolders = $this->list_folders($folder_id);
        if (is_wp_error($subfolders)) {
            gdtp_log('Error listing subfolders in ' . $folder_id . ': ' . $subfolders->get_error_message(), 'error');
            return $all_docs;
        }

        foreach ($subfolders as $subfolder) {
            if ($depth > 0) {
                usleep(100000);
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

        return $this->make_api_request($url);
    }

    /**
     * Make an authenticated PATCH/POST request to Google Drive API
     */
    public function make_api_write_request($url, $method = 'PATCH', $body = array(), $retry = true) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($code === 401 && $retry) {
            delete_transient('gdtp_access_token');
            update_option('gdtp_oauth_token_expiry', 0);
            return $this->make_api_write_request($url, $method, $body, false);
        }

        if ($code >= 400) {
            $error_data = json_decode($response_body, true);
            $message = isset($error_data['error']['message']) ? $error_data['error']['message'] : sprintf('API error: HTTP %d', $code);
            return new WP_Error('api_error', $message);
        }

        return json_decode($response_body, true);
    }

    /**
     * Move a file from one folder to another
     */
    public function move_file($file_id, $from_folder_id, $to_folder_id) {
        $url = add_query_arg(array(
            'addParents'    => $to_folder_id,
            'removeParents' => $from_folder_id,
        ), self::API_BASE . '/files/' . urlencode($file_id));

        return $this->make_api_write_request($url, 'PATCH');
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
}
