<?php
declare(strict_types=1);

/**
 * POST /api/webhook/ownership-change
 *
 * Called by the marketplace whenever a CNFT's current_owner_wallet changes
 * (secondary sale, transfer, gift). Main site uses this to keep cached/derived
 * views (certificate holder, public collector pages) fresh.
 *
 * Expected JSON body:
 *   {
 *     "event"          : "ownership.change",
 *     "cnft_id"        : "qd-silver-0000001",
 *     "previous_owner_display": "addr1q8…old0",   // optional, redacted
 *     "new_owner_display"     : "addr1q8…new0",   // optional, redacted
 *     "tx_hash"        : "abcdef...",             // optional (not always a tx)
 *     "changed_at"     : "2026-04-17T23:51:00Z"
 *   }
 */

require __DIR__ . '/_hmac.php';

$body = rf_webhook_authenticate_or_die();
$data = json_decode($body, true);
if (!is_array($data)) {
    rf_webhook_json_fail(400, 'invalid json body');
}

if (($data['event'] ?? '') !== 'ownership.change') {
    rf_webhook_json_fail(400, 'unexpected event type');
}

foreach (['cnft_id', 'changed_at'] as $k) {
    if (empty($data[$k]) || !is_string($data[$k])) {
        rf_webhook_json_fail(400, "missing or invalid field: $k");
    }
}

$logDir = __DIR__ . '/../../uploads/webhook-log';
if (!is_dir($logDir)) { @mkdir($logDir, 0700, true); }
$line = json_encode([
    'received_at'             => gmdate('c'),
    'event'                   => $data['event'],
    'cnft_id'                 => $data['cnft_id'],
    'previous_owner_display'  => $data['previous_owner_display'] ?? null,
    'new_owner_display'       => $data['new_owner_display']      ?? null,
    'tx_hash'                 => $data['tx_hash']                ?? null,
    'changed_at'              => $data['changed_at'],
], JSON_UNESCAPED_SLASHES);
@file_put_contents($logDir . '/ownership-change.log', $line . "\n", FILE_APPEND | LOCK_EX);

// Merge ownership update into the per-token JSON cache.
$cacheDir = __DIR__ . '/../../uploads/webhook-cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0700, true); }
$safeName = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $data['cnft_id']));
$cacheFile = $cacheDir . '/' . $safeName . '.json';

$cache = [];
if (is_file($cacheFile)) {
    $existing = @json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($existing)) $cache = $existing;
}

$cache['cnft_id']          = $data['cnft_id'];
$cache['owner_display']    = $data['new_owner_display']      ?? null;
$cache['prev_owner']       = $data['previous_owner_display'] ?? null;
$cache['ownership_tx']     = $data['tx_hash']                ?? null;
$cache['owner_changed_at'] = $data['changed_at'];
$cache['last_event']       = 'ownership.change';
$cache['last_event_at']    = gmdate('c');

@file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);

rf_webhook_ok(['recorded' => true]);
