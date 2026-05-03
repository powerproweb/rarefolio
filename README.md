# Rarefolio.io

**Tokenized Silver Bar CNFT Collections on Cardano**

Rarefolio.io is a provenance-first collector platform for tokenized silver bar CNFTs (Cardano Native Fungible Tokens). Each physical .999 pure silver bar is divided into thousands of individually identifiable CNFT "shards," organized into batches with verifiable Certificates of Authenticity.

> **Live at:** [https://rarefolio.io](https://rarefolio.io)

---

## What Is This?

Three 100-ounce silver bars, each with 40,000 CNFTs. Every CNFT maps to a real bar serial number, carries its own art-directed PDF certificate, and belongs to a themed collection block (zodiac signs, special editions, and more).

- **Silver Bar I** — Serial `E101837`, 40,000 CNFTs across 5,000 batches of 8
- **Silver Bar II** — Coming Soon
- **Silver Bar III** — Coming Soon

### Collection Blocks (Silver Bar I)

| Block | Collection | Story Mode | Batch |
|-------|-----------|-----------|-------|
| 00 | Zodiac — Taurus | shared | 1 |
| 01 | Steampunk — Inventors Guild | per_item (8 lore articles) | 2 |
| 02 | Zodiac — Aries | shared | 3 |
| 03 | Steampunk — Robot Butler | per_item (8 lore articles) | 4 |
| 04–13 | Gemini through Pisces (Zodiac Series) | shared | 5–14 |
| 14 | New Series | shared (placeholder) | 15 |
| 15–4999 | Future blocks (DB-driven) | configurable | 16–5000 |

Each block has its own collection artwork at multiple resolutions (web JPGs, print-ready 4000x/8000x CMYK PNGs), QR codes, and story lore.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Static HTML, vanilla CSS, vanilla ES6+ JavaScript — no framework, no bundler |
| Collection Pages | Single PHP template (`collections/block.php`) + `.htaccess` rewrite |
| Backend API | Plain PHP (no framework), MySQL |
| PDF Generation | Dompdf 3.1.4 (vendored) |
| Hosting | Apache on shared hosting (BlueHost/cPanel) |
| Blockchain | Cardano (CNFT standard) |

**No build step.** Deploy by uploading files directly.

---

## Scaling Architecture

The system is designed to scale from the current 15 blocks to 5,000+ per bar across three bars (15,000+ total) without creating additional files.

### Collection Sub-Pages — Single PHP Template

Instead of one HTML file per block (which would mean 5,000 files in the root), a single `collections/block.php` template serves every block for every bar:

```
URL:     /collection/silverbar-01/aquarius?batch=13
Rewrite: collections/block.php?bar=01&block=aquarius&batch=13
```

The PHP template:
1. Maps bar number → bar serial (`01` → `E101837`)
2. Resolves block metadata from a hardcoded fallback (blocks 0–14) or the `qd_blocks` DB table (16+)
3. Renders the page with correct `data-*` attributes, `<title>`, OG meta tags, and heading
4. The JS engine (`qd-wire.js`) takes over for grid rendering, batch navigation, and story loading

**Adding a new block = inserting one DB row.** No files to create.

### Block Routing — Multi-Tier Resolution

`qd-wire.js` resolves which block a batch belongs to through a 4-tier chain:

1. **Page-level override** — `data-block-id` attribute on `<body>`
2. **Batch rules** — `data-block-batch-rules` JSON attribute
3. **Static `QD_BLOCKS` map** — Bar I batches 1–15, instant/offline, no network call
4. **API fallback** — `GET /api/blocks/resolve.php?bar={serial}&batch={num}` — indexed DB query, session-cached in a JS `Map`

When navigating between batches, the JS updates the page in-place via `history.pushState` — no page reload. Title, heading, URL, story, and images all swap dynamically.

### Image Resolution

`blockImageCandidates()` builds image paths from block metadata:

```
/assets/img/collection/{folder_slug}/{cnft_slug}.jpg
```

Example: batch 1 → `block00` → `scnft_zodiac_taurus` → `/assets/img/collection/scnft_zodiac_taurus/qd-silver-0000001.jpg`

Fallback chain: clean folder path → prefixed folder (`01_scnft_...`) → placeholder image, handled via `onerror` on `<img>` tags.

**Adding artwork for a new block** = dropping JPGs into a folder with the correct `qd-silver-NNNNNNN.jpg` naming.

### Certificate Pipeline — Deterministic & Immutable

Each CNFT gets a 2-page art-directed PDF certificate:

- **Two template styles:** Parchment (warm brown/gold) and Cream (navy/silver)
- **6 background variants:** 4 parchment + 2 cream (2550×3300 JPG)
- **20 wax seal variants:** 8 gold + 6 red + 6 blue (600×600 PNG with transparency)
- **Deterministic rotation:** `(cnft_num - 1) % poolSize` — same CNFT always gets the same visual combo, adjacent CNFTs get different visuals, no DB columns or randomness needed

Certificate IDs: `QDCERT-{barSerial}-{cnftNum7}` (e.g., `QDCERT-E101837-0000009`)
Vault Record IDs: `QD-VLT-{barSerial}-AG-{cnftNum7}`

PDFs are generated once via Dompdf and stored immutably outside the webroot. The issuer endpoint refuses to overwrite existing PDFs.

**Pre-DB fallback:** `api/cert.php` has a static map covering the first 24 CNFTs (blocks 00–02). Once the DB is live, `api/cert/index.php` handles all certs via `qd_certificates` table with no hardcoded limits.

### Multi-Bar

Bar serial is the partition key across the entire system:
- `qd_blocks` — `WHERE bar_serial = ?`
- `qd_certificates` — `WHERE bar_serial = ?`
- `collections/block.php` — `$BAR_SERIALS['01'] => 'E101837'`
- `.htaccess` — `/collection/silverbar-{NN}/` routes to the same PHP template

Adding Bar II = one entry in `$BAR_SERIALS`, one JSON config file, and DB rows. Same infrastructure, same template, same JS engine.

---

## NFT Wiring

When new NFT artwork is created, each CNFT wires into the site through these touchpoints:

| Step | What | Where |
|------|------|-------|
| 1 | Drop card image | `assets/img/collection/{folder_slug}/qd-silver-NNNNNNN.jpg` |
| 2 | Issue certificate | `POST /api/admin/issue_cert.php` with cnft_id, bar_serial, template, sealColor |
| 3 | Register block (16+) | `POST /api/admin/manage_blocks.php` with bar_serial, batch_num, block_id, folder_slug, label, story_mode |
| 4 | Author story | `POST /api/admin/manage_stories.php` or static `assets/stories/blockNN/shared.html` |

**Currently wired (24 CNFTs):**
- Block 00 — Taurus (`qd-silver-0000001` through `0000008`) — artwork + cert data + shared story
- Block 01 — Inventors (`qd-silver-0000009` through `0000016`) — artwork + cert data + per-item stories
- Block 02 — Aries (`qd-silver-0000017` through `0000024`) — artwork + cert data + shared story

The NFT detail page (`nft.html`) automatically shows **View Certificate**, **Verify**, and **Download PDF** buttons for any CNFT, linking to `cert.html?cert=QDCERT-E101837-NNNNNNN`.

---

## Site Map

### Public Pages
- **Homepage** (`index.html`) — Hero, featured CNFTs, collection overview
- **Collections Hub** (`collections.html`) — Directory of all silver bars
- **Silver Bar I** (`collection-silverbar-01.html`) — Batch-navigated CNFT grid with pill navigator
- **Block Pages** (`/collection/silverbar-01/{slug}`) — PHP-driven, one template for all 5,000 blocks
- **NFT Detail** (`nft.html`) — Individual CNFT with image, badge, story, and cert links
- **Certificate Viewer** (`cert.html`) — Certificate of Authenticity display
- **Verification** (`verify.html`) — Public cert verification with QR code
- **Calculator** (`collection-silverbar-calculator.html`) — Silver shard calculator
- **Prelaunch** (`collection-inventors-guild-prelaunch.html`) — Founders prelaunch for Block 01
- **Artist Application** (`rarefolio_showcased_artist_application.html`) — Showcased artist submission form
- **Philosophy, Bio, Manifesto, Downloads, Contact, Terms, Privacy, 404**

### Admin Pages (Basic Auth)
- **Admin Hub** (`admin/index.php`) — Launch point for admin-only tools
- **Wallet Dashboard** (`admin/wallet-dashboard.php`) — CIP-30 wallet operations panel for collection visibility and ownership tooling
- **Story Editor** (`admin/story-editor.php`) — Story content management

#### Wallet Dashboard Functionality
- **Wallet provider selector** with preferred ordering (`eternl`, `lace`, `nami`, `typhon`, `flint`, `yoroi`) and retry-based detection on initial load/focus
- **Connect Wallet** uses the selected provider and resolves used/change/reward addresses for ownership checks
- **Refresh Holdings** re-queries ownership via the market collection bridge and updates token/order cards
- **Disconnect** clears in-page wallet session state
- **Switch Wallet / Account** provides guided account-switch flow without blind session clearing for same-provider account changes
  1. Select provider (if needed)
  2. Click **Switch Wallet / Account**
  3. Change account/wallet in the extension UI (Eternl/Lace/etc.)
  4. Click **Connect Wallet** to bind the new account

> Browser security does not allow the page to force-open a wallet extension’s account picker. Account changes must be completed in the wallet extension UI, then reconnected from the dashboard.

### Backend API
- `api/cert.php` — Static cert lookup (blocks 00–02, 24 CNFTs)
- `api/cert/index.php` — DB-driven cert lookup (scales to all CNFTs)
- `api/blocks/resolve.php` — Block metadata resolution by bar serial + batch number
- `api/blocks/story.php` — Story HTML retrieval (shared or per-item)
- `api/artist-application.php` — Artist application submissions with file uploads
- `api/admin/issue_cert.php` — Cert issuance with art-directed PDF generation (Basic Auth)
- `api/admin/seed_blocks.php` — One-time block seeding script
- `api/admin/manage_blocks.php` — Block CRUD
- `api/admin/manage_stories.php` — Story CRUD

### Marketplace Integration (signed webhooks)
- `api/webhook/mint-complete.php` — Receives `mint.complete` events from the marketplace
- `api/webhook/ownership-change.php` — Receives `ownership.change` events
- `api/webhook/_hmac.php` — Shared HMAC verifier + replay protection (not web-reachable)
- `assets/js/rf-market.js` — Browser client that `verify.html` and `nft.html` use to fetch live marketplace data

Full setup walkthrough lives in the marketplace repo:
`../01a_rarefolio_marketplace/docs/CONFIG.md`

The main site needs exactly one env var: `RF_WEBHOOK_SECRET`. See
`api/webhook/README.md` for hosting-specific instructions (cPanel, Apache,
nginx+fpm, or local dev).

---

## Project Structure

```
rarefolio.io/
├── index.html                    # Homepage
├── collections.html              # Silver bar directory
├── collection-silverbar-01.html  # Silver Bar I grid (main bar page)
├── collection-silverbar-02.html  # Silver Bar II (Coming Soon)
├── collection-silverbar-03.html  # Silver Bar III (Coming Soon)
├── collections/
│   └── block.php                 # Single PHP template for ALL block sub-pages
├── nft.html                      # NFT detail page (cert links wired)
├── cert.html                     # Certificate viewer
├── verify.html                   # Public cert verification
├── .htaccess                     # URL rewrites, 301 redirects, security headers
├── admin/
│   ├── index.php                 # Admin hub (Basic Auth)
│   ├── wallet-dashboard.php      # CIP-30 wallet operations dashboard
│   └── story-editor.php          # Story content management
├── api/
│   ├── _config.php               # DB credentials & auth (secrets)
│   ├── cert.php                  # Static cert lookup (24 CNFTs)
│   ├── cert/index.php            # DB cert lookup (all CNFTs)
│   ├── artist-application.php    # Artist submissions
│   ├── blocks/
│   │   ├── resolve.php           # Block resolution API
│   │   └── story.php             # Story content API
│   ├── admin/
│   │   ├── issue_cert.php        # Cert issuance + PDF generation
│   │   ├── seed_blocks.php       # One-time block seeder
│   │   ├── manage_blocks.php     # Block CRUD
│   │   └── manage_stories.php    # Story CRUD
│   ├── CERT_DB_SCHEMA.sql        # qd_certificates table
│   ├── BLOCKS_DB_SCHEMA.sql      # qd_blocks + qd_stories tables
│   └── ARTIST_APP_DB_SCHEMA.sql  # qd_artist_applications table
├── assets/
│   ├── css/                      # Stylesheets (dark theme, custom properties)
│   ├── js/
│   │   ├── main.js               # Sitewide: menu, tilt, watermark, story loader
│   │   ├── qd-wire.js            # Block routing engine + collection grid + NFT detail
│   │   ├── certificates.js       # Cert viewer logic
│   │   └── qr-lite.js            # Embedded QR code generator
│   ├── data/collections/         # Bar JSON configs (qd-silverbar-01.json)
│   ├── img/
│   │   ├── certs/                # 6 PDF backgrounds + 20 wax seals
│   │   ├── collection/           # Block artwork (14 folders, slug-named)
│   │   ├── nfts/                 # System placeholder images
│   │   └── header/               # Hero/header images
│   └── stories/                  # Static story HTML (block00–block14)
├── dompdf/                       # Vendored Dompdf 3.1.4
├── uploads/                      # Artist application file uploads
└── 01_md_plan_files/             # Internal build docs & plans
    ├── AGENTS.md                 # AI agent guidance
    ├── BUILD_rarefolio_master.md # Master build log
    ├── CHANGELOG.md              # Change history
    ├── README_rarefolio.md       # Internal project readme
    ├── ongoing_plan.md           # Ongoing plan & NFT wiring checklist
    └── Art-Directed PDF Certificate Templates.md
```

---

## Database

MySQL database `rarefolio_cnftcert` with four tables:

- **`qd_certificates`** — Certificate records: cert_id, status (`verified`/`unverified`/`revoked`), payload JSON, PDF metadata (sha256, bytes, storage key). Schema: `api/CERT_DB_SCHEMA.sql`
- **`qd_blocks`** — Block-to-batch mapping: `(bar_serial, batch_num)` → block_id, folder_slug, label, story_mode. Schema: `api/BLOCKS_DB_SCHEMA.sql`
- **`qd_stories`** — Story HTML fragments: block_id + item_num (NULL = shared, 1–8 = per-item). Schema: `api/BLOCKS_DB_SCHEMA.sql`
- **`qd_artist_applications`** — Artist submissions with 30+ columns across 6 sections. Status enum: `pending`/`reviewed`/`accepted`/`declined`. Schema: `api/ARTIST_APP_DB_SCHEMA.sql`

### Deploy: DB Setup

```
1. Run CERT_DB_SCHEMA.sql in phpMyAdmin
2. Run BLOCKS_DB_SCHEMA.sql
3. Run ARTIST_APP_DB_SCHEMA.sql
4. Hit /api/admin/seed_blocks.php (Basic Auth) to populate blocks 00–14
5. Ensure uploads/artist_applications/ is writable
```

---

## URL Structure

| Pattern | Serves |
|---------|--------|
| `/collection/silverbar-01/taurus?batch=1` | Block sub-page (PHP template) |
| `/collection-silverbar-01?batch=1` | Main bar grid page (static HTML) |
| `/nft?nft=qd-silver-0000001&bar=E101837&set=1&batch=1` | NFT detail |
| `/cert?cert=QDCERT-E101837-0000001` | Certificate viewer |
| `/verify?cert=QDCERT-E101837-0000001` | Public verification |

Old URLs (`collection-silverbar-01-aquarius.html`) are 301-redirected to new clean URLs via `.htaccess`.

---

## Design

- **Dark theme** — Navy base `#050a18`, gold `#d9b46c`, maroon `#7a1f2a`, lavender `#b9a7ff`
- **CSS custom properties** in `assets/css/styles.css`
- **NFT watermarking** — CSS overlay via `data-watermark` attribute
- **Card tilt effect** — Interactive 3D tilt on collection grid cards
- **Responsive** — Mobile hamburger menu, fluid grid layouts
- **No page reloads** — Batch navigation via `history.pushState` + dynamic DOM updates

---

## Social

- [X (Twitter)](https://x.com/Rarefolioio)
- [Discord](https://discord.gg/JZ8UrzujHK)

---

## Internal Documentation

Detailed build logs, plans, and technical docs are in [`01_md_plan_files/`](01_md_plan_files/):

- `BUILD_rarefolio_master.md` — Full build history with go-live checklist
- `ongoing_plan.md` — Current roadmap, NFT wiring checklist, and status
- `AGENTS.md` — Technical reference for AI-assisted development (Warp)
- `CHANGELOG.md` — Change history
- `Art-Directed PDF Certificate Templates.md` — Cert PDF design spec
- `README_rarefolio.md` — Internal project status & remaining to-do

---

## License

All rights reserved. © 2026 Rarefolio.io
