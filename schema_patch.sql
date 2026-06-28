-- Optional schema patch — run AFTER importing valuation.sql (phpMyAdmin → SQL tab).
-- Fixes a limitation carried over from the original database.

-- 1) The insurance `valuations` table used TINYINT for its primary key,
--    which caps it at 255 rows. Widen it to INT (≈4.2 billion rows).
ALTER TABLE `valuations`
  MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- 2) (Safety) make sure the bank table id is INT as well.
ALTER TABLE `bankvaluations`
  MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Nothing else needs changing — the PHP app matches the existing column names.
