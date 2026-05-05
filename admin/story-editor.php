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
function qd_normalize_no_emdash_text(string $text): string
{
    if (function_exists('mb_chr')) {
        $emDash = mb_chr(8212, 'UTF-8');
        $enDash = mb_chr(8211, 'UTF-8');
        return str_replace([$emDash, $enDash], ', ', $text);
    }
    $normalized = preg_replace('/\x{2014}|\x{2013}/u', ', ', $text);
    return is_string($normalized) ? $normalized : $text;
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
                echo json_encode([
                    'ok' => true,
                    'html_content' => qd_normalize_no_emdash_text((string)$row['html_content']),
                    'updated_at' => $row['updated_at'],
                ]);
            } else {
                echo json_encode(['ok' => true, 'html_content' => '', 'updated_at' => null,
                    'note' => 'No story found for this block/item, will be created on first save.']);
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
        $htmlContent = trim(qd_normalize_no_emdash_text((string)($in['htmlContent'] ?? '')));
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
        $barSerial      = trim((string)($in['barSerial']      ?? ''));
        $batchNum       = isset($in['batchNum']) ? (int)$in['batchNum'] : 0;
        $folderSlug     = trim((string)($in['folderSlug']     ?? ''));
        $label          = trim((string)($in['label']          ?? ''));
        $storyMode      = trim((string)($in['storyMode']      ?? 'shared'));
        $characterNames = $in['characterNames'] ?? null;

        if (!$barSerial)  { echo json_encode(['error' => 'Missing barSerial']);  exit; }
        if ($batchNum < 1){ echo json_encode(['error' => 'Invalid batchNum']);   exit; }
        if (!$folderSlug) { echo json_encode(['error' => 'Missing folderSlug']); exit; }
        if (!$label)      { echo json_encode(['error' => 'Missing label']);      exit; }
        if (!in_array($storyMode, ['shared', 'per_item'], true)) {
            echo json_encode(['error' => 'storyMode must be shared or per_item']); exit;
        }

        $characterNamesJson = null;
        if (is_array($characterNames) && count($characterNames) > 0) {
            $sanitized = [];
            foreach (array_slice($characterNames, 0, 8) as $n) {
                $sanitized[] = trim(qd_normalize_no_emdash_text((string)$n));
            }
            $characterNamesJson = json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        }

        $blockId = $barSerial . '-block' . str_pad((string)$batchNum, 4, '0', STR_PAD_LEFT);
        try {
            $pdo = qd_pdo();
            $sql = 'INSERT INTO qd_blocks (block_id, bar_serial, batch_num, folder_slug, label, story_mode, character_names)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                      folder_slug     = VALUES(folder_slug),
                      label           = VALUES(label),
                      story_mode      = VALUES(story_mode),
                      character_names = COALESCE(VALUES(character_names), character_names),
                      updated_at      = CURRENT_TIMESTAMP';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$blockId, $barSerial, $batchNum, $folderSlug, $label, $storyMode, $characterNamesJson]);
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
<title>Story Editor, Rarefolio Admin</title>
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
.top-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

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
.nb-names-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 6px; margin-top: 8px; }
.nb-names-grid input { background: var(--bg); border: 1px solid var(--border); color: var(--text);
  border-radius: 6px; padding: 6px 10px; font-size: 12px; width: 100%; outline: none; }
.nb-names-grid input:focus { border-color: var(--gold); }
.nb-names-label { color: var(--muted); font-size: 11px; text-transform: uppercase;
  letter-spacing: .07em; font-weight: 600; margin-bottom: 3px; }
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

/* ---- Story Footer styles, mirrors site styles.css ---- */
.preview-panel .owner-footer { margin: 36px auto 10px; clear: both; }
.preview-panel .owner-footer-inner {
  position: relative; padding: 34px 30px 28px 38px;
  background: linear-gradient(155deg,rgba(62,44,14,.98) 0%,rgba(36,26,8,1) 45%,rgba(18,12,3,1) 100%);
  border: 1px solid rgba(217,180,108,.28); border-left: 4px solid rgba(217,180,108,.70);
  border-radius: 0 18px 18px 0; overflow: hidden;
  box-shadow: 0 20px 56px rgba(0,0,0,.60), 0 6px 18px rgba(0,0,0,.40), inset 0 1px 0 rgba(255,239,189,.14), 0 0 0 1px rgba(217,180,108,.08);
}
.preview-panel .owner-footer-inner::before { content:""; pointer-events:none; }
.preview-panel .owner-footer-inner::after {
  content:""; position:absolute; inset:0; pointer-events:none; border-radius:inherit; z-index:0;
  background: radial-gradient(ellipse at 92% 5%,rgba(240,200,80,.12) 0%,transparent 40%), radial-gradient(ellipse at 8% 92%,rgba(185,167,255,.06) 0%,transparent 35%);
}
.preview-panel .owner-badge {
  display:inline-flex; align-items:center; gap:7px; margin-bottom:16px; padding:6px 16px 6px 12px;
  font-family:Georgia,serif; font-size:10px; letter-spacing:2px; text-transform:uppercase;
  color:#1a1004; background:linear-gradient(135deg,#f5e0a8,#d9a84c); border-radius:999px;
  box-shadow:0 3px 14px rgba(0,0,0,.45),0 0 0 1px rgba(255,225,130,.30),0 0 18px rgba(217,160,60,.25);
  position:relative; z-index:1;
}
.preview-panel .owner-badge::before { content:"\2767"; font-size:11px; color:rgba(28,18,4,.55); }
.preview-panel .owner-title { margin:0 0 16px; font-family:Georgia,serif; font-size:22px; line-height:1.3; color:#fff5d6; text-shadow:0 2px 12px rgba(0,0,0,.55),0 0 28px rgba(217,180,108,.18); position:relative; z-index:1; }
.preview-panel .owner-message { margin:0 0 22px; font-family:Georgia,serif; font-size:15px; line-height:1.95; color:#edddb8; font-style:italic; position:relative; z-index:1; }
.preview-panel .owner-divider { display:flex; align-items:center; gap:14px; margin:0 0 20px; position:relative; z-index:1; }
.preview-panel .owner-divider::before,.preview-panel .owner-divider::after { content:""; flex:1; height:1px; background:linear-gradient(90deg,transparent,rgba(240,215,161,.80) 50%,transparent); }
.preview-panel .owner-divider span { font-size:13px; color:rgba(240,215,161,.75); letter-spacing:5px; text-shadow:0 0 8px rgba(217,180,108,.45); }
.preview-panel .owner-signoff { font-family:Georgia,serif; font-size:15px; line-height:1.75; color:#e8d49a; position:relative; z-index:1; }
.preview-panel .owner-signoff .owner-closing { display:block; margin-bottom:8px; font-size:14px; font-style:italic; color:#c8aa6a; letter-spacing:.03em; }
.preview-panel .owner-signoff span { display:block; margin-top:2px; font-size:21px; font-weight:bold; color:#fff5d6; letter-spacing:.5px; text-shadow:0 2px 8px rgba(0,0,0,.50),0 0 20px rgba(217,180,108,.22); }
.preview-panel .owner-signoff em { display:block; font-size:10px; font-style:normal; font-weight:600; letter-spacing:2.5px; text-transform:uppercase; color:rgba(217,180,108,.65); border-top:1px solid rgba(217,180,108,.18); padding-top:8px; margin-top:10px; }

/* ---- Blockquote styles, mirrors site styles.css so preview matches live ---- */
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
.preview-panel blockquote.bq-subtle, .preview-panel blockquote.bq-minimal,
.preview-panel .blockquote.bq-subtle, .preview-panel .blockquote.bq-minimal { padding:14px 18px; font-size:.97rem; background:rgba(13,21,38,.45); box-shadow:none; }
.preview-panel blockquote.bq-subtle::before, .preview-panel blockquote.bq-minimal::before { display:none; }

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
  <div class="top-actions">
    <a class="btn secondary sm" href="/admin/index.php">Admin Home</a>
    <a class="btn secondary sm" href="/admin/wallet-dashboard.php">Wallet Dashboard</a>
    <button class="nb-toggle" onclick="toggleNewBlock()">+ Register New Block</button>
  </div>
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
      <input name="label" id="nb-label" placeholder="Zodiac, Leo" />
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
  <!-- Per-item character names (shown only when storyMode = per_item) -->
  <div id="nb-names-wrap" style="display:none; margin-top:12px;">
    <div class="nb-names-label">Character Names (1 – 8), shown on collection cards &amp; NFT detail</div>
    <div class="nb-names-grid" id="nb-names-grid"></div>
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
    sel.innerHTML = '<option value="">Select a block</option>';
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
        : (d.note || 'No story yet, write content and save.');
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
    const characterNames = storyModeVal === 'per_item' ? getCharacterNames() : null;
    try {
      const payload = { action: 'register_block', barSerial, batchNum, folderSlug, label, storyMode: storyModeVal };
      if (characterNames) payload.characterNames = characterNames;
      const res = await fetch(location.pathname, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
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

  // ---- Per-item names grid ----
  function buildNamesGrid() {
    const grid = $('nb-names-grid');
    grid.innerHTML = '';
    for (let i = 1; i <= 8; i++) {
      const inp = document.createElement('input');
      inp.id          = `nb-name-${i}`;
      inp.type        = 'text';
      inp.placeholder = `Item ${i} name`;
      grid.appendChild(inp);
    }
  }

  function getCharacterNames() {
    const names = [];
    for (let i = 1; i <= 8; i++) {
      const v = ($(`nb-name-${i}`)?.value || '').trim();
      names.push(v);
    }
    // Only return the array if at least one name is filled
    return names.some(n => n !== '') ? names : null;
  }

  // Show / hide names grid based on mode selection
  $('nb-mode').addEventListener('change', function () {
    const isPerItem = this.value === 'per_item';
    $('nb-names-wrap').style.display = isPerItem ? 'block' : 'none';
    if (isPerItem && !$('nb-name-1')) buildNamesGrid();
  });

  function toggleNewBlock() {
    const p = $('new-block-panel');
    const nowVisible = p.style.display === 'none';
    p.style.display = nowVisible ? 'block' : 'none';
    // Ensure names grid is pre-built when panel opens
    if (nowVisible && !$('nb-name-1')) buildNamesGrid();
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
