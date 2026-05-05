-- =============================================================================
--  Rarefolio, Seed: character_names for existing per-item blocks
-- =============================================================================
--  Run AFTER migrate_add_character_names.sql.
--
--  Populates the character_names column for the three blocks that previously
--  relied on the hardcoded QD_ITEM_NAMES object in qd-wire.js:
--    • E101837-block0002  Steampunk, Inventors Guild  (batch 2)
--    • E101837-block0004  Steampunk, Robot Butler     (batch 4)
--    • block88            Founders Collection          (batch 89)
--
--  Re-run safe: UPDATE only touches rows that already exist.
-- =============================================================================

-- ---- Steampunk, Inventors Guild (batch 2) -----------------------------------
UPDATE qd_blocks
SET
  character_names = '["Miss Nyla Vantress, The Stormglass Prodigy","Elowen Thrice, Mistress of Clockwork Nerves","Clara Penhalwick, The Brassheart Aeronaut","Edmund Vale, The Iron Wit of Gallowmere","Vivienne Sloane, Keeper of the Ember Circuit","Octavius Bellmere, The Grand Old Gearsmith","Thaddeus Crowle, The Furnace Baron","Ludorian Marrow, Architect of the Impossible Hour"]',
  updated_at = CURRENT_TIMESTAMP
WHERE block_id = 'E101837-block0002';

-- ---- Steampunk, Robot Butler (batch 4) --------------------------------------
UPDATE qd_blocks
SET
  character_names = '["Alistair Valecourt","Edmund Aurellian","Theodore Valemont","Lucian Everford","Julian Ashcombe","Reginald Fairbourne","Augustin Wrenhall","Benedict Harrowvale"]',
  updated_at = CURRENT_TIMESTAMP
WHERE block_id = 'E101837-block0004';

-- ---- Founders Collection Block 88 (batch 89) ---------------------------------
UPDATE qd_blocks
SET
  character_names = '["Founders #1, The Archivist","Founders #2, The Cartographer","Founders #3, The Sentinel","Founders #4, The Artisan","Founders #5, The Scholar","Founders #6, The Ambassador","Founders #7, The Mentor","Founders #8, The Architect"]',
  updated_at = CURRENT_TIMESTAMP
WHERE block_id = 'block88';

-- ---- Verification ------------------------------------------------------------
-- SELECT block_id, label, story_mode,
--        LEFT(character_names, 80) AS names_preview
--   FROM qd_blocks
--  WHERE block_id IN ('E101837-block0002', 'E101837-block0004', 'block88')
--  ORDER BY batch_num;
-- Expected: 3 rows, each with a non-NULL names_preview.
