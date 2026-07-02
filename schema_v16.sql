-- ============================================================
-- Kennet Valuation — schema upgrade v16  (accept / deny requests)
-- Adds a reason column for denied valuation requests.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `valuation_requests` ADD COLUMN `deny_reason` VARCHAR(255) NULL;
