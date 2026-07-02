-- ============================================================
-- Kennet Valuation — schema upgrade v12  (atomic sequences)
-- Race-safe serial & report numbering shared across valuation types.
-- Run ONCE in phpMyAdmin → SQL tab.
-- ============================================================

CREATE TABLE IF NOT EXISTS `sequences` (
  `name`  VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
