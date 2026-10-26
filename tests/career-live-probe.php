<?php
declare(strict_types=1);
require __DIR__.'/../site/career/lib.php';
require __DIR__.'/../site/career/sources.php';
require __DIR__.'/../site/career/boards.php';
$terms=['StepStone'=>'regionalleiter pharma','Indeed'=>'sales director pharma','LinkedIn'=>'commercial excellence','JobPharm'=>'vertriebsleiter'];
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
