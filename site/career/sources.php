<?php
declare(strict_types=1);
/**
 * Additional job sources for the private career workspace.
 * Gmail alerts are read through the owner's EXISTING server-side Gmail OAuth connection.
 * No message bodies, credentials or alert URLs are published to the source repository.
 */
function career_alert_sources(): array {
    return ['StepStone'=>'Stepstone','Indeed'=>'Indeed','JobPharm'=>'JobPharm','Jobware'=>'Jobware','ZipRecruiter'=>'ZipRecruiter'];
}
function career_alert_body_decode(array $payload): string {
    $mime=strtolower((string)($payload['mimeType']??''));
    if(str_starts_with($mime,'text/plain') || str_starts_with($mime,'text/html')) {
        $raw=(string)($payload['body']['data']??'');
        if($raw!=='')return (string)base64_decode(strtr($raw,'-_','+/'));
    }
    $parts=$payload['parts']??[];
    // Prefer the HTML portion: alerts expose individual application links there.
    foreach(['text/html','text/plain'] as $preferred) {
        foreach($parts as $part) {
            if(str_starts_with(strtolower((string)($part['mimeType']??'')),$preferred)) {
                $body=career_alert_body_decode($part);
                if($body!=='')return $body;
            }
        }
    }
    foreach($parts as $part){$body=career_alert_body_decode($part);if($body!=='')return $body;}
    return '';
}
function career_alert_text(string $raw): string {
    if(!preg_match('/<\s*(?:html|table|div|a)\b/i',$raw))return $raw;
    $raw=preg_replace_callback('~<a\b[^>]*\bhref\s*=\s*(["\x27])(.*?)\1[^>]*>(.*?)</a>~si',static function($m){
        $title=trim(html_entity_decode(strip_tags($m[3]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $url=html_entity_decode($m[2],ENT_QUOTES|ENT_HTML5,'UTF-8');
        return $title!==''?"\n[".$title."](".$url.")\n":'';
    },$raw);
    $raw=preg_replace('~</(?:p|div|tr|td|li|h[1-6]|table|section)>|<br\s*/?>~i',"\n",$raw);
    return html_entity_decode(strip_tags($raw),ENT_QUOTES|ENT_HTML5,'UTF-8');
}
/** Normalize redirect-heavy links only when the underlying job id is explicit. */
function career_alert_url(string $source,string $url): string {
    $url=html_entity_decode($url,ENT_QUOTES|ENT_HTML5,'UTF-8');
    if($source==='Indeed' && preg_match('~(?:[?&])jk=([a-z0-9]+)~i',$url,$m))
        return 'https://de.indeed.com/viewjob?jk='.$m[1];
    if($source==='JobPharm' && preg_match('~[?&]id=(\d+)~',$url,$m))
        return 'https://www.jobpharm.de/job?id='.$m[1];
    if($source==='LinkedIn' && preg_match('~/jobs/(?:view/|view/[^/]*-)(\d{7,})~',$url,$m))
        return 'https://www.linkedin.com/jobs/view/'.$m[1].'/';
    return $url;
}
function career_alert_link_ok(string $source,string $url): bool {
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    return match($source){
        'StepStone' => $host==='click.stepstone.de'||$host==='www.stepstone.de'||$host==='stepstone.de',
        'Indeed' => in_array($host,['de.indeed.com','www.indeed.com','indeed.com'],true) && preg_match('~[?&]jk=[a-z0-9]+~i',$url)===1,
        'JobPharm' => ($host==='jobpharm.de'||$host==='www.jobpharm.de')&&str_contains($url,'/job?'),
        'Jobware' => str_contains($host,'jobware.de')||str_contains($host,'jobware.com'),
        'ZipRecruiter' => str_contains($host,'ziprecruiter.'),
        'LinkedIn' => $host==='www.linkedin.com'||$host==='linkedin.com',
        default => false,
    };
}
function career_alert_candidates(string $source,string $body): array {
    $plain=career_alert_text($body);
    $matches=[];
    preg_match_all('~\[([^\]]{4,220})\]\((https?://[^\s)]+)\)~u',$plain,$matches,PREG_OFFSET_CAPTURE);
    $jobs=[];$seen=[];
    foreach($matches[0]??[] as $i=>$hit) {
        $title=trim(preg_replace('/\s+/u',' ',$matches[1][$i][0]));
        $url=$matches[2][$i][0];
        if(!career_alert_link_ok($source,$url))continue;
        // Only consider clickable vacancies, not unsubscribe, logo, company, or search links.
        if(!career_user_matches(['title'=>$title,'description'=>'']) || excluded_role($title))continue;
        $after=substr($plain,$hit[1]+strlen($hit[0]),600);
        $after=preg_replace('/\r/u','',$after);
        $lines=array_values(array_filter(array_map('trim',preg_split('/\n+/u',$after)),static fn($x)=>$x!=='' && !str_starts_with($x,'[') && $x!=='|'));
        $company='';$location='';
        if($source==='Jobware') {
            $pieces=preg_split('/\s{2,}/u',trim($matches[1][$i][0]));
            // Jobware's linked title commonly ends in a second line containing the employer.
            $rawLines=array_values(array_filter(array_map('trim',preg_split('/\R/u',$matches[1][$i][0]))));
            if(count($rawLines)>1){$title=$rawLines[0];$company=end($rawLines);}
            foreach($lines as $line){if(career_germany_location($line,$source)){$location=$line;break;}}
        } else {
            $company=$lines[0]??'';
            $location=$lines[1]??'';
            if($source==='ZipRecruiter' && str_starts_with(strtolower($location),'in '))$location=substr($location,3);
        }
        if($source==='StepStone') {
            // StepStone titles are followed by the employer, then a city/region line.
            $location='';
            foreach(array_slice($lines,1,6) as $line) {
                if(career_germany_location($line,'StepStone')){$location=$line;break;}
            }
        } elseif($source==='JobPharm') {
            // Real JobPharm mails put a standalone "|" between the company and city.
            // Never accept that separator (or email boilerplate) as a German location.
            $company='';$location='';
            foreach(array_slice($lines,0,8) as $line) {
                if($line==='|' || preg_match('/^(?:Job-Alarm|Wir wünschen|Mit besten|Ihr Team)/iu',$line))continue;
                if($company===''){$company=$line;continue;}
                if(career_germany_location($line,'JobPharm') && preg_match('/[\\p{L}]/u',$line)){$location=$line;break;}
            }
        }
        if($company==='' || !career_germany_location($location,$source))continue;
        $url=career_alert_url($source,$url);
        if(!filter_var($url,FILTER_VALIDATE_URL))continue;
        $key=$title.'|'.$company.'|'.$location;
        if(isset($seen[$key]))continue;
        $seen[$key]=true;
        $jobs[]=['source'=>'Gmail · '.$source,'title'=>$title,'company'=>$company,'location'=>$location,'url'=>$url,'description'=>''];
    }
    return $jobs;
}
/** Normalize a manually reviewed public search-index hit.
 * Requires a specific Indeed vacancy ID. Do not assume search result snippets
 * establish posting freshness; the original listing remains the source of truth.
 */
function career_indexed_indeed_job(array $item): ?array {
    if(($item['source']??null)!=='Indeed')return null;
    $url=trim((string)($item['url']??''));
    if(!preg_match('~^https://de\\.indeed\\.com/viewjob\\?jk=([a-z0-9]{8,})$~i',$url,$m))return null;
    $record=[
        'source'=>'Indeed · Search index',
        'title'=>trim((string)($item['title']??'')),
        'company'=>trim((string)($item['company']??'')),
        'location'=>trim((string)($item['location']??'')),
        'url'=>'https://de.indeed.com/viewjob?jk='.$m[1],
        'description'=>'Public search-index discovery. Confirm vacancy remains open before applying.'
    ];
    if($record['title']==='' || $record['company']==='' || !career_job_eligible($record))return null;
    return $record;
}
/** Prefer configured Gmail labels. If an alert label is absent, search only
 * the provider's sender domain; the vacancy URL is still independently checked.
 * This uses the website's existing authorized Gmail connection, never public scraping.
 */
function career_alert_search_params(string $source,?string $labelId): ?array {
    $params=['maxResults'=>500];
    if($labelId!==null){$params['labelIds']=$labelId;return $params;}
    $query=match($source){
        'StepStone'=>'from:(stepstone.de) newer_than:120d',
        'Indeed'=>'from:(indeed.com) newer_than:120d',
        'LinkedIn'=>'from:(jobalerts-noreply@linkedin.com) newer_than:90d',
        default=>null
    };
    if($query===null)return null;
    $params['q']=$query;
    return $params;
}
/** Pull individually authorized Gmail job alerts into the private database. */
function career_import_gmail_alerts(int $perLabel=6): array {
    if(setting('gmail_refresh')==='')return ['connected'=>false,'new'=>0,'examined'=>0,'duplicates'=>0,'rejected'=>0,'by_source'=>[],'errors'=>['Gmail is not connected in website settings.']];
    $db=db();
    $db->exec('CREATE TABLE IF NOT EXISTS career_imported_alerts(message_id TEXT PRIMARY KEY,source TEXT NOT NULL,processed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,added INTEGER NOT NULL DEFAULT 0)');
    $cols=$db->query('PRAGMA table_info(career_imported_alerts)')->fetchAll();
    if(!in_array('parser_version',array_column($cols,'name'),true))$db->exec('ALTER TABLE career_imported_alerts ADD COLUMN parser_version INTEGER NOT NULL DEFAULT 1');
    $labels=gmailcall('GET','labels');
    $available=[];foreach(($labels['labels']??[]) as $label)$available[strtolower((string)($label['name']??''))]=(string)$label['id'];
    $result=['connected'=>true,'new'=>0,'examined'=>0,'duplicates'=>0,'rejected'=>0,'by_source'=>[],'errors'=>[]];
    $channels=career_alert_sources();
    $channels['LinkedIn']=null; // LinkedIn job-alert emails need no extra mailbox label.
    foreach($channels as $source=>$label) {
        $labelId=$label===null?null:($available[strtolower($label)]??null);
        $params=career_alert_search_params($source,$labelId);
        if($params===null){$result['by_source'][$source]=['new'=>0,'checked'=>0,'parsed'=>0,'message'=>'No Gmail label or sender search'];continue;}
        $channel=['new'=>0,'checked'=>0,'seen'=>0,'parsed'=>0,'mode'=>$labelId!==null?'label':'sender'];
        try {
            $list=gmailcall('GET','messages?'.http_build_query($params));
            foreach(($list['messages']??[]) as $item) {
                $id=(string)($item['id']??'');
                if($id==='')continue;
                $exists=$db->prepare('SELECT 1 FROM career_imported_alerts WHERE message_id=? AND parser_version>=3');
                $exists->execute([$id]);if($exists->fetchColumn())continue;
                if($channel['checked'] >= $perLabel)break;
                $message=gmailcall('GET','messages/'.rawurlencode($id).'?format=full');
                $body=career_alert_body_decode($message['payload']??[]);
                $channel['checked']++;$added=0;
                $candidates=career_alert_candidates($source,$body);
                $channel['parsed']+=count($candidates);
                foreach($candidates as $job) {
                    if(upsertjob($job)){$added++;$result['new']++;}
                    else $result['duplicates']++;
                }
                $channel['new']+=$added;
                $db->prepare('INSERT INTO career_imported_alerts(message_id,source,added,parser_version) VALUES (?,?,?,3) ON CONFLICT(message_id) DO UPDATE SET added=career_imported_alerts.added+excluded.added,parser_version=3,processed_at=CURRENT_TIMESTAMP')->execute([$id,$source,$added]);
            }
            $channel['seen']=count($list['messages']??[]);
        }catch(Throwable $e){$result['errors'][]=$source.': '.$e->getMessage();}
        $result['examined']+=$channel['checked'];
        $result['by_source'][$source]=$channel;
    }
    setsetting('last_gmail_sync',gmdate('Y-m-d H:i:s').' UTC');
    setsetting('last_gmail_sync_info',json_encode($result,JSON_UNESCAPED_UNICODE));
    return $result;
}
function career_scan_all(): array {
    $public=crawl();
    $mail=career_import_gmail_alerts(4);
    $public['new']+=$mail['new'];
    $public['examined']+=$mail['examined'];
    $public['errors']=array_merge($public['errors'],$mail['errors']);
    $public['gmail']=$mail;
    setsetting('last_scan_info',json_encode($public,JSON_UNESCAPED_UNICODE));
    return $public;
}
