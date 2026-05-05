
# scnft_founders, Silver Bar I / Block 88 / Founders

# Artwork directory for the Rarefolio Founders collection.
H:\01_RareFolio.io\01_nft_collections\02_cnft_blocks_of_8\04_1set-8_founders_series_blk88

## Expected files
Eight card images (one per CNFT), named to match `rarefolio_token_id`:

- `qd-silver-0000705.jpg`, Founders #1, The Archivist
- `qd-silver-0000706.jpg`, Founders #2, The Cartographer
- `qd-silver-0000707.jpg`, Founders #3, The Sentinel
- `qd-silver-0000708.jpg`, Founders #4, The Artisan
- `qd-silver-0000709.jpg`, Founders #5, The Scholar
- `qd-silver-0000710.jpg`, Founders #6, The Ambassador
- `qd-silver-0000711.jpg`, Founders #7, The Mentor
- `qd-silver-0000712.jpg`, Founders #8, The Architect

| Token   | Website field                                                |
| ------- | ------------------------------------------------------------ |
| 0000706 | `https://rarefolio.io/nft?nft=qd-silver-0000705&bar=E101837` |
| 0000706 | `https://rarefolio.io/nft?nft=qd-silver-0000706&bar=E101837` |
| 0000707 | `https://rarefolio.io/nft?nft=qd-silver-0000707&bar=E101837` |
| 0000708 | `https://rarefolio.io/nft?nft=qd-silver-0000708&bar=E101837` |
| 0000709 | `https://rarefolio.io/nft?nft=qd-silver-0000709&bar=E101837` |
| 0000710 | `https://rarefolio.io/nft?nft=qd-silver-0000710&bar=E101837` |
| 0000711 | `https://rarefolio.io/nft?nft=qd-silver-0000711&bar=E101837` |
| 0000712 | `https://rarefolio.io/nft?nft=qd-silver-0000712&bar=E101837` |

## Format
- **Web card image** (required): `qd-silver-NNNNNNN.jpg`, ~1600×2000 px, sRGB, quality 85.
- **Print-ready CMYK** (recommended): `qd-silver-NNNNNNN_4000.png` + `_8000.png`, CMYK, lossless, for certificate PDF generation.
- **IPFS CID**: pinned via Pinata / nft.storage, referenced in the CIP-25 metadata.

## Render path

The site resolves these files via `blockImageCandidates()` in `qd-wire.js`:

```
/assets/img/collection/scnft_founders/qd-silver-NNNNNNN.jpg
```

A missing file falls back to `/assets/img/nfts/sys/placeholder.jpg`.

## Related

- Stories: `assets/stories/block88/qd-silver-NNNNNNN.html`
- DB row: `qd_blocks.block_id = 'block88'`, `folder_slug = 'scnft_founders'`
- Token rows (marketplace): `qd_tokens.collection_slug = 'silverbar-01-founders'`










What still needs to happen before the Founders launch

These require artwork first, nothing can move without the 8 JPGs:

1. Drop artwork → assets/img/collection/scnft_founders/qd-silver-0000705.jpg … 0000712.jpg + manifest.json (already created)
2. Deploy → push to main, auto-deploys
3. Preprod mint → follow PHASE_MAINNET_LAUNCH.md Phase 1
4. Mainnet mint → Phase 2
5. Founder purchase + cert issuance → Phase 3 (founders_cert_issue_commands.sh)
6. Secondary listings → Phase 4
7. Also needed on server: run seed_blocks.php to migrate the original 15 static blocks into the DB (batches 1–15)


I need some things clarified: in your data what are the deliverables when someone buys a NFT?

You want a - **Web card image** (required):  `qd-silver-NNNNNNN.jpg` , ~1600×2000 px, sRGB, quality 85.



























