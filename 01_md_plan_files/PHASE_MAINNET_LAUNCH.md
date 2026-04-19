# Founders Block 88 — Preprod → Mainnet Launch Runbook
**Deliverable 9 of `PLAN_founders_block88_launch.md`**
This document covers every step from first preprod mint through first real secondary sale.

---

## Prerequisites
- [ ] BlueHost deploy complete: all files from commits `e191647`…`905fe4d` are live
- [ ] DB migrations run on production:
  - `api/BLOCKS_DB_SCHEMA.sql`
  - `api/sql/seed_block88_blocks.sql`
  - `api/sql/seed_block88_stories.sql`
  - `api/sql/migrate_add_character_names.sql`
  - `api/sql/seed_character_names.sql`
  - Marketplace: `db/migrations/007_seed_founders_block88_tokens.sql`
- [ ] Secrets configured on both sides:
  - Main site `api/_config.php`: `RF_WEBHOOK_SECRET` set to the 32-byte hex secret
  - Marketplace `.env`: `PUBLIC_SITE_WEBHOOK_SECRET` set to the same value
  - Marketplace `.env`: `CORS_ALLOWED_ORIGINS=https://rarefolio.io`
- [ ] Blockfrost preprod key in marketplace `.env`: `BLOCKFROST_PROJECT_ID=preprodXXXXX`
- [ ] Founders artwork dropped: `assets/img/collection/scnft_founders/qd-silver-0000705.jpg` … `0000712.jpg` + `manifest.json`

---

## Phase 1 — Preprod Mint

### 1.1 Set network to preprod
In `01a_rarefolio_marketplace/.env`:
```
BLOCKFROST_NETWORK=preprod
BLOCKFROST_PROJECT_ID=preprod<your_key>
```

### 1.2 Start the marketplace + sidecar
```powershell
cd M:\01_Warp_Projects\01_projects\01a_rarefolio_marketplace
php -S localhost:8080          # or deploy to staging host
node sidecar/index.js          # Node sidecar (separate terminal)
```

### 1.3 Mint all 8 Founders CNFTs
Open marketplace admin dashboard → Mint panel.
For each token `qd-silver-0000705` … `qd-silver-0000712`:
- Confirm `collection_slug = silverbar-01-founders`
- Confirm `primary_sale_status = unminted`
- Click **Mint** → sidecar submits to Blockfrost preprod
- Wait for `mint_tx_hash` to be populated in `qd_tokens`

Bulk mint can also be triggered via curl:
```powershell
curl -s -X POST http://localhost:8080/admin/mint-new.php `
  -H "Content-Type: application/json" `
  -u "qd_admin_legacy:***REDACTED-ROTATED-2026-04-19***" `
  -d '{"collection_slug":"silverbar-01-founders"}'
```

### 1.4 Verify mint-complete webhooks
The sidecar fires `POST https://rarefolio.io/api/webhook/mint-complete.php` for each token.
Check the log on the main site:
```
uploads/webhook-log/mint-complete.log
```
Expected: 8 entries, one per token, with matching `rarefolio_token_id` and `mint_tx_hash`.

Verify via Blockfrost preprod explorer: https://preprod.cardanoscan.io/

### 1.5 Confirm token state
```powershell
curl -s "http://localhost:8080/api/v1/tokens/qd-silver-0000705" | ConvertFrom-Json
```
Expected:
- `primary_sale_status: "minted"`
- `mint_tx_hash`: non-null

---

## Phase 2 — Mainnet Mint

### 2.1 Switch to mainnet
In `01a_rarefolio_marketplace/.env`:
```
BLOCKFROST_NETWORK=mainnet
BLOCKFROST_PROJECT_ID=mainnet<your_key>
```
Restart the sidecar: `node sidecar/index.js`

### 2.2 Fund the minting wallet
Ensure the Cardano mainnet minting wallet has enough ADA to cover:
- Tx fees × 8 (~0.5 ADA each = ~4 ADA minimum)
- Min UTXO per token (~2 ADA each = ~16 ADA minimum)
- Buffer: hold at least 30 ADA in the minting wallet

### 2.3 Mint on mainnet
Same mint process as Phase 1.3 but against the production Blockfrost endpoint.
Policy ID will differ from preprod. Asset names (`qd-silver-0000705` etc.) are the same.

Record each `mint_tx_hash` — these go into the cert payload later.

### 2.4 Verify mainnet mint
Check https://cardanoscan.io/ for each tx hash.
Confirm `uploads/webhook-log/mint-complete.log` has 8 new mainnet entries.

---

## Phase 3 — Founder Purchase (Primary Sale)

### 3.1 Set primary sale price
In the marketplace admin, set `asking_price_ada` on all 8 Founders tokens.

### 3.2 Founder wallet purchases all 8
The founder wallet buys each CNFT through the marketplace purchase flow.

After each purchase:
- `primary_sale_status` → `sold`
- `custody_status` → `external`
- `current_owner_wallet` → founder wallet address

Verify:
```powershell
curl -s "http://localhost:8080/api/v1/tokens/qd-silver-0000705" | ConvertFrom-Json
```
Expected: `primary_sale_status: "sold"`, `custody_status: "external"`.

### 3.3 Issue certificates
Run the 8 cert issuance commands from `api/sql/founders_cert_issue_commands.sh`.
Update each command with the real `txHash` and `contractAddress` from Phase 2.3.

Verify:
```powershell
curl -s "https://rarefolio.io/cert?cert=QDCERT-E101837-0000705" | Select-String "verified"
```

---

## Phase 4 — Secondary Listings

### 4.1 List all 8 at founder-set prices
In the marketplace admin, set `listing_status = listed_fixed` and `listing_price_ada` for each token.

Verify public API:
```powershell
curl -s "https://rarefolio.io/api/v1/listings" | ConvertFrom-Json
```
Expected: 8 listings for `silverbar-01-founders`, all with `listing_status: "listed_fixed"`.

### 4.2 Spot-check collection page
Open: `https://rarefolio.io/collection/silverbar-01/founders?batch=89`
Verify:
- [ ] All 8 cards render with Founders artwork
- [ ] Each card shows the correct archetype name (Archivist, Cartographer, etc.)
- [ ] Story panel loads collection overview
- [ ] Clicking each card opens NFT detail with correct per-item story
- [ ] Cert and verify links visible on each NFT detail page

---

## Phase 5 — First Secondary Sale (Full-Cycle Validation)

When the first real secondary sale occurs:
1. `ownership-change` webhook fires → `api/webhook/ownership-change.php`
2. Verify in `uploads/webhook-log/ownership-change.log`:
   - `old_owner` = founder wallet
   - `new_owner` = buyer wallet
3. Verify `royalty_ledger` row created:
   - `creator_royalty_pct = 8`
   - `platform_fee_pct = 2.5`
   - `seller_net` = correct remainder
4. Verify on Cardano explorer: CNFT transferred to new owner's wallet

Full-cycle validation is complete when step 4 confirms on-chain.

---

## Rollback Notes

**Preprod only**: delete rows and re-mint with corrected metadata.
```sql
UPDATE qd_tokens SET primary_sale_status='unminted', mint_tx_hash=NULL
  WHERE collection_slug='silverbar-01-founders';
```

**Mainnet mint error**: Cardano transactions are immutable. If wrong metadata was minted,
the affected tokens must be burned and re-minted with corrected asset names/metadata.
Document the burn tx hash in the royalty_ledger notes field for provenance.

---

## Reference
- Marketplace `.env` config: `../01a_rarefolio_marketplace/docs/CONFIG.md`
- Marketplace API: `../01a_rarefolio_marketplace/docs/API.md`
- Cert issuance commands: `api/sql/founders_cert_issue_commands.sh`
- Webhook receivers: `api/webhook/mint-complete.php`, `api/webhook/ownership-change.php`
- Collection page: `https://rarefolio.io/collection/silverbar-01/founders?batch=89`
- Blockfrost preprod explorer: `https://preprod.cardanoscan.io/`
- Blockfrost mainnet explorer: `https://cardanoscan.io/`
