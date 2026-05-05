# Rarefolio.io, Technical Architecture

**How every system works and scales.**

---

## Overview

Rarefolio.io is a static-first site with a PHP/MySQL backend that serves tokenized silver bar CNFT collections. The architecture is designed so that scaling from 15 blocks to 5,000+ per bar (across three bars, 15,000+ total) requires zero new files, only DB rows and image drops.

---

## Collection Sub-Pages, Single PHP Template

### Problem
Silver Bar I has 5,000 blocks. Each block needs its own page with unique title, OG tags, heading, and `data-*` attributes. Static HTML files would mean 5,000 files in the root directory per bar.

### Solution
One PHP template (`collections/block.php`) serves every block for every bar.

### How It Works

1. A visitor hits `/collection/silverbar-01/aquarius?batch=13`
2. `.htaccess` rewrites this to `collections/block.php?bar=01&block=aquarius&batch=13`
3. The PHP template:
   - Looks up `bar=01` in `$BAR_SERIALS` → `E101837`
   - Looks up `block=aquarius` in `$STATIC_BLOCKS` → `block12`, folder `scnft_zodiac_aquarius`, label "Zodiac, Aquarius", story_mode `shared`, batch 13
   - If the slug isn't in the static map, it queries the `qd_blocks` DB table
   - If neither resolves, it returns a 404
4. The resolved metadata is injected into the HTML: `data-block-id="block12"`, `data-story-mode="shared"`, `data-collection-title="Bar 01 • Zodiac, Aquarius"`, plus `<title>`, `<meta>` OG tags, and canonical URL
5. The rest of the page (nav, grid container `#nftGrid`, story container `#qd-story`, footer) is identical for every block
6. `qd-wire.js` takes over on the client side, renders the grid, loads stories, handles batch navigation

### Static Fallback Map (blocks 0–14)
The PHP template has a hardcoded `$STATIC_BLOCKS` array mirroring the JS `QD_BLOCKS` map. This means blocks 0–14 resolve instantly with no DB call. This is the pre-deployment fallback, works even before the DB schemas are run.

### Adding a New Block
Insert one row into `qd_blocks`:
```sql
INSERT INTO qd_blocks (block_id, bar_serial, batch_num, folder_slug, label, story_mode)
VALUES ('E101837-block0042', 'E101837', 42, 'scnft_custom_series', 'Custom Series', 'shared');
```
The PHP template and JS engine pick it up automatically. No files to create.

### .htaccess Rules
```apache
# Route clean URLs to PHP template
RewriteRule ^collection/silverbar-([0-9]{2})/([a-z0-9-]+)/?$ collections/block.php?bar=$1&block=$2 [QSA,L]

# 301 redirect old static file URLs to new clean URLs
RewriteRule ^collection-silverbar-([0-9]{2})-([a-z0-9-]+)\.html$ /collection/silverbar-$1/$2 [R=301,L]
RewriteRule ^collection-silverbar-([0-9]{2})-([a-z0-9-]+)$ /collection/silverbar-$1/$2 [R=301,L]
```

---

## Block Routing Engine (qd-wire.js)

### Purpose
Determine which collection block a batch number belongs to, and resolve that block's metadata (folder, label, story mode, story URL).

### Resolution Chain (4 tiers)

**Tier 1: Page-level override**
If the `<body>` has `data-block-id="block00"`, use that block directly from the `QD_BLOCKS` map. This is what the PHP template sets.

**Tier 2: Batch rules**
If `data-block-batch-rules` is set (a JSON array on `<body>`), match the batch number against the ranges. Legacy mechanism, still supported but no longer used by the PHP template.

**Tier 3: Static QD_BLOCKS map**
For Bar I batches 1–15, the JS has a hardcoded map:
- Batch 1 → `block00` (Taurus)
- Batch 2 → `block01` (Inventors)
- Batch 3 → `block02` (Aries)
- ...
- Batch 15 → `block14` (New Series)

This is instant, no network call, works offline.

**Tier 4: API fallback**
For batches 16–5,000+, the JS calls `GET /api/blocks/resolve.php?bar=E101837&batch=42`. The API does an indexed DB query on `(bar_serial, batch_num)` and returns JSON:
```json
{
  "block_id": "E101837-block0042",
  "folder_slug": "scnft_custom_series",
  "label": "Custom Series",
  "story_mode": "shared"
}
```
Results are session-cached in a JS `Map` keyed by `barSerial:batchNum`.

### Batch Navigation Without Page Reloads
When a user clicks a batch pill or navigates with Prev/Next:
1. `go(batchNum)` is called
2. `getBlockMeta()` resolves the block for that batch (using the 4-tier chain)
3. `updatePageMeta()` dynamically updates:
   - `document.title`
   - `<h2 id="qd-collection-heading">`
   - `document.body.dataset.*` attributes (blockId, storyMode, collectionTitle, storySrc)
   - Browser URL via `history.pushState` (e.g., `/collection/silverbar-01/gemini?batch=5`)
4. `renderBatch()` rebuilds the grid with the new block's images and metadata
5. Story loader fetches the new block's story

No page reload. The PHP template only runs on initial load or hard refresh.

### Block Slug Map
The JS has a `BLOCK_SLUGS` map that converts block IDs to URL slugs:
```js
block00 → 'taurus', block01 → 'inventors', block02 → 'aries', ...
```
For DB-driven blocks (16+), the slug is derived from `folder_slug`, e.g., `scnft_zodiac_gemini` → `gemini`.

---

## Image Resolution

### How Images Are Found
`blockImageCandidates(runtimeCfg, batchNum, slug, blockMeta)` builds the image path:

1. If block metadata has a `folder` property, build: `/assets/img/collection/{folder}/{slug}.jpg`
2. Also build a prefixed fallback: `/assets/img/collection/{NN}_{folder}/{slug}.jpg`
3. The `<img>` tag has an `onerror` handler that tries the prefixed path, then falls back to placeholder

### Example Trace
- User views batch 1, CNFT `qd-silver-0000001`
- `getBlockMeta(cfg, 1)` → tier 3 → `block00` → `{ folder: 'scnft_zodiac_taurus' }`
- `blockImageCandidates()` returns:
  - `src: /assets/img/collection/scnft_zodiac_taurus/qd-silver-0000001.jpg`
  - `altSrc: /assets/img/collection/01_scnft_zodiac_taurus/qd-silver-0000001.jpg`
- Browser loads `src`. If 404, `onerror` tries `altSrc`. If that 404s too, shows placeholder.

### Adding Artwork
Drop files into `assets/img/collection/{folder_slug}/` with the naming convention `qd-silver-NNNNNNN.jpg`. No code changes. The block metadata already knows the folder slug.

### Currently Wired
- `scnft_zodiac_taurus/`, 8 JPGs (qd-silver-0000001 through 0000008) + 8 RGB variants
- `scnft_sp_inventors/`, 8 JPGs (qd-silver-0000009 through 0000016) + 8 RGB variants
- `scnft_zodiac_aries/`, 8 JPGs (qd-silver-0000017 through 0000024) + 8 RGB variants
- 11 other block folders exist but are empty, they'll activate when artwork is dropped in

---

## Certificate System

### ID Formats
- Certificate: `QDCERT-{barSerial}-{cnftNum7}` → `QDCERT-E101837-0000009`
- Vault Record: `QD-VLT-{barSerial}-AG-{cnftNum7}` → `QD-VLT-E101837-AG-0000009`
- CNFT slug: `qd-silver-{cnftNum7}` → `qd-silver-0000009`

### Cert Lookup, Two Paths

**Path A: Static fallback (`api/cert.php`)**
A hardcoded `$blocks` array covers the first 24 CNFTs (blocks 00–02). The PHP loops over block definitions and builds a cert map. No DB needed. This is the pre-deployment fallback.

**Path B: DB-driven (`api/cert/index.php`)**
Queries `qd_certificates WHERE cert_id = ?`. Returns the stored `payload_json`. This is the production path once the DB is seeded.

### Cert Issuance (`api/admin/issue_cert.php`)
- Basic Auth protected
- Accepts JSON POST with: cnft_id, bar_serial, collection, template, sealColor, buyer info, chain data
- Generates a 2-page art-directed PDF via Dompdf
- Stores PDF outside webroot at `PDF_STORAGE_DIR` (`/home/rarefolio/rf_storage/pdfs/`)
- Inserts a row into `qd_certificates` with cert_id, status, payload JSON, PDF sha256/size
- **Idempotent**, returns existing cert if cert_id already exists (won't overwrite)

### Art-Directed PDF Templates
The `render_pdf_html()` function builds a 2-page HTML document for Dompdf:

**Page 1 (Certificate of Authenticity):**
- Full-bleed background image (selected by template)
- Centered logo, title, subtitle, VERIFIED badge
- Attestation panel, identification table, holder/custody table
- Wax seal (absolute positioned, bottom-right)
- Footer micro-terms

**Page 2 (Verification & Chain Record):**
- Same background
- Verification URL panel, cert view + PDF download links
- On-chain details table, custody/vault panel, footer

### Deterministic Asset Rotation
`resolve_cert_assets($template, $sealColor, $cnftNum)` selects background + seal:

```php
$bgFile  = $bgPool[($n - 1) % count($bgPool)];   // 4 parchment or 2 cream variants
$sealFile = $sealPool[($n - 1) % count($sealPool)]; // 8 gold, 6 red, or 6 blue variants
```

- Same CNFT always gets the same visual combo
- Adjacent CNFTs get different visuals
- No extra DB columns, no randomness

### Asset Inventory
```
assets/img/certs/
├── bg-parchment_01.jpg through _04.jpg  (4 backgrounds)
├── bg-cream_01.jpg through _02.jpg      (2 backgrounds)
├── wax-seal-gold_01.png through _08.png (8 gold seals)
├── wax-seal-red_09.png through _14.png  (6 red seals)
└── wax-seal-blue_15.png through _20.png (6 blue seals)
```

### NFT Detail → Cert Links
`qd-wire.js` `renderNftDetail()` constructs the cert ID from the CNFT slug:
```js
const cnftNum = nft.match(/(\d{7})$/);
const certId = `QDCERT-${bar}-${cnftNum[1]}`;
```
Then populates three link elements: View Certificate, Verify, Download PDF.

---

## Story System

### Two Modes Per Block
- **shared**, One story for all 8 items in the block. File: `assets/stories/blockNN/shared.html`
- **per_item**, Individual lore for each of the 8 items. File: `assets/stories/blockNN/items.html`

### items.html Format
```html
<article data-item="1">...story for item 1...</article>
<article data-item="2">...story for item 2...</article>
...up to data-item="8"
```

The `loadStory()` function in `main.js` reads `document.body.dataset.storyItem`. If 1–8, it parses the fetched HTML via `DOMParser` and extracts only `article[data-item="N"]`. If 0 or absent, the full content is injected (shared behavior).

### Story Resolution
`storyUrlForBlock(meta, itemNum)`:
- **API-sourced blocks** (DB-driven, `_source: 'api'`): → `/api/blocks/story.php?block=X&item=Y`
- **Static shared blocks**: → `/assets/stories/blockNN/shared.html`
- **Static per-item blocks**: → `/assets/stories/blockNN/items.html` (client extracts the article)

### Current Story Coverage
- Blocks 00–13: Real shared stories (5–14KB each)
- Block 14 (New Series): Intentional placeholder
- Block 01 (Inventors): Per-item stories for 8 articles
- Block 03 (Robot Butler): Per-item stories for 8 articles
- Blocks 16+: DB-driven via `manage_stories.php`

---

## Multi-Bar Architecture

Bar serial is the partition key everywhere:

| System | Key |
|--------|-----|
| `qd_blocks` table | `WHERE bar_serial = ?` |
| `qd_certificates` table | `WHERE bar_serial = ?` |
| `qd_stories` table | `WHERE block_id LIKE '{barSerial}-%'` |
| `collections/block.php` | `$BAR_SERIALS['01'] => 'E101837'` |
| `.htaccess` rewrite | `/collection/silverbar-{NN}/` → `bar=$1` |
| `qd-wire.js` config | `data-bar-serial` on `<body>` |
| JSON config | `assets/data/collections/qd-silverbar-01.json` |
| Image folders | Same structure, different artwork |
| Cert IDs | `QDCERT-{barSerial}-{cnftNum}` |

### Adding Bar II
1. Add `'02' => 'NEW_SERIAL'` to `$BAR_SERIALS` in `block.php`
2. Create `assets/data/collections/qd-silverbar-02.json` with the new serial, batch config, and slug prefix
3. Insert block rows into `qd_blocks` for Bar II
4. Drop artwork into new collection folders
5. Everything else, routing, certs, stories, image resolution, works automatically

---

## NFT Wiring Checklist

When new artwork is created for a CNFT or block:

### For Existing Blocks (0–14)
1. Drop card images: `assets/img/collection/{folder_slug}/qd-silver-NNNNNNN.jpg`
2. Issue certificates: `POST /api/admin/issue_cert.php` with CNFT metadata
3. That's it, routing, stories, and page rendering are already wired

### For New Blocks (16+)
1. Register the block: `POST /api/admin/manage_blocks.php`
   ```json
   { "bar_serial": "E101837", "batch_num": 42, "block_id": "E101837-block0042",
     "folder_slug": "scnft_custom_series", "label": "Custom Series", "story_mode": "shared" }
   ```
2. Create the image folder: `assets/img/collection/scnft_custom_series/`
3. Drop card images with `qd-silver-NNNNNNN.jpg` naming
4. Author the story: `POST /api/admin/manage_stories.php`
5. Issue certificates for each CNFT in the block

### Verification After Wiring
- Collection grid (`/collection/silverbar-01/{slug}?batch=N`) loads images correctly
- NFT detail (`/nft?nft=qd-silver-NNNNNNN&bar=E101837&set=1&batch=N`) renders image, story, cert links
- Certificate viewer (`/cert?cert=QDCERT-E101837-NNNNNNN`) shows verified status
- PDF download works via `/download.php`
- Story panel loads shared or per-item content

---

## Hosting & Deployment

- Apache on BlueHost shared hosting (cPanel)
- `.htaccess` handles: HTTPS canonicalization, `.html` extension stripping, collection block routing, 301 redirects, security headers, browser caching, gzip compression
- PDFs stored outside webroot: `/home/rarefolio/rf_storage/pdfs/`
- No build step, deploy by FTP/cPanel file upload
- Dompdf vendored in `dompdf/` (loaded via `dompdf/autoload.inc.php`)

### Deploy Checklist
1. Run 3 SQL schemas in phpMyAdmin: `CERT_DB_SCHEMA.sql`, `BLOCKS_DB_SCHEMA.sql`, `ARTIST_APP_DB_SCHEMA.sql`
2. Hit `/api/admin/seed_blocks.php` (Basic Auth) to populate blocks 00–14
3. Upload all files to BlueHost webroot
4. Verify `.htaccess` is the current version
5. Verify `uploads/artist_applications/` is writable
6. Verify `dompdf/` directory is intact
7. Smoke test: resolve API, story API, cert API
8. Test cert issuance + PDF download end-to-end

---

## Conventions

- All JS is vanilla ES6+, wrapped in IIFEs, no modules, no bundler, no npm
- Pages communicate config to JS via `data-*` attributes on `<body>`
- Image fallback chain: clean folder → prefixed folder → placeholder (`onerror`)
- Dark theme colors: navy `#050a18`, gold `#d9b46c`, maroon `#7a1f2a`, lavender `#b9a7ff`
- Nav/header/footer markup is duplicated across static HTML files (the PHP template has its own copy)
- `window.__QD` namespace for cross-script communication (tilt rebinding, story loading)
- Certificate PDFs are immutable, issuer endpoint refuses to overwrite
- `api/_config.php` contains secrets, never commit credential changes
