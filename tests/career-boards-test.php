<?php
declare(strict_types=1);
require __DIR__.'/../site/career/lib.php';
require __DIR__.'/../site/career/sources.php';
require __DIR__.'/../site/career/boards.php';
if(!class_exists('DOMDocument')){fwrite(STDERR,"FAIL PHP DOM not installed\n");exit(1);}
$cases=[
 ['StepStone','<article class="job-card" data-testid="job-item"><h2><a href="/stellenangebote--Sales-Director-Dresden--14673054-inline.html">Sales Director Pharma</a></h2><span data-testid="job-item-company">Medical GmbH</span><span data-testid="job-item-location">Dresden</span></article>','Sales Director Pharma','Medical GmbH','Dresden'],
 ['Indeed','<div class="job_seen_beacon"><h2 class="jobTitle"><a class="jcs-JobTitle" href="/viewjob?jk=abc456">Regionalleiter Pharma</a></h2><span class="companyName">IQVIA</span><div data-testid="text-location">München</div></div>','Regionalleiter Pharma','IQVIA','München'],
 ['LinkedIn','<div class="base-search-card"><a class="base-card__full-link" href="https://www.linkedin.com/jobs/view/4482893441/"><h3 class="base-search-card__title">Commercial Effectiveness Manager</h3></a><h4 class="base-search-card__subtitle">Biopharma AG</h4><span class="job-search-card__location">Frankfurt am Main, Germany</span></div>','Commercial Effectiveness Manager','Biopharma AG','Frankfurt am Main, Germany'],
 ['JobPharm','<article class="job-card"><h2><a href="/job?id=992859144">Head of Sales Pharma</a></h2><span class="company-name">Healthcare GmbH</span><span class="location">Berlin</span></article>','Head of Sales Pharma','Healthcare GmbH','Berlin']
];
foreach($cases as [$source,$html,$title,$company,$city]){
  $r=career_board_parse($source,'<html><body>'.$html.'</body></html>');
  if(count($r)!==1||$r[0]['source']!==$source||$r[0]['title']!==$title||$r[0]['company']!==$company||$r[0]['location']!==$city){
      fwrite(STDERR,"FAIL $source parsing ".json_encode($r,JSON_UNESCAPED_UNICODE)."\n");exit(1);
  }
}
$blocked=career_board_parse('StepStone','<html><body><h2>Sign in to view listings</h2></body></html>');
if(count($blocked)!==0){fwrite(STDERR,"FAIL empty document must not create a job\n");exit(1);}
foreach(['StepStone','Indeed','LinkedIn','JobPharm'] as $board){
 $url=career_board_search_url($board,'Sales Director Pharma');
 if(!filter_var($url,FILTER_VALIDATE_URL)){fwrite(STDERR,"FAIL $board query URL\n");exit(1);}
}
$preferred=career_board_scan_terms(['sales director pharma','regionalleiter pharma','head of sales pharma','commercial excellence pharma'],2,3);
if($preferred!==['head of sales pharma','commercial excellence pharma','sales director pharma']){
    fwrite(STDERR,"FAIL circular priority query rotation\n");exit(1);
}
$pool=career_board_terms();
foreach(['sales director pharma','regionalleiter pharma','head of sales pharma','commercial excellence pharma'] as $term){
    if(!in_array($term,$pool,true)){fwrite(STDERR,"FAIL missing priority query: $term\n");exit(1);}
}
echo "PASS: four public job-board formats, search URLs, honest empty-result handling\n";
