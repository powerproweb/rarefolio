<?php
// /download.php
// Serves certificate PDFs stored OUTSIDE web root.
//
// Your actual setup (BlueHost/cPanel):
// - This script lives in: /home/<user>/public_html/download.php
// - PDFs live one level above public_html, e.g. /home/<user>/rf_storage/pdfs
//
// This implementation auto-derives <user> from __DIR__ so you don't have to hardcode it.

require_once __DIR__ . '/api/download/_common.php';

$cert = isset($_GET["cert"]) ? preg_replace("/[^A-Z0-9\-]/", "", $_GET["cert"]) : "";
if ($cert === "") {
  http_response_code(400);
  header("Content-Type: text/plain; charset=utf-8");
  echo "Missing cert parameter";
  exit;
}

$ticket        = trim((string)($_GET['ticket'] ?? ''));
$ticketRequired = rf_download_bool_env('RF_DOWNLOAD_TICKET_REQUIRED', false);

// Compatibility mode:
// - If RF_DOWNLOAD_TICKET_REQUIRED=true, every request must include a valid ticket.
// - If false, legacy direct cert downloads still work, but ticketed requests are validated.
if ($ticketRequired || $ticket !== '') {
  if ($ticket === '') {
    http_response_code(401);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Download ticket required";
    exit;
  }
  $claims = rf_download_verify_ticket($ticket);
  if (!is_array($claims)) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Invalid or expired download ticket";
    exit;
  }
  $ticketCert = strtoupper((string)($claims['cert_id'] ?? ''));
  if ($ticketCert === '' || $ticketCert !== strtoupper($cert)) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Ticket scope mismatch";
    exit;
  }
}

// Derive /home/<user> from /home/<user>/public_html
$homeDir = dirname(__DIR__); // __DIR__ is typically /home/<user>/public_html
$candidates = [
  $homeDir . "/rf_storage/pdfs",
  $homeDir . "/rf_storage/pdfs/",

  // Legacy / fallback (only used if your host actually has this layout)
  "/home/rf_storage/pdfs",
];

$file = null;
foreach ($candidates as $baseDir) {
  $path = rtrim($baseDir, "/") . "/" . $cert . ".pdf";
  if (is_file($path)) { $file = $path; break; }
}

if ($file === null) {
  http_response_code(404);
  header("Content-Type: text/plain; charset=utf-8");
  echo "PDF not found";
  exit;
}

if (!is_readable($file)) {
  http_response_code(403);
  header("Content-Type: text/plain; charset=utf-8");
  echo "PDF not readable";
  exit;
}

header("Content-Type: application/pdf");
header('Content-Disposition: attachment; filename="' . $cert . '.pdf"');
header("Content-Length: " . filesize($file));
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

readfile($file);
exit;
