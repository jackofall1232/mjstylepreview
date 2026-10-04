<?php
namespace MJStylePreview;
defined('ABSPATH') || exit;
function settings() {
    return wp_parse_args(get_option(OPTION, array()), array('enabled'=>0, 'key'=>'', 'model'=>MODEL, 'limit'=>3, 'ip_limit'=>10, 'daily'=>30, 'cooldown'=>60, 'concurrent'=>1, 'upload'=>10, 'lifetime'=>10));
}
function api_key() { return defined('MJ_STYLE_PREVIEW_OPENAI_API_KEY') ? (string) MJ_STYLE_PREVIEW_OPENAI_API_KEY : settings()['key']; }
function activate($network_wide = false) {
    if ($network_wide) { wp_die('MJ Style Preview v1 supports individual site activation only.'); }
    add_option(OPTION, settings(), '', false);
    if (!wp_next_scheduled('mjsp_cleanup')) { wp_schedule_event(time()+300, 'mjsp_five_minutes', 'mjsp_cleanup'); }
}
function deactivate() { wp_clear_scheduled_hook('mjsp_cleanup'); cleanup(true); }
function private_dir() {
    $base = defined('MJ_STYLE_PREVIEW_TEMP_DIR') ? MJ_STYLE_PREVIEW_TEMP_DIR : sys_get_temp_dir();
    $base = realpath($base);
    if (!$base || !is_writable($base)) { throw new \RuntimeException('storage'); }
    $web = realpath(ABSPATH);
    foreach (array_filter(array($web, realpath($_SERVER['DOCUMENT_ROOT'] ?? ABSPATH))) as $root) {
        if ($base === $root || str_starts_with($base, $root . DIRECTORY_SEPARATOR)) { throw new \RuntimeException('storage'); }
    }
    $dir = $base . '/mjsp-' . substr(hash_hmac('sha256', home_url(), wp_salt('auth')), 0, 16);
    if (is_link($dir)) { throw new \RuntimeException('storage'); }
    if (!is_dir($dir) && !mkdir($dir, 0700)) { throw new \RuntimeException('storage'); }
    chmod($dir, 0700);
    return $dir;
}
function cleanup($all = false) {
    try {
        $dir = private_dir();
        foreach (glob($dir . '/photo-*') ?: array() as $file) {
            if (!is_link($file) && is_file($file) && ($all || filemtime($file) < time() - settings()['lifetime']*60)) { unlink($file); }
        }
    } catch (\Throwable $e) { /* No public diagnostics. */ }
    global $wpdb;
    $like = $wpdb->esc_like('mjsp_gate_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $like, time()));
}
// Atomic options work even with persistent object caches; no read-then-write transient locks.
function gate($name, $seconds) {
    global $wpdb;
    $name = 'mjsp_gate_' . $name;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", $name, time()));
    wp_cache_delete($name, 'options');
    return add_option($name, (string)(time()+$seconds), '', false);
}
function release($name) { delete_option('mjsp_gate_' . $name); }
function client_id() {
    $token = isset($_COOKIE['mjsp_session']) && is_string($_COOKIE['mjsp_session']) ? $_COOKIE['mjsp_session'] : '';
    $parts = explode('.', $token);
    if (count($parts) !== 3 || !preg_match('/^[a-f0-9]{64}$/', $parts[0]) || !ctype_digit($parts[1]) || (int)$parts[1] < time() || !hash_equals(hash_hmac('sha256', $parts[0].'.'.$parts[1], wp_salt('nonce')), $parts[2])) { return ''; }
    return $parts[0];
}
function reserve($client) {
    $s = settings();
    $ip = hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('auth'));
    if (!gate('accounting', 15)) { throw new \RuntimeException('busy'); }
    try {
        $now = time();
        $daily = get_option('mjsp_daily', array());
        if (($daily['date'] ?? '') !== gmdate('Ymd')) { update_option('mjsp_daily', array('date'=>gmdate('Ymd'), 'count'=>0), false); }
        $keys = array('browser_'.$client => (int)$s['limit'], 'ip_'.$ip => (int)$s['ip_limit'], 'day_'.gmdate('Ymd') => (int)$s['daily']);
        if (get_transient('mjsp_cool_'.$client)) { throw new \RuntimeException('cooldown'); }
        foreach ($keys as $key=>$max) {
            if ((int)(str_starts_with($key, 'day_') ? get_option('mjsp_daily', array())['count'] ?? 0 : get_transient('mjsp_'.$key)) >= $max) { throw new \RuntimeException('limit'); }
        }
        $slot = null;
        for ($i=0; $i<(int)$s['concurrent']; $i++) { if (gate('slot_'.$i, 240)) { $slot = 'slot_'.$i; break; } }
        if ($slot === null) { throw new \RuntimeException('busy'); }
        foreach ($keys as $key=>$max) {
            $ttl = str_starts_with($key, 'day_') ? strtotime('tomorrow UTC')-$now : HOUR_IN_SECONDS;
            if (str_starts_with($key, 'day_')) {
                $daily=get_option('mjsp_daily'); $daily['count']++; update_option('mjsp_daily',$daily,false);
            } else { set_transient('mjsp_'.$key, (int)get_transient('mjsp_'.$key)+1, $ttl); }
        }
        set_transient('mjsp_cool_'.$client, 1, (int)$s['cooldown']);
        return $slot;
    } finally { release('accounting'); }
}
function record_usage($ok) {
    if (!gate('stats', 5)) { return; }
    try {
        $stats = get_option('mjsp_usage', array());
        foreach (array('day'=>gmdate('Y-m-d'), 'month'=>gmdate('Y-m')) as $period=>$date) {
            if (($stats[$period]['date'] ?? '') !== $date) { $stats[$period] = array('date'=>$date, 'success'=>0, 'failed'=>0); }
            $stats[$period][$ok ? 'success' : 'failed']++;
        }
        update_option('mjsp_usage', $stats, false);
    } finally { release('stats'); }
}
// Positive vocabulary: raw visitor text is NEVER inserted into the model prompt.
function hair_request($text) {
    if (!is_string($text) || strlen($text)>1200 || trim($text)==='') { throw new \RuntimeException('prompt'); }
    $text = strtolower(sanitize_textarea_field($text));
    // Negations require clarification rather than silently reversing their meaning.
    if (preg_match('/\b(no|not|without|avoid|never|don\x27t)\b/', $text)) { throw new \RuntimeException('describe'); }
    $vocabulary = array('shoulder-length','chin-length','long','short','medium','layers','layered','bob','lob','pixie','shag','wolf cut','bangs','curtain bangs','side part','middle part','face-framing','straight','wavy','waves','curly','curls','loose curls','big loose curls','tight curls','coils','braids','twists','volume','blowout','sleek','textured','blonde','warm blonde','platinum','honey blonde','caramel','brunette','brown','chocolate brown','black','auburn','copper','red','burgundy','silver','gray','pink','purple','balayage','ombre','highlights','lowlights','blonde highlights','darker roots','dark roots','warm','cool','natural');
    $matches = array();
    foreach ($vocabulary as $word) {
        $pattern = str_replace('\-', '[- ]', preg_quote($word, '/'));
        if (preg_match('/\b'.$pattern.'\b/i', $text)) { $matches[] = $word; }
    }
    if (!$matches) { throw new \RuntimeException('describe'); }
    return implode(', ', $matches);
}
function protected_prompt($hair) {
    return 'Edit the supplied photograph as a realistic hairstyle consultation preview. Preserve the person\'s identity and recognizable likeness, face, facial structure and features, skin tone, expression, apparent age, body, pose, clothing, camera angle, lighting and background. Modify ONLY hair. Integrate photorealistic, physically plausible hair with the existing head, hairline, lighting and shadows. Do not beautify or change face or body, add accessories, or change the environment. Keep all non-hair pixels as close to the source as possible. Apply these approved hair descriptors, interpreting all length, color and texture words ONLY as hair properties: ' . $hair . '. This is inspiration, not a claim of achievable salon results.';
}
