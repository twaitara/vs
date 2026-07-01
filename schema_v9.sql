-- ============================================================
-- Kennet Valuation — schema upgrade v9
-- Live user activity (who's online, what they're working on).
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_activity` (
  `user_id`   BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  `name`      VARCHAR(255) NULL,
  `login_at`  DATETIME NULL,
  `last_seen` DATETIME NULL,
  `activity`  VARCHAR(255) NULL,
  KEY `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
