<?php
// /download.php
// Serves certificate PDFs stored OUTSIDE web root.
//
// Your actual setup (BlueHost/cPanel):
// - This script lives in: /home/<user>/public_html/download.php
// - PDFs live one level above public_html, e.g. /home/<user>/qd_storage/pdfs
//
// This implementation auto-derives <user> from __DIR__ so you don't have to hardcode it.

$cert = isset($_GET["cert"]) ? preg_replace("/[^A-Z0-9\-]/", "", $_GET["cert"]) : "";
if ($cert === "") {
  http_response_code(400);
  header("Content-Type: text/plain; charset=utf-8");
  echo "Missing cert parameter";
  exit;
}

// Derive /home/<user> from /home/<user>/public_html
$homeDir = dirname(__DIR__); // __DIR__ is typically /home/<user>/public_html
$candidates = [
  $homeDir . "/qd_storage/pdfs",
  $homeDir . "/qd_storage/pdfs/",

  // Legacy / fallback (only used if your host actually has this layout)
  "/home/qd_storage/pdfs",
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
