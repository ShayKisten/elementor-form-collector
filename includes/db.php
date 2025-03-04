<?php
if (!defined('ABSPATH')) {
    exit;
}

function efc_create_submissions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'efc_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        form_id VARCHAR(255) NOT NULL,
        fields LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    error_log('EFC: Submissions table created or updated');
}

function efc_store_submission($submission_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'efc_submissions';

    $result = $wpdb->insert(
        $table_name,
        [
            'form_id' => $submission_data['form_id'],
            'fields' => json_encode($submission_data['fields']),
            'created_at' => $submission_data['created_at'],
        ],
        ['%s', '%s', '%s']
    );
    if ($result === false) {
        error_log('EFC: Failed to store submission - ' . $wpdb->last_error);
    }
}

function efc_get_forms() {
    global $wpdb;
    $forms = $wpdb->get_col("SELECT DISTINCT form_id FROM {$wpdb->prefix}efc_submissions");
    return array_filter($forms);
}

function efc_get_submissions($form_id = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'efc_submissions';
    $query = "SELECT * FROM $table_name";
    if ($form_id) {
        $query .= $wpdb->prepare(" WHERE form_id = %s", $form_id);
    }
    $query .= " ORDER BY created_at DESC";
    return $wpdb->get_results($query, ARRAY_A);
}