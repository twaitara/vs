-- ============================================================
-- Kennet Valuation — schema upgrade v19
-- 30-day inactivity auto-disable for portal (client) users.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `client_users` ADD COLUMN `last_login_at`   TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `client_users` ADD COLUMN `disabled_reason` VARCHAR(20) NULL DEFAULT NULL;
