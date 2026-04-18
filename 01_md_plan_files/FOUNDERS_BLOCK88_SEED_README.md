# Founders Block 88 — Seed Run Guide
This guide covers the database and file-structure pieces that were landed for
the Founders Block 88 launch. Apply these once per environment (dev, staging,
production). All SQL is idempotent, so re-running is safe.
## Files that were added
### Main site (`01_rarefolio.io`)
- `api/sql/seed_block88_blocks.sql` — 1 row in `qd_blocks`
- `api/sql/seed_block88_stories.sql` — 1 shared + 8 per-item rows in `qd_stories`
- `assets/img/collection/scnft_founders/README.md` — artwork drop-in directory
- `assets/stories/block88/shared.html` — shared fallback HTML
- `assets/stories/block88/qd-silver-0000705.html` .. `0712.html` — 8 per-item fallbacks
### Marketplace (`01a_rarefolio_marketplace`)
- `db/migrations/007_seed_founders_block88_tokens.sql` — 8 rows in `qd_tokens`
## Run order
### Step 1 — Main site database
Apply the two SQL files against your Rarefolio main-site database (the one
`api/_config.php` connects to):
```powershell
mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME> < api\sql\seed_block88_blocks.sql
mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME> < api\sql\seed_block88_stories.sql
```
Or run them through phpMyAdmin by opening each file and clicking "Go."
### Step 2 — Marketplace database
Apply the token seed. The marketplace ships a migration runner:
```powershell
cd M:\01_Warp_Projects\01_projects\01a_rarefolio_marketplace
php db\migrate.php
```
The runner will pick up `007_seed_founders_block88_tokens.sql` alongside the
existing six migrations. If migrations have already been applied, re-running
is still safe thanks to `ON DUPLICATE KEY UPDATE`.
### Step 3 — Artwork drop
Place the 8 card images into `assets/img/collection/scnft_founders/`:
- `qd-silver-0000705.jpg` — Founders #1 — The Archivist
- `qd-silver-0000706.jpg` — Founders #2 — The Cartographer
- `qd-silver-0000707.jpg` — Founders #3 — The Sentinel
- `qd-silver-0000708.jpg` — Founders #4 — The Artisan
- `qd-silver-0000709.jpg` — Founders #5 — The Scholar
- `qd-silver-0000710.jpg` — Founders #6 — The Ambassador
- `qd-silver-0000711.jpg` — Founders #7 — The Mentor
- `qd-silver-0000712.jpg` — Founders #8 — The Architect
See the directory's own `README.md` for recommended resolutions and the
print-ready CMYK PNG conventions.
### Step 4 — Verify wiring
Open in a browser:
```
http://<host>/collection/silverbar-01/founders?batch=89
```
Expected:
- Page renders under the generic `collections/block.php` template
- Heading shows "Founders"
- Eight card slots render (placeholder images until Step 3 completes)
- Clicking a card opens `nft.html` with the correct CNFT ID in the URL
- The per-item story loads from either `qd_stories` (DB) or the static HTML fallback
Marketplace API spot-check:
```powershell
curl http://<market-host>/api/v1/tokens/qd-silver-0000705
```
Expected response: `ok: true` with `collection: "silverbar-01-founders"`,
`primary_sale_status: "unminted"`, and the full CIP-25 payload in the `chain`
sub-object.
## What this does NOT do
These seeds intentionally stop at the database + filesystem layer. Still to do
in follow-up passes, all covered in `PLAN_founders_block88_launch.md`:
- Homepage copy swap (Taurus → Founders in Section 3 "Now Exhibiting")
- Nav dropdown additions for the Founders collection
- Global retargeting of the `qd-prelaunch-cta` button
- Certificate issuance (via `api/admin/issue_cert.php`, once artwork is in place
  and the preprod mint has confirmed)
- Preprod → mainnet mint sequence (`PHASE_MAINNET_LAUNCH.md` runbook)
## Rollback
Main site:
```sql
DELETE FROM qd_stories WHERE block_id = 'block88';
DELETE FROM qd_blocks  WHERE block_id = 'block88';
```
Marketplace:
```sql
DELETE FROM qd_tokens WHERE collection_slug = 'silverbar-01-founders';
```
Filesystem:
```powershell
Remove-Item -Recurse assets\stories\block88
Remove-Item -Recurse assets\img\collection\scnft_founders
Remove-Item api\sql\seed_block88_blocks.sql
Remove-Item api\sql\seed_block88_stories.sql
Remove-Item M:\01_Warp_Projects\01_projects\01a_rarefolio_marketplace\db\migrations\007_seed_founders_block88_tokens.sql
```
## Related docs
- `01_md_plan_files/PLAN_founders_block88_launch.md` — the overall launch plan
- `README.md` — main site architecture overview
- `../01a_rarefolio_marketplace/docs/API.md` — what the v1 endpoints return
- `../01a_rarefolio_marketplace/docs/CONFIG.md` — env + deployment
