-- Rarefolio Blocks & Stories (DB-driven block routing at scale)
-- Run in phpMyAdmin against your Rarefolio database.
--
-- qd_blocks:  One row per batch per bar. Maps batch → image folder + label + story mode.
-- qd_stories: Story HTML fragments. One shared + up to 8 per-item per block.

CREATE TABLE IF NOT EXISTS qd_blocks (
  block_id    VARCHAR(48)  NOT NULL,
  bar_serial  VARCHAR(32)  NOT NULL,
  batch_num   INT UNSIGNED NOT NULL,
  folder_slug VARCHAR(120) NOT NULL,
  label       VARCHAR(200) NOT NULL,
  story_mode  ENUM('shared','per_item') NOT NULL DEFAULT 'shared',

  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (block_id),
  UNIQUE KEY uq_bar_batch (bar_serial, batch_num),
  KEY idx_bar_serial (bar_serial)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS qd_stories (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  block_id     VARCHAR(48)     NOT NULL,
  item_num     TINYINT UNSIGNED DEFAULT NULL COMMENT 'NULL = shared story, 1-8 = per-item',
  html_content MEDIUMTEXT      NOT NULL,

  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_block_item (block_id, item_num),
  KEY idx_block_id (block_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
