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
    $html=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if(!is_string($html)||$code<200||$code>=300)throw new RuntimeException('HTTP '.$code);
    if(strlen($html)<150)throw new RuntimeException('Empty public search page');
    if(preg_match('~captcha|verify you are human|unusual traffic|access denied|bot protection~i',substr($html,0,10000)))
        throw new RuntimeException('Public search access restricted');
    return $html;
}
function career_board_terms(): array {
    $a=career_keyword_terms('career_include_keywords');
    // Include saved owner terms, not merely hard-coded queries.
    $all=array_values(array_unique(array_merge($a,['sales director pharma','regionalleiter pharma','commercial effectiveness','head of sales'])));
    return array_values(array_filter($all,static fn($s)=>mb_strlen($s)<80));
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
    for($depth=0;$depth<8 && $n!==null;$depth++,$n=$n->parentNode) {
        if(!$n instanceof DOMElement)continue;
        $class=strtolower($n->getAttribute('class').' '.$n->getAttribute('data-testid'));
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
    $query=match($board) {
        'StepStone'=>'//a[contains(@href,"stellenangebote--")]',
        'Indeed'=>'//a[contains(@class,"jcs-JobTitle") or contains(@href,"jk=") or contains(@href,"viewjob")]',
        'LinkedIn'=>'//a[contains(@class,"base-card__full-link") or contains(@href,"/jobs/view/")]',
        'JobPharm'=>'//a[contains(@href,"/job?id=")]',
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
        $title=career_board_xpath_text($xp,$card,'.//h2|.//h3[contains(@class,"title")]|.//span[contains(@class,"jobTitle")]');
        if($title==='')$title=trim(preg_replace('/\s+/u',' ',$anchor->textContent));
        $company=career_board_xpath_text($xp,$card,'.//*[contains(@data-testid,"company") or contains(@class,"company-name") or contains(@class,"companyName") or contains(@class,"base-search-card__subtitle") or contains(@class,"job-search-card__subtitle")]');
        $location=career_board_xpath_text($xp,$card,'.//*[contains(@data-testid,"location") or contains(@class,"job-item-location") or contains(@class,"companyLocation") or contains(@class,"text-location") or contains(@class,"job-search-card__location") or contains(@class,"location") or contains(@class,"city")]');
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
function career_public_board_scan(): array {
    $sources=['StepStone','Indeed','LinkedIn','JobPharm'];
    $terms=career_board_terms();$result=['new'=>0,'examined'=>0,'sources'=>[],'errors'=>[]];
    if(!$terms)return $result;
    // Rotate through all saved terms over successive days, avoiding excessive requests.
    $offset=((int)setting('career_board_cursor',(string)(((int)gmdate('z')) % count($terms)))) % count($terms);
    if($offset<0)$offset=0;
    foreach($sources as $i=>$source) {
        $word=$terms[($offset+$i)%count($terms)];
        $report=['query'=>$word,'new'=>0,'found'=>0,'status'=>''];
        try {
            $html=career_board_http(career_board_search_url($source,$word));
            $vacancies=career_board_parse($source,$html);
            $report['found']=count($vacancies);$result['examined']+=count($vacancies);
            foreach($vacancies as $vacancy){$n=upsertjob($vacancy);$result['new']+=$n;$report['new']+=$n;}
            $report['status']=$vacancies?'ok':'no_structured_vacancies';
        }catch(Throwable $e){
            $report['status']='unavailable';$report['error']=$e->getMessage();
            $result['errors'][]=$source.': '.$e->getMessage();
        }
        $result['sources'][$source]=$report;
    }
    // Each refresh advances the per-board query to the next saved term.
    setsetting('career_board_cursor',(string)(($offset+1)%count($terms)));
    setsetting('last_board_scan',gmdate('Y-m-d H:i:s').' UTC');
    setsetting('last_board_scan_info',json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
    return $result;
}
