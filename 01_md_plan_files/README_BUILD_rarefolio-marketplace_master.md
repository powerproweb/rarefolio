# BUILD_rarefolio_master.md
**Master build log for rarefolio.io — newest plans go at the top.**


1. Set a real CORS_ALLOWED_ORIGINS (e.g. https://rarefolio.io).
2. Generate one 32-byte hex secret and set it as both:
◦  RF_WEBHOOK_SECRET on the main site
◦  PUBLIC_SITE_WEBHOOK_SECRET on the marketplace

To ChatGPT: Generate one 32-byte hex secret | `5f9c1b74a8e2d6c34071fe9ab25cd8834a6f19d0be7c52a18df3460c9e1b72af`













---
## 2026-04-15 — Static-to-DB Story Migration

### What Changed
All 15 static story files in `assets/stories/block00`–`block14` are now migrated into the `qd_stories` DB table. Stories for all blocks (00–5,000+) are served exclusively via `/api/blocks/story.php`.

### Files Modified
- `assets/js/qd-wire.js`
  - `QD_BLOCKS` entries: removed `shared_story` property from all 15 entries
  - `getBlockMeta()`: removed `shared_story` from returned meta objects
  - `updatePageMeta()`: removed `shared_story`-based `storySrc` assignment
  - `storyUrlForBlock()`: removed `_source === 'api'` gate — always builds `/api/blocks/story.php` URL
  - `renderNftDetail()`: removed static-only preflight fetch block
- `api/admin/seed_blocks.php`: added `items.html` fallback parser using `DOMDocument` + `DOMXPath` to extract `<article data-item="N">` elements and seed each as a separate `item_num` row in `qd_stories`

### Deploy Steps (run once on server)
1. FTP upload `assets/js/qd-wire.js` and `api/admin/seed_blocks.php`
2. Hit `https://rarefolio.io/api/admin/seed_blocks.php` (Basic Auth) — idempotent, safe to re-run
3. Smoke test: `story.php?block=E101837-block0000&item=0` (Taurus shared) and `story.php?block=E101837-block0001&item=1` (Inventors per-item)
4. After confirming stories load, delete `assets/stories/block00`–`block14` from server and local repo

### Architecture After Migration
- `QD_BLOCKS` in `qd-wire.js` remains as a **fast block metadata cache** (folder slugs, labels, story modes) for Bar I batches 1–15 — no static file paths
- All stories (blocks 00–5,000+, all bars) served from `qd_stories` via `story.php`
- `assets/stories/` directory to be deleted post-verification

---
What's left is all deploy + manual testing (Day 2):

1. Run 3 SQL schemas in BlueHost phpMyAdmin — CERT_DB_SCHEMA.sql, BLOCKS_DB_SCHEMA.sql, ARTIST_APP_DB_SCHEMA.sql
2. Hit seed_blocks.php (Basic Auth) to populate the first 15 blocks into DB
3. FTP upload all files to BlueHost — confirm .htaccess is the clean version, uploads/artist_applications/ is writable
4. Smoke test the 3 API endpoints: resolve.php, story.php, cert.php
5. Bar II/III — add "Coming Soon" banner or hide from nav
6. Cert pipeline test — issue test certs (parchment + cream), verify, view, download PDF
7. Collection walkthrough — Silver Bar I batches 1–15, sub-pages, NFT detail, story loading
8. Browser spot-check — Chrome, Firefox, mobile viewport
9. Final deploy — push any Day 2 fixes, clear caches, verify live

Items 1–4 are ~30 minutes. Item 5 is a quick decision + edit. Items 6–8 are ~1.5 hours of testing. You could be live by midday.
---
## 2026-04-09 | ~21:33 UTC — Legacy story cleanup + finalized architecture

### Removed
- `assets/stories/bar1-taurus.html`, `bar1-aries.html`, `bar1-inventors.html` — legacy flat story files (~1,600 lines)
- Legacy heuristic fallback block in `qd-wire.js` (lines 707–714) that pattern-matched URL strings to guess story paths
- `data-story-src` attributes from `collection-silverbar-01-aries.html`, `-taurus.html`, `-inventors.html` that pointed to deleted files
- Legacy fallback documentation line from AGENTS.md

### Finalized Story Resolution Architecture
All stories now resolve through two clean paths — no more legacy fallbacks:
1. **Static (blocks 00–14, Bar I)**: `assets/stories/blockNN/shared.html` or `items.html` → resolved via `QD_BLOCKS` map in `qd-wire.js`
2. **DB-driven (blocks 16–5,000+, all bars)**: `/api/blocks/story.php` → reads from `qd_stories` table, managed via `manage_stories.php`

No static files needed for blocks beyond 14. The DB handles all scaling.

---

---
## 2026-04-09 | ~21:25 UTC — Bugfix: Aries/Taurus block ID swap

### Problem
`collection-silverbar-01-aries.html` had `data-block-id="block00"` (Taurus) and `collection-silverbar-01-taurus.html` had `data-block-id="block02"` (Aries). The page-level override caused each page to load the wrong block’s images and stories.

### Fix
- `collection-silverbar-01-aries.html` line 70: `block00` → `block02`, `per_item` → `shared`
- `collection-silverbar-01-taurus.html` line 70: `block02` → `block00`

### Caught by
End-to-end story wiring test (data-block-id audit across all 15 sub-pages).

---

---
## 2026-04-09 | ~15:45 UTC — Showcased Artist Application

### What Was Built
Full artist application pipeline: public form → client-side validation → PHP backend → MySQL storage with file uploads.

### Files Added
- `rarefolio_showcased_artist_application.html` — Public-facing multi-section application form with client-side validation (required fields, email/URL format, consent checks), dynamic error display, and async `fetch` submission to the backend. On success, replaces form with confirmation + unique reference code.
- `api/artist-application.php` — `POST /api/artist-application.php` endpoint. Accepts `multipart/form-data`, validates required fields server-side, generates a unique reference code (`RF-{hex}-{date}`, e.g. `RF-A3B9C1D2E4F6-20260409`), saves uploaded files to `uploads/artist_applications/{app_ref}/`, inserts into `qd_artist_applications`. Returns JSON `{ success, message, app_ref }`.
- `api/ARTIST_APP_DB_SCHEMA.sql` — Creates `qd_artist_applications` table with 30+ columns across 6 sections: Artist Identity, Artistic Practice & Vision, Portfolio & Presentation, Professional Readiness, Uploads (file paths), and Consent. Indexed on `app_ref` (unique), `email`, `status`, `submitted_at`.

### Files Modified
- `README_rarefolio.md` — Added artist application documentation and new deploy steps (1b: run schema, 3b: ensure upload dir is writable)

### Deploy Steps (added to go-live sprint)
- 1b. Run `api/ARTIST_APP_DB_SCHEMA.sql` in phpMyAdmin (creates `qd_artist_applications`)
- 3b. Ensure web server can write to `uploads/artist_applications/`

### DB Table
`qd_artist_applications` — status enum: `pending` → `reviewed` → `accepted` / `declined`. Uploads stored as relative paths under `uploads/artist_applications/{app_ref}/`.

---

---
## 2026-04-08 | ~22:00 UTC — APR 10 GO-LIVE SPRINT (updated Apr 9 ~20:30 UTC)

**Deadline: April 10, 2026 end-of-day**

### Story Audit (final — Apr 9 ~21:33 UTC)
- block00 (Taurus): shared ✅ 14KB | shared-only
- block01 (Inventors): shared ✅ 8.5KB | items ✅ 20KB (8 articles)
- block02 (Aries): shared ✅ 14KB | shared-only
- block03 (Robot Butler): shared ✅ 10KB | items ✅ 35KB (8 articles)
- block04–13 (Gemini→Pisces): shared ✅ all real (5–6KB) | shared-only
- block14 (New Series): shared ⚠️ placeholder (intentional — next collection)
- Legacy files (`bar1-*.html`): ✅ **DELETED** + dead fallback code removed from qd-wire.js

### What's Done
- ✅ Hero sections on index.html + collections.html
- ✅ All 15 collection sub-pages with correct block routing (Aries/Taurus swap fixed)
- ✅ about.html nav link removed (philosophy page serves as About)
- ✅ Art-directed PDF cert templates (6 backgrounds + 20 wax seals + rotation logic)
- ✅ Shared stories for all 15 blocks (14 real + block14 intentional placeholder)
- ✅ Per-item lore for block01 Inventors (8 items) and block03 Robot Butler (8 items)
- ✅ Showcased Artist Application (form + API + DB schema)
- ✅ Blocks 04–13 items.html disabled (shared-only)
- ✅ Legacy story files + dead fallback code removed
- ✅ Inventors page story-mode attribute corrected

### GO-LIVE CHECKLIST — Apr 10 (Thu)

**STEP 1: Database setup (~10 min)**
Open BlueHost phpMyAdmin → select `rarefolio_cnftcert` database → run these in order:
- [ ] `api/CERT_DB_SCHEMA.sql` → creates `qd_certificates`
- [ ] `api/BLOCKS_DB_SCHEMA.sql` → creates `qd_blocks` + `qd_stories`
- [ ] `api/ARTIST_APP_DB_SCHEMA.sql` → creates `qd_artist_applications`

**STEP 2: Seed block data (~2 min)**
- [ ] Open `https://rarefolio.io/api/admin/seed_blocks.php` in browser (Basic Auth required)
- [ ] Confirm response shows 15 blocks + stories inserted

**STEP 3: File upload (~15 min)**
- [ ] FTP or cPanel File Manager: upload entire local project to BlueHost webroot
- [ ] Verify `.htaccess` is the current clean version (NOT `.htaccess.old1`)
- [ ] Verify `uploads/artist_applications/` directory exists and is writable (chmod 755 or 775)
- [ ] Verify `dompdf/` directory is intact on server

**STEP 4: Smoke test APIs (~5 min)**
- [ ] `GET https://rarefolio.io/api/blocks/resolve.php?bar=E101837&batch=1` → expect JSON with block00 metadata
- [ ] `GET https://rarefolio.io/api/blocks/story.php?block=E101837-block0000&item=0` → expect Taurus shared story HTML
- [ ] `GET https://rarefolio.io/api/cert.php?id=QDCERT-E101837-0000009` → expect cert JSON

**STEP 5: Bar II/III treatment (~10 min)**
- [ ] Option A (recommended): add "Coming Soon" banner to `collection-silverbar-02.html` + `03.html`, remove "(Placeholder)" from nav labels
- [ ] Option B: hide Bar II/III links from nav entirely

**STEP 6: Certificate pipeline test (~20 min)**
- [ ] `POST https://rarefolio.io/api/admin/issue_cert.php` with `template: parchment` → expect success + PDF generated
- [ ] `POST` again with `template: cream` → expect success + different background
- [ ] Visit `https://rarefolio.io/verify.html?id=QDCERT-...` → confirm cert verifies
- [ ] Visit `https://rarefolio.io/cert.html?id=QDCERT-...` → confirm cert viewer renders
- [ ] Download PDF via `https://rarefolio.io/download.php?file=...` → confirm art-directed layout, wax seal, background

**STEP 7: Collection walkthrough (~30 min)**
- [ ] `collection-silverbar-01.html?batch=1` → batch 1 loads, Taurus story appears in story panel
- [ ] Navigate batches 1–15 via pill navigator → each batch redirects to correct sub-page
- [ ] Click into `collection-silverbar-01-inventors.html` → grid renders, shared story loads
- [ ] Click an individual NFT → `nft.html` populates title, image, badge, story
- [ ] On Inventors NFT detail (item 1–8) → per-item story loads from items.html
- [ ] On Robot Butler NFT detail (item 1–8) → per-item story loads
- [ ] On Aries/Taurus NFT detail → shared story loads (no per-item)

**STEP 8: Cross-browser + mobile (~15 min)**
- [ ] Chrome desktop → nav, grid, tilt effect, watermark overlay, back-to-top
- [ ] Firefox desktop → same checks
- [ ] Mobile viewport (Chrome DevTools or real device) → hamburger menu, grid layout, story panel

**STEP 9: Final deploy (~5 min)**
- [ ] Upload any Day 2 fixes (Bar II/III banner, typos, etc.)
- [ ] Clear any server-side caches if applicable
- [ ] Final visit to `https://rarefolio.io/` → confirm live

### Out of Scope
- Blocks 16–5,000 registration (DB-driven, post-launch)
- Per-item stories for blocks 04–14 (shared stories sufficient)
- `site.webmanifest` / PWA, nav/footer templating refactor

### Deliverable
By end of Apr 10: rarefolio.io live with Silver Bar I fully navigable (15 blocks, 120 CNFTs), all shared stories authored, Inventors/Robot Butler with per-item lore, cert pipeline tested on production, Bar II/III gracefully placeholder'd.

---

---
## 2026-04-08 | 18:56 UTC — Status Review & Roadmap
*Source: ongoing_plan.md*

### What's Done
- Homepage, collections hub, calculator, contact, terms, privacy, manifesto, bio, philosophy, downloads, 404, prelaunch, thank-you pages
- Silver Bar I collection page with batch-navigated grid (batches 1–15 live, 16–5000 ready via DB)
- NFT detail page (URL-param driven)
- Certificate pipeline: issuance API, art-directed PDFs (parchment/cream + 20 wax seals), verification, download
- DB-driven block routing system (scales to 5,000 batches per bar, multi-bar)
- Story placeholder files for all 15 blocks + per-item stubs for Inventors, Aries, Robot Butler
- AGENTS.md, CHANGELOG, .htaccess all current

### Still To Do

**DB Setup — Deploy Blockers (do these first)**
1. Run `BLOCKS_DB_SCHEMA.sql` in phpMyAdmin (creates `qd_blocks` + `qd_stories`)
2. Hit `seed_blocks.php` to migrate the first 15 blocks + stories into DB
3. Upload all new files to BlueHost

**Content (authoring work)**
4. Write the 12 story placeholders with real lore (blocks 03–14: Robot Butler, Gemini → Pisces, New Series)
5. Write per-item stories for block01 Inventors (8 files: 1.html–8.html)
6. Write per-item stories for block02 Aries (replace stubs)
7. Write per-item stories for block03 Robot Butler (replace stubs)
8. Register blocks 16–5,000 for Bar I via `manage_blocks.php` (can be scripted with curl)
9. Author stories for blocks 16+ via `manage_stories.php`

**Frontend Gaps**
10. Uncomment and finish hero sections on `index.html` and `collections.html`
11. Create standalone collection sub-pages for the remaining 11 blocks (Gemini through New Series — currently only Taurus, Aries, Inventors have dedicated pages)
12. Build out Silver Bar II and III collection pages (currently placeholders)
13. Create `about.html` (commented out in nav) or remove the nav comment
14. Add `site.webmanifest` for PWA support (commented out in all pages) — optional

**Polish**
15. Test the full cert issuance flow end-to-end on production (issue a cert, verify, download PDF)
16. Test block API resolution for batches 16+ after seeding some test blocks
17. Consider templating/includes for the duplicated nav/header/footer across 22+ HTML files — optional

---

---
## 2026-04-08 — Art-Directed PDF Certificate Templates
*Source: 01_md_plan_files/Art-Directed PDF Certificate Templates.md*

### Problem
The `render_pdf_html()` function in `api/admin/issue_cert.php` produces a plain text-only 2-page PDF. It needs a premium visual design with background images, a wax seal, the site logo, and two distinct template variants (`parchment` and `cream`). The `template` field already flows through the payload but is currently ignored in rendering.

### Current State
- **Renderer**: `render_pdf_html()` in `api/admin/issue_cert.php` (lines 108–268) — returns an HTML string fed to Dompdf 3.1.4
- **Dompdf config**: `isRemoteEnabled: true`, `isHtml5ParserEnabled: true`, letter-size portrait, zero margins
- **Template field**: accepted as `parchment` or `cream` (validated line 327), stored in DB and payload, but `render_pdf_html()` doesn't use it
- **Page 1**: Brand, title, VERIFIED badge, attestation text, Identification table, Holder & Custody table, footer micro-terms
- **Page 2**: Brand, title, Verify URL, Cert View link, PDF Download link, On-chain Details table, Custody & Vault table, footer
- **Image assets**: none exist yet — user is creating background images (2550×3300 JPG) and wax seal (600×600 PNG with transparency)
- **Logo**: `assets/img/rf_logo_site.png` (307 KB) available for embedding
- **Dompdf constraints**: CSS `background-image` works on `<body>` and block elements; `position: absolute` supported; no CSS grid; `background-size: cover` supported; images must use absolute URLs or base64 data URIs

### Proposed Changes

**1. Create image asset directory**
Create `assets/img/certs/` to hold:
- `bg-parchment.jpg` — warm parchment background (2550×3300, user-provided)
- `bg-cream.jpg` — cream/ivory background (2550×3300, user-provided)
- `wax-seal.png` — wax stamp with transparency (600×600, user-provided)

**2. Add helper to resolve image paths as absolute URLs**
Add `cert_image_url(string $relativePath): string` that builds an absolute `https://rarefolio.io/...` URL from a relative asset path.

**3. Rewrite `render_pdf_html()` with template-aware design**
Pass the `template` value into `render_pdf_html()` to select:
- **Background image**: full-page `background-image` on `.page` divs via matching bg file
- **Color palette**: parchment = warm dark-brown text with gold accents; cream = dark-navy text with silver/cool accents
- **Panel styling**: semi-transparent white panels with subtle border, adapted per template

Page 1 layout: full-bleed background, centered logo, title, subtitle, VERIFIED badge, attestation panel, identification table, holder & custody table, wax seal (absolute bottom-right, ~2in), footer micro-terms.

Page 2 layout: same background, logo, verification URL panel, cert view + PDF download panels, on-chain details table, custody & vault panel, footer.

**4. Update `generate_pdf_bytes()` if needed**
Current Dompdf options already have `isRemoteEnabled: true`. No changes expected unless testing reveals issues.

**5. Update AGENTS.md**
Document the new `assets/img/certs/` directory and template-aware PDF rendering.

### Assets Required From User
- `bg-parchment.jpg` — 2550×3300 px, warm parchment texture
- `bg-cream.jpg` — 2550×3300 px, cream/ivory texture
- `wax-seal.png` — 600×600 px, PNG with transparency

### Files Changed
- `api/admin/issue_cert.php` — rewrite `render_pdf_html()`, add `cert_image_url()` helper
- `assets/img/certs/` — new directory with 3 user-provided images
- `AGENTS.md` — document cert image assets and template variants

---
