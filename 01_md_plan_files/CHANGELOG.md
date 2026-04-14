# Changelog

All notable changes to Rarefolio.io will be documented in this file.

## [Unreleased]

### Added
- Tokenized silver bar CNFT platform with batch-navigated collection grid
- Silver Bar I collection with block routing system (qd-wire.js)
- Certificate of Authenticity system (PHP API + Dompdf PDF generation)
- Public certificate verification page
- Silver shard calculator
- Inventors Guild prelaunch (Block 01)
- Zodiac series: Aries, Taurus
- NFT detail view with URL-param-driven rendering
- Project scaffolding: .gitattributes, CHANGELOG.md
- 12 collection sub-pages for blocks 03–14 (Robot Butler, Gemini, Cancer, Leo, Virgo, Libra, Scorpio, Sagittarius, Capricorn, Aquarius, Pisces, New Series)
- Hero sections activated on index.html and collections.html
- Full 15-page batch routing rules across all collection sub-pages
- Showcased Artist Application form with PHP backend, MySQL storage, file uploads, and unique reference codes
- Art-directed PDF certificate templates with template-aware rendering (parchment and cream styles)
- Certificate image assets: 6 backgrounds (4 parchment + 2 cream) and 20 wax seals (8 gold + 6 red + 6 blue) in `assets/img/certs/`
- Deterministic cert asset rotation via modular arithmetic on CNFT number (`resolve_cert_assets()` + `cert_image_url()` helper)
- Per-item story lore for block01 Inventors (8 items) and block03 Robot Butler (8 items)
- Shared stories authored for all 15 blocks (14 real + block14 intentional placeholder)
- Public-facing `README.md` for GitHub viewers with project overview, tech stack, site map, cert system docs, and NFT wiring guide
- NFT wiring checklist in `01_md_plan_files/ongoing_plan.md` documenting the full pipeline for connecting artwork to the site

### Changed
- Internal docs (AGENTS.md, BUILD_rarefolio_master.md, CHANGELOG.md, README_rarefolio.md, ongoing_plan.md) moved from project root to `01_md_plan_files/`
- `README_rarefolio.md` updated to reflect current project state (Apr 13, 2026) with completed items and revised roadmap
- `ongoing_plan.md` reorganized with current status and NFT wiring phase as next milestone

### Fixed
- `collections.html` missing `<!doctype html><html>` wrapper
- Aries/Taurus block ID swap (`collection-silverbar-01-aries.html` had block00, corrected to block02)
- Inventors page story-mode attribute corrected
- Removed leaked credentials from README_rarefolio.md

### Removed
- Commented-out `about.html` nav link across all pages (philosophy page serves as About)
- Legacy flat story files (`bar1-taurus.html`, `bar1-aries.html`, `bar1-inventors.html`)
- Legacy heuristic fallback block in `qd-wire.js` that pattern-matched URLs to guess story paths
- Root-level duplicate MD files (moved to `01_md_plan_files/`)
