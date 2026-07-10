-- =====================================================================
-- 2026_07_09_add_search_indexes.sql
-- Speed up /find-a-doctor browse + search (heart of the portal).
--
-- Problem: the default browse query
--   WHERE is_active=1 AND status='OPERATIONAL' AND country=:country
--   ORDER BY is_claimed DESC, (has photo) DESC, quality_score DESC
-- matched no index that covered BOTH the filter and the sort, so MySQL
-- filtered on one narrow index and then FILESORTED every matching row
-- (all IN doctors) on every page load. On first load this runs
-- server-side in find-a-doctor.php, so it blocked the whole page.
--
-- Fix: composite indexes whose leading columns are the equality filters
-- and whose trailing columns are the ORDER BY, so MySQL can read rows
-- already in sort order and stop at LIMIT/OFFSET (no filesort, no full
-- scan). MySQL sorts a whole index either ASC or DESC; our ORDER BY is
-- all-DESC on the ranking columns, so a plain composite serves it.
--
-- Safe to run repeatedly: each ADD INDEX is guarded so re-running is a
-- no-op rather than a duplicate-key error. Purely additive — no data or
-- column changes. Run once on the server:
--   mysql -u USER -p DBNAME < fetch_doctor/2026_07_09_add_search_indexes.sql
-- =====================================================================

-- --- helper: add an index only if it does not already exist ----------
DROP PROCEDURE IF EXISTS ecp_add_index_if_missing;
DELIMITER //
CREATE PROCEDURE ecp_add_index_if_missing(
    IN tbl VARCHAR(64), IN idx VARCHAR(64), IN cols VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = tbl
          AND index_name = idx
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl, '` ADD INDEX `', idx, '` (', cols, ')');
        PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

-- --- 0. Persisted has_photo flag so the middle ORDER BY key is indexable
-- The sort is: is_claimed DESC, (has photo) DESC, quality_score DESC.
-- The has-photo test is an expression, and it sits BETWEEN two ranking
-- columns, so an index on (is_claimed, quality_score) alone still leaves
-- a filesort. A STORED generated column turns it into a real, indexable
-- column. (Requires MySQL 5.7+/MariaDB 10.2+; if your server is older,
-- skip this block and drop has_photo from the indexes below — the
-- is_claimed+quality_score ordering alone is still a large win.)
SET @has_col := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'directory_doctors'
      AND column_name = 'has_photo'
);
SET @ddl := IF(@has_col = 0,
    'ALTER TABLE directory_doctors
       ADD COLUMN has_photo TINYINT(1)
       AS (photo_reference IS NOT NULL AND photo_reference <> '''') STORED',
    'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- --- 1. Default browse: country + active/status filter, then full sort
-- Serves: WHERE country=? AND is_active=? AND status=?
--         ORDER BY is_claimed DESC, has_photo DESC, quality_score DESC
-- with NO filesort — the index already holds rows in ranking order.
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_browse',
    'country, is_active, status, is_claimed, has_photo, quality_score'
);

-- --- 2. Specialty-in-country browse (e.g. dermatologist across IN) ----
-- Serves the same shape with a specialty equality added.
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_browse_spec',
    'country, is_active, status, specialty, is_claimed, quality_score'
);

-- --- 3. City / specialty-in-city pages (the SEO landing pages) --------
-- Serves: WHERE country=? AND city=? [AND specialty=?] AND active/status
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_browse_city',
    'country, city, is_active, status, specialty, is_claimed, quality_score'
);

-- --- 4. Owner-name subselect: users.clinic_id lookup -----------------
-- ecp_search_doctors runs a correlated subquery per returned row:
--   SELECT u.name FROM users u
--    WHERE u.clinic_id=? AND u.is_owner=1 AND u.is_active=1 ...
-- Index it so each lookup is a key hit, not a scan of users.
CALL ecp_add_index_if_missing(
    'users', 'idx_clinic_owner',
    'clinic_id, is_owner, is_active, id'
);

-- --- 5. Footer "top cities" GROUP BY (runs on EVERY page) -------------
-- ecp_footer_top_cities(): SELECT city, COUNT(*) ... WHERE is_active=1
--   AND status='OPERATIONAL' AND city<>'' GROUP BY city ORDER BY n DESC.
-- Without a covering index this is a full scan + temp-table aggregation
-- on every page render site-wide. This index lets MySQL group by reading
-- the index in city order (loose index scan) instead of scanning rows.
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_footer_cities',
    'is_active, status, city'
);

DROP PROCEDURE IF EXISTS ecp_add_index_if_missing;

-- --- verify (optional) -----------------------------------------------
-- SHOW INDEX FROM directory_doctors;
-- EXPLAIN SELECT dd.id FROM directory_doctors dd
--   WHERE dd.is_active=1 AND dd.status='OPERATIONAL' AND dd.country='IN'
--   ORDER BY dd.is_claimed DESC, dd.quality_score DESC LIMIT 20;
-- Look for key=idx_browse and NO "Using filesort" in Extra.
