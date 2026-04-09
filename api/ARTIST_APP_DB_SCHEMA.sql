-- Rarefolio Showcased Artist Applications
-- Run in phpMyAdmin against your Rarefolio database.

CREATE TABLE IF NOT EXISTS qd_artist_applications (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  app_ref          VARCHAR(32)     NOT NULL COMMENT 'Unique application reference code',

  -- 1. Artist Identity
  full_name        VARCHAR(200)    NOT NULL,
  artist_name      VARCHAR(200)    DEFAULT NULL,
  email            VARCHAR(255)    NOT NULL,
  location         VARCHAR(200)    DEFAULT NULL,
  website          VARCHAR(500)    DEFAULT NULL,
  years_creating   VARCHAR(60)     DEFAULT NULL,
  artist_bio       TEXT            NOT NULL,

  -- 2. Artistic Practice & Vision
  primary_medium   VARCHAR(100)    NOT NULL,
  style_keywords   VARCHAR(300)    DEFAULT NULL,
  artist_statement TEXT            NOT NULL,
  signature_difference TEXT        NOT NULL,
  collector_appeal TEXT            DEFAULT NULL,

  -- 3. Portfolio, Collections & Presentation
  portfolio_url    VARCHAR(500)    NOT NULL,
  social_url       VARCHAR(500)    DEFAULT NULL,
  best_work_links  TEXT            NOT NULL,
  series_info      TEXT            DEFAULT NULL,
  presentation_readiness TEXT      DEFAULT NULL,

  -- 4. Professional Readiness & Fit
  availability     VARCHAR(60)     DEFAULT NULL,
  exclusive_interest VARCHAR(60)   DEFAULT NULL,
  practice_tags    JSON            DEFAULT NULL COMMENT 'Array of selected practice tags',
  why_rarefolio    TEXT            NOT NULL,
  professional_notes TEXT          DEFAULT NULL,

  -- 5. Uploads (stored as relative paths under the uploads directory)
  headshot_path    VARCHAR(500)    DEFAULT NULL,
  portfolio_pdf_path VARCHAR(500)  DEFAULT NULL,
  sample_works_paths JSON         DEFAULT NULL COMMENT 'Array of file paths',
  file_notes       TEXT            DEFAULT NULL,

  -- 6. Consent
  consent_review   TINYINT(1)     NOT NULL DEFAULT 1,
  consent_contact  TINYINT(1)     NOT NULL DEFAULT 0,

  -- Meta
  ip_address       VARCHAR(45)    DEFAULT NULL,
  status           ENUM('pending','reviewed','accepted','declined') NOT NULL DEFAULT 'pending',
  submitted_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_app_ref (app_ref),
  KEY idx_email (email),
  KEY idx_status (status),
  KEY idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
