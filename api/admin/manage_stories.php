<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';

header('Cache-Control: no-store');

function respond_json(int $code, array $payload): void {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function require_basic_auth(): void {
  $u = $_SERVER['PHP_AUTH_USER'] ?? '';
  $p = $_SERVER['PHP_AUTH_PW']   ?? '';
  if (($u === '' || $p === '') && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (stripos($auth, 'basic ') === 0) {
      $decoded = base64_decode(substr($auth, 6));
      if ($decoded !== false && strpos($decoded, ':') !== false) {
        [$u, $p] = explode(':', $decoded, 2);
      }
    }
  }
  if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Rarefolio Admin"');
    respond_json(401, ['error' => 'Unauthorized']);
  }
}

require_basic_auth();

$method = $_SERVER['REQUEST_METHOD'];

// ---- GET: read a story ----
if ($method === 'GET') {
  $blockId = trim($_GET['block'] ?? '');
  $itemRaw = trim($_GET['item']  ?? '');

  if ($blockId === '') {
    respond_json(400, ['error' => 'Missing block param.']);
  }

  // item not provided or 0 → shared (NULL); 1-8 → per-item
  $itemNum = null;
  if ($itemRaw !== '' && $itemRaw !== '0') {
    $itemNum = filter_var($itemRaw, FILTER_VALIDATE_INT);
    if ($itemNum === false || $itemNum < 1 || $itemNum > 8) {
      respond_json(400, ['error' => 'item must be 0 (shared) or 1-8.']);
    }
  }

  try {
    $pdo = qd_pdo();
    if ($itemNum === null) {
      $stmt = $pdo->prepare('SELECT id, block_id, item_num, html_content, created_at, updated_at FROM qd_stories WHERE block_id = ? AND item_num IS NULL LIMIT 1');
      $stmt->execute([$blockId]);
    } else {
      $stmt = $pdo->prepare('SELECT id, block_id, item_num, html_content, created_at, updated_at FROM qd_stories WHERE block_id = ? AND item_num = ? LIMIT 1');
      $stmt->execute([$blockId, $itemNum]);
    }
    $row = $stmt->fetch();

    if (!$row) {
      respond_json(404, ['error' => 'Story not found.', 'block' => $blockId, 'item' => $itemNum ?? 0]);
    }

    respond_json(200, [
      'block_id'     => $row['block_id'],
      'item_num'     => $row['item_num'] !== null ? (int)$row['item_num'] : 0,
      'html_content' => $row['html_content'],
      'created_at'   => $row['created_at'],
      'updated_at'   => $row['updated_at'],
    ]);

  } catch (Throwable $e) {
    respond_json(500, ['error' => 'Server error.']);
  }
}

// ---- POST: create or update a story ----
if ($method === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  $raw = file_get_contents('php://input');
  $in  = is_string($raw) ? json_decode($raw, true) : [];
  if (!is_array($in)) $in = [];

  $blockId     = trim((string)($in['blockId']     ?? ''));
  $itemNumRaw  = $in['itemNum'] ?? 0;
  $htmlContent = (string)($in['htmlContent'] ?? '');

  if ($blockId === '') respond_json(400, ['error' => 'Missing blockId.']);
  if ($htmlContent === '') respond_json(400, ['error' => 'Missing htmlContent.']);

  $itemNum = filter_var($itemNumRaw, FILTER_VALIDATE_INT);
  if ($itemNum === false || $itemNum < 0 || $itemNum > 8) {
    respond_json(400, ['error' => 'itemNum must be 0 (shared) or 1-8.']);
  }

  // 0 → NULL in DB (shared story)
  $dbItemNum = $itemNum === 0 ? null : $itemNum;

  try {
    $pdo = qd_pdo();

    // Upsert
    $sql = 'INSERT INTO qd_stories (block_id, item_num, html_content)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
              html_content = VALUES(html_content),
              updated_at   = CURRENT_TIMESTAMP';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$blockId, $dbItemNum, $htmlContent]);

    respond_json(200, [
      'ok'       => true,
      'block_id' => $blockId,
      'item_num' => $itemNum,
      'mode'     => $stmt->rowCount() === 1 ? 'created' : 'updated',
    ]);

  } catch (Throwable $e) {
    respond_json(500, ['error' => 'Server error.', 'message' => $e->getMessage()]);
  }
}

// ---- DELETE: remove a story ----
if ($method === 'DELETE') {
  header('Content-Type: application/json; charset=utf-8');

  $blockId = trim($_GET['block'] ?? '');
  $itemRaw = trim($_GET['item']  ?? '');

  if ($blockId === '') {
    respond_json(400, ['error' => 'Missing block param.']);
  }

  $itemNum = null;
  if ($itemRaw !== '' && $itemRaw !== '0') {
    $itemNum = filter_var($itemRaw, FILTER_VALIDATE_INT);
    if ($itemNum === false || $itemNum < 1 || $itemNum > 8) {
      respond_json(400, ['error' => 'item must be 0 (shared) or 1-8.']);
    }
  }

  try {
    $pdo = qd_pdo();
    if ($itemNum === null) {
      $stmt = $pdo->prepare('DELETE FROM qd_stories WHERE block_id = ? AND item_num IS NULL');
      $stmt->execute([$blockId]);
    } else {
      $stmt = $pdo->prepare('DELETE FROM qd_stories WHERE block_id = ? AND item_num = ?');
      $stmt->execute([$blockId, $itemNum]);
    }

    if ($stmt->rowCount() === 0) {
      respond_json(404, ['error' => 'Story not found.', 'block' => $blockId, 'item' => $itemNum ?? 0]);
    }

    respond_json(200, ['ok' => true, 'deleted' => ['block' => $blockId, 'item' => $itemNum ?? 0]]);

  } catch (Throwable $e) {
    respond_json(500, ['error' => 'Server error.']);
  }
}

respond_json(405, ['error' => 'Method not allowed. Use GET, POST, or DELETE.']);
