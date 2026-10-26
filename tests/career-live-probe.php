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
        echo $source.': HTTP OK, bytes='.strlen($html).', eligible='.count($jobs).'\n';
        if($jobs)echo '  example: '.mb_substr($jobs[0]['title'],0,100).'\n';
        else echo '  no eligible structured records; source HTML or page selectors need inspection\n';
    }catch(Throwable $e){
        echo $source.': UNAVAILABLE '.$e->getMessage().'\n';
    }
}
