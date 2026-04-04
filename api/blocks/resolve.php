<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$bar   = trim($_GET['bar']   ?? '');
$batch = trim($_GET['batch'] ?? '');

if ($bar === '' || $batch === '') {
  respond(400, ['error' => 'Missing required params: bar, batch']);
}

$batchNum = filter_var($batch, FILTER_VALIDATE_INT);
if ($batchNum === false || $batchNum < 1) {
  respond(400, ['error' => 'Invalid batch number.']);
}

try {
  $pdo  = qd_pdo();
  $stmt = $pdo->prepare(
    'SELECT block_id, bar_serial, batch_num, folder_slug, label, story_mode
       FROM qd_blocks
      WHERE bar_serial = ? AND batch_num = ?
      LIMIT 1'
  );
  $stmt->execute([$bar, $batchNum]);
  $row = $stmt->fetch();

  if (!$row) {
    respond(404, ['error' => 'No block registered for this bar/batch.', 'bar' => $bar, 'batch' => $batchNum]);
  }

  respond(200, [
    'block_id'    => $row['block_id'],
    'bar_serial'  => $row['bar_serial'],
    'batch_num'   => (int)$row['batch_num'],
    'folder_slug' => $row['folder_slug'],
    'label'       => $row['label'],
    'story_mode'  => $row['story_mode'],
  ]);

} catch (Throwable $e) {
  respond(500, ['error' => 'Server error.']);
}
