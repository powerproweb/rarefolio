<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_config.php';

// ---- Basic Auth ----
$u = $_SERVER['PHP_AUTH_USER'] ?? '';
$p = $_SERVER['PHP_AUTH_PW']   ?? '';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (($u === '' || $p === '') && $auth !== '') {
    if (stripos($auth, 'basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$u, $p] = explode(':', $decoded, 2);
        }
    }
}
if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Rarefolio Story Editor"');
    http_response_code(401);
    exit('Unauthorized');
}

// ---- AJAX handlers (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $raw = file_get_contents('php://input');
    $in  = is_string($raw) ? (json_decode($raw, true) ?? []) : [];
    $action = (string)($in['action'] ?? '');

    // Load block list from DB
    if ($action === 'blocks') {
        try {
            $pdo  = qd_pdo();
            $stmt = $pdo->query(
                'SELECT block_id, bar_serial, batch_num, label, story_mode
                   FROM qd_blocks ORDER BY bar_serial, batch_num'
            );
            echo json_encode(['ok' => true, 'blocks' => $stmt->fetchAll()]);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // Load a single story
    if ($action === 'load') {
        $blockId = trim((string)($in['blockId'] ?? ''));
        $itemNum = isset($in['itemNum']) ? (int)$in['itemNum'] : 0;
        if ($blockId === '') { echo json_encode(['error' => 'Missing blockId']); exit; }

        try {
            $pdo = qd_pdo();
            if ($itemNum === 0) {
                $stmt = $pdo->prepare('SELECT html_content, updated_at FROM qd_stories WHERE block_id = ? AND item_num IS NULL LIMIT 1');
                $stmt->execute([$blockId]);
            } else {
                $stmt = $pdo->prepare('SELECT html_content, updated_at FROM qd_stories WHERE block_id = ? AND item_num = ? LIMIT 1');
                $stmt->execute([$blockId, $itemNum]);
            }
            $row = $stmt->fetch();
            if ($row) {
                echo json_encode(['ok' => true, 'html_content' => $row['html_content'], 'updated_at' => $row['updated_at']]);
            } else {
                echo json_encode(['ok' => true, 'html_content' => '', 'updated_at' => null,
                    'note' => 'No story found for this block/item — will be created on first save.']);
            }
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // Save a story
    if ($action === 'save') {
        $blockId     = trim((string)($in['blockId']     ?? ''));
        $itemNum     = isset($in['itemNum']) ? (int)$in['itemNum'] : 0;
        $htmlContent = trim((string)($in['htmlContent'] ?? ''));
        if ($blockId === '')    { echo json_encode(['error' => 'Missing blockId']);     exit; }
        if ($htmlContent === '') { echo json_encode(['error' => 'Content is empty.']); exit; }

        $dbItemNum = $itemNum === 0 ? null : $itemNum;
        try {
            $pdo  = qd_pdo();
            $sql  = 'INSERT INTO qd_stories (block_id, item_num, html_content)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                       html_content = VALUES(html_content),
                       updated_at   = CURRENT_TIMESTAMP';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$blockId, $dbItemNum, $htmlContent]);
            echo json_encode(['ok' => true, 'mode' => $stmt->rowCount() === 1 ? 'created' : 'updated']);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // Register a new block
    if ($action === 'register_block') {
        $barSerial  = trim((string)($in['barSerial']  ?? ''));
        $batchNum   = isset($in['batchNum']) ? (int)$in['batchNum'] : 0;
        $folderSlug = trim((string)($in['folderSlug'] ?? ''));
        $label      = trim((string)($in['label']      ?? ''));
        $storyMode  = trim((string)($in['storyMode']  ?? 'shared'));

        if (!$barSerial)  { echo json_encode(['error' => 'Missing barSerial']);  exit; }
        if ($batchNum < 1){ echo json_encode(['error' => 'Invalid batchNum']);   exit; }
        if (!$folderSlug) { echo json_encode(['error' => 'Missing folderSlug']); exit; }
        if (!$label)      { echo json_encode(['error' => 'Missing label']);      exit; }
        if (!in_array($storyMode, ['shared', 'per_item'], true)) {
            echo json_encode(['error' => 'storyMode must be shared or per_item']); exit;
        }

        $blockId = $barSerial . '-block' . str_pad((string)$batchNum, 4, '0', STR_PAD_LEFT);
        try {
            $pdo = qd_pdo();
            $sql = 'INSERT INTO qd_blocks (block_id, bar_serial, batch_num, folder_slug, label, story_mode)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      folder_slug = VALUES(folder_slug),
                      label       = VALUES(label),
                      story_mode  = VALUES(story_mode),
                      updated_at  = CURRENT_TIMESTAMP';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$blockId, $barSerial, $batchNum, $folderSlug, $label, $storyMode]);
            echo json_encode([
                'ok'       => true,
                'block_id' => $blockId,
                'mode'     => $stmt->rowCount() === 1 ? 'created' : 'updated',
            ]);
        } catch (Throwable $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Story Editor — Rarefolio Admin</title>
<style>
:root {
  --bg:      #050a18;
  --surface: #0d1526;
  --surface2:#121e35;
  --border:  #1e2d4a;
  --gold:    #d9b46c;
  --text:    #c8d4e8;
  --muted:   #6a7a96;
  --ok:      #4caf7d;
  --err:     #e05252;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, sans-serif;
       font-size: 14px; min-height: 100vh; padding: 20px 24px; }

h1 { color: var(--gold); font-size: 20px; font-weight: 700; letter-spacing: -.01em; }
.topbar { display: flex; align-items: center; justify-content: space-between;
          flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }

.section-label { color: var(--muted); font-size: 11px; text-transform: uppercase;
                 letter-spacing: .07em; font-weight: 600; margin-bottom: 5px; }

/* New block panel */
.new-block-bar { background: var(--surface); border: 1px solid var(--border);
  border-radius: 8px; padding: 16px 18px; margin-bottom: 18px; }
.new-block-bar .fields { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; }
.new-block-bar input, .new-block-bar select {
  background: var(--bg); border: 1px solid var(--border); color: var(--text);
  border-radius: 6px; padding: 7px 11px; font-size: 13px; outline: none;
}
.new-block-bar input:focus, .new-block-bar select:focus { border-color: var(--gold); }
.new-block-bar input[name=label]  { min-width: 200px; }
.new-block-bar input[name=folder] { min-width: 200px; }
.new-block-bar input[name=batch]  { width: 80px; }
.new-block-bar input[name=bar]    { width: 110px; }
.new-block-bar select             { min-width: 140px; }
.nb-toggle { background: none; border: 1px solid var(--border); color: var(--muted);
  border-radius: 6px; padding: 6px 14px; cursor: pointer; font-size: 13px; }
.nb-toggle:hover { border-color: var(--gold); color: var(--gold); }

/* Controls row */
.controls-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }

select.block-sel {
  background: var(--surface); border: 1px solid var(--border); color: var(--text);
  border-radius: 6px; padding: 8px 13px; font-size: 14px; outline: none;
  cursor: pointer; min-width: 320px;
}
select.block-sel:focus { border-color: var(--gold); }

.pills { display: flex; gap: 6px; flex-wrap: wrap; }
.pill {
  background: var(--surface); border: 1px solid var(--border); color: var(--text);
  border-radius: 20px; padding: 5px 15px; cursor: pointer; font-size: 13px;
  transition: all .15s; user-select: none;
}
.pill:hover  { border-color: var(--gold); color: var(--gold); }
.pill.active { background: var(--gold); color: #050a18; border-color: var(--gold); font-weight: 600; }

.btn {
  background: var(--gold); color: #050a18; border: none; border-radius: 6px;
  padding: 8px 18px; cursor: pointer; font-weight: 700; font-size: 13px;
  transition: opacity .15s; white-space: nowrap;
}
.btn:hover { opacity: .85; }
.btn.secondary {
  background: var(--surface); color: var(--text);
  border: 1px solid var(--border);
}
.btn.secondary:hover { border-color: var(--gold); color: var(--gold); }
.btn.sm { padding: 6px 14px; font-size: 12px; }

/* Side-by-side layout */
.editor-layout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  align-items: start;
}
@media (max-width: 900px) { .editor-layout { grid-template-columns: 1fr; } }

.pane-label { color: var(--gold); font-size: 11px; text-transform: uppercase;
  letter-spacing: .07em; font-weight: 700; margin-bottom: 6px; }

textarea {
  width: 100%; background: var(--surface); border: 1px solid var(--border);
  color: var(--text); border-radius: 8px; padding: 12px; font-family: 'Courier New', monospace;
  font-size: 12px; line-height: 1.6; resize: none; outline: none;
  height: calc(100vh - 280px); min-height: 400px;
}
textarea:focus { border-color: var(--gold); }

.preview-panel {
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  padding: 20px; overflow-y: auto; line-height: 1.75;
  height: calc(100vh - 280px); min-height: 400px;
}
.preview-panel h2 { color: var(--gold); margin-bottom: 10px; font-size: 16px; }
.preview-panel h3 { color: var(--gold); margin: 14px 0 6px; }
.preview-panel p  { margin-bottom: 12px; color: var(--text); }
.preview-panel hr { margin: 14px 0; border: none; border-top: 1px solid var(--border); }
.preview-panel img { max-width: 160px; height: auto; border-radius: 6px; float: right;
                     margin: 0 0 12px 14px; }
.preview-placeholder { color: var(--muted); font-size: 13px; padding: 20px 0; }

/* ---- Blockquote styles — mirrors site styles.css so preview matches live ---- */
.preview-panel blockquote,
.preview-panel .blockquote {
  position: relative;
  display: block;
  margin: 24px 0;
  padding: 22px 24px 18px 32px;
  background: rgba(13,21,38,0.75);
  border-left: 4px solid #d9b46c;
  border-radius: 0 10px 10px 0;
  box-shadow: 0 6px 28px rgba(0,0,0,.35), inset 0 0 0 1px rgba(217,180,108,.10);
  font-style: italic;
  font-size: 1.04rem;
  line-height: 1.80;
  color: #c8d4e8;
}
.preview-panel blockquote::before,
.preview-panel .blockquote::before {
  content: '\201C';
  position: absolute;
  top: 4px; left: 10px;
  font-size: 60px;
  font-style: normal;
  font-family: Georgia, serif;
  color: #d9b46c;
  opacity: 0.45;
  line-height: 1;
  pointer-events: none;
  user-select: none;
}
.preview-panel blockquote p,
.preview-panel .blockquote p { margin: 0 0 8px; position: relative; z-index: 1; }
.preview-panel blockquote p:last-of-type,
.preview-panel .blockquote p:last-of-type { margin-bottom: 0; }
.preview-panel blockquote cite,
.preview-panel .blockquote cite,
.preview-panel blockquote footer,
.preview-panel .blockquote footer {
  display: block;
  margin-top: 12px;
  padding-top: 9px;
  border-top: 1px solid rgba(217,180,108,.18);
  font-size: 0.87rem;
  font-style: normal;
  font-weight: 600;
  color: #d9b46c;
  letter-spacing: 0.02em;
}
/* Variants */
.preview-panel blockquote.bq-gold,
.preview-panel .blockquote.bq-gold   { border-left-color:#d9b46c; background:rgba(30,22,8,.70); box-shadow:0 6px 28px rgba(0,0,0,.35),inset 0 0 0 1px rgba(217,180,108,.15); }
.preview-panel blockquote.bq-gold::before   { color:#d9b46c; opacity:.55; }
.preview-panel blockquote.bq-maroon,
.preview-panel .blockquote.bq-maroon { border-left-color:#a8304a; background:rgba(28,10,14,.70); box-shadow:0 6px 28px rgba(0,0,0,.35),inset 0 0 0 1px rgba(168,48,74,.15); }
.preview-panel blockquote.bq-maroon::before { color:#a8304a; opacity:.55; }
.preview-panel blockquote.bq-maroon cite    { color:#c85070; border-top-color:rgba(168,48,74,.25); }
.preview-panel blockquote.bq-lavender,
.preview-panel .blockquote.bq-lavender { border-left-color:#b9a7ff; background:rgba(18,12,36,.70); box-shadow:0 6px 28px rgba(0,0,0,.35),inset 0 0 0 1px rgba(185,167,255,.12); }
.preview-panel blockquote.bq-lavender::before { color:#b9a7ff; opacity:.50; }
.preview-panel blockquote.bq-lavender cite  { color:#b9a7ff; border-top-color:rgba(185,167,255,.22); }
.preview-panel blockquote.bq-subtle,
.preview-panel .blockquote.bq-subtle { padding:14px 18px; font-size:.97rem; background:rgba(13,21,38,.45); box-shadow:none; }
.preview-panel blockquote.bq-subtle::before { display:none; }

.toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }

.status {
  font-size: 12px; padding: 6px 12px; border-radius: 5px;
  display: none; white-space: nowrap;
}
.status.ok  { background: #0d2b1c; color: var(--ok);  border: 1px solid var(--ok);  display: inline-block; }
.status.err { background: #2b0d0d; color: var(--err); border: 1px solid var(--err); display: inline-block; }
.status.loading { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); display: inline-block; }

.meta-bar { color: var(--muted); font-size: 11px; margin-bottom: 8px; min-height: 16px; }
hr.sep { border: none; border-top: 1px solid var(--border); margin: 16px 0; }
</style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
  <h1>⬡ Rarefolio Story Editor</h1>
  <button class="nb-toggle" onclick="toggleNewBlock()">+ Register New Block</button>
</div>

<!-- New Block panel (collapsed by default) -->
<div class="new-block-bar" id="new-block-panel" style="display:none;">
  <div class="section-label" style="margin-bottom:10px;">Register New Block</div>
  <div class="fields">
    <div class="field">
      <div class="section-label">Bar Serial</div>
      <input name="bar" id="nb-bar" value="E101837" />
    </div>
    <div class="field">
      <div class="section-label">Batch #</div>
      <input name="batch" id="nb-batch" type="number" min="1" placeholder="16" />
    </div>
    <div class="field">
      <div class="section-label">Folder Slug</div>
      <input name="folder" id="nb-folder" placeholder="scnft_zodiac_leo" />
    </div>
    <div class="field">
      <div class="section-label">Label</div>
      <input name="label" id="nb-label" placeholder="Zodiac &mdash; Leo" />
    </div>
    <div class="field">
      <div class="section-label">Story Mode</div>
      <select id="nb-mode">
        <option value="shared">Shared only</option>
        <option value="per_item">Per-item (shared + 8 items)</option>
      </select>
    </div>
    <div class="field" style="justify-content:flex-end;">
      <button class="btn" onclick="registerBlock()">Register Block</button>
    </div>
  </div>
  <div class="status" id="nb-status" style="margin-top:10px;"></div>
</div>

<!-- Controls row -->
<div class="controls-row">
  <div class="field">
    <div class="section-label">Block</div>
    <select class="block-sel" id="block-select">
      <option value="">Loading blocks from DB&hellip;</option>
    </select>
  </div>
  <div class="field">
    <div class="section-label">Story</div>
    <div class="pills" id="item-pills">
      <button class="pill active" data-item="0">Shared</button>
    </div>
  </div>
  <div class="field" style="justify-content:flex-end;">
    <button class="btn secondary" onclick="loadStory()">&#8593; Load</button>
  </div>
  <div class="field" style="justify-content:flex-end;">
    <button class="btn" onclick="saveStory()">&#8595; Save</button>
  </div>
  <div class="status" id="status"></div>
</div>

<div class="meta-bar" id="meta-bar"></div>

<!-- Side-by-side editor + preview -->
<div class="editor-layout">
  <div>
    <div class="pane-label">HTML Editor</div>
    <textarea id="editor" placeholder="Select a block and story, then click Load&hellip;" spellcheck="false"></textarea>
  </div>
  <div>
    <div class="pane-label">Live Preview</div>
    <div class="preview-panel" id="preview-content">
      <p class="preview-placeholder">Preview will appear here after loading a story.</p>
    </div>
  </div>
</div>

<script>
(function () {
  const $  = id => document.getElementById(id);
  let currentBlock = '';
  let currentItem  = 0;
  let storyMode    = 'shared';

  // ---- Load block list from DB ----
  async function loadBlocks() {
    try {
      const res = await fetch(location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'blocks' }),
      });
      const d = await res.json();
      if (!d.ok) { alert('Failed to load blocks: ' + (d.error || 'unknown')); return; }
      populateBlockSelect(d.blocks);
    } catch (e) {
      alert('Error loading blocks: ' + e.message);
    }
  }

  function populateBlockSelect(blocks) {
    const sel = $('block-select');
    const prev = sel.value;
    sel.innerHTML = '<option value="">\u2014 Select a block \u2014</option>';
    blocks.forEach(b => {
      const opt = document.createElement('option');
      opt.value        = b.block_id;
      opt.dataset.mode = b.story_mode;
      opt.textContent  = `${b.label}  \u00b7  batch ${b.batch_num}  \u00b7  ${
        b.story_mode === 'per_item' ? 'shared + 8 items' : 'shared only'}`;
      sel.appendChild(opt);
    });
    if (prev) sel.value = prev;
  }

  // ---- Block selection ----
  $('block-select').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    currentBlock = opt ? opt.value : '';
    storyMode    = opt ? (opt.dataset.mode || 'shared') : 'shared';
    currentItem  = 0;
    buildPills();
    $('editor').value = '';
    $('meta-bar').textContent = '';
    $('preview-content').innerHTML = '<p class="preview-placeholder">Load a story to see the preview.</p>';
    showStatus('', '');
  });

  // ---- Build story type pills ----
  function buildPills() {
    const container = $('item-pills');
    container.innerHTML = '';
    const labels = ['Shared'];
    if (storyMode === 'per_item') {
      for (let i = 1; i <= 8; i++) labels.push('Item ' + i);
    }
    labels.forEach((label, idx) => {
      const btn = document.createElement('button');
      btn.className    = 'pill' + (idx === currentItem ? ' active' : '');
      btn.textContent  = label;
      btn.dataset.item = idx;
      btn.addEventListener('click', () => {
        currentItem = idx;
        container.querySelectorAll('.pill').forEach(p => p.classList.toggle('active', p === btn));
        $('editor').value = '';
        $('meta-bar').textContent = '';
        $('preview-content').innerHTML = '<p class="preview-placeholder">Load a story to see the preview.</p>';
        showStatus('', '');
      });
      container.appendChild(btn);
    });
  }

  // ---- Load story from DB ----
  async function loadStory() {
    if (!currentBlock) { showStatus('Select a block first.', 'err'); return; }
    showStatus('Loading\u2026', 'loading');
    try {
      const res = await fetch(location.pathname, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'load', blockId: currentBlock, itemNum: currentItem }),
      });
      const d = await res.json();
      if (d.error) { showStatus('Error: ' + d.error, 'err'); return; }
      $('editor').value = d.html_content || '';
      $('meta-bar').textContent = d.updated_at
        ? 'Last saved: ' + d.updated_at
        : (d.note || 'No story yet \u2014 write content and save.');
      showStatus(d.html_content ? 'Story loaded.' : 'No story yet.', 'ok');
      renderPreview();
    } catch (e) {
      showStatus('Network error: ' + e.message, 'err');
    }
  }

  // ---- Save story to DB ----
  async function saveStory() {
    if (!currentBlock) { showStatus('Select a block first.', 'err'); return; }
    const html = $('editor').value.trim();
    if (!html) { showStatus('Content is empty.', 'err'); return; }
    showStatus('Saving\u2026', 'loading');
    try {
      const res = await fetch(location.pathname, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save', blockId: currentBlock, itemNum: currentItem, htmlContent: html }),
      });
      const d = await res.json();
      if (d.error) { showStatus('Error: ' + d.error, 'err'); return; }
      $('meta-bar').textContent = 'Last saved: ' + new Date().toLocaleString();
      showStatus('\u2713 Saved (' + (d.mode || 'updated') + ')', 'ok');
      renderPreview();
    } catch (e) {
      showStatus('Network error: ' + e.message, 'err');
    }
  }

  // ---- Register new block ----
  async function registerBlock() {
    const barSerial  = $('nb-bar').value.trim();
    const batchNum   = parseInt($('nb-batch').value, 10);
    const folderSlug = $('nb-folder').value.trim();
    const label      = $('nb-label').value.trim();
    const storyModeVal = $('nb-mode').value;

    if (!barSerial || !batchNum || !folderSlug || !label) {
      showNbStatus('Fill in all fields.', 'err'); return;
    }
    showNbStatus('Registering\u2026', 'loading');
    try {
      const res = await fetch(location.pathname, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'register_block', barSerial, batchNum, folderSlug, label, storyMode: storyModeVal }),
      });
      const d = await res.json();
      if (d.error) { showNbStatus('Error: ' + d.error, 'err'); return; }
      showNbStatus('\u2713 ' + d.block_id + ' ' + d.mode, 'ok');
      // Refresh block list and select the new block
      await loadBlocks();
      $('block-select').value = d.block_id;
      $('block-select').dispatchEvent(new Event('change'));
    } catch (e) {
      showNbStatus('Network error: ' + e.message, 'err');
    }
  }

  function toggleNewBlock() {
    const p = $('new-block-panel');
    p.style.display = p.style.display === 'none' ? 'block' : 'none';
  }

  // ---- Live preview (updates as you type) ----
  function renderPreview() {
    $('preview-content').innerHTML = $('editor').value || '<p class="preview-placeholder">Nothing to preview.</p>';
  }
  $('editor').addEventListener('input', renderPreview);

  // ---- Status helpers ----
  function showStatus(msg, type) {
    const s = $('status');
    s.textContent = msg;
    s.className   = 'status' + (type ? ' ' + type : '');
  }
  function showNbStatus(msg, type) {
    const s = $('nb-status');
    s.textContent = msg;
    s.className   = 'status' + (type ? ' ' + type : '');
  }

  // Expose for onclick
  window.loadStory      = loadStory;
  window.saveStory      = saveStory;
  window.registerBlock  = registerBlock;
  window.toggleNewBlock = toggleNewBlock;

  // Init
  buildPills();
  loadBlocks();
})();
</script>
</body>
</html>
