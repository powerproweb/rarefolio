-- =============================================================================
--  Rarefolio Main Site — Founders Block 88 seed (qd_blocks)
-- =============================================================================
--  Registers Block 88 ("Founders") so the /collection/silverbar-01/founders
--  URL resolves through api/blocks/resolve.php when loaded in collections/block.php.
--
--  Re-run safe: ON DUPLICATE KEY UPDATE on the (bar_serial, batch_num) unique
--  key keeps this idempotent.
--
--  Depends on: BLOCKS_DB_SCHEMA.sql
-- =============================================================================

INSERT INTO qd_blocks
    (block_id, bar_serial, batch_num, folder_slug, label, story_mode)
VALUES
    ('block88', 'E101837', 89, 'scnft_founders', 'Founders', 'per_item')
ON DUPLICATE KEY UPDATE
    block_id    = VALUES(block_id),
    folder_slug = VALUES(folder_slug),
    label       = VALUES(label),
    story_mode  = VALUES(story_mode),
    updated_at  = CURRENT_TIMESTAMP;

-- -----------------------------------------------------------------------------
-- Verification
-- -----------------------------------------------------------------------------
-- SELECT * FROM qd_blocks WHERE block_id = 'block88';
-- Expected: 1 row, bar_serial='E101837', batch_num=89, label='Founders'.
