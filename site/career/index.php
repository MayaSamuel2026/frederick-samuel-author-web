<?php
declare(strict_types=1);
require __DIR__.'/lib.php';
require __DIR__.'/language.php';
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header('Content-Security-Policy: frame-ancestors \'none\'');
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')ini_set('session.cookie_secure','1');
session_name('FSCAREER');session_start();
$_SESSION['csrf']??=bin2hex(random_bytes(20));
$api=(string)($_GET['api']??'');
if($api==='login'){
    if($_SERVER['REQUEST_METHOD']!=='POST')fail('Method not allowed',405);
    if(!configured())fail('Private server configuration required',503);
    if(($_SESSION['attempts']??0)>8 && time()-($_SESSION['last_attempt']??0)<900)fail('Too many attempts; try later',429);
    $password=(string)($_POST['password']??'');
    if(!password_verify($password,cfg('password_hash'))){$_SESSION['attempts']=($_SESSION['attempts']??0)+1;$_SESSION['last_attempt']=time();fail('Incorrect password',401);}
    session_regenerate_id(true);$_SESSION['authenticated']=true;$_SESSION['attempts']=0;$_SESSION['csrf']=bin2hex(random_bytes(20));jsonout(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}
if($api==='logout'){if($_SERVER['REQUEST_METHOD']!=='POST'||!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??'')))fail('CSRF',403);$_SESSION=[];session_destroy();jsonout(['ok'=>true]);}
$authenticated=($_SESSION['authenticated']??false)===true;
if($api!==''&&!$authenticated)fail('Please sign in',401);
if($authenticated&&isset($_GET['oauth'])&&$_GET['oauth']==='start'){
    if(!cfg('google_client_id')||!cfg('google_client_secret')){http_response_code(503);exit('Google OAuth client must be configured first.');}
    $state=bin2hex(random_bytes(20));$_SESSION['google_oauth_state']=$state;
    $url='https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>cfg('google_client_id'),'redirect_uri'=>'https://fredericksamuel.com/career/index.php?oauth=callback','response_type'=>'code','scope'=>'https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.readonly','access_type'=>'offline','prompt'=>'consent','state'=>$state,'login_hint'=>'fredericksamuel1965@gmail.com']);
    header('Location: '.$url,true,302);exit;
}
if($authenticated&&($_GET['oauth']??'')==='callback'){
    $state=(string)($_GET['state']??'');if(!$state||!hash_equals($_SESSION['google_oauth_state']??'',$state)){http_response_code(403);exit('OAuth state mismatch');}unset($_SESSION['google_oauth_state']);
    $code=(string)($_GET['code']??'');if(!$code){http_response_code(400);exit('Authorization canceled');}
    $c=curl_init('https://oauth2.googleapis.com/token');curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['code'=>$code,'client_id'=>cfg('google_client_id'),'client_secret'=>cfg('google_client_secret'),'redirect_uri'=>'https://fredericksamuel.com/career/index.php?oauth=callback','grant_type'=>'authorization_code']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);$response=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$token=json_decode((string)$response,true);
    if($status!==200||empty($token['refresh_token'])){http_response_code(502);exit('Gmail did not issue a refresh token; reconnect with Google authorization.');}
    $gc=curl_init('https://gmail.googleapis.com/gmail/v1/users/me/profile');curl_setopt_array($gc,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token['access_token']],CURLOPT_TIMEOUT=>15]);$verify=json_decode((string)curl_exec($gc),true);$check=curl_getinfo($gc,CURLINFO_HTTP_CODE);curl_close($gc);
    if($check!==200||strtolower((string)($verify['emailAddress']??''))!=='fredericksamuel1965@gmail.com'){http_response_code(403);exit('Please authorize fredericksamuel1965@gmail.com only.');}
    setsetting('gmail_refresh',$token['refresh_token']);header('Location: ./?connected=1',true,303);exit;
}
if($api!==''){
 try{
    $mutating=!in_array($api,['list','inbox','sent','download','pdf','status'],true);
    if($mutating){if($_SERVER['REQUEST_METHOD']!=='POST')fail('POST required',405);if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??'')))fail('Invalid request token',403);}
    if($api==='list'){
        $cleanup=career_archive_unsuitable_jobs();
        $jobs=array_values(array_filter(rows('SELECT * FROM jobs WHERE status != "archived" ORDER BY CASE status WHEN "interview" THEN 0 WHEN "applied" THEN 1 ELSE 2 END, id DESC LIMIT 3000'),static fn($j)=>career_job_eligible($j)));
        $files=rows('SELECT id,name,kind,mime,size,uploaded_at FROM files ORDER BY id DESC');
        $sent=rows('SELECT sent.id,sent.job_id,sent.recipient,sent.subject,sent.created_at,jobs.company FROM sent LEFT JOIN jobs ON jobs.id=sent.job_id ORDER BY sent.id DESC LIMIT 150');
        $counts=[];foreach($jobs as $j){$st=(string)$j['status'];$counts[$st]=($counts[$st]??0)+1;}
        $stats=[];foreach($counts as $status=>$n)$stats[]=['status'=>$status,'n'=>$n];
        jsonout(['ok'=>true,'jobs'=>$jobs,'files'=>$files,'sent'=>$sent,'stats'=>$stats,'profile'=>['name'=>setting('name','Frederick Samuel'),'address'=>setting('address',"Marienburger Straße 16\n56112 Lahnstein\nDeutschland"),'phone'=>setting('phone'),'default_language'=>setting('default_language','de')],'gmail_connected'=>setting('gmail_refresh')!=='','gmail_available'=>cfg('google_client_id')!==''&&cfg('google_client_secret')!=='','last_scan'=>setting('last_scan'),'scan_info'=>json_decode(setting('last_scan_info','{}'),true),'cleanup'=>$cleanup]);
    }
    if($api==='scan')jsonout(['ok'=>true,'result'=>crawl()]);
    if($api==='importjobs'){
        if(!isset($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)fail('Select the private Gmail job export JSON');
        $file=$_FILES['file'];if($file['size']>3*1024*1024)fail('Import file exceeds 3 MB');
        $body=file_get_contents($file['tmp_name']);$data=json_decode((string)$body,true);
        if(!is_array($data)||($data['schema']??'')!=='frederick-career-mail-import/1.0'||!is_array($data['jobs']??null))fail('Invalid Gmail job import file');
        $added=0;$duplicates=0;$rejected=0;$total=0;
        foreach(array_slice($data['jobs'],0,500) as $job){
            if(!is_array($job))continue;$total++;
            $title=(string)($job['title']??'');$location=(string)($job['location']??'');$source=(string)($job['source']??'Gmail');
            if(!career_job_eligible(['title'=>$title,'location'=>$location,'source'=>$source,'description'=>''])){$rejected++;continue;}
            $record=['source'=>'Gmail · '.$source,'title'=>$title,'company'=>$job['company']??'','location'=>$location,'url'=>$job['url']??'','description'=>'Job alert received '.($job['mail_date']??'')];
            if(upsertjob($record))$added++;else $duplicates++;
        }
        setsetting('last_mail_import',gmdate('Y-m-d H:i:s').' UTC');
        jsonout(['ok'=>true,'result'=>['new'=>$added,'duplicates'=>$duplicates,'rejected'=>$rejected,'examined'=>$total]]);
    }

    if($api==='refine'){
        $id=(int)($_POST['id']??0);$job=onejob($id);
        $lang=($_POST['lang']??'de')==='en'?'en':'de';
        $tone=in_array($_POST['tone']??'natural',['natural','confident','concise'],true)?$_POST['tone']:'natural';
        $draft=clean((string)($_POST['letter']??''),10000);
        jsonout(['ok'=>true,'letter'=>career_refine_language($draft,$job,$lang,$tone)]);
    }
    if($api==='job'){
        $company=clean((string)($_POST['company']??''),160);$title=clean((string)($_POST['title']??''),200);$url=clean((string)($_POST['url']??''),2000);
        if(!$company||!$title||!filter_var($url,FILTER_VALIDATE_URL))fail('Company, position and valid URL required');
        $n=upsertjob(['source'=>'Manual','company'=>$company,'title'=>$title,'url'=>$url,'location'=>$_POST['location']??'','description'=>$_POST['description']??'']);if(!$n)fail('Duplicate, unsuitable position, or location outside Germany');jsonout(['ok'=>true]);
    }
    if($api==='update'){
        $id=(int)($_POST['id']??0);onejob($id);
        $allowed=['new','shortlisted','draft','applied','interview','rejected','offer','archived'];$status=clean((string)($_POST['status']??''),30);
        if(!in_array($status,$allowed,true))fail('Invalid status');
        $job=onejob($id);$when=$status==='applied'&&!$job['applied_at']?gmdate('Y-m-d H:i:s'):$job['applied_at'];
        $reply=in_array($status,['interview','rejected','offer'],true)&&!$job['reply_at']?gmdate('Y-m-d H:i:s'):$job['reply_at'];
        db()->prepare('UPDATE jobs SET status=?,applied_at=?,reply_at=?,note=? WHERE id=?')->execute([$status,$when,$reply,clean((string)($_POST['note']??$job['note']),2000),$id]);jsonout(['ok'=>true]);
    }
    if($api==='draft'){
        $id=(int)($_POST['id']??0);$j=onejob($id);$lang=($_POST['lang']??'de')==='en'?'en':'de';$letter=isset($_POST['letter'])?clean((string)$_POST['letter'],10000):career_letter_voice($j,$lang,clean((string)($_POST['tone']??'natural'),20));
        db()->prepare('UPDATE jobs SET letter=?,lang=?,email=?,status=CASE WHEN status="new" THEN "draft" ELSE status END WHERE id=?')->execute([$letter,$lang,clean((string)($_POST['email']??$j['email']),200),$id]);jsonout(['ok'=>true,'letter'=>$letter]);
    }
    if($api==='profile'){
        foreach(['name'=>180,'address'=>600,'phone'=>100,'default_language'=>5] as $k=>$len){$v=clean((string)($_POST[$k]??''),$len);if($k==='default_language'&&!in_array($v,['de','en'],true))$v='de';setsetting($k,$v);}jsonout(['ok'=>true]);
    }
    if($api==='upload'){
        if(!isset($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)fail('Upload failed (server size limit may apply)');
        $f=$_FILES['file'];if($f['size']>10*1024*1024)fail('Maximum 10 MB per file');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$allow=['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document','image/png','image/jpeg'];if(!in_array($mime,$allow,true))fail('Use PDF, DOCX, PNG or JPEG');
        $kind=clean((string)($_POST['kind']??'other'),20);if(!in_array($kind,['cv','certificate','other','signature'],true))fail('Invalid document type');
        $directory=priv().'/uploads';if(!is_dir($directory)&&!mkdir($directory,0700,true))fail('Storage unavailable',500);
        $path=$directory.'/'.bin2hex(random_bytes(20));if(!move_uploaded_file($f['tmp_name'],$path))fail('Could not save upload',500);chmod($path,0600);
        if($kind==='signature')db()->exec('DELETE FROM files WHERE kind="signature"');
        db()->prepare('INSERT INTO files(name,kind,path,mime,size) VALUES (?,?,?,?,?)')->execute([clean(basename((string)$f['name']),180),$kind,$path,$mime,$f['size']]);jsonout(['ok'=>true]);
    }
    if($api==='download'){
        $id=(int)($_GET['id']??0);$q=db()->prepare('SELECT * FROM files WHERE id=?');$q->execute([$id]);$r=$q->fetch();if(!$r||!is_file($r['path']))fail('File not found',404);
        header('Content-Type: '.$r['mime']);header('Content-Length: '.$r['size']);header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',basename($r['name'])).'"');readfile($r['path']);exit;
    }
    if($api==='deletefile'){$id=(int)($_POST['id']??0);$q=db()->prepare('SELECT path FROM files WHERE id=?');$q->execute([$id]);$path=$q->fetchColumn();if($path&&is_file($path))unlink($path);db()->prepare('DELETE FROM files WHERE id=?')->execute([$id]);jsonout(['ok'=>true]);}
    if($api==='pdf'){
        $job=onejob((int)($_GET['id']??0));$letter=$job['letter']?:jobletter($job,$job['lang']);$pdf=pdftext($job,$letter);
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="Frederick_Samuel_'.preg_replace('/[^A-Za-z0-9_-]/','_',mb_substr($job['company'],0,35)).'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
    }
    if($api==='send'){
        $id=(int)($_POST['id']??0);$j=onejob($id);$to=clean((string)($_POST['email']??''),200);if(!filter_var($to,FILTER_VALIDATE_EMAIL))fail('A verified recipient email address is required');
        $letter=clean((string)($_POST['letter']??''),10000);if(!$letter)fail('Please review the cover letter before sending');
        $lang=($_POST['lang']??'de')==='en'?'en':'de';$subject=($lang==='de'?'Bewerbung als ':'Application — ').$j['title'];
        $chosen=json_decode((string)($_POST['documents']??'[]'),true);if(!is_array($chosen))$chosen=[];$docs=[];
        foreach(array_slice($chosen,0,8) as $fid){$q=db()->prepare('SELECT * FROM files WHERE id=? AND kind!="signature"');$q->execute([(int)$fid]);if($d=$q->fetch())$docs[]=$d;}
        $html='<div style="font-family:Arial,sans-serif;max-width:680px;color:#222;line-height:1.7">'.nl2br(htmlspecialchars($letter,ENT_QUOTES,'UTF-8')).'</div>';
        $sig=rows('SELECT * FROM files WHERE kind="signature" ORDER BY id DESC LIMIT 1');
        $inline=null;
        if($sig&&in_array($sig[0]['mime'],['image/png','image/jpeg'],true)&&is_file($sig[0]['path'])){$inline=$sig[0];$html.='<p><img src="cid:fs-signature" alt="Frederick Samuel signature" style="max-width:240px;height:auto"></p>';}
        $raw=mailraw($to,$subject,$html,$letter,$docs,$inline);
        $sent=gmailcall('POST','messages/send',['raw'=>$raw]);
        db()->prepare('INSERT INTO sent(job_id,recipient,subject,body,gmail_id) VALUES (?,?,?,?,?)')->execute([$id,$to,$subject,$letter,$sent['id']??null]);
        db()->prepare('UPDATE jobs SET status="applied",applied_at=COALESCE(applied_at,CURRENT_TIMESTAMP),email=?,letter=?,lang=? WHERE id=?')->execute([$to,$letter,$lang,$id]);
        jsonout(['ok'=>true,'gmail_id'=>$sent['id']??null]);
    }
    if($api==='inbox'){
        $list=gmailcall('GET','messages?'.http_build_query(['q'=>'in:inbox newer_than:120d','maxResults'=>15]));$out=[];
        foreach(($list['messages']??[]) as $m){$message=gmailcall('GET','messages/'.rawurlencode($m['id']).'?format=metadata&metadataHeaders=From&metadataHeaders=Subject&metadataHeaders=Date');$heads=[];foreach(($message['payload']['headers']??[]) as $h)$heads[strtolower($h['name'])]=$h['value'];$out[]=['id'=>$m['id'],'thread_id'=>$m['threadId'],'from'=>$heads['from']??'','subject'=>$heads['subject']??'','date'=>$heads['date']??'','snippet'=>$message['snippet']??''];}
        jsonout(['ok'=>true,'messages'=>$out]);
    }
    if($api==='sent')jsonout(['ok'=>true,'messages'=>rows('SELECT sent.*,jobs.company,jobs.title FROM sent LEFT JOIN jobs ON sent.job_id=jobs.id ORDER BY sent.id DESC LIMIT 200')]);
    fail('Unknown action',404);
 }catch(Throwable $e){error_log('FS CAREER: '.$e->getMessage());fail($e->getMessage(),500);}
}
$ready=configured();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Career Workspace — Frederick Samuel</title><link rel="stylesheet" href="style.css?v=20261026-germany-import"></head>
<body>
<?php if(!$ready): ?><main class="gate"><div class="gate-card"><div class="monogram">FS<span>.</span></div><p class="eyebrow">PRIVATE CAREER WORKSPACE</p><h1>Private setup required.</h1><p>The application is installed but is locked until a server-side administrator password is configured. No personal documents or applicant data are publicly accessible.</p><p><a class="button primary" href="setup.php">Create my private login →</a></p><p class="muted">A verification link will be emailed only to fredericksamuel1965@gmail.com.</p></div></main>
<?php elseif(!$authenticated): ?><main class="gate"><form id="loginForm" class="gate-card"><div class="monogram">FS<span>.</span></div><p class="eyebrow">PRIVATE · PERSÖNLICH</p><h1>Career, with purpose.</h1><p>Find the right opportunity. Put experience to work.</p><label>Private password<input name="password" type="password" autocomplete="current-password" required autofocus></label><button class="button primary" type="submit">Enter workspace <span>→</span></button><p id="loginError" class="error" role="alert"></p><small>Frederick Samuel · Secure private workspace · DE / EN</small></form></main><script>document.getElementById('loginForm').onsubmit=async e=>{e.preventDefault();const form=e.target;const fd=new FormData(form);const r=await fetch('?api=login',{method:'POST',body:fd});const d=await r.json();if(d.ok)location.reload();else document.getElementById('loginError').textContent=d.error||'Sign-in failed';}</script>
<?php else: ?>
<div class="app" id="app">
<aside class="sidebar">
 <a href="./" class="brand"><span class="brandmark">FS<span>.</span></span><span><b>Frederick Samuel</b><small>CAREER WORKSPACE</small></span></a>
 <p class="nav-label" data-i="workspace">WORKSPACE</p>
 <nav id="navigation"><button class="nav active" data-page="overview"><span>▦</span><span data-i="overview">Overview</span></button><button class="nav" data-page="jobs"><span>⌕</span><span data-i="jobs">Job discovery</span><span class="nav-number" id="jobCount">0</span></button><button class="nav" data-page="mail"><span>✉</span><span data-i="mail">Application studio</span></button><button class="nav" data-page="pipeline"><span>▤</span><span data-i="pipeline">My applications</span></button><button class="nav" data-page="inbox"><span>↙</span><span data-i="inbox">Inbox</span></button><button class="nav" data-page="sent"><span>↗</span><span data-i="sent">Sent mail</span></button><button class="nav" data-page="documents"><span>▧</span><span data-i="documents">Documents</span></button><button class="nav" data-page="settings"><span>⚙</span><span data-i="settings">Settings</span></button></nav>
 <div class="sidebar-bottom"><div class="mail-indicator"><span class="dot" id="mailDot"></span><div><small>GOOGLE GMAIL</small><strong id="mailStatus">Not connected</strong></div></div><button class="logout" id="logout" data-i="signout">Sign out ↗</button></div>
</aside>
<div class="main-area"><header class="topbar"><div class="breadcrumbs">FREDERICK SAMUEL <span>/</span> <strong id="breadcrumb">Overview</strong></div><div class="top-actions"><span id="today"></span><div class="language"><button data-language="en" class="active">EN</button><button data-language="de">DE</button></div><span class="avatar">FS</span></div></header>
<main class="content">
<section class="page shown" id="page-overview"><div class="page-intro"><span class="eyebrow" data-i="desk">PERSONAL CAREER DESK</span><h1 data-i="heading">Your next chapter,<br><em>intentionally chosen.</em></h1><p data-i="welcome">A calm, organized place to discover the right leadership positions, write tailored applications and follow every conversation.</p></div><div class="metrics"><div class="metric"><span data-i="found">Saved opportunities</span><strong id="statJobs">0</strong><small data-i="foundNote">Across all sources</small></div><div class="metric"><span data-i="shortlisted">Shortlisted</span><strong id="statShort">0</strong><small data-i="shortNote">Ready to review</small></div><div class="metric"><span data-i="applied">Applications sent</span><strong id="statApplied">0</strong><small data-i="appliedNote">Including manually recorded</small></div><div class="metric"><span data-i="interviews">Interviews</span><strong id="statInterviews">0</strong><small data-i="interviewNote">Ongoing conversations</small></div></div><div class="two-columns"><div class="panel"><div class="panel-head"><div><span class="eyebrow" data-i="discover">DISCOVERY</span><h2 data-i="current">Latest opportunities</h2></div><button class="text-button" data-go="jobs" data-i="viewall">View all →</button></div><div id="latestJobs" class="latest-list"></div></div><div class="panel action-panel"><span class="eyebrow" data-i="nextStep">NEXT STEP</span><h2 data-i="moveForward">Make your next move.</h2><p data-i="nextCopy">Search matching senior roles, shortlist positions and prepare an individual cover letter before sending.</p><button class="button primary" id="scanOverview" data-i="scanNow">Find new opportunities ↗</button><p class="muted tiny" id="lastRun"></p><div class="divider"></div><span class="eyebrow" data-i="focus">SEARCH FOCUS</span><div class="chips"><span>Pharma Sales</span><span>Sales Director</span><span>Regionalleiter</span><span>Commercial Excellence</span><span>Commercial Effectiveness</span></div></div></div></section>
<section class="page" id="page-jobs"><div class="heading-row"><div><p class="eyebrow" data-i="discovery">OPPORTUNITY FINDER</p><h1 data-i="roles">Discover relevant roles.</h1><p data-i="rolesCopy">Search, filter and save positions with their original application links.</p></div><button class="button primary" id="scanJobs" data-i="refresh">↻ Refresh job feeds</button><button class="button outline" id="importAlertJobs" data-i="importAlerts">Import Gmail alerts</button><input id="importAlertFile" type="file" accept=".json,application/json" hidden></div><div class="toolbar"><label class="search"><span>⌕</span><input id="jobSearch" placeholder="Company, role, city..."></label><select id="sourceFilter"><option value="">All sources</option></select><select id="jobFilter"><option value="">All statuses</option><option value="new">New</option><option value="shortlisted">Shortlisted</option><option value="draft">Draft</option><option value="applied">Applied</option><option value="interview">Interview</option></select><button class="button outline" id="addJob" data-i="addListing">+ Add URL</button></div><div class="board-links"><span data-i="searchDirect">SEARCH JOB BOARDS DIRECTLY</span><a href="https://www.stepstone.de/jobs/pharma-sales-manager" target="_blank" rel="noopener">StepStone ↗</a><a href="https://de.indeed.com/jobs?q=pharma+sales+director" target="_blank" rel="noopener">Indeed ↗</a><a href="https://www.jobpharm.de/search?r=60&q=Leiter%20pharma" target="_blank" rel="noopener">JobPharm ↗</a><a href="https://www.michaelpage.de/jobs/vertriebsleiter" target="_blank" rel="noopener">Michael Page ↗</a><a href="https://www.linkedin.com/jobs/search/?keywords=Commercial%20Effectiveness%20Manager&location=Germany" target="_blank" rel="noopener">LinkedIn ↗</a><a href="https://www.arbeitsagentur.de/jobsuche/" target="_blank" rel="noopener">Arbeitsagentur ↗</a></div><div class="selection-bar"><span id="selectedCount">0 selected</span><button class="button primary" id="applySelected" data-i="prepare">Prepare application →</button></div><div class="panel list-panel"><table class="jobs-table"><thead><tr><th></th><th data-i="position">POSITION / COMPANY</th><th data-i="location">LOCATION</th><th data-i="source">SOURCE</th><th data-i="status">STATUS</th><th></th></tr></thead><tbody id="jobsTable"></tbody></table><div class="empty" id="jobsEmpty" data-i="emptyJobs">No matching jobs yet. Run a discovery scan or add a vacancy URL.</div></div></section>
<section class="page" id="page-mail"><div class="heading-row"><div><p class="eyebrow" data-i="appstudio">APPLICATION STUDIO</p><h1 data-i="personalLetter">A letter that sounds like you.</h1><p data-i="mailCopy">Select a vacancy, generate an individual letter and review it before sending.</p></div><button class="button outline" data-go="jobs" data-i="chooseJob">← Choose a vacancy</button></div><div class="composer"><div class="composer-main panel"><div class="form-row"><label><span data-i="vacancy">VACANCY</span><select id="composeJob"></select></label><label><span data-i="letterLang">LETTER LANGUAGE</span><select id="composeLang"><option value="de">Deutsch</option><option value="en">English</option></select></label></div><div class="form-row"><label><span data-i="recipient">RECRUITER EMAIL (REQUIRED TO SEND)</span><input type="email" id="composeTo" placeholder="recruiting@company.com"></label><div class="generate-wrap"><button class="button outline" id="generateLetter" data-i="generate">✧ Generate tailored letter</button></div></div><div class="language-polish" role="group" aria-label="Language refinement"><label><span data-i="toneLabel">WRITING STYLE</span><select id="refineTone"><option value="natural" data-i="toneNatural">Natural</option><option value="confident" data-i="toneConfident">Confident</option><option value="concise" data-i="toneConcise">Concise</option></select></label><button class="button outline" type="button" id="refineLetter" data-i="refineButton">✧ Refine language</button><button class="text-button" type="button" id="undoRefinement" hidden data-i="undoRefinement">Undo refinement</button></div><p class="small muted refine-note" data-i="refineNote">Improves phrasing locally. Your details stay on this server. Review before sending.</p><label class="block-label"><span data-i="letter">COVER LETTER — EDIT BEFORE SENDING</span><textarea id="composeBody" rows="17" placeholder="Choose a job to create its letter..."></textarea></label><div class="composer-footer"><button class="button outline" id="saveLetter" data-i="saveDraft">Save draft</button><a class="button outline" href="#" id="pdfLetter" data-i="pdf">↓ Download letter PDF</a><button class="button primary" id="sendLetter" data-i="send">Send via Gmail →</button></div></div><div class="composer-aside"><div class="panel"><span class="eyebrow" data-i="filesAtt">ATTACHMENTS</span><h3 data-i="selectDocs">Choose documents</h3><p class="small muted" data-i="docCopy">Upload your CV and certificates in Documents, then select them for this application.</p><div id="composeFiles" class="file-choices"></div><button class="text-button" data-go="documents" data-i="manage">Manage documents ↗</button></div><div class="panel signature-card"><span class="eyebrow" data-i="signature">SIGNATURE</span><h3>Frederick Samuel</h3><p class="small muted" data-i="signatureCopy">Your signature image is added to HTML mail when uploaded in Documents.</p><span id="signatureState" class="small"></span></div></div></div></section>
<section class="page" id="page-pipeline"><div class="heading-row"><div><p class="eyebrow" data-i="tracking">APPLICATION TRACKER</p><h1 data-i="everyStep">Every application. Every outcome.</h1><p data-i="trackingCopy">Track application dates, interviews, rejections and offers in one place.</p></div><button class="button outline" data-go="jobs" data-i="findRoles">+ Find roles</button></div><div class="panel list-panel"><table class="jobs-table"><thead><tr><th data-i="company">COMPANY</th><th data-i="position">POSITION</th><th data-i="discovered">DISCOVERED</th><th data-i="appliedDate">APPLIED</th><th data-i="status">STATUS</th><th data-i="note">NOTES</th></tr></thead><tbody id="pipelineTable"></tbody></table></div></section>
<section class="page" id="page-inbox"><div class="heading-row"><div><p class="eyebrow">GMAIL</p><h1 data-i="replyInbox">Replies, all in one place.</h1><p data-i="inboxCopy">Read incoming mail from your connected Gmail account.</p></div><button class="button outline" id="refreshInbox" data-i="loadInbox">↻ Load inbox</button></div><div class="panel"><div id="inboxRows" class="email-list"><p class="muted">Connect Gmail in Settings, then load your messages.</p></div></div></section>
<section class="page" id="page-sent"><div class="heading-row"><div><p class="eyebrow">APPLICATION HISTORY</p><h1 data-i="sentLetters">Sent and recorded.</h1><p data-i="sentCopy">Applications sent by this workspace are saved here and in Gmail Sent.</p></div></div><div class="panel"><div id="sentRows" class="email-list"></div></div></section>
<section class="page" id="page-documents"><div class="heading-row"><div><p class="eyebrow" data-i="documentVault">PRIVATE DOCUMENT VAULT</p><h1 data-i="documentsTitle">Your credentials, ready.</h1><p data-i="documentsCopy">Files remain outside the public website directory and require your login to access.</p></div></div><div class="panel"><p class="eyebrow">PRIVATE GOOGLE DRIVE · PERSÖNLICHE DOKUMENTE</p><h3>CVs &amp; certificates / Lebensläufe &amp; Zeugnisse</h3><p class="muted small">These files are stored privately in your Google Drive. For direct attachment to an application, download the file and upload it into the private document vault below. · Die Dateien liegen geschützt in Ihrem Google Drive. Für den E-Mail-Versand bitte die gewünschte Datei unten in die Dokumentenablage hochladen.</p><div class="stored-file"><div><span class="doc-icon">▤</span><div><strong>English CV — original German layout</strong><small>PDF · 4 pages · English</small></div></div><a class="text-button" href="https://drive.google.com/file/d/18iGd7NGoxA8TTxDwVX3Knm_Oy_WanyoN/view" target="_blank" rel="noopener noreferrer">Open ↗</a></div><div class="stored-file"><div><span class="doc-icon">▤</span><div><strong>Deutscher Lebenslauf</strong><small>PDF · 4 Seiten · Deutsch</small></div></div><a class="text-button" href="https://drive.google.com/file/d/1Ed9u1jnT43h38G_xW4LeQrW-E7kmMRBK/view" target="_blank" rel="noopener noreferrer">Öffnen ↗</a></div><div class="stored-file"><div><span class="doc-icon">▤</span><div><strong>Arbeitszeugnisse / Employment certificates</strong><small>PDF · 23 pages</small></div></div><a class="text-button" href="https://drive.google.com/file/d/1l_pmv3IHjfZOXCKFPak3ar5mBPUQK0SY/view" target="_blank" rel="noopener noreferrer">Open ↗</a></div></div><div class="panel upload-panel"><form id="uploadForm"><div><h3 data-i="upload">Upload a document</h3><p class="muted small" data-i="fileLimit">PDF, DOCX, PNG or JPEG · 10 MB maximum</p></div><select name="kind"><option value="cv">CV / Lebenslauf</option><option value="certificate">Zeugnis / Certificate</option><option value="signature">Unterschrift / Signature</option><option value="other">Other / Sonstiges</option></select><input type="file" name="file" accept=".pdf,.docx,.png,.jpg,.jpeg" required><button class="button primary" data-i="uploadButton">Upload →</button></form></div><div class="panel" id="filesList"></div></section>
<section class="page" id="page-settings"><div class="heading-row"><div><p class="eyebrow" data-i="preferences">PREFERENCES & CONNECTIONS</p><h1 data-i="settingsTitle">Your workspace, your details.</h1><p data-i="settingsCopy">Used for correctly formatted letters and personal correspondence.</p></div></div><div class="two-columns"><div class="panel"><h3 data-i="senderProfile">Sender profile</h3><form id="profileForm" class="settings-form"><label><span data-i="fullName">FULL NAME</span><input name="name" id="profileName"></label><label><span data-i="postalAddress">POSTAL ADDRESS FOR COVER LETTER PDF</span><textarea name="address" id="profileAddress" rows="4" placeholder="Street and number&#10;Postal code and city&#10;Germany"></textarea></label><label><span data-i="phone">PHONE (OPTIONAL)</span><input name="phone" id="profilePhone"></label><label><span data-i="defaultLanguage">DEFAULT LANGUAGE</span><select name="default_language" id="profileLanguage"><option value="de">Deutsch</option><option value="en">English</option></select></label><button class="button primary" data-i="saveProfile">Save profile →</button></form></div><div class="panel"><span class="eyebrow">MAIL INTEGRATION</span><h3 data-i="gmailTitle">Connect your Gmail account</h3><p class="muted" data-i="gmailCopy">Authorize Google securely. Your Gmail password is never stored on the website. Outgoing applications also appear in Gmail Sent.</p><p class="account-email">fredericksamuel1965@gmail.com</p><p id="gmailDetail" class="muted small"></p><a href="?oauth=start" class="button outline" id="gmailConnect" data-i="connectGmail">Connect Gmail with Google ↗</a><div class="divider"></div><p class="muted small" data-i="securityCopy">Private credentials and OAuth tokens are stored only on your hosting account, outside the public directory. Sending always requires your explicit confirmation.</p></div></div></section>
</main><div class="toast" id="toast" role="status"></div></div></div>
<div class="modal-backdrop" id="jobModal"><form id="manualForm" class="modal"><div class="modal-head"><h2 data-i="addManual">Add an opportunity</h2><button type="button" id="closeModal">×</button></div><label><span data-i="company">COMPANY</span><input name="company" required></label><label><span data-i="position">POSITION</span><input name="title" required></label><label><span data-i="location">LOCATION</span><input name="location"></label><label><span data-i="postingUrl">ORIGINAL JOB POSTING URL</span><input name="url" type="url" required></label><label><span data-i="jobDetails">JOB DETAILS / REQUIREMENTS</span><textarea name="description" rows="4"></textarea></label><button class="button primary" data-i="saveListing">Save opportunity →</button></form></div>
<script>window.FS_CSRF=<?php echo json_encode($_SESSION['csrf']); ?>;</script><script src="app.js?v=20261026-germany-import"></script>
<?php endif; ?></body></html>
