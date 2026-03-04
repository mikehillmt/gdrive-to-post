<?php
/**
 * Database operations for GDrive to Post
 */

if (!defined('ABSPATH')) {
    exit;
}

class GDTP_Database {

    /**
     * Table name
     */
    private $imports_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->imports_table = $wpdb->prefix . 'gdtp_imports';
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->imports_table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            google_doc_id VARCHAR(255) NOT NULL,
            google_doc_title VARCHAR(500) NOT NULL,
            folder_id VARCHAR(255) NOT NULL,
            post_id BIGINT DEFAULT 0,
            imported_at DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'success',
            error_message TEXT,
            INDEX idx_google_doc_id (google_doc_id),
            INDEX idx_folder_id (folder_id),
            INDEX idx_post_id (post_id),
            INDEX idx_imported_at (imported_at),
            INDEX idx_status (status)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if a doc has already been imported
     */
    public function is_doc_imported($google_doc_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->imports_table} WHERE google_doc_id = %s AND status = 'success'",
            $google_doc_id
        )) > 0;
    }

    /**
     * Record an import
     */
    public function record_import($data) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->imports_table,
            array(
                'google_doc_id'    => sanitize_text_field($data['google_doc_id']),
                'google_doc_title' => sanitize_text_field($data['google_doc_title']),
                'folder_id'        => sanitize_text_field($data['folder_id']),
                'post_id'          => (int) $data['post_id'],
                'imported_at'      => current_time('mysql'),
                'status'           => sanitize_text_field($data['status']),
                'error_message'    => isset($data['error_message']) ? sanitize_text_field($data['error_message']) : '',
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get imports with pagination
     */
    public function get_imports($args = array()) {
        global $wpdb;

        $defaults = array(
            'status'  => '',
            'orderby' => 'imported_at',
            'order'   => 'DESC',
            'limit'   => 20,
            'offset'  => 0,
        );

        $args = wp_parse_args($args, $defaults);

        $where = "WHERE 1=1";
        $params = array();

        if (!empty($args['status'])) {
            $where .= " AND status = %s";
            $params[] = $args['status'];
        }

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        if (!$orderby) {
            $orderby = 'imported_at DESC';
        }

        $sql = "SELECT * FROM {$this->imports_table} {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get total imports count
     */
    public function get_imports_count($status = '') {
        global $wpdb;

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->imports_table} WHERE status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->imports_table}");
    }

    /**
     * Get recent imports
     */
    public function get_recent_imports($limit = 5) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->imports_table} ORDER BY imported_at DESC LIMIT %d",
            $limit
        ));
    }

    /**
     * Delete an import record
     */
    public function delete_import($import_id) {
        global $wpdb;

        return $wpdb->delete(
            $this->imports_table,
            array('id' => $import_id),
            array('%d')
        ) !== false;
    }

    /**
     * Get import statistics
     */
    public function get_import_stats() {
        global $wpdb;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->imports_table}");
        $success = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->imports_table} WHERE status = 'success'");
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->imports_table} WHERE status = 'error'");

        $last_import = $wpdb->get_var("SELECT imported_at FROM {$this->imports_table} ORDER BY imported_at DESC LIMIT 1");

        $today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->imports_table} WHERE imported_at >= %s AND status = 'success'",
            date('Y-m-d 00:00:00')
        ));

        return array(
            'total'       => $total,
            'success'     => $success,
            'failed'      => $failed,
            'last_import' => $last_import,
            'today'       => $today,
        );
    }
}
