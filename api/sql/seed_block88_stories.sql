-- =============================================================================
--  Rarefolio Main Site — Founders Block 88 seed (qd_stories)
-- =============================================================================
--  Seeds the 8 per-item lore HTML fragments and 1 shared collection overview
--  for Block 88 ("Founders"). Served via api/blocks/story.php.
--
--  Re-run safe: ON DUPLICATE KEY UPDATE on (block_id, item_num).
--
--  Depends on: BLOCKS_DB_SCHEMA.sql
-- =============================================================================

-- ---- Shared collection overview (item_num = NULL) ----------------------------
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', NULL,
     '<p><em>The Rarefolio Founders collection is the first eight pieces of Block 88, anchored to Silver Bar I (Serial E101837). Purchased by the founder at mint to bootstrap the secondary market and prove every link of the chain — mint, ownership, transfer, royalty settlement — against real collectors. Each piece enters the permanent archive with public provenance from day one.</em></p><p>Eight archetypes. One ledger. A permanent record of how Rarefolio began.</p>'
    )
ON DUPLICATE KEY UPDATE
    html_content = VALUES(html_content),
    updated_at   = CURRENT_TIMESTAMP;

-- ---- Per-item stories (item_num 1..8) ----------------------------------------
-- qd-silver-0000705 → item 1 → The Archivist
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 1,
     '<h3>Founders #1 — The Archivist</h3><p class="lead">Keeper of the First Ledger.</p><p>Before a vault can hold anything of value, someone must decide what to record and how. The Archivist draws the first line in the ledger — the act that turns a bar of silver into a named, traceable, permanent thing.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 1 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000706 → item 2 → The Cartographer
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 2,
     '<h3>Founders #2 — The Cartographer</h3><p class="lead">Drafter of the Vault Map.</p><p>Every collection needs an atlas. The Cartographer charts the territory of the archive: which bar, which block, which edition, which serial — and how a future collector will find their way back to the beginning.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 2 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000707 → item 3 → The Sentinel
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 3,
     '<h3>Founders #3 — The Sentinel</h3><p class="lead">Warden of the Inaugural Seal.</p><p>The Sentinel stands at the threshold between intent and permanence. When a piece is minted, signed, and sealed, the Sentinel has already decided it is worthy of the archive.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 3 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000708 → item 4 → The Artisan
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 4,
     '<h3>Founders #4 — The Artisan</h3><p class="lead">Forger of the Foundational Die.</p><p>Every piece carries the shape of the one who made the mold. The Artisan carves the die — the deterministic logic that turns an idea into a consistent, repeatable piece of the permanent collection.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 4 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000709 → item 5 → The Scholar
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 5,
     '<h3>Founders #5 — The Scholar</h3><p class="lead">Historian of the First Provenance.</p><p>Provenance is not a feature. It is a discipline. The Scholar writes down where every piece came from, who owned it, and how it moved — so that a century from now, the chain of custody still reads as a single, continuous narrative.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 5 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000710 → item 6 → The Ambassador
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 6,
     '<h3>Founders #6 — The Ambassador</h3><p class="lead">Emissary of the Original Charter.</p><p>The Ambassador carries the charter outward. Every early collector who trusts the archive with their wallet gets a direct line back to the Ambassador — the promise that the charter will be honored for as long as the collection exists.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 6 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000711 → item 7 → The Mentor
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 7,
     '<h3>Founders #7 — The Mentor</h3><p class="lead">Steward of the Collector''s Path.</p><p>The Mentor walks new collectors through Discover, Study, and Collect. Not a salesperson. A guide. The one who explains why the bar serial matters, why the edition number matters, and why the long horizon matters most of all.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 7 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- qd-silver-0000712 → item 8 → The Architect
INSERT INTO qd_stories (block_id, item_num, html_content) VALUES
    ('block88', 8,
     '<h3>Founders #8 — The Architect</h3><p class="lead">Builder of the Permanent Vault.</p><p>The final Founder. The Architect draws the walls of the vault itself — the infrastructure, the schema, the policies that make a permanent collection physically possible on Cardano and off-chain alike.</p><p><em>Rarefolio Founders, Silver Bar I, Edition 8 of 8.</em></p>'
    )
ON DUPLICATE KEY UPDATE html_content = VALUES(html_content), updated_at = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Verification
-- -----------------------------------------------------------------------------
-- SELECT item_num, LEFT(html_content, 60) AS preview
-- FROM qd_stories WHERE block_id = 'block88'
-- ORDER BY (item_num IS NULL) DESC, item_num;
-- Expected: 9 rows (1 shared NULL + 8 per-item 1..8).
