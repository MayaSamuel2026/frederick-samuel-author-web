(function(){
if(!API.upload){
  API.upload = async function(path, formData){
    const r = await fetch(path,{method:'POST',body:formData,credentials:'same-origin'});
    let payload = null;
    try{ payload = await r.json(); }catch(_e){ payload = {error:'invalid_server_response'}; }
    if(!r.ok){
      const err = new Error(payload && payload.error ? payload.error : ('Upload failed ('+r.status+')'));
      err.status = r.status; err.payload = payload;
      throw err;
    }
    return payload;
  };
}
var baPdfRecovery={};
var baSelectedImportId=null;
async function baExtractPdfText(file){
  var pdfjs=await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');
  pdfjs.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';
  var data=new Uint8Array(await file.arrayBuffer());
  var pdf=await pdfjs.getDocument({data:data}).promise;
  var pages=[];
  for(var p=1;p<=pdf.numPages;p++){
    var page=await pdf.getPage(p);
    var content=await page.getTextContent();
    var lines=[], line='';
    (content.items||[]).forEach(function(item){
      if(!item || typeof item.str!=='string') return;
      line+=item.str;
      if(item.hasEOL){ lines.push(line.trim()); line=''; }
      else if(item.str && !/\s$/.test(item.str)){ line+=' '; }
    });
    if(line.trim()) lines.push(line.trim());
    pages.push(lines.join('\n'));
  }
  return pages.join('\n\n').replace(/[ \t]+\n/g,'\n').replace(/\n{4,}/g,'\n\n\n').trim();
}
async function baCommitExtractedText(importId,text){
  var r=await fetch('/api/imports/'+encodeURIComponent(importId)+'/extracted-text',{
    method:'POST',
    credentials:'same-origin',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({text:text})
  });
  var payload=null;
  try{payload=await r.json();}catch(_e){payload={error:'invalid_server_response'};}
  if(!r.ok){
    var err=new Error(payload&&payload.error?payload.error:('Text import failed ('+r.status+')'));
    err.status=r.status;err.payload=payload;throw err;
  }
  return payload;
}
async function baRecoverPdfImport(importRec){
  if(!importRec || importRec.status!=='preserved_needs_extractor' || String(importRec.extension||'').toLowerCase()!=='pdf') return false;
  var id=Number(importRec.id||0);
  if(!id || baPdfRecovery[id]) return false;
  baPdfRecovery[id]=true;
  try{
    notify('Recovering PDF text from preserved original…');
    var r=await fetch('/api/imports/'+encodeURIComponent(id)+'/original-file',{credentials:'same-origin',cache:'no-store'});
    if(!r.ok) throw new Error('original_pdf_fetch_failed_'+r.status);
    var blob=await r.blob();
    var text=await baExtractPdfText(blob);
    if(!text || text.trim().length<100) throw new Error('pdf_text_layer_not_detected');
    await baCommitExtractedText(id,text);
    notify('PDF text extracted; whole-book analysis queued');
    return true;
  }catch(e){
    console.error('Book Author PDF recovery failed',e);
    return false;
  }
}
if(document.getElementById('ba-v11-style'))return;
var st=document.createElement('style');st.id='ba-v11-style';st.textContent=
'.dual-actions{display:flex;gap:9px;flex-wrap:wrap}.analyze-grid,.writer-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);gap:16px}.writer-card{background:var(--paper);border:1px solid var(--line);border-radius:18px;padding:18px}.writer-card h3{font-family:Georgia,serif;font-weight:500;margin:4px 0 13px;font-size:23px}.upload-zone{border:1.5px dashed #aaa39a;border-radius:18px;background:rgba(255,255,255,.48);padding:28px;text-align:center}.upload-zone.drag{border-color:var(--accent);background:#eef2ef}.upload-zone input[type=file]{display:none}.upload-icon{width:54px;height:54px;border-radius:16px;background:#e7e2d9;display:grid;place-items:center;margin:0 auto 14px;font-size:24px}.scope-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px}.scope-item{display:flex;gap:9px;align-items:flex-start;border:1px solid var(--line2);border-radius:12px;padding:10px;background:#fff}.scope-item input,.guardrail input{width:auto;margin-top:2px}.scope-item b{font-size:11px}.scope-item span{display:block;font-size:9px;color:var(--muted);margin-top:2px;line-height:1.35}.depth-options{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}.depth{border:1px solid var(--line);border-radius:13px;padding:11px;background:#fff;cursor:pointer}.depth.active{border-color:var(--ink);box-shadow:inset 0 0 0 1px var(--ink);background:#f3f0e9}.depth b{font-size:11px}.depth p{font-size:9px;color:var(--muted);margin:4px 0 0}.pipeline{display:grid;gap:7px}.pipe{display:grid;grid-template-columns:25px 1fr auto;gap:9px;align-items:center;padding:9px;border:1px solid var(--line2);border-radius:11px}.pn{width:25px;height:25px;border-radius:50%;background:#ece8df;display:grid;place-items:center;font-size:9px;font-weight:800}.pipe b{font-size:10px}.pipe span{font-size:9px;color:var(--muted)}.analysis-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0}.metric-card{border:1px solid var(--line);background:#fff;border-radius:12px;padding:10px}.metric-card b{display:block;font-family:Georgia,serif;font-size:22px;font-weight:500}.metric-card span{font-size:9px;color:var(--muted)}.finding{border:1px solid var(--line);border-left:3px solid var(--blue);border-radius:13px;padding:12px;background:#fff;margin:8px 0}.finding.watch{border-left-color:var(--warn)}.finding.good{border-left-color:var(--good)}.finding h4{margin:0 0 5px;font-size:12px}.finding p{font-size:10px;color:var(--muted);margin:0;line-height:1.45}.engine-state{padding:10px 11px;border-radius:12px;background:#eeeae2;font-size:10px;color:#5f5b53;line-height:1.45}.outline-box{width:100%;min-height:210px;font-family:Georgia,serif;font-size:16px;line-height:1.55}.guardrails{display:grid;gap:7px}.guardrail{display:flex;gap:8px;align-items:flex-start;font-size:10px;color:#55524c}.job-card{border:1px solid var(--line);border-radius:13px;padding:12px;background:#fff;margin-top:9px}.job-card h4{margin:0 0 5px;font-size:12px}.ba-import-actions{display:flex;gap:7px;align-items:center;margin-top:9px}.ba-import-actions .btn{min-height:30px;padding:0 10px}.ba-delete{border-color:#c8b6b1;color:#7a3129;background:#fff}.ba-delete:hover{border-color:#7a3129}.ba-import-library-card{position:relative;min-height:368px;border:1px solid var(--line);border-radius:18px;overflow:hidden;background:linear-gradient(145deg,#40534b 0%,#667a70 100%);box-shadow:0 16px 34px rgba(28,31,29,.12);display:flex;flex-direction:column;cursor:pointer}.ba-import-library-card:focus{outline:2px solid var(--ink);outline-offset:3px}.ba-import-library-top{flex:1;padding:26px 24px 24px;display:flex;flex-direction:column;color:#fff}.ba-import-library-kicker{font-size:9px;letter-spacing:.17em;text-transform:uppercase;font-weight:800}.ba-import-library-title{font-family:Georgia,serif;font-size:30px;line-height:.98;margin:auto 0 22px;max-width:90%}.ba-import-library-status{font-size:10px}.ba-import-library-meta{background:rgba(252,250,245,.97);padding:16px 16px 14px;color:var(--ink);min-height:80px}.ba-import-library-meta b{display:block;font-size:12px;margin-bottom:6px}.ba-import-library-delete{position:absolute;right:12px;top:12px;width:30px;height:30px;border:1px solid rgba(255,255,255,.45);border-radius:50%;background:rgba(20,25,23,.35);color:#fff;display:grid;place-items:center;cursor:pointer;font-size:15px;backdrop-filter:blur(5px)}.ba-import-library-delete:hover{background:rgba(87,29,24,.8)}@media(max-width:980px){.analyze-grid,.writer-grid{grid-template-columns:1fr}.analysis-metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.scope-grid,.depth-options{grid-template-columns:1fr}}';
document.head.appendChild(st);

var ws=document.querySelector('.workspace'),rail=document.querySelector('.rail');
if(!ws||!rail)return;
function extShow(id){document.querySelectorAll('.screen').forEach(function(s){s.classList.remove('active')});document.querySelectorAll('.rail .nav').forEach(function(n){n.classList.remove('active')});var s=document.getElementById(id);if(s)s.classList.add('active');var n=document.querySelector('.rail .nav[data-screen="'+id+'"]');if(n)n.classList.add('active')}
document.querySelectorAll('.rail .nav').forEach(function(n){n.addEventListener('click',function(){document.querySelectorAll('.ba-ext-screen').forEach(function(x){x.classList.remove('active')})})});

var lib=rail.querySelector('[data-screen="libraryScreen"]'),write=rail.querySelector('[data-screen="manuscriptScreen"]');
var an=document.createElement('button');an.className='nav';an.dataset.screen='analysisScreen';an.innerHTML='<span class="sym">◫</span><span>Analyze</span>';an.onclick=function(){extShow('analysisScreen')};lib.insertAdjacentElement('afterend',an);
var dr=document.createElement('button');dr.className='nav';dr.dataset.screen='chapterWriterScreen';dr.innerHTML='<span class="sym">✦</span><span>Draft</span>';dr.onclick=function(){extShow('chapterWriterScreen')};write.insertAdjacentElement('afterend',dr);

var head=document.querySelector('#libraryScreen .library-head');
if(head){var actions=head.querySelector('.dual-actions');if(!actions){actions=document.createElement('div');actions.className='dual-actions';var nb=document.getElementById('newBookBtn');if(nb){nb.parentNode.insertBefore(actions,nb);actions.appendChild(nb)}}var ab=document.createElement('button');ab.className='btn';ab.innerHTML='◫ Analyze existing book';ab.onclick=function(){extShow('analysisScreen')};actions.insertBefore(ab,actions.firstChild)}
var crumb=document.querySelector('#manuscriptScreen .crumb span:last-child');if(crumb){var wb=document.createElement('button');wb.className='btn small';wb.textContent='✦ Write from outline';wb.style.marginRight='5px';wb.onclick=function(){extShow('chapterWriterScreen')};crumb.insertBefore(wb,crumb.firstChild)}

ws.insertAdjacentHTML('beforeend',
'<section class="screen ba-ext-screen" id="analysisScreen"><div class="studio-page"><div class="page-head"><div><div class="eyebrow">Manuscript Analysis & Optimization</div><h1>Analyze an existing book</h1><div class="small muted">Upload the manuscript you already wrote. The original is preserved unchanged while Book Author reconstructs the book and builds an optimization map.</div></div><span class="badge" id="analysisEngineBadge">Checking intelligence…</span></div><div class="page-body analyze-grid"><div><div class="writer-card"><div class="eyebrow">Original manuscript</div><h3>Upload your work</h3><div class="upload-zone" id="uploadZone"><div class="upload-icon">⇧</div><b>Drop a manuscript here</b><div class="small muted" style="margin:6px 0 13px">DOCX · TXT · Markdown · HTML · ODT · EPUB · PDF up to 25 MB</div><label class="btn primary" for="manuscriptFile">Choose manuscript</label><input id="manuscriptFile" type="file" accept=".docx,.txt,.md,.markdown,.html,.htm,.odt,.epub,.pdf"><div id="selectedFile" class="small" style="margin-top:11px"></div></div><div class="hr"></div><div class="eyebrow">Optimization depth</div><div class="depth-options" id="depthOptions"><div class="depth" data-depth="conservative"><b>Conservative</b><p>Continuity, clarity and obvious weaknesses. Protect structure and prose.</p></div><div class="depth active" data-depth="editorial"><b>Editorial</b><p>Scene-level pacing, dialogue, setups/payoffs and prose improvement.</p></div><div class="depth" data-depth="developmental"><b>Developmental</b><p>May propose structural, scene, character-arc and plot changes.</p></div></div><div class="eyebrow" style="margin-top:16px">Whole-book checks</div><div class="scope-grid" id="analysisScopes"></div><label class="guardrail" style="margin-top:14px"><input type="checkbox" checked disabled><span><b>Original manuscript is immutable.</b> Optimization creates proposals and new revisions only.</span></label><div class="actions" style="margin-top:15px"><button class="btn primary" id="uploadAnalyzeBtn">Upload & analyze manuscript</button></div></div><div class="writer-card" style="margin-top:16px"><div class="eyebrow">Analysis result</div><h3>Book Optimization Map</h3><div id="analysisResults"><div class="engine-state">Upload a manuscript to create its structural scan, immutable source record and deep-analysis pipeline.</div></div></div></div><aside><div class="writer-card"><div class="eyebrow">Whole-book pipeline</div><h3>Understand first. Edit second.</h3><div class="pipeline" id="pipeline"></div></div><div class="writer-card" style="margin-top:16px"><div class="eyebrow">Recent imports</div><div id="recentImports" style="margin-top:8px"><div class="small muted">No imported manuscript loaded yet.</div></div></div></aside></div></div></section>'+
'<section class="screen ba-ext-screen" id="chapterWriterScreen"><div class="studio-page"><div class="page-head"><div><div class="eyebrow">Writing Engine</div><h1>Write a chapter from its outline</h1><div class="small muted">The requested word count, outline, approved style, Story Graph, characters and continuity form one explicit writing contract.</div></div><span class="badge" id="writerEngineBadge">Checking intelligence…</span></div><div class="page-body writer-grid"><div class="writer-card"><div class="eyebrow">Chapter contract</div><h3>What must this chapter accomplish?</h3><div class="creation-grid"><div class="field"><label>Chapter</label><select id="writerChapter"><option value="3">Chapter 3 · Before Sunrise</option><option value="4">Chapter 4 · A Name in the Ledger</option><option value="5">Chapter 5 · Border Country</option></select></div><div class="field"><label>Target word count</label><input id="writerWords" type="number" min="500" max="10000" step="100" value="3200"></div><div class="field"><label>POV</label><select id="writerPov"><option>Use chapter / book canon</option><option>Mara Weiss · close third</option><option>Elias Varga · close third</option></select></div><div class="field"><label>Tense</label><select id="writerTense"><option>Use book canon</option><option>Past</option><option>Present</option></select></div><div class="field"><label>Style</label><select id="writerStyle"><option>Use approved book style profile</option><option>Use current chapter voice</option><option>Custom style instructions</option></select></div><div class="field"><label>Research policy</label><select id="writerResearch"><option>Respect verified facts; flag unknowns</option><option>Do not invent factual detail</option><option>Draft freely; verify afterward</option></select></div><div class="field full"><label>Specific chapter outline</label><textarea id="writerOutline" class="outline-box">Mara waits at Rosenfeld Station before dawn with the sealed envelope. Elias arrives late, warns her not to trust the archive ledger, and refuses to explain why. Their exchange should remain restrained and subtext-heavy. Elias leaves shortly before sunrise without saying goodbye. Six minutes later the train arrives. End with Mara deciding to board despite his warning.</textarea></div><div class="field full"><label>Additional style / scene instructions</label><textarea id="writerInstructions" rows="4">Preserve the book’s restrained literary suspense voice. Concrete sensory detail before explanation. Avoid melodrama. Let omissions and objects carry pressure. Do not resolve Elias’s secret in this chapter.</textarea></div></div><div class="eyebrow" style="margin-top:15px">Use canonical context</div><div class="scope-grid" id="writerContexts"></div><div class="eyebrow" style="margin-top:15px">Generation guardrails</div><div class="guardrails" id="writerGuards"></div><div class="actions" style="margin-top:16px"><button class="btn primary" id="generateChapterBtn">✦ Generate chapter draft</button></div></div><aside><div class="writer-card"><div class="eyebrow">Draft contract</div><h3>Before prose is written</h3><div class="card"><h4>Outline is authoritative</h4><p>The engine expands your outline; it does not independently replace the intended chapter function.</p></div><div class="card"><h4>Word count is explicit</h4><p>The requested length is carried into generation and checked against the resulting draft.</p></div><div class="card"><h4>Style comes from the book</h4><p>Book voice, POV and character voice are canonical context, not cosmetic post-processing.</p></div><div class="card"><h4>No silent overwrite</h4><p>Generated prose arrives as a new draft revision and becomes canonical only after your approval.</p></div></div><div class="writer-card" style="margin-top:16px"><div class="eyebrow">Writing jobs</div><div id="writingJobs"><div class="small muted">No chapter draft requested yet.</div></div></div></aside></div></div></section>');

var scopes=[['congruency','Congruency & continuity','Dates, knowledge, objects, geography, relationships and contradictions.'],['plot','Plot','Causality, holes, coincidences, setups/payoffs and unresolved threads.'],['characters','Characters','Motivation, behaviour, voice, relationships and development.'],['structure','Structure & pacing','Chapter balance, scene purpose, slow and rushed sections.'],['writing','Writing quality','Dialogue, repetition, viewpoint, exposition, rhythm and naturalness.'],['reader','Reader experience','Confusion, emotional movement, tension and reveal effectiveness.'],['history','Historical / factual','Anachronisms, dates, institutions, terminology and material culture.'],['style','Style preservation','Learn the manuscript voice before proposing revisions.']];
document.getElementById('analysisScopes').innerHTML=scopes.map(function(x){return '<label class="scope-item"><input type="checkbox" checked data-analysis-scope="'+x[0]+'"><div><b>'+x[1]+'</b><span>'+x[2]+'</span></div></label>'}).join('');
var pipes=[['Preserve original','Hash + immutable source'],['Parse & segment','Chapters · scenes · passages'],['Reconstruct book state','Characters · plot · timeline · Story Graph'],['Cross-book checks','Congruency · causality · arcs · pacing'],['Optimization map','Evidence-linked issues + options'],['Selective revision','Accept · modify · reject · lock']];
document.getElementById('pipeline').innerHTML=pipes.map(function(x,i){return '<div class="pipe"><div class="pn">'+(i+1)+'</div><div><b>'+x[0]+'</b><span>'+x[1]+'</span></div><span>'+(i<2?'Automatic':i===5?'Author-controlled':'Intelligence')+'</span></div>'}).join('');
var contexts=[['story_graph','Story Graph','Plot threads, objects, events and causal dependencies.'],['characters','Character state','Voice, motivations, relationships, knowledge and arc position.'],['continuity','Continuity','Timeline, locations, injuries, possessions and established facts.'],['style','Book style','Approved voice, rhythm, narrative distance and dialogue profile.'],['previous','Previous chapter','Carry emotional and factual state forward accurately.'],['future_outline','Future outline','Avoid stealing reveals or resolving later beats early.']];
document.getElementById('writerContexts').innerHTML=contexts.map(function(x){return '<label class="scope-item"><input type="checkbox" checked data-writer-context="'+x[0]+'"><div><b>'+x[1]+'</b><span>'+x[2]+'</span></div></label>'}).join('');
var guards=[['no_overwrite','Generate into a new draft revision; never overwrite approved prose.'],['canon','Do not contradict established canon unless the outline explicitly changes it.'],['voice','Preserve character-specific voice and the author’s established book style.'],['word_count','Aim for the requested word count as a real drafting constraint.']];
document.getElementById('writerGuards').innerHTML=guards.map(function(x){return '<label class="guardrail"><input type="checkbox" checked data-writer-guard="'+x[0]+'"><span>'+x[1]+'</span></label>'}).join('');

var depth='editorial';document.querySelectorAll('#depthOptions .depth').forEach(function(x){x.onclick=function(){depth=x.dataset.depth;document.querySelectorAll('#depthOptions .depth').forEach(function(y){y.classList.toggle('active',y===x)})}});
var fi=document.getElementById('manuscriptFile'),zone=document.getElementById('uploadZone'),sel=document.getElementById('selectedFile');
function picked(f){sel.textContent=f?f.name+' · '+(f.size/1024/1024).toFixed(2)+' MB':''}
fi.onchange=function(){picked(fi.files[0])};
['dragenter','dragover'].forEach(function(e){zone.addEventListener(e,function(v){v.preventDefault();zone.classList.add('drag')})});
['dragleave','drop'].forEach(function(e){zone.addEventListener(e,function(v){v.preventDefault();zone.classList.remove('drag')})});
zone.addEventListener('drop',function(e){var f=e.dataTransfer.files[0];if(f){var dt=new DataTransfer();dt.items.add(f);fi.files=dt.files;picked(f)}});

function findingHtml(f){return '<div class="finding '+(f.level||'')+'"><h4>'+f.title+' <span class="badge '+(f.level==='watch'?'warn':f.level==='good'?'good':'')+'">'+(f.kind||'Observation')+'</span></h4><p>'+f.detail+'</p></div>'}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
function compactText(v){if(v==null)return '';if(typeof v==='string')return v;if(Array.isArray(v))return v.map(compactText).filter(Boolean).join(' · ');if(typeof v==='object'){return Object.entries(v).slice(0,8).map(function(kv){return kv[0].replaceAll('_',' ')+': '+compactText(kv[1])}).join(' · ')}return String(v)}
function optimizationHtml(d){if(!d)return '';var map=d.optimization_map||d;if(!map||typeof map!=='object')return '';var sum=map.executive_summary||'';var recs=Array.isArray(map.priority_recommendations)?map.priority_recommendations:[];var chars=Array.isArray(map.characters)?map.characters.length:(map.characters&&typeof map.characters==='object'?Object.keys(map.characters).length:0);var blocks='';if(sum)blocks+='<div class="finding good"><h4>Whole-book synthesis</h4><p>'+esc(compactText(sum))+'</p></div>';if(chars)blocks+='<div class="card"><h4>Character model reconstructed</h4><p class="small muted">'+chars+' character records/arc observations were synthesized from the manuscript.</p></div>';if(recs.length){blocks+='<div class="eyebrow" style="margin-top:14px">Priority optimization recommendations</div>'+recs.slice(0,10).map(function(r){var sev=(r&&typeof r==='object'?(r.priority||r.severity||r.level):'')||'recommendation';var title=(r&&typeof r==='object'?(r.title||r.issue||r.category):'')||'Optimization opportunity';var detail=(r&&typeof r==='object'?(r.detail||r.reason||r.why_it_matters||r.suggestion||r.resolution_options):r);return '<div class="finding '+(String(sev).toLowerCase().includes('high')||String(sev).toLowerCase().includes('critical')?'watch':'')+'"><h4>'+esc(title)+' <span class="badge">'+esc(sev)+'</span></h4><p>'+esc(compactText(detail))+'</p></div>'}).join('')}return blocks}
function renderImport(i){var s=i.structural_scan||{},e=(s.entity_candidates||[]).slice(0,10).map(function(x){return '<span class="trait">'+esc(x.name)+' · '+Number(x.mentions||0)+'</span>'}).join('');var st=String(i.analysis_status||'queued');var cls=st==='completed'?'good':(st==='failed'?'warn':'');document.getElementById('analysisResults').innerHTML='<div class="engine-state"><b>Original preserved.</b> SHA-256 '+esc(String(i.sha256||'').slice(0,16))+'… · Import #'+Number(i.id||0)+' · '+esc(String(i.status||'').replaceAll('_',' '))+'</div><div class="analysis-metrics"><div class="metric-card"><b>'+Number(s.word_count||0).toLocaleString()+'</b><span>Words extracted</span></div><div class="metric-card"><b>'+(s.chapter_estimate||0)+'</b><span>Chapter headings</span></div><div class="metric-card"><b>'+(s.paragraph_count||0)+'</b><span>Paragraphs</span></div><div class="metric-card"><b>'+(s.dialogue_ratio||0)+'%</b><span>Dialogue signal</span></div></div>'+(e?'<div class="card"><h4>Entity candidates — awaiting semantic confirmation</h4><div class="traits">'+e+'</div></div>':'')+(s.findings||[]).map(findingHtml).join('')+'<div class="card suggest"><h4>Deep manuscript analysis <span class="badge '+cls+'">'+esc(st.replaceAll('_',' '))+'</span></h4><p>'+esc(i.analysis_message||'')+'</p></div>'+optimizationHtml(i.deep_analysis)}

function baImportTitle(i){
  var t=String((i&&i.display_title)||'').trim();
  if(t) return t;
  t=String((i&&i.original_name)||'Imported manuscript').replace(/\.[^.]+$/,'').replace(/[_-]+/g,' ').replace(/\s+(published|publication|final|manuscript|proof|print)\s*$/i,'').replace(/\s+/g,' ').trim();
  return t||'Imported manuscript';
}
function baImportStatus(i){
  return String((i&&i.analysis_status)||'queued').replaceAll('_',' ');
}
function baOpenImport(i){
  if(!i)return;
  baSelectedImportId=Number(i.id||0)||null;
  extShow('analysisScreen');
  renderImport(i);
  try{window.scrollTo({top:0,behavior:'smooth'})}catch(_e){window.scrollTo(0,0)}
}
async function baDeleteImport(i){
  if(!i)return;
  var title=baImportTitle(i);
  if(!window.confirm('Delete “'+title+'” from Book Author?\n\nThis removes the preserved uploaded source file and its analysis record. This cannot be undone.')) return;
  try{
    var r=await fetch('/api/imports/'+encodeURIComponent(i.id),{method:'DELETE',credentials:'same-origin',headers:{'Accept':'application/json'}});
    var payload=null;
    try{payload=await r.json()}catch(_e){payload={error:'invalid_server_response'}}
    if(!r.ok) throw new Error(payload&&payload.error?payload.error:('Delete failed ('+r.status+')'));
    if(Number(baSelectedImportId)===Number(i.id)) baSelectedImportId=null;
    notify('Deleted '+title);
    await refresh();
  }catch(e){
    console.error('Book Author delete failed',e);
    notify('Could not delete '+title+' · '+(e&&e.message?e.message:'unknown error'));
  }
}
function baUniqueLibraryImports(items){
  var byKey={};
  (items||[]).forEach(function(i){
    var key=String(i.sha256||('id:'+i.id));
    var prior=byKey[key];
    if(!prior){byKey[key]=i;return}
    var rank=function(x){var s=String(x.analysis_status||'');return s==='completed'?5:s==='running_local'?4:s==='queued_local'?3:s==='dispatch_pending'?2:s==='failed'?1:0};
    if(rank(i)>rank(prior)||(rank(i)===rank(prior)&&Number(i.id||0)>Number(prior.id||0)))byKey[key]=i;
  });
  return Object.values(byKey).sort(function(a,b){return Number(a.id||0)-Number(b.id||0)});
}
function baFindLibraryGrid(){
  var screen=document.getElementById('libraryScreen');
  if(!screen)return null;
  var candidates=Array.from(screen.querySelectorAll('*')).filter(function(el){
    var txt=String(el.textContent||'').replace(/\s+/g,' ').trim();
    return txt==='Start a new book'||(el.children.length===0&&txt.indexOf('Start a new book')>=0);
  });
  for(var ci=0;ci<candidates.length;ci++){
    var node=candidates[ci];
    while(node&&node.parentElement&&node.parentElement!==screen){
      var parent=node.parentElement;
      try{
        var d=getComputedStyle(parent).display;
        if((d==='grid'||d==='flex')&&parent.children.length>=2&&parent.children.length<=12)return parent;
      }catch(_e){}
      node=parent;
    }
  }
  var known=screen.querySelector('.library-grid,.book-grid,.projects-grid,.cards-grid');
  if(known)return known;
  var fallback=screen.querySelector('.ba-import-library-fallback');
  if(!fallback){
    fallback=document.createElement('div');
    fallback.className='ba-import-library-fallback';
    fallback.style.cssText='display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,230px));gap:24px;margin:28px 0 10px';
    var head=screen.querySelector('.library-head');
    if(head&&head.parentNode)head.parentNode.insertBefore(fallback,head.nextSibling);else screen.appendChild(fallback);
  }
  return fallback;
}
function baRenderLibraryImports(items){
  var grid=baFindLibraryGrid();
  if(!grid)return;
  grid.querySelectorAll('.ba-import-library-card').forEach(function(el){el.remove()});
  var startNode=Array.from(grid.children).find(function(el){return /Start a new book/i.test(String(el.textContent||''))})||null;
  baUniqueLibraryImports(items).forEach(function(i){
    var card=document.createElement('article');
    card.className='ba-import-library-card';
    card.tabIndex=0;
    card.dataset.importId=String(i.id);
    var wc=Number((i.structural_scan||{}).word_count||0);
    var status=baImportStatus(i);
    var badgeClass=String(i.analysis_status||'')==='completed'?'good':(String(i.analysis_status||'')==='failed'?'warn':'');
    card.innerHTML='<button class="ba-import-library-delete" type="button" title="Delete imported book" aria-label="Delete '+esc(baImportTitle(i))+'">×</button><div class="ba-import-library-top"><div class="ba-import-library-kicker">Imported book</div><div class="ba-import-library-title">'+esc(baImportTitle(i))+'</div><div class="ba-import-library-status">Analysis · '+esc(status)+'</div></div><div class="ba-import-library-meta"><b>'+wc.toLocaleString()+' words · <span class="badge '+badgeClass+'">'+esc(status)+'</span></b><div class="tiny muted">Open manuscript analysis and optimization map</div></div>';
    card.onclick=function(ev){if(ev.target&&ev.target.closest&&ev.target.closest('.ba-import-library-delete'))return;baOpenImport(i)};
    card.onkeydown=function(ev){if(ev.key==='Enter'||ev.key===' '){ev.preventDefault();baOpenImport(i)}};
    var del=card.querySelector('.ba-import-library-delete');
    del.onclick=function(ev){ev.preventDefault();ev.stopPropagation();baDeleteImport(i)};
    if(startNode)grid.insertBefore(card,startNode);else grid.appendChild(card);
  });
}
function baRenderRecentImports(items){
  var list=document.getElementById('recentImports');
  if(!list)return;
  list.innerHTML=(items&&items.length)?items.slice().reverse().slice(0,8).map(function(i){
    var wc=Number((i.structural_scan||{}).word_count||0);
    var st=baImportStatus(i);
    return '<div class="job-card" data-import-row="'+Number(i.id||0)+'"><h4>'+esc(baImportTitle(i))+'</h4><div class="tiny muted">'+wc.toLocaleString()+' words · '+esc(i.optimization_depth||'editorial')+' · '+esc(st)+'</div><div class="ba-import-actions"><button class="btn small" type="button" data-open-import="'+Number(i.id||0)+'">Open</button><button class="btn small ba-delete" type="button" data-delete-import="'+Number(i.id||0)+'">Delete</button></div></div>';
  }).join(''):'<div class="small muted">No imported manuscript loaded yet.</div>';
  list.querySelectorAll('[data-open-import]').forEach(function(btn){
    btn.onclick=function(){var id=Number(btn.dataset.openImport);baOpenImport((items||[]).find(function(x){return Number(x.id)===id}))};
  });
  list.querySelectorAll('[data-delete-import]').forEach(function(btn){
    btn.onclick=function(){var id=Number(btn.dataset.deleteImport);baDeleteImport((items||[]).find(function(x){return Number(x.id)===id}))};
  });
}
async function refresh(){
  try{
    var im=await API.get('/api/projects/'+ACTIVE_PROJECT_ID+'/imports'),is=await API.get('/api/intelligence/status');
    if(im.items&&im.items.length){
      var newest=im.items[im.items.length-1];
      if(await baRecoverPdfImport(newest)){im=await API.get('/api/projects/'+ACTIVE_PROJECT_ID+'/imports')}
    }
    ['analysisEngineBadge','writerEngineBadge'].forEach(function(id){
      var b=document.getElementById(id);
      b.textContent=is.bound?'NOEVA local intelligence bound':(is.reachable?'NOEVA CORE ready · local node syncing':'Local structural engine · CORE unavailable');
      b.className='badge '+(is.bound?'good':'warn');
    });
    baRenderRecentImports(im.items||[]);
    baRenderLibraryImports(im.items||[]);
    if(im.items&&im.items.length){
      var chosen=baSelectedImportId?(im.items.find(function(x){return Number(x.id)===Number(baSelectedImportId)})||null):null;
      if(!chosen)chosen=im.items[im.items.length-1];
      renderImport(chosen);
    }else{
      document.getElementById('analysisResults').innerHTML='<div class="engine-state">Upload a manuscript to create its structural scan, immutable source record and deep-analysis pipeline.</div>';
    }
  }catch(e){console.warn(e)}
}
document.getElementById('uploadAnalyzeBtn').onclick=async function(){var f=fi.files[0];if(!f){notify('Choose a manuscript first');return}this.disabled=true;this.textContent='Preserving & parsing…';try{var fd=new FormData();fd.append('manuscript',f);fd.append('optimization_depth',depth);fd.append('scopes',JSON.stringify(Array.from(document.querySelectorAll('[data-analysis-scope]:checked')).map(function(x){return x.dataset.analysisScope})));var r=await API.upload('/api/projects/'+ACTIVE_PROJECT_ID+'/import-manuscript',fd);
if(r&&r.duplicate&&r.import){baSelectedImportId=Number(r.import.id||0)||null;renderImport(r.import);await refresh();notify('This manuscript is already in the library; the existing book was opened');return;}
var isPdf=/\.pdf$/i.test(f.name||'')||String(f.type||'').toLowerCase()==='application/pdf';
if(isPdf && r.import && r.import.status==='preserved_needs_extractor'){
  this.textContent='Extracting PDF text…';
  try{
    var pdfText=await baExtractPdfText(f);
    if(!pdfText || pdfText.trim().length<100) throw new Error('pdf_text_layer_not_detected');
    this.textContent='Queuing whole-book analysis…';
    var committed=await baCommitExtractedText(r.import.id,pdfText);
    if(committed&&committed.import) r.import=committed.import;
  }catch(pdfErr){
    console.error('Book Author PDF extraction failed',pdfErr);
    var pdfDetail=(pdfErr&&pdfErr.payload&&pdfErr.payload.error)?pdfErr.payload.error:(pdfErr&&pdfErr.message?pdfErr.message:'pdf_extraction_failed');
    renderImport(r.import);
    document.getElementById('analysisResults').insertAdjacentHTML('beforeend','<div class="finding watch"><h4>PDF preserved · text extraction needs attention</h4><p>'+String(pdfDetail).replace(/[<>&]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c]})+'. If this PDF is scanned rather than text-based, OCR will be required.</p></div>');
    notify('PDF preserved · '+pdfDetail);
    return;
  }
}
baSelectedImportId=Number((r.import||{}).id||0)||null;renderImport(r.import);await refresh();notify('Original preserved; manuscript analysis pipeline created')}catch(e){console.error('Book Author import failed',e);var detail=(e&&e.payload&&e.payload.error)?e.payload.error:(e&&e.message?e.message:'unknown_error');document.getElementById('analysisResults').innerHTML='<div class="finding watch"><h4>Import could not be completed</h4><p>'+String(detail).replace(/[<>&]/g,function(c){return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c]})+'</p></div>';notify('Import failed · '+detail)}finally{this.disabled=false;this.textContent='Upload & analyze manuscript'}};
function renderJobs(a){var b=document.getElementById('writingJobs');b.innerHTML=a.length?a.slice().reverse().slice(0,5).map(function(j){var ready=j.status==='draft_ready';return '<div class="job-card"><h4>Chapter '+Number(j.chapter_id||0)+' draft <span class="badge '+(ready?'good':'warn')+'">'+esc(String(j.status||'').replaceAll('_',' '))+'</span></h4><div class="tiny muted">'+Number(j.target_words||0).toLocaleString()+' words · '+esc(j.style_source||'')+(j.generated_word_count?' · generated '+Number(j.generated_word_count).toLocaleString()+' words':'')+'</div><p class="small muted">'+esc(j.message||'')+'</p>'+(ready&&j.generated_draft?'<details><summary class="btn small">Read generated draft</summary><div style="white-space:pre-wrap;font-family:Georgia,serif;font-size:13px;line-height:1.65;margin-top:10px;max-height:520px;overflow:auto">'+esc(j.generated_draft)+'</div></details>':'')+'</div>'}).join(''):'<div class="small muted">No chapter draft requested yet.</div>'}
async function jobs(){try{var r=await API.get('/api/projects/'+ACTIVE_PROJECT_ID+'/write-jobs');renderJobs(r.items)}catch(e){console.warn(e)}}
document.getElementById('generateChapterBtn').onclick=async function(){var outline=document.getElementById('writerOutline').value.trim();if(!outline){notify('Add the chapter outline first');return}var body={chapter_id:Number(document.getElementById('writerChapter').value),target_words:Number(document.getElementById('writerWords').value),outline:outline,pov:document.getElementById('writerPov').value,tense:document.getElementById('writerTense').value,style_source:document.getElementById('writerStyle').value,research_policy:document.getElementById('writerResearch').value,instructions:document.getElementById('writerInstructions').value,context_flags:Array.from(document.querySelectorAll('[data-writer-context]:checked')).map(function(x){return x.dataset.writerContext}),guardrails:Array.from(document.querySelectorAll('[data-writer-guard]:checked')).map(function(x){return x.dataset.writerGuard})};this.disabled=true;this.textContent='Creating chapter contract…';try{var r=await API.send('/api/projects/'+ACTIVE_PROJECT_ID+'/write-jobs','POST',body);await jobs();notify(r.message)}catch(e){notify('Could not create writing job')}finally{this.disabled=false;this.textContent='✦ Generate chapter draft'}};
refresh();jobs();setInterval(refresh,15000);setInterval(jobs,15000);
})();