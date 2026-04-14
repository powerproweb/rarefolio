# rarefolio.io — Ongoing Plan Log

---

---
## 2026-04-13 | NFT Wiring Phase
### What Needs to Happen After NFT Art Is Created

**NFT artwork creation is scheduled for Apr 14, 2026.** Once the art is done, wiring begins.

Once NFT artwork is finalized, each CNFT must be wired into multiple systems across the site. This is the checklist per CNFT / per block:

**1. NFT Card Images**
- Drop individual card images into `assets/img/nfts/qd-silver/{cnftNum7}.jpg`
- Naming: 7-digit zero-padded number matching the CNFT slug (e.g., `0000001.jpg` for `qd-silver-0000001`)
- The NFT detail page (`nft.html`) and collection grid both resolve images from the `cardImageTemplate` path in `assets/data/collections/qd-silverbar-01.json`
- Fallback: `assets/img/nfts/placeholder.jpg` is shown if the image file doesn't exist

**2. Collection Block Artwork**
- Each block has a folder under `assets/img/collection/{folder_slug}/`
- Existing folders: `scnft_sp_inventors`, `scnft_sp_robot_butler`, `scnft_zodiac_aries`, `scnft_zodiac_taurus`, etc.
- Each folder should contain: web JPGs, print-ready 4000x/8000x CMYK PNGs, ZIPs for download, QR codes, and a `manifest.json`
- The collection sub-pages reference these via `data-image-template` attributes

**3. Certificate Issuance**
- Issue certs via `POST /api/admin/issue_cert.php` (Basic Auth required)
- Required payload fields:
  - `cert_id`: `QDCERT-{barSerial}-{cnftNum7}` (e.g., `QDCERT-E101837-0000009`)
  - `cnft_id`: slug like `qd-silver-0000009`
  - `bar_serial`: `E101837`
  - `template`: `parchment` or `cream`
  - `sealColor`: `gold`, `red`, or `blue` (default: `gold`)
  - Holder info, wallet display, vault record ID, etc.
- The cert PDF is generated with art-directed backgrounds (4 parchment + 2 cream variants) and wax seals (8 gold + 6 red + 6 blue) from `assets/img/certs/`
- Background + seal selection is deterministic via `(cnft_num - 1) % poolSize`
- PDFs are stored immutably outside webroot at `/home/<user>/rf_storage/pdfs/`

**4. Block Registration (for blocks 16+)**
- Blocks 00–14 (batches 1–15) are hardcoded in `QD_BLOCKS` map in `qd-wire.js` and seeded to DB via `seed_blocks.php`
- Blocks 16–5,000 are registered via `POST /api/admin/manage_blocks.php`
- Each block needs: `bar_serial`, `batch_num`, `block_id`, `folder_slug`, `label`, `story_mode` (`shared` or `per_item`)
- Can be scripted with curl for bulk registration

**5. Story Content**
- Static stories (blocks 00–14): `assets/stories/blockNN/shared.html` and optionally `items.html`
- DB-driven stories (blocks 16+): created via `POST /api/admin/manage_stories.php`
- `items.html` format: `<article data-item="1">...lore...</article>` through `data-item="8"`
- Per-item stories currently exist for block01 (Inventors) and block03 (Robot Butler)

**6. Wiring Verification**
- After wiring, test each CNFT:
  - Collection grid loads image correctly
  - NFT detail page (`nft.html?nft=qd-silver-NNNNNNN&bar=E101837&set=1`) renders image, badge, story
  - Certificate viewer (`cert.html?cert=QDCERT-E101837-NNNNNNN`) shows verified status
  - PDF download works via `download.php`
  - Story panel loads shared or per-item content

---

---
## 2026-04-08 | 18:56 UTC
### Status Review & Roadmap

**What's Done (as of Apr 13)**
- Homepage, collections hub, calculator, contact, terms, privacy, manifesto, bio, philosophy, downloads, 404, prelaunch, thank-you pages
- Silver Bar I collection page with batch-navigated grid (batches 1–15 live, 16–5000 ready via DB)
- NFT detail page (URL-param driven)
- Certificate pipeline: issuance API, art-directed PDFs (parchment/cream + 20 wax seals), verification, download
- Art-directed cert templates with 6 backgrounds (4 parchment + 2 cream) and 20 wax seals (8 gold + 6 red + 6 blue)
- DB-driven block routing system (scales to 5,000 batches per bar, multi-bar)
- All 15 collection sub-pages with correct block routing
- Hero sections activated on index.html and collections.html
- Shared stories for all 15 blocks (14 real + block14 intentional placeholder)
- Per-item lore for block01 Inventors (8 items) and block03 Robot Butler (8 items)
- Showcased Artist Application (form + API + DB schema)
- Internal docs reorganized to `01_md_plan_files/`
- AGENTS.md, CHANGELOG, .htaccess all current

---

**Still To Do**

**DB Setup — Deploy Blockers (do these first)**
1. Run `CERT_DB_SCHEMA.sql`, `BLOCKS_DB_SCHEMA.sql`, `ARTIST_APP_DB_SCHEMA.sql` in phpMyAdmin
2. Hit `seed_blocks.php` to migrate the first 15 blocks + stories into DB
3. Upload all new files to BlueHost
4. Ensure `uploads/artist_applications/` is writable

**NFT Wiring (after artwork is created)**
5. Create and place NFT card images per the wiring checklist above
6. Issue certificates for each CNFT via the admin API
7. Register blocks 16–5,000 for Bar I via `manage_blocks.php`
8. Author stories for new blocks via `manage_stories.php`

**Content**
9. Write per-item stories for block02 Aries (replace stubs)
10. Author stories for blocks 16+ as they're registered

**Frontend**
11. Build out Silver Bar II and III collection pages (currently placeholders / Coming Soon)
12. Add `site.webmanifest` for PWA support — optional

**Polish**
13. Test the full cert issuance flow end-to-end on production
14. Test block API resolution for batches 16+ after seeding test blocks
15. Consider templating/includes for duplicated nav/header/footer — optional

---
