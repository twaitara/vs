-- ============================================================
-- Kennet Valuation — schema upgrade v15  (in-app notifications)
-- Per-user notifications for staff and portal users.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `audience`   VARCHAR(10) NOT NULL,          -- staff | client
  `user_id`    BIGINT UNSIGNED NOT NULL,      -- users.id or client_users.id
  `title`      VARCHAR(255) NOT NULL,
  `body`       TEXT NULL,
  `url`        VARCHAR(255) NULL,
  `read_at`    TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_n_user` (`audience`, `user_id`, `read_at`),
  KEY `idx_n_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
