<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/lib.php';
require __DIR__.'/sources.php';
require __DIR__.'/boards.php';
if(!configured()){fwrite(STDERR,"Not configured\n");exit(1);}
$lock=fopen(priv().'/scan.lock','c');
if(!flock($lock,LOCK_EX|LOCK_NB))exit(0);
try {
    // Same source coverage as the signed-in "Refresh sources" action.
    $result=crawl();
    $boards=career_public_board_scan(3); // Three rotating queries per priority portal in unattended runs
    $result['new']+=$boards['new'];
    $result['examined']+=$boards['examined'];
    $result['errors']=array_merge($result['errors'],$boards['errors']);
    $result['boards']=$boards['sources'];
    // Gmail is optional; it is read only after website-side OAuth authorization.
    if(setting('gmail_refresh')!==''){
        $mail=career_import_gmail_alerts(8);
        $result['new']+=$mail['new'];
        $result['examined']+=$mail['examined'];
        $result['errors']=array_merge($result['errors'],$mail['errors']);
        $result['gmail']=$mail;
    }
    setsetting('last_scan_info',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
finally{flock($lock,LOCK_UN);fclose($lock);}
