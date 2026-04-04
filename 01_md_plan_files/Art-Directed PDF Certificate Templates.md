# Art-Directed PDF Certificate Templates
## Problem
The `render_pdf_html()` function in `api/admin/issue_cert.php` produces a plain text-only 2-page PDF. It needs a premium visual design with background images, a wax seal, the site logo, and two distinct template variants (`parchment` and `cream`). The `template` field already flows through the payload but is currently ignored in rendering.
## Current State
* **Renderer**: `render_pdf_html()` in `api/admin/issue_cert.php` (lines 108–268) — returns an HTML string fed to Dompdf 3.1.4
* **Dompdf config**: `isRemoteEnabled: true`, `isHtml5ParserEnabled: true`, letter-size portrait, zero margins (`@page { size: letter; margin: 0; }`)
* **Template field**: accepted as `parchment` or `cream` (validated line 327), stored in DB and payload, but `render_pdf_html()` doesn't use it
* **Page 1**: Brand, title, VERIFIED badge, attestation text, Identification table, Holder & Custody table, footer micro-terms
* **Page 2**: Brand, title, Verify URL, Cert View link, PDF Download link, On-chain Details table, Custody & Vault table, footer
* **Image assets**: none exist yet — user is creating background images (2550×3300 JPG) and wax seal (600×600 PNG with transparency)
* **Logo**: `assets/img/rf_logo_site.png` (307 KB) available for embedding
* **Dompdf constraints**: CSS `background-image` works on `<body>` and block elements; `position: absolute` supported; no CSS grid; `background-size: cover` supported; images must use absolute URLs or base64 data URIs for reliable rendering
## Proposed Changes
### 1. Create image asset directory
Create `assets/img/certs/` to hold:
* `bg-parchment.jpg` — warm parchment background (2550×3300, user-provided)
* `bg-cream.jpg` — cream/ivory background (2550×3300, user-provided)
* `wax-seal.png` — wax stamp with transparency (600×600, user-provided)
### 2. Add helper to resolve image paths as absolute URLs
Add a small PHP helper function `cert_image_url(string $relativePath): string` that builds an absolute `https://rarefolio.io/...` URL from a relative asset path. Dompdf with `isRemoteEnabled: true` will fetch these during render. This keeps the HTML clean and avoids base64 bloat.
### 3. Rewrite `render_pdf_html()` with template-aware design
Pass the `template` value (`parchment` or `cream`) into `render_pdf_html()` (currently only receives `$payload` — the template is at `$payload['template']`). Use it to select:
* **Background image**: full-page `background-image` on `.page` divs via the matching `bg-parchment.jpg` or `bg-cream.jpg`
* **Color palette**: parchment uses warm dark-brown text with gold accents; cream uses dark-navy text with silver/cool accents
* **Panel styling**: semi-transparent white panels with subtle border, adapted per template
#### Page 1 layout (Certificate of Authenticity):
* Full-bleed background image on `.page`
* Logo (`rf_logo_site.png`) centered at top, ~1in wide
* Title "Certificate of Authenticity" in serif font below logo
* Subtitle line with CNFT series + bar serial
* VERIFIED badge (styled pill)
* Attestation paragraph in a styled panel
* Identification table panel (cert ID, CNFT ID, collection, bar serial, edition, silver allocation)
* Holder & Custody table panel
* Wax seal image positioned absolute, bottom-right of page 1, ~2in wide
* Footer micro-terms at page bottom
#### Page 2 layout (Verification & Chain Record):
* Same full-bleed background
* Logo centered at top
* Verification URL panel
* Certificate View + PDF Download panels
* On-chain Details table panel
* Custody & Vault Reference panel
* Footer text
### 4. Update `generate_pdf_bytes()` if needed
The current Dompdf options already have `isRemoteEnabled: true` which is required for fetching images via URL. No changes expected here unless testing reveals issues, in which case we'd fall back to base64-encoded data URIs.
### 5. Update AGENTS.md
Document the new `assets/img/certs/` directory and the template-aware PDF rendering.
## Assets Required From User
Before execution can begin:
* `bg-parchment.jpg` — 2550×3300 px, warm parchment texture
* `bg-cream.jpg` — 2550×3300 px, cream/ivory texture
* `wax-seal.png` — 600×600 px, PNG with transparency
Place in `assets/img/certs/`.
## Files Changed
* `api/admin/issue_cert.php` — rewrite `render_pdf_html()`, add `cert_image_url()` helper
* `assets/img/certs/` — new directory with 3 user-provided images
* `AGENTS.md` — document cert image assets and template variants
