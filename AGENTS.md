# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

QuantumDrive.io is a static website for tokenized silver bar CNFTs (Cardano Native Fungible Tokens). Each physical silver bar (serial `E101837`) is divided into 40,000 CNFT "shards" organized into batches of 8, with provenance certificates and collector-grade artwork. The site is hosted on shared hosting (BlueHost/cPanel) with Apache/.htaccess and a PHP backend for certificate management.

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

**Key JS modules (all vanilla, IIFE-wrapped, no imports):**
- `assets/js/main.js` — Sitewide: mobile menu, dropdown nav, back-to-top, NFT image watermarking (CSS overlay via `data-watermark`), card tilt effect, story loader. Exposes `window.__QD.setupTilt()` and `window.__QD.loadStory()`.
- `assets/js/qd-wire.js` — Data-driven CNFT rendering engine. Contains the `QD_BLOCKS` routing map (block00–block14) that maps batch numbers to image folder slugs and story modes. Handles collection grid rendering (`#nftGrid`, `#batchSelect`) and NFT detail page population. This is the core rendering logic.
- `assets/js/certificates.js` — Fetches certificate data from `api/cert/` endpoint and renders cert details + QR code.
- `assets/js/qr-lite.js` — Embedded minimal QR code generator (canvas-based). Exposes `window.qdQrLite.drawQrToCanvas()`.
- `assets/js/collections/collection-batches.js` — Legacy batch renderer (replaced by `qd-wire.js` for most pages).

### Backend (PHP API)

The backend is plain PHP (no framework, no Composer for the app itself). Dompdf is vendored in the `dompdf/` directory.

- `api/_config.php` — Database credentials, PDO factory (`qd_pdo()`), PDF storage path, admin auth constants. **Contains secrets — never commit changes to credentials.**
- `api/cert.php` — Static certificate lookup (hardcoded `$map` for Block 01 certs `QDCERT-E101837-0000009` through `0000016`). Returns JSON.
- `api/cert/index.php` — Database-driven certificate lookup. Reads from `qd_certificates` table, returns stored `payload_json`.
- `api/admin/issue_cert.php` — Admin endpoint (Basic Auth protected). Accepts JSON POST to issue a new certificate: validates input, generates a 2-page PDF via Dompdf (`render_pdf_html()`), writes PDF outside webroot to `PDF_STORAGE_DIR`, inserts a row into `qd_certificates`. Idempotent (returns existing cert if `cert_id` already exists).
- `download.php` — Serves PDFs stored outside webroot (`/home/<user>/qd_storage/pdfs/`). Auto-derives paths from `__DIR__`.

### Database

MySQL (`quantumdrive_cnftcert`), single table:
- `qd_certificates` — Stores cert_id (format: `QDCERT-<BAR>-<7DIGIT>`), bar_serial, cnft_id, status (`verified`/`unverified`/`revoked`), payload as JSON, PDF metadata (sha256, bytes, storage key). Schema in `api/CERT_DB_SCHEMA.sql`.

### Data & Configuration

- `assets/data/collections/qd-silverbar-01.json` — Silver Bar I config: serial `E101837`, slug prefix `qd-silver`, 7-digit IDs starting at 1, batch size 8, 5000 batches (40,000 CNFTs total).
- Collection image folders live under `assets/img/collection/` with stable slug names (e.g., `scnft_sp_inventors`, `scnft_zodiac_aries`, `scnft_zodiac_taurus`). Each contains artwork at multiple resolutions (JPG for web, 4000x/8000x CMYK PNGs + ZIPs for print), QR code PNGs, and a `manifest.json`.

### Block Routing System (qd-wire.js)

The `QD_BLOCKS` map in `qd-wire.js` is the single source of truth for mapping batches to image folders and story content:
- Batch 1 → `block00` (scnft_zodiac_aries)
- Batch 2 → `block01` (scnft_sp_inventors)
- Batch 3 → `block02` (scnft_zodiac_taurus)
- Batches 4–15 → `block03`–`block14` (remaining zodiac/series)

Each block has a `story_mode`: `shared` (one story HTML per block) or `per_item` (up to 8 individual story HTMLs). Stories live in `assets/stories/`.

### Certificate ID Format

All certificate and vault IDs follow deterministic patterns:
- Certificate: `QDCERT-{barSerial}-{cnftNum7}` (e.g., `QDCERT-E101837-0000009`)
- Vault Record: `QD-VLT-{barSerial}-AG-{cnftNum7}`
- CNFT slug: `qd-silver-{cnftNum7}`

## Hosting & Deployment

- Apache on shared hosting (BlueHost/cPanel)
- `.htaccess` at root handles 404 routing and contains an extensive IP blocklist for contact form spam
- PDFs are stored outside webroot at `/home/<user>/qd_storage/pdfs/` and served via `download.php`
- No build step — deploy by uploading files directly
- Dompdf is vendored in `dompdf/` (loaded via `dompdf/autoload.inc.php`)

## Conventions

- All JS is vanilla ES6+, wrapped in IIFEs — no modules, no bundler, no npm
- Pages communicate configuration to JS via `data-*` attributes on `<body>` (e.g., `data-bar-serial`, `data-batch-size`, `data-block-id`, `data-image-template`)
- Image fallback chain: clean folder path → prefixed folder path (`01_scnft_...`) → placeholder image, handled via `onerror` on `<img>` tags
- Dark theme (navy base `#050a18`, gold `#d9b46c`, maroon `#7a1f2a`, lavender `#b9a7ff`) defined in CSS custom properties in `assets/css/styles.css`
- Nav/header/footer markup is duplicated across all HTML files (no includes or templating)
- The `window.__QD` namespace is used for cross-script communication (tilt rebinding, story loading)

## Important Notes

- `api/_config.php` contains database credentials and admin auth secrets — handle with care
- The `dompdf/` directory is a third-party dependency (vendored via Composer) — do not modify files inside it
- Certificate PDFs are generated once and are immutable (the issuer endpoint refuses to overwrite existing PDFs)
- The `.htaccess` IP blocklist is very large (~2900 lines) and is manually maintained — avoid reformatting it
