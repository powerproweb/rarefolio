<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'POST') {
    rf_download_json_fail(405, 'POST required');
}

$body = rf_download_parse_json_body();

$challengeId   = trim((string) ($body['challenge_id'] ?? ''));
$signedAddress = trim((string) ($body['signed_address'] ?? ''));
$signature     = $body['signature'] ?? null;

if (!preg_match('/^[a-f0-9]{32}$/i', $challengeId)) {
    rf_download_json_fail(400, 'invalid challenge_id');
}
if ($signedAddress === '' || !is_array($signature)) {
    rf_download_json_fail(400, 'signed_address and signature are required');
}
if (!is_string($signature['signature'] ?? null) || !is_string($signature['key'] ?? null)) {
    rf_download_json_fail(400, 'signature must contain signature and key');
}

$challenge = rf_download_get_challenge($challengeId);
if (!$challenge) {
    rf_download_json_fail(404, 'challenge not found');
}
if (($challenge['used'] ?? false) === true) {
    rf_download_json_fail(409, 'challenge already used');
}

$expiresAt = (int) ($challenge['expires_at'] ?? 0);
if ($expiresAt < time()) {
    rf_download_json_fail(410, 'challenge expired');
}

$certId = (string) ($challenge['cert_id'] ?? '');
$cnftId = strtolower((string) ($challenge['cnft_id'] ?? ''));
$nonce  = (string) ($challenge['nonce'] ?? '');
if ($certId === '' || $cnftId === '' || $nonce === '') {
    rf_download_json_fail(500, 'challenge data invalid');
}

$verifyUrl    = rf_download_env('RF_MARKET_OWNERSHIP_VERIFY_URL');
$verifySecret = rf_download_env('RF_MARKET_OWNERSHIP_VERIFY_SECRET');
if ($verifyUrl === '' || $verifySecret === '') {
    rf_download_json_fail(503, 'download verification backend not configured');
}

try {
    $verifyResp = rf_download_http_post_json(
        $verifyUrl,
        [
            'cnft_id'        => $cnftId,
            'signed_address' => $signedAddress,
            'nonce'          => $nonce,
            'signature'      => [
                'signature' => (string) $signature['signature'],
                'key'       => (string) $signature['key'],
            ],
        ],
        ['Authorization: Bearer ' . $verifySecret]
    );
} catch (Throwable $e) {
    error_log('[download claim] verify backend error: ' . $e->getMessage());
    rf_download_json_fail(502, 'verification backend unavailable');
}

$verifyBody = $verifyResp['body'];
if (!is_array($verifyBody) || !($verifyBody['ok'] ?? false)) {
    rf_download_json_fail(403, 'wallet proof failed');
}

if (!($verifyBody['signature_valid'] ?? false)) {
    rf_download_json_fail(401, 'invalid wallet signature');
}

if (!($verifyBody['owns_token'] ?? false)) {
    rf_download_json_fail(403, 'wallet does not currently own this token');
}

$challenge['used']    = true;
$challenge['used_at'] = time();
rf_download_put_challenge($challengeId, $challenge);

try {
    $ticket = rf_download_issue_ticket([
        'challenge_id' => $challengeId,
        'cert_id'      => $certId,
        'cnft_id'      => $cnftId,
        'scope'        => 'download_pdf',
    ], 300);
} catch (Throwable $e) {
    error_log('[download claim] ticket issue error: ' . $e->getMessage());
    rf_download_json_fail(500, 'could not issue download ticket');
}

$downloadUrl = '/download.php?cert=' . rawurlencode($certId) . '&ticket=' . rawurlencode($ticket);

rf_download_json_ok([
    'download_url' => $downloadUrl,
    'expires_in'   => 300,
]);
