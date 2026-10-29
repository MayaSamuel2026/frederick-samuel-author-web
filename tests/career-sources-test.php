<?php
declare(strict_types=1);
require __DIR__.'/../site/career/lib.php';
require __DIR__.'/../site/career/sources.php';
$cases=[
 ['Indeed',"[Regionalleiter Pharma](https://de.indeed.com/rc/clk/dl?jk=abc123&from=ja)\n\nIQVIA\n\nMünchen\n\nVor 1 Tag\n",'https://de.indeed.com/viewjob?jk=abc123','IQVIA'],
 ['JobPharm',"[Head of Sales Pharma](https://www.jobpharm.de/job?id=12345&utm_source=jobmail)\n\nGesundheit GmbH\n|\nBensheim bei Mannheim\n",'https://www.jobpharm.de/job?id=12345','Gesundheit GmbH'],
 ['StepStone',"[Gesundheit GmbH](https://click.stepstone.de/f/a/company)\n\n[Regionalleiter West (m/w/d)](https://click.stepstone.de/f/a/individualjob)\n\nGesundheit GmbH\n\nBerlin\n",'https://click.stepstone.de/f/a/individualjob','Gesundheit GmbH']
];
foreach($cases as [$source,$text,$url,$company]) {
    $parsed=career_alert_candidates($source,$text);
    if(count($parsed)!==1 || $parsed[0]['url']!==$url || $parsed[0]['company']!==$company || ($source==='JobPharm' && $parsed[0]['location']!=='Bensheim bei Mannheim')) {
        fwrite(STDERR,"FAILED $source mail extraction: ".json_encode($parsed,JSON_UNESCAPED_UNICODE)."\n");exit(1);
    }
}
$html='<html><a href="https://www.jobpharm.de/job?id=87654">Sales Director Pharma</a></html>';
if(!str_contains(career_alert_text($html),'[Sales Director Pharma](https://www.jobpharm.de/job?id=87654)')){
    fwrite(STDERR,"FAILED HTML link extraction\n");exit(1);
}
// Mirror the currently received Indeed alert: two distinct individual vacancy IDs.
$indeedMail="2 regionalleiter vertrieb pharma Jobs\n"
    ."[Regionalleiter Dermatology (all genders)](https://de.indeed.com/rc/clk/dl?jk=affd2e88e7f3ce0e&from=ja)\n\n"
    ."AbbVie\n\nWiesbaden\n\nEinfach bewerben\n\n"
    ."[Regionalleiter m/w/d](https://de.indeed.com/rc/clk/dl?jk=7e4a5af6bf8601d4&from=ja)\n\n"
    ."IQVIA\n\nMannheim\n\nVor 1 Tag\n\n"
    ."[Regionalleiter gesucht](https://de.indeed.com/jobs?q=regionalleiter)\n\nSearch results\n";
$vacancies=career_alert_candidates('Indeed',$indeedMail);
if(count($vacancies)!==2 || $vacancies[0]['company']!=='AbbVie' || $vacancies[1]['company']!=='IQVIA'
    || $vacancies[0]['url']!=='https://de.indeed.com/viewjob?jk=affd2e88e7f3ce0e'
    || $vacancies[1]['location']!=='Mannheim'){
    fwrite(STDERR,"FAILED real-format Indeed multi-vacancy extraction: ".json_encode($vacancies)."\n");exit(1);
}
$stepstoneParams=career_alert_search_params('StepStone',null);
$indeedParams=career_alert_search_params('Indeed',null);
$labelParams=career_alert_search_params('Indeed','Label_123');
if(!str_contains($stepstoneParams['q']??'','stepstone.de')
    || !str_contains($indeedParams['q']??'','indeed.com')
    || ($labelParams['labelIds']??'')!=='Label_123'
    || career_alert_search_params('Jobware',null)!==null){
    fwrite(STDERR,"FAILED source alert label/sender fallback\n");exit(1);
}
echo "PASS: Gmail alert parsers extract application links and associated employer/location\n";
