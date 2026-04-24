<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    rf_download_json_fail(405, 'POST required');
}

$body = rf_download_parse_json_body();
$certId = trim((string) ($body['cert_id'] ?? ''));
$cnftId = trim((string) ($body['cnft_id'] ?? ''));

if (!preg_match('/^QDCERT-[A-Z0-9]+-\d{7}$/', $certId)) {
    rf_download_json_fail(400, 'invalid cert_id format');
}
if (!preg_match('/^qd-silver-\d{7}$/', strtolower($cnftId))) {
    rf_download_json_fail(400, 'invalid cnft_id format');
}

$challengeId = bin2hex(random_bytes(16));
$nonceToken  = bin2hex(random_bytes(16));
$issuedAt    = time();
$expiresAt   = $issuedAt + 300;
$nonce       = "Rarefolio download claim\ncert={$certId}\ncnft={$cnftId}\nnonce={$nonceToken}\nts={$issuedAt}";

rf_download_put_challenge($challengeId, [
    'challenge_id' => $challengeId,
    'cert_id'      => $certId,
    'cnft_id'      => strtolower($cnftId),
    'nonce'        => $nonce,
    'issued_at'    => $issuedAt,
    'expires_at'   => $expiresAt,
    'used'         => false,
]);

rf_download_sweep_challenges();

rf_download_json_ok([
    'challenge_id' => $challengeId,
    'nonce'        => $nonce,
    'expires_at'   => gmdate('c', $expiresAt),
    'expires_in'   => 300,
]);
