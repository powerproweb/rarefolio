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
       font-size: 14px; min-height: 100vh; padding: 28px 24px; }

h1 { color: var(--gold); font-size: 22px; font-weight: 700; margin-bottom: 6px; letter-spacing: -.01em; }
.subtitle { color: var(--muted); font-size: 13px; margin-bottom: 28px; }

.section-label { color: var(--muted); font-size: 11px; text-transform: uppercase;
                 letter-spacing: .07em; font-weight: 600; margin-bottom: 6px; }

.row { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
.field { display: flex; flex-direction: column; gap: 6px; }

select {
  background: var(--surface); border: 1px solid var(--border); color: var(--text);
  border-radius: 6px; padding: 9px 14px; font-size: 14px; outline: none;
  cursor: pointer; min-width: 300px;
}
select:focus { border-color: var(--gold); }

.pills { display: flex; gap: 7px; flex-wrap: wrap; }
.pill {
  background: var(--surface); border: 1px solid var(--border); color: var(--text);
  border-radius: 20px; padding: 6px 16px; cursor: pointer; font-size: 13px;
  transition: all .15s; user-select: none;
}
.pill:hover  { border-color: var(--gold); color: var(--gold); }
.pill.active { background: var(--gold); color: #050a18; border-color: var(--gold); font-weight: 600; }

.btn {
  background: var(--gold); color: #050a18; border: none; border-radius: 6px;
  padding: 10px 22px; cursor: pointer; font-weight: 700; font-size: 14px;
  transition: opacity .15s;
}
.btn:hover { opacity: .85; }
.btn.secondary {
  background: var(--surface); color: var(--text);
  border: 1px solid var(--border);
}
.btn.secondary:hover { border-color: var(--gold); color: var(--gold); }

.editor-wrap { position: relative; margin-bottom: 14px; }
textarea {
  width: 100%; min-height: 480px; background: var(--surface); border: 1px solid var(--border);
  color: var(--text); border-radius: 8px; padding: 14px; font-family: 'Courier New', monospace;
  font-size: 13px; line-height: 1.6; resize: vertical; outline: none;
}
textarea:focus { border-color: var(--gold); }

.toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }

.status {
  font-size: 13px; padding: 7px 14px; border-radius: 5px;
  display: none; white-space: nowrap;
}
.status.ok  { background: #0d2b1c; color: var(--ok);  border: 1px solid var(--ok);  display: inline-block; }
.status.err { background: #2b0d0d; color: var(--err); border: 1px solid var(--err); display: inline-block; }
.status.loading { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); display: inline-block; }

.meta-bar { color: var(--muted); font-size: 12px; margin-bottom: 10px; min-height: 18px; }

hr { border: none; border-top: 1px solid var(--border); margin: 24px 0; }

.preview-panel {
  background: var(--surface); border: 1px solid var(--border); border-radius: 8px;
  padding: 24px; max-height: 600px; overflow-y: auto; line-height: 1.7;
}
.preview-panel h2 { color: var(--gold); margin-bottom: 10px; }
.preview-panel h3 { color: var(--gold); margin: 14px 0 6px; }
.preview-panel p  { margin-bottom: 12px; }
.preview-panel hr { margin: 14px 0; }
.preview-panel img { max-width: 180px; height: auto; border-radius: 6px; float: right;
                     margin: 0 0 12px 16px; }
</style>
</head>
<body>

<h1>⬡ Story Editor</h1>
<p class="subtitle">Rarefolio Admin &mdash; Edit shared and per-item stories for any collection block.</p>

<!-- Block selector -->
<div class="row">
  <div class="field">
    <div class="section-label">Block</div>
    <select id="block-select">
      <option value="">Loading blocks from DB&hellip;</option>
    </select>
  </div>
</div>

<!-- Story type pills -->
<div class="field" style="margin-bottom:20px;">
  <div class="section-label">Story</div>
  <div class="pills" id="item-pills">
    <button class="pill active" data-item="0">Shared</button>
  </div>
</div>

<!-- Load button + meta -->
<div class="row" style="margin-bottom:8px;">
  <button class="btn secondary" onclick="loadStory()">&#8593; Load Story</button>
</div>
<div class="meta-bar" id="meta-bar"></div>

<!-- Editor -->
<div class="editor-wrap">
  <textarea id="editor" placeholder="Select a block and story type, then click Load Story&hellip;" spellcheck="false"></textarea>
</div>

<!-- Save toolbar -->
<div class="toolbar">
  <button class="btn" onclick="saveStory()">&#8595; Save Story</button>
  <button class="btn secondary" onclick="togglePreview()">&#9654; Preview</button>
  <div class="status" id="status"></div>
</div>

<!-- Live preview -->
<div id="preview-wrap" style="display:none;">
  <hr />
  <div class="section-label" style="margin-bottom:12px;">Preview</div>
  <div class="preview-panel" id="preview-content"></div>
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

      const sel = $('block-select');
      sel.innerHTML = '<option value="">— Select a block —</option>';
      d.blocks.forEach(b => {
        const opt = document.createElement('option');
        opt.value          = b.block_id;
        opt.dataset.mode   = b.story_mode;
        opt.dataset.batch  = b.batch_num;
        opt.textContent    = `${b.label}  ·  batch ${b.batch_num}  ·  ${b.story_mode === 'per_item' ? 'shared + 8 items' : 'shared only'}`;
        sel.appendChild(opt);
      });
    } catch (e) {
      alert('Error loading blocks: ' + e.message);
    }
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
    showStatus('', '');
  });

  // ---- Build story type pills ----
  function buildPills() {
    const container = $('item-pills');
    container.innerHTML = '';

    const pills = ['Shared'];
    if (storyMode === 'per_item') {
      for (let i = 1; i <= 8; i++) pills.push('Item ' + i);
    }

    pills.forEach((label, idx) => {
      const btn = document.createElement('button');
      btn.className   = 'pill' + (idx === currentItem ? ' active' : '');
      btn.textContent = label;
      btn.dataset.item = idx;
      btn.addEventListener('click', () => {
        currentItem = idx;
        container.querySelectorAll('.pill').forEach(p => p.classList.toggle('active', p === btn));
        $('editor').value = '';
        $('meta-bar').textContent = '';
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
      if ($('preview-wrap').style.display !== 'none') renderPreview();
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
      const now = new Date().toLocaleString();
      $('meta-bar').textContent = 'Last saved: ' + now;
      showStatus('\u2713 Saved (' + (d.mode || 'updated') + ')', 'ok');
      if ($('preview-wrap').style.display !== 'none') renderPreview();
    } catch (e) {
      showStatus('Network error: ' + e.message, 'err');
    }
  }

  // ---- Preview ----
  function renderPreview() {
    $('preview-content').innerHTML = $('editor').value;
  }

  function togglePreview() {
    const wrap = $('preview-wrap');
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
    if (wrap.style.display !== 'none') renderPreview();
  }

  // ---- Status display ----
  function showStatus(msg, type) {
    const s = $('status');
    s.textContent = msg;
    s.className   = 'status' + (type ? ' ' + type : '');
  }

  // Expose for onclick
  window.loadStory    = loadStory;
  window.saveStory    = saveStory;
  window.togglePreview = togglePreview;

  // Init
  loadBlocks();
})();
</script>
</body>
</html>
