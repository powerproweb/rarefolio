# OPERATIONS.md
**Rarefolio.io, Site Operations**
*This repo is PUBLIC on GitHub. Do not put live credentials here.*
*Credential values live ONLY in: (a) the server's gitignored `api/_config.php`, (b) the operator's password manager. See `SECURITY_NOTES.md` for the rotation runbook.*

---

## Hosting & Infrastructure

| Item | Value |
|---|---|
| Host | BlueHost (shared hosting / cPanel) |
| Domain | rarefolio.io |
| Webroot | `public_html/` |
| PHP version | 8.1 (handler: `ea-php81`) |
| PDF storage (outside webroot) | `/home/rarefolio/rf_storage/pdfs/` |
| Upload directory | `uploads/artist_applications/` (must be chmod 755 or 775) |
| Dompdf | Vendored in `dompdf/`, do not modify |

## GitHub

| Item | Value |
|---|---|
| Repo | https://github.com/powerproweb/rarefolio.git |
| Branch | `main` |
| Deploy method | FTP files to BlueHost webroot, no build step |

---

## Credentials (references only, values live in `api/_config.php` on the server)

### Database (MySQL)
Runtime file: `api/_config.php` (gitignored). Rotation: cPanel → MySQL Databases.

| Item | Source |
|---|---|
| Host | `api/_config.php :: DB_HOST` |
| Database | `api/_config.php :: DB_NAME` (user `rarefolio_cnftcert`) |
| Username | `api/_config.php :: DB_USER` |
| Password | `api/_config.php :: DB_PASS`, **never commit** |

### Admin API (Basic Auth for `/api/admin/*` and `/admin/story-editor.php`)
| Item | Source |
|---|---|
| Username | `api/_config.php :: ADMIN_USER` |
| Password | `api/_config.php :: ADMIN_PASS`, **never commit** |

### Under-development gate (`api/login.php`)
| Item | Source |
|---|---|
| Username | `api/_config.php :: UD_USER` |
| Password | `api/_config.php :: UD_PASS`, **never commit** |

Operators: pull current values from your password manager. Rotation history + procedures live in `SECURITY_NOTES.md`.

---

## Key URLs

### Public Site
| Page | URL |
|---|---|
| Homepage | https://rarefolio.io/ |
| Collections hub | https://rarefolio.io/collections |
| Silver Bar I | https://rarefolio.io/collection-silverbar-01 |
| Silver Bar I (Taurus) | https://rarefolio.io/collection/silverbar-01/taurus |
| Silver Bar I (Inventors) | https://rarefolio.io/collection/silverbar-01/inventors |
| NFT detail | https://rarefolio.io/nft |
| Certificate viewer | https://rarefolio.io/cert |
| Verify certificate | https://rarefolio.io/verify |
| Artist application | https://rarefolio.io/rarefolio_showcased_artist_application |
| Philosophy | https://rarefolio.io/rf_bus_philosophy |
| Silver shard calculator | https://rarefolio.io/collection-silverbar-calculator |

### Admin (Basic Auth required, use admin credentials above)
| Tool | URL |
|---|---|
| **Story Editor** | https://rarefolio.io/admin/story-editor.php |
| Seed blocks (re-run to refresh DB) | https://rarefolio.io/api/admin/seed_blocks.php |
| Issue certificate | https://rarefolio.io/api/admin/issue_cert.php |
| Manage blocks | https://rarefolio.io/api/admin/manage_blocks.php |
| Manage stories | https://rarefolio.io/api/admin/manage_stories.php |

### Public API
| Endpoint | Example |
|---|---|
| Resolve block by batch | https://rarefolio.io/api/blocks/resolve.php?bar=E101837&batch=1 |
| Get story | https://rarefolio.io/api/blocks/story.php?block=E101837-block0001&item=0 |
| Get cert | https://rarefolio.io/api/cert.php?id=QDCERT-E101837-0000009 |

---

## Bar I, Silver Bar Reference

| Item | Value |
|---|---|
| Bar Serial | `E101837` |
| CNFT ID format | `qd-silver-0000001` through `qd-silver-0040000` |
| Batch size | 8 CNFTs per batch |
| Total batches | 5,000 |
| Total CNFTs | 40,000 |
| Block ID format | `{barSerial}-block{NNNN}` e.g. `E101837-block0001` |
| Cert ID format | `QDCERT-{barSerial}-{cnftNum7}` e.g. `QDCERT-E101837-0000009` |

### Block List (Bar I, Batches 1–15, in DB)

| Batch | Block ID | Label | Story Mode |
|---|---|---|---|
| 1 | E101837-block0001 | Zodiac, Taurus | shared |
| 2 | E101837-block0002 | Steampunk, Inventors | per_item |
| 3 | E101837-block0003 | Zodiac, Aries | per_item |
| 4 | E101837-block0004 | Steampunk, Robot Butler | per_item |
| 5 | E101837-block0005 | Zodiac, Gemini | shared |
| 6 | E101837-block0006 | Zodiac, Cancer | shared |
| 7 | E101837-block0007 | Zodiac, Leo | shared |
| 8 | E101837-block0008 | Zodiac, Virgo | shared |
| 9 | E101837-block0009 | Zodiac, Libra | shared |
| 10 | E101837-block0010 | Zodiac, Scorpio | shared |
| 11 | E101837-block0011 | Zodiac, Sagittarius | shared |
| 12 | E101837-block0012 | Zodiac, Capricorn | shared |
| 13 | E101837-block0013 | Zodiac, Aquarius | shared |
| 14 | E101837-block0014 | Zodiac, Pisces | shared |
| 15 | E101837-block0015 | New Series | shared |

Batches 16–5,000 are registered via the Story Editor or `manage_blocks.php` as NFTs are minted.

---

## Common Operations

### Edit a Story
1. Go to https://rarefolio.io/admin/story-editor.php (Basic Auth)
2. Select the block from the dropdown
3. Select **Shared** or **Item 1–8** pill
4. Click **↑ Load** to pull current content
5. Edit HTML in the left pane (preview updates live on the right)
6. Click **↓ Save**, changes are live immediately

### Register a New Block (batch 16+)
Use the **+ Register New Block** button in the Story Editor, or POST directly:
```
POST https://rarefolio.io/api/admin/manage_blocks.php
Content-Type: application/json
Body: {
  "barSerial":  "E101837",
  "batchNum":   16,
  "folderSlug": "scnft_zodiac_leo",
  "label":      "Zodiac, Leo",
  "storyMode":  "shared"
}
```
`storyMode`: `"shared"` = one story for the block · `"per_item"` = shared + up to 8 item stories

### Issue a Certificate
```
POST https://rarefolio.io/api/admin/issue_cert.php
Content-Type: application/json
Body: {
  "bar_serial":   "E101837",
  "cnft_id":      "qd-silver-0000009",
  "holder_name":  "Collector Name",
  "template":     "parchment",
  "sealColor":    "gold"
}
```
- `template`: `"parchment"` (warm brown/gold) or `"cream"` (navy/silver)
- `sealColor`: `"gold"`, `"red"`, or `"blue"`
- Cert ID returned: `QDCERT-E101837-0000009`
- PDF stored at: `/home/rarefolio/rf_storage/pdfs/`
- Idempotent, returns existing cert if already issued

### Deploy Files
1. Edit files locally
2. FTP changed files to BlueHost `public_html/`
3. No build step, upload and it's live

**CRITICAL, Editing `.htaccess` on Windows:**
Never use PowerShell `Set-Content` or `[System.Text.Encoding]::UTF8`, both add a BOM that breaks Apache site-wide.
Always use:
```powershell
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
$content = [System.IO.File]::ReadAllText($path, $utf8NoBom)
# ... edit $content ...
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)
# Verify first byte is 35 (#), never 239 (BOM):
$bytes = [System.IO.File]::ReadAllBytes($path)
"First byte: $($bytes[0])"
```

### Run seed_blocks.php (re-seed Bar I stories)
Hit `https://rarefolio.io/api/admin/seed_blocks.php` in a browser, Basic Auth prompt will appear.
Requires `assets/stories/block01/items.html` and `block03/items.html` on the server (restore from git if needed).
Safe to re-run, uses `ON DUPLICATE KEY UPDATE`.

### Verify a Certificate
Public URL: `https://rarefolio.io/verify?cert=QDCERT-E101837-0000009`

### Download a Certificate PDF
`https://rarefolio.io/download.php?cert=QDCERT-E101837-0000009`

---

## DB Tables

| Table | Purpose |
|---|---|
| `qd_blocks` | One row per batch per bar. Maps batch → folder slug, label, story mode |
| `qd_stories` | Story HTML fragments. `item_num` NULL = shared, 1–8 = per-item |
| `qd_certificates` | Cert records, cert_id, payload JSON, PDF metadata |
| `qd_artist_applications` | Artist application submissions |

SQL schemas in `api/BLOCKS_DB_SCHEMA.sql`, `api/CERT_DB_SCHEMA.sql`, `api/ARTIST_APP_DB_SCHEMA.sql`.

---

## Image Asset Locations

| Asset | Path |
|---|---|
| Collection images | `assets/img/collection/{folderSlug}/` |
| Cert backgrounds | `assets/img/certs/bg-parchment_01–04.jpg`, `bg-cream_01–02.jpg` |
| Cert wax seals | `assets/img/certs/wax-seal-gold_01–08.png`, `-red_09–14.png`, `-blue_15–20.png` |
| NFT placeholder | `assets/img/nfts/sys/placeholder.jpg` |
| Site logo | `assets/img/rf_logo_site.png` |

---

## Social / External Links

| Platform | Short link | Full URL |
|---|---|---|
| X (Twitter) | https://qdls.io/qdcnft-x | https://x.com/Rarefolioio |
| Discord | https://qdls.io/qdcnft-d | https://discord.gg/JZ8UrzujHK |
| Facebook | https://qdls.io/qdcnft-fb | https://fb.com/ |

---

## Post-Launch Remaining Tasks

- [ ] Cert pipeline test, issue parchment + cream cert, verify, download PDF
- [ ] Collection walkthrough, all 15 batches, sub-pages, NFT detail, story panels
- [ ] Cross-browser + mobile spot-check (Chrome, Firefox, mobile)
- [ ] Write per-item stories for Aries (block0003, items 1–8)
- [ ] Register blocks 16–5,000 for Bar I (scriptable with curl to manage_blocks.php)
- [ ] Build Silver Bar II and III collection pages (currently Coming Soon)
