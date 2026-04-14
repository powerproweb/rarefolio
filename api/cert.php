<?php
// /api/cert.php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

$cert = isset($_GET["cert"]) ? preg_replace("/[^A-Z0-9\-]/", "", $_GET["cert"]) : "";
if ($cert === "") {
  http_response_code(400);
  echo json_encode(["status"=>"error","error"=>"Missing cert parameter"]);
  exit;
}

// ---- Block metadata for cert resolution ----
$blocks = [
  // block00: Taurus (batch 1, CNFTs 0000001–0000008)
  'block00' => [
    'collection' => 'Zodiac — Taurus (Block 00)',
    'folder'     => 'scnft_zodiac_taurus',
    'batch'      => 1,
    'start'      => 1,
    'end'        => 8,
  ],
  // block01: Inventors Guild (batch 2, CNFTs 0000009–0000016)
  'block01' => [
    'collection' => 'Founders Prelaunch — Inventors Guild (Block 01)',
    'folder'     => 'scnft_sp_inventors',
    'batch'      => 2,
    'start'      => 9,
    'end'        => 16,
  ],
  // block02: Aries (batch 3, CNFTs 0000017–0000024)
  'block02' => [
    'collection' => 'Zodiac — Aries (Block 02)',
    'folder'     => 'scnft_zodiac_aries',
    'batch'      => 3,
    'start'      => 17,
    'end'        => 24,
  ],
];

// Build the full cert map from block definitions
$map = [];       // cert_id => cnft_slug
$blockMap = [];  // cert_id => block_key
foreach ($blocks as $bk => $bdata) {
  for ($n = $bdata['start']; $n <= $bdata['end']; $n++) {
    $num = str_pad((string)$n, 7, '0', STR_PAD_LEFT);
    $certKey = "QDCERT-E101837-{$num}";
    $map[$certKey] = "qd-silver-{$num}";
    $blockMap[$certKey] = $bk;
  }
}

if (!isset($map[$cert])) {
  http_response_code(404);
  echo json_encode(["status"=>"not_found","cert"=>$cert]);
  exit;
}

$nftId = $map[$cert];
$bk = $blockMap[$cert];
$bdata = $blocks[$bk];
$cnftNum = substr($cert, -7);

// Build response
$data = [
  "status" => "verified",
  "certId" => $cert,
  "cnft" => [
    "id" => $nftId,
    "barSerial" => "E101837",
    "collection" => $bdata['collection'],
    "image" => "/assets/img/collection/{$bdata['folder']}/{$nftId}.jpg",
    "edition" => "Shard " . (int)$cnftNum . " of 40,000",
    "silverAllocationTroyOz" => "0.00025",
  ],
  "holder" => [
    "displayName" => "",
    "privacyEnabled" => true,
    "walletDisplay" => "—",
  ],
  "custody" => [
    "vaultRecordId" => "QD-VLT-E101837-AG-{$cnftNum}",
    "vaultAddress" => "50 CR 356, Shiner, TX 77984",
    "statement" => "Custody recorded; verify via QR reference.",
  ],
  "chain" => [
    "network" => "Cardano",
    "txHash" => "—",
    "contractAddress" => "—",
    "tokenId" => $nftId,
  ],
  "pdf" => [
    "downloadUrl" => "/download.php?cert=" . rawurlencode($cert),
  ],
  "links" => [
    "verifyUrl" => "/verify?cert=" . rawurlencode($cert),
    "certUrl" => "/cert?cert=" . rawurlencode($cert),
    "nftUrl" => "/nft?nft=" . rawurlencode($nftId) . "&bar=E101837&set=1&batch={$bdata['batch']}&block={$bk}&img=/assets/img/collection/{$bdata['folder']}/{id}.jpg",
  ],
];

echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
