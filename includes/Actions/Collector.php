<?php
namespace ElementorFormCollector\Actions;

use ElementorPro\Modules\Forms\Classes\Action_Base;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EFC_Form_Action extends Action_Base {
    public function get_name() {
        return 'efc-collector';
    }

    public function get_label() {
        return esc_html__('Elementor Form Collector', 'elementor-form-collector');
    }

    public function run($record, $ajax_handler) {
        $form_id = $record->get('form_settings')['id'] ?? null;
        $sent_data = $record->get('sent_data') ?? [];
        $fields = $record->get('fields') ?? [];
        $form_fields = $record->get('form_settings')['form_fields'] ?? [];
    
        error_log('EFC: Form ID: ' . print_r($form_id, true));
        error_log('EFC: Sent Data: ' . print_r($sent_data, true));
        error_log('EFC: Fields: ' . print_r($fields, true));
        error_log('EFC: Form Fields: ' . print_r($form_fields, true));
    
        if (empty($form_id) || empty($sent_data)) {
            error_log('EFC: Form data is incomplete.');
            return;
        }
    
        $submission_data = [
            'form_id' => $form_id,
            'fields' => [],
            'created_at' => current_time('mysql'),
        ];
    
        // Process sent_data for all fields except file uploads
        foreach ($sent_data as $field_id => $value) {
            if (!empty($value)) {
                $field_data = [
                    'field_id' => $field_id,
                    'value' => $value,
                    'title' => ucfirst(str_replace('field_', '', $field_id)),
                ];
    
                foreach ($form_fields as $form_field) {
                    if (strpos($field_id, $form_field['custom_id']) === 0) {
                        $field_data['title'] = $form_field['field_label'] ?? $field_data['title'];
                        break;
                    }
                }
    
                $submission_data['fields'][$field_id] = $field_data;
            }
        }
    
        // Check for file uploads
        foreach ($fields as $field_id => $field_data) {
            if (isset($field_data['type']) && $field_data['type'] === 'upload' && isset($field_data['value'])) {
                $submission_data['fields'][$field_id] = [
                    'field_id' => $field_id,
                    'value' => $field_data['value'],
                    'title' => $field_data['title'] ?? ucfirst(str_replace('field_', '', $field_id)),
                ];
            }
        }
    
        if (function_exists('efc_store_submission')) {
            try {
                efc_store_submission($submission_data);
                error_log('EFC: Submission collected - ' . print_r($submission_data, true));
            } catch (\Exception $e) {
                error_log('EFC: Error storing submission: ' . $e->getMessage());
            }
        } else {
            error_log('EFC: efc_store_submission function not found');
        }
    }

    public function register_settings_section($widget) {
        // No settings needed
    }

    public function on_export($element) {
        // No export modifications
    }
}