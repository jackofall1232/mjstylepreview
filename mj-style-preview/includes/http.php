<?php
namespace MJStylePreview;
defined('ABSPATH') || exit;
function same_origin() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $expected = wp_parse_url(home_url());
    $actual = wp_parse_url($origin);
    return is_array($actual) && isset($actual['host'], $actual['scheme']) && strtolower($actual['host']) === strtolower($expected['host']) && $actual['scheme'] === $expected['scheme'] && ($actual['port'] ?? 443) === ($expected['port'] ?? 443);
}
function protect_request() {
    nocache_headers();
    header('Cache-Control: no-store, private, max-age=0');
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !same_origin() || !is_ssl()) { fail('security', 403); }
}
function fail($code, $status=400) {
    $messages = array(
        'security'=>'Please reload this page securely and try again.', 'disabled'=>'Style Preview is currently unavailable.',
        'consent'=>'Please confirm your permission to use this photo.', 'prompt'=>'Describe your hairstyle in 1–1,200 characters.',
        'describe'=>'Please describe the hair you want using supported positive descriptions, such as shoulder-length layers, loose curls, blonde highlights and darker roots. Avoid instructions about what not to change.',
        'upload'=>'Choose a JPG, PNG or WebP photo within the upload limit. Export HEIC photos as JPG first.',
        'dimensions'=>'Please use a photo between 256 pixels and 6,000 pixels per side, and at most 16 megapixels.',
        'storage'=>'The photo service needs attention. Please try again later.', 'busy'=>'The preview studio is busy. Please try again in a few minutes.',
        'limit'=>'The preview allowance has been reached. Please try again later.', 'cooldown'=>'Please wait a little before trying another look.',
        'upstream'=>'The AI service could not finish this preview. Please try again later.', 'timeout'=>'The AI service took too long. Please wait before trying again.',
        'api_limit'=>'The AI service is busy. Please try again later.', 'response'=>'The AI service returned an unusable preview. Please try again later.'
    );
    if (!isset($messages[$code])) { $code='upstream'; }
    if (in_array($code, array('busy','limit','cooldown','api_limit'), true)) { header('Retry-After: 60'); $status=429; }
    wp_send_json_error(array('message'=>$messages[$code] ?? $messages['upstream'], 'code'=>$code), $status);
}
function session_endpoint() {
    protect_request();
    if (!settings()['enabled'] || !api_key()) { fail('disabled', 503); }
    $id = client_id();
    if (!$id) {
        $id = bin2hex(random_bytes(32));
        $value = $id.'.'.(time()+DAY_IN_SECONDS);
        $value .= '.'.hash_hmac('sha256', $value, wp_salt('nonce'));
        setcookie('mjsp_session', $value, array('expires'=>time()+DAY_IN_SECONDS, 'path'=>'/', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict'));
    }
    wp_send_json_success(array('nonce'=>wp_create_nonce('mjsp_'.$id), 'maxBytes'=>(int)settings()['upload']*MB_IN_BYTES));
}
function normalize_upload() {
    $f = $_FILES['photo'] ?? null;
    if (!is_array($f) || !isset($f['tmp_name'], $f['name'], $f['error']) || !is_string($f['tmp_name']) || !is_string($f['name']) || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { throw new \RuntimeException('upload'); }
    $size = filesize($f['tmp_name']);
    if (!$size || $size > (int)settings()['upload']*MB_IN_BYTES) { throw new \RuntimeException('upload'); }
    $info = @getimagesize($f['tmp_name']);
    $mimes = array('jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp');
    $checked = wp_check_filetype_and_ext($f['tmp_name'], sanitize_file_name($f['name']), $mimes);
    if (!$info || !in_array($info['mime'], $mimes, true) || empty($checked['ext']) || $checked['type'] !== $info['mime']) { throw new \RuntimeException('upload'); }
    if (min($info[0],$info[1])<256 || max($info[0],$info[1])>6000 || $info[0]*$info[1]>16000000) { throw new \RuntimeException('dimensions'); }
    // GD writes a fresh raster without source EXIF/XMP/IPTC metadata.
    if (!extension_loaded('gd')) { throw new \RuntimeException('storage'); }
    $limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
    if ($limit>0 && memory_get_usage(true)+$info[0]*$info[1]*7+32*MB_IN_BYTES > $limit) { throw new \RuntimeException('dimensions'); }
    $image = @imagecreatefromstring(file_get_contents($f['tmp_name']));
    if (!$image) { throw new \RuntimeException('upload'); }
    try {
        if ($info['mime']==='image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($f['tmp_name']);
            $orientation = (int)($exif['Orientation'] ?? 1);
            if (in_array($orientation, array(2,4,5,7), true)) { imageflip($image, IMG_FLIP_HORIZONTAL); }
            $angle = array(3=>180,4=>180,5=>90,6=>-90,7=>-90,8=>90)[$orientation] ?? 0;
            if ($angle) { $rotated = imagerotate($image, $angle, 0); imagedestroy($image); $image=$rotated; }
        } elseif ($info['mime']==='image/jpeg') { throw new \RuntimeException('storage'); }
        $w=imagesx($image); $h=imagesy($image); $scale=min(1,1536/max($w,$h));
        $out=imagecreatetruecolor((int)round($w*$scale),(int)round($h*$scale));
        imagefill($out,0,0,imagecolorallocate($out,255,248,244));
        imagecopyresampled($out,$image,0,0,0,0,imagesx($out),imagesy($out),$w,$h);
        $path=private_dir().'/photo-'.bin2hex(random_bytes(16)).'.jpg';
        try {
            if (!imagejpeg($out,$path,88)) { throw new \RuntimeException('storage'); }
            chmod($path,0600);
            return $path;
        } finally { imagedestroy($out); }
    } finally { imagedestroy($image); }
}
function edit_image($path, $hair) {
    $boundary='mjsp'.bin2hex(random_bytes(20));
    $fields=array('model'=>settings()['model'],'prompt'=>protected_prompt($hair),'n'=>'1','size'=>'1024x1536','quality'=>'medium','output_format'=>'jpeg','output_compression'=>'85');
    $body='';
    foreach ($fields as $key=>$value) { $body.="--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n"; }
    $body.="--$boundary\r\nContent-Disposition: form-data; name=\"image\"; filename=\"photo.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n".file_get_contents($path)."\r\n--$boundary--\r\n";
    $response=wp_remote_post('https://api.openai.com/v1/images/edits',array('timeout'=>150,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>12*MB_IN_BYTES,'headers'=>array('Authorization'=>'Bearer '.api_key(),'Content-Type'=>'multipart/form-data; boundary='.$boundary),'body'=>$body,'data_format'=>'body'));
    if (is_wp_error($response)) { throw new \RuntimeException('timeout'); }
    $status=wp_remote_retrieve_response_code($response);
    if ($status===429) { throw new \RuntimeException('api_limit'); }
    if ($status!==200) { throw new \RuntimeException('upstream'); }
    $data=json_decode(wp_remote_retrieve_body($response),true);
    $encoded=$data['data'][0]['b64_json'] ?? null;
    if (!is_string($encoded)) { throw new \RuntimeException('response'); }
    $bytes=base64_decode($encoded,true);
    $info=$bytes ? @getimagesizefromstring($bytes) : false;
    if (!$info || $info['mime']!=='image/jpeg' || strlen($bytes)>8*MB_IN_BYTES || $info[0]*$info[1]>4000000) { throw new \RuntimeException('response'); }
    return base64_encode($bytes);
}
function generate_endpoint() {
    protect_request();
    $id=client_id();
    $nonce=isset($_POST['nonce']) && is_string($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
    if (!$id || !wp_verify_nonce($nonce,'mjsp_'.$id)) { fail('security',403); }
    if (!settings()['enabled'] || !api_key()) { fail('disabled',503); }
    if (($_POST['consent'] ?? '')!=='1') { fail('consent'); }
    $file=null; $slot=null; $error=null; $result=null; $start=microtime(true);
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) { return false; }
        throw new \ErrorException('Processing warning', 0, $severity);
    });
    try {
        $hair=hair_request(isset($_POST['hairstyle']) ? wp_unslash($_POST['hairstyle']) : '');
        // Serialize each session before any expensive image decoding.
        if (!gate('client_'.$id,240)) { throw new \RuntimeException('busy'); }
        try {
            $slot=reserve($id);
            cleanup();
            $file=normalize_upload();
            $result=edit_image($file,$hair);
        } finally { release('client_'.$id); }
    } catch (\Throwable $e) { $error=$e instanceof \RuntimeException ? $e->getMessage() : 'upstream'; }
    finally {
        restore_error_handler();
        if ($file && is_file($file)) { unlink($file); }
        if ($slot) { release($slot); record_usage($error===null); }
    }
    // Opt-in operational diagnostics only; no upstream body, prompt, identity or image.
    if (defined('MJ_STYLE_PREVIEW_LOG') && MJ_STYLE_PREVIEW_LOG && $slot) {
        $safe=in_array($error,array('upload','dimensions','storage','timeout','api_limit','response','upstream'),true) ? $error : ($error ? 'failed' : 'success');
        error_log('MJSP '.gmdate('c').' '.$safe.' duration_ms='.(int)((microtime(true)-$start)*1000));
    }
    if ($error) { fail($error); }
    wp_send_json_success(array('image'=>$result,'applied'=>$hair,'lifetime'=>(int)settings()['lifetime']*60));
}
