-- Rarefolio Catalog Registry
-- Run in phpMyAdmin against your Rarefolio database.
-- Purpose:
-- - Keep stable catalog numbers for all 3 silver bars
-- - Keep stable catalog numbers for every NFT or FT tracked in admin

CREATE TABLE IF NOT EXISTS qd_catalog_registry (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Stable public-facing catalog number, examples:
  -- RF-SLVBAR-05-2026-100oz-E101837-01
  -- RF-GEN-05-2026-B01-0000001
  -- RF-FND-05-2026-B88-0000001
  catalog_no VARCHAR(96) NOT NULL,

  record_type ENUM('silver_bar','nft','ft') NOT NULL,

  -- For silver_bar rows this is 1, 2, or 3.
  -- For token rows, this is resolved from bar_serial when known.
  bar_number TINYINT UNSIGNED DEFAULT NULL,
  bar_serial VARCHAR(32) DEFAULT NULL,

  -- Token identifiers are null for silver_bar rows.
  token_id VARCHAR(96) DEFAULT NULL,
  cert_id VARCHAR(64) DEFAULT NULL,
  title VARCHAR(255) DEFAULT NULL,

  source ENUM('seed','cert_sync','webhook_sync','manual') NOT NULL DEFAULT 'manual',
  notes TEXT DEFAULT NULL,

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_no (catalog_no),
  UNIQUE KEY uq_token_id (token_id),
  KEY idx_record_type (record_type),
  KEY idx_bar_number (bar_number),
  KEY idx_bar_serial (bar_serial),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
