<?php
namespace MJStylePreview;
defined('ABSPATH') || exit;
function admin_menu() { add_options_page('MJ Style Preview','MJ Style Preview','manage_options','mjsp',__NAMESPACE__.'\\admin_page'); }
function admin_init() {
    register_setting('mjsp',OPTION,array('type'=>'array','sanitize_callback'=>__NAMESPACE__.'\\sanitize_settings','show_in_rest'=>false));
}
function sanitize_settings($input) {
    $old=settings();
    if (!current_user_can('manage_options') || !is_array($input)) { return $old; }
    $out=$old;
    $out['enabled']=empty($input['enabled']) ? 0 : 1;
    if (!defined('MJ_STYLE_PREVIEW_OPENAI_API_KEY')) {
        if (!empty($input['clear_key'])) { $out['key']=''; }
        elseif (!empty($input['key']) && is_string($input['key'])) {
            $key=trim($input['key']);
            if (preg_match('/^[A-Za-z0-9_-]{20,512}$/D',$key)) { $out['key']=$key; }
            else { add_settings_error(OPTION,'key','API key was not saved: invalid format.'); }
        }
    }
    $model=is_string($input['model'] ?? null) ? trim($input['model']) : '';
    if (preg_match('/^gpt-image-[a-z0-9.-]{1,80}$/D',$model)) { $out['model']=$model; }
    else { add_settings_error(OPTION,'model','Enter a supported GPT Image model identifier.'); }
    foreach (array('limit'=>array(1,20),'ip_limit'=>array(1,100),'daily'=>array(1,500),'cooldown'=>array(15,3600),'concurrent'=>array(1,3),'upload'=>array(1,15),'lifetime'=>array(5,30)) as $key=>$range) {
        $out[$key]=max($range[0],min($range[1],absint($input[$key] ?? $old[$key])));
    }
    return $out;
}
function admin_page() {
    if (!current_user_can('manage_options')) { return; }
    $s=settings(); $usage=get_option('mjsp_usage',array());
    ?>
    <div class="wrap"><h1>MJ Style Preview</h1>
    <p>Independent hairstyle consultation previews. Place <code>[mj_style_preview]</code> on any page. HTTPS, PHP GD and EXIF are required.</p>
    <?php settings_errors(); ?>
    <form action="options.php" method="post">
    <?php settings_fields('mjsp'); ?>
    <table class="form-table" role="presentation"><tbody>
    <tr><th scope="row">Enable previews</th><td><label><input name="mjsp_settings[enabled]" type="checkbox" value="1" <?php checked($s['enabled'],1); ?>> Allow public generation</label></td></tr>
    <tr><th scope="row"><label for="mjsp-key">OpenAI API key</label></th><td>
    <?php if (defined('MJ_STYLE_PREVIEW_OPENAI_API_KEY')) : ?><p>Managed by wp-config.php. Value is never displayed.</p>
    <?php else : ?><input id="mjsp-key" type="password" name="mjsp_settings[key]" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo esc_attr($s['key'] ? '•••••••• — saved' : 'Enter API key'); ?>">
    <p class="description">Leave blank to keep the saved key. Database keys are stored server-side, unencrypted; prefer the wp-config.php constant.</p>
    <label><input type="checkbox" name="mjsp_settings[clear_key]" value="1"> Remove saved key</label><?php endif; ?></td></tr>
    <tr><th scope="row"><label for="mjsp-model">Image model</label></th><td><input id="mjsp-model" class="regular-text" name="mjsp_settings[model]" value="<?php echo esc_attr($s['model']); ?>"><p class="description">Default: <?php echo esc_html(MODEL); ?>. Alternatives require API compatibility testing.</p></td></tr>
    <?php foreach (array('limit'=>'Requests per browser / rolling hour','ip_limit'=>'Requests per IP / rolling hour','daily'=>'Site-wide requests / UTC day','cooldown'=>'Browser cooldown (seconds)','concurrent'=>'Concurrent AI requests (1–3)','upload'=>'Maximum upload (MB, 1–15)','lifetime'=>'Temporary lifetime (minutes, 5–30)') as $key=>$label) : ?>
    <tr><th scope="row"><label for="mjsp-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input type="number" min="1" id="mjsp-<?php echo esc_attr($key); ?>" name="mjsp_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($s[$key]); ?>"></td></tr>
    <?php endforeach; ?></tbody></table>
    <?php submit_button(); ?></form>
    <h2>Usage summary (UTC)</h2>
    <?php foreach (array('day'=>gmdate('Y-m-d'),'month'=>gmdate('Y-m')) as $period=>$date) :
        $counts=($usage[$period]['date'] ?? '')===$date ? $usage[$period] : array('success'=>0,'failed'=>0); ?>
    <p><?php echo esc_html(($period==='day' ? 'Today' : 'This month').': '.($counts['success']+$counts['failed']).' attempts · '.$counts['success'].' successful · '.$counts['failed'].' failed'); ?></p>
    <?php endforeach; ?>
    <p>Failed attempts consume the allowance. IP hashes and session hashes expire; usage totals contain no visitor identities. Set an OpenAI project spending budget as an additional safeguard.</p>
    <p>Cleanup is scheduled every five minutes through WP-Cron and on generation. For reliable abandoned-file expiry on quiet sites, configure a real cron job to run WordPress cron every five minutes. Review README.md before enabling.</p>
    </div><?php
}
