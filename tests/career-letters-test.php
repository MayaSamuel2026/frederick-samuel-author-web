<?php
declare(strict_types=1);
require dirname(__DIR__).'/site/career/lib.php';
require dirname(__DIR__).'/site/career/letter_workflow.php';
$private=sys_get_temp_dir().'/fs-career-letters-'.bin2hex(random_bytes(5));
$config=['password_hash'=>'test'];
function ok(bool $condition,string $message): void {
    if(!$condition)throw new RuntimeException('FAIL: '.$message);
    echo "PASS: {$message}\n";
}
try {
    $db=db();
    $desc='This German healthcare sales leadership role requires leading and coaching sales teams, '
       .'commercial effectiveness, territory planning, KPI dashboards and forecasting. '
       .'We seek pharmaceutical market knowledge and proven business development, '
       .'go-to-market execution, key account management, partnerships and international experience. '
       .'Successful candidates will work with hospitals, clinicians and pharmacies.';
    $db->prepare('INSERT INTO jobs(source,company,title,location,url,description) VALUES(?,?,?,?,?,?)')
       ->execute(['Synthetic','Example Healthcare','Head of Sales','Germany','https://example.com/jobs/head-of-sales',$desc]);
    $id=(int)$db->lastInsertId();$job=onejob($id);
    $analysis=career_match_cv($job,'de');
    ok(count($analysis['matched'])>=4,'CV comparison matches multiple stated role requirements');
    ok($analysis['description_available'],'Description has enough content');
    $de=career_composed_letter($job,'de','natural');
    $en=career_composed_letter($job,'en','concise');
    ok(str_contains($de,'Sehr geehrtes')&&str_contains($de,'Vifor Pharma'),'German letter uses verified CV evidence');
    ok(str_contains($en,'Dear Hiring Team')&&str_contains($en,'Example Healthcare'),'English letter is tailored to vacancy');
    $saved=career_create_pdf($id,$de,'de');
    $record=career_preparation($id);
    ok(is_file($record['pdf_path'])&&str_starts_with(file_get_contents($record['pdf_path']),'%PDF-'),'PDF created and saved outside public web directory');
    ok($record['pdf_filename']===$saved['filename'],'Vacancy references its saved PDF');
    career_create_pdf($id,$en,'en');
    $new=career_preparation($id);
    ok(is_file($new['pdf_path'])&&!is_file($record['pdf_path']),'Regeneration replaces prior PDF safely');
    try{career_composed_letter(['description'=>'','title'=>'Head of Sales','company'=>'Example'],'de','natural');throw new RuntimeException('Expected missing-description failure');}
    catch(RuntimeException $e){ok(str_contains($e->getMessage(),'Stellenbeschreibung'),'No cover letter generated without genuine advert details');}
    $braun=[
        'company'=>'B. Braun Gruppe','title'=>'Business Development Manager (f/m/d)',
        'location'=>'Melsungen','url'=>'https://de.indeed.com/viewjob?jk=demo','description'=>'Job alert received'
    ];
    $official=career_known_official_adverts($braun);
    ok(count($official)===1 && str_contains($official[0],'14588'),'B. Braun board listing resolves to exact employer requisition 14588');
    $summary=career_bbraun_14588_summary($braun);
    ok(is_array($summary)&&mb_strlen($summary['description'])>180&&str_contains($summary['description'],'Business Cases'),'Verified employer summary is available if official fetch is blocked');
    ok(career_candidate_company_match($braun,$summary['description'])
       && career_candidate_title_match($braun,$summary['description']),'Employer-backed description matches original employer and title');
    $other=$braun;$other['location']='Tuttlingen';
    ok(career_known_official_adverts($other)===[],'Different B. Braun role is not silently assigned to Melsungen vacancy');
    $other['location']='Melsungen';$other['title']='Business Development Manager - Robotic Surgery';
    ok(career_known_official_adverts($other)===[],'Robotic Surgery requisition is not confused with Health Care Services');
    $stored=career_preparation($id);
    ok(array_key_exists('source_url',$stored),'Source URL is persisted in preparation record');
    echo "Career cover-letter workflow: PASS\n";
} finally {
    if(is_dir($private)){
        foreach(['letters'] as $sub)if(is_dir($private.'/'.$sub)) {
            foreach(glob($private.'/'.$sub.'/*')?:[] as $file)@unlink($file);
            @rmdir($private.'/'.$sub);
        }
        foreach(glob($private.'/*')?:[] as $file)@unlink($file);
        @rmdir($private);
    }
}
