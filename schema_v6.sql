-- ============================================================
-- Kennet Valuation — schema upgrade v6
-- Report signing: who signed and when. Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `bankvaluations`
  ADD COLUMN `signed_at` DATETIME NULL,
  ADD COLUMN `signed_by` BIGINT UNSIGNED NULL;

ALTER TABLE `valuations`
  ADD COLUMN `signed_at` DATETIME NULL,
  ADD COLUMN `signed_by` BIGINT UNSIGNED NULL;

INSERT IGNORE INTO `settings` (`key`,`value`) VALUES ('signature_image','');
