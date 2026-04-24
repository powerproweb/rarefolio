<?php
declare(strict_types=1);

/**
 * collections/block.php — Single template for all collection block sub-pages.
 *
 * Replaces the 15+ static HTML files (collection-silverbar-01-taurus.html, etc.)
 * with one PHP template that resolves block metadata and renders the page.
 *
 * URL: /collection/silverbar-{bar}/{slug}?batch=N
 * Rewritten by .htaccess to: collections/block.php?bar={NN}&block={slug}
 */

// ---- Bar serial lookup ----
$BAR_SERIALS = [
  '01' => 'E101837',
  '02' => 'TBD-BAR2',
  '03' => 'TBD-BAR3',
];

// ---- Static block map (Bar I blocks 0–14, mirrors QD_BLOCKS in qd-wire.js) ----
$STATIC_BLOCKS = [
  'taurus'      => ['block_id' => 'block00', 'folder' => 'scnft_zodiac_taurus',      'label' => 'Zodiac — Taurus',              'story_mode' => 'shared',   'batch' => 1],
  'inventors'   => ['block_id' => 'block01', 'folder' => 'scnft_sp_inventors',       'label' => 'Steampunk — Inventors',        'story_mode' => 'per_item', 'batch' => 2],
  'aries'       => ['block_id' => 'block02', 'folder' => 'scnft_zodiac_aries',       'label' => 'Zodiac — Aries',               'story_mode' => 'shared',   'batch' => 3],
  'robot-butler'=> ['block_id' => 'block03', 'folder' => 'scnft_sp_robot_butler',    'label' => 'Steampunk — Robot Butler',     'story_mode' => 'per_item', 'batch' => 4],
  'gemini'      => ['block_id' => 'block04', 'folder' => 'scnft_zodiac_gemini',      'label' => 'Zodiac — Gemini',              'story_mode' => 'shared',   'batch' => 5],
  'cancer'      => ['block_id' => 'block05', 'folder' => 'scnft_zodiac_cancer',      'label' => 'Zodiac — Cancer',              'story_mode' => 'shared',   'batch' => 6],
  'leo'         => ['block_id' => 'block06', 'folder' => 'scnft_zodiac_leo',         'label' => 'Zodiac — Leo',                 'story_mode' => 'shared',   'batch' => 7],
  'virgo'       => ['block_id' => 'block07', 'folder' => 'scnft_zodiac_virgo',       'label' => 'Zodiac — Virgo',               'story_mode' => 'shared',   'batch' => 8],
  'libra'       => ['block_id' => 'block08', 'folder' => 'scnft_zodiac_libra',       'label' => 'Zodiac — Libra',               'story_mode' => 'shared',   'batch' => 9],
  'scorpio'     => ['block_id' => 'block09', 'folder' => 'scnft_zodiac_scorpio',     'label' => 'Zodiac — Scorpio',             'story_mode' => 'shared',   'batch' => 10],
  'sagittarius' => ['block_id' => 'block10', 'folder' => 'scnft_zodiac_sagittarius', 'label' => 'Zodiac — Sagittarius',         'story_mode' => 'shared',   'batch' => 11],
  'capricorn'   => ['block_id' => 'block11', 'folder' => 'scnft_zodiac_capricorn',   'label' => 'Zodiac — Capricorn',           'story_mode' => 'shared',   'batch' => 12],
  'aquarius'    => ['block_id' => 'block12', 'folder' => 'scnft_zodiac_aquarius',    'label' => 'Zodiac — Aquarius',            'story_mode' => 'shared',   'batch' => 13],
  'pisces'      => ['block_id' => 'block13', 'folder' => 'scnft_zodiac_pisces',      'label' => 'Zodiac — Pisces',              'story_mode' => 'shared',   'batch' => 14],
  'new-series'  => ['block_id' => 'block14', 'folder' => 'scnft_new_series',         'label' => 'New Series',                   'story_mode' => 'shared',   'batch' => 15],
  // Founders Block 88 — 8 unique tokens (qd-silver-0000705 through 0000712), batch 89 on Bar I
  // Founders Block 88: batch=89 on Bar I, tokens 705-712.
  // total_batches=89 keeps batch 89 valid (no URL clamping). start_index=1 so the
  // standard formula (1 + (89-1)*8 = 705) gives the correct token range.
  // block.php has no batch navigator elements so the 1-89 range is invisible to users.
  'founders'    => ['block_id' => 'block88', 'folder' => 'scnft_founders',           'label' => 'Founders Block 88',            'story_mode' => 'per_item', 'batch' => 89, 'start_index' => 1, 'total_batches' => 89],
];

// ---- Read params ----
$barNum   = preg_replace('/[^0-9]/', '', $_GET['bar'] ?? '01');
$slug     = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['block'] ?? ''));
$batchParam = isset($_GET['batch']) ? max(1, (int)$_GET['batch']) : 0;

$barSerial = $BAR_SERIALS[$barNum] ?? null;
if (!$barSerial || $slug === '') {
  http_response_code(404);
  include __DIR__ . '/../404.html';
  exit;
}

// ---- Resolve block metadata ----
$block      = null;
$blockFromDb = false; // true when resolved from qd_blocks (block_id is authoritative)

// 1) Try static map first (instant, no DB)
if (isset($STATIC_BLOCKS[$slug])) {
  $block = $STATIC_BLOCKS[$slug];
  if ($batchParam === 0) $batchParam = $block['batch'];
}

// 2) Try DB for dynamic blocks (slug-based lookup via label or folder)
if (!$block) {
  try {
    require_once __DIR__ . '/../api/_config.php';
    $pdo = qd_pdo();

    // For DB blocks, the slug might be derived from the label or folder.
    // Try matching by folder_slug pattern or by batch number.
    if ($batchParam > 0) {
      $stmt = $pdo->prepare(
        'SELECT block_id, folder_slug, label, story_mode, batch_num
           FROM qd_blocks WHERE bar_serial = ? AND batch_num = ? LIMIT 1'
      );
      $stmt->execute([$barSerial, $batchParam]);
    } else {
      // Try matching slug against folder_slug
      $stmt = $pdo->prepare(
        'SELECT block_id, folder_slug, label, story_mode, batch_num
           FROM qd_blocks WHERE bar_serial = ? AND folder_slug LIKE ? LIMIT 1'
      );
      $stmt->execute([$barSerial, '%' . $slug . '%']);
    }
    $row = $stmt->fetch();
    if ($row) {
      $block = [
        'block_id'   => $row['block_id'],
        'folder'     => $row['folder_slug'],
        'label'      => $row['label'],
        'story_mode' => $row['story_mode'],
        'batch'      => (int)$row['batch_num'],
      ];
      $blockFromDb = true;
      if ($batchParam === 0) $batchParam = $block['batch'];
    }
  } catch (Throwable $e) {
    // DB unavailable — fall through to 404
  }
}

if (!$block) {
  http_response_code(404);
  include __DIR__ . '/../404.html';
  exit;
}

// Redirect to canonical URL if no batch param was supplied.
// Without it, qd-wire.js defaults to batch=1 which shows wrong tokens.
if (!isset($_GET['batch'])) {
  $canonBatch = $batchParam ?: ($block['batch'] ?? 1);
  header('Location: /collection/silverbar-' . $barNum . '/' . $slugE . '?batch=' . $canonBatch, true, 302);
  exit;
}

// ---- Computed values ----
$blockId     = htmlspecialchars($block['block_id'], ENT_QUOTES, 'UTF-8');
$storyMode   = htmlspecialchars($block['story_mode'], ENT_QUOTES, 'UTF-8');
$label       = htmlspecialchars($block['label'], ENT_QUOTES, 'UTF-8');
$folder      = htmlspecialchars($block['folder'], ENT_QUOTES, 'UTF-8');
$barSerialE  = htmlspecialchars($barSerial, ENT_QUOTES, 'UTF-8');
$slugE       = htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');

$pageTitle   = "Silver Bar {$barNum} | {$label} | Tokenized Silver Bar CNFTs | Rarefolio.io";
$collTitle   = "Bar {$barNum} • {$label}";
$canonicalUrl= "https://rarefolio.io/collection/silverbar-{$barNum}/{$slugE}?batch={$batchParam}";

// Story block ID for the story API.
// DB-resolved blocks use their stored block_id directly (may be non-standard, e.g. 'block88').
// Static-map blocks reconstruct the DB-format ID from bar serial + batch number.
$storyBlockId = $blockFromDb
  ? $block['block_id']
  : ($barSerial . '-block' . str_pad((string)$block['batch'], 4, '0', STR_PAD_LEFT));
$storySrc    = '/api/blocks/story.php?block=' . urlencode($storyBlockId) . '&item=0';

$cssVersion  = '20260424';
?>
<!doctype html>
<html lang="en">
<head>

<!-- ========================= Rarefolio Modern Meta Block ========================= -->
<!-- Core -->
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
<meta http-equiv="x-ua-compatible" content="ie=edge" />

<title><?= $pageTitle ?></title>
<meta name="description" content="<?= $label ?> collection — tokenized silver bar CNFTs on Rarefolio.io, premium Cardano collectibles mapped to bar serial <?= $barSerialE ?>." />
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" />

<!-- Canonical -->
<link rel="canonical" href="<?= $canonicalUrl ?>" />

<!-- Branding / Theme -->
<meta name="theme-color" content="#070A12" />
<meta name="color-scheme" content="dark light" />

<!-- Icons -->
<link rel="icon" href="/assets/img/rf_logo_site.png" />
<link rel="apple-touch-icon" href="/assets/img/rf_logo_site.png" />

<!-- Open Graph -->
<meta property="og:site_name" content="Rarefolio.io" />
<meta property="og:type" content="website" />
<meta property="og:title" content="<?= $pageTitle ?>" />
<meta property="og:description" content="<?= $label ?> — tokenized silver bar CNFTs on Cardano, 40,000 CNFTs per bar, provenance-first collector experience." />
<meta property="og:url" content="<?= $canonicalUrl ?>" />
<meta property="og:image" content="https://rarefolio.io/assets/img/header/silver_bar_header_2400x1200_01.jpg" />
<meta property="og:image:width" content="2400" />
<meta property="og:image:height" content="1200" />
<meta property="og:locale" content="en_US" />

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="<?= $pageTitle ?>" />
<meta name="twitter:description" content="<?= $label ?> — tokenized silver bar CNFTs on Cardano with provenance-first presentation." />
<meta name="twitter:image" content="https://rarefolio.io/assets/img/header/silver_bar_header_2400x1200_01.jpg" />

<!-- Styles -->
<link rel="preload" as="style" href="/assets/css/styles.css?v=<?= $cssVersion ?>" />
<link rel="stylesheet" href="/assets/css/styles.css?v=<?= $cssVersion ?>" />

<!-- Structured Data (Organization) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Rarefolio.io",
  "url": "https://rarefolio.io/",
  "logo": "https://rarefolio.io/assets/img/rf_logo_site.png",
  "sameAs": [
    "https://rarefolio.io/"
  ]
}
</script>
</head>

<?php
// Per-block overrides for display range (used by Founders and similar limited-edition blocks)
$startIndex   = (int) ($block['start_index']   ?? 1);
$totalBatches = (int) ($block['total_batches'] ?? 5000);
?>
<body id="top"
  data-block-id="<?= $blockId ?>"
  data-story-mode="<?= $storyMode ?>"
  data-bar-serial="<?= $barSerialE ?>"
  data-bar-num="<?= $barNum ?>"
  data-set="1"
  data-total-batches="<?= $totalBatches ?>"
  data-demo-batches="<?= $totalBatches ?>"
  data-batch-size="8"
  data-start-index="<?= $startIndex ?>"
  data-collection-title="<?= $collTitle ?>"
  data-image-template="/assets/img/nfts/sys/placeholder.jpg"
  data-fallback-image="/assets/img/nfts/sys/placeholder.jpg"
  data-story-src="<?= $storySrc ?>"
  data-block-slug="<?= $slugE ?>">

<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="/index.html">
      <img src="/assets/img/rf_logo_site.png" alt="Rarefolio.io logo" />
      <div class="title mas_txt_clr">
        <strong>Rarefolio.io</strong>
        <span>CNFT Collections</span>
      </div>
    </a>

    <button class="menu-toggle" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="qd-primary-nav">Menu</button>

    <nav id="qd-primary-nav" class="nav mas_txt_clr" aria-label="Primary navigation">

      <div class="dropdown">
        <a class="dropbtn" href="/index.html" data-page="index.html" aria-haspopup="true" aria-expanded="false">
          Home <span class="caret" aria-hidden="true"></span>
        </a>
        <div class="dropmenu" role="menu" aria-label="Home menu">
          <a href="/index.html" role="menuitem">Overview</a>
          <a href="/index.html#featured" role="menuitem">Featured CNFTs</a>
        </div>
      </div>

      <div class="dropdown">
        <a class="dropbtn" href="/collections.html" data-page="collections.html" aria-haspopup="true" aria-expanded="false">
          Collections <span class="caret" aria-hidden="true"></span>
        </a>
        <div class="dropmenu" role="menu" aria-label="CNFT Collections menu">
          <a href="/collection/silverbar-01/founders?batch=89" role="menuitem">Founders Collection (Block 88)</a>
          <a href="/collection-inventors-guild-prelaunch.html" role="menuitem">Inventors Guild Prelaunch (Block 01)</a>
          <a href="/collection-silverbar-calculator.html" role="menuitem">Silver Shard Calculator</a>
          <a href="/collections.html" role="menuitem">All Tokenized Silver Bars</a>
          <a href="/collection-silverbar-01.html" role="menuitem">Silver Bar I (Live)</a>
          <a href="/collection-silverbar-02.html" role="menuitem">Silver Bar II (Coming Soon)</a>
          <a href="/collection-silverbar-03.html" role="menuitem">Silver Bar III (Coming Soon)</a>
        </div>
      </div>

      <div class="dropdown align-right">
        <a class="dropbtn" href="/rf_bus_philosophy.html" data-page="rf_bus_philosophy.html" aria-haspopup="true" aria-expanded="false">
          About <span class="caret" aria-hidden="true"></span>
        </a>
        <div class="dropmenu" role="menu" aria-label="About menu">
          <a href="/rf_bus_philosophy.html" role="menuitem">Rarefolio's Business Philosophy</a>
          <a href="/downloads.html" role="menuitem">Downloads</a>
          <a href="/terms.html" role="menuitem">Terms of Service</a>
          <a href="/privacy.html" role="menuitem">Privacy Policy</a>
        </div>
      </div>

      <div class="dropdown align-right">
        <a class="dropbtn" href="/contact.html" data-page="contact.html" aria-haspopup="true" aria-expanded="false">
          Contact <span class="caret" aria-hidden="true"></span>
        </a>
        <div class="dropmenu" role="menu" aria-label="Contact menu">
          <a href="/contact.html" role="menuitem">Contact Form</a>
          <a href="/contact.html#support" role="menuitem">Support</a>
        </div>
      </div>

      <div class="dropdown align-right">
        <a class="dropbtn" href="/contact.html" data-page="contact.html" aria-haspopup="true" aria-expanded="false">
          Social <span class="caret" aria-hidden="true"></span>
        </a>
        <div class="dropmenu" role="menu" aria-label="Social menu">
          <a href="https://qdls.io/qdcnft-x" target="window" title="Rarefolio.io's X Account" role="menuitem">X.com Rarefolio.io</a>
          <a href="https://qdls.io/qdcnft-d" target="window" title="Rarefolio.io's Discord Account" role="menuitem">Discord Rarefolio.io</a>
          <a href="https://qdls.io/qdcnft-fb" target="window" title="Rarefolio.io's Facebook Account" role="menuitem">Facebook Rarefolio.io</a>
        </div>
      </div>

    </nav>

    <!-- Founders Collection CTA Button (top-right) -->
    <a class="btn primary qd-prelaunch-cta"
       href="/collection/silverbar-01/founders?batch=89"
       title="Founders Collection (Block 88)">
      Founders
    </a>

  </div>
</header>

<?php if ($slugE === 'founders'): ?>
<div style="background:rgba(217,180,108,.10);border-bottom:1px solid rgba(217,180,108,.25);padding:10px 0;text-align:center;">
  <p style="margin:0;font-size:0.85rem;letter-spacing:0.06em;color:#d9b46c;">
    <strong>&#9733; PREVIEW MODE</strong> &mdash; Founders Block 88 is live for browsing.
    Mainnet minting opens soon. &nbsp;<a href="/contact.html" style="color:#d9b46c;text-decoration:underline;">Join the waitlist</a>
  </p>
</div>
<?php endif; ?>

<section class="section">
  <div class="container">

    <div class="section-title">
      <h2 id="qd-collection-heading"><?= $collTitle ?></h2>
      <p>Bar Serial: <strong><?= $barSerialE ?></strong> &#8226; Supply: 40,000 CNFTs &#8226; 5,000 batches &#215; 8</p>
    </div>

    <div class="panel pad">
      <p class="lead" style="margin:0;">
        Clarified structure: the Bar Serial # is constant across the first 40,000 CNFTs in this bar.
        For Bar <?= $barNum ?>, that Serial # is <strong><?= $barSerialE ?></strong>.
        Individual CNFTs use IDs like <strong>qd-silver-0000001</strong> and count upward.
      </p>
      <hr class="sep" />
      <p class="muted small" style="margin:0;">
        Supply (planned): 40,000 &#8226; Chain: Cardano &#8226; Theme: Silver Bar Series &#8226; Styling: Dark navy + gold + maroon + lavender glows
      </p>
    </div>

    <div class="section-title" style="margin-top:18px;">
      <h2>CNFTs</h2>
      <p>Eight displayed at a time &#8226; Badge shows Bar Serial &#8226; Placeholder images for performance</p>
    </div>

    <div id="nftGrid" class="grid cols-4"></div>

    <!-- Story (loaded from /assets/stories/*.html via main.js) -->
    <div id="qd-story" class="panel pad" style="margin-top:18px;"></div>

  </div>
</section>

<footer class="footer">
  <div class="container footer-inner">
    <div class="footer-left">
      &copy; 2026 Rarefolio.io. All rights reserved.
    </div>
    <div class="footer-right">
      <a href="/downloads.html">Downloads</a>
      <a href="/privacy.html">Privacy</a>
      <a href="/terms.html">Terms</a>
    </div>
  </div>
</footer>

<div class="backtotop">
  <a href="#top" aria-label="Back to top">Back to top</a>
</div>

<script src="/assets/js/main.js?v=<?= $cssVersion ?>"></script>
<script src="/assets/js/qd-wire.js?v=<?= $cssVersion ?>"></script>
</body>
</html>
