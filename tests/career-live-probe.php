<?php
declare(strict_types=1);
require __DIR__.'/../site/career/lib.php';
require __DIR__.'/../site/career/sources.php';
require __DIR__.'/../site/career/boards.php';
$terms=['StepStone'=>'sales director pharma','Indeed'=>'sales director pharma','LinkedIn'=>'commercial excellence','JobPharm'=>'vertriebsleiter'];
foreach($terms as $source=>$term){
    $url=career_board_search_url($source,$term);
    try{
        $html=career_board_http($url);
        $jobs=career_board_parse($source,$html);
        echo $source.': HTTP OK, bytes='.strlen($html).', eligible='.count($jobs) . PHP_EOL;
        if($source==='StepStone' && !$jobs) {
            $doc=new DOMDocument();$prev=libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8"?>'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
            libxml_clear_errors();libxml_use_internal_errors($prev);$xp=new DOMXPath($doc);
            $a=$xp->query('//a[contains(@href,"stellenangebote--")]');
            echo '  diagnostic: links='.($a?$a->length:0)
                .', text_mentions='.substr_count($html,'stellenangebote--')
                .', json_jobposting='.substr_count($html,'JobPosting')
                .', testids='.substr_count($html,'job-item') . PHP_EOL;
            if($a && $a->length){
                $anchor=$a->item(0);$card=career_board_card($anchor,'StepStone');
                $parents=[];$node=$anchor;
                for($level=0;$level<9 && $node instanceof DOMElement;$level++,$node=$node->parentNode){
                    $parents[]=$node->tagName.'['.mb_substr($node->getAttribute('class'),0,45).']'
                        .'['.mb_substr($node->getAttribute('data-testid'),0,38).']'
                        .'['.mb_substr($node->getAttribute('data-at'),0,38).']';
                }
                echo '  ancestor_chain='.implode(' > ',$parents).PHP_EOL;
                $childNodes=$xp->query('.//*[@data-at or @data-testid or @data-qa]', $card);
                $attributes=[];
                if($childNodes){
                    foreach($childNodes as $child){
                        if(!$child instanceof DOMElement)continue;
                        $attributes[]=$child->tagName.' data-at='.mb_substr($child->getAttribute('data-at'),0,45)
                          .' testid='.mb_substr($child->getAttribute('data-testid'),0,45)
                          .' text='.mb_substr(trim(preg_replace('/\\s+/u',' ',$child->textContent)),0,65);
                        if(count($attributes)>=8)break;
                    }
                }
                echo '  card_elements='.implode(' | ',$attributes).PHP_EOL;
                echo '  first_anchor='.mb_substr(trim($anchor->textContent),0,90)
                     .' ; ancestor='.($card instanceof DOMElement?$card->tagName.' '.mb_substr($card->getAttribute('class'),0,110):'n/a')
                     .' ; company='.career_board_xpath_text($xp,$card,'.//*[contains(@data-testid,"company") or contains(@class,"company-name")]')
                     .' ; location='.career_board_xpath_text($xp,$card,'.//*[contains(@data-testid,"location") or contains(@class,"location")]') . PHP_EOL;
            }
        }
        if($jobs)echo '  example: '.mb_substr($jobs[0]['title'],0,100) . PHP_EOL;
        else echo '  no eligible structured records; source HTML or page selectors need inspection' . PHP_EOL;
    }catch(Throwable $e){
        echo $source.': UNAVAILABLE '.$e->getMessage() . PHP_EOL;
    }
}


require __DIR__.'/../site/career/letter_workflow.php';
$bbraun='https://jobs.bbraun.com/job/Melsungen-Business-Development-Manager-%28wmd%29/14588-de_DE/';
try {
    $posting=career_read_public_advert($bbraun);
    $matches=career_advert_matches(['company'=>'B. Braun Gruppe','title'=>'Business Development Manager (f/m/d)'],$posting,$bbraun);
    echo 'B. Braun official requisition 14588: '.($matches?'VERIFIED_FULL_ADVERT':'UNVERIFIED_CONTENT')
         .', chars='.mb_strlen($posting).PHP_EOL;
} catch(Throwable $e) {
    echo 'B. Braun official requisition 14588: DIRECT_FETCH_UNAVAILABLE '.$e->getMessage().PHP_EOL;
    echo '  verified employer-summary fallback remains available'.PHP_EOL;
    try {
        [$host,$ip]=career_safe_advert_url($bbraun);
        $c=curl_init($bbraun);
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_RESOLVE=>["{$host}:443:{$ip}"],CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; FrederickCareerPrivate/1.0)',CURLOPT_TIMEOUT=>12]);
        $html=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$type=curl_getinfo($c,CURLINFO_CONTENT_TYPE);curl_close($c);
        $html=is_string($html)?$html:'';
        echo '  employer_raw_http='.$code.' mime='.$type.' raw_bytes='.strlen($html).PHP_EOL;
        echo '  content_markers='.json_encode([
            'JobPosting'=>stripos($html,'JobPosting')!==false,
            'jobdescription'=>stripos($html,'jobdescription')!==false,
            'business development'=>stripos($html,'Business Development')!==false,
            'Melsungen'=>stripos($html,'Melsungen')!==false,
            'iframe'=>stripos($html,'<iframe')!==false,
            'access denied'=>stripos($html,'Access Denied')!==false,
        ]).PHP_EOL;
        echo '  body_sample='.mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags($html))),0,650).PHP_EOL;
        if(preg_match('/.{0,140}jobdescription.{0,250}/si',$html,$m))
           echo '  marker_sample='.mb_substr(preg_replace('/\s+/u',' ',$m[0]),0,450).PHP_EOL;
    }catch(Throwable $err){echo '  diagnostic failed: '.$err->getMessage().PHP_EOL;}
}
