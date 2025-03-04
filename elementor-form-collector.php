<?php
/*
Plugin Name: Elementor Form Collector
Description: Collects and stores Elementor Pro form submissions with an admin interface to view them. Allows the deletion of records and the exporting of records to csv.
Version: 1.0.0
Author: ShayKisten
License: GPL-2.0+
Text Domain: elementor-form-collector
*/


if (!defined('ABSPATH')) {
    exit;
}

define('EFC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EFC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EFC_PLUGIN_DIR . 'includes/db.php';
require_once EFC_PLUGIN_DIR . 'includes/admin.php';

if (class_exists('\ElementorPro\Modules\Forms\Classes\Action_Base')) {
    error_log('EFC: Elementor Pro Action_Base class exists');
} else {
    error_log('EFC: Elementor Pro Action_Base class missing');
}

function efc_load_textdomain() {
    load_plugin_textdomain('elementor-form-collector', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'efc_load_textdomain');

function efc_register_form_action($form_actions_registrar) {

    require_once EFC_PLUGIN_DIR . 'includes/Actions/Collector.php';

    if (class_exists('\ElementorFormCollector\Actions\EFC_Form_Action')) {
        $form_actions_registrar->register(new \ElementorFormCollector\Actions\EFC_Form_Action());
        error_log('EFC: Form action registered');
    } else {
        error_log('EFC: EFC_Form_Action class not loaded');
    }
}
add_action('elementor_pro/forms/register_action', 'efc_register_form_action', 20, 1);

register_activation_hook(__FILE__, 'efc_create_submissions_table');

function efc_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_elementor-form-collector') {
        return;
    }
    wp_enqueue_style('efc_admin_css', EFC_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0');
    wp_enqueue_script('efc_admin_js', EFC_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], '1.0.0', true);
}
add_action('admin_enqueue_scripts', 'efc_enqueue_admin_assets');

add_action('admin_init', function() {
    if (did_action('elementor_pro/forms/register_action')) {
        error_log('EFC: elementor_pro/forms/register_action hook fired');
    } else {
        error_log('EFC: elementor_pro/forms/register_action hook not fired yet');
    }
});