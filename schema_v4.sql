-- ============================================================
-- Kennet Valuation — schema upgrade v4
-- Client portal logins. Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `client_users` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `client_id`  INT NOT NULL,
  `name`       VARCHAR(255) NULL,
  `email`      VARCHAR(255) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uq_cu_email` (`email`),
  KEY `idx_cu_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
