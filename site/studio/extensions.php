<?php
declare(strict_types=1);
require_once __DIR__ . '/bridge.php';

function ba_intelligence_status(): array {
    $health=ba_core_health();
    $reachable=(bool)($health['ok']??false);
    $bound=$reachable && (bool)($health['compute_bound']??false);
    return [
        'bound'=>$bound,
        'reachable'=>$reachable,
        'mode'=>'noeva_local_intelligence',
        'adapter'=>'NOEVA CORE → local compute → Ollama',
        'pricing'=>'LOCAL_NO_API_FEES',
        'capable_nodes'=>$health['capable_nodes']??[],
        'message'=>$bound
            ? 'NOEVA local intelligence is bound. Manuscript analysis and chapter writing run on the local compute cluster without a paid model API.'
            : ($reachable
                ? 'NOEVA CORE is reachable; waiting for a compute node to advertise the Book Author local-intelligence tasks.'
                : 'Local parsing is active. NOEVA CORE local-intelligence binding is temporarily unavailable.')
    ];
}
function ba_safe_name(string $name): string {
    $name=preg_replace('/[^A-Za-z0-9._-]+/','_',basename($name)) ?: 'manuscript';
    return trim($name,'._-') ?: 'manuscript';
}
function ba_import_title(string $name): string {
    $base=pathinfo(basename($name),PATHINFO_FILENAME);
    $base=preg_replace('/[_-]+/u',' ',$base)??$base;
    $base=preg_replace('/\s+(published|publication|final|manuscript|proof|print)\s*$/iu','',$base)??$base;
    $base=preg_replace('/\s+/u',' ',trim($base))??trim($base);
    return $base!==''?$base:'Imported manuscript';
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
        return $text===false
            ? ['status'=>'extract_failed','text'=>'','message'=>'Text file could not be read.']
            : ['status'=>'parsed','text'=>trim($text),'message'=>'Plain-text manuscript extracted.'];
    }
    if(in_array($ext,['html','htm'],true)){
        $raw=@file_get_contents($path);
        if($raw===false) return ['status'=>'extract_failed','text'=>'','message'=>'HTML file could not be read.'];
        $raw=preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/li)>/i',"\n",$raw)??$raw;
        return ['status'=>'parsed','text'=>trim(html_entity_decode(strip_tags($raw),ENT_QUOTES|ENT_HTML5,'UTF-8')),'message'=>'HTML manuscript extracted.'];
    }
    if(in_array($ext,['docx','odt','epub'],true)){
        if(!class_exists('ZipArchive')){
            return ['status'=>'preserved_needs_extractor','text'=>'','message'=>'Original preserved; the ZIP document parser is unavailable on this PHP host.'];
        }
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
        return $text===''
            ? ['status'=>'preserved_needs_extractor','text'=>'','message'=>'Original preserved; manuscript text could not be extracted from this document.']
            : ['status'=>'parsed','text'=>$text,'message'=>strtoupper($ext).' manuscript extracted.'];
    }
    if($ext==='pdf'){
        return ['status'=>'preserved_needs_extractor','text'=>'','message'=>'PDF original preserved. Text extraction is delegated to the document-intelligence worker.'];
    }
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
    foreach($counts as $name=>$mentions){
        if($mentions<3) continue;
        $out[]=['name'=>$name,'mentions'=>$mentions];
        if(count($out)>=20) break;
    }
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
function ba_find_index(array $rows,int $id): int {
    foreach($rows as $i=>$row){ if((int)($row['id']??0)===$id) return (int)$i; }
    return -1;
}
function ba_sanitize_editorial_feedback(mixed $value): array {
    if(!is_array($value)) return [];
    $allowed=['short_sentences','fragments','philosophical_narration','philosophical_dialogue','metaphoric_language','explicit_emotion','rhetorical_questions'];
    $out=[];
    foreach($value as $row){
        if(!is_array($row)) continue;
        $dimension=trim((string)($row['dimension']??''));
        $direction=strtolower(trim((string)($row['direction']??'')));
        $decision=strtolower(trim((string)($row['decision']??'')));
        if(!in_array($dimension,$allowed,true)||!in_array($direction,['increase','decrease'],true)||!in_array($decision,['accepted','rejected'],true)) continue;
        $weight=(float)($row['weight']??1.0); $weight=max(0.25,min(2.0,$weight));
        $feedbackId=trim((string)($row['feedback_id']??''));
        if(!preg_match('/^[A-Za-z0-9_-]{8,80}$/',$feedbackId)) $feedbackId=bin2hex(random_bytes(8));
        $claimed=(int)($row['claimed_by_job_id']??0);
        $out[]=[
            'feedback_id'=>$feedbackId,
            'dimension'=>$dimension,
            'direction'=>$direction,
            'decision'=>$decision,
            'weight'=>$weight,
            'recorded_at'=>(string)($row['recorded_at']??nowIso()),
            'claimed_by_job_id'=>$claimed>0?$claimed:null,
        ];
        if(count($out)>=50) break;
    }
    return $out;
}
function ba_feedback_claim(array &$projectLi,int $jobId,array $direct=[]): array {
    $pending=ba_sanitize_editorial_feedback($projectLi['pending_feedback']??[]);
    foreach(ba_sanitize_editorial_feedback($direct) as $row){
        $row['claimed_by_job_id']=null;
        $pending[]=$row;
    }
    $seen=[]; $dedup=[];
    foreach($pending as $row){
        $fid=(string)($row['feedback_id']??'');
        if($fid===''||isset($seen[$fid])) continue;
        $seen[$fid]=true; $dedup[]=$row;
    }
    $claimed=[];
    foreach($dedup as &$row){
        if(empty($row['claimed_by_job_id'])){
            $row['claimed_by_job_id']=$jobId;
            $claimed[]=$row;
        }
    }
    unset($row);
    $projectLi['pending_feedback']=array_slice($dedup,-50);
    return $claimed;
}
function ba_feedback_resolve(array &$projectLi,int $jobId,bool $consume): void {
    $pending=ba_sanitize_editorial_feedback($projectLi['pending_feedback']??[]);
    $next=[];
    foreach($pending as $row){
        $claimed=(int)($row['claimed_by_job_id']??0);
        if($claimed!==$jobId){ $next[]=$row; continue; }
        if(!$consume){
            $row['claimed_by_job_id']=null;
            $next[]=$row;
        }
    }
    $projectLi['pending_feedback']=array_slice($next,-50);
}
function ba_json_from_string(string $value): ?array {
    $value=trim($value);
    if($value==='') return null;
    $whole=json_decode($value,true);
    if(is_array($whole) && (isset($whole['kind'])||isset($whole['optimization_map'])||isset($whole['draft']))) return $whole;
    $lines=preg_split('/\R/u',$value)?:[];
    for($i=count($lines)-1;$i>=0;$i--){
        $line=trim($lines[$i]);
        if($line==='' || ($line[0]??'')!=='{') continue;
        $decoded=json_decode($line,true);
        if(is_array($decoded) && (isset($decoded['kind'])||isset($decoded['optimization_map'])||isset($decoded['draft']))) return $decoded;
    }
    return null;
}
function ba_extract_task_payload(mixed $value,int $depth=0): ?array {
    if($depth>6) return null;
    if(is_string($value)) return ba_json_from_string($value);
    if(!is_array($value)) return null;
    if(isset($value['kind'])||isset($value['optimization_map'])||isset($value['draft'])) return $value;
    foreach(['stdout','output','result','payload','message'] as $key){
        if(array_key_exists($key,$value)){
            $found=ba_extract_task_payload($value[$key],$depth+1);
            if($found) return $found;
        }
    }
    foreach($value as $child){
        if(is_array($child)||is_string($child)){
            $found=ba_extract_task_payload($child,$depth+1);
            if($found) return $found;
        }
    }
    return null;
}
function ba_source_url(string $kind,int $id,string $token): string {
    return 'https://studio.fredericksamuel.com/worker.php?kind='.rawurlencode($kind).'&id='.$id.'&token='.rawurlencode($token);
}
function ba_dispatch_analysis(array &$s,int $jobIndex,string $stateFile): void {
    $job=$s['analysis_jobs'][$jobIndex]??null;
    if(!$job || empty($job['worker_token_pending'])) return;
    $importId=(int)($job['import_id']??0); if($importId<1) return;
    $token=(string)$job['worker_token_pending'];
    $reply=ba_core_submit('analyze',ba_source_url('analysis',$importId,$token),'analysis',(int)($job['project_id']??1),$importId);
    if(($reply['ok']??false) && !empty($reply['job']['id'])){
        $s['analysis_jobs'][$jobIndex]['core_job_id']=(string)$reply['job']['id'];
        $s['analysis_jobs'][$jobIndex]['status']='queued_local';
        $s['analysis_jobs'][$jobIndex]['message']='Structural scan complete. Deep whole-book analysis queued on NOEVA local compute.';
        unset($s['analysis_jobs'][$jobIndex]['worker_token_pending']);
        $ii=ba_find_index($s['imports'],$importId);
        if($ii>=0){
            $s['imports'][$ii]['analysis_status']='queued_local';
            $s['imports'][$ii]['analysis_message']=$s['analysis_jobs'][$jobIndex]['message'];
            $s['imports'][$ii]['core_job_id']=(string)$reply['job']['id'];
        }
        saveState($stateFile,$s);
    } else {
        $s['analysis_jobs'][$jobIndex]['status']='dispatch_pending';
        $s['analysis_jobs'][$jobIndex]['message']='Manuscript preserved and parsed. Waiting for NOEVA CORE local-intelligence dispatch.';
        saveState($stateFile,$s);
    }
}
function ba_dispatch_write(array &$s,int $jobIndex,string $stateFile): void {
    $job=$s['write_jobs'][$jobIndex]??null;
    if(!$job || empty($job['worker_token_pending'])) return;
    $id=(int)($job['id']??0); if($id<1) return;
    $token=(string)$job['worker_token_pending'];
    $reply=ba_core_submit('write',ba_source_url('write',$id,$token),'write',(int)($job['project_id']??1),$id);
    if(($reply['ok']??false) && !empty($reply['job']['id'])){
        $s['write_jobs'][$jobIndex]['core_job_id']=(string)$reply['job']['id'];
        $s['write_jobs'][$jobIndex]['status']='queued_local';
        $s['write_jobs'][$jobIndex]['message']='Chapter contract queued on NOEVA local compute.';
        unset($s['write_jobs'][$jobIndex]['worker_token_pending']);
        saveState($stateFile,$s);
    } else {
        $s['write_jobs'][$jobIndex]['status']='dispatch_pending';
        $s['write_jobs'][$jobIndex]['message']='Chapter contract preserved. Waiting for NOEVA CORE local-intelligence dispatch.';
        saveState($stateFile,$s);
    }
}
function ba_sync_core_jobs(array &$s,string $stateFile): void {
    $changed=false;
    $health=ba_core_health();
    $computeBound=(bool)($health['ok']??false) && (bool)($health['compute_bound']??false);
    $capableNodes=is_array($health['capable_nodes']??null)?array_values($health['capable_nodes']):[];
    foreach($s['analysis_jobs']??[] as $i=>$job){
        if(($job['status']??'')==='dispatch_pending' && !empty($job['worker_token_pending'])){
            ba_dispatch_analysis($s,(int)$i,$stateFile);
            $job=$s['analysis_jobs'][$i];
        }
        $coreId=(string)($job['core_job_id']??'');
        if($coreId==='' || in_array(($job['status']??''),['completed','failed'],true)) continue;
        $reply=ba_core_status($coreId);
        if(!($reply['ok']??false) || !is_array($reply['job']??null)) continue;
        $core=$reply['job']; $status=strtoupper((string)($core['status']??''));
        if($status==='QUEUED'){
            $s['analysis_jobs'][$i]['status']='queued_local';
            $s['analysis_jobs'][$i]['message']=$computeBound
                ? 'Queued on NOEVA local compute; waiting for an available Book Author slot.'
                : 'Queued safely. Waiting for a Book Author-capable NOEVA compute node to come online.';
            $s['analysis_jobs'][$i]['capable_nodes']=$capableNodes;
            $changed=true;
        } elseif($status==='LEASED'){
            $s['analysis_jobs'][$i]['status']='running_local';
            $s['analysis_jobs'][$i]['message']='Whole-book analysis is running on NOEVA local compute.';
            $changed=true;
        } elseif($status==='COMPLETED'){
            $payload=ba_extract_task_payload($core['result']??[]);
            $s['analysis_jobs'][$i]['status']='completed';
            $s['analysis_jobs'][$i]['completed_at']=$core['completed_at']??nowIso();
            $s['analysis_jobs'][$i]['core_result']=$core['result']??[];
            if($payload){
                $s['analysis_jobs'][$i]['deep_analysis']=$payload;
                $s['analysis_jobs'][$i]['message']='Whole-book optimization analysis completed on NOEVA local compute.';
            } else {
                $s['analysis_jobs'][$i]['message']='Local analysis completed; structured result is available in the compute audit payload.';
            }
            $s['analysis_jobs'][$i]['worker_token_hash']=null;
            $importId=(int)($job['import_id']??0); $ii=ba_find_index($s['imports'],$importId);
            if($ii>=0){
                $s['imports'][$ii]['analysis_status']='completed';
                $s['imports'][$ii]['analysis_message']=$s['analysis_jobs'][$i]['message'];
                if($payload) $s['imports'][$ii]['deep_analysis']=$payload;
            }
            $changed=true;
        } elseif($status==='FAILED'){
            $s['analysis_jobs'][$i]['status']='failed';
            $s['analysis_jobs'][$i]['message']='NOEVA local analysis failed. The original manuscript remains preserved and can be retried.';
            $s['analysis_jobs'][$i]['core_result']=$core['result']??[];
            $changed=true;
        }
    }
    foreach($s['write_jobs']??[] as $i=>$job){
        if(($job['status']??'')==='dispatch_pending' && !empty($job['worker_token_pending'])){
            ba_dispatch_write($s,(int)$i,$stateFile);
            $job=$s['write_jobs'][$i];
        }
        $coreId=(string)($job['core_job_id']??'');
        if($coreId==='' || in_array(($job['status']??''),['draft_ready','failed'],true)) continue;
        $reply=ba_core_status($coreId);
        if(!($reply['ok']??false) || !is_array($reply['job']??null)) continue;
        $core=$reply['job']; $status=strtoupper((string)($core['status']??''));
        if($status==='QUEUED'){
            $s['write_jobs'][$i]['status']='queued_local';
            $s['write_jobs'][$i]['message']=$computeBound
                ? 'Queued on NOEVA local compute; waiting for an available Book Author slot.'
                : 'Queued safely. Waiting for a Book Author-capable NOEVA compute node to come online.';
            $s['write_jobs'][$i]['capable_nodes']=$capableNodes;
            $changed=true;
        } elseif($status==='LEASED'){
            $s['write_jobs'][$i]['status']='writing_local';
            $s['write_jobs'][$i]['message']='Chapter draft is being written on NOEVA local compute.';
            $changed=true;
        } elseif($status==='COMPLETED'){
            $payload=ba_extract_task_payload($core['result']??[]);
            $s['write_jobs'][$i]['core_result']=$core['result']??[];
            $s['write_jobs'][$i]['completed_at']=$core['completed_at']??nowIso();
            $pid=(int)($job['project_id']??1); $key=(string)$pid;
            $projectLi=is_array($s['literary_intelligence_by_project'][$key]??null)?$s['literary_intelligence_by_project'][$key]:[];
            if($payload && isset($payload['draft'])){
                $s['write_jobs'][$i]['status']='draft_ready';
                $s['write_jobs'][$i]['generated_draft']=(string)$payload['draft'];
                $s['write_jobs'][$i]['generated_word_count']=(int)($payload['word_count']??ba_words((string)$payload['draft']));
                $s['write_jobs'][$i]['model']=$payload['model']??null;
                $li=is_array($payload['literary_intelligence']??null)?$payload['literary_intelligence']:[];
                if($li){
                    $s['write_jobs'][$i]['literary_intelligence']=$li;
                    if(is_array($li['author_style_memory_next']??null)) $projectLi['author_style_memory']=$li['author_style_memory_next'];
                    if(is_array($li['style_profile_next']??null)) $projectLi['style_profile']=$li['style_profile_next'];
                    elseif(is_array($li['style_control']??null)) $projectLi['style_profile']=$li['style_control'];
                    if(is_array($li['recent_patterns_next']??null)) $projectLi['recent_patterns']=array_slice(array_values($li['recent_patterns_next']),-30);
                    if(is_array($li['protected_motifs']??null)) $projectLi['protected_motifs']=array_slice(array_values($li['protected_motifs']),-30);
                    if(is_array($li['learning_event']??null)){
                        $events=is_array($projectLi['learning_events']??null)?$projectLi['learning_events']:[];
                        $event=$li['learning_event']; $event['recorded_at']=nowIso(); $event['write_job_id']=(int)($job['id']??0);
                        $events[]=$event;
                        $projectLi['learning_events']=array_slice($events,-200);
                    }
                    ba_feedback_resolve($projectLi,(int)($job['id']??0),true);
                } else {
                    ba_feedback_resolve($projectLi,(int)($job['id']??0),false);
                }
                $projectLi['updated_at']=nowIso();
                $s['literary_intelligence_by_project'][$key]=$projectLi;
                $s['write_jobs'][$i]['message']='Draft ready for author review. Existing manuscript text has not been overwritten.';
            } else {
                $s['write_jobs'][$i]['status']='failed';
                $s['write_jobs'][$i]['message']='Local writing task completed without a usable draft payload.';
                ba_feedback_resolve($projectLi,(int)($job['id']??0),false);
                $s['literary_intelligence_by_project'][$key]=$projectLi;
            }
            $s['write_jobs'][$i]['worker_token_hash']=null;
            $changed=true;
        } elseif($status==='FAILED'){
            $s['write_jobs'][$i]['status']='failed';
            $s['write_jobs'][$i]['message']='NOEVA local writing task failed. No manuscript revision was changed.';
            $s['write_jobs'][$i]['core_result']=$core['result']??[];
            $pid=(int)($job['project_id']??1); $key=(string)$pid;
            $projectLi=is_array($s['literary_intelligence_by_project'][$key]??null)?$s['literary_intelligence_by_project'][$key]:[];
            ba_feedback_resolve($projectLi,(int)($job['id']??0),false);
            $s['literary_intelligence_by_project'][$key]=$projectLi;
            $changed=true;
        }
    }
    if($changed) saveState($stateFile,$s);
}
function ba_extended_api(array &$s,string $stateFile,string $method,string $path,string $dataDir): void {
    $s['imports']=$s['imports']??[]; $s['analysis_jobs']=$s['analysis_jobs']??[]; $s['write_jobs']=$s['write_jobs']??[];
    if($method==='GET'&&$path==='intelligence/status') respond(ba_intelligence_status());

    $s['literary_intelligence_by_project']=is_array($s['literary_intelligence_by_project']??null)?$s['literary_intelligence_by_project']:[];
    if($method==='GET'&&$path==='intelligence/status') respond(ba_intelligence_status());

    if($method==='GET'&&preg_match('#^projects/(\d+)/literary-intelligence$#',$path,$m)){
        $pid=(int)$m[1]; $key=(string)$pid;
        respond(['ok'=>true,'literary_intelligence'=>$s['literary_intelligence_by_project'][$key]??[
            'author_style_memory'=>[],'style_profile'=>[],'pending_feedback'=>[],'learning_events'=>[]
        ]]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/literary-intelligence/feedback$#',$path,$m)){
        $pid=(int)$m[1]; $key=(string)$pid; $b=bodyJson();
        $feedback=ba_sanitize_editorial_feedback($b['feedback']??[]);
        if(!$feedback) respond(['error'=>'valid_feedback_required'],422);
        $li=is_array($s['literary_intelligence_by_project'][$key]??null)?$s['literary_intelligence_by_project'][$key]:[];
        $pending=ba_sanitize_editorial_feedback($li['pending_feedback']??[]);
        foreach($feedback as &$row) $row['claimed_by_job_id']=null;
        unset($row);
        $li['pending_feedback']=array_slice(array_merge($pending,$feedback),-50);
        $li['updated_at']=nowIso();
        $s['literary_intelligence_by_project'][$key]=$li;
        audit($s,'literary_intelligence.feedback','project',$pid,['project_id'=>$pid,'items'=>count($feedback)]);
        saveState($stateFile,$s);
        respond(['ok'=>true,'pending_feedback'=>$li['pending_feedback']]);
    }

    if($method==='GET'&&preg_match('#^projects/(\d+)/imports$#',$path,$m)){
        ba_sync_core_jobs($s,$stateFile);
        $pid=(int)$m[1];
        $items=array_values(array_filter($s['imports'],fn($x)=>(int)($x['project_id']??0)===$pid));
        foreach($items as &$item){
            if(empty($item['display_title'])) $item['display_title']=ba_import_title((string)($item['original_name']??'Imported manuscript'));
        }
        unset($item);
        respond(['items'=>$items]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/import-manuscript$#',$path,$m)){
        $pid=(int)$m[1];
        if(!isset($_FILES['manuscript'])||!is_array($_FILES['manuscript'])) respond(['error'=>'manuscript_file_required'],422);
        $f=$_FILES['manuscript'];
        if((int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) respond(['error'=>'upload_failed','code'=>(int)($f['error']??-1)],422);
        $size=(int)($f['size']??0);
        if($size<=0||$size>25*1024*1024) respond(['error'=>'file_size_out_of_range','max_bytes'=>25*1024*1024],422);
        $original=(string)($f['name']??'manuscript'); $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));
        $allowed=['docx','txt','md','markdown','html','htm','odt','epub','pdf'];
        if(!in_array($ext,$allowed,true)) respond(['error'=>'unsupported_format','allowed'=>$allowed],422);

        $dir=rtrim($dataDir,'/').'/imports/project_'.$pid;
        if(!is_dir($dir)&&!@mkdir($dir,0770,true)&&!is_dir($dir)) respond(['error'=>'import_storage_unavailable'],500);
        $id=maxId($s['imports'])+1;
        $stored=gmdate('Ymd_His').'_'.$id.'_'.bin2hex(random_bytes(4)).'_'.ba_safe_name($original);
        $dest=$dir.'/'.$stored;
        if(!move_uploaded_file((string)$f['tmp_name'],$dest)) respond(['error'=>'upload_commit_failed'],500);
        @chmod($dest,0660);

        $sha=hash_file('sha256',$dest)?:'';
        foreach($s['imports'] as $existing){
            if((int)($existing['project_id']??0)!==$pid || (string)($existing['sha256']??'')!==$sha) continue;
            @unlink($dest);
            if(empty($existing['display_title'])) $existing['display_title']=ba_import_title((string)($existing['original_name']??$original));
            $existingJob=null;
            foreach($s['analysis_jobs']??[] as $candidate){
                if((int)($candidate['import_id']??0)===(int)($existing['id']??0)){ $existingJob=$candidate; break; }
            }
            respond(['ok'=>true,'duplicate'=>true,'import'=>$existing,'analysis_job'=>$existingJob,'message'=>'This manuscript is already in the library; the existing import was opened instead.']);
        }
        $ex=ba_extract_text($dest,$ext); $text=(string)$ex['text']; $scan=ba_scan($text); $textFile=null;
        if($text!==''){
            $textFile=$stored.'.extracted.txt';
            @file_put_contents($dir.'/'.$textFile,$text,LOCK_EX); @chmod($dir.'/'.$textFile,0660);
        }
        $depth=(string)($_POST['optimization_depth']??'editorial');
        if(!in_array($depth,['conservative','editorial','developmental'],true)) $depth='editorial';
        $scopes=json_decode((string)($_POST['scopes']??'[]'),true); if(!is_array($scopes)) $scopes=[];
        $aid=maxId($s['analysis_jobs'])+1;
        $workerToken=bin2hex(random_bytes(32));
        $workerHash=hash('sha256',$workerToken);
        $astatus=$text!==''?'dispatch_pending':'waiting_for_text_extraction';
        $amsg=$text!==''
            ? 'Original preserved and structural scan complete. Preparing NOEVA local whole-book analysis.'
            : (string)$ex['message'];

        $rec=['id'=>$id,'project_id'=>$pid,'display_title'=>ba_import_title($original),'original_name'=>$original,'stored_name'=>$stored,'extension'=>$ext,'bytes'=>$size,'sha256'=>$sha,'created_at'=>nowIso(),'status'=>(string)$ex['status'],'extraction_message'=>(string)$ex['message'],'extracted_text_file'=>$textFile,'optimization_depth'=>$depth,'scopes'=>array_values($scopes),'structural_scan'=>$scan,'analysis_job_id'=>$aid,'analysis_status'=>$astatus,'analysis_message'=>$amsg,'immutable_original'=>true];
        $job=['id'=>$aid,'project_id'=>$pid,'import_id'=>$id,'status'=>$astatus,'optimization_depth'=>$depth,'scopes'=>array_values($scopes),'pipeline'=>['segment','chapter_extract','character_relationship_graph','plot_threads','timeline_congruency','structure_pacing','style_profile','reader_experience','historical_checks','optimization_map'],'created_at'=>nowIso(),'message'=>$amsg,'worker_token_hash'=>$workerHash,'worker_token_pending'=>$workerToken,'core_job_id'=>null];

        $s['imports'][]=$rec; $s['analysis_jobs'][]=$job;
        audit($s,'manuscript.imported','manuscript_import',$id,['project_id'=>$pid,'original_name'=>$original,'sha256'=>$sha,'bytes'=>$size,'status'=>$rec['status'],'analysis_job_id'=>$aid]);
        saveState($stateFile,$s);
        if($text!=='') ba_dispatch_analysis($s,count($s['analysis_jobs'])-1,$stateFile);
        $ii=ba_find_index($s['imports'],$id);
        respond(['ok'=>true,'import'=>$ii>=0?$s['imports'][$ii]:$rec,'analysis_job'=>$s['analysis_jobs'][count($s['analysis_jobs'])-1]]);
    }

    if($method==='DELETE'&&preg_match('#^imports/(\d+)$#',$path,$m)){
        $id=(int)$m[1];
        $ii=ba_find_index($s['imports'],$id);
        if($ii<0) respond(['error'=>'import_not_found'],404);
        $import=$s['imports'][$ii];
        $pid=(int)($import['project_id']??1);
        $dir=rtrim($dataDir,'/').'/imports/project_'.$pid;
        $files=[];
        foreach(['stored_name','extracted_text_file'] as $key){
            $name=basename((string)($import[$key]??''));
            if($name!=='') $files[]=$dir.'/'.$name;
        }
        foreach(array_unique($files) as $file){
            if(is_file($file)) @unlink($file);
        }
        $s['analysis_jobs']=array_values(array_filter(
            $s['analysis_jobs']??[],
            fn($job)=>(int)($job['import_id']??0)!==$id
        ));
        array_splice($s['imports'],$ii,1);
        audit($s,'manuscript.deleted','manuscript_import',$id,[
            'project_id'=>$pid,
            'original_name'=>(string)($import['original_name']??''),
            'display_title'=>(string)($import['display_title']??ba_import_title((string)($import['original_name']??''))),
            'sha256'=>(string)($import['sha256']??'')
        ]);
        saveState($stateFile,$s);
        respond(['ok'=>true,'deleted_import_id'=>$id]);
    }

    if($method==='GET'&&preg_match('#^imports/(\d+)/original-file$#',$path,$m)){
        $id=(int)$m[1];
        $ii=ba_find_index($s['imports'],$id);
        if($ii<0) respond(['error'=>'import_not_found'],404);
        $import=$s['imports'][$ii];
        $pid=(int)($import['project_id']??1);
        $stored=basename((string)($import['stored_name']??''));
        if($stored==='') respond(['error'=>'original_file_unavailable'],404);
        $file=rtrim($dataDir,'/').'/imports/project_'.$pid.'/'.$stored;
        if(!is_file($file) || !is_readable($file)) respond(['error'=>'original_file_unavailable'],404);
        $ext=strtolower((string)($import['extension']??pathinfo($stored,PATHINFO_EXTENSION)));
        $types=[
            'pdf'=>'application/pdf',
            'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'odt'=>'application/vnd.oasis.opendocument.text',
            'epub'=>'application/epub+zip',
            'txt'=>'text/plain; charset=UTF-8',
            'md'=>'text/markdown; charset=UTF-8',
            'markdown'=>'text/markdown; charset=UTF-8',
            'html'=>'text/html; charset=UTF-8',
            'htm'=>'text/html; charset=UTF-8',
        ];
        header('Content-Type: '.($types[$ext]??'application/octet-stream'));
        header('Content-Length: '.(string)filesize($file));
        header('Content-Disposition: inline; filename="'.addcslashes((string)($import['original_name']??$stored),"\\\"").'"');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($file);
        exit;
    }

    if($method==='POST'&&preg_match('#^imports/(\d+)/extracted-text$#',$path,$m)){
        $id=(int)$m[1];
        $ii=ba_find_index($s['imports'],$id);
        if($ii<0) respond(['error'=>'import_not_found'],404);
        $import=$s['imports'][$ii];
        $b=bodyJson();
        $text=(string)($b['text']??'');
        $text=str_replace("\0",'',$text);
        $text=preg_replace('/\R/u',"\n",$text)??$text;
        $text=trim($text);
        $bytes=strlen($text);
        if($bytes<100) respond(['error'=>'extracted_text_too_short'],422);
        if($bytes>15*1024*1024) respond(['error'=>'extracted_text_too_large','max_bytes'=>15*1024*1024],422);

        $pid=(int)($import['project_id']??1);
        $dir=rtrim($dataDir,'/').'/imports/project_'.$pid;
        if(!is_dir($dir)&&!@mkdir($dir,0770,true)&&!is_dir($dir)) respond(['error'=>'import_storage_unavailable'],500);
        $stored=basename((string)($import['stored_name']??('import_'.$id)));
        $textFile=$stored.'.extracted.txt';
        $textPath=$dir.'/'.$textFile;
        if(@file_put_contents($textPath,$text,LOCK_EX)===false) respond(['error'=>'extracted_text_write_failed'],500);
        @chmod($textPath,0660);

        $scan=ba_scan($text);
        $s['imports'][$ii]['status']='parsed';
        $s['imports'][$ii]['extraction_message']='PDF text layer extracted locally in the browser; immutable original preserved.';
        $s['imports'][$ii]['extracted_text_file']=$textFile;
        $s['imports'][$ii]['structural_scan']=$scan;
        $s['imports'][$ii]['analysis_status']='dispatch_pending';
        $s['imports'][$ii]['analysis_message']='PDF text extracted. Preparing NOEVA local whole-book analysis.';

        $ji=-1;
        foreach($s['analysis_jobs']??[] as $j=>$row){
            if((int)($row['import_id']??0)===$id){ $ji=(int)$j; break; }
        }
        if($ji<0) respond(['error'=>'analysis_job_not_found'],409);
        $s['analysis_jobs'][$ji]['status']='dispatch_pending';
        $s['analysis_jobs'][$ji]['message']='PDF text extracted. Preparing NOEVA local whole-book analysis.';
        audit($s,'manuscript.text_extracted','manuscript_import',$id,[
            'project_id'=>$pid,
            'bytes'=>$bytes,
            'word_count'=>(int)($scan['word_count']??0),
            'extractor'=>'browser_pdfjs'
        ]);
        saveState($stateFile,$s);
        ba_dispatch_analysis($s,$ji,$stateFile);
        $ii=ba_find_index($s['imports'],$id);
        respond([
            'ok'=>true,
            'import'=>$ii>=0?$s['imports'][$ii]:$import,
            'analysis_job'=>$s['analysis_jobs'][$ji],
        ]);
    }

    if($method==='GET'&&preg_match('#^projects/(\d+)/analysis-jobs$#',$path,$m)){
        ba_sync_core_jobs($s,$stateFile);
        $pid=(int)$m[1]; respond(['items'=>array_values(array_filter($s['analysis_jobs'],fn($x)=>(int)($x['project_id']??0)===$pid))]);
    }

    if($method==='GET'&&preg_match('#^projects/(\d+)/write-jobs$#',$path,$m)){
        ba_sync_core_jobs($s,$stateFile);
        $pid=(int)$m[1]; respond(['items'=>array_values(array_filter($s['write_jobs'],fn($x)=>(int)($x['project_id']??0)===$pid))]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/write-jobs$#',$path,$m)){
        $pid=(int)$m[1]; $b=bodyJson();
        $outline=trim((string)($b['outline']??'')); if($outline==='') respond(['error'=>'outline_required'],422);
        $target=(int)($b['target_words']??0); if($target<500||$target>10000) respond(['error'=>'target_words_out_of_range','min'=>500,'max'=>10000],422);
        $id=maxId($s['write_jobs'])+1;
        $ctx=is_array($b['context_flags']??null)?array_values($b['context_flags']):[];
        $guards=is_array($b['guardrails']??null)?array_values($b['guardrails']):[];
        $sceneMode=strtolower(trim((string)($b['scene_mode']??'ordinary')));
        if(!in_array($sceneMode,['ordinary','building_tension','shock','aftermath','reflection'],true)) $sceneMode='ordinary';
        $key=(string)$pid;
        $projectLi=is_array($s['literary_intelligence_by_project'][$key]??null)?$s['literary_intelligence_by_project'][$key]:[];
        $styleControl=is_array($b['style_control']??null)
            ? $b['style_control']
            : (is_array($projectLi['style_profile']??null)
                ? $projectLi['style_profile']
                : (is_array($projectLi['style_control']??null)?$projectLi['style_control']:[]));
        if(!isset($styleControl['recent_patterns'])&&is_array($projectLi['recent_patterns']??null)) $styleControl['recent_patterns']=$projectLi['recent_patterns'];
        if(!isset($styleControl['protected_motifs'])&&is_array($projectLi['protected_motifs']??null)) $styleControl['protected_motifs']=$projectLi['protected_motifs'];
        $feedback=ba_feedback_claim($projectLi,$id,ba_sanitize_editorial_feedback($b['editorial_feedback']??[]));
        $s['literary_intelligence_by_project'][$key]=$projectLi;
        $influences=[];
        foreach((is_array($b['style_influences']??null)?$b['style_influences']:[]) as $row){
            if(!is_array($row)) continue;
            $name=trim((string)($row['name']??$row['author']??''));
            if($name==='') continue;
            $percent=$row['percent']??$row['weight']??null;
            if($percent!==null) $percent=max(0,min(100,(float)$percent));
            $influences[]=['name'=>mb_substr($name,0,120),'percent'=>$percent];
            if(count($influences)>=12) break;
        }
        $workerToken=bin2hex(random_bytes(32)); $workerHash=hash('sha256',$workerToken);
        $job=['id'=>$id,'project_id'=>$pid,'chapter_id'=>(int)($b['chapter_id']??0),'target_words'=>$target,'outline'=>$outline,'pov'=>(string)($b['pov']??'Use book canon'),'tense'=>(string)($b['tense']??'Use book canon'),'style_source'=>(string)($b['style_source']??'Use approved book style profile'),'style_influences'=>$influences,'research_policy'=>(string)($b['research_policy']??'Respect verified facts; flag unknowns'),'instructions'=>(string)($b['instructions']??''),'scene_mode'=>$sceneMode,'style_control'=>$styleControl,'editorial_feedback'=>$feedback,'context_flags'=>$ctx,'guardrails'=>$guards,'status'=>'dispatch_pending','message'=>'Chapter contract preserved. Preparing NOEVA local writing job.','created_at'=>nowIso(),'output_passage_id'=>null,'output_revision'=>null,'core_job_id'=>null,'worker_token_hash'=>$workerHash,'worker_token_pending'=>$workerToken,'generation_contract'=>['outline_authoritative'=>true,'target_word_count'=>$target,'word_count_tolerance_percent'=>8,'preserve_book_style'=>true,'preserve_character_voice'=>true,'style_modulation'=>true,'controlled_unpredictability'=>true,'author_style_learning'=>true,'use_story_graph'=>in_array('story_graph',$ctx,true),'use_continuity'=>in_array('continuity',$ctx,true),'never_overwrite_approved_text'=>true,'result_requires_author_approval'=>true]];
        $s['write_jobs'][]=$job;
        audit($s,'write_job.created','write_job',$id,['project_id'=>$pid,'chapter_id'=>$job['chapter_id'],'target_words'=>$target,'status'=>'dispatch_pending']);
        saveState($stateFile,$s);
        ba_dispatch_write($s,count($s['write_jobs'])-1,$stateFile);
        $job=$s['write_jobs'][count($s['write_jobs'])-1];
        respond(['ok'=>true,'job'=>$job,'message'=>$job['message']]);
    }
}
