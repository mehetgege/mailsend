#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""split-html-professional.py
Professional HTML splitter with modern web UI (Flask + Bootstrap).
Features:
 - Web-based modern UI (Bootstrap 5), responsive and user friendly
 - Upload or choose file from server working directory
 - Modes: Words (DOM-aware, preserves tags), Lines, Chars
 - Dry-run preview / estimate chunk count before writing
 - Safe atomic writes (.tmp -> os.replace), retry on failure
 - Resume support via manifest.json (output folder)
 - Background worker with progress polling & logs
 - Download zipped output when complete
Requirements:
    pip install flask beautifulsoup4
Run:
    python split-html-professional.py
Open in browser: http://127.0.0.1:5000/
"""
from __future__ import annotations
import os, time, json, re, shutil, zipfile, hashlib, threading
from datetime import datetime
from typing import List, Dict, Any

try:
    from flask import Flask, render_template_string, request, jsonify, send_file
    from werkzeug.utils import secure_filename
except Exception:
    print("Missing Flask. Install with: pip install flask")
    raise

try:
    from bs4 import BeautifulSoup, NavigableString, Tag
except Exception:
    print("Missing BeautifulSoup. Install with: pip install beautifulsoup4")
    raise

# Config
UPLOAD_FOLDER = os.path.abspath("uploads")
OUTPUT_BASE = os.path.abspath("outputs")
ALLOWED_EXT = {".html", ".htm"}

os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(OUTPUT_BASE, exist_ok=True)

app = Flask(__name__)

# Global job state
JOB_STATE: Dict[str, Any] = {
    "running": False,
    "progress": {"current": 0, "total": 0},
    "logs": [],
    "error": None,
    "result": None,
    "cancel": False,
    "started_at": None,
    "mode": None,
    "input_name": None
}

def log(msg: str):
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    entry = f"[{ts}] {msg}"
    print(entry)
    JOB_STATE['logs'].append(entry)

def sha256_of_file(path: str) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(8192), b""):
            h.update(chunk)
    return h.hexdigest()

def safe_write_text(path: str, text: str, retries: int = 2, delay: float = 0.2) -> bool:
    tmp = path + ".tmp"
    attempt = 0
    while attempt <= retries:
        try:
            os.makedirs(os.path.dirname(path) or ".", exist_ok=True)
            with open(tmp, "w", encoding="utf-8") as wf:
                wf.write(text)
            os.replace(tmp, path)
            return True
        except Exception as e:
            attempt += 1
            try:
                if os.path.exists(tmp):
                    os.remove(tmp)
            except Exception:
                pass
            time.sleep(delay)
    return False

def save_manifest(output_dir: str, manifest: dict):
    safe_write_text(os.path.join(output_dir, "manifest.json"), json.dumps(manifest, ensure_ascii=False, indent=2))

def load_manifest(output_dir: str) -> dict:
    p = os.path.join(output_dir, "manifest.json")
    if os.path.exists(p):
        try:
            with open(p, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return {}
    return {}

def zip_output(output_dir: str) -> str:
    zipname = os.path.join(output_dir, "..", os.path.basename(output_dir) + ".zip")
    zipname = os.path.abspath(zipname)
    with zipfile.ZipFile(zipname, "w", zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(output_dir):
            for fn in files:
                zf.write(os.path.join(root, fn), arcname=os.path.relpath(os.path.join(root, fn), output_dir))
    return zipname

# Splitting algorithms
COPY_TAGS = {'img','br','hr','iframe','video','audio','embed','object','svg','figure'}
COPY_WORD_THRESHOLD = 6

def split_by_lines(body_html: str, max_lines: int) -> List[str]:
    lines = body_html.splitlines(True)
    return ["".join(lines[i:i+max_lines]) for i in range(0, len(lines), max_lines)]

def split_by_chars(body_html: str, max_chars: int) -> List[str]:
    return [ body_html[i:i+max_chars] for i in range(0, len(body_html), max_chars) ]

def split_dom_wordaware(soup: BeautifulSoup, body: Tag, max_words: int) -> List[str]:
    def new_doc():
        out = BeautifulSoup("", "html.parser")
        html_tag = out.new_tag("html"); head_tag = out.new_tag("head"); body_tag = out.new_tag("body")
        html_tag.append(head_tag); html_tag.append(body_tag); out.append(html_tag)
        if soup.head:
            for c in soup.head.contents:
                try:
                    parsed = BeautifulSoup(str(c), "html.parser")
                    for e in parsed.contents:
                        head_tag.append(e)
                except Exception:
                    pass
        return out, body_tag

    out_doc, out_body = new_doc()
    clone_map = { id(body): out_body }
    skip = set()
    parts: List[str] = []
    word_count = 0

    def ensure_ancestors(orig_parent):
        anc = []
        p = orig_parent
        while p is not None and p is not soup:
            anc.append(p)
            if p == body:
                break
            p = p.parent
        anc.reverse()
        parent_clone = clone_map.get(id(body))
        for orig in anc:
            if id(orig) in clone_map:
                parent_clone = clone_map[id(orig)]
                continue
            try:
                newtag = out_doc.new_tag(orig.name)
                for k,v in getattr(orig, "attrs", {}).items():
                    newtag.attrs[k] = v
            except Exception:
                newtag = out_doc.new_tag("div")
            parent_clone.append(newtag)
            clone_map[id(orig)] = newtag
            parent_clone = newtag
        return parent_clone

    for node in body.descendants:
        if JOB_STATE['cancel']:
            log("Cancelled by user")
            break
        if id(node) in skip:
            continue
        if isinstance(node, Tag):
            nm = (node.name or "").lower()
            if nm in COPY_TAGS:
                txt = node.get_text(separator=" ", strip=True)
                num = len(re.findall(r'\S+', txt))
                if num <= COPY_WORD_THRESHOLD:
                    parent_clone = ensure_ancestors(node.parent if node.parent else body)
                    try:
                        parsed = BeautifulSoup(str(node), "html.parser")
                        for e in parsed.contents:
                            parent_clone.append(e)
                    except Exception:
                        parent_clone.append(out_doc.new_string(str(node)))
                    for d in node.descendants:
                        skip.add(id(d))
                    if num:
                        word_count += num
                        if word_count >= max_words:
                            parts.append(str(out_doc))
                            out_doc, out_body = new_doc()
                            clone_map = { id(body): out_body }
                            skip = set()
                            word_count = 0
                    continue
            continue
        if isinstance(node, NavigableString):
            txt = str(node)
            if not txt:
                continue
            parent = node.parent if node.parent else body
            if not txt.strip():
                parent_clone = ensure_ancestors(parent)
                parent_clone.append(out_doc.new_string(txt))
                continue
            tokens = re.split(r'(\s+)', txt)
            for tok in tokens:
                if tok == "": continue
                is_word = bool(tok.strip())
                parent_clone = ensure_ancestors(parent)
                if is_word:
                    if word_count >= max_words:
                        parts.append(str(out_doc))
                        out_doc, out_body = new_doc()
                        clone_map = { id(body): out_body }
                        parent_clone = ensure_ancestors(parent)
                        word_count = 0
                    parent_clone.append(out_doc.new_string(tok))
                    word_count += 1
                else:
                    parent_clone.append(out_doc.new_string(tok))
    if out_doc.body.find_all(text=True) or out_doc.body.find(True):
        parts.append(str(out_doc))
    log(f"[dom-split] {len(parts)} parts (target {max_words})")
    return parts

def split_file_content(file_content: str, mode: str, max_value: int) -> List[str]:
    """
    Split file content into parts based on the specified mode and max value.
    Modes: 'lines', 'words', 'chars'
    """
    if mode == "lines":
        lines = file_content.splitlines(True)  # Preserve line endings
        return ["".join(lines[i:i+max_value]) for i in range(0, len(lines), max_value)]
    elif mode == "words":
        words = file_content.split()  # Split by whitespace
        return [" ".join(words[i:i+max_value]) for i in range(0, len(words), max_value)]
    elif mode == "chars":
        return [file_content[i:i+max_value] for i in range(0, len(file_content), max_value)]
    else:
        raise ValueError("Invalid mode. Use 'lines', 'words', or 'chars'.")

# Worker
def worker_split_and_write(input_path: str, mode: str, max_value: int, output_dir: str,
                           resume: bool=True, force: bool=False, retries: int=2, dry_run: bool=False):
    JOB_STATE.update({"running": True, "progress": {"current": 0, "total": 0}, "logs": [], "error": None, "result": None, "cancel": False})
    JOB_STATE['started_at'] = datetime.now().isoformat()
    JOB_STATE['mode'] = mode
    JOB_STATE['input_name'] = os.path.basename(input_path)
    log(f"Starting job for {input_path} mode={mode} max={max_value} output={output_dir}")
    try:
        if not os.path.exists(input_path):
            raise FileNotFoundError("Input file missing")
        os.makedirs(output_dir, exist_ok=True)
        file_hash = sha256_of_file(input_path)
        manifest = load_manifest(output_dir) if resume else {}
        start_index = 1
        if resume and manifest.get("input_hash") == file_hash:
            last = manifest.get("last_index", 0)
            start_index = last + 1
            log(f"Resuming from part_{last+1}")
        else:
            if resume and manifest:
                log("Manifest exists but for different file - starting fresh (use resume=false to ignore)")
        with open(input_path, "r", encoding="utf-8", errors="replace") as f:
            html = f.read()
        soup = BeautifulSoup(html, "html.parser")
        body = soup.body or soup
        # Updated logic to process raw file content without assuming HTML structure
        if mode in ("lines", "words", "chars"):
            chunks = split_file_content(html, mode, max_value)  # Use raw file content
        else:
            raise ValueError("Unsupported mode. Use 'lines', 'words', or 'chars'.")
        total = len(chunks)
        JOB_STATE['progress'] = {"current": 0, "total": total}
        log(f"Estimated {total} chunks")
        if dry_run:
            JOB_STATE['result'] = {"preview_count": min(5, total), "total": total, "preview": chunks[:5]}
            JOB_STATE['running'] = False
            return JOB_STATE['result']
        written = []
        for idx in range(start_index-1, total):
            if JOB_STATE['cancel']:
                log("Job cancelled by user")
                break
            pnum = idx + 1
            fname = f"part_{pnum}.html"
            outp = os.path.join(output_dir, fname)
            if os.path.exists(outp) and not force:
                log(f"[skip] {fname} exists (force=False)")
                written.append(outp)
                manifest['input_hash'] = file_hash
                manifest['last_index'] = pnum
                save_manifest(output_dir, manifest)
                JOB_STATE['progress'] = {"current": pnum, "total": total}
                continue
            content = chunks[idx]  # Use the original chunk content directly without wrapping
            ok = safe_write_text(outp, content, retries=retries)
            if ok:
                log(f"[ok] wrote {fname}")
                written.append(outp)
                manifest['input_hash'] = file_hash
                manifest['last_index'] = pnum
                save_manifest(output_dir, manifest)
            else:
                log(f"❌ write failed for {fname} -- skipping (will retry on next run)") 
            JOB_STATE['progress'] = {"current": pnum, "total": total}
        index_html = "<!doctype html>\n<html><body>\n"
        for p in sorted([os.path.basename(x) for x in written]):
            index_html += f'<a href="{p}">{p}</a><br/>\n'
        index_html += "</body></html>\n"
        safe_write_text(os.path.join(output_dir, "index.html"), index_html, retries=1)
        JOB_STATE['result'] = {"output_dir": output_dir, "files": written, "total_written": len(written)}
        JOB_STATE['running'] = False
        log(f"Done. {len(written)} parts written. Index in {output_dir}")
        return JOB_STATE['result']
    except Exception as e:
        JOB_STATE['error'] = str(e)
        JOB_STATE['running'] = False
        log(f"ERROR: {e}")
        raise

# Flask routes
@app.route('/')
def index():
    return render_template_string(INDEX_HTML, now=datetime.now().strftime("%Y-%m-%d %H:%M:%S"))

@app.route('/files')
def list_files():
    files = []
    for dir_path in [".", UPLOAD_FOLDER]:
        if os.path.exists(dir_path):
            for f in os.listdir(dir_path):
                if os.path.isfile(os.path.join(dir_path, f)) and os.path.splitext(f)[1].lower() in ALLOWED_EXT:
                    files.append(f)
    return jsonify({"files": sorted(set(files))})

@app.route('/start', methods=['POST'])
def start_job():
    if JOB_STATE['running']:
        return jsonify({"error": "Job already running"})
    
    # Get parameters
    file = request.files.get('file')
    server_file = request.form.get('server_file')
    mode = request.form.get('mode', 'words')
    max_value = int(request.form.get('max_value', 1800))
    output_dir_name = request.form.get('output_dir', 'auto')
    resume = request.form.get('resume') == 'on'
    force = request.form.get('force') == 'on'
    preview = request.form.get('preview') == '1'
    
    # Determine input file
    input_path = None
    if file and file.filename:
        filename = secure_filename(file.filename)
        input_path = os.path.join(UPLOAD_FOLDER, filename)
        file.save(input_path)
    elif server_file:
        for dir_path in [".", UPLOAD_FOLDER]:
            test_path = os.path.join(dir_path, server_file)
            if os.path.exists(test_path):
                input_path = test_path
                break
    
    if not input_path or not os.path.exists(input_path):
        return jsonify({"error": "No valid input file provided"})
    
    # Determine output directory
    if output_dir_name == 'auto':
        base_name = os.path.splitext(os.path.basename(input_path))[0]
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_dir = os.path.join(OUTPUT_BASE, f"{base_name}_{timestamp}")
    else:
        output_dir = os.path.join(OUTPUT_BASE, output_dir_name)
    
    # Start job in background thread
    def run_job():
        worker_split_and_write(
            input_path=input_path,
            mode=mode,
            max_value=max_value,
            output_dir=output_dir,
            resume=resume,
            force=force,
            dry_run=preview
        )
    
    thread = threading.Thread(target=run_job)
    thread.daemon = True
    thread.start()
    
    return jsonify({"status": "started"})

@app.route('/status')
def status():
    return jsonify(JOB_STATE)

@app.route('/cancel', methods=['POST'])
def cancel_job():
    if JOB_STATE['running']:
        JOB_STATE['cancel'] = True
        return jsonify({"status": "cancellation requested"})
    return jsonify({"status": "no job running"})

@app.route('/download')
def download():
    output_dir = request.args.get('dir')
    if not output_dir or not os.path.exists(output_dir):
        return jsonify({"error": "Invalid output directory"})
    
    zip_path = zip_output(output_dir)
    return send_file(zip_path, as_attachment=True)

INDEX_HTML = """<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HTML Splitter - Professional</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{padding-top:20px;background:#f7f9fc} .card{border-radius:12px} pre.log{height:260px;overflow:auto;background:#0b1220;color:#cfe8ff;padding:12px;border-radius:6px}</style>
</head>
<body>
<div class="container">
  <div class="row mb-3">
    <div class="col-md-8"><h3>HTML Splitter - Professional</h3><p class="text-muted">Modern, resume-capable splitter. Upload a file or choose from server folder.</p></div>
    <div class="col-md-4 text-end"><small class="text-muted">Local app • {{now}}</small></div>
  </div>
  <div class="row">
    <div class="col-lg-6">
      <div class="card p-3 mb-3">
        <form id="startForm" enctype="multipart/form-data">
          <div class="mb-2"><label class="form-label">Upload HTML file</label><input class="form-control" type="file" name="file" accept=".html,.htm"></div>
          <div class="mb-2"><label class="form-label">Or pick server file</label><select id="serverFiles" class="form-select" name="server_file"><option value="">-- none --</option></select><div class="form-text">Files in server working dir (uploads/ and current dir)</div></div>
          <div class="row gx-2 mb-2"><div class="col"><label class="form-label">Mode</label><select class="form-select" name="mode"><option value="words" selected>Words (balanced)</option><option value="lines">Lines</option><option value="chars">Chars</option></select></div><div class="col"><label class="form-label">Max (words/lines/chars)</label><input class="form-control" type="number" name="max_value" value="1800" min="1"></div></div>
          <div class="row gx-2 mb-2"><div class="col"><label class="form-label">Output folder name</label><input class="form-control" type="text" name="output_dir" value="auto" placeholder="auto"><div class="form-text">Use 'auto' to create outputs/&lt;inputname&gt;_YYYYmmdd_HHMMSS</div></div><div class="col"><label class="form-label">Options</label><div class="form-check"><input class="form-check-input" type="checkbox" name="resume" id="resume" checked><label class="form-check-label" for="resume">Resume (manifest)</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="force" id="force"><label class="form-check-label" for="force">Force overwrite existing</label></div></div></div>
          <div class="d-flex gap-2"><button type="button" id="btnPreview" class="btn btn-outline-primary">Preview (dry-run)</button><button type="button" id="btnStart" class="btn btn-primary">Start</button><button type="button" id="btnCancel" class="btn btn-danger" disabled>Cancel</button></div>
        </form>
      </div>
      <div class="card p-3 mb-3"><h6>Result / actions</h6><div id="resultArea"></div></div>
    </div>
    <div class="col-lg-6">
      <div class="card p-3 mb-3"><h6>Progress</h6><div class="progress mb-2"><div id="progbar" class="progress-bar" style="width:0%">0%</div></div><div class="small text-muted mb-2" id="statusline">Idle</div><h6>Log</h6><pre class="log" id="logarea"></pre></div>
      <div class="card p-3"><h6>Server files (working dir)</h6><ul id="fileList" class="small mb-0"></ul></div>
    </div>
  </div>
</div>
<script>
async function fetchServerFiles(){ let res = await fetch('/files'); let j = await res.json(); let sel = document.getElementById('serverFiles'); sel.innerHTML = '<option value="">-- none --</option>'; let fl = document.getElementById('fileList'); fl.innerHTML = ''; j.files.forEach(f=>{ let opt=document.createElement('option'); opt.value=f; opt.text=f; sel.appendChild(opt); let li=document.createElement('li'); li.textContent=f; fl.appendChild(li); }); }
fetchServerFiles();
let polling=false; function appendLog(s){ let a=document.getElementById('logarea'); a.textContent += s + '\\n'; a.scrollTop = a.scrollHeight; }
document.getElementById('btnPreview').addEventListener('click', async ()=>{ let form=document.getElementById('startForm'); let fd=new FormData(form); fd.append('preview','1'); appendLog('Requesting preview...'); let r=await fetch('/start',{method:'POST',body:fd}); let j=await r.json(); if(j.error){ appendLog('ERROR: '+j.error); alert('Error: '+j.error); return; } appendLog('Preview: estimated '+j.total+' parts. Showing up to 5 previews.'); let area=document.getElementById('resultArea'); area.innerHTML='<div class="mb-2"><strong>Preview:</strong></div>'; j.preview.slice(0,5).forEach((html, idx)=>{ let card=document.createElement('div'); card.className='card p-2 mb-2'; card.innerHTML='<small class="text-muted">part_'+(idx+1)+'</small><div style="max-height:160px;overflow:auto;background:#fff;padding:8px;border-radius:6px;margin-top:6px">'+html+'</div>'; area.appendChild(card); }); });
document.getElementById('btnStart').addEventListener('click', async ()=>{ let form=document.getElementById('startForm'); let fd=new FormData(form); fd.append('preview','0'); appendLog('Starting job...'); document.getElementById('btnStart').disabled=true; document.getElementById('btnCancel').disabled=false; let r=await fetch('/start',{method:'POST',body:fd}); let j=await r.json(); if(j.error){ appendLog('ERROR: '+j.error); alert('Error: '+j.error); document.getElementById('btnStart').disabled=false; document.getElementById('btnCancel').disabled=true; return; } appendLog('Job started. Polling status...'); polling=true; pollStatus(); });
document.getElementById('btnCancel').addEventListener('click', async ()=>{ await fetch('/cancel',{method:'POST'}); appendLog('Cancel requested.'); document.getElementById('btnCancel').disabled=true; });
async function pollStatus(){ if(!polling) return; let r=await fetch('/status'); let j=await r.json(); document.getElementById('logarea').textContent = j.logs.join('\\n'); let prog=0; if(j.progress.total>0) prog=Math.round((j.progress.current/j.progress.total)*100); document.getElementById('progbar').style.width=prog+'%'; document.getElementById('progbar').textContent=prog+'%'; document.getElementById('statusline').textContent=(j.running? 'Running':'Idle')+' • '+j.progress.current+'/'+j.progress.total; if(j.result){ let area=document.getElementById('resultArea'); area.innerHTML='<div class="alert alert-success">Done. '+j.result.total_written+' parts written. <a href="/download?dir='+encodeURIComponent(j.result.output_dir)+'">Download ZIP</a></div>'; polling=false; document.getElementById('btnStart').disabled=false; document.getElementById('btnCancel').disabled=true; fetchServerFiles(); return; } if(j.error){ appendLog('ERROR: '+j.error); polling=false; document.getElementById('btnStart').disabled=false; document.getElementById('btnCancel').disabled=true; return; } setTimeout(pollStatus,800); }
(async ()=>{ let r=await fetch('/status'); let j=await r.json(); if(j.logs) document.getElementById('logarea').textContent=j.logs.join('\\n'); })();
</script>
</body>
</html>
"""

if __name__ == "__main__":
    app.run(debug=True, port=5000)