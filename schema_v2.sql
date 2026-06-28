-- ============================================================
-- Kennet Valuation — schema upgrade v2
-- Adds: user roles & status, settings, audit log, login attempts,
--       record ownership and soft-delete.
-- Run ONCE in phpMyAdmin → SQL tab. Safe to run on the existing DB.
-- (MySQL has no "ADD COLUMN IF NOT EXISTS"; if a column already
--  exists, skip that line.)
-- ============================================================

-- --- Users: role + active flag -----------------------------
ALTER TABLE `users`
  ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'valuer' AFTER `email`,
  ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

-- Make the earliest existing user an admin so you can log in and manage others.
UPDATE `users` SET `role` = 'admin'
 WHERE `id` = (SELECT id FROM (SELECT MIN(id) AS id FROM `users`) t);

-- --- Settings (key/value store) ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(64) NOT NULL PRIMARY KEY,
  `value`      TEXT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default company/report settings (ignored if already present).
INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
  ('company_name','Kennet Automobile Valuers And Assessors Ltd.'),
  ('company_address','Desai Road off Forest Rd, Opp Nairobi Gymkhana'),
  ('company_pobox','1268 - 00600 NAIROBI, KENYA'),
  ('company_tel','+254 723 441 666, +254 712 730 738, +254 706 850 101'),
  ('company_email','info@kennetvaluers.com'),
  ('report_footer','© Kennet Automobile Valuers And Assessors Ltd. | Valuation Report | Confidential Document'),
  ('currency','KES'),
  ('default_valuer',''),
  ('per_page','25');

-- --- Audit log ---------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    BIGINT UNSIGNED NULL,
  `user_name`  VARCHAR(255) NULL,
  `action`     VARCHAR(40) NULL,
  `entity`     VARCHAR(40) NULL,
  `entity_id`  VARCHAR(40) NULL,
  `details`    TEXT NULL,
  `ip`         VARCHAR(45) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_audit_entity` (`entity`,`entity_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Login attempts (rate limiting) ------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(255) NULL,
  `ip`         VARCHAR(45) NULL,
  `success`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_login_ip` (`ip`,`created_at`),
  KEY `idx_login_email` (`email`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Ownership + soft delete on valuation tables -----------
ALTER TABLE `bankvaluations`
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL,
  ADD COLUMN `updated_by` BIGINT UNSIGNED NULL,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE `valuations`
  ADD COLUMN `created_by` BIGINT UNSIGNED NULL,
  ADD COLUMN `updated_by` BIGINT UNSIGNED NULL,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL;
