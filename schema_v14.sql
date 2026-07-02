-- ============================================================
-- Kennet Valuation — schema upgrade v14  (email rate limiting / queue)
-- Caps outgoing email per hour; overflow is queued and sent later.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `recipients`  TEXT NOT NULL,                 -- comma-separated addresses
  `subject`     VARCHAR(255) NULL,
  `body`        LONGTEXT NULL,
  `is_html`     TINYINT NOT NULL DEFAULT 0,
  `attachments` LONGTEXT NULL,                 -- JSON: [{name,type,data(base64)}]
  `status`      VARCHAR(10) NOT NULL DEFAULT 'pending',  -- pending | sent | failed
  `attempts`    INT NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  `sent_at`     TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_eq_status` (`status`),
  KEY `idx_eq_sent`   (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
