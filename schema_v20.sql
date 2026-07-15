-- ============================================================
-- Kennet Valuation — schema upgrade v20
-- Allow up to 2 concurrent sessions per user on ANY devices
-- (replaces the one-per-device-class table). Sessions are transient,
-- so recreating the table just asks everyone to log in once more.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

DROP TABLE IF EXISTS `user_sessions`;

CREATE TABLE `user_sessions` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `token`      VARCHAR(64) NOT NULL,
  `device`     VARCHAR(10) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `last_seen`  TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
