-- =============================================================================
--  Rarefolio Main Site — Placeholder seed for bar E101837, batches 1–5000
-- =============================================================================
--  Pre-populates qd_blocks with 5,000 [TBD] rows so every block_id appears in
--  the Story Editor dropdown (admin/story-editor.php). Each placeholder row is:
--
--    block_id        = 'E101837-block{NNNN}'     (4-digit zero-padded batch num)
--    bar_serial      = 'E101837'
--    batch_num       = 1..5000
--    folder_slug     = 'tbd_block{NNNN}'
--    label           = '[TBD] block{NNNN}'
--    story_mode      = 'shared'
--    character_names = NULL
--
--  WHY:
--    The Story Editor's block dropdown is populated by
--      SELECT block_id, bar_serial, batch_num, label, story_mode
--        FROM qd_blocks ORDER BY bar_serial, batch_num;
--    (admin/story-editor.php lines 37–40). It has no LIMIT and no pagination,
--    so whatever rows exist in qd_blocks are exactly what you can edit. Running
--    this script makes all 5,000 slots visible and editable immediately — you
--    can open any block and start writing stories without pre-registering.
--
--  SAFETY:
--    * Uses INSERT IGNORE keyed off the PRIMARY KEY (block_id) AND the unique
--      key uq_bar_batch (bar_serial, batch_num). Any row already present is
--      left 100% untouched — no UPDATE, no overwrite. This means:
--         - Real seeded blocks from seed_blocks.php (batches 1–15)   -> untouched
--         - Founders row from seed_block88_blocks.sql (batch 89)     -> untouched
--         - Any row authored via manage_blocks.php or the editor's
--           "+ Register New Block" panel                              -> untouched
--         - A previous run of THIS script                              -> untouched
--    * Fully idempotent — safe to re-run to top up any gaps after manual adds.
--    * Does NOT touch qd_stories. Placeholder blocks start story-empty; the
--      editor will happily save a first story against them.
--
--  PROMOTING A PLACEHOLDER LATER:
--    When block 47 becomes, say, "Mythic Beasts — Phoenix," POST to
--    /api/admin/manage_blocks.php with the real folder_slug / label /
--    story_mode / character_names. Its ON DUPLICATE KEY UPDATE clause
--    (see api/admin/manage_blocks.php lines 82–87) will overwrite the [TBD]
--    values cleanly in place.
--
--  MYSQL COMPATIBILITY:
--    The row-generator is a 4-level CROSS JOIN of a 0–9 digit table, so it
--    works on MySQL 5.7 / 5.6 / 8.0+ / MariaDB without needing recursive CTEs
--    or a session variable tweak for cte_max_recursion_depth.
--
--  HOW TO RUN:
--    Upload this file, then execute in phpMyAdmin against the Rarefolio DB
--    (rarefolio_cnftcert). The three SELECTs at the bottom print a before /
--    after count and a real-vs-placeholder breakdown.
--
--  DEPENDS ON: BLOCKS_DB_SCHEMA.sql
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Pre-flight: row count before
-- -----------------------------------------------------------------------------
SELECT COUNT(*) AS rows_before
  FROM qd_blocks
 WHERE bar_serial = 'E101837';

-- -----------------------------------------------------------------------------
-- Placeholder seed (5,000 rows; existing rows are skipped)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO qd_blocks
    (block_id, bar_serial, batch_num, folder_slug, label, story_mode, character_names)
SELECT
    CONCAT('E101837-block', LPAD(nums.n, 4, '0'))   AS block_id,
    'E101837'                                       AS bar_serial,
    nums.n                                          AS batch_num,
    CONCAT('tbd_block', LPAD(nums.n, 4, '0'))       AS folder_slug,
    CONCAT('[TBD] block', LPAD(nums.n, 4, '0'))     AS label,
    'shared'                                        AS story_mode,
    NULL                                            AS character_names
  FROM (
        SELECT a.N + b.N*10 + c.N*100 + d.N*1000 + 1 AS n
          FROM       (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
          CROSS JOIN (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
          CROSS JOIN (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c
          CROSS JOIN (SELECT 0 N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d
       ) nums
 WHERE nums.n <= 5000
 ORDER BY nums.n;

-- -----------------------------------------------------------------------------
-- Verification
-- -----------------------------------------------------------------------------

-- 1) Total rows for bar E101837 after the run (should be 5000 if this is a
--    fresh DB, or 5000 regardless once any gaps are topped up — there are no
--    rows outside 1..5000 expected).
SELECT COUNT(*) AS rows_after_total
  FROM qd_blocks
 WHERE bar_serial = 'E101837';

-- 2) Breakdown of real vs placeholder rows (by label prefix).
SELECT
    SUM(CASE WHEN label LIKE '[TBD]%'     THEN 1 ELSE 0 END) AS placeholder_rows,
    SUM(CASE WHEN label NOT LIKE '[TBD]%' THEN 1 ELSE 0 END) AS real_rows
  FROM qd_blocks
 WHERE bar_serial = 'E101837';

-- 3) Spot-check a handful of rows across the range.
SELECT block_id, batch_num, label, story_mode
  FROM qd_blocks
 WHERE bar_serial = 'E101837'
   AND batch_num IN (1, 15, 16, 89, 90, 2500, 5000)
 ORDER BY batch_num;
