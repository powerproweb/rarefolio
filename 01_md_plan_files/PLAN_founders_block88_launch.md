# Rarefolio Founders Block 88, Launch Plan

## Objective
Create Block 88 on Silver Bar I as the "Founders" collection. Mint 8 CNFTs. The founder personally purchases all 8 to prove the chain end-to-end and bootstrap the secondary market with provenance from day one.

## Block 88 Identity
- Collection name: **Founders**
- Block ID: `block88`
- Batch number: `89`
- Bar serial: `E101837` (Silver Bar I)
- Collection slug: `silverbar-01-founders`
- Folder slug (artwork): `scnft_founders`
- Story mode: `per_item` (8 unique lore articles)
- Story location: `assets/stories/block88/`

## CNFT Range
The 8 tokens that make up the Founders collection:
- `qd-silver-0000705`
- `qd-silver-0000706`
- `qd-silver-0000707`
- `qd-silver-0000708`
- `qd-silver-0000709`
- `qd-silver-0000710`
- `qd-silver-0000711`
- `qd-silver-0000712`

## Certificate Range
- `QDCERT-E101837-0000705`
- `QDCERT-E101837-0000706`
- `QDCERT-E101837-0000707`
- `QDCERT-E101837-0000708`
- `QDCERT-E101837-0000709`
- `QDCERT-E101837-0000710`
- `QDCERT-E101837-0000711`
- `QDCERT-E101837-0000712`

All 8 served via the DB-driven `api/cert/index.php` path. Deterministic template + seal rotation gives each certificate a distinct visual combo.

## URL Routing
- Collection page: `/collection/silverbar-01/founders?batch=89`
- NFT detail: `/nft.html?nft=qd-silver-0000705&bar=E101837&set=1&batch=89&col=/collection/silverbar-01/founders`
- Cert page: `/cert.html?cert=QDCERT-E101837-0000705`
- Verify page: `/verify.html?cert=QDCERT-E101837-0000705&cnft=qd-silver-0000705`

## Naming Decision
Block 88 is the new "Founders" block. Block 01 (Inventors Guild) reverts to being labeled simply "Inventors Guild Prelaunch."

Global copy pass:
- "Founders Prelaunch" → "Founders Collection" on any current button/copy that refers to the inventor's prelaunch
- "Join the Founders" CTA on new homepage points to the Founders page
- Nav dropdown item "Prelaunch: Inventors Guild (Block 01)" stays but loses "Founders" framing
- Add new nav dropdown item "Founders Collection (Block 88)" pointing at the Founders page
- Top-right `qd-prelaunch-cta` button re-aimed at the Founders collection

## Homepage Updates (index.html, Vault Entrance)
- **Section 3 "Now Exhibiting"**: swap spotlight from Taurus Zodiac to Founders (Block 88)
- **Section 5 Collector Pathway "Step III · Collect"**: CTA becomes "View Founders Collection" pointing at Block 88
- **Section 6 Archive Preview**: include 2 Founders pieces in the 6-tile strip (rotate one Taurus and one Aries out)
- **Hero sub-copy**: replace "Founders Prelaunch" mention with "Founders Collection, Live"

## Database Work

### `qd_blocks`
One new row registering Block 88:
- `bar_serial`: `E101837`
- `batch_num`: `89`
- `block_id`: `block88`
- `folder_slug`: `scnft_founders`
- `label`: `Founders`
- `story_mode`: `per_item`

### `qd_tokens`
Eight new rows, one per CNFT. Key fields for each:
- `rarefolio_token_id`: `qd-silver-00007NN`
- `collection_slug`: `silverbar-01-founders`
- `title`: `Founders #NN`
- `character_name`: per item (lore-dependent)
- `edition`: `1/8` through `8/8`
- `primary_sale_status`: starts at `unminted` → transitions to `minted` → `sold` as the test-purchase happens
- `custody_status`: starts at `platform` → flips to `external` after transfer to founder wallet
- `secondary_eligible`: `1`
- `cip25_json`: built via the existing CIP-25 validator, includes `bar_serial = E101837`

## Certificate Work
Seed 8 rows in `qd_certificates` via the `api/cert/index.php` DB path. Certificate numbers 0000705–0000712 each get a deterministic template + seal combo. PDFs generated once via Dompdf and stored immutably outside the webroot.

## Artwork
- Directory: `assets/img/collection/scnft_founders/`
- Files: `qd-silver-0000705.jpg` through `qd-silver-0000712.jpg`
- Print-ready 4000× and 8000× CMYK PNGs per the existing certificate pipeline convention
- QR codes per cert (standard pipeline)

## Story Content
Directory: `assets/stories/block88/`

One HTML file per CNFT (per_item mode):
- `qd-silver-0000705.html`
- `qd-silver-0000706.html`
- `qd-silver-0000707.html`
- `qd-silver-0000708.html`
- `qd-silver-0000709.html`
- `qd-silver-0000710.html`
- `qd-silver-0000711.html`
- `qd-silver-0000712.html`

## Marketplace Bootstrap Flow

### Step 1, Mint on Cardano preprod
Mint all 8 Founders CNFTs through the marketplace admin dashboard + Node sidecar against Blockfrost preprod. Verify every `mint_tx_hash` lands and the `mint-complete` webhook reaches `uploads/webhook-log/mint-complete.log` on the main site.

### Step 2, Mint on Cardano mainnet
Switch `BLOCKFROST_NETWORK=mainnet` in marketplace `.env`. Mint the same 8 CNFTs with identical `rarefolio_token_id`s on mainnet. Policy and network differ; asset names stay the same.

### Step 3, Founder purchase
Founder wallet purchases all 8 at primary-sale price. `primary_sale_status` flips to `sold`, `custody_status` flips to `external`, `current_owner_wallet` set to founder address.

### Step 4, Secondary listing
List all 8 at founder-set prices. `listing_status` flips to `listed_fixed`. `GET /api/v1/listings` starts returning them. Rarefolio secondary market is live.

### Step 5, First secondary sale
One or more Founders pieces sell through the marketplace to real collectors. The `ownership-change` webhook fires. Royalty ledger records the 8% creator / 2.5% platform / seller-net split. Full-cycle validation complete.

## Founder Disclosure Copy (Founders Collection Page)
Opening paragraph on `/collection-silverbar-01-founders.html`:

> *The Founders collection is the first eight CNFTs in the Rarefolio Founders block, purchased by the founder at mint to bootstrap the secondary market and prove every link of the chain, mint, ownership, transfer, royalty settlement, against real collectors. They enter the secondary market with public provenance from day one.*

## Deliverables
1. SQL INSERT for the `qd_blocks` Block 88 row
2. SQL INSERT for the 8 `qd_tokens` Founders rows with full CIP-25 JSON payloads
3. SQL INSERT for the 8 `qd_certificates` rows with deterministic template/seal assignments
4. New page `collection-silverbar-01-founders.html` (or rely on the generic PHP template at `/collection/silverbar-01/founders`)
5. Copy updates across `index.html`, `verify.html`, `nft.html`, and every nav dropdown
6. Re-aim of the `qd-prelaunch-cta` button to the Founders page
7. Artwork directory + 8 CNFT images in `assets/img/collection/scnft_founders/`
8. Story directory + 8 lore HTML files in `assets/stories/block88/`
9. `PHASE_MAINNET_LAUNCH.md` runbook covering the full preprod → mainnet sequence with exact Blockfrost calls and verification steps
10. Homepage Featured Exhibition swap (Taurus → Founders) + Archive Preview retile

## References
- Main site: `index.html`, `verify.html`, `nft.html`, `collection-silverbar-01.html`
- Cert pipeline: `api/cert/index.php`, `api/admin/issue_cert.php`
- Block resolution: `api/blocks/resolve.php`, `api/blocks/story.php`
- Collection template: `collections/block.php`
- Marketplace admin: `01a_rarefolio_marketplace/admin/mint.php`, `admin/mint-new.php`
- Marketplace API: `01a_rarefolio_marketplace/api/v1/` (tokens, bars, listings)
- Webhook receivers: `api/webhook/mint-complete.php`, `api/webhook/ownership-change.php`
- Schema: `qd_tokens`, `qd_blocks`, `qd_certificates`, `qd_mint_queue`, `royalty_ledger`
