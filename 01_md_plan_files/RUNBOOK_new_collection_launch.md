# New Collection Launch, Zero-Code Runbook
**For any future collection on Rarefolio (batch 16+ on any bar).**
No PHP, JS, or HTML edits required.

---

## Pre-flight checklist
- [ ] Artwork JPGs ready: `qd-silver-NNNNNNN.jpg` (7-digit, zero-padded)
- [ ] Folder slug decided: e.g. `scnft_my_series`
- [ ] Label decided: e.g. `My Series, Subtitle`
- [ ] Story mode decided: `shared` (one story for whole block) or `per_item` (8 distinct stories)
- [ ] If `per_item`: character names for items 1–8 ready

---

## Step 1, Drop artwork
Place the 8 card JPGs in `assets/img/collection/<folder_slug>/`.

If the `qd-mini-strip` preview component is used on any content page pointing at this folder,
also drop a `manifest.json` in the same directory:

```json
{
  "images": [
    "qd-silver-NNNNNNN.jpg",
    ...8 filenames total...
  ]
}
```

---

## Step 2, Register the block in the Story Editor
Open: `https://rarefolio.io/admin/story-editor.php` (Basic Auth)

1. Click **"+ Register New Block"**
2. Fill in:
   - **Bar Serial**, e.g. `E101837`
   - **Batch #**, the batch number (e.g. `90`)
   - **Folder Slug**, e.g. `scnft_my_series`
   - **Label**, e.g. `My Series, Subtitle`
   - **Story Mode**, `Shared only` or `Per-item (shared + 8 items)`
3. If `Per-item`, the **Character Names** grid appears, fill in all 8 names.
   These are shown on collection cards and the NFT detail page.
4. Click **Register Block**

The block is now live. The collection grid will resolve it from the DB for any visitor.

---

## Step 3, Write stories
Still in the Story Editor:

1. Select the new block from the **Block** dropdown
2. Select **Shared** pill → write the collection overview → click **Save**
3. If `per_item`: select **Item 1** through **Item 8** pills → write each lore entry → **Save** each

Story HTML format (plain fragments, no `<html>/<body>` wrapper):
```html
<h3>Collection Name, Item Title</h3>
<p class="lead">Subtitle / tagline.</p>
<p>Main lore paragraph.</p>
<p><em>Collection Name, Bar Label, Edition N of 8.</em></p>
```

---

## Step 4, Verify
Open the collection page and navigate to the new batch number:
```
https://rarefolio.io/collection-silverbar-01.html?batch=<batch_num>
```

Check:
- [ ] Batch label shows the correct block ID and collection label
- [ ] All 8 card images render (or fallback placeholder shows)
- [ ] Click a card → NFT detail page shows the correct title/character name
- [ ] Story panel loads the collection story
- [ ] If `per_item`: click individual NFTs and verify each item story

---

## Step 5, Certificates (after preprod mint confirms)
Issue certs via `POST /api/admin/issue_cert.php` for each CNFT.
See `FOUNDERS_BLOCK88_SEED_README.md` → **Step 3** for the full cert issuance payload reference.

---

## Run order for DB migrations (one-time, already done on this install)
1. `api/BLOCKS_DB_SCHEMA.sql`, creates `qd_blocks` and `qd_stories`
2. `api/sql/migrate_add_character_names.sql`, adds `character_names` column
3. `api/sql/seed_character_names.sql`, backfills Inventors, Robot Butler, Founders

---

## Reference
- Story Editor: `admin/story-editor.php`
- Block API: `api/blocks/resolve.php`, `api/admin/manage_blocks.php`
- Story API: `api/blocks/story.php`, `api/admin/manage_stories.php`
- Cert issuance: `api/admin/issue_cert.php`
- Architecture: `01_md_plan_files/ARCHITECTURE.md`
- Founders example: `01_md_plan_files/PLAN_founders_block88_launch.md`
