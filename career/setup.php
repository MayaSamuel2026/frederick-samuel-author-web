<?php
declare(strict_types=1);
/** First-run login creation, verified by a single-use link sent only to the owner's known Gmail address. */
require __DIR__.'/lib.php';
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Content-Security-Policy: frame-ancestors \'none\'');
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure','1');
session_name('FSCAREER');session_start();
$_SESSION['setup_csrf'] ??= bin2hex(random_bytes(20));
$owner='fredericksamuel1965@gmail.com';
$stateFile=priv().'/.setup-token.json';
$error='';$notice='';$valid=false;
if (configured()) {header('Location: ./',true,303);exit;}
function same_token(string $submitted,string $file): bool {
    if (!preg_match('/^[a-f0-9]{48}$/D',$submitted) || !is_file($file)) return false;
    $rec=json_decode((string)file_get_contents($file),true);
    return is_array($rec) && (int)($rec['expires']??0)>time() && hash_equals((string)($rec['hash']??''),hash('sha256',$submitted));
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals((string)$_SESSION['setup_csrf'],(string)($_POST['csrf']??''))) {$error='Invalid request. Reload the page.';}
    elseif (($_POST['action']??'')==='send') {
        if (is_file($stateFile) && filemtime($stateFile)>time()-300) {$notice='A setup email has been requested recently. Check your inbox or try again shortly.';}
        else {
            if (!is_dir(priv()) && !mkdir(priv(),0700,true)) {$error='Cannot initialize private storage.';}
            else {
                $token=bin2hex(random_bytes(24));
                $record=['hash'=>hash('sha256',$token),'expires'=>time()+1800];
                if (file_put_contents($stateFile,json_encode($record),LOCK_EX)===false) {$error='Could not save the setup request.';}
                else {
                    chmod($stateFile,0600);
                    $link='https://fredericksamuel.com/career/setup.php?token='.$token;
                    $sent=@mail($owner,'Frederick Samuel — private career login setup',"A setup link was requested for the private Career Workspace.\n\nOpen this link within 30 minutes to choose your private password:\n{$link}\n\nIf you did not request this, ignore the email.\n",['From'=>'Frederick Samuel Website <frederick@fredericksamuel.com>','Content-Type'=>'text/plain; charset=UTF-8']);
                    if ($sent) $notice='Check fredericksamuel1965@gmail.com for a private one-time setup link (valid 30 minutes).';
                    else {$error='The website mail service could not deliver your setup link. Use the private Hostinger installer instead.';@unlink($stateFile);}
                }
            }
        }
    } elseif (($_POST['action']??'')==='complete') {
        $token=(string)($_POST['token']??'');$pw=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm']??'');
        if (!same_token($token,$stateFile)) $error='This setup link has expired. Request a new one.';
        elseif (strlen($pw)<14 || $pw!==$confirm) $error='Use a matching password with at least 14 characters.';
        else {
            $hash=password_hash($pw,PASSWORD_DEFAULT);
            $config=['password_hash'=>$hash,'google_client_id'=>'','google_client_secret'=>''];
            $configFile=priv().'/config.php';
            if (!is_file($configFile)) {
                $temp=priv().'/.config-'.bin2hex(random_bytes(6));
                $body="<?php\nreturn ".var_export($config,true).";\n";
                if (file_put_contents($temp,$body,LOCK_EX)!==false){chmod($temp,0600);rename($temp,$configFile);}
            }
            if (!is_file($configFile)) $error='Server could not save the private password. Try again.';
            else {@unlink($stateFile);session_regenerate_id(true);$_SESSION['authenticated']=true;$_SESSION['csrf']=bin2hex(random_bytes(20));header('Location: ./',true,303);exit;}
        }
    }
}
$token=(string)($_POST['token']??$_GET['token']??'');$valid=same_token($token,$stateFile);
function e(string $s): string {return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Private setup — Frederick Samuel</title><link rel="stylesheet" href="style.css"></head><body><main class="gate"><div class="gate-card"><div class="monogram">FS<span>.</span></div><p class="eyebrow">PRIVATE CAREER WORKSPACE</p><h1>Set up your private login.</h1><p>Personal applications and documents are accessible only after signing in.</p>
<?php if($error):?><p class="error" role="alert"><?=e($error)?></p><?php endif;?>
<?php if($notice):?><p><?=e($notice)?></p><?php endif;?>
<?php if($valid):?>
<form method="post" autocomplete="off"><input type="hidden" name="action" value="complete"><input type="hidden" name="csrf" value="<?=e($_SESSION['setup_csrf'])?>"><input type="hidden" name="token" value="<?=e($token)?>"><label>Choose a password (14+ characters)<input type="password" name="password" required minlength="14" autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm" required minlength="14" autocomplete="new-password"></label><button class="button primary" type="submit">Create secure login →</button></form>
<?php else:?>
<form method="post"><input type="hidden" name="action" value="send"><input type="hidden" name="csrf" value="<?=e($_SESSION['setup_csrf'])?>"><button class="button primary" type="submit">Email my private setup link →</button></form>
<?php endif;?><p class="muted"><a href="./">← Back to login</a></p></div></main></body></html>
