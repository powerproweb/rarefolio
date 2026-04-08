# BUILD_rarefolio_master.md
> **Master build log for rarefolio.io — newest plans go at the top.**

---

---
## 2026-04-08 | ~22:00 UTC — APR 10 GO-LIVE SPRINT

**Deadline: April 10, 2026 end-of-day | ~48 hours**

### Reality Check
The codebase is further along than the old TODO suggests. Per CHANGELOG, these are DONE:
- ✅ Hero sections on index.html + collections.html
- ✅ All 15 collection sub-pages (blocks 00–14) with batch routing rules
- ✅ about.html nav link removed (philosophy page serves as About)
- ✅ Art-directed PDF cert templates (6 backgrounds + 20 wax seals + rotation logic)

What's left falls into 3 lanes: **Infrastructure**, **Content**, and **Polish**.

### DAY 1 — Apr 9 (Wed): Infrastructure + Content Blitz

**Morning: Deploy the backend (~1 hr)**
1. Run `api/CERT_DB_SCHEMA.sql` + `api/BLOCKS_DB_SCHEMA.sql` in BlueHost phpMyAdmin
2. Hit `seed_blocks.php` (Basic Auth) to populate first 15 blocks + stories
3. FTP upload all local files to BlueHost; confirm `.htaccess` is the clean version
4. Smoke test: `resolve.php?bar=E101837&batch=1`, `story.php?block=E101837-block0000&item=0`, `cert.php?id=QDCERT-E101837-0000009`

**Afternoon + Evening: Content sprint (4–6 hrs)**
5. Write shared stories for blocks 03–14 (12 files × ~150–300 words each):
   block03 Robot Butler, block04 Gemini, block05 Cancer, block06 Leo, block07 Virgo, block08 Libra, block09 Scorpio, block10 Sagittarius, block11 Capricorn, block12 Aquarius, block13 Pisces, block14 New Series
6. ~~Write per-item stories for block01 — Inventors Guild (8 items)~~ ✅ DONE
7. ~~Write per-item stories for block02 — Aries~~ → using shared story for all 8 items
8. ~~Write per-item stories for block03 — Robot Butler~~ → using shared story for all 8 items

### DAY 2 — Apr 10 (Thu): Polish, Test, Go Live

**Morning (~1.5 hrs)**
9. Review/finalize all 12 shared stories + block01 per-item stories
10. Bar II/III decision — Option A (recommended): keep pages live, add "Coming Soon" banner, drop "(Placeholder)" from nav. Option B: hide from nav entirely.

**Afternoon: End-to-end testing (~2 hrs)**
11. Full cert pipeline: issue test certs (parchment + cream), verify, view, download PDF — confirm art-directed layout
12. Collection walkthrough: Silver Bar I batches 1–15, block sub-pages, individual NFT detail, story loading
13. Cross-browser + mobile spot-check (Chrome, Firefox, mobile viewport)
14. Final deploy: upload Day 2 changes, clear caches, verify live

### Out of Scope
- Blocks 16–5,000 registration (not needed — only 15 blocks are live)
- Per-item stories for blocks 04–14 (shared stories sufficient for launch)
- `site.webmanifest` / PWA, nav/footer templating refactor

### Deliverable
By end of Apr 10: rarefolio.io live with Silver Bar I fully navigable (15 blocks, 120 CNFTs), all shared stories authored, Inventors with per-item lore, Aries/Robot Butler using shared stories, cert pipeline tested on production, Bar II/III gracefully placeholder'd.

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
