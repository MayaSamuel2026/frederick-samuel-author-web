<?php
declare(strict_types=1);
/** Per-vacancy research, CV comparison, and saved private cover-letter PDF. */
function career_preparation_schema(): void {
    db()->exec('CREATE TABLE IF NOT EXISTS job_preparations (
        job_id INTEGER PRIMARY KEY,
        source_status TEXT NOT NULL DEFAULT "",
        source_note TEXT NOT NULL DEFAULT "",
        checked_at TEXT,
        analysis TEXT NOT NULL DEFAULT "",
        pdf_path TEXT NOT NULL DEFAULT "",
        pdf_filename TEXT NOT NULL DEFAULT "",
        pdf_created_at TEXT,
        source_url TEXT NOT NULL DEFAULT ""
    )');
    $cols=array_column(db()->query('PRAGMA table_info(job_preparations)')->fetchAll(), 'name');
    if(!in_array('source_url',$cols,true))db()->exec('ALTER TABLE job_preparations ADD COLUMN source_url TEXT NOT NULL DEFAULT ""');
}
function career_preparation(int $id): array {
    career_preparation_schema();
    $q=db()->prepare('SELECT * FROM job_preparations WHERE job_id=?');
    $q->execute([$id]);
    return $q->fetch() ?: ['job_id'=>$id,'source_status'=>'','source_note'=>'','analysis'=>'','pdf_path'=>'','pdf_filename'=>'','pdf_created_at'=>null,'source_url'=>''];
}
function career_prepare_save(int $id,array $changes): void {
    career_preparation_schema();
    $old=career_preparation($id);
    $v=array_merge($old,$changes);
    db()->prepare('INSERT INTO job_preparations(job_id,source_status,source_note,checked_at,analysis,pdf_path,pdf_filename,pdf_created_at,source_url)
        VALUES(?,?,?,?,?,?,?,?,?)
        ON CONFLICT(job_id) DO UPDATE SET source_status=excluded.source_status, source_note=excluded.source_note,
        checked_at=excluded.checked_at, analysis=excluded.analysis, pdf_path=excluded.pdf_path,
        pdf_filename=excluded.pdf_filename, pdf_created_at=excluded.pdf_created_at, source_url=excluded.source_url')
      ->execute([$id,$v['source_status'],$v['source_note'],$v['checked_at']??null,$v['analysis'],$v['pdf_path'],$v['pdf_filename'],$v['pdf_created_at'],$v['source_url']??'']);
}
function career_verified_cv_evidence(): array {
    // Facts checked against Frederick_Samuel_CV_DE_2026.pdf (October 2026).
    return [
      ['key'=>'leadership','test'=>'/führung|leiten|leitung|team|leadership|people.management|coaching|außendienst|field.force|sales.manager|vertriebsleiter|regionalleiter/i',
       'de'=>'Bei Vifor Pharma habe ich ein bundesweites Vertriebsteam aufgebaut, geführt und gecoacht.',
       'en'=>'At Vifor Pharma I built, led and coached a nationwide sales team.',
       'title_de'=>'Führung und Teamentwicklung','title_en'=>'Sales leadership'],
      ['key'=>'performance','test'=>'/kpi|forecast|reporting|performance|ziel|steuern|steuerung|effektiv|excellence|effectiveness|dashboard|analys|efficien|kennzahl|controlling/i',
       'de'=>'Als Leiter Commercial Effectiveness bei Danone Medical Nutrition habe ich KPI-Systeme, Dashboards, Gebietsanalysen und Targeting-Ansätze entwickelt.',
       'en'=>'As Head of Commercial Effectiveness at Danone Medical Nutrition, I developed KPI systems, dashboards, territory analysis and targeting.',
       'title_de'=>'Commercial Effectiveness','title_en'=>'Commercial effectiveness'],
      ['key'=>'international','test'=>'/international|global|europe|europ|dach|länder|cross.border|export|region|market.expansion|markterschließung/i',
       'de'=>'Bei Uriach / Pascoe steuere ich den OTC-/Rx-Vertrieb in Deutschland, Österreich, Tschechien, der Slowakei und dem Vereinigten Königreich.',
       'en'=>'At Uriach / Pascoe I manage OTC/Rx sales activity in Germany, Austria, the Czech Republic, Slovakia and the UK.',
       'title_de'=>'Internationale Märkte','title_en'=>'International markets'],
      ['key'=>'growth','test'=>'/wachstum|growth|geschäftsentwicklung|business.development|go.to.market|launch|marktanteil|umsatz|revenue|profit|commercial.strategy|neukunden/i',
       'de'=>'Bei Uriach / Pascoe entwickle und realisiere ich Go-to-Market-, Launch- und Business-Development-Initiativen mit Fokus auf Umsatz und Marktanteile.',
       'en'=>'At Uriach / Pascoe I develop and execute go-to-market, launch and business-development initiatives focused on revenue and market share.',
       'title_de'=>'Wachstum und Marktentwicklung','title_en'=>'Growth and go-to-market'],
      ['key'=>'healthcare','test'=>'/pharma|healthcare|medtech|health|medizin|arzt|ärzte|kliniken|hospital|apothel|apothek|rx|otc|hcp|patient|therap/i',
       'de'=>'Meine Vertriebs- und Führungserfahrung umfasst Pharma, OTC/Rx, Facharztsegmente, Kliniken und Apotheken.',
       'en'=>'My sales and leadership experience spans pharmaceuticals, OTC/Rx, medical specialties, hospitals and pharmacies.',
       'title_de'=>'Pharma und Healthcare','title_en'=>'Pharma and healthcare'],
      ['key'=>'keyaccounts','test'=>'/key.account|strategische.partner|kundenbeziehung|kundenmanagement|stakeholder|verhandlung|vertrags|channel|distribu|partnerschaft/i',
       'de'=>'Bei Hollister habe ich strategische Kundenbeziehungen mit Fachhändlern, Ärzten, Kliniken und Apotheken ausgebaut und Vertragsverhandlungen geführt.',
       'en'=>'At Hollister I developed strategic relationships with distributors, physicians, hospitals and pharmacies, including contract negotiations.',
       'title_de'=>'Key Accounts und Partnerschaften','title_en'=>'Key accounts and partnerships'],
      ['key'=>'training','test'=>'/training|schulung|entwickl|enablement|fähigkeiten|coaching|personalentwicklung|change.management/i',
       'de'=>'Bei Danone Medical Nutrition habe ich auch die Schulungsabteilung geführt und praxisnahe Vertriebs- und Führungstrainings entwickelt.',
       'en'=>'At Danone Medical Nutrition I also led training and developed practical sales and management programmes.',
       'title_de'=>'Training und Entwicklung','title_en'=>'Coaching and development']
    ];
}
function career_match_cv(array $job,string $lang='de'): array {
    $description=trim(strip_tags(html_entity_decode((string)($job['description']??''),ENT_QUOTES|ENT_HTML5,'UTF-8')));
    $text=$description.' '.(string)($job['title']??'');
    $matches=[];
    foreach(career_verified_cv_evidence() as $item) {
        if(preg_match($item['test'],$text))$matches[]=[
          'key'=>$item['key'],
          'theme'=>$item['title_'.$lang],
          'cv_evidence'=>$item[$lang]
        ];
    }
    // Requirement text has priority over generic title keywords; never invent an experience.
    if(!$matches)$matches[]= [
        'key'=>'core','theme'=>$lang==='de'?'Vertriebserfahrung':'Commercial background',
        'cv_evidence'=>$lang==='de'?'Ich habe langjährige Erfahrung in Vertrieb und Business Development, unter anderem in Pharma und Healthcare.':'I bring extensive experience in sales and business development, including pharmaceutical and healthcare markets.'
    ];
    return ['matched'=>$matches,'description_length'=>mb_strlen($description),
      'description_available'=>mb_strlen($description)>=180,'cv_source'=>'Frederick_Samuel_CV_DE_2026.pdf'];
}
function career_composed_letter(array $job,string $lang,string $tone): string {
    $analysis=career_match_cv($job,$lang);
    if(!$analysis['description_available'])throw new RuntimeException($lang==='de'?'Bitte zuerst die vollständige Stellenbeschreibung laden oder einfügen.':'Load or paste the full job description before drafting.');
    $matches=array_slice($analysis['matched'],0,3);
    $proofs=array_map(static fn($m)=>$m['cv_evidence'],$matches);
    $company=trim((string)($job['company']??''));$title=trim((string)($job['title']??''));
    $concise=$tone==='concise';
    if($lang==='en') {
      $opening="I would like to apply for the {$title} position at {$company}. The responsibilities described in your posting connect well with my work in commercial leadership and healthcare.";
      $body=implode(' ',array_slice($proofs,0,$concise?2:3));
      return "Dear Hiring Team,\n\n{$opening}\n\n{$body}\n\nI would be pleased to discuss how this experience could be useful to your team.\n\nKind regards,\nFrederick Samuel";
    }
    $opening="ich bewerbe mich auf die Position {$title} bei {$company}. In Ihrer Ausschreibung finde ich Aufgaben wieder, mit denen ich mich in meiner bisherigen Laufbahn intensiv beschäftigt habe.";
    $body=implode(' ',array_slice($proofs,0,$concise?2:3));
    return "Sehr geehrtes Recruiting-Team,\n\n{$opening}\n\n{$body}\n\nGerne bespreche ich mit Ihnen, wie ich diese Erfahrung bei {$company} einbringen kann.\n\nMit freundlichen Grüßen\nFrederick Samuel";
}
/** Verify host and pin its public DNS address; do not fetch private or internal URLs. */
function career_safe_advert_url(string $url): array {
    $p=parse_url($url);
    if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||(!empty($p['port'])&&(int)$p['port']!==443))
        throw new RuntimeException('Only public HTTPS job advert URLs can be read.');
    $host=strtolower(rtrim((string)$p['host'],'.'));
    if(strlen($host)>220||$host==='localhost'||!str_contains($host,'.')||filter_var($host,FILTER_VALIDATE_IP)||preg_match('/\.(local|internal|localhost|test|invalid)$/i',$host))
        throw new RuntimeException('Invalid public job site.');
    $records=dns_get_record($host,DNS_A);
    $ips=[];
    foreach(($records?:[]) as $rec) {
        $ip=(string)($rec['ip']??'');
        if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))$ips[]=$ip;
    }
    if(!$ips)throw new RuntimeException('The job site has no public IPv4 address.');
    return [$host,$ips[0]];
}
function career_read_public_advert(string $url): string {
    if(!function_exists('curl_init'))throw new RuntimeException('Job-page retrieval requires cURL.');
    for($redirect=0;$redirect<4;$redirect++) {
        [$host,$ip]=career_safe_advert_url($url);
        $c=curl_init($url);
        curl_setopt_array($c,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_TIMEOUT=>14,CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_RESOLVE=>["{$host}:443:{$ip}"],CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; FrederickCareerPrivate/1.0)',
            CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml','Accept-Language: de-DE,de;q=0.9,en;q=0.6'],
            CURLOPT_ENCODING=>'',CURLOPT_MAXFILESIZE=>8000000,
            CURLOPT_HEADER=>true
        ]);
        $result=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);
        $headerSize=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);$type=(string)curl_getinfo($c,CURLINFO_CONTENT_TYPE);
        $error=curl_error($c);curl_close($c);
        if(!is_string($result))throw new RuntimeException('Job site inaccessible: '.clean($error,150));
        $headers=substr($result,0,$headerSize);
        if($status>=300&&$status<400&&preg_match('/^location:\s*(.+)$/mi',$headers,$m)){
            $next=trim($m[1]);
            if(str_starts_with($next,'/'))$next='https://'.$host.$next;
            if(!str_starts_with($next,'https://'))throw new RuntimeException('Non-HTTPS redirect refused.');
            $url=$next;continue;
        }
        if($status<200||$status>=300)throw new RuntimeException('Job site returned HTTP '.$status);
        if(!str_contains($type,'html'))throw new RuntimeException('Original advert is not an HTML page.');
        $html=substr($result,$headerSize,8000000);
        if(preg_match('/captcha|verify you are human|enable javascript to continue|access denied|unusual traffic/i',substr($html,0,25000)))
            throw new RuntimeException('Advert requires a browser session; paste the description below.');
        return career_extract_job_description($html);
    }
    throw new RuntimeException('Too many job-page redirects.');
}
function career_extract_job_description(string $html): string {
    $dom=new DOMDocument();$prior=libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET|LIBXML_NOWARNING|LIBXML_NOERROR);
    libxml_clear_errors();libxml_use_internal_errors($prior);
    $xp=new DOMXPath($dom);
    foreach($xp->query('//script[@type="application/ld+json"]') ?: [] as $script) {
        $json=json_decode((string)$script->textContent,true);
        $items=isset($json['@graph'])?$json['@graph']:(array_is_list((array)$json)?$json:[$json]);
        foreach($items as $item)if(is_array($item)&&str_contains(strtolower(implode(' ',(array)($item['@type']??''))),'jobposting')&&!empty($item['description'])) {
            $desc=career_normalize_description((string)$item['description']);
            if(mb_strlen($desc)>=180)return $desc;
        }
    }
    foreach($xp->query('//script|//style|//nav|//footer|//header|//noscript|//form') ?: [] as $node)$node->parentNode?->removeChild($node);
    $queries=[
        '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"job-description")]',
        '//*[@id="jobDescriptionText" or @id="job-description" or @id="jobDescription"]',
        '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"jobdescription")]',
        '//main//article','//article','//main'
    ];
    foreach($queries as $query) {
        foreach($xp->query($query) ?: [] as $node) {
            $s=career_normalize_description($node->textContent??'');
            if(mb_strlen($s)>=180)return $s;
        }
    }
    throw new RuntimeException('Full description not readable on this page. Paste the advert text below.');
}
function career_normalize_description(string $s): string {
    $s=html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $s=preg_replace('/[\x{00A0}\t ]+/u',' ',$s);
    $s=preg_replace('/[\r\n ]{2,}/u',"\n",trim($s));
    return trim((string)$s);
}

/**
 * Independently validated employer advert; a different similarly named
 * B. Braun position must never be substituted.
 */
function career_known_official_adverts(array $job): array {
    $company=mb_strtolower((string)($job['company']??''));
    $title=mb_strtolower((string)($job['title']??''));
    $location=mb_strtolower((string)($job['location']??''));
    $context=mb_strtolower(implode(' ',[$location,(string)($job['description']??''),(string)($job['url']??'')]));
    $isBraun=str_contains($company,'braun') && (str_contains($company,'b.') || str_contains($company,'b ') || str_contains($company,'bbraun'));
    if($isBraun && str_contains($title,'business development manager')
       && !str_contains($context,'tuttlingen') && !str_contains($context,'robotic surgery') && !str_contains($title,'robotic surgery')
       && (str_contains($context,'melsungen') || str_contains($context,'14588') || in_array(trim($location),['','deutschland','germany','hessen','germany (remote)','bundesweit'],true))) {
        return ['https://jobs.bbraun.com/job/Melsungen-Business-Development-Manager-%28wmd%29/14588-de_DE/'];
    }
    return [];
}
function career_bbraun_14588_summary(array $job): ?array {
    $official=career_known_official_adverts($job);
    if(!$official)return null;
    // Independently verified against the official B. Braun Avitum job posting (14588).
    $summary="B. Braun Avitum AG · Business Development Manager (w/m/d)\nMelsungen, Hessen · Hybrid · Global Marketing & Sales · Health Care Services · Referenz 14588\n\n"
      ."Unternehmen: B. Braun betreibt weltweit Dialysezentren sowie europäische Homecare-Angebote. Der Bereich Health Care Services ist für die internationale Weiterentwicklung dieses Netzwerks verantwortlich.\n\n"
      ."Aufgaben: Entwicklung und Abstimmung kurz- und langfristiger Business-Development-Ziele; Koordination und Umsetzung strategischer Wachstums- und Portfolioinitiativen; Identifikation und Bewertung von Expansions-, Akquisitions-, Beteiligungs- und Kooperationsmöglichkeiten; Entwicklung und Validierung von Investitions-Business-Cases; Verhandlungsführung, Projektbudgetierung und Kostenkontrolle; Erstellung von Vorstandsunterlagen und Managementpräsentationen; Abstimmung mit regionalen und lokalen Führungskräften, Gesundheitsdienstleistern und internationalen Projektteams; Steuerung der Projekte bis zum Abschluss.\n\n"
      ."Anforderungen: abgeschlossenes Hochschulstudium mit Bezug zu Betriebswirtschaft, Marketing, Vertrieb oder Ingenieurwesen; mehrjährige Erfahrung in Marketing, Vertrieb, Produktmanagement und/oder Business Development; sehr gute Englisch- und gute Deutschkenntnisse; strukturierte, eigenständige Arbeitsweise, Verhandlungskompetenz und Umsetzungskraft; Erfahrung mit interkulturellen Teams von Vorteil; Bereitschaft zu internationalen Dienstreisen.\n\n"
      ."Quelle: offizielle B. Braun Avitum AG Stellenanzeige, Requisition 14588 (inhaltlich geprüfte Zusammenfassung, kein vollständiges Originalzitat).";
    return ['description'=>$summary,'url'=>$official[0]];
}
function career_advert_matches(array $job,string $description,string $sourceUrl): bool {
    if(mb_strlen($description)<180)return false;
    if(str_contains($sourceUrl,'jobs.bbraun.com') && str_contains($sourceUrl,'14588')) {
        return (bool)preg_match('/B\.?\s*Braun|Avitum/iu',$description)
          && (bool)preg_match('/Melsungen|14588/iu',$description)
          && (bool)preg_match('/business.development.manager/iu',$description);
    }
    return true;
}

/** Search indexed public employer postings when an intermediary job board blocks access. */
function career_indexed_employer_candidates(array $job): array {
    $title=trim((string)($job['title']??''));
    $company=trim((string)($job['company']??''));
    if($title==='' || $company==='')return [];
    $query=$title.' '.$company.' '.trim((string)($job['location']??'')).' careers job';
    $url='https://www.bing.com/search?'.http_build_query(['q'=>$query,'format'=>'rss']);
    try {
        [$host,$ip]=career_safe_advert_url($url);
        $c=curl_init($url);
        curl_setopt_array($c,[
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>["{$host}:443:{$ip}"],
            CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>9,CURLOPT_MAXFILESIZE=>600000,
            CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; FrederickCareerPrivate/1.0)',
            CURLOPT_HTTPHEADER=>['Accept: application/rss+xml,application/xml,text/xml']
        ]);
        $rss=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        if(!is_string($rss)||$code!==200)return [];
        $doc=new DOMDocument();$prev=libxml_use_internal_errors(true);
        $valid=$doc->loadXML($rss,LIBXML_NONET|LIBXML_NOWARNING|LIBXML_NOERROR);
        libxml_clear_errors();libxml_use_internal_errors($prev);
        if(!$valid)return [];
        $candidates=[];$xpath=new DOMXPath($doc);
        foreach($xpath->query('//item') ?: [] as $item) {
            $link=trim((string)$xpath->evaluate('string(link)',$item));
            $label=trim((string)$xpath->evaluate('string(title)',$item)).' '.trim(strip_tags((string)$xpath->evaluate('string(description)',$item)));
            if(!career_looks_like_employer_link($link))continue;
            if(!career_candidate_title_match($job,$label.' '.$link))continue;
            $candidates[$link]=$link;
        }
        return array_values($candidates);
    }catch(Throwable $e) {
        error_log('Career public employer lookup: '.$e->getMessage());
        return [];
    }
}
function career_looks_like_employer_link(string $url): bool {
    $p=parse_url($url);
    if(!is_array($p)||($p['scheme']??'')!=='https'||empty($p['host']))return false;
    $host=mb_strtolower((string)$p['host']);$path=mb_strtolower((string)($p['path']??''));
    foreach(['indeed.','stepstone.','linkedin.','xing.','glassdoor.','jobijoba.','stellenanzeigen.','adzuna.','jobteaser.','monster.'] as $aggregator)
        if(str_contains($host,$aggregator))return false;
    return (bool)preg_match('/jobs?\.|career|workdayjobs|personio|greenhouse|lever\.co|smartrecruiters|successfactors|ashbyhq|recruitee/u',$host)
        || (bool)preg_match('~/(jobs|careers|karriere|stellenangebote|jobdetail)/~u',$path);
}
function career_candidate_title_match(array $job,string $evidence): bool {
    $title=mb_strtolower((string)($job['title']??''));$evidence=mb_strtolower($evidence);
    $terms=preg_split('/[^\p{L}\p{N}]+/u',$title,-1,PREG_SPLIT_NO_EMPTY);
    $terms=array_values(array_unique(array_filter($terms,static fn($w)=>mb_strlen($w)>=4&&!in_array($w,['oder','andere','senior','junior'],true))));
    if(!$terms)return false;
    $hits=0;
    foreach($terms as $term)if(str_contains($evidence,$term))$hits++;
    $required=min(2,count($terms));
    return $hits>=$required;
}
function career_candidate_company_match(array $job,string $description): bool {
    $company=mb_strtolower((string)($job['company']??''));$description=mb_strtolower($description);
    $words=preg_split('/[^\p{L}\p{N}]+/u',$company,-1,PREG_SPLIT_NO_EMPTY);
    $words=array_values(array_filter($words,static fn($w)=>mb_strlen($w)>=4&&!in_array($w,['group','gruppe','gmbh','holding','international','company','aktiengesellschaft'],true)));
    foreach($words as $word)if(str_contains($description,$word))return true;
    return false;
}
function career_research_job(int $id,?string $manual=null): array {
    $job=onejob($id);$status='retrieved';$note='';$sourceUrl=(string)$job['url'];
    if($manual!==null) {
        $description=career_normalize_description($manual);$status='supplied';
        if($description==='')throw new RuntimeException('Please paste the job description.');
        $sourceUrl='';
    } else {
        $errors=[];
        try {
            $description=career_read_public_advert((string)$job['url']);
            if(!career_advert_matches($job,$description,(string)$job['url']))
                throw new RuntimeException('Source did not contain the matching vacancy.');
        } catch(Throwable $e) {
            $description='';$errors[]=$e->getMessage();
            foreach(career_known_official_adverts($job) as $official) {
                try {
                    $candidate=career_read_public_advert($official);
                    if(!career_advert_matches($job,$candidate,$official))
                        throw new RuntimeException('Employer posting does not match this vacancy.');
                    $description=$candidate;$status='official';$sourceUrl=$official;
                    $note='Stellenbeschreibung direkt von der offiziellen Arbeitgeberseite eingelesen.';
                    break;
                } catch(Throwable $officialError) {
                    $errors[]='Employer source: '.$officialError->getMessage();
                }
            }
            if($description==='') {
                foreach(career_indexed_employer_candidates($job) as $candidateUrl) {
                    try {
                        $candidateText=career_read_public_advert($candidateUrl);
                        if(!career_candidate_title_match($job,$candidateText)
                           || !career_candidate_company_match($job,$candidateText))
                            continue;
                        $description=$candidateText;$status='official';$sourceUrl=$candidateUrl;
                        $note='Stellenbeschreibung aus öffentlich auffindbarer Arbeitgeberanzeige eingelesen.';
                        break;
                    }catch(Throwable $indexedError) {
                        $errors[]='Indexed employer: '.$indexedError->getMessage();
                    }
                }
            }
            if($description==='') {
                $saved=career_normalize_description((string)$job['description']);
                // Ignore the "Job alert received" placeholder; use genuine saved text.
                if(mb_strlen($saved)>=180) {
                    $description=$saved;$status='saved';$sourceUrl=(string)$job['url'];
                    $note='Originalquelle nicht direkt abrufbar; gespeicherte Stellenbeschreibung verwendet.';
                } else {
                    $verified=career_bbraun_14588_summary($job);
                    if($verified!==null) {
                        $description=$verified['description'];$status='verified_summary';$sourceUrl=$verified['url'];
                        $note='Geprüfte Zusammenfassung aus der offiziellen B. Braun Ausschreibung 14588. Originalseite ist verlinkt.';
                    } else {
                        throw new RuntimeException('Originalanzeige momentan nicht direkt abrufbar ('.implode(' · ',array_slice($errors,0,2)).'). Bitte offiziellen Arbeitgeberlink prüfen oder Beschreibung einfügen.');
                    }
                }
            }
        }
    }
    db()->prepare('UPDATE jobs SET description=? WHERE id=?')->execute([$description,$id]);
    $job['description']=$description;
    $analysis=['de'=>career_match_cv($job,'de'),'en'=>career_match_cv($job,'en')];
    career_prepare_save($id,['source_status'=>$status,'source_note'=>$note,'source_url'=>$sourceUrl,'checked_at'=>gmdate('Y-m-d H:i:s'),
        'analysis'=>json_encode($analysis,JSON_UNESCAPED_UNICODE)]);
    return ['description'=>$description,'source_status'=>$status,'source_note'=>$note,'source_url'=>$sourceUrl,'analysis'=>$analysis];
}
function career_create_pdf(int $id,string $letter,string $lang): array {
    $job=onejob($id);
    $letter=clean($letter,10000);
    if(!$letter)throw new RuntimeException('Review the cover-letter draft first.');
    db()->prepare('UPDATE jobs SET letter=?,lang=?,status=CASE WHEN status="new" THEN "draft" ELSE status END WHERE id=?')
       ->execute([$letter,$lang,$id]);
    $job['letter']=$letter;
    $data=pdftext($job,$letter);
    if(!str_starts_with($data,'%PDF-'))throw new RuntimeException('PDF creation failed.');
    $dir=priv().'/letters';
    if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Private PDF storage unavailable.');
    $filename='Frederick_Samuel_'.preg_replace('/[^A-Za-z0-9_-]+/','_',(string)$job['company']).'_'.(int)$id.'.pdf';
    $path=$dir.'/job-'.$id.'-'.bin2hex(random_bytes(7)).'.pdf';
    if(file_put_contents($path,$data,LOCK_EX)===false)throw new RuntimeException('Could not save letter PDF.');
    @chmod($path,0600);
    $previous=career_preparation($id);
    career_prepare_save($id,['pdf_path'=>$path,'pdf_filename'=>$filename,'pdf_created_at'=>gmdate('Y-m-d H:i:s')]);
    if(!empty($previous['pdf_path'])&&$previous['pdf_path']!==$path&&is_file($previous['pdf_path']))@unlink($previous['pdf_path']);
    return ['filename'=>$filename,'created_at'=>gmdate('Y-m-d H:i:s')];
}
