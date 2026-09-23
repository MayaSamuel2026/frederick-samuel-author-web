<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/extensions.php';
require_once __DIR__ . '/blueprint.php';
require_once __DIR__ . '/authoring.php';
require_once __DIR__ . '/specialist.php';
studio_gate_api();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$dataDir = __DIR__ . '/data';
$stateFile = $dataDir . '/state.json';
if (!is_dir($dataDir)) { @mkdir($dataDir, 0770, true); }

function nowIso(): string { return gmdate('c'); }
function respond($data, int $code=200): never { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function bodyJson(): array { $raw=file_get_contents('php://input'); if(!$raw) return []; $j=json_decode($raw,true); return is_array($j)?$j:[]; }
function seedState(): array {
  $ts=nowIso();
  return [
    'project'=>['id'=>1,'title'=>'The Winter Cartographer','genre'=>'Historical literary suspense','primary_language'=>'EN','translation_language'=>'DE','current_revision'=>18,'updated_at'=>$ts],
    'chapters'=>[
      ['id'=>1,'chapter_no'=>1,'title'=>'The Letter','approved_revision'=>null,'is_locked'=>false],
      ['id'=>2,'chapter_no'=>2,'title'=>'Frozen Coordinates','approved_revision'=>null,'is_locked'=>false],
      ['id'=>3,'chapter_no'=>3,'title'=>'Before Sunrise','approved_revision'=>null,'is_locked'=>false],
      ['id'=>4,'chapter_no'=>4,'title'=>'A Name in the Ledger','approved_revision'=>null,'is_locked'=>false],
      ['id'=>5,'chapter_no'=>5,'title'=>'Border Country','approved_revision'=>null,'is_locked'=>false],
      ['id'=>6,'chapter_no'=>6,'title'=>'The Archivist','approved_revision'=>null,'is_locked'=>false]
    ],
    'passages'=>[
      ['id'=>1,'project_id'=>1,'chapter_id'=>3,'scene_id'=>1,'language'=>'EN','revision'=>18,'approved'=>false,'locked'=>false,'parent_passage_id'=>null,'created_at'=>$ts,'content_html'=>'<p>The station lamps made islands in the fog. Beyond them, the tracks disappeared east, toward a border that had already changed twice in Elias’s lifetime.</p><p>Mara stood beneath the clock with one gloved hand closed around the envelope. She had read the name on it three times and still did not believe it belonged to her.</p><p>“You should go,” Elias said.</p><p>She looked at him then. <span class="note">There was something in his face she had not seen before</span>—not fear exactly, but the expression of a man who had finally understood the price of being right.</p><p><span class="hl">He left shortly before sunrise, without saying goodbye.</span></p><p>The train arrived six minutes later.</p>'],
      ['id'=>2,'project_id'=>1,'chapter_id'=>3,'scene_id'=>1,'language'=>'DE','revision'=>11,'approved'=>true,'locked'=>true,'parent_passage_id'=>null,'created_at'=>$ts,'content_html'=>'<p>Die Lampen am Bahnhof zeichneten Inseln in den Nebel. Dahinter verloren sich die Gleise nach Osten, in Richtung einer Grenze, die sich schon zweimal in Elias’ Leben verschoben hatte.</p><p>Mara stand unter der Uhr, eine behandschuhte Hand um den Umschlag geschlossen.</p><p>Er ging vor Sonnenaufgang, ohne sich zu verabschieden.</p>']
    ],
    'characters'=>[
      ['id'=>1,'name'=>'Mara Weiss','role'=>'Protagonist','state'=>['voice'=>'short declaratives under pressure','arc'=>'certainty → destabilization → agency','trust_elias'=>74]],
      ['id'=>2,'name'=>'Elias Varga','role'=>'Mentor','state'=>['voice'=>'compressed speech','arc'=>'guide → compromised witness → absence']],
      ['id'=>3,'name'=>'Anna Heller','role'=>'Archivist ally','state'=>['voice'=>'dry humour','arc'=>'observer → reluctant accomplice']],
      ['id'=>4,'name'=>'Konrad Bale','role'=>'Institutional antagonist','state'=>['voice'=>'polite, controlled','arc'=>'invisible pressure → direct intervention']]
    ],
    'research'=>[
      ['id'=>1,'claim'=>'Railway clocks used standardized timetable time.','status'=>'verified','confidence'=>'High','usage_location'=>'Chapter 3','source_note'=>'Two corroborating source notes attached.','queued'=>false],
      ['id'=>2,'claim'=>'Six-minute connection margin at Rosenfeld Station.','status'=>'check','confidence'=>'Medium','usage_location'=>'Chapter 3','source_note'=>'Requires timetable-specific evidence for fictionalized route analogue.','queued'=>false],
      ['id'=>3,'claim'=>'Local administrative usage of the pre-1938 border name.','status'=>'contested','confidence'=>'Medium','usage_location'=>'Chapters 2–5','source_note'=>'Sources differ by institution and publication date.','queued'=>false],
      ['id'=>4,'claim'=>'Paper identity documents commonly carried official seals.','status'=>'verified','confidence'=>'High','usage_location'=>'Chapter 5','source_note'=>'Material-culture reference captured.','queued'=>false],
      ['id'=>5,'claim'=>'Phrase “security clearance” appears in draft dialogue.','status'=>'anachronism','confidence'=>'High','usage_location'=>'Chapter 8','source_note'=>'Terminology predates setting in current usage.','queued'=>false]
    ],
    'translation_patches'=>[
      ['id'=>1,'project_id'=>1,'source_passage_id'=>1,'target_passage_id'=>2,'status'=>'pending','source_revision'=>18,'target_revision'=>11,'proposed_html'=>'<p>Er ging <ins>kurz</ins> vor Sonnenaufgang, ohne sich zu verabschieden.</p>','nuance'=>['register'=>'restrained','subtext'=>'farewell withheld','emotional_temperature'=>'cool/high pressure','rhythm'=>'clipped ending','material'=>true],'created_at'=>$ts]
    ],
    'semantic_diffs'=>[['id'=>1,'source_passage_id'=>1,'prior_revision'=>17,'new_revision'=>18,'material'=>true,'summary'=>'Added temporal precision and explicit relational action.','created_at'=>$ts]],
    'audit'=>[['id'=>1,'event_type'=>'seed','object_type'=>'project','object_id'=>1,'payload'=>['version'=>'1.1'],'created_at'=>$ts]],
    'exports'=>[]
  ];
}
function loadState(string $file): array {
  if(!file_exists($file)) { $s=seedState(); saveState($file,$s); return $s; }
  $raw=@file_get_contents($file); $j=$raw?json_decode($raw,true):null;
  if(!is_array($j)) { $s=seedState(); saveState($file,$s); return $s; }
  return $j;
}
function saveState(string $file, array $state): void {
  $tmp=$file.'.tmp'; $json=json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if(file_put_contents($tmp,$json,LOCK_EX)===false) respond(['error'=>'storage_write_failed'],500);
  @chmod($tmp,0660); if(!@rename($tmp,$file)) respond(['error'=>'storage_commit_failed'],500);
}
function audit(array &$s,string $type,string $obj,int $id,array $payload=[]): void {
  $s['audit'][]=['id'=>count($s['audit'])+1,'event_type'=>$type,'object_type'=>$obj,'object_id'=>$id,'payload'=>$payload,'created_at'=>nowIso()];
}
function maxId(array $items): int { $m=0; foreach($items as $x){$m=max($m,(int)($x['id']??0));} return $m; }
function findIndexById(array $items,int $id): int { foreach($items as $i=>$x){ if((int)($x['id']??0)===$id) return $i; } return -1; }

$method=$_SERVER['REQUEST_METHOD'] ?? 'GET';
$path=trim((string)($_GET['path'] ?? ''),'/');
$s=loadState($stateFile);
ba_specialist_api($s,$stateFile,$method,$path,$dataDir);
ba_authoring_api($s,$stateFile,$method,$path,$dataDir);
ba_blueprint_api($s,$stateFile,$method,$path,$dataDir);
ba_extended_api($s,$stateFile,$method,$path,$dataDir);

if($method==='GET' && $path==='health') respond(['ok'=>true,'service'=>'book-author-studio','version'=>'1.6.1','specialist_intelligence'=>true,'historical_research_frontier'=>true]);
if($method==='GET' && $path==='projects') respond(['items'=>[$s['project']]]);
if($method==='GET' && preg_match('#^projects/(\d+)/workspace$#',$path,$m)){
  $current=null; foreach(array_reverse($s['passages']) as $p){ if($p['language']==='EN'){ $current=$p; break; } }
  respond(['project'=>$s['project'],'current_passage'=>$current,'chapters'=>$s['chapters']]);
}
if($method==='PATCH' && preg_match('#^passages/(\d+)$#',$path,$m)){
  $id=(int)$m[1]; $idx=findIndexById($s['passages'],$id); if($idx<0) respond(['error'=>'Passage not found'],404);
  $old=$s['passages'][$idx]; if(!empty($old['locked'])) respond(['error'=>'Locked passages are immutable'],409);
  $b=bodyJson(); $content=(string)($b['content_html']??''); if($content==='') respond(['error'=>'content_html required'],422);
  $newRev=max((int)$s['project']['current_revision']+1,(int)$old['revision']+1);
  $new=$old; $new['id']=maxId($s['passages'])+1; $new['revision']=$newRev; $new['content_html']=$content; $new['parent_passage_id']=$old['id']; $new['approved']=false; $new['locked']=false; $new['created_at']=nowIso();
  $s['passages'][]=$new; $s['project']['current_revision']=$newRev; $s['project']['updated_at']=nowIso();
  $material=$old['content_html']!==$content; $s['semantic_diffs'][]=['id'=>maxId($s['semantic_diffs'])+1,'source_passage_id'=>$new['id'],'prior_revision'=>$old['revision'],'new_revision'=>$newRev,'material'=>$material,'summary'=>$material?'Passage content changed; semantic materiality requires engine review.':'No content change.','created_at'=>nowIso()];
  audit($s,'passage.revision_created','passage',$new['id'],['parent_passage_id'=>$old['id'],'from_revision'=>$old['revision'],'to_revision'=>$newRev]); saveState($stateFile,$s);
  respond(['ok'=>true,'passage_id'=>$new['id'],'revision'=>$newRev,'material'=>$material]);
}
if($method==='GET' && preg_match('#^projects/(\d+)/characters$#',$path)) respond(['items'=>$s['characters']]);
if($method==='GET' && preg_match('#^projects/(\d+)/research$#',$path)) respond(['items'=>$s['research']]);
if($method==='POST' && preg_match('#^research/(\d+)/queue$#',$path,$m)){
  $id=(int)$m[1]; $idx=findIndexById($s['research'],$id); if($idx<0) respond(['error'=>'Claim not found'],404);
  $s['research'][$idx]['queued']=true; audit($s,'research.queued','research_claim',$id,['claim'=>$s['research'][$idx]['claim']]); saveState($stateFile,$s); respond(['ok'=>true,'claim_id'=>$id,'queued'=>true]);
}
if($method==='GET' && preg_match('#^projects/(\d+)/translation-patches$#',$path)){
  $status=(string)($_GET['status']??'pending'); $items=array_values(array_filter($s['translation_patches'],fn($p)=>$p['status']===$status)); respond(['items'=>$items]);
}
if($method==='POST' && preg_match('#^translation-patches/(\d+)/accept$#',$path,$m)){
  $id=(int)$m[1]; $pi=findIndexById($s['translation_patches'],$id); if($pi<0) respond(['error'=>'Patch not found'],404); $patch=$s['translation_patches'][$pi]; if($patch['status']!=='pending') respond(['error'=>'Patch already resolved'],409);
  $ti=findIndexById($s['passages'],(int)$patch['target_passage_id']); if($ti<0) respond(['error'=>'Target passage not found'],404); $target=$s['passages'][$ti]; $newRev=(int)$target['revision']+1;
  $new=$target; $new['id']=maxId($s['passages'])+1; $new['revision']=$newRev; $new['content_html']=$patch['proposed_html']; $new['approved']=false; $new['locked']=false; $new['parent_passage_id']=$target['id']; $new['created_at']=nowIso(); $s['passages'][]=$new;
  $s['translation_patches'][$pi]['status']='accepted'; $s['translation_patches'][$pi]['accepted_at']=nowIso(); audit($s,'translation_patch.accepted','translation_patch',$id,['prior_target_passage_id'=>$target['id'],'new_target_passage_id'=>$new['id'],'new_target_revision'=>$newRev]); saveState($stateFile,$s);
  respond(['ok'=>true,'new_target_passage_id'=>$new['id'],'target_revision'=>$newRev]);
}
if($method==='POST' && preg_match('#^chapters/(\d+)/approve$#',$path,$m)){
  $id=(int)$m[1]; $ci=findIndexById($s['chapters'],$id); if($ci<0) respond(['error'=>'Chapter not found'],404); $rev=(int)$s['project']['current_revision']; $s['chapters'][$ci]['approved_revision']=$rev; $s['chapters'][$ci]['is_locked']=true;
  foreach($s['passages'] as &$p){ if((int)$p['chapter_id']===$id && $p['language']==='EN' && (int)$p['revision']===$rev){$p['approved']=true;$p['locked']=true;} } unset($p);
  audit($s,'chapter.approved','chapter',$id,['approved_revision'=>$rev]); saveState($stateFile,$s); respond(['ok'=>true,'chapter_id'=>$id,'approved_revision'=>$rev]);
}
if($method==='GET' && preg_match('#^projects/(\d+)/audit$#',$path)) respond(['items'=>array_reverse($s['audit'])]);
if($method==='POST' && preg_match('#^projects/(\d+)/exports$#',$path,$m)){
  $b=bodyJson(); $id=maxId($s['exports'])+1; $manifest=['project'=>$s['project']['title'],'language'=>$b['language']??'bilingual','format'=>$b['format']??'project-package','include_historical_notes'=>(bool)($b['include_historical_notes']??true),'include_audit'=>(bool)($b['include_audit']??true),'created_at'=>nowIso()];
  $s['exports'][]=['id'=>$id,'manifest'=>$manifest]; audit($s,'export.created','export',$id,$manifest); saveState($stateFile,$s); respond(['ok'=>true,'export_id'=>$id,'manifest'=>$manifest]);
}
respond(['error'=>'Not found','method'=>$method,'path'=>$path],404);
