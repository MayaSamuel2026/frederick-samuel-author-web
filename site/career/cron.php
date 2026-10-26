<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/lib.php';
if(!configured()){fwrite(STDERR,"Not configured\n");exit(1);}
$lock=fopen(priv().'/scan.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))exit(0);
try{$result=crawl();echo json_encode($result,JSON_UNESCAPED_UNICODE)."\n";}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}finally{flock($lock,LOCK_UN);fclose($lock);}
