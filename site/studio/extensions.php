<?php
declare(strict_types=1);

function ba_intelligence_status(): array {
    $endpoint=trim((string)(getenv('BOOK_AUTHOR_INTELLIGENCE_ENDPOINT') ?: ''));
    return [
        'bound'=>$endpoint!=='',
        'mode'=>$endpoint!==''?'external_adapter':'queue_only',
        'message'=>$endpoint!==''?'Production intelligence adapter is configured.':'Local parsing and structural analysis are active. Deep manuscript analysis and prose generation are queued until the production intelligence adapter is bound.'
    ];
}
function ba_safe_name(string $name): string {
    $name=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($name)) ?: 'manuscript';
    return trim($name,'._-') ?: 'manuscript';
}
function ba_words(string $text): int {
    if($text==='') return 0;
    preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}’\'\-]*/u',$text,$m);
    return count($m[0]??[]);
}
function ba_xml_text(string $xml,string $kind): string {
    if($kind==='docx'){
        $xml=preg_replace('/<w:tab\b[^>]*\/>/i',"\t",$xml)??$xml;
        $xml=preg_replace('/<w:br\b[^>]*\/>/i',"\n",$xml)??$xml;
        $xml=preg_replace('/<\/w:p>/i',"\n",$xml)??$xml;
    } else {
        $xml=preg_replace('/<\/text:p>/i',"\n",$xml)??$xml;
        $xml=preg_replace('/<text:line-break\b[^>]*\/>/i',"\n",$xml)??$xml;
    }
    $text=html_entity_decode(strip_tags($xml),ENT_QUOTES|ENT_XML1,'UTF-8');
    $text=preg_replace('/[ \t]+/u',' ',$text)??$text;
    $text=preg_replace('/\R{3,}/u',"\n\n",$text)??$text;
    return trim($text);
}
function ba_extract_text(string $path,string $ext): array {
    $ext=strtolower($ext);
    if(in_array($ext,['txt','md','markdown'],true)){
        $text=@file_get_contents($path);
        return $text===false?['status'=>'extract_failed','text'=>'','message'=>'Text file could not be read.']:['status'=>'parsed','text'=>trim($text),'message'=>'Plain-text manuscript extracted.'];
    }
    if(in_array($ext,['html','htm'],true)){
        $raw=@file_get_contents($path);
        if($raw===false) return ['status'=>'extract_failed','text'=>'','message'=>'HTML file could not be read.'];
        $raw=preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/li)>/i',"\n",$raw)??$raw;
        return ['status'=>'parsed','text'=>trim(html_entity_decode(strip_tags($raw),ENT_QUOTES|ENT_HTML5,'UTF-8')),'message'=>'HTML manuscript extracted.'];
    }
    if(in_array($ext,['docx','odt','epub'],true)){
        if(!class_exists('ZipArchive')) return ['status'=>'preserved_needs_extractor','text'=>'','message'=>'Original preserved; document ZIP parser is unavailable on this host.'];
        $zip=new ZipArchive();
        if($zip->open($path)!==true) return ['status'=>'extract_failed','text'=>'','message'=>'Document container could not be opened.'];
        $text='';
        if($ext==='docx'){
            $xml=$zip->getFromName('word/document.xml');
            if(is_string($xml)) $text=ba_xml_text($xml,'docx');
        } elseif($ext==='odt'){
            $xml=$zip->getFromName('content.xml');
            if(is_string($xml)) $text=ba_xml_text($xml,'odt');
        } else {
            $parts=[];
            for($i=0;$i<$zip->numFiles;$i++){
                $st=$zip->statIndex($i); $name=(string)($st['name']??'');
                if(preg_match('/\.(xhtml|html|htm)$/i',$name)){
                    $raw=$zip->getFromIndex($i);
                    if(is_string($raw)){
                        $raw=preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/li)>/i',"\n",$raw)??$raw;
                        $parts[$name]=trim(html_entity_decode(strip_tags($raw),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                    }
                }
            }
            ksort($parts,SORT_NATURAL|SORT_FLAG_CASE);
            $text=trim(implode("\n\n",$parts));
        }
        $zip->close();
        return $text===''?['status'=>'preserved_needs_extractor','text'=>'','message'=>'Original preserved; manuscript text could not be extracted from this document.']:['status'=>'parsed','text'=>$text,'message'=>strtoupper($ext).' manuscript extracted.'];
    }
    if($ext==='pdf') return ['status'=>'preserved_needs_extractor','text'=>'','message'=>'PDF original preserved. Text extraction is delegated to the document-intelligence worker so page order and layout can be handled safely.'];
    return ['status'=>'unsupported','text'=>'','message'=>'Unsupported format.'];
}
function ba_entities(string $text): array {
    if($text==='') return [];
    preg_match_all('/\b[\p{Lu}][\p{L}\p{M}’\'\-]{2,}(?:\s+[\p{Lu}][\p{L}\p{M}’\'\-]{2,})?\b/u',$text,$m);
    $stop=array_fill_keys(['The','This','That','There','Then','When','Where','What','Which','Chapter','Part','Book','She','He','They','Her','His','Their','You','Your','But','And','For','From','With','Into','After','Before','Without','Under','Over','Der','Die','Das','Und','Aber','Kapitel'],true);
    $counts=[];
    foreach(($m[0]??[]) as $name){
        $name=trim(preg_replace('/\s+/u',' ',$name)??$name);
        if(isset($stop[$name])||mb_strlen($name)>60) continue;
        $counts[$name]=($counts[$name]??0)+1;
    }
    arsort($counts); $out=[];
    foreach($counts as $name=>$mentions){ if($mentions<3) continue; $out[]=['name'=>$name,'mentions'=>$mentions]; if(count($out)>=20) break; }
    return $out;
}
function ba_chapters(string $text): array {
    $lines=preg_split('/\R/u',$text)?:[]; $out=[]; $title='Opening'; $buf=[];
    $pat='/^\s*(chapter|kapitel|part|book|prologue|epilogue|глава|часть)\b.{0,80}$/iu';
    foreach($lines as $line){
        $t=trim($line);
        if($t!==''&&preg_match($pat,$t)){
            if($buf) $out[]=['title'=>$title,'words'=>ba_words(implode("\n",$buf))];
            $title=$t; $buf=[];
        } else $buf[]=$line;
    }
    if($buf) $out[]=['title'=>$title,'words'=>ba_words(implode("\n",$buf))];
    if(count($out)===1&&$out[0]['title']==='Opening') return [];
    return $out;
}
function ba_scan(string $text): array {
    $wc=ba_words($text);
    $pars=array_values(array_filter(array_map('trim',preg_split('/\R{1,}/u',$text)?:[]),fn($x)=>$x!==''));
    $chapters=ba_chapters($text);
    preg_match_all('/[“„"][^”“"\n]{8,}[”“"]/u',$text,$dm);
    $dw=ba_words(implode(' ',$dm[0]??[]));
    $ratio=$wc>0?(int)round(($dw/$wc)*100):0;
    $avg=count($pars)?(int)round($wc/count($pars)):0;
    $find=[];
    if(!$chapters) $find[]=['level'=>'observation','kind'=>'Structure','title'=>'Explicit chapter headings not detected','detail'=>'Semantic segmentation will determine chapter and scene boundaries instead of relying on headings.'];
    else $find[]=['level'=>'good','kind'=>'Structure','title'=>count($chapters).' chapter boundaries detected','detail'=>'These boundaries seed scene and passage reconstruction.'];
    if($avg>120) $find[]=['level'=>'watch','kind'=>'Readability','title'=>'Dense paragraph pattern detected','detail'=>'Average extracted paragraph length is about '.$avg.' words. The editorial pass should determine whether this density is deliberate or reduces readability.'];
    if($ratio<3&&$wc>10000) $find[]=['level'=>'observation','kind'=>'Dialogue','title'=>'Low dialogue signal','detail'=>'Quoted dialogue appears to make up roughly '.$ratio.'% of extracted words. This is context for pacing analysis, not automatically a weakness.'];
    if($chapters){
        $lens=array_column($chapters,'words'); sort($lens); $median=$lens[(int)floor((count($lens)-1)/2)]?:1;
        if(max($lens)>$median*2.2) $find[]=['level'=>'watch','kind'=>'Pacing','title'=>'Large chapter-length variance','detail'=>'The longest detected chapter is more than twice the median chapter length. The pacing engine should inspect whether it is overloaded or intentionally expansive.'];
    }
    return ['word_count'=>$wc,'character_count'=>mb_strlen($text),'paragraph_count'=>count($pars),'chapter_estimate'=>count($chapters),'dialogue_ratio'=>$ratio,'avg_paragraph_words'=>$avg,'chapter_lengths'=>array_slice($chapters,0,80),'entity_candidates'=>ba_entities($text),'findings'=>$find];
}
function ba_extended_api(array &$s,string $stateFile,string $method,string $path,string $dataDir): void {
    $s['imports']=$s['imports']??[]; $s['analysis_jobs']=$s['analysis_jobs']??[]; $s['write_jobs']=$s['write_jobs']??[];
    if($method==='GET'&&$path==='intelligence/status') respond(ba_intelligence_status());
    if($method==='GET'&&preg_match('#^projects/(\d+)/imports$#',$path,$m)){
        $pid=(int)$m[1]; respond(['items'=>array_values(array_filter($s['imports'],fn($x)=>(int)($x['project_id']??0)===$pid))]);
    }
    if($method==='POST'&&preg_match('#^projects/(\d+)/import-manuscript$#',$path,$m)){
        $pid=(int)$m[1];
        if(!isset($_FILES['manuscript'])||!is_array($_FILES['manuscript'])) respond(['error'=>'manuscript_file_required'],422);
        $f=$_FILES['manuscript']; if((int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) respond(['error'=>'upload_failed','code'=>(int)($f['error']??-1)],422);
        $size=(int)($f['size']??0); if($size<=0||$size>25*1024*1024) respond(['error'=>'file_size_out_of_range','max_bytes'=>25*1024*1024],422);
        $original=(string)($f['name']??'manuscript'); $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        $allowed=['docx','txt','md','markdown','html','htm','odt','epub','pdf']; if(!in_array($ext,$allowed,true)) respond(['error'=>'unsupported_format','allowed'=>$allowed],422);
        $dir=rtrim($dataDir,'/').'/imports/project_'.$pid; if(!is_dir($dir)&&!@mkdir($dir,0770,true)&&!is_dir($dir)) respond(['error'=>'import_storage_unavailable'],500);
        $id=maxId($s['imports'])+1; $stored=gmdate('Ymd_His').'_'.$id.'_'.bin2hex(random_bytes(4)).'_'.ba_safe_name($original); $dest=$dir.'/'.$stored;
        if(!move_uploaded_file((string)$f['tmp_name'],$dest)) respond(['error'=>'upload_commit_failed'],500); @chmod($dest,0660);
        $sha=hash_file('sha256',$dest)?:''; $ex=ba_extract_text($dest,$ext); $text=(string)$ex['text']; $scan=ba_scan($text); $textFile=null;
        if($text!==''){ $textFile=$stored.'.extracted.txt'; @file_put_contents($dir.'/'.$textFile,$text,LOCK_EX); @chmod($dir.'/'.$textFile,0660); }
        $depth=(string)($_POST['optimization_depth']??'editorial'); if(!in_array($depth,['conservative','editorial','developmental'],true)) $depth='editorial';
        $scopes=json_decode((string)($_POST['scopes']??'[]'),true); if(!is_array($scopes)) $scopes=[];
        $intel=ba_intelligence_status(); $aid=maxId($s['analysis_jobs'])+1;
        $astatus=$text!==''?($intel['bound']?'ready_for_dispatch':'queued'):'waiting_for_text_extraction';
        $amsg=$text!==''?($intel['bound']?'Structural scan complete; deep whole-book analysis is ready for dispatch.':'Structural scan complete. Whole-book character, plot, congruency, pacing and optimization analysis is queued for the production intelligence adapter.'):(string)$ex['message'];
        $rec=['id'=>$id,'project_id'=>$pid,'original_name'=>$original,'stored_name'=>$stored,'extension'=>$ext,'bytes'=>$size,'sha256'=>$sha,'created_at'=>nowIso(),'status'=>(string)$ex['status'],'extraction_message'=>(string)$ex['message'],'extracted_text_file'=>$textFile,'optimization_depth'=>$depth,'scopes'=>array_values($scopes),'structural_scan'=>$scan,'analysis_job_id'=>$aid,'analysis_status'=>$astatus,'analysis_message'=>$amsg,'immutable_original'=>true];
        $s['imports'][]=$rec;
        $s['analysis_jobs'][]=['id'=>$aid,'project_id'=>$pid,'import_id'=>$id,'status'=>$astatus,'optimization_depth'=>$depth,'scopes'=>array_values($scopes),'pipeline'=>['segment','chapter_extract','character_relationship_graph','plot_threads','timeline_congruency','structure_pacing','style_profile','reader_experience','historical_checks','optimization_map'],'created_at'=>nowIso(),'message'=>$amsg];
        audit($s,$pid,'manuscript.imported','manuscript_import',$id,['original_name'=>$original,'sha256'=>$sha,'bytes'=>$size,'status'=>$rec['status'],'analysis_job_id'=>$aid]); saveState($stateFile,$s);
        respond(['ok'=>true,'import'=>$rec,'analysis_job'=>$s['analysis_jobs'][count($s['analysis_jobs'])-1]]);
    }
    if($method==='GET'&&preg_match('#^projects/(\d+)/analysis-jobs$#',$path,$m)){
        $pid=(int)$m[1]; respond(['items'=>array_values(array_filter($s['analysis_jobs'],fn($x)=>(int)($x['project_id']??0)===$pid))]);
    }
    if($method==='GET'&&preg_match('#^projects/(\d+)/write-jobs$#',$path,$m)){
        $pid=(int)$m[1]; respond(['items'=>array_values(array_filter($s['write_jobs'],fn($x)=>(int)($x['project_id']??0)===$pid))]);
    }
    if($method==='POST'&&preg_match('#^projects/(\d+)/write-jobs$#',$path,$m)){
        $pid=(int)$m[1]; $b=bodyJson(); $outline=trim((string)($b['outline']??'')); if($outline==='') respond(['error'=>'outline_required'],422);
        $target=(int)($b['target_words']??0); if($target<500||$target>10000) respond(['error'=>'target_words_out_of_range','min'=>500,'max'=>10000],422);
        $intel=ba_intelligence_status(); $id=maxId($s['write_jobs'])+1; $status=$intel['bound']?'ready_for_dispatch':'queued_for_intelligence';
        $message=$intel['bound']?'Chapter contract created and ready for the production intelligence adapter.':'Chapter contract created. Outline, word-count constraint, style profile and canonical context are preserved; prose generation will execute when the production intelligence adapter is bound.';
        $ctx=is_array($b['context_flags']??null)?array_values($b['context_flags']):[]; $guards=is_array($b['guardrails']??null)?array_values($b['guardrails']):[];
        $job=['id'=>$id,'project_id'=>$pid,'chapter_id'=>(int)($b['chapter_id']??0),'target_words'=>$target,'outline'=>$outline,'pov'=>(string)($b['pov']??'Use book canon'),'tense'=>(string)($b['tense']??'Use book canon'),'style_source'=>(string)($b['style_source']??'Use approved book style profile'),'research_policy'=>(string)($b['research_policy']??'Respect verified facts; flag unknowns'),'instructions'=>(string)($b['instructions']??''),'context_flags'=>$ctx,'guardrails'=>$guards,'status'=>$status,'message'=>$message,'created_at'=>nowIso(),'output_passage_id'=>null,'output_revision'=>null,'generation_contract'=>['outline_authoritative'=>true,'target_word_count'=>$target,'word_count_tolerance_percent'=>8,'preserve_book_style'=>true,'preserve_character_voice'=>true,'use_story_graph'=>in_array('story_graph',$ctx,true),'use_continuity'=>in_array('continuity',$ctx,true),'never_overwrite_approved_text'=>true,'result_requires_author_approval'=>true]];
        $s['write_jobs'][]=$job; audit($s,$pid,'write_job.created','write_job',$id,['chapter_id'=>$job['chapter_id'],'target_words'=>$target,'status'=>$status]); saveState($stateFile,$s); respond(['ok'=>true,'job'=>$job,'message'=>$message]);
    }
}
