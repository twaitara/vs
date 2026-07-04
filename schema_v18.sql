-- ============================================================
-- Kennet Valuation — schema upgrade v18
-- Concurrent-session control (1 desktop + 1 mobile) and
-- 30-day inactivity auto-disable for staff users.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

ALTER TABLE `users` ADD COLUMN `last_login_at`   TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `disabled_reason` VARCHAR(20) NULL DEFAULT NULL;

-- One row per (user, device class); a new login on that class replaces the token.
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `device`     VARCHAR(10) NOT NULL,       -- desktop | mobile
  `token`      VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `last_seen`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`, `device`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
