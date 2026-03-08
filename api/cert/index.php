<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$certId = trim($_GET['cert'] ?? '');

if ($certId === '' || !preg_match('/^QDCERT-[A-Z0-9]+-\d{7}$/', $certId)) {
  respond(400, [
    'error' => 'Invalid certificate ID format.',
    'expected' => 'QDCERT-<BAR>-<CNFT_NUM_7DIGITS>'
  ]);
}

try {
  $pdo = qd_pdo();
  $stmt = $pdo->prepare('SELECT status, payload_json, pdf_storage_key FROM qd_certificates WHERE cert_id = ? LIMIT 1');
  $stmt->execute([$certId]);
  $row = $stmt->fetch();

  if (!$row) {
    respond(404, ['error' => 'Certificate record not found.', 'certId' => $certId]);
  }

  $payload = $row['payload_json'];
  if (is_string($payload)) {
    $payload = json_decode($payload, true);
  }
  if (!is_array($payload)) {
    respond(500, ['error' => 'Stored payload is not valid JSON.', 'certId' => $certId]);
  }

  // Canonical VERIFIED logic comes from DB status
  $payload['status'] = $row['status'];

  // Provide canonical download URL (immutable PDF served from outside-webroot storage)
  if (!empty($row['pdf_storage_key'])) {
    $payload['pdf'] = [
      'downloadUrl' => '/download.php?cert=' . rawurlencode($certId)
    ];
  }

  respond(200, $payload);

} catch (Throwable $e) {
  respond(500, ['error' => 'Server error.']);
}
