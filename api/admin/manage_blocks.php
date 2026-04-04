<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $code, array $payload): void {
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
    respond(401, ['error' => 'Unauthorized']);
  }
}

require_basic_auth();

$method = $_SERVER['REQUEST_METHOD'];

// ---- POST: create or update a block ----
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  $in  = is_string($raw) ? json_decode($raw, true) : [];
  if (!is_array($in)) $in = [];

  $barSerial  = trim((string)($in['barSerial']  ?? ''));
  $batchNum   = $in['batchNum']   ?? null;
  $folderSlug = trim((string)($in['folderSlug'] ?? ''));
  $label      = trim((string)($in['label']      ?? ''));
  $storyMode  = trim((string)($in['storyMode']  ?? 'shared'));

  if ($barSerial === '') respond(400, ['error' => 'Missing barSerial.']);
  if ($folderSlug === '') respond(400, ['error' => 'Missing folderSlug.']);
  if ($label === '') respond(400, ['error' => 'Missing label.']);

  $batchNum = filter_var($batchNum, FILTER_VALIDATE_INT);
  if ($batchNum === false || $batchNum < 1) {
    respond(400, ['error' => 'batchNum must be a positive integer.']);
  }

  if (!in_array($storyMode, ['shared', 'per_item'], true)) {
    respond(400, ['error' => 'storyMode must be shared or per_item.']);
  }

  $blockId = $barSerial . '-block' . str_pad((string)$batchNum, 4, '0', STR_PAD_LEFT);

  try {
    $pdo = qd_pdo();

    // Upsert: insert or update on duplicate key
    $sql = 'INSERT INTO qd_blocks (block_id, bar_serial, batch_num, folder_slug, label, story_mode)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              folder_slug = VALUES(folder_slug),
              label       = VALUES(label),
              story_mode  = VALUES(story_mode),
              updated_at  = CURRENT_TIMESTAMP';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$blockId, $barSerial, $batchNum, $folderSlug, $label, $storyMode]);

    respond(200, [
      'ok'       => true,
      'block_id' => $blockId,
      'mode'     => $stmt->rowCount() === 1 ? 'created' : 'updated',
    ]);

  } catch (Throwable $e) {
    respond(500, ['error' => 'Server error.', 'message' => $e->getMessage()]);
  }
}

// ---- DELETE: remove a block ----
if ($method === 'DELETE') {
  $blockId = trim($_GET['block_id'] ?? '');
  if ($blockId === '') {
    respond(400, ['error' => 'Missing block_id param.']);
  }

  try {
    $pdo = qd_pdo();

    // Remove associated stories first
    $del = $pdo->prepare('DELETE FROM qd_stories WHERE block_id = ?');
    $del->execute([$blockId]);
    $storiesRemoved = $del->rowCount();

    // Remove the block
    $del = $pdo->prepare('DELETE FROM qd_blocks WHERE block_id = ?');
    $del->execute([$blockId]);

    if ($del->rowCount() === 0) {
      respond(404, ['error' => 'Block not found.', 'block_id' => $blockId]);
    }

    respond(200, ['ok' => true, 'deleted' => $blockId, 'stories_removed' => $storiesRemoved]);

  } catch (Throwable $e) {
    respond(500, ['error' => 'Server error.', 'message' => $e->getMessage()]);
  }
}

respond(405, ['error' => 'Method not allowed. Use POST or DELETE.']);
