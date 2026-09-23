<?php
declare(strict_types=1);

/**
 * Book Author V1.5 — canonical authoring orchestration.
 *
 * This module does not replace the specialist engines. It binds the ten canonical
 * authoring engines into one persisted run contract and keeps author approval as
 * the only path from generated prose into the manuscript.
 */

function ba_authoring_engine_registry(): array {
    return [
        ['key'=>'character','name'=>'Character Engine','purpose'=>'Characters, relationships, voice, knowledge and arc state.'],
        ['key'=>'plot','name'=>'Plot Engine','purpose'=>'Causality, twists, objects, clues, promises and payoffs.'],
        ['key'=>'story','name'=>'Story Engine','purpose'=>'Whole-book coherence, Story Graph and narrative trajectory.'],
        ['key'=>'chapter','name'=>'Chapter Engine','purpose'=>'Chapter function, structure, order, pacing and target contract.'],
        ['key'=>'writing','name'=>'Writing Engine','purpose'=>'Full prose generation from the approved chapter contract.'],
        ['key'=>'humanize','name'=>'Humanize Engine','purpose'=>'Natural human prose, cadence variation and anti-formula checks.'],
        ['key'=>'refinement','name'=>'Refinement Engine','purpose'=>'Evidence-bound revision, compliance and material weakness removal.'],
        ['key'=>'self_improvement','name'=>'Self-improvement Engine','purpose'=>'Learns from author decisions, diagnostics and revision deltas.'],
        ['key'=>'catalogue','name'=>'Catalogue Engine','purpose'=>'Project/book metadata, reusable author intelligence and library state.'],
        ['key'=>'research','name'=>'Research Engine','purpose'=>'Facts, historical evidence, terminology and unresolved research obligations.'],
    ];
}

function ba_authoring_default_profile(array $project): array {
    return [
        'story_concept'=>'',
        'setting'=>'',
        'themes'=>[],
        'tone'=>'',
        'target_length_words'=>80000,
        'genre'=>(string)($project['genre']??''),
        'style_preferences'=>'',
        'style_influences'=>[],
        'research_requirements'=>'',
        'revision_preferences'=>'',
        'series_canon'=>'',
        'prologue_policy'=>'as_needed',
        'epilogue_policy'=>'as_needed',
        'updated_at'=>nowIso(),
    ];
}

function ba_authoring_ensure(array &$s): void {
    $s['authoring_profile']=is_array($s['authoring_profile']??null)
        ? $s['authoring_profile']
        : ba_authoring_default_profile($s['project']??[]);
    $s['story_nodes']=is_array($s['story_nodes']??null)?array_values($s['story_nodes']):[];
    $s['story_edges']=is_array($s['story_edges']??null)?array_values($s['story_edges']):[];
    $s['relationships']=is_array($s['relationships']??null)?array_values($s['relationships']):[];
    $s['authoring_runs']=is_array($s['authoring_runs']??null)?array_values($s['authoring_runs']):[];
    $s['engine_learning']=is_array($s['engine_learning']??null)?array_values($s['engine_learning']):[];
}

function ba_authoring_plain(string $value,int $limit=12000): string {
    $value=trim(preg_replace('/\s+/u',' ',$value)??$value);
    return mb_substr($value,0,$limit);
}

function ba_authoring_array_strings(mixed $value,int $limit=50,int $itemLimit=240): array {
    if(!is_array($value)) return [];
    $out=[];$seen=[];
    foreach($value as $item){
        $v=ba_authoring_plain((string)$item,$itemLimit);
        $k=mb_strtolower($v);
        if($v===''||isset($seen[$k])) continue;
        $seen[$k]=true;$out[]=$v;
        if(count($out)>=$limit) break;
    }
    return $out;
}

function ba_authoring_profile_public(array $p): array {
    return [
        'story_concept'=>(string)($p['story_concept']??''),
        'setting'=>(string)($p['setting']??''),
        'themes'=>array_values($p['themes']??[]),
        'tone'=>(string)($p['tone']??''),
        'target_length_words'=>(int)($p['target_length_words']??80000),
        'genre'=>(string)($p['genre']??''),
        'style_preferences'=>(string)($p['style_preferences']??''),
        'style_influences'=>array_values($p['style_influences']??[]),
        'research_requirements'=>(string)($p['research_requirements']??''),
        'revision_preferences'=>(string)($p['revision_preferences']??''),
        'series_canon'=>(string)($p['series_canon']??''),
        'prologue_policy'=>(string)($p['prologue_policy']??'as_needed'),
        'epilogue_policy'=>(string)($p['epilogue_policy']??'as_needed'),
        'updated_at'=>$p['updated_at']??null,
    ];
}

function ba_authoring_find_run(array $runs,int $id): int {
    foreach($runs as $i=>$run) if((int)($run['id']??0)===$id) return (int)$i;
    return -1;
}

function ba_authoring_find_write_job(array $s,int $id): ?array {
    foreach($s['write_jobs']??[] as $job) if((int)($job['id']??0)===$id) return $job;
    return null;
}

function ba_authoring_research_for_chapter(array $s,int $chapterId,?array $bpChapter): array {
    $items=[];
    foreach($s['research']??[] as $row){
        $loc=mb_strtolower((string)($row['usage_location']??''));
        if($loc===''||str_contains($loc,'chapter '.$chapterId)||str_contains($loc,'chapters '.$chapterId)){
            $items[]=$row;
        }
    }
    foreach($s['historical_claims']??[] as $row){
        $claimChapter=(int)($row['chapter_id']??0);
        if($claimChapter!==0&&$claimChapter!==$chapterId) continue;
        $status=(string)($row['verification_status']??'unverified');
        $items[]=[
            'id'=>'hist_'.(string)($row['id']??''),
            'claim'=>(string)($row['claim']??''),
            'status'=>$status==='verified'?'verified':($status==='contested'?'contested':'check'),
            'confidence'=>$status==='verified'?'Evidence adjudicated':'Unresolved',
            'usage_location'=>$claimChapter?'Chapter '.$claimChapter:'Book-wide',
            'source_note'=>$status==='verified'?'Historical Intelligence claim with attached evidence and explicit verification.':'Historical Intelligence claim requiring evidence/review.',
            'queued'=>$status!=='verified',
            'historical_claim_id'=>$row['id']??null,
        ];
    }
    foreach($bpChapter['research']??[] as $claim){
        $items[]=[
            'id'=>null,
            'claim'=>(string)$claim,
            'status'=>'unverified',
            'confidence'=>null,
            'usage_location'=>'Blueprint chapter '.$chapterId,
            'source_note'=>'Book Blueprint research obligation.',
            'queued'=>false,
        ];
    }
    return array_slice($items,0,60);
}

function ba_authoring_character_context(array $s,?array $bpChapter): array {
    $wanted=array_map('mb_strtolower',array_values($bpChapter['characters']??[]));
    $chars=[];
    foreach($s['characters']??[] as $c){
        if(!$wanted||in_array(mb_strtolower((string)($c['name']??'')),$wanted,true)) $chars[]=$c;
    }
    return ['characters'=>$chars,'relationships'=>$s['relationships']??[]];
}

function ba_authoring_graph_context(array $s,int $chapterId): array {
    $nodes=[];
    foreach($s['story_nodes']??[] as $n){
        $chapter=(int)($n['chapter_id']??0);
        if($chapter===0||$chapter===$chapterId) $nodes[]=$n;
    }
    $nodeIds=array_fill_keys(array_map(fn($n)=>(string)($n['id']??''),$nodes),true);
    $edges=[];
    foreach($s['story_edges']??[] as $e){
        $from=(string)($e['from']??$e['source']??'');
        $to=(string)($e['to']??$e['target']??'');
        if(isset($nodeIds[$from])||isset($nodeIds[$to])) $edges[]=$e;
    }
    return ['nodes'=>array_slice($nodes,0,120),'edges'=>array_slice($edges,0,180)];
}

function ba_authoring_stage(string $key,string $status,array $summary=[],array $evidence=[]): array {
    return [
        'engine'=>$key,
        'status'=>$status,
        'summary'=>$summary,
        'evidence'=>$evidence,
        'updated_at'=>nowIso(),
    ];
}

function ba_authoring_preflight(array $s,int $chapterId,?array $bpChapter): array {
    $profile=ba_authoring_profile_public($s['authoring_profile']);
    $research=ba_authoring_research_for_chapter($s,$chapterId,$bpChapter);
    $characters=ba_authoring_character_context($s,$bpChapter);
    $graph=ba_authoring_graph_context($s,$chapterId);
    $unresolved=array_values(array_filter($research,fn($r)=>!in_array(strtolower((string)($r['status']??'')),['verified','resolved'],true)));
    $chapterTitle=(string)($bpChapter['title']??'');
    return [
        'catalogue'=>ba_authoring_stage('catalogue','complete',[
            'project_title'=>$s['project']['title']??'',
            'genre'=>$profile['genre'],
            'target_length_words'=>$profile['target_length_words'],
            'blueprint_version'=>$s['book_blueprint']['version']??null,
        ]),
        'research'=>ba_authoring_stage('research','complete',[
            'claims_considered'=>count($research),
            'unresolved_claims'=>count($unresolved),
            'policy'=>'verified facts are authoritative; unresolved claims are flagged rather than silently invented',
        ],array_slice(array_map(fn($r)=>(string)($r['claim']??''),$unresolved),0,12)),
        'character'=>ba_authoring_stage('character','complete',[
            'characters'=>array_map(fn($c)=>(string)($c['name']??''),$characters['characters']),
            'relationships'=>count($characters['relationships']),
        ]),
        'plot'=>ba_authoring_stage('plot','complete',[
            'story_nodes'=>count($graph['nodes']),
            'story_edges'=>count($graph['edges']),
        ]),
        'story'=>ba_authoring_stage('story','complete',[
            'story_concept'=>$profile['story_concept'],
            'themes'=>$profile['themes'],
            'tone'=>$profile['tone'],
            'blueprint_authority'=>(string)($s['book_blueprint']['authority']??'none'),
        ]),
        'chapter'=>ba_authoring_stage('chapter','complete',[
            'chapter_id'=>$chapterId,
            'title'=>$chapterTitle,
            'purpose'=>$bpChapter['purpose']??'',
            'binding'=>$bpChapter['binding']??'guide',
            'order_locked'=>(bool)($bpChapter['order_locked']??false),
        ]),
        'writing'=>ba_authoring_stage('writing','queued'),
        'humanize'=>ba_authoring_stage('humanize','waiting'),
        'refinement'=>ba_authoring_stage('refinement','waiting'),
        'self_improvement'=>ba_authoring_stage('self_improvement','waiting'),
    ];
}

function ba_authoring_contract(array $s,int $chapterId,?array $bpChapter,array $b): array {
    $profile=ba_authoring_profile_public($s['authoring_profile']);
    $outline=trim((string)($b['outline']??''));
    if($outline===''&&$bpChapter){
        $parts=[];
        foreach(['synopsis','purpose','hook'] as $k) if(trim((string)($bpChapter[$k]??''))!=='') $parts[]=$bpChapter[$k];
        $outline=implode("\n\n",$parts);
    }
    if($outline==='') respond(['error'=>'chapter_outline_required'],422);
    $target=(int)($b['target_words']??($bpChapter['target_words']??3000));
    $target=max(500,min($target,10000));
    $styleInfluences=is_array($b['style_influences']??null)
        ? array_values($b['style_influences'])
        : array_values($profile['style_influences']??[]);
    $sceneMode=strtolower((string)($b['scene_mode']??'ordinary'));
    if(!in_array($sceneMode,['ordinary','building_tension','shock','aftermath','reflection'],true)) $sceneMode='ordinary';

    return [
        'chapter_id'=>$chapterId,
        'target_words'=>$target,
        'outline'=>$outline,
        'pov'=>(string)($b['pov']??($bpChapter['pov']??'Use book canon')),
        'tense'=>(string)($b['tense']??'Use book canon'),
        'style_source'=>(string)($b['style_source']??'Use approved book style profile'),
        'style_influences'=>$styleInfluences,
        'research_policy'=>(string)($b['research_policy']??'Respect verified facts and Blueprint research obligations; flag unknowns.'),
        'scene_mode'=>$sceneMode,
        'instructions'=>trim((string)($b['instructions']??$profile['style_preferences']??'')),
        'context_flags'=>['story_graph','characters','continuity','style','previous','future_outline','research','blueprint'],
        'guardrails'=>['no_overwrite','canon','voice','word_count','research_evidence','blueprint'],
    ];
}

function ba_authoring_html_from_draft(string $draft): string {
    $draft=trim($draft);
    if($draft==='') return '';
    $parts=preg_split('/\R{2,}/u',$draft)?:[$draft];
    $html=[];
    foreach($parts as $part){
        $part=trim($part);
        if($part==='') continue;
        $html[]='<p>'.nl2br(htmlspecialchars($part,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),false).'</p>';
    }
    return implode('',$html);
}

function ba_authoring_sync_runs(array &$s,string $stateFile): void {
    ba_sync_core_jobs($s,$stateFile);
    $changed=false;
    foreach($s['authoring_runs'] as $i=>$run){
        $jobId=(int)($run['write_job_id']??0);
        if($jobId<1) continue;
        $job=ba_authoring_find_write_job($s,$jobId);
        if(!$job) continue;
        $status=(string)($job['status']??'');
        if(($run['write_status']??null)!==$status){$s['authoring_runs'][$i]['write_status']=$status;$changed=true;}
        if(in_array($status,['queued_local','dispatch_pending'],true)){
            $s['authoring_runs'][$i]['status']='generating';
            $s['authoring_runs'][$i]['stages']['writing']['status']='queued';
            $changed=true;
        } elseif($status==='writing_local'){
            $s['authoring_runs'][$i]['status']='generating';
            $s['authoring_runs'][$i]['stages']['writing']['status']='running';
            $changed=true;
        } elseif($status==='draft_ready'){
            $li=is_array($job['literary_intelligence']??null)?$job['literary_intelligence']:[];
            $s['authoring_runs'][$i]['status']='draft_ready';
            $s['authoring_runs'][$i]['draft_ready_at']=$job['completed_at']??nowIso();
            $s['authoring_runs'][$i]['generated_word_count']=$job['generated_word_count']??null;
            $s['authoring_runs'][$i]['blueprint_compliance']=$job['blueprint_compliance']??(($job['core_result']['blueprint_compliance']??null));
            $trace=is_array($job['engine_trace']??null)?$job['engine_trace']:[];
            foreach($trace as $row){
                if(!is_array($row)) continue;
                $engine=(string)($row['engine']??'');
                if($engine===''||!isset($s['authoring_runs'][$i]['stages'][$engine])) continue;
                $status=$engine==='self_improvement'?'waiting_author':'complete';
                $summary=is_array($row['summary']??null)?$row['summary']:[];
                if($engine==='writing') $summary['model']=$job['model']??null;
                if($engine==='self_improvement') $summary['author_decision_required']=true;
                $s['authoring_runs'][$i]['stages'][$engine]=ba_authoring_stage($engine,$status,$summary);
            }
            if(!$trace){
                $s['authoring_runs'][$i]['stages']['writing']=ba_authoring_stage('writing','complete',[
                    'word_count'=>$job['generated_word_count']??null,'model'=>$job['model']??null,
                ]);
                $s['authoring_runs'][$i]['stages']['humanize']=ba_authoring_stage('humanize','complete',[
                    'diagnostics'=>$li['diagnostics_after']??[],'style_resolution'=>$li['style_resolution']??[],
                ]);
                $s['authoring_runs'][$i]['stages']['refinement']=ba_authoring_stage('refinement','complete',[
                    'revision_performed'=>(bool)($li['revision_performed']??false),
                    'revision_requirements'=>$li['revision_requirements']??[],
                    'blueprint_compliance'=>$job['blueprint_compliance']??null,
                ]);
                $s['authoring_runs'][$i]['stages']['self_improvement']=ba_authoring_stage('self_improvement','waiting_author',[
                    'learning_event'=>$li['learning_event']??null,
                    'patterns_remembered'=>count($li['recent_patterns_next']??[]),
                    'author_decision_required'=>true,
                ]);
            }
            $changed=true;
        } elseif($status==='failed'){
            $s['authoring_runs'][$i]['status']='failed';
            foreach(['writing','humanize','refinement','self_improvement'] as $k){
                if(($s['authoring_runs'][$i]['stages'][$k]['status']??'')!=='complete')
                    $s['authoring_runs'][$i]['stages'][$k]['status']='failed';
            }
            $changed=true;
        }
    }
    if($changed) saveState($stateFile,$s);
}

function ba_authoring_api(array &$s,string $stateFile,string $method,string $path,string $dataDir): void {
    ba_authoring_ensure($s);

    if($method==='GET'&&preg_match('#^projects/(\d+)/authoring$#',$path,$m)){
        ba_authoring_sync_runs($s,$stateFile);
        $latest=$s['authoring_runs']?end($s['authoring_runs']):null;
        respond([
            'ok'=>true,
            'project'=>$s['project'],
            'profile'=>ba_authoring_profile_public($s['authoring_profile']),
            'engines'=>ba_authoring_engine_registry(),
            'story_graph'=>['nodes'=>$s['story_nodes'],'edges'=>$s['story_edges']],
            'characters'=>$s['characters']??[],
            'relationships'=>$s['relationships'],
            'chapters'=>$s['chapters']??[],
            'blueprint'=>$s['book_blueprint']??null,
            'latest_run'=>$latest?:null,
            'learning_events'=>array_slice(array_reverse($s['engine_learning']),0,50),
        ]);
    }

    if($method==='PATCH'&&preg_match('#^projects/(\d+)/authoring-profile$#',$path,$m)){
        $b=bodyJson();$p=$s['authoring_profile'];
        foreach(['story_concept','setting','tone','genre','style_preferences','research_requirements','revision_preferences','series_canon'] as $k){
            if(array_key_exists($k,$b)) $p[$k]=ba_authoring_plain((string)$b[$k],20000);
        }
        if(array_key_exists('themes',$b)) $p['themes']=ba_authoring_array_strings($b['themes'],30,160);
        if(array_key_exists('style_influences',$b)&&is_array($b['style_influences'])){
            $in=[];
            foreach($b['style_influences'] as $row){
                if(!is_array($row)) continue;
                $name=ba_authoring_plain((string)($row['name']??$row['author']??''),120);
                if($name==='') continue;
                $percent=$row['percent']??null;
                $in[]=['name'=>$name,'percent'=>$percent===null?null:max(0,min(100,(float)$percent))];
                if(count($in)>=12) break;
            }
            $p['style_influences']=$in;
        }
        if(array_key_exists('target_length_words',$b)) $p['target_length_words']=max(10000,min(300000,(int)$b['target_length_words']));
        foreach(['prologue_policy','epilogue_policy'] as $k){
            if(array_key_exists($k,$b)){
                $v=strtolower((string)$b[$k]);
                $p[$k]=in_array($v,['as_needed','required','forbidden'],true)?$v:'as_needed';
            }
        }
        $p['updated_at']=nowIso();$s['authoring_profile']=$p;
        if(trim((string)($p['genre']??''))!=='') $s['project']['genre']=$p['genre'];
        audit($s,'authoring.profile_updated','project',(int)$m[1],['fields'=>array_keys($b)]);
        saveState($stateFile,$s);respond(['ok'=>true,'profile'=>ba_authoring_profile_public($p)]);
    }

    if($method==='GET'&&preg_match('#^projects/(\d+)/story-graph$#',$path,$m)){
        respond(['ok'=>true,'nodes'=>$s['story_nodes'],'edges'=>$s['story_edges']]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/story-graph/nodes$#',$path,$m)){
        $b=bodyJson();$label=ba_authoring_plain((string)($b['label']??''),240);
        if($label==='') respond(['error'=>'label_required'],422);
        $id='sgn_'.(maxId(array_map(function($n){
            $x=$n;if(isset($x['id'])&&!is_numeric($x['id'])) $x['id']=(int)preg_replace('/\D+/','',(string)$x['id']);
            return $x;
        },$s['story_nodes']))+1);
        $node=[
            'id'=>$id,'project_id'=>(int)$m[1],'chapter_id'=>(int)($b['chapter_id']??0),
            'type'=>strtoupper(ba_authoring_plain((string)($b['type']??'EVENT'),40)),
            'label'=>$label,'state'=>(string)($b['state']??'active'),
            'binding'=>(string)($b['binding']??'guide'),'created_at'=>nowIso(),
        ];
        $s['story_nodes'][]=$node;audit($s,'story_graph.node_created','story_node',count($s['story_nodes']),$node);
        saveState($stateFile,$s);respond(['ok'=>true,'node'=>$node]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/story-graph/edges$#',$path,$m)){
        $b=bodyJson();$from=(string)($b['from']??'');$to=(string)($b['to']??'');
        if($from===''||$to==='') respond(['error'=>'from_and_to_required'],422);
        $ids=array_fill_keys(array_map(fn($n)=>(string)($n['id']??''),$s['story_nodes']),true);
        if(!isset($ids[$from])||!isset($ids[$to])) respond(['error'=>'story_node_not_found'],404);
        $edge=[
            'id'=>'sge_'.(count($s['story_edges'])+1),'from'=>$from,'to'=>$to,
            'relation'=>strtoupper(ba_authoring_plain((string)($b['relation']??'DEPENDS_ON'),50)),
            'binding'=>(string)($b['binding']??'guide'),'created_at'=>nowIso(),
        ];
        $s['story_edges'][]=$edge;audit($s,'story_graph.edge_created','story_edge',count($s['story_edges']),$edge);
        saveState($stateFile,$s);respond(['ok'=>true,'edge'=>$edge]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/authoring-runs$#',$path,$m)){
        $pid=(int)$m[1];$b=bodyJson();$chapterId=(int)($b['chapter_id']??0);
        if($chapterId<1) respond(['error'=>'chapter_id_required'],422);
        $ci=findIndexById($s['chapters']??[],$chapterId);
        if($ci<0) respond(['error'=>'chapter_not_found'],404);
        if(!empty($s['chapters'][$ci]['is_locked'])) respond(['error'=>'chapter_locked'],409);
        $bpChapter=($s['book_blueprint']??null)?ba_bp_find($s['book_blueprint'],$chapterId):null;
        $contract=ba_authoring_contract($s,$chapterId,$bpChapter,$b);
        $runId=maxId($s['authoring_runs'])+1;
        $stages=ba_authoring_preflight($s,$chapterId,$bpChapter);

        $key=(string)$pid;
        $projectLi=is_array($s['literary_intelligence_by_project'][$key]??null)?$s['literary_intelligence_by_project'][$key]:[];
        $styleControl=is_array($projectLi['style_profile']??null)?$projectLi['style_profile']:[];
        if(!isset($styleControl['recent_patterns'])&&is_array($projectLi['recent_patterns']??null)) $styleControl['recent_patterns']=$projectLi['recent_patterns'];
        if(!isset($styleControl['protected_motifs'])&&is_array($projectLi['protected_motifs']??null)) $styleControl['protected_motifs']=$projectLi['protected_motifs'];

        $jobId=maxId($s['write_jobs']??[])+1;$token=bin2hex(random_bytes(32));
        $job=[
            'id'=>$jobId,'project_id'=>$pid,'authoring_run_id'=>$runId,
            'chapter_id'=>$chapterId,'target_words'=>$contract['target_words'],'outline'=>$contract['outline'],
            'pov'=>$contract['pov'],'tense'=>$contract['tense'],'style_source'=>$contract['style_source'],
            'style_influences'=>$contract['style_influences'],'research_policy'=>$contract['research_policy'],
            'instructions'=>$contract['instructions'],'scene_mode'=>$contract['scene_mode'],
            'style_control'=>$styleControl,'editorial_feedback'=>[],
            'context_flags'=>$contract['context_flags'],'guardrails'=>$contract['guardrails'],
            'status'=>'dispatch_pending','message'=>'Unified ten-engine authoring contract preserved. Preparing NOEVA local writing job.',
            'created_at'=>nowIso(),'output_passage_id'=>null,'output_revision'=>null,'core_job_id'=>null,
            'worker_token_hash'=>hash('sha256',$token),'worker_token_pending'=>$token,
            'book_blueprint'=>$s['book_blueprint']??null,'blueprint_id'=>$s['book_blueprint']['id']??null,
            'blueprint_version'=>$s['book_blueprint']['version']??null,'blueprint_chapter'=>$bpChapter,
            'authoring_profile'=>ba_authoring_profile_public($s['authoring_profile']),
            'engine_contract'=>[
                'schema'=>'bookauthor-authoring-contract/1.0','run_id'=>$runId,
                'engines'=>array_column(ba_authoring_engine_registry(),'key'),
                'authority_chain'=>['series_canon','book_blueprint','chapter_contract','story_graph','character_state','writing','humanize','refinement','author_approval','self_improvement'],
            ],
            'generation_contract'=>[
                'outline_authoritative'=>true,'blueprint_authoritative'=>(bool)$bpChapter,
                'binding_semantics'=>['guide'=>'interpret','required'=>'must satisfy','locked'=>'must preserve','forbidden'=>'must not occur'],
                'target_word_count'=>$contract['target_words'],'word_count_tolerance_percent'=>8,
                'preserve_book_style'=>true,'preserve_character_voice'=>true,'style_modulation'=>true,
                'controlled_unpredictability'=>true,'author_style_learning'=>true,'use_story_graph'=>true,
                'use_continuity'=>true,'use_research_evidence'=>true,'never_overwrite_approved_text'=>true,
                'result_requires_author_approval'=>true,'compliance_check_required'=>(bool)$bpChapter,
            ],
        ];
        $run=[
            'id'=>$runId,'project_id'=>$pid,'chapter_id'=>$chapterId,'status'=>'queued',
            'write_job_id'=>$jobId,'write_status'=>'dispatch_pending','contract'=>$contract,'stages'=>$stages,
            'created_at'=>nowIso(),'accepted_passage_id'=>null,'rejected_at'=>null,
        ];
        $s['write_jobs'][]=$job;$s['authoring_runs'][]=$run;
        audit($s,'authoring.run_created','authoring_run',$runId,['chapter_id'=>$chapterId,'write_job_id'=>$jobId]);
        saveState($stateFile,$s);
        ba_dispatch_write($s,count($s['write_jobs'])-1,$stateFile);
        ba_authoring_sync_runs($s,$stateFile);
        $ri=ba_authoring_find_run($s['authoring_runs'],$runId);
        respond(['ok'=>true,'run'=>$s['authoring_runs'][$ri],'message'=>'Ten-engine authoring run started.']);
    }

    if($method==='GET'&&preg_match('#^projects/(\d+)/authoring-runs$#',$path,$m)){
        ba_authoring_sync_runs($s,$stateFile);
        $pid=(int)$m[1];
        respond(['items'=>array_values(array_filter($s['authoring_runs'],fn($r)=>(int)($r['project_id']??0)===$pid))]);
    }

    if($method==='GET'&&preg_match('#^authoring-runs/(\d+)$#',$path,$m)){
        ba_authoring_sync_runs($s,$stateFile);$ri=ba_authoring_find_run($s['authoring_runs'],(int)$m[1]);
        if($ri<0) respond(['error'=>'authoring_run_not_found'],404);
        $run=$s['authoring_runs'][$ri];$job=ba_authoring_find_write_job($s,(int)($run['write_job_id']??0));
        respond(['ok'=>true,'run'=>$run,'write_job'=>$job]);
    }

    if($method==='POST'&&preg_match('#^authoring-runs/(\d+)/accept$#',$path,$m)){
        ba_authoring_sync_runs($s,$stateFile);$id=(int)$m[1];$ri=ba_authoring_find_run($s['authoring_runs'],$id);
        if($ri<0) respond(['error'=>'authoring_run_not_found'],404);
        $run=$s['authoring_runs'][$ri];if(($run['status']??'')!=='draft_ready') respond(['error'=>'draft_not_ready'],409);
        $job=ba_authoring_find_write_job($s,(int)$run['write_job_id']);
        if(!$job||trim((string)($job['generated_draft']??''))==='') respond(['error'=>'generated_draft_unavailable'],409);
        $chapterId=(int)$run['chapter_id'];$newRev=(int)($s['project']['current_revision']??0)+1;
        $parentId=null;
        foreach(array_reverse($s['passages']??[]) as $prior){
            if((int)($prior['chapter_id']??0)===$chapterId&&($prior['language']??'')==='EN'){$parentId=(int)$prior['id'];break;}
        }
        $passage=[
            'id'=>maxId($s['passages']??[])+1,'project_id'=>(int)$run['project_id'],'chapter_id'=>$chapterId,
            'scene_id'=>1,'language'=>'EN','revision'=>$newRev,'approved'=>false,'locked'=>false,
            'parent_passage_id'=>$parentId,'created_at'=>nowIso(),
            'content_html'=>ba_authoring_html_from_draft((string)$job['generated_draft']),
            'source'=>'authoring_run','authoring_run_id'=>$id,
        ];
        $s['passages'][]=$passage;$s['project']['current_revision']=$newRev;$s['project']['updated_at']=nowIso();
        $s['authoring_runs'][$ri]['status']='accepted';
        $s['authoring_runs'][$ri]['accepted_passage_id']=$passage['id'];
        $s['authoring_runs'][$ri]['accepted_at']=nowIso();
        $s['authoring_runs'][$ri]['stages']['self_improvement']=ba_authoring_stage('self_improvement','complete',[
            'author_decision'=>'accepted',
            'learning_event'=>$job['literary_intelligence']['learning_event']??null,
            'patterns_remembered'=>count($job['literary_intelligence']['recent_patterns_next']??[]),
        ]);
        $learning=[
            'id'=>maxId($s['engine_learning'])+1,'project_id'=>(int)$run['project_id'],'run_id'=>$id,
            'event'=>'draft_accepted','chapter_id'=>$chapterId,'revision'=>$newRev,
            'literary_intelligence'=>$job['literary_intelligence']??null,'created_at'=>nowIso(),
        ];
        $s['engine_learning'][]=$learning;
        audit($s,'authoring.draft_accepted','authoring_run',$id,['passage_id'=>$passage['id'],'revision'=>$newRev]);
        saveState($stateFile,$s);
        respond(['ok'=>true,'run'=>$s['authoring_runs'][$ri],'passage'=>$passage]);
    }

    if($method==='POST'&&preg_match('#^authoring-runs/(\d+)/reject$#',$path,$m)){
        ba_authoring_sync_runs($s,$stateFile);$id=(int)$m[1];$ri=ba_authoring_find_run($s['authoring_runs'],$id);
        if($ri<0) respond(['error'=>'authoring_run_not_found'],404);
        $b=bodyJson();$reason=ba_authoring_plain((string)($b['reason']??'Author rejected draft'),2000);
        $s['authoring_runs'][$ri]['status']='rejected';$s['authoring_runs'][$ri]['rejected_at']=nowIso();$s['authoring_runs'][$ri]['rejection_reason']=$reason;
        $s['authoring_runs'][$ri]['stages']['self_improvement']=ba_authoring_stage('self_improvement','complete',[
            'author_decision'=>'rejected','reason'=>$reason,
        ]);
        $s['engine_learning'][]=[
            'id'=>maxId($s['engine_learning'])+1,'project_id'=>(int)($s['authoring_runs'][$ri]['project_id']??1),
            'run_id'=>$id,'event'=>'draft_rejected','reason'=>$reason,'created_at'=>nowIso(),
        ];
        audit($s,'authoring.draft_rejected','authoring_run',$id,['reason'=>$reason]);saveState($stateFile,$s);
        respond(['ok'=>true,'run'=>$s['authoring_runs'][$ri]]);
    }
}
