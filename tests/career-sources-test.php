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
// Independent, manually reviewed search-index route for Indeed.
// Ensure invalid hosts, job identifiers, and non-German bases never import.
$indexed=[
    'source'=>'Indeed','title'=>'Head of Sales Germany (m/w/d)',
    'company'=>'Merz Therapeutics','location'=>'Frankfurt am Main',
    'url'=>'https://de.indeed.com/viewjob?jk=4f804ddccab860e4'
];
$accepted=career_indexed_indeed_job($indexed);
if(!$accepted || $accepted['source']!=='Indeed · Search index' || $accepted['company']!=='Merz Therapeutics') {
    fwrite(STDERR,"FAILED Indeed index import validation\n");exit(1);
}
foreach([
    array_merge($indexed,['url'=>'https://example.com/viewjob?jk=4f804ddccab860e4']),
    array_merge($indexed,['url'=>'https://de.indeed.com/jobs?q=sales']),
    array_merge($indexed,['company'=>'']),
    array_merge($indexed,['location'=>'London']),
    array_merge($indexed,['source'=>'Unknown'])
] as $invalid) {
    if(career_indexed_indeed_job($invalid)!==null){
        fwrite(STDERR,"FAILED unsafe Indeed index import rejection\n");exit(1);
    }
}
// Real LinkedIn hiring digests encode job, employer and location inside the same link.
$linkedinMail="[LinkedIn](https://www.linkedin.com/comm/feed/)\n"
    ."[Abbott](https://www.linkedin.com/comm/jobs/view/4420290693/?trk=company)\n"
    ."[National Sales Manager Germany Cardiometabolic (m/f/d)\nAbbott · Wiesbaden\nActively recruiting](https://www.linkedin.com/comm/jobs/view/4420290693/?trk=alert)\n"
    ."[View job](https://www.linkedin.com/comm/jobs/view/4420290693/?trk=view)\n"
    ."[Molecular Devices](https://www.linkedin.com/comm/jobs/view/4396747191/?trk=company)\n"
    ."[Senior Director, European Sales (m/f/d)\nMolecular Devices · Dresden (Remote)](https://www.linkedin.com/comm/jobs/view/4396747191/?trk=mail)\n"
    ."[Head of Sales Germany (m/w/d)\nAirbus\nBerlin (Hybrid)](https://www.linkedin.com/comm/jobs/view/4420154552/?trackingId=test)\n"
    ."[Regionalleiter Sales\nExample Ltd · London](https://www.linkedin.com/comm/jobs/view/4420154553/)\n"
    ."[Sales Director\nFake · Berlin](https://www.linkedin.com/jobs/search/?keywords=sales)\n";
$linkedinJobs=career_alert_candidates('LinkedIn',$linkedinMail);
if(count($linkedinJobs)!==3
    || $linkedinJobs[0]['company']!=='Abbott'
    || $linkedinJobs[0]['location']!=='Wiesbaden'
    || $linkedinJobs[0]['url']!=='https://www.linkedin.com/jobs/view/4420290693/'
    || $linkedinJobs[1]['company']!=='Molecular Devices'
    || $linkedinJobs[1]['location']!=='Dresden (Remote)'
    || $linkedinJobs[2]['company']!=='Airbus'
    || $linkedinJobs[2]['title']!=='Head of Sales Germany (m/w/d)'){
    fwrite(STDERR,"FAILED LinkedIn notification job-card extraction: ".json_encode($linkedinJobs,JSON_UNESCAPED_UNICODE)."\n");exit(1);
}
$linkedinQuery=career_alert_search_params('LinkedIn',null);
if(!str_contains($linkedinQuery['q']??'','from:(linkedin.com)')||!str_contains($linkedinQuery['q']??'','newer_than:45d')){
    fwrite(STDERR,"FAILED LinkedIn sender search coverage/freshness guard\n");exit(1);
}
if(career_alert_link_ok('LinkedIn','https://www.linkedin.com/jobs/search/?keywords=sales')
    ||career_alert_link_ok('LinkedIn','https://linkedin.example.com/jobs/view/4420290693/')
    ||career_linkedin_label_parts("Regionalleiter\nExample Ltd · London")!==null){
    fwrite(STDERR,"FAILED LinkedIn invalid/foreign link guard\n");exit(1);
}
echo "PASS: Gmail alert parsers extract application links and associated employer/location\n";
