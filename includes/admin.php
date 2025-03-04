<?php
if (!defined('ABSPATH')) {
    exit;
}

function efc_admin_menu() {
    add_menu_page(
        esc_html__('Form Submissions', 'elementor-form-collector'),
        esc_html__('Form Submissions', 'elementor-form-collector'),
        'manage_options',
        'elementor-form-collector',
        'efc_admin_page',
        'dashicons-list-view',
        80
    );
    add_submenu_page(
        'elementor-form-collector',
        esc_html__('Submission Details', 'elementor-form-collector'),
        null,
        'manage_options',
        'efc-submission-details',
        'efc_submission_details_page'
    );
}
add_action('admin_menu', 'efc_admin_menu');

function efc_handle_csv_export() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'elementor-form-collector' || !isset($_GET['export']) || $_GET['export'] !== 'csv') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to export submissions.', 'elementor-form-collector'));
    }

    $selected_form = isset($_GET['form_id']) ? sanitize_text_field($_GET['form_id']) : '';
    $submissions = efc_get_submissions($selected_form);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="efc_submissions_' . ($selected_form ? $selected_form : 'all') . '_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Submission ID', 'Form ID', 'Field', 'Value', 'Created At']);

    $first_submission = true;
    foreach ($submissions as $submission) {
        if (!$first_submission) {
            fputcsv($output, ['---', 'new submission', '---', '---', '---']);
        }
        $first_submission = false;

        $fields = json_decode($submission['fields'], true);
        foreach ($fields as $field_id => $field_data) {
            $label = $field_data['title'];
            if (isset($field_data['parent'])) {
                $label = ucfirst($field_data['parent']['identifier']) . ' ' . ($field_data['parent']['index'] + 1);
                if (isset($field_data['child'])) {
                    $label .= ' - ' . ucfirst($field_data['child']['identifier']) . ' ' . ($field_data['child']['index'] + 1);
                }
                $label .= ' - ' . $field_data['title'];
            }
            fputcsv($output, [$submission['id'], $submission['form_id'], $label, $field_data['value'], $submission['created_at']]);
        }
    }

    fclose($output);
    exit;
}
add_action('admin_init', 'efc_handle_csv_export');

function efc_handle_submission_delete() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'efc_delete_submission' || !isset($_GET['submission_id']) || !isset($_GET['_wpnonce'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to delete submissions.', 'elementor-form-collector'));
    }

    $submission_id = absint($_GET['submission_id']);
    $nonce = sanitize_text_field($_GET['_wpnonce']);

    if (!wp_verify_nonce($nonce, 'efc_delete_submission_' . $submission_id)) {
        wp_die(esc_html__('Security check failed.', 'elementor-form-collector'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'efc_submissions';
    $deleted = $wpdb->delete($table_name, ['id' => $submission_id], ['%d']);

    if ($deleted) {
        wp_redirect(admin_url('admin.php?page=elementor-form-collector&deleted=1'));
        exit;
    } else {
        wp_die(esc_html__('Failed to delete submission.', 'elementor-form-collector'));
    }
}
add_action('admin_init', 'efc_handle_submission_delete');

function efc_admin_page() {
    $forms = efc_get_forms();
    $selected_form = isset($_GET['form_id']) ? sanitize_text_field($_GET['form_id']) : '';
    $submissions = efc_get_submissions($selected_form);

    if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
        echo '<div class="notice notice-success"><p>' . esc_html__('Submission deleted successfully.', 'elementor-form-collector') . '</p></div>';
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Elementor Form Submissions', 'elementor-form-collector'); ?></h1>
        <form method="get">
            <input type="hidden" name="page" value="elementor-form-collector">
            <label for="form_id"><?php esc_html_e('Select Form:', 'elementor-form-collector'); ?></label>
            <select name="form_id" id="form_id" onchange="this.form.submit()">
                <option value=""><?php esc_html_e('All Forms', 'elementor-form-collector'); ?></option>
                <?php foreach ($forms as $form_id) : ?>
                    <option value="<?php echo esc_attr($form_id); ?>" <?php selected($selected_form, $form_id); ?>>
                        <?php echo esc_html($form_id); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="<?php echo esc_url(add_query_arg('export', 'csv')); ?>" class="button" style="margin-left: 10px;"><?php esc_html_e('Export to CSV', 'elementor-form-collector'); ?></a>
        </form>
        <table class="wp-list-table widefat fixed striped efc-submissions-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Form ID', 'elementor-form-collector'); ?></th>
                    <th><?php esc_html_e('Fields', 'elementor-form-collector'); ?></th>
                    <th><?php esc_html_e('Created At', 'elementor-form-collector'); ?></th>
                    <th><?php esc_html_e('Actions', 'elementor-form-collector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission) : 
                    $fields = json_decode($submission['fields'], true);
                    $details_url = admin_url('admin.php?page=efc-submission-details&submission_id=' . $submission['id']);
                    $delete_url = wp_nonce_url(admin_url('admin.php?page=elementor-form-collector&action=efc_delete_submission&submission_id=' . $submission['id']), 'efc_delete_submission_' . $submission['id']);
                    ?>
                    <tr class="efc-submission-row" data-url="<?php echo esc_url($details_url); ?>">
                        <td><?php echo esc_html($submission['form_id']); ?></td>
                        <td>
                            <?php 
                            foreach ($fields as $field_id => $field_data) {
                                $label = $field_data['title'];
                                if (isset($field_data['parent'])) {
                                    $label = ucfirst($field_data['parent']['identifier']) . ' ' . ($field_data['parent']['index'] + 1);
                                    if (isset($field_data['child'])) {
                                        $label .= ' - ' . ucfirst($field_data['child']['identifier']) . ' ' . ($field_data['child']['index'] + 1);
                                    }
                                    $label .= ' - ' . $field_data['title'];
                                }
                                echo esc_html($label) . ': ' . esc_html($field_data['value']) . '<br>';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($submission['created_at']); ?></td>
                        <td><a href="<?php echo esc_url($delete_url); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this submission?', 'elementor-form-collector'); ?>');"><?php esc_html_e('Delete', 'elementor-form-collector'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function efc_submission_details_page() {
    $submission_id = isset($_GET['submission_id']) ? absint($_GET['submission_id']) : 0;
    if (!$submission_id) {
        wp_die(esc_html__('Invalid submission ID', 'elementor-form-collector'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'efc_submissions';
    $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id), ARRAY_A);

    if (!$submission) {
        wp_die(esc_html__('Submission not found', 'elementor-form-collector'));
    }

    $fields = json_decode($submission['fields'], true);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Submission Details', 'elementor-form-collector'); ?> - Form ID: <?php echo esc_html($submission['form_id']); ?></h1>
        <p><strong><?php esc_html_e('Created At:', 'elementor-form-collector'); ?></strong> <?php echo esc_html($submission['created_at']); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Field', 'elementor-form-collector'); ?></th>
                    <th><?php esc_html_e('Value', 'elementor-form-collector'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $field_id => $field_data) : 
                    $label = $field_data['title'];
                    if (isset($field_data['parent'])) {
                        $label = ucfirst($field_data['parent']['identifier']) . ' ' . ($field_data['parent']['index'] + 1);
                        if (isset($field_data['child'])) {
                            $label .= ' - ' . ucfirst($field_data['child']['identifier']) . ' ' . ($field_data['child']['index'] + 1);
                        }
                        $label .= ' - ' . $field_data['title'];
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html($label); ?></td>
                        <td><?php echo esc_html($field_data['value']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><a href="<?php echo esc_url(admin_url('admin.php?page=elementor-form-collector')); ?>" class="button"><?php esc_html_e('Back to Submissions', 'elementor-form-collector'); ?></a></p>
    </div>
    <?php
}