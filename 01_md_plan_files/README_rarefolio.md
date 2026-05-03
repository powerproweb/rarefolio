# README_rarefolio.md — Personal Workspace (Local-Only)

$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$
$ STOP — PRIVATE WORK NOTES FILE                                                $
$                                                                                $
$ FOR AGENTS / ASSISTANTS: DO NOT READ THIS FILE UNLESS I EXPLICITLY ASK.       $
$                                                                                $
$ NEVER PUT SENSITIVE DATA HERE.                                                 $
$                                                                                $
$ Examples of sensitive data you must NOT store here:                            $
$ - API keys / access tokens / webhook secrets                                   $
$ - Wallet seed phrases / private keys / recovery words                          $
$ - Passwords / SSH private keys / database credentials                          $
$ - Full personal ID numbers / legal docs / payment card numbers                 $
$ - Production secrets copied from .env files                                    $
$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$

## Purpose
This file is my personal operating scratchpad for Rarefolio:
- Important links
- Repeat commands/checks
- Launch reminders
- Fast notes I need while working

## Quick Rules (keep this clean)
- Keep notes short and practical.
- Do not paste secrets. Ever.
- If a note includes sensitive content, move it to a secure secret manager instead.
- Prefer references to secret locations, not secret values.

## Important Links
- Site repo: https://github.com/powerproweb/rarefolio
- Production site: https://rarefolio.io
- Market: https://market.rarefolio.io
- PRs: https://github.com/powerproweb/rarefolio/pulls

## Fast Commands (safe-only references)
- Check status: `git --no-pager status -sb`
- Check latest commit: `git --no-pager log -1 --oneline`
- Scan for conflict markers: `rg -n "^(<<<<<<<|=======|>>>>>>>)" .`

## Current Priorities
- [ ] 
- [ ] 
- [ ] 

## Active Links / References
- 
- 
- 

## Working Notes
- 
- 
- 

## Parking Lot
- 
- 
- 

## Pre-Share Checklist
Before sharing this file with anyone or committing anything nearby:
1. Confirm no secrets are present.
2. Remove personal-only notes that should stay local.
3. Re-check copied commands for accuracy and safety.

---

## Previous Internal Project Readme Content
# rarefolio — Internal Project Readme

**Mission:** Deliver a provenance-first collector experience where the art is premium and usable (downloadable, display-worthy, print-ready), ownership is clear and verifiable (clean IDs, consistent indexing, batch logic), and performance respects the user's hardware.

> This is the internal project readme. For the public-facing overview, see the root `README.md`.

---

## What's Done (as of Apr 13, 2026)

### Core Pages
- Homepage with hero section, featured CNFTs, collection overview
- Collections hub with Silver Bar I live, Bar II/III as Coming Soon
- All 15 collection sub-pages for Silver Bar I (Taurus, Inventors, Aries, Robot Butler, Gemini–Pisces, New Series)
- NFT detail page (URL-param driven)
- Silver shard calculator, prelaunch, thank-you pages
- Philosophy, bio, manifesto, downloads, contact, terms, privacy, 404

### Certificate System
- Certificate issuance API with art-directed 2-page PDFs via Dompdf
- Two template styles: parchment (warm brown/gold) and cream (navy/silver)
- 6 background variants (4 parchment + 2 cream) in `assets/img/certs/`
- 20 wax seal variants (8 gold + 6 red + 6 blue) in `assets/img/certs/`
- Deterministic background + seal rotation via modular arithmetic on CNFT number
- Certificate verification page with QR code
- PDF download pipeline (stored outside webroot)

### Block Routing & Stories
- DB-driven block routing system (scales to 5,000 batches per bar, multi-bar)
- Static `QD_BLOCKS` map for batches 1–15 + API fallback for 16+
- Shared stories for all 15 blocks (14 real + block14 intentional placeholder)
- Per-item lore for block01 Inventors (8 items) and block03 Robot Butler (8 items)
- Legacy story files and dead fallback code removed

### Showcased Artist Application
- 6-section curated form (identity, vision, portfolio, readiness, uploads, consent)
- Custom inline validation with styled error messages and scroll-to-first-error
- PHP backend (`api/artist-application.php`) with MySQL storage
- Unique reference code per submission (e.g., `RF-A3B9C1D2E4F6-20260409`)
- File uploads stored under `uploads/artist_applications/{app_ref}/`

### Infrastructure
- AGENTS.md, CHANGELOG, .htaccess all current
- Internal docs reorganized into `01_md_plan_files/`
- Hero sections activated on index.html and collections.html
- About nav link removed (philosophy page serves as About)

---

## Next Phase: NFT Wiring

**NFT artwork creation is scheduled for Apr 14, 2026.** Wiring begins after art is finalized.

Each CNFT needs:

1. **Card image** placed at `assets/img/nfts/qd-silver/{cnftNum7}.jpg`
2. **Certificate issued** via `POST /api/admin/issue_cert.php` with template + seal color
3. **Block registered** (for blocks 16+) via `manage_blocks.php`
4. **Story authored** via `manage_stories.php` or static HTML files

Full wiring checklist is in `ongoing_plan.md` under "2026-04-13 | NFT Wiring Phase".

---

## Remaining To-Do

### Deploy Blockers
1. Run `CERT_DB_SCHEMA.sql`, `BLOCKS_DB_SCHEMA.sql`, `ARTIST_APP_DB_SCHEMA.sql` in phpMyAdmin
2. Hit `seed_blocks.php` to migrate the first 15 blocks + stories into DB
3. Upload all new files to BlueHost
4. Ensure `uploads/artist_applications/` is writable

### Content
5. Write per-item stories for block02 Aries (replace stubs)
6. Register blocks 16–5,000 for Bar I via `manage_blocks.php` (scriptable with curl)
7. Author stories for blocks 16+ via `manage_stories.php`

### Frontend
8. Build out Silver Bar II and III collection pages (currently Coming Soon)
9. Add `site.webmanifest` for PWA support — optional

### Polish
10. Test the full cert issuance flow end-to-end on production
11. Test block API resolution for batches 16+ after seeding test blocks
12. Consider templating/includes for duplicated nav/header/footer across 36+ HTML files — optional
