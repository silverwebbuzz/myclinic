-- =====================================================================
-- 2026_09_29_clinic_sibling_ids.sql
-- Precompute "doctors at this clinic" so clinic profile pages stop
-- LIKE-scanning their whole city on every first visit.
--
-- Problem: partials/directory_profile.php, for a clinic page with no portal
-- doctors, runs
--     WHERE city = :city AND id <> :id AND name LIKE '%<base name>%'
--     ORDER BY doctor_name ASC LIMIT 20
-- A leading-wildcard LIKE can't use an index, so every uncached clinic page
-- reads every row in its city. Measured on production: clinic pages 8-18s on
-- first visit (bigger cities slower) vs ~0.8s for doctor pages.
--
-- Fix: store that query's result (up to 20 ids, JSON, in the same order) in
-- directory_doctors.sibling_ids. The page then loads those rows by primary
-- key. The column is filled by fetch_doctor/build_clinic_siblings.php (and by
-- insert_db.php for the cities it imports):
--     NULL      not built yet  -> page falls back to the old LIKE query
--     '[]'      no siblings    -> no query at all
--     '[12,34]' sibling ids    -> WHERE id IN (...)
--
-- Safe to run repeatedly: the ADD COLUMN is guarded, so re-running is a
-- no-op. Purely additive — no existing data is changed. Run once:
--   mysql DBNAME < fetch_doctor/2026_09_29_clinic_sibling_ids.sql
-- then run fetch_doctor/build_clinic_siblings.php once.
-- =====================================================================

-- --- helper: add a column only if it does not already exist -----------
DROP PROCEDURE IF EXISTS ecp_add_column_if_missing;
DELIMITER //
CREATE PROCEDURE ecp_add_column_if_missing(
    IN tbl VARCHAR(64), IN col VARCHAR(64), IN def VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = tbl
          AND column_name = col
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `', col, '` ', def);
        PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

-- JSON array of up to 20 directory_doctors ids, e.g. [812,4410,77].
-- TEXT rather than JSON so it behaves the same on MySQL and MariaDB.
CALL ecp_add_column_if_missing(
    'directory_doctors', 'sibling_ids', 'TEXT NULL DEFAULT NULL'
);

DROP PROCEDURE IF EXISTS ecp_add_column_if_missing;

-- =====================================================================
-- VERIFY after running (expect one row, Type = text):
--   SHOW COLUMNS FROM directory_doctors LIKE 'sibling_ids';
--
-- After build_clinic_siblings.php has run, most clinic rows are non-NULL:
--   SELECT COUNT(*) AS total, SUM(sibling_ids IS NULL) AS not_built,
--          SUM(sibling_ids = '[]') AS no_siblings
--   FROM directory_doctors WHERE is_active = 1 AND status = 'OPERATIONAL';
-- =====================================================================
