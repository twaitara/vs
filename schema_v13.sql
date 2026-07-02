-- ============================================================
-- Kennet Valuation — schema upgrade v13
-- Requesting officer on insurance valuations (bank & machine already have one).
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `valuations` ADD COLUMN `insurance_officer` VARCHAR(255) NULL;
