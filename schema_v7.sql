-- ============================================================
-- Kennet Valuation — schema upgrade v7
-- Per-user signing mandate. Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `users` ADD COLUMN `can_sign` TINYINT(1) NOT NULL DEFAULT 0;
