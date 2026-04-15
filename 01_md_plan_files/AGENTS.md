# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

Rarefolio.io is a static website for tokenized silver bar CNFTs (Cardano Native Fungible Tokens). Each physical silver bar is divided into CNFT "shards" organized into batches (e.g., Bar I serial `E101837` has 40,000 CNFTs in batches of 8 = 5,000 batches). Bars can be any size (100oz, 5oz, etc.) with independent batch counts. The site is hosted on shared hosting (BlueHost/cPanel) with Apache/.htaccess and a PHP/MySQL backend for certificates, block routing, and story management.

## Architecture

### Frontend (Static HTML + Vanilla JS)

All pages are flat `.html` files at the project root — no build step, no bundler, no framework. CSS is in `assets/css/` and JS in `assets/js/`.

**Key pages:**
- `index.html` — Homepage
- `collections.html` — Silver bar directory
- `collection-silverbar-01.html` — Silver Bar I with batch-navigated CNFT grid
- `collection-inventors-guild-prelaunch.html` — Prelaunch Block 01 (Inventors Guild)
- `nft.html` — Single NFT detail view (driven by URL params: `nft`, `bar`, `set`, `batch`, `block`, `item`, `col`)
- `cert.html` — Certificate of Authenticity viewer
- `verify.html` — Public certificate verification page
- `collection-silverbar-calculator.html` — Silver shard calculator
- `rarefolio_showcased_artist_application.html` — Showcased artist application form (multi-section, client-side validation, async submit to `/api/artist-application.php`)

**Key JS modules (all vanilla, IIFE-wrapped, no imports):**
- `assets/js/main.js` — Sitewide: mobile menu, dropdown nav, back-to-top, NFT image watermarking (CSS overlay via `data-watermark`), card tilt effect, story loader. Exposes `window.__QD.setupTilt()` and `window.__QD.loadStory()`.
- `assets/js/qd-wire.js` — Data-driven CNFT rendering engine. Contains a static `QD_BLOCKS` fallback map (block00–block14, Bar I only) plus an async API-driven resolution chain that scales to 5,000+ batches per bar. Resolution order: page override → static map → API (`/api/blocks/resolve.php`). Handles collection grid rendering (`#nftGrid`, windowed pill navigator `#batchNav`) and NFT detail page population. Loads block stories from static files (legacy) or `/api/blocks/story.php` (DB-driven). Session-caches API results in a `Map`.
- `assets/js/certificates.js` — Fetches certificate data from `api/cert/` endpoint and renders cert details + QR code.
- `assets/js/qr-lite.js` — Embedded minimal QR code generator (canvas-based). Exposes `window.qdQrLite.drawQrToCanvas()`.
- `assets/js/collections/collection-batches.js` — Legacy batch renderer (replaced by `qd-wire.js` for most pages).

### Backend (PHP API)

The backend is plain PHP (no framework, no Composer for the app itself). Dompdf is vendored in the `dompdf/` directory.

- `api/_config.php` — Database credentials, PDO factory (`qd_pdo()`), PDF storage path, admin auth constants. **Contains secrets — never commit changes to credentials.**
- `api/cert.php` — Static certificate lookup (hardcoded `$map` for Block 01 certs `QDCERT-E101837-0000009` through `0000016`). Returns JSON.
- `api/cert/index.php` — Database-driven certificate lookup. Reads from `qd_certificates` table, returns stored `payload_json`.
- `api/admin/issue_cert.php` — Admin endpoint (Basic Auth protected). Accepts JSON POST to issue a new certificate: validates input, generates a 2-page art-directed PDF via Dompdf (`render_pdf_html()`), writes PDF outside webroot to `PDF_STORAGE_DIR`, inserts a row into `qd_certificates`. Idempotent (returns existing cert if `cert_id` already exists). Supports `template` (`parchment`|`cream`) and optional `sealColor` (`gold`|`red`|`blue`, default `gold`) parameters.
- `api/blocks/resolve.php` — Public endpoint. `GET ?bar=E101837&batch=42` returns block metadata JSON (block_id, folder_slug, label, story_mode). Indexed query on `(bar_serial, batch_num)`. Cached 1hr.
- `api/blocks/story.php` — Public endpoint. `GET ?block=E101837-block0042&item=0` returns raw story HTML. `item=0` = shared, `1`–`8` = per-item. Auto-falls back to shared if per-item is missing. Returns 404 HTML if no story.
- `api/admin/manage_blocks.php` — Admin endpoint (Basic Auth). `POST` to create/update a block, `DELETE ?block_id=X` to remove.
- `api/admin/manage_stories.php` — Admin endpoint (Basic Auth). `POST` to create/update a story, `GET ?block=X&item=N` to read, `DELETE` to remove.
- `api/admin/seed_blocks.php` — One-time admin script to migrate the first 15 static blocks + stories into the DB for Bar I.
- `api/artist-application.php` — Public endpoint. `POST` with `multipart/form-data`. Validates required fields, generates unique reference code (`RF-{hex}-{date}`), saves file uploads to `uploads/artist_applications/{app_ref}/`, inserts into `qd_artist_applications`. Returns JSON `{ success, message, app_ref }`.
- `download.php` — Serves PDFs stored outside webroot (`/home/<user>/rf_storage/pdfs/`). Auto-derives paths from `__DIR__`.

### Database

MySQL (`rarefolio_cnftcert`), three tables:
- `qd_certificates` — Stores cert_id (format: `QDCERT-<BAR>-<7DIGIT>`), bar_serial, cnft_id, status (`verified`/`unverified`/`revoked`), payload as JSON, PDF metadata (sha256, bytes, storage key). Schema in `api/CERT_DB_SCHEMA.sql`.
- `qd_blocks` — One row per batch per bar. Maps `(bar_serial, batch_num)` to block_id, folder_slug, label, story_mode. Block ID format: `{barSerial}-block{NNNN}` (e.g., `E101837-block0042`). Unique on `(bar_serial, batch_num)`. Schema in `api/BLOCKS_DB_SCHEMA.sql`.
- `qd_stories` — Story HTML fragments. Each row has a `block_id` + optional `item_num` (NULL = shared, 1–8 = per-item). Unique on `(block_id, item_num)`. Schema in `api/BLOCKS_DB_SCHEMA.sql`.
- `qd_artist_applications` — Showcased artist application submissions. Columns span 6 sections (identity, practice, portfolio, readiness, uploads, consent). Status enum: `pending`/`reviewed`/`accepted`/`declined`. File uploads stored as relative paths under `uploads/artist_applications/{app_ref}/`. Unique on `app_ref`. Schema in `api/ARTIST_APP_DB_SCHEMA.sql`.

### Data & Configuration

- `assets/data/collections/qd-silverbar-01.json` — Silver Bar I config: serial `E101837`, slug prefix `qd-silver`, 7-digit IDs starting at 1, batch size 8, 5000 batches (40,000 CNFTs total).
- Collection image folders live under `assets/img/collection/` with stable slug names (e.g., `scnft_sp_inventors`, `scnft_zodiac_aries`, `scnft_zodiac_taurus`). Each contains artwork at multiple resolutions (JPG for web, 4000x/8000x CMYK PNGs + ZIPs for print), QR code PNGs, and a `manifest.json`.

### Block Routing System (qd-wire.js + DB API)

**Multi-bar, DB-driven block routing** — `bar_serial` is the partition key across the entire system.

**Resolution chain** (in `qd-wire.js`):
1. Page-level override (`data-block-id` attribute)
2. Batch rules (`data-block-batch-rules` attribute)
3. Static `QD_BLOCKS` map (Bar I batches 1–15 only, instant/offline)
4. API: `GET /api/blocks/resolve.php?bar={serial}&batch={num}` → DB lookup, session-cached

**Block ID format**: `{barSerial}-block{NNNN}` (e.g., `E101837-block0042`). Globally unique across all bars.

**Story resolution** (`storyUrlForBlock()` in qd-wire.js):
All stories (blocks 00–5,000+, all bars) are served exclusively via `/api/blocks/story.php`. The static `assets/stories/` folder has been deleted. `QD_BLOCKS` in `qd-wire.js` is a fast metadata cache (folder slug, label, story mode) — it no longer holds story file paths. Each static block's DB-format block ID is computed via `story_block_id` (e.g., `block00` → `E101837-block0001`) before calling the story API.
**Per-item
```
<article data-item="1">...story for item 1...</article>
<article data-item="2">...story for item 2...</article>
...up to data-item="8"
```
The `loadStory()` function in `main.js` reads `document.body.dataset.storyItem` (set by `qd-wire.js`). If the value is 1–8, it parses the fetched HTML via `DOMParser` and injects only the matching `article[data-item="N"]`. If 0 or absent, the full content is injected (shared story behavior).

**Scaling**: A 100oz bar has 5,000 blocks. A 5oz bar might have 250. Each bar's batch size and count are set in its JSON config file (`qd-silverbar-01.json`, etc.) — not hardcoded.

### Certificate Image Assets & Rotation

Certificate PDF backgrounds and wax seals live in `assets/img/certs/`:
- **Backgrounds** (selected by `template`): `bg-parchment_01.jpg`–`_04` (4 variants), `bg-cream_01.jpg`–`_02` (2 variants)
- **Wax Seals** (selected by `sealColor`): Gold `wax-seal-gold_01.png`–`_08` (8 variants), Red `wax-seal-red_09.png`–`_14` (6 variants), Blue `wax-seal-blue_15.png`–`_20` (6 variants)

Asset selection uses deterministic modular arithmetic on the 7-digit CNFT number (`cnft_num`): `($n - 1) % poolSize`. The same CNFT always gets the same seal + background combo, adjacent CNFTs get different visuals, and no extra DB columns or randomness are required.

The `resolve_cert_assets()` function in `api/admin/issue_cert.php` handles rotation. The `cert_image_url()` helper builds absolute URLs for Dompdf's remote image fetching.

### Certificate ID Format

All certificate and vault IDs follow deterministic patterns:
- Certificate: `QDCERT-{barSerial}-{cnftNum7}` (e.g., `QDCERT-E101837-0000009`)
- Vault Record: `QD-VLT-{barSerial}-AG-{cnftNum7}`
- CNFT slug: `qd-silver-{cnftNum7}`

## Hosting & Deployment

- Apache on shared hosting (BlueHost/cPanel)
- `.htaccess` at root handles HTTPS canonicalization, `.html` extension stripping, security headers, browser caching, gzip compression, and error pages. The old IP blocklist has been moved to `.htaccess.old1`.
- PDFs are stored outside webroot at `/home/<user>/rf_storage/pdfs/` and served via `download.php`
- No build step — deploy by uploading files directly
- Dompdf is vendored in `dompdf/` (loaded via `dompdf/autoload.inc.php`)

### CRITICAL: Editing .htaccess on Windows

**NEVER use PowerShell `Set-Content` or `[System.Text.Encoding]::UTF8` to write `.htaccess`.** Both add a UTF-8 BOM (`EF BB BF`) or write UTF-16 LE. Apache on Linux cannot parse a BOM-prefixed `.htaccess` — it silently breaks ALL requests site-wide with 500 errors, including the ErrorDocument handler itself.

**Always use `[System.Text.Encoding]::UTF8` is wrong. The correct pattern is:**
```powershell
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
$content = [System.IO.File]::ReadAllText($path, $utf8NoBom)
# ... make changes to $content ...
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
```
**Always verify after writing:**
```powershell
$bytes = [System.IO.File]::ReadAllBytes($path)
"First byte: $($bytes[0])"  # Must be 35 (#), never 239 (BOM)
```
If the first byte is 239 (0xEF), the file has a BOM and will break Apache. Rewrite immediately.

## Conventions

- All JS is vanilla ES6+, wrapped in IIFEs — no modules, no bundler, no npm
- Pages communicate configuration to JS via `data-*` attributes on `<body>` (e.g., `data-bar-serial`, `data-batch-size`, `data-block-id`, `data-image-template`)
- Image fallback chain: clean folder path → prefixed folder path (`01_scnft_...`) → placeholder image, handled via `onerror` on `<img>` tags
- Dark theme (navy base `#050a18`, gold `#d9b46c`, maroon `#7a1f2a`, lavender `#b9a7ff`) defined in CSS custom properties in `assets/css/styles.css`
- Nav/header/footer markup is duplicated across all HTML files (no includes or templating)
- **All `href` values in HTML files must use absolute paths (leading `/`).** Never use relative paths like `href="collections.html"` — use `href="/collections.html"`. Relative links break when pages are served via `.htaccess` rewrites at non-root URLs (e.g., `/collections/` trailing slash). The regex to bulk-fix: `href="(?!https?://|/|#|mailto:)([^"]+)"` → `href="/$1"` applied with `[System.IO.File]` and `UTF8Encoding($false)`.
- The `window.__QD` namespace is used for cross-script communication (tilt rebinding, story loading)

## Important Notes

- `api/_config.php` contains database credentials and admin auth secrets — handle with care
- The `dompdf/` directory is a third-party dependency (vendored via Composer) — do not modify files inside it
- Certificate PDFs are generated once and are immutable (the issuer endpoint refuses to overwrite existing PDFs)
- Site logo is `assets/img/rf_logo_site.png` (used sitewide and in certificate PDFs)
- The old `.htaccess` IP blocklist (~2900 lines) has been archived to `.htaccess.old1` — the current `.htaccess` is clean and modular
