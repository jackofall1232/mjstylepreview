<?php
/**
 * Plugin Name: MJ Style Preview
 * Description: Private, hair-focused AI consultation previews via [mj_style_preview].
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: MJ Hair Artist
 * License: GPL-2.0-or-later
 * Text Domain: mj-style-preview
 */
namespace MJStylePreview;
defined('ABSPATH') || exit;
const VERSION = '1.0.0';
const MODEL = 'gpt-image-2.5-flare';
const OPTION = 'mjsp_settings';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/http.php';
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate');
add_filter('cron_schedules', function ($schedules) {
    $schedules['mjsp_five_minutes'] = array('interval'=>300, 'display'=>'Every five minutes (MJ Style Preview)');
    return $schedules;
});
add_action('mjsp_cleanup', __NAMESPACE__ . '\\cleanup');
add_action('admin_menu', __NAMESPACE__ . '\\admin_menu');
add_action('admin_init', __NAMESPACE__ . '\\admin_init');
add_action('wp_ajax_mjsp_session', __NAMESPACE__ . '\\session_endpoint');
add_action('wp_ajax_nopriv_mjsp_session', __NAMESPACE__ . '\\session_endpoint');
add_action('wp_ajax_mjsp_generate', __NAMESPACE__ . '\\generate_endpoint');
add_action('wp_ajax_nopriv_mjsp_generate', __NAMESPACE__ . '\\generate_endpoint');
add_shortcode('mj_style_preview', function () {
    if (!settings()['enabled']) { return '<p>Style Preview is currently unavailable.</p>'; }
    wp_enqueue_style('mjsp', plugins_url('assets/css/preview.css', __FILE__), array(), VERSION);
    wp_enqueue_script('mjsp', plugins_url('assets/js/preview.js', __FILE__), array(), VERSION, true);
    ob_start();
    // Printing the enqueued stylesheet also supports shortcodes rendered after wp_head.
    wp_print_styles('mjsp');
    $id = wp_unique_id('mjsp-');
    include __DIR__ . '/templates/preview.php';
    return ob_get_clean();
});
