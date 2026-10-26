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
 ['Sales Director','',false],
 ['Sales Director','München, Deutschland',true],
 ['Sales Director','Berlin, Germany',true],
];
foreach($cases as $i=>$test){
 [$title,$location,$expected]=$test;
 foreach(['Arbeitnow','Arbeitsagentur','Gmail · StepStone','Manual'] as $source){
   $actual=career_job_eligible(['title'=>$title,'location'=>$location,'source'=>$source,'description'=>'']);
   if($actual!==$expected){
      fwrite(STDERR,"FAIL test $i: $title @ $location from $source => ".($actual?'accepted':'rejected')."\n");
      exit(1);
   }
 }
}
echo "PASS: ".(count($cases)*4)." role/location eligibility checks across crawler, Gmail and manual sources\n";
