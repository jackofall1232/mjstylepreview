<?php
$_SERVER['HTTP_HOST']='example.test';
require '/var/www/html/wp-load.php';
$html=do_shortcode('[mj_style_preview]');
$html=preg_replace('#<link[^>]*>#','',$html);
echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>MJ Style Preview test</title><link rel="stylesheet" href="/mj-style-preview/assets/css/preview.css"><body style="margin:0;background:#fff8f4">'.$html.'<script src="/mj-style-preview/assets/js/preview.js"></script></body></html>';
