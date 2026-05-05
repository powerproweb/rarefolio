<?php
declare(strict_types=1);

/**
 * Shared HMAC verifier + replay protection for marketplace -> main-site webhooks.
 *
 * Required headers on incoming requests:
 *   X-RF-Timestamp : unix epoch seconds (integer). Must be within RF_WEBHOOK_MAX_SKEW of now.
 *   X-RF-Nonce     : random, unique per request. Rejected if seen before.
 *   X-RF-Signature : "sha256=<hex>" of HMAC_SHA256(secret, timestamp + "." + nonce + "." + body)
 *
 * Env:
 *   RF_WEBHOOK_SECRET, shared secret (required; keep out of git)
 *   RF_WEBHOOK_MAX_SKEW, optional, default 300 seconds
 *
 * Usage:
 *   require __DIR__ . '/_hmac.php';
 *   $body = rf_webhook_authenticate_or_die();
 */

const RF_WEBHOOK_SECRET_ENV = 'RF_WEBHOOK_SECRET';

/**
 * Reads an env var, falling back to api/webhook/.env if not set at the OS level.
 * This lets shared-hosting setups that can't set real env vars still configure
 * the webhook receiver via a plain file. The .env file is gitignored.
 */
function rf_webhook_env(string $key): string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    static $fileVars = null;
    if ($fileVars === null) {
        $fileVars = [];
        $envFile  = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envFile) && is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                $eq = strpos($line, '=');
                if ($eq === false) continue;
                $k = trim(substr($line, 0, $eq));
                $val = trim(substr($line, $eq + 1));
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                $fileVars[$k] = $val;
            }
        }
    }
    return $fileVars[$key] ?? '';
}

function rf_webhook_nonce_dir(): string
{
    // Use /tmp/rf_webhook_nonces by default. Override with RF_WEBHOOK_NONCE_DIR env.
    $override = rf_webhook_env('RF_WEBHOOK_NONCE_DIR');
    $dir = $override !== '' ? $override : (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rf_webhook_nonces');
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function rf_webhook_json_fail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

/**
 * Validates incoming webhook; returns the raw body string on success.
 * Terminates the request (JSON error) on any failure.
 */
function rf_webhook_authenticate_or_die(): string
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        rf_webhook_json_fail(405, 'POST required');
    }

    $secret = rf_webhook_env(RF_WEBHOOK_SECRET_ENV);
    if ($secret === '') {
        rf_webhook_json_fail(503, 'webhook secret not configured');
    }

    $ts    = $_SERVER['HTTP_X_RF_TIMESTAMP'] ?? '';
    $nonce = $_SERVER['HTTP_X_RF_NONCE']     ?? '';
    $sig   = $_SERVER['HTTP_X_RF_SIGNATURE'] ?? '';

    if ($ts === '' || $nonce === '' || $sig === '') {
        rf_webhook_json_fail(400, 'missing required headers');
    }
    if (!ctype_digit($ts)) {
        rf_webhook_json_fail(400, 'invalid timestamp');
    }

    $maxSkew = (int) (rf_webhook_env('RF_WEBHOOK_MAX_SKEW') ?: 300);
    if (abs(time() - (int) $ts) > $maxSkew) {
        rf_webhook_json_fail(400, 'timestamp outside allowed skew');
    }

    // Body (raw, exactly what the sender signed)
    $body = (string) file_get_contents('php://input');

    // Compute expected signature
    $payload  = $ts . '.' . $nonce . '.' . $body;
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

    if (!hash_equals($expected, (string) $sig)) {
        rf_webhook_json_fail(401, 'signature mismatch');
    }

    // Replay protection: nonce file must NOT exist
    // Nonce is up to 64 safe chars; reject anything else to prevent path tricks.
    if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $nonce)) {
        rf_webhook_json_fail(400, 'invalid nonce format');
    }
    $nonceFile = rf_webhook_nonce_dir() . DIRECTORY_SEPARATOR . $nonce;
    if (is_file($nonceFile)) {
        rf_webhook_json_fail(409, 'replayed nonce');
    }
    // Record the nonce. A small TTL sweep keeps the directory bounded.
    @file_put_contents($nonceFile, (string) time(), LOCK_EX);
    rf_webhook_sweep_old_nonces($maxSkew * 4);

    return $body;
}

function rf_webhook_sweep_old_nonces(int $maxAgeSeconds): void
{
    // Best-effort cleanup. Runs occasionally to avoid heavy IO on each request.
    if (mt_rand(1, 50) !== 1) return;
    $dir = rf_webhook_nonce_dir();
    $now = time();
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
        if (!is_file($f)) continue;
        $mt = filemtime($f);
        if ($mt !== false && ($now - $mt) > $maxAgeSeconds) {
            @unlink($f);
        }
    }
}

function rf_webhook_ok(array $data = []): void
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}
