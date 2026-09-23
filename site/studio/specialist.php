<?php
declare(strict_types=1);

/**
 * Book Author V1.6 — Specialist Intelligence persistence and authority boundaries.
 * Translation, Historical Intelligence and Naming/Titling are specialist modules,
 * not additional core engines.
 */

function ba_specialist_ensure(array &$s): void {
    $s['translation_jobs']=is_array($s['translation_jobs']??null)?array_values($s['translation_jobs']):[];
    $s['translation_links']=is_array($s['translation_links']??null)?array_values($s['translation_links']):[];
    $s['translation_terminology']=is_array($s['translation_terminology']??null)?array_values($s['translation_terminology']):[];
    $s['historical_jobs']=is_array($s['historical_jobs']??null)?array_values($s['historical_jobs']):[];
    $s['historical_claims']=is_array($s['historical_claims']??null)?array_values($s['historical_claims']):[];
    $s['naming_jobs']=is_array($s['naming_jobs']??null)?array_values($s['naming_jobs']):[];
    $s['naming_decisions']=is_array($s['naming_decisions']??null)?array_values($s['naming_decisions']):[];
    $s['rename_review']=is_array($s['rename_review']??null)?array_values($s['rename_review']):[];
}

function ba_specialist_find(array $rows,int $id): int {
    foreach($rows as $i=>$row) if((int)($row['id']??0)===$id) return (int)$i;
    return -1;
}

function ba_specialist_latest_passage(array $s,int $chapterId,string $language): ?array {
    $best=null;
    foreach($s['passages']??[] as $p){
        if((int)($p['chapter_id']??0)!==$chapterId || strtoupper((string)($p['language']??''))!==strtoupper($language)) continue;
        if($best===null || (int)($p['revision']??0)>(int)($best['revision']??0)) $best=$p;
    }
    return $best;
}

function ba_specialist_passage(array $s,int $id): ?array {
    foreach($s['passages']??[] as $p) if((int)($p['id']??0)===$id) return $p;
    return null;
}

function ba_specialist_plain_text(array $p): string {
    return trim(html_entity_decode(strip_tags((string)($p['content_html']??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}

function ba_specialist_context(array $s,int $chapterId=0): array {
    $bp=($s['book_blueprint']??null)&&$chapterId>0?ba_bp_find($s['book_blueprint'],$chapterId):null;
    $recent=[];
    foreach(array_reverse($s['passages']??[]) as $p){
        if(($p['language']??'')!=='EN') continue;
        $recent[]=[
            'id'=>$p['id']??null,'chapter_id'=>$p['chapter_id']??null,'revision'=>$p['revision']??null,
            'content'=>mb_substr(ba_specialist_plain_text($p),0,8000),
        ];
        if(count($recent)>=6) break;
    }
    return [
        'project'=>$s['project']??[],
        'authoring_profile'=>$s['authoring_profile']??[],
        'characters'=>$s['characters']??[],
        'relationships'=>$s['relationships']??[],
        'story_nodes'=>$s['story_nodes']??[],
        'story_edges'=>$s['story_edges']??[],
        'book_blueprint'=>$s['book_blueprint']??null,
        'blueprint_chapter'=>$bp,
        'research_claims'=>$s['research']??[],
        'historical_claims'=>$s['historical_claims']??[],
        'recent_manuscript'=>array_reverse($recent),
    ];
}

function ba_specialist_dispatch(array &$s,string $bucket,int $index,string $stateFile,string $action): void {
    $job=$s[$bucket][$index]??null;
    if(!$job || empty($job['worker_token_pending'])) return;
    $id=(int)($job['id']??0); if($id<1) return;
    $token=(string)$job['worker_token_pending'];
    $reply=ba_core_submit($action,ba_source_url($action,$id,$token),$action,(int)($job['project_id']??1),$id);
    if(($reply['ok']??false)&&!empty($reply['job']['id'])){
        $s[$bucket][$index]['core_job_id']=(string)$reply['job']['id'];
        $s[$bucket][$index]['status']='queued_local';
        $s[$bucket][$index]['message']='Queued on NOEVA local specialist intelligence.';
        unset($s[$bucket][$index]['worker_token_pending']);
    } else {
        $s[$bucket][$index]['status']='dispatch_pending';
        $s[$bucket][$index]['message']='Specialist contract preserved; waiting for local compute dispatch.';
    }
    saveState($stateFile,$s);
}

function ba_specialist_materialize_historical_claims(array &$s,int $jobId,array $payload): void {
    $exists=false;
    foreach($s['historical_claims'] as $row) if((int)($row['job_id']??0)===$jobId){$exists=true;break;}
    if($exists) return;
    foreach($payload['claims']??[] as $row){
        if(!is_array($row)) continue;
        $claim=trim((string)($row['claim']??'')); if($claim==='') continue;
        $id=maxId($s['historical_claims'])+1;
        $status=(string)($row['evidence_status']??'RESEARCH_REQUIRED');
        $s['historical_claims'][]=[
            'id'=>$id,'job_id'=>$jobId,'claim'=>$claim,'category'=>(string)($row['category']??'other'),
            'risk'=>(string)($row['risk']??'medium'),'reason'=>(string)($row['reason']??''),
            'contested'=>(bool)($row['contested']??false),'research_queries'=>array_values($row['research_queries']??[]),
            'model_evidence_status'=>$status,'verification_status'=>'unverified',
            'evidence'=>[],'created_at'=>nowIso(),'verified_at'=>null,'verification_rationale'=>null,
        ];
    }
}

function ba_specialist_sync(array &$s,string $stateFile): void {
    ba_specialist_ensure($s);
    $changed=false;
    foreach([
        ['bucket'=>'translation_jobs','action'=>'translate'],
        ['bucket'=>'historical_jobs','action'=>'historical'],
        ['bucket'=>'naming_jobs','action'=>'naming'],
    ] as $cfg){
        $bucket=$cfg['bucket'];$action=$cfg['action'];
        foreach($s[$bucket] as $i=>$job){
            if(($job['status']??'')==='dispatch_pending'&&!empty($job['worker_token_pending'])){
                ba_specialist_dispatch($s,$bucket,(int)$i,$stateFile,$action);$job=$s[$bucket][$i];
            }
            $coreId=(string)($job['core_job_id']??'');
            if($coreId===''||in_array((string)($job['status']??''),['completed','accepted','rejected','failed'],true)) continue;
            $reply=ba_core_status($coreId);
            if(!($reply['ok']??false)||!is_array($reply['job']??null)) continue;
            $core=$reply['job'];$status=strtoupper((string)($core['status']??''));
            if($status==='QUEUED'){
                $s[$bucket][$i]['status']='queued_local';$s[$bucket][$i]['message']='Waiting for a specialist-capable NOEVA compute slot.';$changed=true;
            } elseif($status==='LEASED'){
                $s[$bucket][$i]['status']='running_local';$s[$bucket][$i]['message']='Specialist intelligence is running locally.';$changed=true;
            } elseif($status==='COMPLETED'){
                $payload=ba_extract_task_payload($core['result']??[]);
                $s[$bucket][$i]['core_result']=$core['result']??[];
                $s[$bucket][$i]['completed_at']=$core['completed_at']??nowIso();
                $s[$bucket][$i]['worker_token_hash']=null;
                if($payload){
                    $s[$bucket][$i]['status']='completed';$s[$bucket][$i]['result']=$payload;
                    $s[$bucket][$i]['message']='Specialist result ready for author review.';
                    if($action==='historical') ba_specialist_materialize_historical_claims($s,(int)$job['id'],$payload);
                } else {
                    $s[$bucket][$i]['status']='failed';$s[$bucket][$i]['message']='Local specialist task returned no structured result.';
                }
                $changed=true;
            } elseif($status==='FAILED'){
                $s[$bucket][$i]['status']='failed';$s[$bucket][$i]['core_result']=$core['result']??[];
                $s[$bucket][$i]['message']='Local specialist task failed without changing canonical manuscript state.';$changed=true;
            }
        }
    }
    if($changed) saveState($stateFile,$s);
}

function ba_translation_queues(array $s): array {
    $queues=['changed_source'=>[],'low_confidence'=>[],'nuance_risk'=>[],'terminology'=>[],'historical_language'=>[],'author_edited'=>[]];
    foreach($s['translation_jobs']??[] as $j){
        if(($j['status']??'')!=='completed') continue;
        $result=is_array($j['result']??null)?$j['result']:[];
        foreach($result['review_queues']??[] as $q){
            $q=(string)$q;if(isset($queues[$q])) $queues[$q][]=(int)$j['id'];
        }
        if(($result['requires_review']??false)&&!in_array((int)$j['id'],$queues['low_confidence'],true)) $queues['low_confidence'][]=(int)$j['id'];
    }
    foreach($s['translation_links']??[] as $link){
        $source=ba_specialist_passage($s,(int)($link['source_passage_id']??0));
        $chapter=(int)($link['chapter_id']??($source['chapter_id']??0));
        $latestSource=$chapter?ba_specialist_latest_passage($s,$chapter,(string)($link['source_language']??'EN')):null;
        if($latestSource&&(int)($latestSource['revision']??0)>(int)($link['source_revision']??0)) $queues['changed_source'][]=(int)$link['id'];
        $latestTarget=$chapter?ba_specialist_latest_passage($s,$chapter,(string)($link['target_language']??'DE')):null;
        if($latestTarget&&(int)($latestTarget['revision']??0)>(int)($link['target_revision']??0)) $queues['author_edited'][]=(int)$link['id'];
    }
    foreach($queues as &$rows) $rows=array_values(array_unique($rows));unset($rows);
    return $queues;
}

function ba_historical_notes(array $s): array {
    $out=[];
    foreach($s['historical_claims']??[] as $c){
        if(($c['verification_status']??'')!=='verified') continue;
        $out[]=[
            'claim'=>$c['claim'],'category'=>$c['category']??'other','evidence'=>$c['evidence']??[],
            'rationale'=>$c['verification_rationale']??'','verified_at'=>$c['verified_at']??null,
        ];
    }
    return $out;
}

function ba_specialist_api(array &$s,string $stateFile,string $method,string $path,string $dataDir): void {
    ba_specialist_ensure($s);

    if($method==='GET'&&preg_match('#^projects/(\d+)/specialist$#',$path,$m)){
        ba_specialist_sync($s,$stateFile);
        $health=ba_core_health();
        respond([
            'ok'=>true,'specialist_bound'=>(bool)($health['specialist_bound']??false),
            'specialist_actions'=>$health['specialist_actions']??[],
            'translation_jobs'=>$s['translation_jobs'],'translation_links'=>$s['translation_links'],
            'translation_queues'=>ba_translation_queues($s),'terminology'=>$s['translation_terminology'],
            'historical_jobs'=>$s['historical_jobs'],'historical_claims'=>$s['historical_claims'],
            'historical_notes'=>ba_historical_notes($s),
            'naming_jobs'=>$s['naming_jobs'],'naming_decisions'=>$s['naming_decisions'],'rename_review'=>$s['rename_review'],
            'chapters'=>$s['chapters']??[],'characters'=>$s['characters']??[],'passages'=>$s['passages']??[],
        ]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/translation-jobs$#',$path,$m)){
        $pid=(int)$m[1];$b=bodyJson();$sourceId=(int)($b['source_passage_id']??0);
        $source=ba_specialist_passage($s,$sourceId);if(!$source) respond(['error'=>'source_passage_not_found'],404);
        if(strtoupper((string)($source['language']??''))!=='EN') respond(['error'=>'translation_source_must_be_en'],422);
        $target=strtoupper(trim((string)($b['target_language']??'DE')));if($target==='')$target='DE';
        $id=maxId($s['translation_jobs'])+1;$token=bin2hex(random_bytes(32));
        $job=[
            'id'=>$id,'project_id'=>$pid,'source_passage_id'=>$sourceId,'source_revision'=>(int)$source['revision'],
            'chapter_id'=>(int)$source['chapter_id'],'source_language'=>'EN','target_language'=>$target,
            'status'=>'dispatch_pending','created_at'=>nowIso(),'core_job_id'=>null,
            'worker_token_hash'=>hash('sha256',$token),'worker_token_pending'=>$token,
            'protected_ambiguities'=>is_array($b['protected_ambiguities']??null)?array_values($b['protected_ambiguities']):[],
            'message'=>'Translation contract preserved; preparing local Translation & Nuance pipeline.',
        ];
        $s['translation_jobs'][]=$job;audit($s,'translation.job_created','translation_job',$id,['source_passage_id'=>$sourceId,'source_revision'=>$source['revision'],'target_language'=>$target]);
        saveState($stateFile,$s);ba_specialist_dispatch($s,'translation_jobs',count($s['translation_jobs'])-1,$stateFile,'translate');
        respond(['ok'=>true,'job'=>$s['translation_jobs'][count($s['translation_jobs'])-1]]);
    }

    if($method==='POST'&&preg_match('#^translation-jobs/(\d+)/accept$#',$path,$m)){
        ba_specialist_sync($s,$stateFile);$id=(int)$m[1];$i=ba_specialist_find($s['translation_jobs'],$id);
        if($i<0) respond(['error'=>'translation_job_not_found'],404);$job=$s['translation_jobs'][$i];
        if(($job['status']??'')!=='completed') respond(['error'=>'translation_not_ready'],409);
        $result=is_array($job['result']??null)?$job['result']:[];
        $b=bodyJson();if(($result['requires_review']??false)&&empty($b['override_review'])) respond(['error'=>'translation_review_required','review_queues'=>$result['review_queues']??[]],409);
        $source=ba_specialist_passage($s,(int)$job['source_passage_id']);if(!$source) respond(['error'=>'source_passage_not_found'],404);
        $latestSource=ba_specialist_latest_passage($s,(int)$job['chapter_id'],'EN');
        if(!$latestSource||(int)$latestSource['revision']!==(int)$job['source_revision']) respond(['error'=>'source_changed_since_translation'],409);
        $latestTarget=ba_specialist_latest_passage($s,(int)$job['chapter_id'],(string)$job['target_language']);
        if($latestTarget&&!empty($latestTarget['locked'])) respond(['error'=>'target_passage_locked'],409);
        $text=trim((string)($result['translation']??''));if($text==='') respond(['error'=>'translation_missing'],409);
        $newRev=max((int)($s['project']['current_revision']??0)+1,(int)($latestTarget['revision']??0)+1);
        $passage=[
            'id'=>maxId($s['passages'])+1,'project_id'=>(int)$job['project_id'],'chapter_id'=>(int)$job['chapter_id'],'scene_id'=>$source['scene_id']??1,
            'language'=>(string)$job['target_language'],'revision'=>$newRev,'approved'=>false,'locked'=>false,
            'parent_passage_id'=>$latestTarget['id']??null,'created_at'=>nowIso(),
            'content_html'=>ba_authoring_html_from_draft($text),'source'=>'translation_job','translation_job_id'=>$id,
        ];
        $s['passages'][]=$passage;$s['project']['current_revision']=$newRev;$s['project']['updated_at']=nowIso();
        $linkId=maxId($s['translation_links'])+1;$s['translation_links'][]=[
            'id'=>$linkId,'project_id'=>(int)$job['project_id'],'chapter_id'=>(int)$job['chapter_id'],
            'source_passage_id'=>(int)$job['source_passage_id'],'source_revision'=>(int)$job['source_revision'],'source_language'=>'EN',
            'target_passage_id'=>$passage['id'],'target_revision'=>$newRev,'target_language'=>(string)$job['target_language'],
            'nuance_profile'=>$result['nuance_profile']??[],'fidelity_gate'=>$result['fidelity_gate']??[],
            'created_at'=>nowIso(),
        ];
        $s['translation_jobs'][$i]['status']='accepted';$s['translation_jobs'][$i]['accepted_at']=nowIso();$s['translation_jobs'][$i]['target_passage_id']=$passage['id'];
        audit($s,'translation.accepted','translation_job',$id,['target_passage_id'=>$passage['id'],'target_revision'=>$newRev]);saveState($stateFile,$s);
        respond(['ok'=>true,'job'=>$s['translation_jobs'][$i],'passage'=>$passage]);
    }

    if($method==='POST'&&preg_match('#^translation-jobs/(\d+)/reject$#',$path,$m)){
        $id=(int)$m[1];$i=ba_specialist_find($s['translation_jobs'],$id);if($i<0) respond(['error'=>'translation_job_not_found'],404);
        $b=bodyJson();$s['translation_jobs'][$i]['status']='rejected';$s['translation_jobs'][$i]['rejected_at']=nowIso();$s['translation_jobs'][$i]['rejection_reason']=mb_substr(trim((string)($b['reason']??'Author rejected translation')),0,1200);
        audit($s,'translation.rejected','translation_job',$id,['reason'=>$s['translation_jobs'][$i]['rejection_reason']]);saveState($stateFile,$s);respond(['ok'=>true,'job'=>$s['translation_jobs'][$i]]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/historical-jobs$#',$path,$m)){
        $pid=(int)$m[1];$b=bodyJson();$passageId=(int)($b['passage_id']??0);$chapterId=(int)($b['chapter_id']??0);
        if($passageId<1&&$chapterId<1) respond(['error'=>'passage_id_or_chapter_id_required'],422);
        $id=maxId($s['historical_jobs'])+1;$token=bin2hex(random_bytes(32));
        $s['historical_jobs'][]=[
            'id'=>$id,'project_id'=>$pid,'passage_id'=>$passageId?:null,'chapter_id'=>$chapterId?:null,
            'status'=>'dispatch_pending','created_at'=>nowIso(),'core_job_id'=>null,
            'worker_token_hash'=>hash('sha256',$token),'worker_token_pending'=>$token,
            'message'=>'Historical Intelligence contract preserved; preparing local claim analysis.',
        ];
        audit($s,'historical.job_created','historical_job',$id,['passage_id'=>$passageId,'chapter_id'=>$chapterId]);saveState($stateFile,$s);
        ba_specialist_dispatch($s,'historical_jobs',count($s['historical_jobs'])-1,$stateFile,'historical');
        respond(['ok'=>true,'job'=>$s['historical_jobs'][count($s['historical_jobs'])-1]]);
    }

    if($method==='POST'&&preg_match('#^historical-claims/(\d+)/evidence$#',$path,$m)){
        $id=(int)$m[1];$i=ba_specialist_find($s['historical_claims'],$id);if($i<0) respond(['error'=>'historical_claim_not_found'],404);
        $b=bodyJson();$title=trim((string)($b['source_title']??''));if($title==='') respond(['error'=>'source_title_required'],422);
        $e=[
            'id'=>'hev_'.(count($s['historical_claims'][$i]['evidence']??[])+1),'source_title'=>mb_substr($title,0,300),
            'source_url'=>mb_substr(trim((string)($b['source_url']??'')),0,1200),'citation'=>mb_substr(trim((string)($b['citation']??'')),0,1000),
            'note'=>mb_substr(trim((string)($b['note']??'')),0,2500),'support'=>(string)($b['support']??'context'),
            'rights_note'=>mb_substr(trim((string)($b['rights_note']??'')),0,600),'added_at'=>nowIso(),
        ];
        $s['historical_claims'][$i]['evidence'][]=$e;$s['historical_claims'][$i]['verification_status']='evidence_attached';
        audit($s,'historical.evidence_attached','historical_claim',$id,['evidence_id'=>$e['id'],'source_title'=>$e['source_title']]);saveState($stateFile,$s);
        respond(['ok'=>true,'claim'=>$s['historical_claims'][$i]]);
    }

    if($method==='POST'&&preg_match('#^historical-claims/(\d+)/verify$#',$path,$m)){
        $id=(int)$m[1];$i=ba_specialist_find($s['historical_claims'],$id);if($i<0) respond(['error'=>'historical_claim_not_found'],404);
        if(empty($s['historical_claims'][$i]['evidence'])) respond(['error'=>'evidence_required_before_verification'],409);
        $b=bodyJson();$decision=strtolower((string)($b['decision']??''));
        if(!in_array($decision,['verified','contested','rejected'],true)) respond(['error'=>'invalid_verification_decision'],422);
        $rationale=trim((string)($b['rationale']??''));if($rationale==='') respond(['error'=>'verification_rationale_required'],422);
        $s['historical_claims'][$i]['verification_status']=$decision;$s['historical_claims'][$i]['verified_at']=nowIso();$s['historical_claims'][$i]['verification_rationale']=mb_substr($rationale,0,2500);
        audit($s,'historical.claim_adjudicated','historical_claim',$id,['decision'=>$decision]);saveState($stateFile,$s);respond(['ok'=>true,'claim'=>$s['historical_claims'][$i]]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/naming-jobs$#',$path,$m)){
        $pid=(int)$m[1];$b=bodyJson();$kind=strtolower((string)($b['naming_kind']??'character'));
        if(!in_array($kind,['character','protagonist','novel_title'],true)) respond(['error'=>'invalid_naming_kind'],422);
        $id=maxId($s['naming_jobs'])+1;$token=bin2hex(random_bytes(32));
        $s['naming_jobs'][]=[
            'id'=>$id,'project_id'=>$pid,'naming_kind'=>$kind,'target_character_id'=>isset($b['target_character_id'])?(int)$b['target_character_id']:null,
            'constraints'=>is_array($b['constraints']??null)?$b['constraints']:[],'avoid'=>is_array($b['avoid']??null)?array_values($b['avoid']):[],
            'status'=>'dispatch_pending','created_at'=>nowIso(),'core_job_id'=>null,
            'worker_token_hash'=>hash('sha256',$token),'worker_token_pending'=>$token,
            'message'=>'Naming/Titling contract preserved; preparing local ideation.',
        ];
        audit($s,'naming.job_created','naming_job',$id,['naming_kind'=>$kind]);saveState($stateFile,$s);
        ba_specialist_dispatch($s,'naming_jobs',count($s['naming_jobs'])-1,$stateFile,'naming');
        respond(['ok'=>true,'job'=>$s['naming_jobs'][count($s['naming_jobs'])-1]]);
    }

    if($method==='POST'&&preg_match('#^naming-jobs/(\d+)/accept$#',$path,$m)){
        ba_specialist_sync($s,$stateFile);$id=(int)$m[1];$i=ba_specialist_find($s['naming_jobs'],$id);if($i<0) respond(['error'=>'naming_job_not_found'],404);
        $job=$s['naming_jobs'][$i];if(($job['status']??'')!=='completed') respond(['error'=>'naming_job_not_ready'],409);
        $b=bodyJson();$index=(int)($b['candidate_index']??-1);$candidates=$job['result']['candidates']??[];
        if($index<0||!isset($candidates[$index])||!is_array($candidates[$index])) respond(['error'=>'candidate_not_found'],404);
        $candidate=$candidates[$index];$value=trim((string)($candidate['value']??''));if($value==='') respond(['error'=>'candidate_value_missing'],409);
        $decision=['id'=>maxId($s['naming_decisions'])+1,'job_id'=>$id,'kind'=>$job['naming_kind'],'value'=>$value,'candidate'=>$candidate,'accepted_at'=>nowIso()];
        if($job['naming_kind']==='novel_title'){
            $decision['previous_value']=$s['project']['title']??'';$s['project']['title']=$value;$s['project']['updated_at']=nowIso();
        } else {
            $targetId=(int)($job['target_character_id']??0);$ci=$targetId?findIndexById($s['characters']??[],$targetId):-1;
            if($ci>=0){
                $old=(string)$s['characters'][$ci]['name'];$decision['previous_value']=$old;$decision['character_id']=$targetId;
                $aliases=is_array($s['characters'][$ci]['aliases']??null)?$s['characters'][$ci]['aliases']:[];
                if($old!==''&&!in_array($old,$aliases,true))$aliases[]=$old;
                $s['characters'][$ci]['aliases']=$aliases;$s['characters'][$ci]['name']=$value;
                foreach($s['relationships']??[] as &$r) foreach(['from','to','character_a','character_b'] as $k) if(($r[$k]??null)===$old)$r[$k]=$value;unset($r);
                if(is_array($s['book_blueprint']['chapters']??null))foreach($s['book_blueprint']['chapters'] as &$ch)foreach($ch['characters']??[] as $k=>$n)if((string)$n===$old)$ch['characters'][$k]=$value;unset($ch);
                $affected=[];foreach($s['passages']??[] as $p)if($old!==''&&mb_stripos(ba_specialist_plain_text($p),$old)!==false)$affected[]=(int)$p['id'];
                if($affected)$s['rename_review'][]=['id'=>maxId($s['rename_review'])+1,'character_id'=>$targetId,'old_name'=>$old,'new_name'=>$value,'passage_ids'=>$affected,'status'=>'pending','created_at'=>nowIso()];
            } else {
                $newId=maxId($s['characters']??[])+1;$role=$job['naming_kind']==='protagonist'?'Protagonist':'Character';
                $s['characters'][]=['id'=>$newId,'name'=>$value,'role'=>$role,'state'=>[],'aliases'=>[],'generated_from_naming_job'=>$id];
                $decision['character_id']=$newId;$decision['created_character']=true;
            }
        }
        $s['naming_decisions'][]=$decision;$s['naming_jobs'][$i]['status']='accepted';$s['naming_jobs'][$i]['accepted_candidate_index']=$index;$s['naming_jobs'][$i]['accepted_at']=nowIso();
        audit($s,'naming.accepted','naming_job',$id,['kind'=>$job['naming_kind'],'value'=>$value]);saveState($stateFile,$s);respond(['ok'=>true,'decision'=>$decision]);
    }
}
