-- QuantumDrive Certificates (DB + Disk)
-- Run in phpMyAdmin against your QuantumDrive database.

CREATE TABLE IF NOT EXISTS qd_certificates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cert_id VARCHAR(64) NOT NULL,
  bar_serial VARCHAR(32) NOT NULL,
  cnft_id VARCHAR(64) NOT NULL,
  cnft_num CHAR(7) NOT NULL,
  status ENUM('verified','unverified','revoked') NOT NULL DEFAULT 'verified',
  template ENUM('parchment','cream') NOT NULL DEFAULT 'parchment',

  payload_json JSON NOT NULL,
  payload_sha256 CHAR(64) NOT NULL,

  -- Disk artifact reference (outside webroot)
  pdf_storage_key VARCHAR(128) DEFAULT NULL,
  pdf_sha256 CHAR(64) DEFAULT NULL,
  pdf_bytes BIGINT UNSIGNED DEFAULT NULL,

  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  generator_version VARCHAR(32) NOT NULL DEFAULT 'v1',

  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_cert_id (cert_id),
  KEY idx_cnft_id (cnft_id),
  KEY idx_bar_serial (bar_serial),
  KEY idx_status (status),
  KEY idx_issued_at (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
