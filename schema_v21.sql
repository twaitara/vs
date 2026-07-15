-- ============================================================
-- Kennet Valuation — schema upgrade v21
-- Server-side autosave drafts, so admins can see what valuers
-- are working on (including unsaved drafts).
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `form_drafts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `draft_key`  VARCHAR(40) NOT NULL,          -- e.g. banknew | bank12 | machinenew
  `form`       VARCHAR(12) NULL,              -- bank | insurance | machine
  `record_id`  BIGINT UNSIGNED NULL,          -- set when editing an existing record
  `label`      VARCHAR(150) NULL,             -- reg no / machine name for display
  `payload`    LONGTEXT NULL,                 -- JSON of the form fields
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uq_user_key` (`user_id`, `draft_key`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
