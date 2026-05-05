-- =============================================================================
--  Rarefolio, Migration: add character_names to qd_blocks
-- =============================================================================
--  Run once in phpMyAdmin (or via mysql CLI) against the Rarefolio main-site DB.
--
--  Safe to re-run: IF NOT EXISTS guard on the ALTER.
--
--  What it does:
--    Adds a nullable TEXT column `character_names` to qd_blocks.
--    Stored as a JSON-encoded array of up to 8 display names, one per item.
--    NULL for shared-mode blocks (no per-item names needed).
--
--  After running this migration, also run:
--    api/sql/seed_character_names.sql, backfills the 3 existing per-item blocks
-- =============================================================================

SET @col_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'qd_blocks'
    AND COLUMN_NAME  = 'character_names'
);

SET @sql = IF(
  @col_exists = 0,
  'ALTER TABLE qd_blocks
     ADD COLUMN character_names TEXT NULL DEFAULT NULL
     COMMENT ''JSON-encoded array of up to 8 per-item display names. NULL for shared-mode blocks.''
     AFTER story_mode',
  'SELECT ''character_names column already exists, skipped.'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
