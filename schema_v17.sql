-- ============================================================
-- Kennet Valuation — schema upgrade v17  (machine details)
-- Machine's own serial number and year of manufacture (both optional).
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `machinevaluations` ADD COLUMN `machine_serial` VARCHAR(100) NULL;
ALTER TABLE `machinevaluations` ADD COLUMN `manufacture_year` VARCHAR(10) NULL;
