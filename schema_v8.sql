-- ============================================================
-- Kennet Valuation — schema upgrade v8
-- Per-user initials (shown as the valuer on the reports list).
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `users` ADD COLUMN `initials` VARCHAR(10) NULL;
