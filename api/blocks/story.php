<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';

$blockId = trim($_GET['block'] ?? '');
$itemRaw = trim($_GET['item']  ?? '0');

if ($blockId === '') {
  http_response_code(400);
  header('Content-Type: text/html; charset=utf-8');
  echo '<p class="muted small">Missing block parameter.</p>';
  exit;
}

$itemNum = filter_var($itemRaw, FILTER_VALIDATE_INT);
if ($itemNum === false || $itemNum < 0 || $itemNum > 8) {
  $itemNum = 0;
}

try {
  $pdo = qd_pdo();

  if ($itemNum >= 1) {
    // Try per-item first
    $stmt = $pdo->prepare(
      'SELECT html_content FROM qd_stories WHERE block_id = ? AND item_num = ? LIMIT 1'
    );
    $stmt->execute([$blockId, $itemNum]);
    $row = $stmt->fetch();

    if ($row) {
      header('Content-Type: text/html; charset=utf-8');
      header('Cache-Control: public, max-age=3600');
      echo $row['html_content'];
      exit;
    }

    // Fall back to shared story (item_num IS NULL)
  }

  // Shared story
  $stmt = $pdo->prepare(
    'SELECT html_content FROM qd_stories WHERE block_id = ? AND item_num IS NULL LIMIT 1'
  );
  $stmt->execute([$blockId]);
  $row = $stmt->fetch();

  if ($row) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo $row['html_content'];
    exit;
  }

  // No story at all
  http_response_code(404);
  header('Content-Type: text/html; charset=utf-8');
  echo '<p class="muted small">No story available for this block.</p>';

} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: text/html; charset=utf-8');
  echo '<p class="muted small">Story loading error.</p>';
}
