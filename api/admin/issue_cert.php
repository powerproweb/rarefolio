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


function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function must_string(array $in, string $key): string {
  $v = $in[$key] ?? '';
  $v = is_string($v) ? trim($v) : '';
  if ($v === '') respond(400, ['error' => "Missing required field: {$key}"]);
  return $v;
}

function cnft_num_from_id(string $cnftId): string {
  if (preg_match('/(\d{7})$/', $cnftId, $m)) return $m[1];
  respond(400, ['error' => 'CNFT ID must end with 7 digits (e.g., qd-silver-0000001).']);
  return '0000000';
}

function server_origin(): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'rarefolio.io';
  return $scheme . '://' . $host;
}

function ensure_storage_dir(): void {
  if (!is_dir(PDF_STORAGE_DIR)) {
    respond(500, ['error' => 'PDF_STORAGE_DIR does not exist. Create it outside webroot and update api/_config.php', 'dir' => PDF_STORAGE_DIR]);
  }
  if (!is_writable(PDF_STORAGE_DIR)) {
    respond(500, ['error' => 'PDF_STORAGE_DIR is not writable by PHP.', 'dir' => PDF_STORAGE_DIR]);
  }
}

function build_payload(array $in, string $certId, string $vaultRecordId): array {
  $privacy = (bool)($in['privacyEnabled'] ?? true);

  return [
    'certId' => $certId,
    'status' => 'verified',
    'template' => $in['template'] ?? 'parchment',

    'cnft' => [
      'id' => $in['cnftId'],
      'collection' => $in['collection'],
      'barSerial' => $in['barSerial'],
      'edition' => $in['edition'] ?? 'Shard 1 of 40,000',
      'silverAllocationTroyOz' => $in['silverAllocationTroyOz'] ?? '0.00025',
      'mintedOnChain' => $in['mintedOnChain'] ?? null
    ],

    'holder' => [
      'displayName' => $in['buyerName'] ?? '',
      'privacyEnabled' => $privacy,
      'wallet' => $in['wallet']
    ],

    'chain' => [
      'network' => $in['network'],
      'contractAddress' => $in['contractAddress'],
      'tokenId' => $in['tokenId'],
      'txHash' => $in['txHash'],
      'blockNumber' => $in['blockNumber'] ?? ''
    ],

    'custody' => [
      'vaultRecordId' => $vaultRecordId,
      'vaultAddress' => '50 CR 356, Shiner, TX 77984',
      'statement' => 'Custody recorded; verify via QR reference.'
    ],

    'terms' => [
      'footerMicroTerms' => 'This certificate is a provenance document for the referenced CNFT. It does not constitute financial advice, an investment contract, or a promise of value. Verification and applicable terms are provided via the QR reference.'
    ],

    'pdf' => [
      'downloadUrl' => '/download.php?cert=' . rawurlencode($certId)
    ],

    'verification' => [
      'verifyUrl' => server_origin() . '/verify.html?cert=' . rawurlencode($certId)
    ]
  ];
}

function payload_sha256(array $payload): string {
  $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  return hash('sha256', $json ?: '');
}

function render_pdf_html(array $payload): string {
  // Minimal premium PDF layout (2 pages). Replace with art-directed version later.
  $certId = htmlspecialchars($payload['certId'] ?? '');
  $cnftId = htmlspecialchars($payload['cnft']['id'] ?? '');
  $collection = htmlspecialchars($payload['cnft']['collection'] ?? '');
  $bar = htmlspecialchars($payload['cnft']['barSerial'] ?? '');
  $edition = htmlspecialchars((string)($payload['cnft']['edition'] ?? ''));
  $silver = htmlspecialchars((string)($payload['cnft']['silverAllocationTroyOz'] ?? '0.00025'));

  $vaultId = htmlspecialchars($payload['custody']['vaultRecordId'] ?? '');
  $vaultAddr = htmlspecialchars($payload['custody']['vaultAddress'] ?? '');

  $privacy = (bool)($payload['holder']['privacyEnabled'] ?? true);
  $holder = $privacy ? 'Private Holder' : htmlspecialchars((string)($payload['holder']['displayName'] ?? ''));
  $wallet = (string)($payload['holder']['wallet'] ?? '');
  $walletTail = $wallet !== '' ? '…' . substr($wallet, -8) : '—';
  $walletTail = htmlspecialchars($walletTail);

  $network = htmlspecialchars((string)($payload['chain']['network'] ?? ''));
  $contract = htmlspecialchars((string)($payload['chain']['contractAddress'] ?? ''));
  $tokenId = htmlspecialchars((string)($payload['chain']['tokenId'] ?? ''));
  $tx = htmlspecialchars((string)($payload['chain']['txHash'] ?? ''));
  $block = htmlspecialchars((string)($payload['chain']['blockNumber'] ?? '—'));

  $verifyUrl = htmlspecialchars((string)($payload['verification']['verifyUrl'] ?? ''));

// Build absolute URLs for PDF hyperlinks (PDF viewers are picky about relative links)
$origin = function_exists('server_origin') ? server_origin() : '';
$certAbs = $origin . '/cert.html?cert=' . rawurlencode($payload['certId'] ?? '');
$downloadRel = (string)($payload['pdf']['downloadUrl'] ?? '');
$downloadAbs = $downloadRel !== '' && str_starts_with($downloadRel, '/') ? ($origin . $downloadRel) : $downloadRel;

// Optional explorer links (Cardano default). If network unknown, fall back to plain text.
$txRaw = (string)($payload['chain']['txHash'] ?? '');
$txExplorer = '';
if ($txRaw !== '') {
  $net = strtolower((string)($payload['chain']['network'] ?? ''));
  if (strpos($net, 'cardano') !== false) {
    $txExplorer = 'https://cardanoscan.io/transaction/' . rawurlencode($txRaw);
  }
}
$txExplorerEsc = htmlspecialchars($txExplorer);
$certAbsEsc = htmlspecialchars($certAbs);
$downloadAbsEsc = htmlspecialchars($downloadAbs);

  $attest = 'This Certificate of Authenticity confirms that the CNFT identified on this document has been issued by Rarefolio.io and recorded on a public blockchain. The CNFT is associated with an allocation reference of 0.00025 troy oz of fine silver attributed to a serialized Rarefolio Silver Bar. Ownership and provenance may be independently verified using the certificate details and QR verification reference provided.';
  $attest = htmlspecialchars($attest);

  $micro = htmlspecialchars((string)($payload['terms']['footerMicroTerms'] ?? ''));

  return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: letter; margin: 0; }
    body { margin:0; font-family: DejaVu Sans, Arial, sans-serif; color:#111; }
    a { color:#0a4db3; text-decoration: underline; }
    .page { width: 8.5in; height: 11in; position: relative; }
    .frame { position:absolute; inset: 0.5in; }
    .brand { text-align:center; font-weight: 900; letter-spacing:.08em; margin-top: 6px; }
    .title { text-align:center; font-family: DejaVu Serif, "Times New Roman", serif; font-size: 24px; font-weight: 900; text-transform: uppercase; letter-spacing:.08em; margin: 10px 0 4px; }
    .sub { text-align:center; font-size: 11px; letter-spacing:.08em; text-transform: uppercase; color: rgba(0,0,0,.65); margin: 0 0 12px; }
    .badge { text-align:center; font-size: 11px; font-weight: 900; color:#0b6b3a; border:1px solid rgba(0,0,0,.15); display:inline-block; padding:6px 10px; border-radius: 999px; }
    .panel { border:1px solid rgba(0,0,0,.14); padding:10px 12px; border-radius: 10px; font-size: 12px; line-height: 1.4; background: rgba(255,255,255,.85); margin-top: 10px; }
    .h { font-size: 11px; letter-spacing:.08em; text-transform: uppercase; color: rgba(0,0,0,.62); font-weight: 900; margin-bottom: 6px; }
    table { width:100%; border-collapse: collapse; }
    td { padding: 6px 4px; vertical-align: top; font-size: 12.5px; }
    .k { color: rgba(0,0,0,.62); font-weight: 800; width: 170px; }
    .v { font-weight: 900; word-break: break-word; }
    .footer { position:absolute; left: 0.5in; right: 0.5in; bottom: 0.5in; font-size: 9.5px; color: rgba(0,0,0,.62); border-top:1px solid rgba(0,0,0,.14); padding-top: 8px; }
    .pb { page-break-after: always; }
  </style>
</head>
<body>
  <div class="page pb">
    <div class="frame">
      <div class="brand">Rarefolio.io</div>
      <div class="title">Certificate of Authenticity</div>
      <div class="sub">Rarefolio Silver Shard CNFT — Provenance &amp; Verification</div>
      <div style="text-align:center;">
        <span class="badge">VERIFIED</span>
      </div>

      <div class="panel">$attest</div>

      <div class="panel">
        <div class="h">Identification</div>
        <table>
          <tr><td class="k">Certificate ID</td><td class="v">$certId</td></tr>
          <tr><td class="k">CNFT ID</td><td class="v">$cnftId</td></tr>
          <tr><td class="k">Collection / Series</td><td class="v">$collection</td></tr>
          <tr><td class="k">Bar Serial #</td><td class="v">$bar</td></tr>
          <tr><td class="k">Edition</td><td class="v">$edition</td></tr>
          <tr><td class="k">Silver Allocation</td><td class="v">$silver troy oz</td></tr>
        </table>
      </div>

      <div class="panel">
        <div class="h">Holder &amp; Custody</div>
        <table>
          <tr><td class="k">Holder Name</td><td class="v">$holder</td></tr>
          <tr><td class="k">Wallet (last 8)</td><td class="v">$walletTail</td></tr>
          <tr><td class="k">Vault Record ID</td><td class="v">$vaultId</td></tr>
          <tr><td class="k">Vault Location</td><td class="v">$vaultAddr</td></tr>
          <tr><td class="k">Custody Note</td><td class="v">Custody recorded; verify via QR reference.</td></tr>
        </table>
      </div>

      <div class="footer">$micro</div>
    </div>
  </div>

  <div class="page">
    <div class="frame">
      <div class="brand">Rarefolio.io</div>
      <div class="title" style="font-size:20px;">Verification &amp; Chain Record</div>
      <div class="sub">Use the verification URL below to confirm provenance</div>

      <div class="panel">
        <div class="h">Verify URL</div>
        <div class="v"><a href="$verifyUrl" target="_blank" rel="noopener">$verifyUrl</a></div>
      </div>

      <div class="panel">
        <div class="h">Certificate View</div>
        <div class="v"><a href="$certAbsEsc" target="_blank" rel="noopener">$certAbsEsc</a></div>
      </div>

      <div class="panel">
        <div class="h">Original PDF Download</div>
        <div class="v"><a href="$downloadAbsEsc" target="_blank" rel="noopener">$downloadAbsEsc</a></div>
      </div>

      <div class="panel">
        <div class="h">On-chain Details</div>
        <table>
          <tr><td class="k">Network</td><td class="v">$network</td></tr>
          <tr><td class="k">Contract Address</td><td class="v">$contract</td></tr>
          <tr><td class="k">Token ID</td><td class="v">$tokenId</td></tr>
          <tr><td class="k">Transaction Hash</td><td class="v">$tx</td></tr>
          <tr><td class="k">Block Number</td><td class="v">$block</td></tr>
        </table>
      </div>

      <div class="panel">
        <div class="h">Custody &amp; Vault Reference</div>
        <table>
          <tr><td class="k">Vault Record ID</td><td class="v">$vaultId</td></tr>
          <tr><td class="k">Vault Location</td><td class="v">$vaultAddr</td></tr>
        </table>
      </div>

      <div class="footer">This page supports independent verification of issuance and provenance for the referenced CNFT.</div>
    </div>
  </div>
</body>
</html>
HTML;
}

function generate_pdf_bytes(string $html): string {
  if (!file_exists(DOMPDF_AUTOLOAD)) {
    respond(500, ['error' => 'Dompdf not installed. Run composer require dompdf/dompdf at site root.', 'expected' => DOMPDF_AUTOLOAD]);
  }
  require_once DOMPDF_AUTOLOAD;

  $options = new \Dompdf\Options();
  $options->set('isRemoteEnabled', true);
  $options->set('isHtml5ParserEnabled', true);

  $dompdf = new \Dompdf\Dompdf($options);
  $dompdf->setPaper('letter', 'portrait');
  $dompdf->loadHtml($html);
  $dompdf->render();
  return $dompdf->output();
}

// ---- main ----
function require_basic_auth(): void {
  // Standard PHP vars (work on many hosts)
  $u = $_SERVER['PHP_AUTH_USER'] ?? '';
  $p = $_SERVER['PHP_AUTH_PW'] ?? '';

  // Some hosts only provide the raw Authorization header
  if (($u === '' || $p === '') && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (stripos($auth, 'basic ') === 0) {
      $decoded = base64_decode(substr($auth, 6));
      if ($decoded !== false && strpos($decoded, ':') !== false) {
        [$u, $p] = explode(':', $decoded, 2);
      }
    }
  }

  if ($u !== ADMIN_USER || $p !== ADMIN_PASS) {
    header('WWW-Authenticate: Basic realm="Rarefolio Issuer"');
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
    exit;
  }
}

require_basic_auth();

$in = read_json_body();

$cnftId = must_string($in, 'cnftId');
$barSerial = must_string($in, 'barSerial');
$collection = must_string($in, 'collection');
$network = must_string($in, 'network');
$contractAddress = must_string($in, 'contractAddress');
$tokenId = must_string($in, 'tokenId');
$txHash = must_string($in, 'txHash');
$wallet = must_string($in, 'wallet');

$template = $in['template'] ?? 'parchment';
if (!in_array($template, ['parchment', 'cream'], true)) {
  respond(400, ['error' => 'Invalid template. Use parchment or cream.']);
}

$cnftNum = cnft_num_from_id($cnftId);
$certId = "QDCERT-{$barSerial}-{$cnftNum}";
$vaultRecordId = "QD-VLT-{$barSerial}-AG-{$cnftNum}";

ensure_storage_dir();
$pdfKey = $certId . '.pdf';
$pdfPath = rtrim(PDF_STORAGE_DIR, '/\\') . DIRECTORY_SEPARATOR . $pdfKey;

try {
  $pdo = qd_pdo();
  $pdo->beginTransaction();

  // idempotent: if exists, return existing URLs (do not overwrite)
  $check = $pdo->prepare('SELECT cert_id, pdf_storage_key FROM qd_certificates WHERE cert_id = ? LIMIT 1');
  $check->execute([$certId]);
  $existing = $check->fetch();
  if ($existing) {
    $pdo->commit();
    respond(200, [
      'ok' => true,
      'mode' => 'existing',
      'certId' => $certId,
      'vaultRecordId' => $vaultRecordId,
      'certUrl' => '/cert.html?cert=' . rawurlencode($certId),
      'verifyUrl' => '/verify.html?cert=' . rawurlencode($certId),
      'downloadUrl' => '/download.php?cert=' . rawurlencode($certId)
    ]);
  }

  $payloadInput = [
    'cnftId' => $cnftId,
    'barSerial' => $barSerial,
    'collection' => $collection,
    'edition' => $in['edition'] ?? 'Shard 1 of 40,000',
    'silverAllocationTroyOz' => $in['silverAllocationTroyOz'] ?? '0.00025',
    'mintedOnChain' => $in['mintedOnChain'] ?? null,

    'buyerName' => $in['buyerName'] ?? '',
    'privacyEnabled' => (bool)($in['privacyEnabled'] ?? true),
    'wallet' => $wallet,

    'network' => $network,
    'contractAddress' => $contractAddress,
    'tokenId' => $tokenId,
    'txHash' => $txHash,
    'blockNumber' => $in['blockNumber'] ?? '',

    'template' => $template
  ];

  $payload = build_payload($payloadInput, $certId, $vaultRecordId);
  $payloadSha = payload_sha256($payload);

  $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  $insert = $pdo->prepare('INSERT INTO qd_certificates (cert_id, bar_serial, cnft_id, cnft_num, status, template, payload_json, payload_sha256, generator_version) VALUES (?, ?, ?, ?, "verified", ?, ?, ?, "v1")');
  $insert->execute([$certId, $barSerial, $cnftId, $cnftNum, $template, $payloadJson, $payloadSha]);

  // Generate immutable PDF and write once
  if (file_exists($pdfPath)) {
    $pdo->rollBack();
    respond(409, ['error' => 'PDF already exists on disk; refusing to overwrite.', 'pdf' => $pdfKey]);
  }

  $html = render_pdf_html($payload);
  $pdfBytes = generate_pdf_bytes($html);

  $written = file_put_contents($pdfPath, $pdfBytes);
  if ($written === false) {
    $pdo->rollBack();
    respond(500, ['error' => 'Failed to write PDF to storage.', 'dir' => PDF_STORAGE_DIR]);
  }

  $pdfSha = hash('sha256', $pdfBytes);
  $pdfLen = strlen($pdfBytes);

  $upd = $pdo->prepare('UPDATE qd_certificates SET pdf_storage_key = ?, pdf_sha256 = ?, pdf_bytes = ? WHERE cert_id = ? LIMIT 1');
  $upd->execute([$pdfKey, $pdfSha, $pdfLen, $certId]);

  $pdo->commit();

  respond(201, [
    'ok' => true,
    'mode' => 'created',
    'certId' => $certId,
    'vaultRecordId' => $vaultRecordId,
    'certUrl' => '/cert.html?cert=' . rawurlencode($certId),
    'verifyUrl' => '/verify.html?cert=' . rawurlencode($certId),
    'downloadUrl' => '/download.php?cert=' . rawurlencode($certId),
    'pdfSha256' => $pdfSha
  ]);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode([
    'error' => 'Exception',
    'type' => get_class($e),
    'message' => $e->getMessage(),
    'file' => $e->getFile(),
    'line' => $e->getLine()
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

