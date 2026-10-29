<?php
declare(strict_types=1);
/** Frederick Samuel private career workspace. Never store configuration or documents under public_html. */
$private = dirname(__DIR__, 2) . '/frederick-career-private';
$configFile = $private . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
if (!is_array($config)) $config = [];
function configured(): bool { global $config; return !empty($config['password_hash']); }
function priv(): string { global $private; return $private; }
function cfg(string $k, string $default=''): string { global $config; return (string)($config[$k] ?? $default); }
function db(): PDO {
    static $db;
    if ($db) return $db;
    if (!configured()) throw new RuntimeException('Workspace is not configured.');
    $dir = priv();
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Cannot create private directory.');
    $db = new PDO('sqlite:' . $dir . '/career.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('CREATE TABLE IF NOT EXISTS jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,source TEXT NOT NULL,company TEXT NOT NULL,title TEXT NOT NULL,location TEXT DEFAULT "",url TEXT NOT NULL UNIQUE,description TEXT DEFAULT "",email TEXT DEFAULT "",status TEXT NOT NULL DEFAULT "new",found_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,applied_at TEXT,reply_at TEXT,note TEXT DEFAULT "",lang TEXT DEFAULT "de",letter TEXT DEFAULT "")');
    $db->exec('CREATE TABLE IF NOT EXISTS files(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,kind TEXT NOT NULL,path TEXT NOT NULL,mime TEXT NOT NULL,size INTEGER NOT NULL,uploaded_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY,v TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS sent(id INTEGER PRIMARY KEY AUTOINCREMENT,job_id INTEGER,recipient TEXT NOT NULL,subject TEXT NOT NULL,body TEXT NOT NULL,gmail_id TEXT,created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    return $db;
}
function setting(string $k,string $fallback=''): string {$q=db()->prepare('SELECT v FROM settings WHERE k=?');$q->execute([$k]);$v=$q->fetchColumn();return $v===false?$fallback:(string)$v;}
function setsetting(string $k,string $v): void {db()->prepare('INSERT INTO settings(k,v) VALUES (?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$k,$v]);}
function rows(string $sql,array $bind=[]): array {$q=db()->prepare($sql);$q->execute($bind);return $q->fetchAll();}
function onejob(int $id): array {$q=db()->prepare('SELECT * FROM jobs WHERE id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)throw new RuntimeException('Job not found');return $r;}
function clean(string $s,int $length=4000): string { $s=trim($s);return function_exists('mb_substr')?mb_substr($s,0,$length):substr($s,0,$length); }
function jsonout(array $arr,int $code=200): never {http_response_code($code);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');echo json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function fail(string $s,int $code=400): never {jsonout(['ok'=>false,'error'=>$s],$code);}
function httpget(string $url,array $headers=[]): array {
    $h=array_merge(['Accept: application/json','User-Agent: FrederickCareerPrivate/1.0 (+https://fredericksamuel.com)'],$headers);
    if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required');
    $c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>16,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$h]);
    $s=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if(!is_string($s)||$code<200||$code>=300)throw new RuntimeException('Source HTTP '.$code);
    $v=json_decode($s,true);return is_array($v)?$v:[];
}
/** Accept only clearly Germany-based vacancies. Ambiguous and international-only locations stay out. */
/** An explicitly foreign work location is never eligible for the Job Center. */
function career_foreign_location(string $location): bool {
    return (bool)preg_match('/\b(london|barcelona|paris|madrid|amsterdam|brussels?|brüssel|vienna|wien|zurich|zürich|prague|praha|prag|warsaw|warszawa|roma|rome|milan|milano|lisbon|lissabon|stockholm|copenhagen|kopenhagen|new york|boston|dublin|ireland|irland|edinburgh|manchester|birmingham|glasgow|lisboa|porto|budapest|hungary|ungarn|bratislava|slovakia|slowakei|czechia|tschechien|czech republic|denmark|dänemark|sweden|schweden|norway|norwegen|finland|finnland|romania|rumänien|bulgaria|bulgarien|greece|griechenland|turkey|türkei|portugal|luxembourg|luxemburg|canada|kanada|usa|united states|u\.s\.|uk|u\.k\.|united kingdom|great britain|großbritannien|england|spain|spanien|france|frankreich|italy|italien|poland|polen|austria|österreich|schweiz|switzerland|belgium|belgien|netherlands|niederlande|nederland|holland|worldwide|weltweit|remote worldwide)\b/iu',$location);
}
function career_germany_location(string $location,string $source=''): bool {
    $loc=trim($location);
    if($loc==='')return false;
    // Never accept explicitly foreign bases, even when the vacancy was carried by a German board.
    if(career_foreign_location($loc))return false;
    // Country / nationwide and common German territory labels, including regional postings.
    if(preg_match('/\b(deutschland|germany|deutschlandweit|bundesweit|bundesgebiet|bundesrepublik|homeoffice deutschland|remote germany|dach deutschland|norddeutschland|süddeutschland|westdeutschland|ostdeutschland|mitteldeutschland|nordwestdeutschland|südwestdeutschland|nordostdeutschland|südostdeutschland|baden.württemberg|rheinland.pfalz|nordrhein.westfalen|niedersachsen|schleswig.holstein|thüringen|brandenburg|mecklenburg.vorpommern|bayern|bavaria|sachsen.anhalt|sachsen|saarland|hessen|westfalen|rhein.main|ruhrgebiet|oberbayern|niederbayern|oberfranken|mittelfranken|unterfranken|oberpfalz|schwaben|rheinland|pfalz|rhein.neckar|bergisches land|sauerland|ostwestfalen|eifel|mosel|schwarzwald|allgäu|bodensee)\b/iu',$loc))return true;
    // Germany-specific postal notation; a bare international 5-digit number alone is not sufficient.
    if(preg_match('/\b(?:DE|D)[- ]?\d{5}\b/i',$loc))return true;
    // A five-digit postal code attached to a German-source vacancy is usually a German PLZ.
    if(preg_match('/\b\d{5}\b/',$loc) && preg_match('/Arbeitsagentur|StepStone|JobPharm|Indeed/i',$source))return true;
    // Widely used German location names, including those in recent senior pharma alerts.
    if(preg_match('/\b(berlin|hamburg|münchen|munich|frankfurt|köln|cologne|stuttgart|düsseldorf|dresden|leipzig|dortmund|essen|bremen|hannover|nürnberg|nuremberg|bonn|duisburg|bochum|wuppertal|bielefeld|mannheim|karlsruhe|münster|augsburg|wiesbaden|aachen|mönchengladbach|gelsenkirchen|braunschweig|chemnitz|kiel|halle|magdeburg|freiburg|krefeld|mainz|lübeck|erfurt|rostock|kassel|potsdam|heidelberg|darmstadt|offenbach|oldenburg|osnabrück|regensburg|ingolstadt|würzburg|ulm|heilbronn|pforzheim|reutlingen|fürth|jena|trier|koblenz|gießen|giessen|neuss|solingen|leverkusen|bensheim|frechen|bad homburg|ansbach|suhl|gotha|bamberg|minden|bremerhaven|wetzlar|ravensburg|walsrode|remscheid|schweinfurt|wolfsburg|saarbrücken|hildesheim|sigmaringen|lüneburg|ludwigshafen|tübingen|bayreuth|fulda|montabaur|lahnstein|neuwied|weiden|rüsselsheim|rottenburg|falkensee|cottbus|aalen|düren|greifswald|kaufbeuren|fürstenfeldbruck|leer|iserlohn|gütersloh|verden|dinslaken|schwerin|göttingen|delmenhorst|freiberg|neubrandenburg|coburg|detmold|paderborn|passau|straubing|landshut|memmingen|kempten|konstanz|friedrichshafen|singen|villingen.schwenningen|lörrach|offenburg|baden.baden|rastatt|lahr|bruchsal|ettlingen|altdorf|worms|speyer|kaiserslautern|landau|zweibrücken|bad kreuznach|idar.oberstein|bitburg|zell|cochem|andernach|mayen|siegburg|troisdorf|bergisch gladbach|leverku(s|z)en|hennef|brühl|hürth|euskirchen|mülheim|oberhausen|herne|hagen|iserlohn|ar(y|n)sberg|lippstadt|gütersloh|rheda.wiedenbrück|marl|recklinghausen|dinslaken|wesel|moers|kleve|geldern|erkrath|ratingen|hilden|velbert|mettmann|lüdenscheid|sie gen|siegen|olpe|wenden|wetzlar|marburg|limburg|fulda|hanau|offenbach|dietzenbach|neu.isenburg|bad vilbel|bad nauheim|friedberg|gießen|butzbach|grünberg|hünfeld|alsfeld|eschwege|korbach|baunatal|hann.münden|hameln|celle|hildesheim|göttingen|northeim|osterode|goslar|salzgitter|peine|wolfenbüttel|helmstedt|stade|cuxhaven|wilhelmshaven|emden|aurich|leer|lingen|meppen|nordhorn|osnabrück|melle|bramsche|lohne|vechta|cloppenburg|quakenbrück|oldenbur(g|ger)|delmenhorst|westerstede|syke|achim|verden|lüneburg|uelzen|buchholz|wins(en)?|norderstedt|elmshorn|pinneberg|itzehoe|neumünster|rendsburg|flensburg|schleswig|husum|heide|eckernförde|lübeck|bad schwartau|ahrensburg|reinbek|geesthacht|schwerin|wismar|güstrow|waren|stralsund|greifswald|neustrelitz|neubrandenburg|potsdam|cottbus|frankfurt oder|eberswalde|oranienburg|bernau|strausberg|teltow|wildau|königs wusterhausen|brandenburg an der havel|rathenow|neuruppin|luckenwalde|senftenberg|finsterwalde|forst|guben|dresden|pirna|freital|rabenau|radebeul|meißen|riesa|görlitz|zittau|bautzen|kamenz|freiberg|chemnitz|zwickau|plauen|annaberg.buchholz|glauchau|leipzig|markkleeberg|delitzsch|grimma|torgau|eilenburg|borna|halle|merseburg|weißenfels|naumburg|zeitz|bitterfeld|dessau|köthen|bernburg|magdeburg|schönebeck|halberstadt|wernigerode|quedlinburg|stendal|salzwedel|wolfsburg|osterburg|erfurt|weimar|jena|gera|eisenach|gotha|suhl|ilmenau|arnstadt|nordhausen|mühlhausen|altenburg|schmölln|rudolstadt|saalfeld|sonneberg|meiningen|schmalkalden)\b/iu',$loc))return true;
    // German Federal Employment Agency feed is scoped to Germany; its smaller towns
    // must not disappear just because they are missing from a handwritten city list.
    if(strcasecmp($source,'Arbeitsagentur')===0)return true;
    // Public result searches explicitly restricted to Germany contain many smaller towns.
    // Reject any explicitly foreign base above, then allow their German result-page cities.
    if(in_array($source,['StepStone','Indeed','LinkedIn','JobPharm'],true)||in_array($source,['Gmail · LinkedIn','Hostinger · LinkedIn'],true))return true;
    // Genuine unknown bases are not automatically treated as Germany.
    return false;
}
function career_norm(string $x): string {
    $x=function_exists('mb_strtolower')?mb_strtolower($x,'UTF-8'):strtolower($x);
    $x=preg_replace('/\b(m\/w\/d|w\/m\/d|all genders|gmbh|co|kg|ag|mbh|deutschland)\b/iu','',$x);
    return trim(preg_replace('/[^\pL\pN]+/u',' ',$x));
}
function career_same_job(array $a,array $b): bool {
    $co1=career_norm((string)($a['company']??''));$co2=career_norm((string)($b['company']??''));
    $t1=career_norm((string)($a['title']??''));$t2=career_norm((string)($b['title']??''));
    $l1=career_norm((string)($a['location']??''));$l2=career_norm((string)($b['location']??''));
    if($l1!==$l2||!$co1||!$co2||!$t1||!$t2)return false;
    $co=($co1===$co2)||(strlen($co1)>12&&strlen($co2)>12&&str_starts_with($co1,$co2))||(strlen($co1)>12&&strlen($co2)>12&&str_starts_with($co2,$co1));
    if(!$co)return false;
    if($t1===$t2)return true;
    // StepStone truncates some alerts at 40 characters. Require a substantial identical prefix.
    return (strlen($t1)>28&&str_starts_with($t2,substr($t1,0,28)))||(strlen($t2)>28&&str_starts_with($t1,substr($t2,0,28)));
}
function upsertjob(array $r): int {
    $url=trim((string)($r['url']??''));
    if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true))return 0;
    $r['title']=clean((string)($r['title']??''),200);
    $r['location']=clean((string)($r['location']??''),140);
    $r['company']=clean((string)($r['company']??''),160);
    if(!career_job_eligible($r))return 0;
    // URL uniqueness + semantic de-duplication across crawler and Gmail alert sources.
    foreach(rows('SELECT id,company,title,location,url FROM jobs WHERE status != "archived"') as $existing){
        if((string)$existing['url']===$url||career_same_job($r,$existing))return 0;
    }
    $s=db()->prepare('INSERT OR IGNORE INTO jobs(source,company,title,location,url,description) VALUES (?,?,?,?,?,?)');
    $s->execute([clean((string)($r['source']??'Manual'),80),$r['company'],$r['title'],$r['location'],$url,clean(strip_tags((string)($r['description']??'')),2400)]);
    return $s->rowCount();
}
/** The owner can maintain comma-separated discovery and exclusion terms. */
function career_default_includes(): string {
    return 'regionalleiter, sales manager, sales director, pharma, commercial effectiveness, commercial excellence, head of sales, vertriebsleiter, head of field force';
}
function career_default_excludes(): string {
    return 'immobilien, maklersoftware, software';
}
function career_keyword_terms(string $key): array {
    $fallback=$key==='career_include_keywords'?career_default_includes():career_default_excludes();
    $value=configured()?setting($key,$fallback):$fallback;
    $parts=explode(',',$value);
    $terms=[];
    foreach($parts as $part){
        $term=trim($part);
        if($term===''||strlen($term)>90)continue;
        $term=function_exists('mb_strtolower')?mb_strtolower($term,'UTF-8'):strtolower($term);
        $terms[$term]=$term;
        if(count($terms)>=30)break;
    }
    return array_values($terms);
}
function career_has_keyword(string $text,array $terms): bool {
    if(!$terms)return false;
    $text=function_exists('mb_strtolower')?mb_strtolower($text,'UTF-8'):strtolower($text);
    foreach($terms as $term){
        // Substring detection intentionally catches 'Immobilienverwaltung' and 'Maklersoftware'.
        if($term!==''&&str_contains($text,$term))return true;
    }
    return false;
}
function career_excluded_by_owner(array $j): bool {
    // Titles and company names only: job descriptions may mention CRM software,
    // property addresses or representative staff even in suitable pharma leadership roles.
    return career_has_keyword((string)($j['title']??'').' '.(string)($j['company']??''),career_keyword_terms('career_exclude_keywords'));
}
function career_user_matches(array $j): bool {
    $title=(string)($j['title']??'');
    // Search terms augment the existing senior-role logic, they do not hide
    // otherwise eligible Regionalleiter or Sales Director roles.
    return relevant($title,(string)($j['description']??''))
        || (career_has_keyword($title,career_keyword_terms('career_include_keywords'))
            && (bool)preg_match('/(manager|leiter|leitung|director|direktor|head|regional|commercial|vertrieb|sales)/iu',$title));
}
function excluded_role(string $title): bool {
    // Apply exclusions to the role title, never to the job description (which may mention sales reps).
    return (bool)preg_match('/\b(junior|jr\.?|trainee|intern(ship)?|graduate|representatives?|sales[\s-]*reps?|account[\s-]*executives?|account[\s-]*execs?|sales[\s-]*executives?|legal|jurist(in)?|rechtsanw(a|ä)lt(in)?|rechtsabteilung|pharmareferent(in)?|pharmaberater(in)?|außendienstmitarbeiter(in)?|aussendienstmitarbeiter(in)?)\b/iu', $title);
}
function relevant(string $title,string $description=''): bool {
    if(excluded_role($title))return false;
    $x=function_exists('mb_strtolower')?mb_strtolower($title):strtolower($title);$focus=['sales manager','sales director','head of sales','regionalleiter','regional sales','vertriebsleiter','vertriebsdirektor','commercial effectiveness','commercial excellence','sales excellence','commercial director','leiter vertrieb','key account director','business unit director','country manager','head of commercial','pharma vertrieb','leiter außendienst','leiter aussendienst','national sales manager','sales lead','head of field force','head of field','sales operations','vertriebsleitung','leitung vertrieb','gebietsverkaufsleiter','gebietsleiter','bezirksverkaufsleiter','bezirksleiter apotheke','niederlassungsleiter vertrieb','head of sales dach','teamleitung sales','abteilungsleitung sales','vertriebs- und projektleitung','vertriebs und projektleitung','leitung vertrieb und marketing','leiter vertriebspartner','leitung außen','leiter aussendienst','leiter außendienst','director sales','regionalverkaufsleiter','regionalverkaufsleitung','regionalleitung therapie','mvz-regionalgeschäftsführer','verkaufsleitung'];
    foreach($focus as $p)if((function_exists('mb_strpos')?mb_strpos($x,$p):strpos($x,$p))!==false)return true;
    // Senior healthcare roles often use commercial, market or field leadership titles
    // without the literal word Sales. Include these explicit leadership patterns.
    if(preg_match('/\\b(?:head of (?:biopharma|strategic market excellence|commercial|field force)|head launch & field excellence|national sales director|regional business head|commercial leader|senior director,? commercial|director international commercialization|director business development|associate director.{0,18}sales force effectiveness|commercial operations associate director)\\b/iu',$title))return true;
    if(preg_match('/\\bregional\\s+verkaufsleiter\\b|\\bdirector\\s*\\/\\s*senior director.{0,12}business development\\b|\\b(?:senior manager|associate director).{0,35}commercial operations\\b|\\bsales force excellence manager\\b/iu',$title))return true;
    // International employers often put geography between Director and Sales.
    if(preg_match('/\\b(?:senior\\s+|associate\\s+)?director\\b.{0,55}\\bsales\\b/iu',$title))return true;
    return (bool)preg_match('/(pharma|life.science|healthcare|medical|klinisch|hospital|krankenhaus|biotech)/iu',$description.' '.$title) && (bool)preg_match('/(sales|vertrieb|commercial|geschäftsbereich|business.development)/iu',$title);
}
/** Existing candidate review is deliberately less destructive than intake.
 * New crawler imports still require a confirmed German location, while an older
 * listing without enough location information remains visible for review.
 */
function career_existing_visible(array $j): bool {
    $title=(string)($j['title']??'');
    return !excluded_role($title)
        && !career_excluded_by_owner($j)
        && career_user_matches($j)
        && !career_foreign_location((string)($j['location']??''));
}
/** One rule for every intake channel, existing results and manual additions. */
function career_job_eligible(array $j): bool {
    return !excluded_role((string)($j['title']??''))
        && !career_excluded_by_owner($j)
        && career_user_matches($j)
        && career_germany_location((string)($j['location']??''),(string)($j['source']??''));
}
/** Remove unsuitable discovery results from the active database without deleting mail/application history. */
function career_archive_unsuitable_jobs(): array {
    $archive=[];$historical=0;
    foreach(rows('SELECT id,source,title,description,location,status FROM jobs WHERE status != "archived"') as $j){
        // Archive only permanently forbidden titles and confirmed foreign locations.
        // Editable personal keyword preferences filter the results without deleting
        // or archiving them; changing a keyword immediately makes the role visible again.
        if(!excluded_role((string)$j['title']) && !career_foreign_location((string)$j['location']))continue;
        if(in_array($j['status'],['applied','interview','rejected','offer'],true)){$historical++;continue;}
        $archive[]=(int)$j['id'];
    }
    if($archive){
        $conn=db();$conn->beginTransaction();
        try {
            // Record this cleanup in a dedicated table so restoration is selective.
            $conn->exec('CREATE TABLE IF NOT EXISTS filter_archives(job_id INTEGER PRIMARY KEY, prior_status TEXT NOT NULL, archived_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            $mark=$conn->prepare('INSERT OR IGNORE INTO filter_archives(job_id,prior_status) VALUES (?,?)');
            $update=$conn->prepare('UPDATE jobs SET status="archived" WHERE id=? AND status IN ("new","shortlisted","draft")');
            foreach($archive as $id) {
                $job=onejob($id);
                $mark->execute([$id,(string)$job['status']]);
                $update->execute([$id]);
            }
            $conn->commit();
        } catch(Throwable $e){$conn->rollBack();throw $e;}
    }
    return ['archived'=>count($archive),'historical_preserved'=>$historical];
}

/** One-time recovery from the overstrict 26 October location filter.
 *  Only crawler/alert discovery records with no application, note or draft.
 *  An explicit manually archived record is never resurrected.
 */
function career_restore_relevant_archives(): array {
    if(setting('restore_location_filter_20261026_done')==='yes')return ['restored'=>0,'already_run'=>true];
    $restored=0;
    $db=db();
    $db->beginTransaction();
    try {
        // A prior filter archive that predates the new filter_archives ledger cannot
        // be identified exactly. Restrict to unworked discovery jobs.
        $jobs=rows('SELECT id,source,title,description,location,status,note,letter,applied_at FROM jobs WHERE status="archived"');
        $q=$db->prepare('UPDATE jobs SET status="new" WHERE id=? AND status="archived"');
        foreach($jobs as $j){
            if(!career_existing_visible($j))continue;
            if(!preg_match('/^(Arbeitsagentur|Arbeitnow|Gmail\s*·\s*StepStone|Gmail\s*·\s*Indeed|Gmail\s*·\s*JobPharm|StepStone|Indeed|JobPharm)$/ui',(string)$j['source']))continue;
            if(trim((string)($j['note']??''))!==''||trim((string)($j['letter']??''))!==''||!empty($j['applied_at']))continue;
            $q->execute([$j['id']]);$restored+=$q->rowCount();
        }
        $db->prepare('INSERT INTO settings(k,v) VALUES (?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute(['restore_location_filter_20261026_done','yes']);
        $db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
    return ['restored'=>$restored,'already_run'=>false];
}

function crawl(): array {
    $cleanup=career_archive_unsuitable_jobs();
    $new=0;$seen=0;$log=[];
    // Unofficial BA endpoint may change; continue independently on failures.
    $searchTerms=array_slice(array_unique(array_merge(['Pharma Sales Manager','Sales Director Pharma','Regionalleiter Pharma','Commercial Effectiveness'],career_keyword_terms('career_include_keywords'))),0,10);
    foreach($searchTerms as $term){
      try {$q=httpget('https://rest.arbeitsagentur.de/jobboerse/jobsuche-service/pc/v6/jobs?'.http_build_query(['was'=>$term,'size'=>50,'page'=>1]),['X-API-Key: jobboerse-jobsuche']);
        foreach(($q['stellenangebote']??[]) as $p){$seen++;$r=['source'=>'Arbeitsagentur','company'=>$p['arbeitgeber']??'','title'=>$p['titel']??$p['beruf']??'','location'=>$p['arbeitsort']['ort']??'','url'=>($p['externeUrl']??'')?:'https://www.arbeitsagentur.de/jobsuche/jobdetail/'.rawurlencode((string)($p['refnr']??'')),'description'=>''];$new+=upsertjob($r);}
      }catch(Throwable $e){$log[]='Arbeitsagentur: '.$e->getMessage();break;}
    }
    try{
      for($page=1;$page<=5;$page++){$data=httpget('https://www.arbeitnow.com/api/job-board-api?page='.$page);$items=$data['data']??[];if(!$items)break;
        foreach($items as $p){$seen++;$title=(string)($p['title']??'');$description=(string)($p['description']??'');if(!career_user_matches(['title'=>$title,'description'=>$description]))continue;$new+=upsertjob(['source'=>'Arbeitnow','company'=>$p['company_name']??'','title'=>$title,'location'=>$p['location']??'','url'=>$p['url']??'','description'=>$description]);}
        if(empty($data['links']['next']))break;
      }
    }catch(Throwable $e){$log[]='Arbeitnow: '.$e->getMessage();}
    setsetting('last_scan',gmdate('Y-m-d H:i:s').' UTC');setsetting('last_scan_info',json_encode(['new'=>$new,'examined'=>$seen,'errors'=>$log],JSON_UNESCAPED_UNICODE));
    return ['new'=>$new,'examined'=>$seen,'errors'=>$log,'cleanup'=>$cleanup,'at'=>setting('last_scan')];
}
function jobletter(array $j,string $lang): string {
    $title=(string)$j['title'];$company=(string)$j['company'];$effect=(bool)preg_match('/effectiveness|excellence|effizienz|commercial strategy|commercial operation|vertriebssteuerung/i',$title);
    if($lang==='en'){
      $middle=$effect?'Throughout my career I have combined commercial strategy with hands-on implementation, developing segmentation and targeting approaches, CRM-based steering, sales force effectiveness and actionable reporting. I focus on turning analytical insight into practical improvements for field teams and customers.':'My professional background combines international sales leadership with operational delivery in pharmaceutical and healthcare markets. I have led cross-country commercial teams, developed key-account approaches, supported hospital engagement and driven disciplined territory and field-force execution.';
      return "Dear Hiring Team,\n\nI am writing to express my interest in the position of {$title} at {$company}. The scope of this role strongly aligns with my experience in international pharmaceutical sales leadership and commercial performance.\n\n{$middle}\n\nIn recent roles, my responsibilities have included sales management across Austria, the Czech Republic, Slovakia and the United Kingdom. I bring a pragmatic management style, a strong understanding of healthcare stakeholders and the ability to translate priorities into measurable execution.\n\nI would welcome the opportunity to discuss how my experience could contribute to {$company}. My CV and supporting documents are available for your consideration.\n\nKind regards,\nFrederick Samuel";
    }
    $middle=$effect?'Ein besonderer Schwerpunkt meiner Laufbahn liegt auf der Verzahnung von Vertriebsstrategie und Umsetzung: Segmentierung und Targeting, CRM-gestützte Vertriebssteuerung, Außendienst-Effektivität und aussagekräftige Kennzahlen. Entscheidend ist für mich, Analysen in konkrete Verbesserungen für Vertriebsteams und Kunden zu übersetzen.':'Ich verbinde langjährige internationale Vertriebsführung im Pharma- und Gesundheitsmarkt mit einer ausgeprägten Umsetzungsorientierung. Zu meinen Erfahrungen zählen die Führung länderübergreifender Teams, Key Account Management, die Zusammenarbeit mit Kliniken und eine konsequente Gebiets- und Außendienststeuerung.';
    return "Sehr geehrtes Recruiting-Team,\n\nIhre ausgeschriebene Position als {$title} bei {$company} hat mein Interesse geweckt. Die Aufgaben passen sehr gut zu meiner Erfahrung in der internationalen Vertriebsführung und der systematischen Weiterentwicklung kommerzieller Organisationen im Pharmabereich.\n\n{$middle}\n\nIn meinen jüngsten Funktionen war ich unter anderem für Vertriebsaktivitäten in Österreich, Tschechien, der Slowakei und im Vereinigten Königreich verantwortlich. Ich stehe für klare Prioritäten, partnerschaftliche Führung und eine messbare Umsetzung im Markt.\n\nGerne erläutere ich Ihnen in einem persönlichen Gespräch, welchen Beitrag ich bei {$company} leisten kann. Über eine Einladung zum Austausch freue ich mich.\n\nMit freundlichen Grüßen\nFrederick Samuel";
}
function gmailtoken(): string {
    static $cached=''; if($cached!=='')return $cached;
    $refresh=setting('gmail_refresh');$id=cfg('google_client_id');$secret=cfg('google_client_secret');
    if(!$refresh||!$id||!$secret)throw new RuntimeException('Gmail OAuth not yet connected');
    $c=curl_init('https://oauth2.googleapis.com/token');curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$id,'client_secret'=>$secret,'refresh_token'=>$refresh,'grant_type'=>'refresh_token']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);$r=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$data=json_decode((string)$r,true);
    if($code!==200||empty($data['access_token']))throw new RuntimeException('Gmail token refresh failed. Reconnect Gmail.');return $cached=$data['access_token'];
}
function gmailcall(string $method,string $route,?array $body=null): array {
    $url='https://gmail.googleapis.com/gmail/v1/users/me/'.$route;$c=curl_init($url);$headers=['Authorization: Bearer '.gmailtoken(),'Accept: application/json'];
    if($body!==null){$headers[]='Content-Type: application/json';curl_setopt($c,CURLOPT_POSTFIELDS,json_encode($body));}
    curl_setopt_array($c,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);$r=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);$d=json_decode((string)$r,true);
    if($status<200||$status>=300)throw new RuntimeException('Gmail HTTP '.$status.': '.($d['error']['message']??'unknown error'));return is_array($d)?$d:[];
}
function mailraw(string $to,string $subject,string $html,string $text,array $docs,?array $inline=null): string {
    $boundary='fs_'.bin2hex(random_bytes(12));$rel='rel_'.bin2hex(random_bytes(12));$sender='Frederick Samuel <fredericksamuel1965@gmail.com>';
    $raw='From: '.$sender."\r\nTo: ".$to."\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
    $raw.="--{$boundary}\r\nContent-Type: multipart/alternative; boundary=\"{$rel}\"\r\n\r\n";
    $raw.="--{$rel}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text));
    $raw.="--{$rel}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html));
    $raw.="--{$rel}--\r\n";
    if($inline && is_file($inline['path'])){$mime=$inline['mime'];$raw.="--{$boundary}\r\nContent-Type: {$mime}\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <fs-signature>\r\nContent-Disposition: inline\r\n\r\n".chunk_split(base64_encode(file_get_contents($inline['path'])));}
    foreach($docs as $d){$path=$d['path'];if(!is_file($path))continue;$filename=preg_replace('/[^A-Za-z0-9._-]/','_',basename($d['name']));$raw.="--{$boundary}\r\nContent-Type: {$d['mime']}; name=\"{$filename}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n".chunk_split(base64_encode(file_get_contents($path)));}
    $raw.="--{$boundary}--\r\n";return rtrim(strtr(base64_encode($raw),'+/','-_'),'=');
}
function pdftext(array $j,string $letter): string {
    $address=setting('address',"Marienburger Straße 16\n56112 Lahnstein\nDeutschland");$email='fredericksamuel1965@gmail.com';
    $lines=array_merge(['Frederick Samuel'],preg_split('/\R/u',$address),[$email,'',date('d.m.Y'),'',$j['company'],$j['title'],''],preg_split('/\R/u',$letter));
    $chunks=[];$page=[];
    foreach($lines as $line){
        $line=trim((string)$line);
        $wrapped=$line===''?['']:explode("\n",wordwrap($line,78,"\n",false));
        foreach($wrapped as $part){if(count($page)>=41){$chunks[]=$page;$page=[];}$page[]=$part;}
    }
    if($page)$chunks[]=$page;
    $objs=[null,'<< /Type /Catalog /Pages 2 0 R >>', '', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];$kids=[];
    foreach($chunks as $i=>$linesPage){
        $p=4+$i*2;$c=$p+1;$kids[]="{$p} 0 R";
        $y=751;$commands=["0.13 0.24 0.30 rg", "BT /F1 16 Tf 58 795 Td (FREDERICK SAMUEL) Tj ET", "0.72 0.47 0.34 RG 1 w 58 777 m 537 777 l S", "0.14 0.20 0.24 rg"];
        foreach($linesPage as $line){
            $line=iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$line);
            $line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);
            $commands[]="BT /F1 10.5 Tf 58 {$y} Td ({$line}) Tj ET";
            $y-=($line===''?17:16);
        }
        $commands[]="0.48 0.54 0.56 rg BT /F1 8 Tf 58 35 Td (Frederick Samuel  |  ".($i+1)." / ".count($chunks).") Tj ET";
        $stream=implode("\n",$commands)."\n";
        $objs[$p]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$c} 0 R >>";
        $objs[$c]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
    }
    $objs[2]='<< /Type /Pages /Kids ['.implode(' ',$kids).'] /Count '.count($chunks).' >>';
    ksort($objs);$out="%PDF-1.4\n";$offset=[0];$max=max(array_keys($objs));
    for($i=1;$i<=$max;$i++){$offset[$i]=strlen($out);$out.="{$i} 0 obj\n".$objs[$i]."\nendobj\n";}
    $start=strlen($out);$out.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
    for($i=1;$i<=$max;$i++)$out.=sprintf('%010d 00000 n ',$offset[$i])."\n";
    return $out."trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$start}\n%%EOF";
}
