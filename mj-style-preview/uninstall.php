<?php
/** Remove only this plugin's data for the current site. */
defined('WP_UNINSTALL_PLUGIN') || exit;
if (!defined('MJStylePreview\\OPTION')) {
    define('MJStylePreview\\OPTION','mjsp_settings');
    define('MJStylePreview\\MODEL','gpt-image-2.5-flare');
    require_once __DIR__.'/includes/core.php';
}
function mjsp_uninstall_site() {
    \MJStylePreview\deactivate();
    try { $dir=\MJStylePreview\private_dir(); if (is_dir($dir) && count(scandir($dir))===2) { rmdir($dir); } } catch (\Throwable $e) {}
    global $wpdb;
    foreach (array('mjsp_','_transient_mjsp_','_transient_timeout_mjsp_') as $prefix) {
        $names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like($prefix).'%'));
        foreach ($names as $name) {
            if (str_starts_with($name,'_transient_mjsp_')) { delete_transient(substr($name,11)); }
            delete_option($name);
        }
    }
}
if (is_multisite()) {
    // Multisite network activation is not supported in v1; clean any per-site installs.
    foreach (get_sites(array('fields'=>'ids','number'=>0)) as $site_id) { switch_to_blog($site_id); mjsp_uninstall_site(); restore_current_blog(); }
} else { mjsp_uninstall_site(); }
