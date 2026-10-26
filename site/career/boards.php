<?php
declare(strict_types=1);
/**
 * Public job-board discovery. Uses public result pages only, with conservative
 * timeouts and normal HTTP requests; deliberately does not circumvent sign-in,
 * CAPTCHAs, bot challenges, or rate limits.
 */
function career_board_http(string $url): string {
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL unavailable');
    $c=curl_init($url);
    curl_setopt_array($c,[
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>7,
        CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>2,
        CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; FrederickCareerPublicJobs/1.0; +https://fredericksamuel.com)',
        CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml','Accept-Language: de-DE,de;q=0.9,en;q=0.7'],
        CURLOPT_ENCODING=>''
    ]);
    $html=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
    $errno=(int)curl_errno($c);$curlError=(string)curl_error($c);
    // One ordinary HTTP/1.1 retry for StepStone's intermittent HTTP/2 stream reset.
    // No CAPTCHA bypass, rotating proxy, synthetic identities or repeated retries.
    if(!is_string($html) && $errno===92 && str_contains($url,'stepstone.de')) {
        curl_setopt($c,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        curl_setopt($c,CURLOPT_TIMEOUT,10);
        $html=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
        $errno=(int)curl_errno($c);$curlError=(string)curl_error($c);
    }
    curl_close($c);
    if(!is_string($html))throw new RuntimeException('cURL '.$errno.': '.clean($curlError,180));
    if($code<200||$code>=300)throw new RuntimeException('HTTP '.$code);
    if(strlen($html)<150)throw new RuntimeException('Empty public search page');
    if(preg_match('~captcha|verify you are human|unusual traffic|access denied|bot protection~i',substr($html,0,10000)))
        throw new RuntimeException('Public search access restricted');
    return $html;
}
function career_board_terms(): array {
    // High-intent pharma/commercial leadership searches run first; saved owner
    // keywords remain part of the rotating query pool.
    $focus=['sales director pharma','regionalleiter pharma','head of sales pharma','commercial excellence pharma','vertriebsleiter pharma','head of field force','commercial effectiveness'];
    $all=array_values(array_unique(array_merge($focus,career_keyword_terms('career_include_keywords'))));
    return array_values(array_filter($all,static fn($s)=>mb_strlen($s)<80));
}
function career_board_scan_terms(array $terms,int $offset,int $count): array {
    if(!$terms)return [];
    $selected=[];$total=count($terms);
    for($i=0;$i<min($count,$total);$i++)$selected[]=$terms[($offset+$i)%$total];
    return $selected;
}
function career_board_search_url(string $board,string $term): string {
    return match($board){
        'StepStone'=>'https://www.stepstone.de/jobs/'.rawurlencode(trim(preg_replace('/[^a-z0-9]+/','-',strtolower(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$term)?:$term)),'-')).'/in-deutschland',
        'Indeed'=>'https://de.indeed.com/jobs?'.http_build_query(['q'=>$term,'l'=>'Deutschland']),
        'LinkedIn'=>'https://www.linkedin.com/jobs-guest/jobs/api/seeMoreJobPostings/search?'.http_build_query(['keywords'=>$term,'location'=>'Germany','start'=>0]),
        'JobPharm'=>'https://www.jobpharm.de/search?'.http_build_query(['r'=>60,'q'=>$term]),
        default=>throw new InvalidArgumentException('Unknown board')
    };
}
function career_board_xpath_text(DOMXPath $xp,DOMNode $root,string $query): string {
    $items=$xp->query($query,$root);
    if(!$items)return '';
    foreach($items as $item) {
        $value=trim(preg_replace('/\s+/u',' ',$item->textContent??''));
        if($value!=='')return $value;
    }
    return '';
}
function career_board_card(DOMNode $anchor,string $board): DOMNode {
    $n=$anchor;$best=$anchor;
    for($depth=0;$depth<12 && $n!==null;$depth++,$n=$n->parentNode) {
        if(!$n instanceof DOMElement)continue;
        $class=strtolower($n->getAttribute('class').' '.$n->getAttribute('data-testid').' '.$n->getAttribute('data-at').' '.$n->getAttribute('data-qa'));
        if(preg_match('/job[-_ ]?(?:card|item|listing|seen|result)|base-search-card|job-search-card/i',$class))return $n;
        if(in_array(strtolower($n->tagName),['article','li'],true))return $n;
        $length=mb_strlen(trim(preg_replace('/\s+/u',' ',$n->textContent??'')));
        if($depth>=2 && $length>=75 && $length<1600)$best=$n;
        if($length>3500)break;
    }
    return $best;
}
function career_board_parse(string $board,string $html): array {
    if(!class_exists('DOMDocument'))throw new RuntimeException('PHP DOM extension unavailable');
    $doc=new DOMDocument();
    $old=libxml_use_internal_errors(true);
    try{$doc->loadHTML('<?xml encoding="utf-8"?>'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
    finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    $xp=new DOMXPath($doc);
    // Modern StepStone pages embed extensive CSS inside job-link nodes.
    // Ignore style/script text before finding cards, titles and locations.
    $noise=$xp->query('//style|//script|//noscript');
    if($noise){foreach(iterator_to_array($noise) as $node)if($node->parentNode)$node->parentNode->removeChild($node);}
    $query=match($board) {
        'StepStone'=>'//a[contains(@href,"stellenangebote--") or contains(@data-testid,"job-item-title") or contains(@data-at,"job-item-title")]',
        'Indeed'=>'//a[contains(@class,"jcs-JobTitle") or contains(@href,"jk=") or contains(@href,"viewjob")]',
        'LinkedIn'=>'//a[contains(@class,"base-card__full-link") or contains(@href,"/jobs/view/")]',
        'JobPharm'=>'//a[contains(@href,"/job?") or contains(@href,"/job/")]',
        default=>'//a[@href]'
    };
    $anchors=$xp->query($query);$jobs=[];$seen=[];
    if(!$anchors)return [];
    foreach($anchors as $anchor) {
        if(!$anchor instanceof DOMElement)continue;
        $href=trim($anchor->getAttribute('href'));
        if($href==='')continue;
        if(str_starts_with($href,'/'))$href='https://'.match($board){
            'StepStone'=>'www.stepstone.de','Indeed'=>'de.indeed.com',
            'LinkedIn'=>'www.linkedin.com','JobPharm'=>'www.jobpharm.de',default=>''}.$href;
        if(!filter_var($href,FILTER_VALIDATE_URL))continue;
        $host=strtolower((string)parse_url($href,PHP_URL_HOST));
        $safe=match($board){
            'StepStone'=>in_array($host,['stepstone.de','www.stepstone.de'],true),
            'Indeed'=>in_array($host,['de.indeed.com','indeed.com','www.indeed.com'],true),
            'LinkedIn'=>in_array($host,['www.linkedin.com','linkedin.com','de.linkedin.com'],true),
            'JobPharm'=>in_array($host,['www.jobpharm.de','jobpharm.de'],true),default=>false
        };
        if(!$safe)continue;
        $card=career_board_card($anchor,$board);
        $title=career_board_xpath_text($xp,$card,'.//h2|.//h3[contains(@class,"title")]|.//span[contains(@class,"jobTitle")]|.//*[@data-testid="job-item-title"]');
        if($title==='')$title=trim(preg_replace('/\s+/u',' ',$anchor->textContent));
        $company=career_board_xpath_text($xp,$card,'.//*[contains(@data-testid,"company") or contains(@data-at,"company") or contains(@class,"company-name") or contains(@class,"companyName") or contains(@class,"base-search-card__subtitle") or contains(@class,"job-search-card__subtitle")]');
        $location=career_board_xpath_text($xp,$card,'.//*[contains(@data-testid,"location") or contains(@data-at,"location") or contains(@class,"job-item-location") or contains(@class,"companyLocation") or contains(@class,"text-location") or contains(@class,"job-search-card__location") or contains(@class,"location") or contains(@class,"city")]');
        // Prefer structured, clearly attributable vacancies; never invent company/city.
        if($company==='' || $location==='')continue;
        $job=['source'=>$board,'title'=>$title,'company'=>$company,'location'=>$location,'url'=>career_alert_url($board,$href),'description'=>''];
        if(!career_job_eligible($job))continue;
        $key=career_norm($company).'|'.career_norm($title).'|'.career_norm($location);
        if(isset($seen[$key]))continue;$seen[$key]=true;
        $jobs[]=$job;
        if(count($jobs)>=70)break;
    }
    return $jobs;
}
function career_public_board_scan(int $priorityDepth=1): array {
    // The three largest discovery sources are first-class; specialist
    // JobPharm remains complementary rather than consuming their query budget.
    $sources=['StepStone','Indeed','LinkedIn','JobPharm'];
    $terms=career_board_terms();$result=['new'=>0,'examined'=>0,'sources'=>[],'errors'=>[]];
    if(!$terms)return $result;
    $offset=((int)setting('career_board_cursor',(string)(((int)gmdate('z')) % count($terms)))) % count($terms);
    if($offset<0)$offset=0;
    $priorityDepth=max(1,min(4,$priorityDepth));
    foreach($sources as $i=>$source) {
        $depth=in_array($source,['StepStone','Indeed','LinkedIn'],true)?$priorityDepth:1;
        $queries=career_board_scan_terms($terms,$offset+$i,$depth);
        $report=['query'=>implode(', ',$queries),'queries'=>[],'new'=>0,'found'=>0,'status'=>'','searched_url'=>career_board_search_url($source,$queries[0])];
        $successful=0;$restricted=0;
        foreach($queries as $word) {
            $url=career_board_search_url($source,$word);
            $check=['query'=>$word,'url'=>$url,'found'=>0,'new'=>0,'status'=>''];
            try {
                $html=career_board_http($url);
                $vacancies=career_board_parse($source,$html);
                $check['found']=count($vacancies);$result['examined']+=count($vacancies);
                foreach($vacancies as $vacancy) {
                    $n=upsertjob($vacancy);
                    $result['new']+=$n;$report['new']+=$n;$check['new']+=$n;
                }
                $report['found']+=count($vacancies);
                $check['status']=$vacancies?'ok':'no_structured_vacancies';
                if($vacancies)$successful++;
            } catch(Throwable $e) {
                $check['status']='unavailable';$check['error']=$e->getMessage();$restricted++;
                $result['errors'][]=$source.' ('.$word.'): '.$e->getMessage();
            }
            $report['queries'][]=$check;
            // Respect providers that explicitly reject or rate-limit automated access.
            if($check['status']==='unavailable' &&
               preg_match('/HTTP (403|429)\\b/',(string)($check['error']??'')))break;
        }
        $report['status']=$successful>0?'ok':($restricted===count($report['queries'])?'unavailable':'no_structured_vacancies');
        if($restricted && !$successful)$report['error']=$restricted.' of '.count($queries).' queries unavailable';
        $result['sources'][$source]=$report;
    }
    // Advance by the number of terms processed during the unattended scan.
    setsetting('career_board_cursor',(string)(($offset+$priorityDepth)%count($terms)));
    setsetting('last_board_scan',gmdate('Y-m-d H:i:s').' UTC');
    setsetting('last_board_scan_info',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
    return $result;
}
