<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');

$stateFile = __DIR__ . '/data/state.json';

function worker_respond(array $payload, int $code=200): never {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function worker_state(string $path): array {
    $raw=@file_get_contents($path);
    $state=$raw?json_decode($raw,true):null;
    return is_array($state)?$state:[];
}
function worker_match_token(?string $hash, string $token): bool {
    if(!$hash || $token==='') return false;
    return hash_equals((string)$hash, hash('sha256',$token));
}
function worker_find(array $rows, int $id): ?array {
    foreach($rows as $row){ if((int)($row['id']??0)===$id) return $row; }
    return null;
}
function worker_latest_passages(array $state, int $max=12): array {
    $rows=[];
    foreach(array_reverse($state['passages']??[]) as $p){
        if(($p['language']??'')!=='EN') continue;
        $rows[]=[
            'chapter_id'=>$p['chapter_id']??null,
            'scene_id'=>$p['scene_id']??null,
            'revision'=>$p['revision']??null,
            'content'=>mb_substr(trim(strip_tags((string)($p['content_html']??''))),0,12000),
        ];
        if(count($rows)>=$max) break;
    }
    return array_reverse($rows);
}

function worker_recent_prose(array $state, int $max=8): string {
    $parts=[];
    foreach(worker_latest_passages($state,$max) as $p){
        $text=trim((string)($p['content']??''));
        if($text!=='') $parts[]=$text;
    }
    $joined=implode("\n\n",$parts);
    return mb_substr($joined,max(0,mb_strlen($joined)-24000));
}

$kind=strtolower(trim((string)($_GET['kind']??'')));
$id=(int)($_GET['id']??0);
$token=(string)($_GET['token']??'');
if(!in_array($kind,['analysis','write','translate','historical','naming'],true) || $id<1 || $token===''){
    worker_respond(['ok'=>false,'error'=>'invalid_worker_request'],400);
}
$state=worker_state($stateFile);
if(!$state) worker_respond(['ok'=>false,'error'=>'state_unavailable'],503);

if($kind==='analysis'){
    $job=null;
    foreach($state['analysis_jobs']??[] as $row){
        if((int)($row['import_id']??0)===$id){ $job=$row; break; }
    }
    $import=worker_find($state['imports']??[],$id);
    if(!$job || !$import || !worker_match_token($job['worker_token_hash']??null,$token)){
        worker_respond(['ok'=>false,'error'=>'worker_authorization_failed'],401);
    }
    $textName=basename((string)($import['extracted_text_file']??''));
    $projectId=(int)($import['project_id']??1);
    if($textName==='') worker_respond(['ok'=>false,'error'=>'extracted_text_unavailable'],409);
    $textPath=__DIR__.'/data/imports/project_'.$projectId.'/'.$textName;
    $text=@file_get_contents($textPath);
    if($text===false || trim($text)==='') worker_respond(['ok'=>false,'error'=>'extracted_text_unavailable'],409);
    worker_respond([
        'kind'=>'analysis',
        'project_id'=>$projectId,
        'import_id'=>$id,
        'original_name'=>$import['original_name']??'manuscript',
        'optimization_depth'=>$job['optimization_depth']??'editorial',
        'scopes'=>$job['scopes']??[],
        'structural_scan'=>$import['structural_scan']??[],
        'book_blueprint'=>$state['book_blueprint']??null,
        'blueprint_authority'=>['guide'=>'interpret creatively','required'=>'must satisfy','locked'=>'must not contradict or relocate','forbidden'=>'must not occur'],
        'manuscript_text'=>$text,
        'immutable_original'=>true,
    ]);
}


if(in_array($kind,['translate','historical','naming'],true)){
    $bucket=$kind==='translate'?'translation_jobs':($kind==='historical'?'historical_jobs':'naming_jobs');
    $job=worker_find($state[$bucket]??[],$id);
    if(!$job || !worker_match_token($job['worker_token_hash']??null,$token)){
        worker_respond(['ok'=>false,'error'=>'worker_authorization_failed'],401);
    }
    $projectId=(int)($job['project_id']??1);
    $canonical=[
        'project'=>$state['project']??[],
        'authoring_profile'=>$state['authoring_profile']??[],
        'characters'=>$state['characters']??[],
        'relationships'=>$state['relationships']??[],
        'story_nodes'=>$state['story_nodes']??[],
        'story_edges'=>$state['story_edges']??[],
        'book_blueprint'=>$state['book_blueprint']??null,
        'research_claims'=>$state['research']??[],
        'historical_claims'=>$state['historical_claims']??[],
        'recent_manuscript'=>worker_latest_passages($state,8),
    ];

    if($kind==='translate'){
        $source=worker_find($state['passages']??[],(int)($job['source_passage_id']??0));
        if(!$source) worker_respond(['ok'=>false,'error'=>'source_passage_not_found'],404);
        $sourceText=trim(html_entity_decode(strip_tags((string)($source['content_html']??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        if($sourceText==='') worker_respond(['ok'=>false,'error'=>'source_passage_empty'],409);
        worker_respond([
            'kind'=>'translate',
            'project_id'=>$projectId,
            'translation_job_id'=>$id,
            'source_passage_id'=>$source['id'],
            'source_revision'=>$source['revision']??null,
            'source_language'=>strtoupper((string)($job['source_language']??$source['language']??'EN')),
            'target_language'=>strtoupper((string)($job['target_language']??'DE')),
            'source_text'=>$sourceText,
            'protected_ambiguities'=>$job['protected_ambiguities']??[],
            'terminology'=>$state['translation_terminology']??[],
            'canonical_context'=>$canonical,
        ]);
    }

    if($kind==='historical'){
        $parts=[];
        $passageId=(int)($job['passage_id']??0);
        $chapterId=(int)($job['chapter_id']??0);
        if($passageId>0){
            $p=worker_find($state['passages']??[],$passageId);
            if(!$p) worker_respond(['ok'=>false,'error'=>'historical_passage_not_found'],404);
            $parts[]=trim(html_entity_decode(strip_tags((string)($p['content_html']??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'));
            if($chapterId<1)$chapterId=(int)($p['chapter_id']??0);
        } else {
            foreach($state['passages']??[] as $p){
                if((int)($p['chapter_id']??0)===$chapterId && strtoupper((string)($p['language']??''))==='EN'){
                    $parts[]=trim(html_entity_decode(strip_tags((string)($p['content_html']??'')),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                }
            }
        }
        $text=trim(implode("\n\n",$parts));
        if($text==='') worker_respond(['ok'=>false,'error'=>'historical_scope_empty'],409);
        $evidence=[];
        foreach($state['historical_claims']??[] as $claim){
            foreach($claim['evidence']??[] as $ev){
                if(is_array($ev))$evidence[]=['id'=>$ev['id']??null,'claim_id'=>$claim['id']??null]+$ev;
            }
        }
        worker_respond([
            'kind'=>'historical',
            'project_id'=>$projectId,
            'historical_job_id'=>$id,
            'passage_id'=>$passageId?:null,
            'chapter_id'=>$chapterId?:null,
            'text'=>mb_substr($text,0,60000),
            'setting'=>[
                'setting'=>$state['authoring_profile']['setting']??'',
                'genre'=>$state['authoring_profile']['genre']??($state['project']['genre']??''),
            ],
            'evidence_records'=>array_slice($evidence,0,120),
            'canonical_context'=>$canonical,
        ]);
    }

    worker_respond([
        'kind'=>'naming',
        'project_id'=>$projectId,
        'naming_job_id'=>$id,
        'naming_kind'=>$job['naming_kind']??'character',
        'target_character_id'=>$job['target_character_id']??null,
        'constraints'=>$job['constraints']??[],
        'avoid'=>$job['avoid']??[],
        'canonical_context'=>$canonical,
    ]);
}

$job=worker_find($state['write_jobs']??[],$id);
if(!$job || !worker_match_token($job['worker_token_hash']??null,$token)){
    worker_respond(['ok'=>false,'error'=>'worker_authorization_failed'],401);
}
$projectId=(int)($job['project_id']??1);
$literaryByProject=is_array($state['literary_intelligence_by_project']??null)?$state['literary_intelligence_by_project']:[];
$literary=is_array($literaryByProject[(string)$projectId]??null)?$literaryByProject[(string)$projectId]:[];
$canonical=[
    'project'=>$state['project']??[],
    'characters'=>$state['characters']??[],
    'relationships'=>$state['relationships']??[],
    'story_nodes'=>$state['story_nodes']??[],
    'story_edges'=>$state['story_edges']??[],
    'research_claims'=>$state['research']??[],
    'book_blueprint'=>$state['book_blueprint']??null,
    'recent_manuscript'=>worker_latest_passages($state,12),
    'recent_prose'=>worker_recent_prose($state,8),
    'literary_intelligence'=>$literary,
    'authoring_profile'=>$job['authoring_profile']??($state['authoring_profile']??[]),
    'engine_contract'=>$job['engine_contract']??[],
];
worker_respond([
    'kind'=>'write',
    'project_id'=>$projectId,
    'write_job_id'=>$id,
    'chapter_id'=>$job['chapter_id']??null,
    'target_words'=>$job['target_words']??2500,
    'outline'=>$job['outline']??'',
    'pov'=>$job['pov']??'Use book canon',
    'tense'=>$job['tense']??'Use book canon',
    'style_source'=>$job['style_source']??'Use approved book style profile',
    'style_influences'=>$job['style_influences']??[],
    'research_policy'=>$job['research_policy']??'Respect verified facts; flag unknowns',
    'instructions'=>$job['instructions']??'',
    'scene_mode'=>$job['scene_mode']??'ordinary',
    'style_control'=>$job['style_control']??($literary['style_profile']??($literary['style_control']??[])),
    'author_style_memory'=>$literary['author_style_memory']??[],
    'editorial_feedback'=>$job['editorial_feedback']??[],
    'recent_patterns'=>$literary['recent_patterns']??[],
    'protected_motifs'=>$literary['protected_motifs']??[],
    'recent_prose'=>$canonical['recent_prose'],
    'context_flags'=>$job['context_flags']??[],
    'guardrails'=>$job['guardrails']??[],
    'canonical_context'=>$canonical,
    'book_blueprint'=>$state['book_blueprint']??null,
    'blueprint_chapter'=>$job['blueprint_chapter']??null,
    'blueprint_version'=>$job['blueprint_version']??null,
    'generation_contract'=>$job['generation_contract']??[],
    'engine_contract'=>$job['engine_contract']??[],
    'authoring_profile'=>$job['authoring_profile']??($state['authoring_profile']??[]),
    'authoring_run_id'=>$job['authoring_run_id']??null,
]);
