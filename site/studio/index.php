<?php
require_once __DIR__ . '/auth.php';
studio_gate_web();
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
$parts=['app.01.b64','app.02.b64','app.03.b64','app.04.b64'];
$payload='';
foreach($parts as $part){
  $chunk=@file_get_contents(__DIR__.'/'.$part);
  if($chunk===false){ http_response_code(500); echo 'Book Author application payload missing.'; exit; }
  $payload.=trim($chunk);
}
$html=@gzdecode(base64_decode($payload));
if($html===false){ http_response_code(500); echo 'Book Author application payload invalid.'; exit; }
echo $html;
