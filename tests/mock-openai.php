<?php
// TEST FIXTURE ONLY. Never package or install on a production site.
add_filter('pre_http_request',function($pre,$args,$url) {
    if ($url!=='https://api.openai.com/v1/images/edits') return $pre;
    preg_match('/Content-Type: image\/jpeg\r\n\r\n(.*?)\r\n--mjsp/s', $args['body'], $match);
    $info=getimagesizefromstring($match[1]);
    update_option('mjsp_test_normalized',array('width'=>$info[0],'height'=>$info[1],'exif'=>str_contains($match[1], "Exif\0\0")),false);
    $mode=get_option('mjsp_test_mode','success');
    if ($mode==='timeout') return new WP_Error('http_request_failed','Test timeout');
    if ($mode==='rate') return array('response'=>array('code'=>429),'body'=>'{"error":"private upstream details"}');
    if ($mode==='invalid') return array('response'=>array('code'=>200),'body'=>'{"data":[{"b64_json":"invalid"}]}');
    if ($mode==='error') return array('response'=>array('code'=>401),'body'=>'{"error":"private upstream details"}');
    if (str_contains($args['body'],'Ferrari') || !str_contains($args['body'],'name="image"') || !str_contains($args['body'],'gpt-image-2.5-flare') || isset($args['headers']['X-API-Key'])) throw new Exception('Malformed API request');
    $im=imagecreatetruecolor(400,600);imagefill($im,0,0,imagecolorallocate($im,215,166,162));ob_start();imagejpeg($im);$bytes=ob_get_clean();imagedestroy($im);
    return array('response'=>array('code'=>200),'body'=>json_encode(array('data'=>array(array('b64_json'=>base64_encode($bytes))))));
},10,3);
