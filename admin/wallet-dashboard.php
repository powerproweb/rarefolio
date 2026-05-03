<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_config.php';

function rf_admin_auth_credentials(): array
{
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW']   ?? '';
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (($u === '' || $p === '') && is_string($auth) && $auth !== '' && stripos($auth, 'basic ') === 0) {
        $decoded = base64_decode(substr($auth, 6));
        if ($decoded !== false && strpos($decoded, ':') !== false) {
            [$u, $p] = explode(':', $decoded, 2);
        }
    }
    return [(string)$u, (string)$p];
}

function rf_admin_require_basic_auth(): void
{
    [$u, $p] = rf_admin_auth_credentials();
    if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
        header('WWW-Authenticate: Basic realm="Rarefolio Wallet Dashboard"');
        http_response_code(401);
        exit('Unauthorized');
    }
}

function rf_admin_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rf_admin_parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    $in = json_decode((string)$raw, true);
    return is_array($in) ? $in : [];
}

function rf_admin_market_base(): string
{
    $env = trim((string)getenv('RF_MARKET_BASE'));
    if ($env !== '' && filter_var($env, FILTER_VALIDATE_URL)) {
        return rtrim($env, '/');
    }
    return 'https://market.rarefolio.io';
}

function rf_admin_http_post_json(string $url, array $payload, array $headers = []): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        throw new RuntimeException('could not encode payload');
    }

    $allHeaders = array_merge(
        [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        $headers
    );

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $respBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($respBody === false) {
            throw new RuntimeException('http post failed: ' . $err);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $allHeaders),
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $respBody = file_get_contents($url, false, $context);
        if ($respBody === false) {
            throw new RuntimeException('http post failed');
        }
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
    }

    $decoded = json_decode((string)$respBody, true);
    return [
        'status' => $status,
        'body'   => is_array($decoded) ? $decoded : null,
    ];
}

function rf_admin_http_get_json(string $url, array $headers = []): array
{
    $allHeaders = array_merge(['Accept: application/json'], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $respBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($respBody === false) {
            throw new RuntimeException('http get failed: ' . $err);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $allHeaders),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $respBody = file_get_contents($url, false, $context);
        if ($respBody === false) {
            throw new RuntimeException('http get failed');
        }
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
    }

    $decoded = json_decode((string)$respBody, true);
    return [
        'status' => $status,
        'body'   => is_array($decoded) ? $decoded : null,
    ];
}

function rf_admin_sanitize_addresses(mixed $raw): array
{
    if (!is_array($raw)) return [];
    $out = [];
    foreach (array_slice($raw, 0, 20) as $addr) {
        if (!is_string($addr)) continue;
        $v = trim($addr);
        if ($v === '' || strlen($v) < 10 || strlen($v) > 512) continue;
        if (!preg_match('/^[a-zA-Z0-9]+$/', $v)) continue;
        if (!in_array($v, $out, true)) $out[] = $v;
    }
    return $out;
}

function rf_admin_validate_cnft_id(string $v): ?string
{
    $v = strtolower(trim($v));
    return preg_match('/^qd-silver-\d{7}$/', $v) ? $v : null;
}

rf_admin_require_basic_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = rf_admin_parse_json_body();
    $action = (string)($in['action'] ?? '');
    $marketBase = rf_admin_market_base();

    if ($action === 'collection') {
        $addresses = rf_admin_sanitize_addresses($in['addresses'] ?? []);
        if ($addresses === []) {
            rf_admin_json_response(400, ['ok' => false, 'error' => 'Provide at least one valid wallet address.']);
        }

        try {
            $resp = rf_admin_http_post_json($marketBase . '/api/my-collection.php', ['addresses' => $addresses]);
        } catch (Throwable $e) {
            error_log('[admin wallet dashboard] collection proxy failed: ' . $e->getMessage());
            rf_admin_json_response(502, ['ok' => false, 'error' => 'Marketplace collection API is unavailable.']);
        }

        $body = $resp['body'];
        if (!is_array($body) || !($body['ok'] ?? false)) {
            rf_admin_json_response(502, ['ok' => false, 'error' => 'Unexpected marketplace collection response.']);
        }

        $tokens = is_array($body['tokens'] ?? null) ? $body['tokens'] : [];
        $orders = is_array($body['orders'] ?? null) ? $body['orders'] : [];
        $total  = isset($body['total']) && is_numeric($body['total'])
            ? (int)$body['total']
            : (count($tokens) + count($orders));

        rf_admin_json_response(200, [
            'ok' => true,
            'tokens' => $tokens,
            'orders' => $orders,
            'total' => $total,
        ]);
    }

    if ($action === 'token_lookup') {
        $cnftId = rf_admin_validate_cnft_id((string)($in['cnftId'] ?? ''));
        if ($cnftId === null) {
            rf_admin_json_response(400, ['ok' => false, 'error' => 'Invalid CNFT ID format. Use qd-silver-0000001.']);
        }

        try {
            $resp = rf_admin_http_get_json($marketBase . '/api/v1/tokens/' . rawurlencode($cnftId));
        } catch (Throwable $e) {
            error_log('[admin wallet dashboard] token lookup proxy failed: ' . $e->getMessage());
            rf_admin_json_response(502, ['ok' => false, 'error' => 'Marketplace token API is unavailable.']);
        }

        $body = $resp['body'];
        if (!is_array($body) || !($body['ok'] ?? false) || !is_array($body['data'] ?? null)) {
            rf_admin_json_response(404, ['ok' => false, 'error' => 'Token not found in marketplace API.']);
        }

        rf_admin_json_response(200, ['ok' => true, 'token' => $body['data']]);
    }

    if ($action === 'ownership_verify') {
        $cnftId = rf_admin_validate_cnft_id((string)($in['cnftId'] ?? ''));
        $signedAddress = trim((string)($in['signedAddress'] ?? ''));
        $nonce = (string)($in['nonce'] ?? '');
        $sig = $in['signature'] ?? null;

        if ($cnftId === null) {
            rf_admin_json_response(400, ['ok' => false, 'error' => 'Invalid CNFT ID format. Use qd-silver-0000001.']);
        }
        if ($signedAddress === '' || strlen($signedAddress) > 512) {
            rf_admin_json_response(400, ['ok' => false, 'error' => 'Missing or invalid signed address.']);
        }
        if ($nonce === '' || strlen($nonce) > 4096) {
            rf_admin_json_response(400, ['ok' => false, 'error' => 'Missing or invalid nonce.']);
        }
        if (!is_array($sig) || !is_string($sig['signature'] ?? null) || !is_string($sig['key'] ?? null)) {
            rf_admin_json_response(400, ['ok' => false, 'error' => 'Missing or invalid signature payload.']);
        }

        $verifyUrl = trim((string)getenv('RF_MARKET_OWNERSHIP_VERIFY_URL'));
        if ($verifyUrl === '') {
            $verifyUrl = $marketBase . '/api/private/ownership-verify.php';
        }
        $verifySecret = trim((string)getenv('RF_MARKET_OWNERSHIP_VERIFY_SECRET'));
        if ($verifySecret === '') {
            rf_admin_json_response(503, ['ok' => false, 'error' => 'Ownership verifier secret is not configured on this host.']);
        }

        try {
            $resp = rf_admin_http_post_json(
                $verifyUrl,
                [
                    'cnft_id' => $cnftId,
                    'signed_address' => $signedAddress,
                    'nonce' => $nonce,
                    'signature' => [
                        'signature' => (string)$sig['signature'],
                        'key' => (string)$sig['key'],
                    ],
                ],
                ['Authorization: Bearer ' . $verifySecret]
            );
        } catch (Throwable $e) {
            error_log('[admin wallet dashboard] ownership verify proxy failed: ' . $e->getMessage());
            rf_admin_json_response(502, ['ok' => false, 'error' => 'Ownership verifier backend is unavailable.']);
        }

        $body = $resp['body'];
        if (!is_array($body) || !($body['ok'] ?? false)) {
            $err = is_array($body) && is_string($body['error'] ?? null)
                ? $body['error']
                : 'Ownership verifier rejected this request.';
            rf_admin_json_response(403, ['ok' => false, 'error' => $err]);
        }

        rf_admin_json_response(200, [
            'ok' => true,
            'result' => [
                'signature_valid' => (bool)($body['signature_valid'] ?? false),
                'owns_token' => (bool)($body['owns_token'] ?? false),
                'signed_reward_address' => is_string($body['signed_reward_address'] ?? null) ? $body['signed_reward_address'] : null,
                'owner_reward_address' => is_string($body['owner_reward_address'] ?? null) ? $body['owner_reward_address'] : null,
            ],
        ]);
    }

    rf_admin_json_response(400, ['ok' => false, 'error' => 'Unknown action.']);
}

$marketBase = rf_admin_market_base();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Wallet Operations — Rarefolio Admin</title>
<style>
:root {
  --bg: #050a18;
  --surface: #0d1526;
  --surface2: #121e35;
  --border: #1e2d4a;
  --gold: #d9b46c;
  --text: #c8d4e8;
  --muted: #6a7a96;
  --ok: #4caf7d;
  --err: #e05252;
  --warn: #e3b64f;
  --cyan: #5dd5ff;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: system-ui, -apple-system, sans-serif;
  font-size: 14px;
  min-height: 100vh;
  padding: 20px 24px;
}
h1 { color: var(--gold); font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
h2 { color: var(--gold); font-size: 17px; margin-bottom: 10px; }
p { line-height: 1.55; }
a { color: var(--cyan); text-decoration: none; }
a:hover { text-decoration: underline; }

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 16px;
}
.top-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.btn {
  background: var(--gold);
  color: #050a18;
  border: none;
  border-radius: 6px;
  padding: 8px 18px;
  cursor: pointer;
  font-weight: 700;
  font-size: 13px;
  transition: opacity .15s;
  white-space: nowrap;
}
.btn:hover { opacity: .86; text-decoration: none; }
.btn.secondary {
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
}
.btn.secondary:hover { border-color: var(--gold); color: var(--gold); }
.btn.sm { padding: 7px 14px; font-size: 12px; }
.btn:disabled { opacity: .45; cursor: default; }

.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px 18px;
  margin-bottom: 14px;
}
.section-label {
  color: var(--muted);
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .07em;
  font-weight: 600;
  margin-bottom: 5px;
}
.muted { color: var(--muted); }

.status {
  margin-top: 10px;
  font-size: 12px;
  padding: 7px 12px;
  border-radius: 6px;
  border: 1px solid transparent;
}
.status.ok { background: #0d2b1c; color: var(--ok); border-color: var(--ok); }
.status.err { background: #2b0d0d; color: var(--err); border-color: var(--err); }
.status.warn { background: #2d2209; color: var(--warn); border-color: var(--warn); }
.status.loading { background: var(--surface2); color: var(--muted); border-color: var(--border); }

.controls { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
.wallet-picker {
  display: inline-flex;
  flex-direction: column;
  gap: 4px;
  min-width: 210px;
}
.wallet-picker .section-label { margin: 0; }
.wallet-picker select {
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  outline: none;
}
.wallet-picker select:focus { border-color: var(--gold); }

.wallet-meta {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 8px;
}
.meta-pill {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 10px;
}
.meta-pill strong { color: var(--gold); display: block; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 4px; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

.collection-grid {
  margin-top: 12px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}
.card {
  background: rgba(255,255,255,.02);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}
.card:hover { border-color: rgba(217,180,108,.5); }
.card-media { background: #081226; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; color: var(--muted); }
.card-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
.card-body { padding: 12px; }
.card-title { color: #ffefbd; font-weight: 700; margin-bottom: 4px; line-height: 1.35; }
.card-sub { color: var(--muted); font-size: 12px; margin-bottom: 8px; }
.badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.badge {
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 11px;
  border: 1px solid var(--border);
  background: var(--surface2);
  color: var(--text);
}
.badge.ok { color: var(--ok); border-color: rgba(76,175,125,.45); background: rgba(76,175,125,.12); }
.badge.warn { color: var(--warn); border-color: rgba(227,182,79,.45); background: rgba(227,182,79,.12); }
.links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.links a {
  font-size: 12px;
  border: 1px solid var(--border);
  padding: 5px 9px;
  border-radius: 6px;
  color: var(--text);
}
.links a:hover { border-color: var(--gold); color: var(--gold); text-decoration: none; }

.verify-layout {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 10px;
}
.field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 8px; }
.field input {
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 13px;
  outline: none;
}
.field input:focus { border-color: var(--gold); }
.code-block {
  margin-top: 8px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px;
  min-height: 140px;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 12px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}
.bridge-links { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }

@media (max-width: 720px) {
  body { padding: 15px 14px; }
  .btn { width: 100%; }
  .btn.sm { width: auto; }
}
</style>
</head>
<body>

<div class="topbar">
  <h1>⬡ Rarefolio Wallet Operations</h1>
  <div class="top-actions">
    <a class="btn secondary sm" href="/admin/index.php">Admin Home</a>
    <a class="btn secondary sm" href="/admin/story-editor.php">Story Editor</a>
    <a class="btn secondary sm" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/admin/index.php" target="_blank" rel="noopener">Market Admin</a>
  </div>
</div>

<section class="panel">
  <h2>Wallet Dashboard</h2>
  <p class="muted">Connect a CIP-30 wallet to inspect holdings. This dashboard is read-only and does not submit transactions.</p>
  <div class="controls">
    <label class="wallet-picker" for="wallet-provider-select">
      <span class="section-label">Wallet Provider</span>
      <select id="wallet-provider-select"></select>
    </label>
    <button class="btn" id="btn-connect" type="button">Connect Wallet</button>
    <button class="btn secondary" id="btn-switch-account" type="button">Switch Wallet / Account</button>
    <button class="btn secondary" id="btn-refresh" type="button" disabled>Refresh Holdings</button>
    <button class="btn secondary" id="btn-disconnect" type="button" disabled>Disconnect</button>
  </div>
  <div class="status" id="wallet-status">Not connected.</div>
  <div class="wallet-meta" id="wallet-meta" style="display:none;">
    <div class="meta-pill">
      <strong>Wallet Provider</strong>
      <span id="meta-wallet-provider">—</span>
    </div>
    <div class="meta-pill">
      <strong>Primary Address</strong>
      <span class="mono" id="meta-wallet-address">—</span>
    </div>
    <div class="meta-pill">
      <strong>Address Count</strong>
      <span id="meta-wallet-count">0</span>
    </div>
  </div>
</section>

<section class="panel">
  <h2>Collection Results</h2>
  <p class="muted">Tokens and pending orders resolved from marketplace ownership records for the connected wallet.</p>
  <div class="status loading" id="collection-status" style="display:none;">Loading collection…</div>
  <div id="collection-empty" class="muted" style="margin-top:10px;">Connect a wallet to load collection data.</div>
  <div class="collection-grid" id="collection-grid"></div>
</section>

<section class="panel">
  <h2>Ownership Verification Tools</h2>
  <p class="muted">Look up a token and verify whether the currently connected wallet is the owner using signed wallet proof.</p>
  <div class="verify-layout">
    <div>
      <div class="field">
        <div class="section-label">CNFT ID</div>
        <input id="ov-cnft" type="text" value="qd-silver-0000705" placeholder="qd-silver-0000001" />
      </div>
      <div class="controls">
        <button class="btn secondary" id="btn-lookup-token" type="button">Lookup Token</button>
        <button class="btn" id="btn-verify-owner" type="button" disabled>Verify Connected Wallet Owns Token</button>
      </div>
      <div class="status" id="verify-status">No verification yet.</div>
    </div>
    <div>
      <div class="section-label">Token Lookup</div>
      <div class="code-block" id="token-lookup-result">No lookup response yet.</div>
    </div>
    <div>
      <div class="section-label">Ownership Verification</div>
      <div class="code-block" id="ownership-result">No ownership verification response yet.</div>
    </div>
  </div>
</section>

<section class="panel">
  <h2>Market Admin Operations Bridge</h2>
  <p class="muted">Jump into marketplace admin operations without leaving the wallet context.</p>
  <div class="bridge-links">
    <a class="btn secondary sm" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/admin/index.php" target="_blank" rel="noopener">Market Overview</a>
    <a class="btn secondary sm" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/admin/orders.php" target="_blank" rel="noopener">Orders Operations</a>
    <a class="btn secondary sm" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/admin/mint.php" target="_blank" rel="noopener">Mint Queue</a>
    <a class="btn secondary sm" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/admin/companion-grants.php" target="_blank" rel="noopener">Companion Grants</a>
    <a class="btn secondary sm" id="bridge-asset-lookup" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>/admin/asset-lookup.php" target="_blank" rel="noopener">Asset Lookup / Ownership Sync</a>
    <a class="btn secondary sm" id="bridge-buy-page" href="<?= htmlspecialchars($marketBase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener">Open Token Buy Page</a>
  </div>
</section>

<script>
(() => {
  const API_ENDPOINT = location.pathname;
  const MARKET_BASE = <?= json_encode($marketBase, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  const $ = (id) => document.getElementById(id);

  let walletApi = null;
  let walletProvider = '';
  let walletAddresses = [];
  const WALLET_PREFERRED_ORDER = ['eternl', 'lace', 'nami', 'typhon', 'flint', 'yoroi'];

  function isAccountChangeError(err) {
    const code = Number(err && err.code);
    const info = String((err && err.info) || (err && err.message) || err || '').toLowerCase();
    return code === -4 || (info.includes('account') && info.includes('change'));
  }

  function humanWalletName(key) {
    const cardano = window.cardano || {};
    const injected = cardano[key];
    const name = injected && typeof injected.name === 'string' ? injected.name.trim() : '';
    return name || key;
  }

  function discoverWalletProviders() {
    const cardano = window.cardano;
    if (!cardano || typeof cardano !== 'object') return [];

    const keys = Object.keys(cardano).filter((key) => {
      const candidate = cardano[key];
      return candidate && typeof candidate.enable === 'function';
    });
    const rank = new Map(WALLET_PREFERRED_ORDER.map((k, i) => [k, i]));
    keys.sort((a, b) => {
      const ai = rank.has(a) ? rank.get(a) : Number.MAX_SAFE_INTEGER;
      const bi = rank.has(b) ? rank.get(b) : Number.MAX_SAFE_INTEGER;
      if (ai !== bi) return ai - bi;
      return a.localeCompare(b);
    });
    return keys;
  }

  function selectedWalletKey() {
    const select = $('wallet-provider-select');
    return (select && typeof select.value === 'string') ? select.value : '';
  }

  function populateWalletProviderSelect() {
    const select = $('wallet-provider-select');
    if (!select) return;

    const providers = discoverWalletProviders();
    const previous = select.value || '';
    select.innerHTML = '';

    if (!providers.length) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'No wallet extension detected';
      select.appendChild(opt);
      select.disabled = true;
      return;
    }

    providers.forEach((key) => {
      const opt = document.createElement('option');
      opt.value = key;
      opt.textContent = `${humanWalletName(key)} (${key})`;
      select.appendChild(opt);
    });

    const preferred = providers.includes('eternl') ? 'eternl' : providers[0];
    select.value = providers.includes(previous) ? previous : preferred;
    select.disabled = false;
  }

  function escHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function safeUrl(raw) {
    if (!raw) return '';
    try {
      const u = new URL(String(raw), window.location.origin);
      return /^https?:$/.test(u.protocol) ? u.href : '';
    } catch {
      return '';
    }
  }

  function setStatus(el, message, kind) {
    el.textContent = message;
    el.className = 'status' + (kind ? ' ' + kind : '');
  }

  function shortValue(v) {
    const s = String(v || '');
    if (s.length <= 30) return s;
    return s.slice(0, 18) + '…' + s.slice(-10);
  }

  function statusLabel(status) {
    const map = {
      confirmed: 'delivered',
      submitted: 'submitted',
      pending: 'queued',
      failed: 'failed',
      awaiting_settlement: 'awaiting settlement',
      not_queued: 'not queued yet',
      not_enabled: 'not enabled',
      sold: 'sold',
      minted: 'minted',
      available: 'available',
    };
    return map[status] || status || 'unknown';
  }

  function companionProofMeta(companion, proof) {
    const links = [];
    const manifest = safeUrl(proof?.manifest_uri || '');
    const evidence = safeUrl(proof?.evidence_public_url || '');
    const txHash = companion?.delivery?.tx_hash || '';
    if (manifest) links.push(`<a href="${escHtml(manifest)}" target="_blank" rel="noopener">Manifest</a>`);
    if (evidence) links.push(`<a href="${escHtml(evidence)}" target="_blank" rel="noopener">Evidence</a>`);
    if (txHash) links.push(`<a href="https://cardanoscan.io/transaction/${encodeURIComponent(txHash)}" target="_blank" rel="noopener">Companion Tx</a>`);
    if (!links.length) return '';
    return `<div class="links">${links.join('')}</div>`;
  }

  function tokenCard(t) {
    const companion = t.companion || {};
    const proof = t.proof || {};
    const imgUrl = safeUrl(t.image_url || '');
    const media = imgUrl
      ? `<img src="${escHtml(imgUrl)}" alt="${escHtml(t.title || t.cnft_id || 'NFT artwork')}" loading="lazy">`
      : `<span>Artwork unavailable</span>`;

    return `
      <article class="card">
        <div class="card-media">${media}</div>
        <div class="card-body">
          <div class="card-title">${escHtml(t.title || t.cnft_id || 'Untitled token')}</div>
          <div class="card-sub">${escHtml(t.character_name || t.collection || '')}</div>
          <div class="badges">
            <span class="badge ok">Owned</span>
            <span class="badge">${escHtml(t.cnft_id || '—')}</span>
            ${t.status ? `<span class="badge">${escHtml(statusLabel(t.status))}</span>` : ''}
            ${companion.enabled ? `<span class="badge warn">Companion: ${escHtml(statusLabel(companion.delivery?.status || 'not_queued'))}</span>` : ''}
          </div>
          ${companionProofMeta(companion, proof)}
          <div class="links">
            <a href="/verify.html?nft=${encodeURIComponent(t.cnft_id || '')}" target="_blank" rel="noopener">Verify</a>
            <a href="/nft.html?nft=${encodeURIComponent(t.cnft_id || '')}" target="_blank" rel="noopener">NFT Detail</a>
            <a href="${escHtml(MARKET_BASE)}/buy.php?token=${encodeURIComponent(t.cnft_id || '')}" target="_blank" rel="noopener">Market</a>
          </div>
        </div>
      </article>
    `;
  }

  function orderCard(o) {
    const companion = o.companion || {};
    const proof = o.proof || {};
    const imgUrl = safeUrl(o.image_url || '');
    const media = imgUrl
      ? `<img src="${escHtml(imgUrl)}" alt="${escHtml(o.title || o.cnft_id || 'NFT artwork')}" loading="lazy">`
      : `<span>Artwork unavailable</span>`;

    return `
      <article class="card">
        <div class="card-media">${media}</div>
        <div class="card-body">
          <div class="card-title">${escHtml(o.title || o.cnft_id || 'Pending order')}</div>
          <div class="card-sub">${escHtml(o.note || 'Ownership sync pending')}</div>
          <div class="badges">
            <span class="badge warn">Pending</span>
            <span class="badge">${escHtml(o.cnft_id || '—')}</span>
            ${o.amount_ada != null ? `<span class="badge">${escHtml(String(o.amount_ada))} ₳</span>` : ''}
            ${companion.enabled ? `<span class="badge warn">Companion: ${escHtml(statusLabel(companion.delivery?.status || 'pending'))}</span>` : ''}
          </div>
          ${companionProofMeta(companion, proof)}
          <div class="links">
            <a href="${escHtml(MARKET_BASE)}/order-status.php?order=${encodeURIComponent(String(o.order_id || ''))}" target="_blank" rel="noopener">Order Status</a>
          </div>
        </div>
      </article>
    `;
  }

  function renderCollection(tokens, orders) {
    const grid = $('collection-grid');
    const empty = $('collection-empty');
    grid.innerHTML = '';

    const fragments = [];
    (tokens || []).forEach((t) => fragments.push(tokenCard(t)));
    (orders || []).forEach((o) => fragments.push(orderCard(o)));

    if (!fragments.length) {
      empty.textContent = 'No Rarefolio tokens were found for the connected wallet.';
      empty.style.display = '';
      return;
    }

    empty.style.display = 'none';
    grid.innerHTML = fragments.join('');
  }

  function updateWalletMeta() {
    const meta = $('wallet-meta');
    if (!walletApi || !walletAddresses.length) {
      meta.style.display = 'none';
      return;
    }
    $('meta-wallet-provider').textContent = walletProvider ? humanWalletName(walletProvider) : 'unknown';
    $('meta-wallet-address').textContent = shortValue(walletAddresses[0]);
    $('meta-wallet-count').textContent = String(walletAddresses.length);
    meta.style.display = 'grid';
  }

  async function pickWallet(requestedKey = '') {
    const cardano = window.cardano;
    if (!cardano) throw new Error('No Cardano wallet extension detected.');
    const providers = discoverWalletProviders();
    if (!providers.length) throw new Error('No CIP-30 compatible wallet was found.');

    const target = requestedKey && providers.includes(requestedKey)
      ? requestedKey
      : providers[0];
    if (!target) throw new Error('No wallet provider is available for connection.');
    return { key: target, api: await cardano[target].enable() };
  }

  async function collectAddresses(api) {
    const used = await api.getUsedAddresses();
    const change = await api.getChangeAddress();
    const reward = typeof api.getRewardAddresses === 'function'
      ? await api.getRewardAddresses()
      : [];
    const set = new Set();
    (Array.isArray(used) ? used : []).forEach((v) => {
      if (typeof v === 'string' && v.length >= 10) set.add(v);
    });
    (Array.isArray(reward) ? reward : []).forEach((v) => {
      if (typeof v === 'string' && v.length >= 10) set.add(v);
    });
    if (typeof change === 'string' && change.length >= 10) set.add(change);
    return Array.from(set);
  }

  async function refreshConnectedAddresses() {
    if (!walletApi) {
      throw new Error('Wallet is not connected.');
    }
    try {
      const addresses = await collectAddresses(walletApi);
      if (!addresses.length) {
        throw new Error('Wallet returned no addresses.');
      }
      walletAddresses = addresses;
      updateWalletMeta();
      return addresses;
    } catch (err) {
      if (isAccountChangeError(err)) {
        throw new Error('Wallet account changed. Click "Switch Wallet / Account" then reconnect.');
      }
      throw err;
    }
  }

  async function postAction(payload) {
    const resp = await fetch(API_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok || !data.ok) {
      throw new Error(data.error || ('HTTP ' + resp.status));
    }
    return data;
  }

  async function loadCollection() {
    const addresses = await refreshConnectedAddresses();

    const statusEl = $('collection-status');
    statusEl.style.display = '';
    setStatus(statusEl, 'Loading collection data…', 'loading');

    const data = await postAction({
      action: 'collection',
      addresses,
    });

    renderCollection(data.tokens || [], data.orders || []);
    statusEl.style.display = '';
    setStatus(
      statusEl,
      `Loaded ${data.total ?? ((data.tokens || []).length + (data.orders || []).length)} item(s) from marketplace ownership records.`,
      'ok'
    );
  }

  async function connectWallet() {
    const walletStatus = $('wallet-status');
    populateWalletProviderSelect();
    const chosen = selectedWalletKey();
    if (!chosen) {
      setStatus(walletStatus, 'No wallet extension detected. Install/unlock Eternl and try again.', 'err');
      return;
    }
    $('btn-connect').disabled = true;
    $('btn-switch-account').disabled = true;
    setStatus(walletStatus, 'Connecting wallet…', 'loading');
    try {
      const { key, api } = await pickWallet(chosen);
      const addresses = await collectAddresses(api);
      if (!addresses.length) {
        throw new Error('Wallet returned no addresses.');
      }

      walletApi = api;
      walletProvider = key;
      walletAddresses = addresses;

      $('btn-refresh').disabled = false;
      $('btn-disconnect').disabled = false;
      $('btn-verify-owner').disabled = false;
      updateWalletMeta();

      await loadCollection();
      setStatus(walletStatus, `Connected (${humanWalletName(key)}). Use "Switch Wallet / Account" to change Eternl account.`, 'ok');
    } catch (e) {
      walletApi = null;
      walletProvider = '';
      walletAddresses = [];
      updateWalletMeta();
      const msg = isAccountChangeError(e)
        ? 'Wallet account changed. Click "Switch Wallet / Account" then reconnect.'
        : 'Error: ' + (e?.message || String(e));
      setStatus(walletStatus, msg, 'err');
    } finally {
      $('btn-connect').disabled = false;
      $('btn-switch-account').disabled = false;
    }
  }

  function disconnectWallet(customMessage = 'Disconnected.', customKind = '') {
    walletApi = null;
    walletProvider = '';
    walletAddresses = [];
    updateWalletMeta();
    $('btn-refresh').disabled = true;
    $('btn-disconnect').disabled = true;
    $('btn-verify-owner').disabled = true;
    $('collection-grid').innerHTML = '';
    $('collection-empty').style.display = '';
    $('collection-empty').textContent = 'Connect a wallet to load collection data.';
    setStatus($('wallet-status'), customMessage, customKind);
    setStatus($('collection-status'), 'No active wallet session.', '');
  }

  function switchWalletAccount() {
    const key = selectedWalletKey();
    const walletLabel = key ? humanWalletName(key) : 'your wallet';
    disconnectWallet(
      `Session cleared. In ${walletLabel}, switch to the target account/wallet, then click Connect Wallet.`,
      'warn'
    );
  }

  function currentCnftId() {
    const raw = String(($('ov-cnft').value || '')).trim().toLowerCase();
    return /^qd-silver-\d{7}$/.test(raw) ? raw : null;
  }

  function utf8ToHex(str) {
    const bytes = new TextEncoder().encode(str);
    return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
  }

  function randomHex(bytesLen) {
    const arr = new Uint8Array(bytesLen);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      window.crypto.getRandomValues(arr);
    } else {
      for (let i = 0; i < arr.length; i++) arr[i] = Math.floor(Math.random() * 256);
    }
    return Array.from(arr).map((b) => b.toString(16).padStart(2, '0')).join('');
  }

  function updateBridgeLinks(token) {
    const cnft = token?.cnft_id || currentCnftId() || '';
    const buy = $('bridge-buy-page');
    buy.href = cnft ? `${MARKET_BASE}/buy.php?token=${encodeURIComponent(cnft)}` : MARKET_BASE;

    const policy = token?.chain?.policy_id || '';
    const assetHex = token?.chain?.asset_name_hex || '';
    const unit = policy && assetHex ? `${policy}${assetHex}` : '';
    const assetLink = $('bridge-asset-lookup');
    assetLink.href = unit
      ? `${MARKET_BASE}/admin/asset-lookup.php?mode=sync&q=${encodeURIComponent(unit)}`
      : `${MARKET_BASE}/admin/asset-lookup.php`;
  }

  async function lookupToken() {
    const verifyStatus = $('verify-status');
    const cnftId = currentCnftId();
    if (!cnftId) {
      setStatus(verifyStatus, 'Enter a valid CNFT ID (qd-silver-0000001).', 'err');
      return;
    }

    setStatus(verifyStatus, 'Looking up token…', 'loading');
    try {
      const data = await postAction({ action: 'token_lookup', cnftId });
      const token = data.token || {};
      updateBridgeLinks(token);

      const summary = {
        cnft_id: token.cnft_id || null,
        title: token.title || null,
        character_name: token.character_name || null,
        owner_display: token.owner_display || null,
        primary_sale: token.status?.primary_sale || null,
        listing_status: token.status?.listing || null,
        chain: {
          network: token.chain?.network || null,
          policy_id: token.chain?.policy_id || null,
          asset_name_hex: token.chain?.asset_name_hex || null,
          asset_fingerprint: token.chain?.asset_fingerprint || null,
          mint_tx_hash: token.chain?.mint_tx_hash || null,
          minted_at: token.chain?.minted_at || null,
        },
      };
      $('token-lookup-result').textContent = JSON.stringify(summary, null, 2);
      setStatus(verifyStatus, 'Token lookup complete.', 'ok');
    } catch (e) {
      $('token-lookup-result').textContent = 'Lookup failed: ' + (e?.message || String(e));
      setStatus(verifyStatus, 'Lookup failed.', 'err');
    }
  }

  async function verifyConnectedWalletOwnership() {
    const verifyStatus = $('verify-status');
    const cnftId = currentCnftId();
    if (!cnftId) {
      setStatus(verifyStatus, 'Enter a valid CNFT ID (qd-silver-0000001).', 'err');
      return;
    }
    if (!walletApi || !walletAddresses.length) {
      setStatus(verifyStatus, 'Connect a wallet before running ownership verification.', 'err');
      return;
    }

    try {
      const addresses = await refreshConnectedAddresses();
      const signedAddress = addresses[0];
      const nonce = `Rarefolio admin ownership check\ncnft=${cnftId}\nnonce=${randomHex(16)}\nts=${Date.now()}`;
      setStatus(verifyStatus, 'Requesting wallet signature…', 'loading');
      const signature = await walletApi.signData(signedAddress, utf8ToHex(nonce));
      setStatus(verifyStatus, 'Verifying ownership against market backend…', 'loading');

      const data = await postAction({
        action: 'ownership_verify',
        cnftId,
        signedAddress,
        nonce,
        signature,
      });
      const result = data.result || {};
      $('ownership-result').textContent = JSON.stringify({
        cnft_id: cnftId,
        signed_address: signedAddress,
        signature_valid: !!result.signature_valid,
        owns_token: !!result.owns_token,
        signed_reward_address: result.signed_reward_address || null,
        owner_reward_address: result.owner_reward_address || null,
      }, null, 2);

      if (result.owns_token) {
        setStatus(verifyStatus, 'Ownership verified: connected wallet currently owns this token.', 'ok');
      } else if (result.signature_valid) {
        setStatus(verifyStatus, 'Signature is valid, but this wallet is not the current token owner.', 'warn');
      } else {
        setStatus(verifyStatus, 'Signature validation failed.', 'err');
      }
    } catch (e) {
      $('ownership-result').textContent = 'Verification failed: ' + (e?.message || String(e));
      setStatus(verifyStatus, 'Ownership verification failed.', 'err');
    }
  }

  $('btn-connect').addEventListener('click', connectWallet);
  $('btn-refresh').addEventListener('click', async () => {
    const walletStatus = $('wallet-status');
    if (!walletApi || !walletAddresses.length) {
      setStatus(walletStatus, 'Connect a wallet first.', 'err');
      return;
    }
    try {
      await loadCollection();
      setStatus(walletStatus, `Connected (${humanWalletName(walletProvider)}).`, 'ok');
    } catch (e) {
      setStatus(walletStatus, 'Refresh failed: ' + (e?.message || String(e)), 'err');
    }
  });
  $('btn-switch-account').addEventListener('click', switchWalletAccount);
  $('btn-disconnect').addEventListener('click', disconnectWallet);
  $('btn-lookup-token').addEventListener('click', lookupToken);
  $('btn-verify-owner').addEventListener('click', verifyConnectedWalletOwnership);
  $('wallet-provider-select').addEventListener('change', () => {
    if (walletApi) return;
    const key = selectedWalletKey();
    if (!key) return;
    setStatus($('wallet-status'), `Selected ${humanWalletName(key)}. Click Connect Wallet.`, '');
  });
  $('ov-cnft').addEventListener('input', () => updateBridgeLinks(null));
  populateWalletProviderSelect();
  if (!selectedWalletKey()) {
    setStatus($('wallet-status'), 'No wallet extension detected. Install/unlock Eternl and refresh.', 'warn');
  }

  updateBridgeLinks(null);
})();
</script>
</body>
</html>
