-- ============================================================
-- Kennet Valuation — schema upgrade v11  (Machine Valuations)
-- Adds a third valuation type: machinevaluations.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `machinevaluations` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `serial_no`           VARCHAR(50)  NULL,
  `report_no`           VARCHAR(50)  NULL,
  `corporate_ref_no`    VARCHAR(100) NULL,
  `customer_name`       VARCHAR(255) NULL,
  `phone_no`            VARCHAR(50)  NULL,
  `assesment_date`      DATE         NULL,           -- inspection date
  `inspection_location` VARCHAR(255) NULL,
  `machine_name`        VARCHAR(255) NULL,           -- e.g. CLAAS MARKANT 65 BALER
  `colour`              VARCHAR(80)  NULL,
  `market_value`        DECIMAL(15,2) NULL,
  `forced_value`        DECIMAL(15,2) NULL,
  `note_value`          VARCHAR(255) NULL,           -- value in words
  `client`             INT          NULL,           -- clients.id (report destination)
  `officer`             VARCHAR(255) NULL,
  `principal_valuer`    VARCHAR(255) NULL,           -- inspected by
  `extras`              TEXT         NULL,
  `notes`               TEXT         NULL,
  `images`              TEXT         NULL,           -- JSON array of photo paths
  `logbook`             VARCHAR(255) NULL,
  `status`              VARCHAR(20)  NOT NULL DEFAULT 'draft',
  `created_by`          INT          NULL,
  `updated_by`          INT          NULL,
  `signed_at`           TIMESTAMP    NULL DEFAULT NULL,
  `signed_by`           INT          NULL,
  `created_at`          TIMESTAMP    NULL DEFAULT NULL,
  `updated_at`          TIMESTAMP    NULL DEFAULT NULL,
  `deleted_at`          TIMESTAMP    NULL DEFAULT NULL,
  KEY `idx_mv_created`  (`created_at`),
  KEY `idx_mv_owner`    (`created_by`),
  KEY `idx_mv_client`   (`client`),
  KEY `idx_mv_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
