<?php
declare(strict_types=1);

function ba_bp_binding(string $v): string {
    $v=strtolower(trim($v));
    return in_array($v,['guide','required','locked','forbidden'],true)?$v:'required';
}
function ba_bp_clean(string $v): string {
    $v=str_replace("\0",'',trim($v));
    return trim(preg_replace('/[ \t]+/u',' ',$v)??$v);
}
function ba_bp_list(string $v): array {
    $p=preg_split('/\s*(?:,|;|\||•)\s*/u',trim($v))?:[];
    return array_values(array_filter(array_map('ba_bp_clean',$p),fn($x)=>$x!==''));
}
function ba_bp_instruction(string $text,string $binding='required',string $category='general'): array {
    return ['id'=>bin2hex(random_bytes(5)),'binding'=>ba_bp_binding($binding),'category'=>$category,'text'=>ba_bp_clean($text),'source'=>'author_blueprint'];
}
function ba_bp_header(string $line): ?array {
    $line=trim($line);
    if(preg_match('/^(prologue|epilogue|interlude)\b\s*[:\-–—]?\s*(.*)$/iu',$line,$m))
        return ['kind'=>strtolower($m[1]),'number'=>null,'title'=>ba_bp_clean($m[2]?:ucfirst($m[1]))];
    if(preg_match('/^(?:#{1,3}\s*)?(?:chapter|kapitel|глава)\s+([0-9IVXLCDM]+)\b\s*[:.\-–—]?\s*(.*)$/iu',$line,$m))
        return ['kind'=>'chapter','number'=>$m[1],'title'=>ba_bp_clean($m[2]?:('Chapter '.$m[1]))];
    return null;
}
function ba_bp_parse_chapter(array $h,array $lines,int $order): array {
    $c=['id'=>'bpch_'.$order,'order'=>$order,'kind'=>$h['kind'],'source_number'=>$h['number'],'title'=>$h['title'],'binding'=>'required','order_locked'=>true,'synopsis'=>'','purpose'=>'','pov'=>'','location'=>'','timeline'=>'','hook'=>'','style_notes'=>'','target_words'=>null,'characters'=>[],'objects'=>[],'clues'=>[],'research'=>[],'instructions'=>[]];
    $free=[]; $mode=null;
    foreach($lines as $raw){
        $line=trim(preg_replace('/^[\-*•]\s*/u','',trim($raw))??trim($raw)); if($line==='')continue;
        if(preg_match('/^\[(GUIDE|REQUIRED|LOCKED|FORBIDDEN)\]\s*(.+)$/iu',$line,$m)){ $c['instructions'][]=ba_bp_instruction($m[2],strtolower($m[1])); $mode=null; continue; }
        if(preg_match('/^(guide|required|locked|forbidden)\s*:\s*(.+)$/iu',$line,$m)){ $c['instructions'][]=ba_bp_instruction($m[2],strtolower($m[1])); $mode=strtolower($m[1]); continue; }
        if(preg_match('/^(synopsis|summary)\s*:\s*(.*)$/iu',$line,$m)){ $c['synopsis']=ba_bp_clean($m[2]);$mode='synopsis';continue; }
        if(preg_match('/^(purpose|chapter purpose)\s*:\s*(.*)$/iu',$line,$m)){ $c['purpose']=ba_bp_clean($m[2]);$mode='purpose';continue; }
        if(preg_match('/^pov\s*:\s*(.*)$/iu',$line,$m)){ $c['pov']=ba_bp_clean($m[1]);$mode=null;continue; }
        if(preg_match('/^(location|setting)\s*:\s*(.*)$/iu',$line,$m)){ $c['location']=ba_bp_clean($m[2]);$mode=null;continue; }
        if(preg_match('/^(timeline|time|date)\s*:\s*(.*)$/iu',$line,$m)){ $c['timeline']=ba_bp_clean($m[2]);$mode=null;continue; }
        if(preg_match('/^(hook|ending|exit hook)\s*:\s*(.*)$/iu',$line,$m)){ $c['hook']=ba_bp_clean($m[2]);$mode='hook';continue; }
        if(preg_match('/^(style|style notes?)\s*:\s*(.*)$/iu',$line,$m)){ $c['style_notes']=ba_bp_clean($m[2]);$mode='style_notes';continue; }
        if(preg_match('/^(target words?|word target|length)\s*:\s*([0-9,\.]+)/iu',$line,$m)){ $c['target_words']=(int)preg_replace('/\D/','',$m[2]);$mode=null;continue; }
        if(preg_match('/^characters?\s*:\s*(.*)$/iu',$line,$m)){ $c['characters']=array_values(array_unique(array_merge($c['characters'],ba_bp_list($m[1]))));$mode='characters';continue; }
        if(preg_match('/^objects?\s*:\s*(.*)$/iu',$line,$m)){ $c['objects']=array_values(array_unique(array_merge($c['objects'],ba_bp_list($m[1]))));$mode='objects';continue; }
        if(preg_match('/^clues?\s*:\s*(.*)$/iu',$line,$m)){ $c['clues']=array_values(array_unique(array_merge($c['clues'],ba_bp_list($m[1]))));$mode='clues';continue; }
        if(preg_match('/^(research|fact check|historical check)\s*:\s*(.*)$/iu',$line,$m)){ if(ba_bp_clean($m[2])!=='')$c['research'][]=ba_bp_clean($m[2]);$mode='research';continue; }
        if(in_array($mode,['guide','required','locked','forbidden'],true)){ $c['instructions'][]=ba_bp_instruction($line,$mode);continue; }
        if($mode==='research'){ $c['research'][]=ba_bp_clean($line);continue; }
        if(in_array($mode,['synopsis','purpose','hook','style_notes'],true)){ $c[$mode]=trim($c[$mode].' '.$line);continue; }
        $free[]=$line;
    }
    if($c['synopsis']===''&&$free)$c['synopsis']=implode(' ',$free);
    elseif($free)$c['instructions'][]=ba_bp_instruction(implode(' ',$free),'required','outline');
    if($c['purpose']!=='')$c['instructions'][]=ba_bp_instruction($c['purpose'],'required','purpose');
    if($c['hook']!=='')$c['instructions'][]=ba_bp_instruction($c['hook'],'required','hook');
    foreach($c['research'] as $r)$c['instructions'][]=ba_bp_instruction($r,'required','research');
    return $c;
}
function ba_bp_parse(string $text): array {
    $text=trim(str_replace(["\r\n","\r"],"\n",$text));
    $json=json_decode($text,true);
    if(is_array($json)&&is_array($json['chapters']??null)){
        $chapters=[];$n=1;
        foreach($json['chapters'] as $r){
            if(!is_array($r))continue; $ins=[];
            foreach(($r['instructions']??[]) as $x){if(is_string($x))$ins[]=ba_bp_instruction($x);elseif(is_array($x)&&trim((string)($x['text']??''))!=='')$ins[]=ba_bp_instruction((string)$x['text'],(string)($x['binding']??'required'),(string)($x['category']??'general'));}
            foreach(['guide','required','locked','forbidden'] as $b)foreach(($r[$b]??[]) as $x)if(is_string($x)&&trim($x)!=='')$ins[]=ba_bp_instruction($x,$b);
            $chapters[]=['id'=>'bpch_'.$n,'order'=>$n,'kind'=>(string)($r['kind']??'chapter'),'source_number'=>$r['number']??$n,'title'=>(string)($r['title']??('Chapter '.$n)),'binding'=>ba_bp_binding((string)($r['binding']??'required')),'order_locked'=>(bool)($r['order_locked']??true),'synopsis'=>(string)($r['synopsis']??$r['summary']??''),'purpose'=>(string)($r['purpose']??''),'pov'=>(string)($r['pov']??''),'location'=>(string)($r['location']??''),'timeline'=>(string)($r['timeline']??''),'hook'=>(string)($r['hook']??''),'style_notes'=>(string)($r['style_notes']??''),'target_words'=>isset($r['target_words'])?(int)$r['target_words']:null,'characters'=>array_values($r['characters']??[]),'objects'=>array_values($r['objects']??[]),'clues'=>array_values($r['clues']??[]),'research'=>array_values($r['research']??[]),'instructions'=>$ins];$n++;
        }
        return ['chapters'=>$chapters,'preamble'=>'','parse_notes'=>['Structured JSON blueprint imported.']];
    }
    $lines=preg_split('/\n/u',$text)?:[];$groups=[];$header=null;$buf=[];$preamble=[];
    foreach($lines as $line){$h=ba_bp_header($line);if($h){if($header!==null)$groups[]=[$header,$buf];elseif($buf)$preamble=array_merge($preamble,$buf);$header=$h;$buf=[];}else{$buf[]=$line;}}
    if($header!==null)$groups[]=[$header,$buf];else{$groups[]=[['kind'=>'chapter','number'=>1,'title'=>'Chapter 1'],$lines];}
    $chapters=[];$n=1;foreach($groups as [$h,$ls])$chapters[]=ba_bp_parse_chapter($h,$ls,$n++);
    return ['chapters'=>$chapters,'preamble'=>trim(implode("\n",$preamble)),'parse_notes'=>$preamble?['Book-level preamble preserved.']:[]];
}
function ba_bp_refresh_research(array &$bp): void {
    $out=[];$seen=[];
    foreach($bp['chapters']??[] as $c)foreach($c['research']??[] as $r){$k=mb_strtolower(trim((string)$r));if($k===''||isset($seen[$k]))continue;$seen[$k]=1;$out[]=['id'=>'bpr_'.(count($out)+1),'chapter_id'=>$c['id'],'chapter_order'=>$c['order'],'claim'=>$r,'status'=>'unverified','binding'=>'required'];}
    $bp['research_obligations']=$out;
}
function ba_bp_find(?array $bp,int $chapterId): ?array {
    if(!$bp)return null;
    foreach($bp['chapters']??[] as $c)if((int)($c['order']??0)===$chapterId||(int)($c['source_number']??0)===$chapterId)return $c;
    return null;
}
function ba_bp_outline(array $c): string {
    $a=['Chapter '.($c['order']??'').' — '.($c['title']??'')];
    foreach(['synopsis'=>'Synopsis','purpose'=>'Purpose','pov'=>'POV','location'=>'Location','timeline'=>'Timeline','hook'=>'Exit hook','style_notes'=>'Style'] as $k=>$label)if(trim((string)($c[$k]??''))!=='')$a[]=$label.': '.$c[$k];
    if(!empty($c['characters']))$a[]='Characters: '.implode(', ',$c['characters']);
    if(!empty($c['objects']))$a[]='Objects: '.implode(', ',$c['objects']);
    if(!empty($c['clues']))$a[]='Clues: '.implode(', ',$c['clues']);
    foreach($c['instructions']??[] as $i)$a[]='['.strtoupper((string)($i['binding']??'required')).'] '.($i['text']??'');
    return trim(implode("\n",$a));
}
function ba_bp_compliance(array $s,array $bp): array {
    $out=[];
    foreach($s['write_jobs']??[] as $j){$c=ba_bp_find($bp,(int)($j['chapter_id']??0));if(!$c)continue;$p=ba_extract_task_payload($j['core_result']??[]);$out[]=['write_job_id'=>$j['id'],'chapter_id'=>$c['id'],'chapter_order'=>$c['order'],'title'=>$c['title'],'status'=>$j['status']??'unknown','compliance'=>is_array($p['blueprint_compliance']??null)?$p['blueprint_compliance']:null,'updated_at'=>$j['completed_at']??$j['created_at']??null];}
    return $out;
}
function ba_blueprint_api(array &$s,string $stateFile,string $method,string $path,string $dataDir): void {
    $s['book_blueprint']=$s['book_blueprint']??null;$s['blueprint_history']=$s['blueprint_history']??[];
    if($method==='GET'&&preg_match('#^projects/(\d+)/blueprint$#',$path)){respond(['blueprint'=>$s['book_blueprint'],'history'=>$s['blueprint_history'],'compliance'=>$s['book_blueprint']?ba_bp_compliance($s,$s['book_blueprint']):[]]);}
    if($method==='GET'&&preg_match('#^projects/(\d+)/blueprint/engine-context$#',$path)){
        if(!$s['book_blueprint'])respond(['error'=>'blueprint_not_found'],404);$e=strtolower((string)($_GET['engine']??'story'));$bp=$s['book_blueprint'];
        respond(['engine'=>$e,'context'=>['blueprint_id'=>$bp['id'],'version'=>$bp['version'],'authority'=>'author_approved_plan','chapters'=>$bp['chapters'],'research_obligations'=>$bp['research_obligations']??[],'rules'=>['guide'=>'interpret creatively','required'=>'must satisfy','locked'=>'must not contradict or relocate','forbidden'=>'must not occur']]]);
    }
    if($method==='POST'&&preg_match('#^projects/(\d+)/blueprint/import$#',$path,$m)){
        $pid=(int)$m[1];$text='';$name='Pasted blueprint';$type='paste';
        if(isset($_FILES['blueprint'])&&is_array($_FILES['blueprint'])&&(int)($_FILES['blueprint']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){
            $f=$_FILES['blueprint'];$size=(int)($f['size']??0);if($size<=0||$size>12*1024*1024)respond(['error'=>'file_size_out_of_range'],422);
            $name=(string)($f['name']??'blueprint');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$allowed=['docx','txt','md','markdown','html','htm','odt','epub','pdf','json'];if(!in_array($ext,$allowed,true))respond(['error'=>'unsupported_format','allowed'=>$allowed],422);
            if($ext==='json')$text=(string)@file_get_contents((string)$f['tmp_name']);else{$x=ba_extract_text((string)$f['tmp_name'],$ext);$text=(string)($x['text']??'');if($text==='')respond(['error'=>'blueprint_text_extraction_required','message'=>$x['message']??'Text extraction unavailable. Paste the outline or upload DOCX/TXT/Markdown.'],422);} $type='file';
        }else{$b=bodyJson();$text=(string)($b['text']??'');$name=ba_bp_clean((string)($b['source_name']??'Pasted blueprint'))?:'Pasted blueprint';}
        $text=trim($text);if(mb_strlen($text)<20)respond(['error'=>'blueprint_too_short'],422);$parsed=ba_bp_parse($text);if(empty($parsed['chapters']))respond(['error'=>'no_blueprint_chapters_detected'],422);
        $ver=(int)(($s['book_blueprint']['version']??0)+1);if($s['book_blueprint']){$old=$s['book_blueprint'];$old['replaced_at']=nowIso();$s['blueprint_history'][]=$old;}
        $bp=['id'=>'bp_'.$pid.'_v'.$ver,'project_id'=>$pid,'version'=>$ver,'authority'=>'author_approved_plan','status'=>'active','source_type'=>$type,'source_name'=>$name,'source_sha256'=>hash('sha256',$text),'raw_text'=>$text,'preamble'=>$parsed['preamble']??'','parse_notes'=>$parsed['parse_notes']??[],'chapters'=>$parsed['chapters'],'engine_bindings'=>['character'=>true,'story'=>true,'plot'=>true,'chapter'=>true,'writing'=>true,'research'=>true,'refinement'=>true],'created_at'=>nowIso(),'updated_at'=>nowIso()];ba_bp_refresh_research($bp);$s['book_blueprint']=$bp;audit($s,'blueprint.imported','book_blueprint',$ver,['project_id'=>$pid,'chapters'=>count($bp['chapters']),'source_name'=>$name]);saveState($stateFile,$s);respond(['ok'=>true,'blueprint'=>$bp,'message'=>'Book Blueprint imported and connected to all bound engines.']);
    }
    if($method==='PATCH'&&preg_match('#^blueprint/chapters/([^/]+)$#',$path,$m)){
        if(!$s['book_blueprint'])respond(['error'=>'blueprint_not_found'],404);$id=(string)$m[1];$b=bodyJson();$found=false;
        foreach($s['book_blueprint']['chapters'] as &$c){if((string)$c['id']!==$id)continue;$found=true;foreach(['title','synopsis','purpose','pov','location','timeline','hook','style_notes'] as $k)if(array_key_exists($k,$b))$c[$k]=ba_bp_clean((string)$b[$k]);if(array_key_exists('target_words',$b))$c['target_words']=$b['target_words']===null?null:max(500,min((int)$b['target_words'],10000));if(array_key_exists('binding',$b))$c['binding']=ba_bp_binding((string)$b['binding']);if(array_key_exists('order_locked',$b))$c['order_locked']=(bool)$b['order_locked'];break;}unset($c);if(!$found)respond(['error'=>'blueprint_chapter_not_found'],404);$s['book_blueprint']['updated_at']=nowIso();saveState($stateFile,$s);respond(['ok'=>true,'blueprint'=>$s['book_blueprint']]);
    }
    if($method==='POST'&&preg_match('#^blueprint/chapters/([^/]+)/instructions$#',$path,$m)){
        if(!$s['book_blueprint'])respond(['error'=>'blueprint_not_found'],404);$id=(string)$m[1];$b=bodyJson();$text=ba_bp_clean((string)($b['text']??''));if($text==='')respond(['error'=>'instruction_text_required'],422);$found=false;
        foreach($s['book_blueprint']['chapters'] as &$c){if((string)$c['id']!==$id)continue;$found=true;$c['instructions'][]=ba_bp_instruction($text,(string)($b['binding']??'required'),(string)($b['category']??'general'));break;}unset($c);if(!$found)respond(['error'=>'blueprint_chapter_not_found'],404);$s['book_blueprint']['updated_at']=nowIso();saveState($stateFile,$s);respond(['ok'=>true,'blueprint'=>$s['book_blueprint']]);
    }
    if($method==='POST'&&preg_match('#^projects/(\d+)/write-jobs$#',$path,$m)&&$s['book_blueprint']){
        $pid=(int)$m[1];$b=bodyJson();$chapterId=(int)($b['chapter_id']??0);$bc=ba_bp_find($s['book_blueprint'],$chapterId);if(!$bc)return;
        $outline=trim((string)($b['outline']??''));if($outline==='')$outline=ba_bp_outline($bc);$target=(int)($b['target_words']??0);if((int)($bc['target_words']??0)>=500)$target=(int)$bc['target_words'];if($target<500||$target>10000)$target=2500;
        $id=maxId($s['write_jobs']??[])+1;$token=bin2hex(random_bytes(32));$job=['id'=>$id,'project_id'=>$pid,'chapter_id'=>$chapterId,'target_words'=>$target,'outline'=>$outline,'pov'=>(string)($bc['pov']?:($b['pov']??'Use book canon')),'tense'=>(string)($b['tense']??'Use book canon'),'style_source'=>(string)($b['style_source']??'Use approved book style profile'),'research_policy'=>(string)($b['research_policy']??'Respect blueprint research obligations and verified facts; flag unknowns'),'instructions'=>(string)($b['instructions']??''),'context_flags'=>is_array($b['context_flags']??null)?array_values($b['context_flags']):[],'guardrails'=>is_array($b['guardrails']??null)?array_values($b['guardrails']):[],'status'=>'dispatch_pending','message'=>'Blueprint-bound chapter contract preserved. Preparing NOEVA local writing job.','created_at'=>nowIso(),'output_passage_id'=>null,'output_revision'=>null,'core_job_id'=>null,'worker_token_hash'=>hash('sha256',$token),'worker_token_pending'=>$token,'blueprint_id'=>$s['book_blueprint']['id'],'blueprint_version'=>$s['book_blueprint']['version'],'blueprint_chapter'=>$bc,'generation_contract'=>['outline_authoritative'=>true,'blueprint_authoritative'=>true,'binding_semantics'=>['guide'=>'interpret','required'=>'must satisfy','locked'=>'must preserve','forbidden'=>'must not occur'],'target_word_count'=>$target,'word_count_tolerance_percent'=>8,'preserve_book_style'=>true,'preserve_character_voice'=>true,'use_story_graph'=>true,'use_continuity'=>true,'never_overwrite_approved_text'=>true,'result_requires_author_approval'=>true,'compliance_check_required'=>true]];
        $s['write_jobs'][]=$job;audit($s,'write_job.created_from_blueprint','write_job',$id,['chapter_id'=>$chapterId,'blueprint_version'=>$s['book_blueprint']['version']]);saveState($stateFile,$s);ba_dispatch_write($s,count($s['write_jobs'])-1,$stateFile);$job=$s['write_jobs'][count($s['write_jobs'])-1];respond(['ok'=>true,'job'=>$job,'message'=>$job['message']]);
    }
}
