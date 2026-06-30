-- ============================================================
-- Kennet Valuation — schema upgrade v5
-- Allow letters/units in the insurance odometer reading
-- (e.g. "85000 KMS", "3200 HRS", "52000 MI").
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `valuations` MODIFY `mileage` VARCHAR(30) NULL;
