<?php
declare(strict_types=1);

/**
 * POST /api/webhook/mint-complete
 *
 * Called by the marketplace when a CNFT mint lands on-chain. Purpose on the
 * main site: log the event, optionally trigger cache invalidation, and (later)
 * send a confirmation email via the main-site mailer.
 *
 * Expected JSON body:
 *   {
 *     "event"        : "mint.complete",
 *     "cnft_id"      : "qd-silver-0000001",
 *     "bar_serial"   : "E101837",
 *     "tx_hash"      : "abcdef...",
 *     "policy_id"    : "...",
 *     "asset_fingerprint": "asset1...",
 *     "minted_at"    : "2026-04-17T23:50:00Z",
 *     "owner_display": "addr1q8…xy9z"  // optional, redacted form only
 *   }
 */

require __DIR__ . '/_hmac.php';

$body = rf_webhook_authenticate_or_die();
$data = json_decode($body, true);
if (!is_array($data)) {
    rf_webhook_json_fail(400, 'invalid json body');
}

if (($data['event'] ?? '') !== 'mint.complete') {
    rf_webhook_json_fail(400, 'unexpected event type');
}

foreach (['cnft_id', 'tx_hash', 'policy_id'] as $k) {
    if (empty($data[$k]) || !is_string($data[$k])) {
        rf_webhook_json_fail(400, "missing or invalid field: $k");
    }
}

// Minimal append-only log. Upgrade to DB in Phase 2.
$logDir = __DIR__ . '/../../uploads/webhook-log';
if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
$line = json_encode([
    'received_at'       => gmdate('c'),
    'event'             => $data['event'],
    'cnft_id'           => $data['cnft_id'],
    'bar_serial'        => $data['bar_serial']       ?? null,
    'tx_hash'           => $data['tx_hash'],
    'policy_id'         => $data['policy_id'],
    'asset_fingerprint' => $data['asset_fingerprint'] ?? null,
    'minted_at'         => $data['minted_at']        ?? null,
    'owner_display'     => $data['owner_display']    ?? null,
], JSON_UNESCAPED_SLASHES);
@file_put_contents($logDir . '/mint-complete.log', $line . "\n", FILE_APPEND | LOCK_EX);

// Per-token JSON cache — gives the main site a local proof of mint without
// hitting the marketplace API. Keyed by cnft_id. Ownership-change events
// merge into the same file. Fully server-side; never committed to git.
$cacheDir = __DIR__ . '/../../uploads/webhook-cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0700, true); }
$safeName = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $data['cnft_id']));
$cacheFile = $cacheDir . '/' . $safeName . '.json';

$cache = [];
if (is_file($cacheFile)) {
    $existing = @json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($existing)) $cache = $existing;
}

$cache['cnft_id']           = $data['cnft_id'];
$cache['bar_serial']        = $data['bar_serial']         ?? null;
$cache['policy_id']         = $data['policy_id'];
$cache['asset_fingerprint'] = $data['asset_fingerprint']  ?? null;
$cache['mint_tx_hash']      = $data['tx_hash'];
$cache['minted_at']         = $data['minted_at']          ?? null;
$cache['primary_sale']      = 'minted';
$cache['last_event']        = 'mint.complete';
$cache['last_event_at']     = gmdate('c');

@file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);

rf_webhook_ok(['recorded' => true]);
