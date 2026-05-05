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
function qd_normalize_no_emdash_text(string $text): string {
  $normalized = preg_replace('/\s*\\\\u2014\s*/i', ', ', $text);
  if (!is_string($normalized)) $normalized = $text;
  $normalized = preg_replace('/\s*\\\\u2013\s*/i', ', ', $normalized);
  if (!is_string($normalized)) $normalized = $text;
  if (function_exists('mb_chr')) {
    $emDash = mb_chr(8212, 'UTF-8');
    $enDash = mb_chr(8211, 'UTF-8');
    $normalized = str_replace([$emDash, $enDash], ', ', $normalized);
  } else {
    $tmp = preg_replace('/\x{2014}|\x{2013}/u', ', ', $normalized);
    if (is_string($tmp)) $normalized = $tmp;
  }
  $collapsed = preg_replace('/\s{2,}/', ' ', $normalized);
  return is_string($collapsed) ? trim($collapsed) : trim($normalized);
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
    'SELECT block_id, bar_serial, batch_num, folder_slug, label, story_mode, character_names
       FROM qd_blocks
      WHERE bar_serial = ? AND batch_num = ?
      LIMIT 1'
  );
  $stmt->execute([$bar, $batchNum]);
  $row = $stmt->fetch();

  if (!$row) {
    respond(404, ['error' => 'No block registered for this bar/batch.', 'bar' => $bar, 'batch' => $batchNum]);
  }

  // Decode character_names JSON; null if not set or invalid
  $characterNames = null;
  if ($row['character_names'] !== null) {
    $decoded = json_decode($row['character_names'], true);
    if (is_array($decoded)) {
      $characterNames = array_values(array_map(
        static fn($name): string => qd_normalize_no_emdash_text((string)$name),
        $decoded
      ));
    }
  }

  respond(200, [
    'block_id'        => $row['block_id'],
    'bar_serial'      => $row['bar_serial'],
    'batch_num'       => (int)$row['batch_num'],
    'folder_slug'     => $row['folder_slug'],
    'label'           => $row['label'],
    'story_mode'      => $row['story_mode'],
    'character_names' => $characterNames,
  ]);

} catch (Throwable $e) {
  respond(500, ['error' => 'Server error.']);
}
