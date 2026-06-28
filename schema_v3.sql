-- ============================================================
-- Kennet Valuation — schema upgrade v3
-- Adds: valuation status workflow + sequential report numbers,
--       and helpful indexes. Run ONCE in phpMyAdmin → SQL tab.
-- (Skip any line whose column/index already exists.)
-- ============================================================

ALTER TABLE `bankvaluations`
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  ADD COLUMN `report_no` VARCHAR(30) NULL;

ALTER TABLE `valuations`
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  ADD COLUMN `report_no` VARCHAR(30) NULL;

-- Indexes to speed up lists/filters (ignore "duplicate key name" if re-run).
CREATE INDEX `idx_bv_client`  ON `bankvaluations` (`client`);
CREATE INDEX `idx_bv_created` ON `bankvaluations` (`created_at`);
CREATE INDEX `idx_bv_status`  ON `bankvaluations` (`status`);
CREATE INDEX `idx_v_client`   ON `valuations` (`client`);
CREATE INDEX `idx_v_created`  ON `valuations` (`created_at`);
CREATE INDEX `idx_v_status`   ON `valuations` (`status`);

-- Settings for report numbering.
INSERT IGNORE INTO `settings` (`key`,`value`) VALUES
  ('report_prefix','KEN');
