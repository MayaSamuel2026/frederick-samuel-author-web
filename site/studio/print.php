<?php
declare(strict_types=1);

/**
 * Book Author V1.7 — PrintEdition source of truth.
 * Manuscript and cover inputs are bound automatically from canonical project state.
 */

function ba_print_ensure(array &$s): void {
    $s['print_editions']=is_array($s['print_editions']??null)?array_values($s['print_editions']):[];
    $s['print_render_history']=is_array($s['print_render_history']??null)?array_values($s['print_render_history']):[];
}

function ba_print_find(array $rows,string $id): int {
    foreach($rows as $i=>$row) if((string)($row['id']??'')===$id) return (int)$i;
    return -1;
}

function ba_print_default(array $s,int $pid,string $id): array {
    return [
        'schema'=>'bookauthor-print-edition/1.0','id'=>$id,'revision'=>1,'project_id'=>$pid,
        'name'=>'KDP Paperback · 6 × 9','binding'=>'paperback','trim_key'=>'6x9','paper'=>'bw_cream','ink'=>'black',
        'bleed'=>false,'language'=>'EN','page_count'=>1,'estimated_page_count'=>250,
        'title'=>(string)($s['project']['title']??'Untitled'),'subtitle'=>'','author_name'=>'',
        'copyright_text'=>'Copyright © '.date('Y').'. All rights reserved.',
        'include_title_page'=>true,'include_copyright_page'=>true,'include_historical_notes'=>true,
        'recto_chapter_open'=>true,'running_headers'=>true,'page_numbers'=>true,'hyphenation'=>true,
        'widows'=>2,'orphans'=>2,
        'typography'=>[
            'body_family'=>'Noto Serif','heading_family'=>'Noto Serif','body_pt'=>10.5,
            'line_height'=>1.42,'first_line_indent_em'=>1.2,'paragraph_space_em'=>0,
        ],
        'chapter_opening'=>['title_align'=>'center','top_space_in'=>1.0,'drop_cap'=>false],
        'cover'=>[
            'background'=>'#1f2724','foreground'=>'#ffffff','back_blurb'=>'','spine_text'=>(string)($s['project']['title']??''),
            'finish'=>'matte','amazon_barcode'=>true,'artwork_file'=>null,'artwork_mime'=>null,'artwork_dpi'=>0,
        ],
        'hardcover_template'=>[],
        'last_preflight'=>null,'last_interior_render'=>null,'last_cover_render'=>null,
        'created_at'=>nowIso(),'updated_at'=>nowIso(),
    ];
}

function ba_print_latest_scene_passages(array $s,string $language): array {
    $latest=[];
    foreach($s['passages']??[] as $p){
        if(strtoupper((string)($p['language']??''))!==strtoupper($language)) continue;
        $key=(int)($p['chapter_id']??0).':'.(int)($p['scene_id']??0);
        if(!isset($latest[$key])||(int)($p['revision']??0)>(int)($latest[$key]['revision']??0))$latest[$key]=$p;
    }
    uasort($latest,function($a,$b){
        $c=(int)($a['chapter_id']??0)<=>(int)($b['chapter_id']??0);
        return $c!==0?$c:((int)($a['scene_id']??0)<=>(int)($b['scene_id']??0));
    });
    return array_values($latest);
}

function ba_print_manuscript(array $s,array $edition): array {
    $language=(string)($edition['language']??'EN');
    $passages=ba_print_latest_scene_passages($s,$language);
    $byChapter=[];
    foreach($passages as $p){
        $cid=(int)($p['chapter_id']??0);if($cid<1)continue;
        if(!isset($byChapter[$cid]))$byChapter[$cid]=[];
        $byChapter[$cid][]=$p;
    }
    $chapters=[];
    foreach($s['chapters']??[] as $ch){
        $cid=(int)($ch['id']??0);$parts=[];
        foreach($byChapter[$cid]??[] as $p)$parts[]=(string)($p['content_html']??'');
        if(!$parts)continue;
        $chapters[]=[
            'chapter_id'=>$cid,'chapter_no'=>(int)($ch['chapter_no']??$cid),'title'=>(string)($ch['title']??('Chapter '.$cid)),
            'html'=>implode("\n",$parts),
            'approved_revision'=>$ch['approved_revision']??null,'locked'=>(bool)($ch['is_locked']??false),
        ];
    }
    return [
        'project_id'=>(int)($edition['project_id']??1),'language'=>$language,'chapters'=>$chapters,
        'historical_notes'=>function_exists('ba_historical_notes')?ba_historical_notes($s):[],
        'source_revision'=>(int)($s['project']['current_revision']??0),
    ];
}

function ba_print_asset_path(string $dataDir,array $edition): ?string {
    $file=(string)($edition['cover']['artwork_file']??'');
    if($file==='')return null;
    $base=realpath($dataDir.'/print_assets');$path=realpath($dataDir.'/print_assets/'.basename($file));
    if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path))return null;
    return $path;
}

function ba_print_core_edition(array $edition,string $dataDir,bool $includeArtwork=false): array {
    $copy=$edition;
    unset($copy['last_preflight'],$copy['last_interior_render'],$copy['last_cover_render'],$copy['created_at'],$copy['updated_at']);
    if($includeArtwork){
        $path=ba_print_asset_path($dataDir,$edition);
        if($path){
            $mime=(string)($edition['cover']['artwork_mime']??'image/jpeg');
            $copy['cover']['artwork_data_uri']='data:'.$mime.';base64,'.base64_encode((string)file_get_contents($path));
        }
    }
    unset($copy['cover']['artwork_file'],$copy['cover']['artwork_mime']);
    return $copy;
}

function ba_print_public(array $edition): array {
    $out=$edition;
    $out['artwork_url']=!empty($edition['cover']['artwork_file'])?'/api/print-editions/'.rawurlencode((string)$edition['id']).'/artwork':null;
    unset($out['cover']['artwork_file'],$out['cover']['artwork_mime']);
    return $out;
}

function ba_print_patch(array &$edition,array $b): void {
    foreach(['name','binding','trim_key','paper','ink','language','title','subtitle','author_name','copyright_text'] as $k){
        if(array_key_exists($k,$b))$edition[$k]=is_string($b[$k])?trim($b[$k]):$b[$k];
    }
    foreach(['bleed','include_title_page','include_copyright_page','include_historical_notes','recto_chapter_open','running_headers','page_numbers','hyphenation'] as $k){
        if(array_key_exists($k,$b))$edition[$k]=(bool)$b[$k];
    }
    foreach(['widows','orphans','estimated_page_count'] as $k)if(array_key_exists($k,$b))$edition[$k]=(int)$b[$k];
    if(is_array($b['typography']??null))$edition['typography']=array_replace($edition['typography']??[],$b['typography']);
    if(is_array($b['chapter_opening']??null))$edition['chapter_opening']=array_replace($edition['chapter_opening']??[],$b['chapter_opening']);
    if(is_array($b['cover']??null)){
        $safe=$b['cover'];unset($safe['artwork_data_uri'],$safe['artwork_file'],$safe['artwork_mime']);
        $edition['cover']=array_replace($edition['cover']??[],$safe);
    }
    if(is_array($b['hardcover_template']??null))$edition['hardcover_template']=$b['hardcover_template'];
    $edition['revision']=(int)($edition['revision']??1)+1;$edition['updated_at']=nowIso();
    $edition['last_preflight']=null;$edition['last_interior_render']=null;$edition['last_cover_render']=null;
}

function ba_print_api(array &$s,string $stateFile,string $method,string $path,string $dataDir): void {
    ba_print_ensure($s);

    if($method==='GET'&&preg_match('#^projects/(\d+)/print-editions$#',$path,$m)){
        $status=ba_core_print_status();$profiles=ba_core_print_profiles();
        respond(['ok'=>true,'items'=>array_map('ba_print_public',$s['print_editions']),'print_status'=>$status,'profiles'=>$profiles]);
    }

    if($method==='POST'&&preg_match('#^projects/(\d+)/print-editions$#',$path,$m)){
        $pid=(int)$m[1];$id='ped_'.(maxId(array_map(function($e){$x=$e;$x['id']=(int)preg_replace('/\D+/','',(string)($e['id']??'0'));return $x;},$s['print_editions']))+1);
        $edition=ba_print_default($s,$pid,$id);$b=bodyJson();
        if($b)ba_print_patch($edition,$b);
        $edition['revision']=1;$edition['created_at']=nowIso();$edition['updated_at']=nowIso();
        $s['print_editions'][]=$edition;audit($s,'print_edition.created','print_edition',count($s['print_editions']),['edition_id'=>$id]);
        saveState($stateFile,$s);respond(['ok'=>true,'edition'=>ba_print_public($edition)]);
    }

    if($method==='GET'&&preg_match('#^print-editions/([^/]+)$#',$path,$m)){
        $id=(string)$m[1];$i=ba_print_find($s['print_editions'],$id);if($i<0)respond(['error'=>'print_edition_not_found'],404);
        $edition=$s['print_editions'][$i];$manuscript=ba_print_manuscript($s,$edition);
        respond(['ok'=>true,'edition'=>ba_print_public($edition),'manuscript'=>$manuscript]);
    }

    if($method==='PATCH'&&preg_match('#^print-editions/([^/]+)$#',$path,$m)){
        $id=(string)$m[1];$i=ba_print_find($s['print_editions'],$id);if($i<0)respond(['error'=>'print_edition_not_found'],404);
        $b=bodyJson();ba_print_patch($s['print_editions'][$i],$b);
        audit($s,'print_edition.updated','print_edition',$i+1,['edition_id'=>$id,'revision'=>$s['print_editions'][$i]['revision']]);
        saveState($stateFile,$s);respond(['ok'=>true,'edition'=>ba_print_public($s['print_editions'][$i])]);
    }

    if($method==='POST'&&preg_match('#^print-editions/([^/]+)/artwork$#',$path,$m)){
        $id=(string)$m[1];$i=ba_print_find($s['print_editions'],$id);if($i<0)respond(['error'=>'print_edition_not_found'],404);
        if(empty($_FILES['artwork'])||!is_uploaded_file($_FILES['artwork']['tmp_name']))respond(['error'=>'artwork_file_required'],422);
        $file=$_FILES['artwork'];if((int)($file['size']??0)<1||(int)$file['size']>12*1024*1024)respond(['error'=>'artwork_file_size_invalid'],413);
        $info=@getimagesize($file['tmp_name']);if(!$info||!in_array((string)$info['mime'],['image/jpeg','image/png','image/webp'],true))respond(['error'=>'unsupported_artwork_format'],422);
        $assetDir=$dataDir.'/print_assets';if(!is_dir($assetDir))@mkdir($assetDir,0770,true);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][(string)$info['mime']];
        $name=preg_replace('/[^A-Za-z0-9_-]/','_',trim($id)).'_'.bin2hex(random_bytes(5)).'.'.$ext;
        if(!move_uploaded_file($file['tmp_name'],$assetDir.'/'.$name))respond(['error'=>'artwork_storage_failed'],500);
        $s['print_editions'][$i]['cover']['artwork_file']=$name;$s['print_editions'][$i]['cover']['artwork_mime']=(string)$info['mime'];
        $s['print_editions'][$i]['cover']['artwork_dpi']=(int)($_POST['dpi']??300);
        $s['print_editions'][$i]['revision']=(int)$s['print_editions'][$i]['revision']+1;$s['print_editions'][$i]['updated_at']=nowIso();
        $s['print_editions'][$i]['last_preflight']=null;$s['print_editions'][$i]['last_cover_render']=null;
        audit($s,'print_edition.artwork_selected','print_edition',$i+1,['edition_id'=>$id,'mime'=>$info['mime'],'width'=>$info[0],'height'=>$info[1]]);
        saveState($stateFile,$s);respond(['ok'=>true,'edition'=>ba_print_public($s['print_editions'][$i])]);
    }

    if($method==='GET'&&preg_match('#^print-editions/([^/]+)/artwork$#',$path,$m)){
        $id=(string)$m[1];$i=ba_print_find($s['print_editions'],$id);if($i<0){http_response_code(404);exit;}
        $asset=ba_print_asset_path($dataDir,$s['print_editions'][$i]);if(!$asset){http_response_code(404);exit;}
        header('Content-Type: '.(string)($s['print_editions'][$i]['cover']['artwork_mime']??'image/jpeg'));header('Cache-Control: private, max-age=0');readfile($asset);exit;
    }

    if($method==='POST'&&preg_match('#^print-editions/([^/]+)/preflight$#',$path,$m)){
        $id=(string)$m[1];$i=ba_print_find($s['print_editions'],$id);if($i<0)respond(['error'=>'print_edition_not_found'],404);
        $edition=$s['print_editions'][$i];$manuscript=ba_print_manuscript($s,$edition);
        $result=ba_core_print_preflight(ba_print_core_edition($edition,$dataDir,false),$manuscript);
        if(!($result['ok']??false)&&isset($result['error']))respond($result,409);
        $s['print_editions'][$i]['last_preflight']=$result;$s['print_editions'][$i]['updated_at']=nowIso();saveState($stateFile,$s);
        respond(['ok'=>true,'preflight'=>$result]);
    }

    if($method==='POST'&&preg_match('#^print-editions/([^/]+)/render/(interior|cover)$#',$path,$m)){
        $id=(string)$m[1];$kind=(string)$m[2];$i=ba_print_find($s['print_editions'],$id);if($i<0)respond(['error'=>'print_edition_not_found'],404);
        $edition=$s['print_editions'][$i];$manuscript=ba_print_manuscript($s,$edition);
        if($kind==='cover'&&empty($edition['last_interior_render']['page_count']))respond(['error'=>'render_interior_first_for_final_page_count'],409);
        if($kind==='cover')$edition['page_count']=(int)$edition['last_interior_render']['page_count'];
        $result=ba_core_print_render($kind,ba_print_core_edition($edition,$dataDir,$kind==='cover'),$kind==='interior'?$manuscript:[]);
        if(!($result['ok']??false))respond($result,409);
        if($kind==='interior'){
            $s['print_editions'][$i]['page_count']=(int)($result['page_count']??1);
            $s['print_editions'][$i]['last_interior_render']=$result;
        } else $s['print_editions'][$i]['last_cover_render']=$result;
        $s['print_editions'][$i]['last_preflight']=$result['preflight']??$s['print_editions'][$i]['last_preflight'];
        $s['print_editions'][$i]['updated_at']=nowIso();
        $s['print_render_history'][]=['id'=>maxId($s['print_render_history'])+1,'edition_id'=>$id,'edition_revision'=>$edition['revision'],'kind'=>$kind,'render'=>$result,'created_at'=>nowIso()];
        audit($s,'print_edition.rendered','print_edition',$i+1,['edition_id'=>$id,'kind'=>$kind,'render_id'=>$result['render_id']??null,'page_count'=>$result['page_count']??null]);
        saveState($stateFile,$s);respond(['ok'=>true,'edition'=>ba_print_public($s['print_editions'][$i]),'render'=>$result]);
    }

    if($method==='GET'&&preg_match('#^print-editions/([^/]+)/download/(interior|cover)$#',$path,$m)){
        $id=(string)$m[1];$kind=(string)$m[2];$i=ba_print_find($s['print_editions'],$id);if($i<0){http_response_code(404);exit;}
        $key=$kind==='interior'?'last_interior_render':'last_cover_render';$renderId=(string)($s['print_editions'][$i][$key]['render_id']??'');
        if($renderId===''){http_response_code(404);exit;}
        $result=ba_core_print_download($renderId);if(!($result['ok']??false)){http_response_code(502);echo 'Print render unavailable.';exit;}
        $filename=preg_replace('/[^A-Za-z0-9._-]+/','-',(string)($s['project']['title']??'book')).'-'.$kind.'.pdf';
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Cache-Control: no-store');header('Content-Length: '.strlen($result['raw']));echo $result['raw'];exit;
    }
}
