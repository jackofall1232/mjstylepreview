<?php
$_SERVER['HTTP_HOST']='example.test';
require '/var/www/html/wp-load.php';
update_option('mjsp_test_owned','test');update_option('unrelated_keep','safe');set_transient('mjsp_uninstall_fixture',1,3600);
$dir=MJStylePreview\private_dir();file_put_contents($dir.'/photo-uninstall.jpg','fixture');
define('WP_UNINSTALL_PLUGIN','mj-style-preview/mj-style-preview.php');
require '/var/www/html/wp-content/plugins/mj-style-preview/uninstall.php';
foreach(array('mjsp_settings','mjsp_usage','mjsp_daily','mjsp_test_owned') as $key) {if(get_option($key)!==false)throw new Exception('Option remained: '.$key);}
if(get_transient('mjsp_uninstall_fixture')!==false||wp_next_scheduled('mjsp_cleanup')||is_dir($dir)||get_option('unrelated_keep')!=='safe')throw new Exception('Uninstall cleanup failed');
echo "PASS: uninstall removes options, transients, scheduled tasks and private directory, preserving unrelated data\n";
