<?php
declare(strict_types=1);

/**
 * Shared helpers for private download-claim flow.
 */

function rf_download_env(string $key): string
{
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return (string) $v;
    }

    static $fileVars = null;
    if ($fileVars === null) {
        $fileVars = [];
        $envFile  = __DIR__ . DIRECTORY_SEPARATOR . '.env';
        if (is_file($envFile) && is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = ltrim((string) $line, "\xEF\xBB\xBF");
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                $eq = strpos($line, '=');
                if ($eq === false) continue;
                $k = trim(substr($line, 0, $eq));
                $k = ltrim($k, "\xEF\xBB\xBF");
                $val = trim(substr($line, $eq + 1));
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                $fileVars[$k] = $val;
            }
        }
    }

    return (string) ($fileVars[$key] ?? '');
}

function rf_download_bool_env(string $key, bool $default = false): bool
{
    $v = strtolower(trim(rf_download_env($key)));
    if ($v === '') return $default;
    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function rf_download_json_fail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rf_download_json_ok(array $payload): void
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rf_download_parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
        rf_download_json_fail(400, 'invalid JSON');
    }
    return $body;
}

function rf_download_challenge_dir(): string
{
    $base = __DIR__ . '/../../uploads/download-claims/challenges';
    if (!is_dir($base)) {
        @mkdir($base, 0700, true);
    }
    return $base;
}

function rf_download_challenge_path(string $challengeId): string
{
    return rf_download_challenge_dir() . DIRECTORY_SEPARATOR . $challengeId . '.json';
}

function rf_download_put_challenge(string $challengeId, array $data): void
{
    $path = rf_download_challenge_path($challengeId);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || @file_put_contents($path, $json, LOCK_EX) === false) {
        rf_download_json_fail(500, 'could not persist challenge');
    }
}

function rf_download_get_challenge(string $challengeId): ?array
{
    $path = rf_download_challenge_path($challengeId);
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') return null;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function rf_download_http_post_json(string $url, array $payload, array $headers = []): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        throw new RuntimeException('could not encode request payload');
    }

    $baseHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $allHeaders = array_merge($baseHeaders, $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $respBody = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);
        if ($respBody === false) {
            throw new RuntimeException('http request failed: ' . $err);
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $allHeaders),
                'content' => $body,
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);
        $respBody = file_get_contents($url, false, $ctx);
        if ($respBody === false) {
            throw new RuntimeException('http request failed');
        }
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    $decoded = json_decode((string) $respBody, true);
    return [
        'status' => $status,
        'body'   => is_array($decoded) ? $decoded : null,
    ];
}

function rf_download_b64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function rf_download_b64url_decode(string $raw): string|false
{
    $padded = strtr($raw, '-_', '+/');
    $padLen = strlen($padded) % 4;
    if ($padLen > 0) {
        $padded .= str_repeat('=', 4 - $padLen);
    }
    return base64_decode($padded, true);
}

function rf_download_issue_ticket(array $claims, int $ttlSeconds = 300): string
{
    $secret = rf_download_env('RF_DOWNLOAD_TICKET_SECRET');
    if ($secret === '') {
        throw new RuntimeException('RF_DOWNLOAD_TICKET_SECRET not configured');
    }

    $now = time();
    $payload = array_merge($claims, [
        'iat' => $now,
        'exp' => $now + max(30, $ttlSeconds),
    ]);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($payloadJson)) {
        throw new RuntimeException('could not encode ticket payload');
    }

    $payloadEnc = rf_download_b64url_encode($payloadJson);
    $sigRaw     = hash_hmac('sha256', $payloadEnc, $secret, true);
    $sigEnc     = rf_download_b64url_encode($sigRaw);
    return $payloadEnc . '.' . $sigEnc;
}

function rf_download_verify_ticket(string $ticket): ?array
{
    $secret = rf_download_env('RF_DOWNLOAD_TICKET_SECRET');
    if ($secret === '') return null;

    $parts = explode('.', $ticket, 2);
    if (count($parts) !== 2) return null;
    [$payloadEnc, $sigEnc] = $parts;
    if ($payloadEnc === '' || $sigEnc === '') return null;

    $expectedSig = rf_download_b64url_encode(hash_hmac('sha256', $payloadEnc, $secret, true));
    if (!hash_equals($expectedSig, $sigEnc)) return null;

    $payloadRaw = rf_download_b64url_decode($payloadEnc);
    if (!is_string($payloadRaw) || $payloadRaw === '') return null;
    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) return null;
    if (!isset($payload['exp']) || !is_numeric($payload['exp'])) return null;
    if ((int) $payload['exp'] < time()) return null;

    return $payload;
}

function rf_download_sweep_challenges(int $maxAgeSeconds = 3600): void
{
    if (mt_rand(1, 50) !== 1) return;
    $dir = rf_download_challenge_dir();
    $now = time();
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $f) {
        if (!is_file($f)) continue;
        $mt = filemtime($f);
        if ($mt !== false && ($now - $mt) > $maxAgeSeconds) {
            @unlink($f);
        }
    }
}
