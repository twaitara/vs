-- ============================================================
-- Kennet Valuation — schema upgrade v10  (Request workflow, stage 1)
-- Client type, portal-user roles, and the valuation_requests table.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

-- Client company type: 'bank' or 'client' (client = insurance-type)
ALTER TABLE `clients` ADD COLUMN `type` VARCHAR(10) NOT NULL DEFAULT 'client';

-- Portal user role: 'admin' (manages the company's portal) or 'officer'
ALTER TABLE `client_users` ADD COLUMN `role` VARCHAR(20) NOT NULL DEFAULT 'officer';

-- Valuation requests raised by portal officers, triaged by Kennet.
CREATE TABLE IF NOT EXISTS `valuation_requests` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `client_id`       INT NOT NULL,
  `requested_by`    BIGINT UNSIGNED NULL,          -- client_users.id
  `requester_name`  VARCHAR(255) NULL,
  `reg_no`          VARCHAR(30) NULL,
  `type`            VARCHAR(12) NOT NULL DEFAULT 'bank',   -- bank | insurance
  `notes`           TEXT NULL,
  `status`          VARCHAR(20) NOT NULL DEFAULT 'requested', -- requested|assigned|in_progress|complete|cancelled
  `assigned_to`     BIGINT UNSIGNED NULL,          -- users.id (Kennet officer)
  `valuation_id`    BIGINT UNSIGNED NULL,          -- created record id
  `valuation_table` VARCHAR(20) NULL,              -- bankvaluations | valuations
  `created_at`      TIMESTAMP NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL,
  KEY `idx_vr_client` (`client_id`),
  KEY `idx_vr_status` (`status`),
  KEY `idx_vr_assigned` (`assigned_to`),
  KEY `idx_vr_requested` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
