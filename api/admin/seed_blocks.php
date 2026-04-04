<?php
declare(strict_types=1);

/**
 * One-time seed script: migrates the first 15 static blocks and their story
 * HTML files into qd_blocks + qd_stories for Bar I (E101837).
 *
 * Run via curl or browser (Basic Auth required).
 * Safe to re-run: uses INSERT ... ON DUPLICATE KEY UPDATE.
 */

require_once __DIR__ . '/../_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function require_basic_auth(): void {
  $u = $_SERVER['PHP_AUTH_USER'] ?? '';
  $p = $_SERVER['PHP_AUTH_PW']   ?? '';
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
    header('WWW-Authenticate: Basic realm="Rarefolio Admin"');
    respond(401, ['error' => 'Unauthorized']);
  }
}

require_basic_auth();

// ---- Static block definitions (mirrors QD_BLOCKS in qd-wire.js) ----
$BAR_SERIAL = 'E101837';

$blocks = [
  ['batch' => 1,  'folder' => 'scnft_zodiac_taurus',      'label' => 'Zodiac — Taurus',              'mode' => 'shared'],
  ['batch' => 2,  'folder' => 'scnft_sp_inventors',        'label' => 'Steampunk — Inventors',        'mode' => 'per_item'],
  ['batch' => 3,  'folder' => 'scnft_zodiac_aries',        'label' => 'Zodiac — Aries',               'mode' => 'per_item'],
  ['batch' => 4,  'folder' => 'scnft_sp_robot_butler',     'label' => 'Steampunk — Robot Butler',     'mode' => 'per_item'],
  ['batch' => 5,  'folder' => 'scnft_zodiac_gemini',       'label' => 'Zodiac — Gemini',              'mode' => 'shared'],
  ['batch' => 6,  'folder' => 'scnft_zodiac_cancer',       'label' => 'Zodiac — Cancer',              'mode' => 'shared'],
  ['batch' => 7,  'folder' => 'scnft_zodiac_leo',          'label' => 'Zodiac — Leo',                 'mode' => 'shared'],
  ['batch' => 8,  'folder' => 'scnft_zodiac_virgo',        'label' => 'Zodiac — Virgo',               'mode' => 'shared'],
  ['batch' => 9,  'folder' => 'scnft_zodiac_libra',        'label' => 'Zodiac — Libra',               'mode' => 'shared'],
  ['batch' => 10, 'folder' => 'scnft_zodiac_scorpio',      'label' => 'Zodiac — Scorpio',             'mode' => 'shared'],
  ['batch' => 11, 'folder' => 'scnft_zodiac_sagittarius',  'label' => 'Zodiac — Sagittarius',         'mode' => 'shared'],
  ['batch' => 12, 'folder' => 'scnft_zodiac_capricorn',    'label' => 'Zodiac — Capricorn',           'mode' => 'shared'],
  ['batch' => 13, 'folder' => 'scnft_zodiac_aquarius',     'label' => 'Zodiac — Aquarius',            'mode' => 'shared'],
  ['batch' => 14, 'folder' => 'scnft_zodiac_pisces',       'label' => 'Zodiac — Pisces',              'mode' => 'shared'],
  ['batch' => 15, 'folder' => 'scnft_new_series',          'label' => 'New Series',                   'mode' => 'shared'],
];

// ---- Story file directory (relative to site root) ----
$siteRoot  = realpath(__DIR__ . '/../../');
$storyBase = $siteRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'stories';

$pdo = qd_pdo();

$blockSql = 'INSERT INTO qd_blocks (block_id, bar_serial, batch_num, folder_slug, label, story_mode)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               folder_slug = VALUES(folder_slug),
               label       = VALUES(label),
               story_mode  = VALUES(story_mode),
               updated_at  = CURRENT_TIMESTAMP';

$storySql = 'INSERT INTO qd_stories (block_id, item_num, html_content)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               html_content = VALUES(html_content),
               updated_at   = CURRENT_TIMESTAMP';

$blockStmt = $pdo->prepare($blockSql);
$storyStmt = $pdo->prepare($storySql);

$results = [];

foreach ($blocks as $b) {
  $batchNum  = $b['batch'];
  $blockNum  = str_pad((string)$batchNum, 4, '0', STR_PAD_LEFT);
  $blockId   = $BAR_SERIAL . '-block' . $blockNum;

  // The static directory uses block00..block14 (zero-indexed)
  $staticDir = 'block' . str_pad((string)($batchNum - 1), 2, '0', STR_PAD_LEFT);

  // Insert/update block
  $blockStmt->execute([$blockId, $BAR_SERIAL, $batchNum, $b['folder'], $b['label'], $b['mode']]);

  $entry = [
    'block_id' => $blockId,
    'batch'    => $batchNum,
    'stories'  => [],
  ];

  // Load shared story
  $sharedPath = $storyBase . DIRECTORY_SEPARATOR . $staticDir . DIRECTORY_SEPARATOR . 'shared.html';
  if (file_exists($sharedPath)) {
    $html = file_get_contents($sharedPath);
    if ($html !== false && trim($html) !== '') {
      $storyStmt->execute([$blockId, null, $html]);
      $entry['stories'][] = 'shared';
    }
  }

  // Load per-item stories (1-8)
  for ($i = 1; $i <= 8; $i++) {
    $itemPath = $storyBase . DIRECTORY_SEPARATOR . $staticDir . DIRECTORY_SEPARATOR . $i . '.html';
    if (file_exists($itemPath)) {
      $html = file_get_contents($itemPath);
      if ($html !== false && trim($html) !== '') {
        $storyStmt->execute([$blockId, $i, $html]);
        $entry['stories'][] = $i;
      }
    }
  }

  $results[] = $entry;
}

respond(200, [
  'ok'      => true,
  'bar'     => $BAR_SERIAL,
  'seeded'  => count($results),
  'details' => $results,
]);
