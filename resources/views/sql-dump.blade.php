<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SQL Dump Preparer</title>
<style>
:root{--ink:#172033;--muted:#687085;--line:#dfe3eb;--brand:#2155cd;--dark:#173f9e;--wash:#f3f6fb}*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);background:radial-gradient(circle at 10% 0,#e5edff 0,transparent 35%),var(--wash)}main{width:min(920px,calc(100% - 32px));margin:auto;padding:64px 0}.eyebrow{color:var(--brand);font-size:.78rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{margin:12px 0 10px;font-size:clamp(2rem,6vw,3.5rem);line-height:1.02;letter-spacing:-.04em}.lead{max-width:650px;color:var(--muted);font-size:1.08rem;line-height:1.65}.card{margin-top:34px;padding:30px;border:1px solid rgba(215,221,233,.9);border-radius:20px;background:rgba(255,255,255,.94);box-shadow:0 20px 55px rgba(28,45,85,.1)}label{display:block;margin-bottom:10px;font-weight:750}input[type=file]{width:100%;padding:18px;border:1.5px dashed #aeb9cf;border-radius:12px;background:#f9fbff;color:var(--muted)}input[type=file]::file-selector-button{margin-right:14px;padding:10px 14px;border:0;border-radius:8px;background:#e5edff;color:var(--dark);font-weight:750;cursor:pointer}.actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px}button{width:100%;padding:15px 20px;border:0;border-radius:11px;color:#fff;background:var(--brand);font-size:1rem;font-weight:800;cursor:pointer}button.secondary{color:var(--dark);background:#e5edff}button:hover{filter:brightness(.92)}button:disabled{opacity:.6;cursor:not-allowed}.error{display:none;margin:0 0 18px;padding:13px 15px;border-radius:10px;background:#fff0f0;color:#a41e2b}.error.visible{display:block}.progress-wrap{display:none;margin-top:22px;padding:18px;border:1px solid var(--line);border-radius:12px;background:#f9fbff}.progress-wrap.visible{display:block}.progress-head{display:flex;justify-content:space-between;gap:16px;margin-bottom:10px;font-size:.92rem;font-weight:750}.progress-track{height:12px;overflow:hidden;border-radius:999px;background:#e1e7f2}.progress-bar{height:100%;width:0;border-radius:inherit;background:linear-gradient(90deg,#2155cd,#5d86ed);transition:width .3s ease}.progress-detail{margin:10px 0 0;color:var(--muted);font-size:.84rem}.rules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:28px}.rule{padding:16px;border:1px solid var(--line);border-radius:12px}.rule strong{display:block;margin-bottom:4px}.rule span,.note{color:var(--muted);font-size:.91rem;line-height:1.45}.note{margin-top:22px}@media(max-width:640px){main{padding:38px 0}.card{padding:21px}.rules,.actions{grid-template-columns:1fr}}
</style></head><body><main>
<div class="eyebrow">Database import utility</div><h1>Prepare your SQL dump.</h1>
<p class="lead">Upload one MySQL <code>.sql</code> file. The cleaned, prefixed and split import files will download automatically in a ZIP archive.</p>
<section class="card">
<div id="error" class="error @if($errors->any()) visible @endif">@if($errors->any()){{ $errors->first('sql_dump') }}@endif</div>
<form id="upload-form" method="POST" action="{{ route('sql-dump.store') }}" enctype="multipart/form-data">@csrf
<label for="sql_dump">Choose SQL dump</label><input id="sql_dump" name="sql_dump" type="file" accept=".sql,text/plain,application/sql" required>
<div class="actions"><button id="download-button" data-action="download" type="submit">Download ZIP</button><button id="import-button" class="secondary" data-action="import" type="submit">Import to database</button></div></form>
<div id="progress" class="progress-wrap" aria-live="polite">
<div class="progress-head"><span id="progress-label">Uploading SQL dump…</span><span id="progress-percent">0%</span></div>
<div class="progress-track"><div id="progress-bar" class="progress-bar"></div></div>
<p id="progress-detail" class="progress-detail">Preparing secure upload…</p>
</div>
<div class="rules">
<div class="rule"><strong>Compatible collation</strong><span>Removes <code>utf8mb4_0900_ai_ci</code>.</span></div>
<div class="rule"><strong>Table prefix</strong><span>Adds <code>wpkm_</code> without double-prefixing.</span></div>
<div class="rule"><strong>Safe counters</strong><span>Resets table <code>AUTO_INCREMENT</code> values to 1.</span></div>
<div class="rule"><strong>Clean session SQL</strong><span>Removes mysqldump session <code>SET</code> directives from both files.</span></div>
</div><p class="note">The original upload is temporary and is not modified. Maximum application upload: 512 MB (your server's PHP limits may be lower).</p>
</section></main>
<script>
const form=document.getElementById('upload-form'),input=document.getElementById('sql_dump'),buttons=[...form.querySelectorAll('button')],box=document.getElementById('progress'),bar=document.getElementById('progress-bar'),percent=document.getElementById('progress-percent'),label=document.getElementById('progress-label'),detail=document.getElementById('progress-detail'),error=document.getElementById('error');
let processingTimer;
function setProgress(value,title,message){const safe=Math.max(0,Math.min(100,Math.round(value)));bar.style.width=safe+'%';percent.textContent=safe+'%';label.textContent=title;detail.textContent=message}
function setBusy(value){buttons.forEach(button=>button.disabled=value)}
function fail(message){clearInterval(processingTimer);setProgress(0,'Processing stopped','Please correct the issue and try again.');error.textContent=message;error.classList.add('visible');setBusy(false)}
form.addEventListener('submit',event=>{
 event.preventDefault();if(!input.files.length)return;
 const action=event.submitter?.dataset.action||'download',isImport=action==='import';error.classList.remove('visible');box.classList.add('visible');setBusy(true);setProgress(2,'Uploading SQL dump…','Sending '+input.files[0].name+' to the processor.');
 const xhr=new XMLHttpRequest();xhr.open('POST',isImport?'{{ route('sql-dump.import') }}':form.action);if(!isImport)xhr.responseType='blob';xhr.setRequestHeader('Accept','application/json');
 xhr.upload.onprogress=e=>{if(e.lengthComputable){const ceiling=isImport?20:70,value=Math.min(ceiling,(e.loaded/e.total)*ceiling);setProgress(value,'Uploading SQL dump…',Math.round(e.loaded/1048576)+' MB of '+Math.round(e.total/1048576)+' MB uploaded.')}};
 xhr.upload.onload=()=>{if(isImport){setProgress(21,'Starting import…','Waiting for the database importer.')}else{let value=72;setProgress(value,'Processing SQL…','Cleaning directives, prefixing tables and separating wpkm_refs.');processingTimer=setInterval(()=>{value=Math.min(96,value+Math.max(1,(96-value)*.08));setProgress(value,'Processing SQL…','Building and compressing your two import files.');},450)}};
 let received=0,pending='';xhr.onprogress=()=>{if(!isImport)return;const chunk=xhr.responseText.slice(received);received=xhr.responseText.length;pending+=chunk;const lines=pending.split('\n');pending=lines.pop();lines.filter(Boolean).forEach(line=>{try{const update=JSON.parse(line);if(update.status==='error')fail(update.detail);else setProgress(update.percent,update.label,update.detail)}catch(e){}})};
 xhr.onload=async()=>{clearInterval(processingTimer);if(xhr.status<200||xhr.status>=300){let message='The dump could not be processed.';try{const raw=isImport?xhr.responseText:await xhr.response.text(),data=JSON.parse(raw);message=data.message||Object.values(data.errors||{})[0]?.[0]||message}catch(e){}fail(message);return}if(isImport){if(pending.trim()){try{const update=JSON.parse(pending);if(update.status==='error'){fail(update.detail);return}setProgress(update.percent,update.label,update.detail)}catch(e){}}setBusy(false);return}setProgress(100,'ZIP ready','Your prepared SQL files are downloading now.');const disposition=xhr.getResponseHeader('Content-Disposition')||'',match=disposition.match(/filename="?([^";]+)"?/i),url=URL.createObjectURL(xhr.response),link=document.createElement('a');link.href=url;link.download=match?match[1]:'prepared_sql_dump.zip';document.body.appendChild(link);link.click();link.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);setBusy(false)};
 xhr.onerror=()=>fail('Network error while uploading the SQL dump.');xhr.send(new FormData(form));
});
</script></body></html>
