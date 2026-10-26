<?php
declare(strict_types=1);
require __DIR__.'/../site/career/lib.php';
$cases=[
 ['Head of Sales','Berlin',true],
 ['Sales Director Pharma','München',true],
 ['National Sales Manager - Pharma','Mannheim',true],
 ['Commercial Effectiveness Manager','Frankfurt am Main',true],
 ['Regionalleiter Pharma','bundesweit',true],
 ['Key Account Director','Köln',true],
 ['Sales Representative Pharma','Berlin',false],
 ['Senior Sales Representative','Stuttgart',false],
 ['Sales Rep','Hamburg',false],
 ['Account Executive','Berlin',false],
 ['Senior Account Executive','Frankfurt am Main',false],
 ['Account-Executive (m/w/d)','Düsseldorf',false],
 ['Junior Sales Director','Bonn',false],
 ['Junior Regionalleiter','Dresden',false],
 ['Pharmaberater','München',false],
 ['Außendienstmitarbeiter','München',false],
 ['Sales Director','London',false],
 ['Head of Sales','Barcelona',false],
 ['Head of Sales','Paris',false],
 ['Head of Sales','Dublin',false],
 ['Head of Sales','Prague',false],
 ['Head of Sales','Vienna',false],
 ['Sales Director','Berlin / London',false],
 ['Sales Director','München, Österreich',false],
 ['Sales Director','Berlin / Switzerland',false],
 ['Sales Director','Worldwide',false],
 ['Sales Director','Remote',false],
 ['Sales Director','Unknown City',false],
 ['Sales Manager Pharma','Hof',false],
 ['Regionalleiter','Neustadt an der Weinstraße',false],
 ['Sales Director','',false],
 ['Sales Director','München, Deutschland',true],
 ['Sales Director','Berlin, Germany',true],
];
foreach($cases as $i=>$test){
 [$title,$location,$expected]=$test;
 foreach(['Arbeitnow','Gmail · StepStone','Manual'] as $source){
   $actual=career_job_eligible(['title'=>$title,'location'=>$location,'source'=>$source,'description'=>'']);
   if($actual!==$expected){
      fwrite(STDERR,"FAIL test $i: $title @ $location from $source => ".($actual?'accepted':'rejected')."\n");
      exit(1);
   }
 }
}
$germanAgency=['Hof','Neustadt an der Weinstraße','Bad Dürkheim'];
foreach($germanAgency as $location){
    if(!career_job_eligible(['title'=>'Regionalleiter Pharma','location'=>$location,'source'=>'Arbeitsagentur'])) {
        fwrite(STDERR,"FAIL agency regional city: $location\\n");exit(1);
    }
}
$reviewCases=[
 ['Regionalleiter Pharma','Hof',true],
 ['Sales Manager Pharma','Neustadt an der Weinstraße',true],
 ['Sales Director','Remote',true],
 ['Head of Sales','Barcelona',false],
 ['Regionalleiter','Berlin / London',false],
 ['Sales Representative','Berlin',false],
 ['Account Executive','Berlin',false],
 ['Junior Sales Manager','Berlin',false],
];
foreach($reviewCases as $test) {
  [$title,$location,$expected]=$test;
  $actual=career_existing_visible(['title'=>$title,'location'=>$location,'description'=>'']);
  if($actual!==$expected){fwrite(STDERR,"FAIL existing review $title @ $location\\n");exit(1);}
}
echo "PASS: ".(count($cases)*3+count($germanAgency)+count($reviewCases))." role, Germany-location and archive recovery checks\\n";
