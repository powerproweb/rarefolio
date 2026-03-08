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

// Block 01 mapping (Inventors Guild prelaunch: 0000009–0000016)
$map = [
  "QDCERT-E101837-0000009" => "qd-silver-0000009",
  "QDCERT-E101837-0000010" => "qd-silver-0000010",
  "QDCERT-E101837-0000011" => "qd-silver-0000011",
  "QDCERT-E101837-0000012" => "qd-silver-0000012",
  "QDCERT-E101837-0000013" => "qd-silver-0000013",
  "QDCERT-E101837-0000014" => "qd-silver-0000014",
  "QDCERT-E101837-0000015" => "qd-silver-0000015",
  "QDCERT-E101837-0000016" => "qd-silver-0000016",
];

if (!isset($map[$cert])) {
  http_response_code(404);
  echo json_encode(["status"=>"not_found","cert"=>$cert]);
  exit;
}

$nftId = $map[$cert];

// NOTE: PDFs live OUTSIDE webroot; serve them via /download.php
$data = [
  "status" => "verified",
  "certId" => $cert,
  "cnft" => [
    "id" => $nftId,
    "barSerial" => "E101837",
    "collection" => "Founders Prelaunch — Inventors Guild (Block 01)",
    "image" => "/assets/img/collection/scnft_sp_inventors/{$nftId}.jpg",
  ],
  "custody" => [
    "vaultRecordId" => "—",
    "vaultAddress" => "—",
  ],
  "chain" => [
    "network" => "Cardano",
    "txHash" => "—",
  ],
  "pdf" => [
    "downloadUrl" => "/download.php?cert=" . rawurlencode($cert),
  ],
  "links" => [
    "verifyUrl" => "/verify.html?cert=" . rawurlencode($cert),
    "certUrl" => "/cert.html?cert=" . rawurlencode($cert),
    "nftUrl" => "/nft.html?nft=" . rawurlencode($nftId) . "&bar=E101837&set=1&batch=2&col=collection-inventors-guild-prelaunch.html&img=assets/img/collection/scnft_sp_inventors/{id}.jpg",
    "downloadsShared" => "/assets/downloads/block01/QD_InventorsGuild_Block01_Shared_Pack.zip",
    "downloadsIndividual" => "/assets/downloads/block01/QD_InventorsGuild_Block01_" . rawurlencode($cert) . ".zip",
  ],
];

echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
