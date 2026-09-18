-- =====================================================================
-- 2026_09_18_fix_default_sort_index.sql
-- Fix the slow /find-a-doctor listing AND the slow clinic detail pages.
--
-- Two separate problems, same root cause (an index that doesn't match the
-- query): the listing API took 8-15s, and clinic detail/booking pages took
-- 7-15s. Sections 1-3 fix the listing, section 4 fixes the detail pages.
--
-- Problem: idx_browse (added 2026_07_09) stops at quality_score:
--     country, is_active, status, is_claimed, has_photo, quality_score
-- but the default/relevance sort in partials/search_doctors_query.php is
--     is_claimed DESC, has_photo DESC, quality_score DESC,
--     reviews DESC, rating DESC          <-- two extra keys
-- Those trailing keys are not in the index, so MySQL reads the index and
-- then FILESORTS the whole matching set (~84k rows) on every request.
--
-- Measured on production before this migration (identical filters, only
-- the sort differs) — this is the entire bug in one table:
--     sort=claimed  (stops at quality_score)   0.55s
--     default / sort=relevance (+reviews,rating)  7.8-9.8s
--
-- Why we extend the index instead of dropping the two sort keys:
-- quality_score is currently NULL for every row in directory_doctors, so
-- is_claimed/has_photo/quality_score are nearly flat and `reviews DESC`
-- is doing ~all of the real ranking. Dropping it would leave the listing
-- ordered by whatever the index happens to return (verified: the top of
-- sort=claimed is ~100-280 review clinics, vs ~2700-3700 for the default
-- sort). The ranking is correct; only the index was one step short.
--
-- Safe to run repeatedly: every ADD INDEX is guarded, so re-running is a
-- no-op rather than a duplicate-key error. Purely additive — no data or
-- column changes, no writes to existing rows. Run once on the server:
--   mysql -u USER -p DBNAME < fetch_doctor/2026_09_18_fix_default_sort_index.sql
--
-- NOTE: directory_doctors is large, so each ALTER takes a while and will
-- hold a metadata lock; run it in a quiet window. InnoDB builds these
-- online (ALGORITHM=INPLACE), so reads/writes keep working meanwhile.
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

-- --- 1. Default / relevance browse sort (THE fix) ---------------------
-- Serves: WHERE country=? AND is_active=? AND status=?
--         ORDER BY is_claimed DESC, has_photo DESC, quality_score DESC,
--                  reviews DESC, rating DESC
-- with no filesort. This also serves the page-1 COUNT(*), which runs the
-- same WHERE and was adding ~5.5s on top (page 1 13.5s vs page 2 8.0s).
--
-- This supersedes idx_browse: same leading columns, two more trailing
-- ones, so it answers everything idx_browse did. idx_browse is dropped
-- at the end of this file to avoid paying for a redundant duplicate on
-- every INSERT/UPDATE.
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_browse_rank',
    'country, is_active, status, is_claimed, has_photo, quality_score, reviews, rating'
);

-- --- 2. sort=rating ---------------------------------------------------
-- ORDER BY rating DESC, reviews DESC — also uncovered today (~6.4s).
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_browse_rating',
    'country, is_active, status, rating, reviews'
);

-- --- 3. sort=fee_asc / fee_desc --------------------------------------
-- ORDER BY consultation_fee IS NULL, consultation_fee — also ~6.4s.
-- The "IS NULL" leading term is an expression, but with fee indexed
-- MySQL can still read in fee order and group the NULLs cheaply.
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_browse_fee',
    'country, is_active, status, consultation_fee'
);

-- --- 4. Profile / detail pages (the 7-15s "clinic detail" pages) ------
-- partials/directory_profile.php runs two city-scoped queries per page:
--   a) "related clinics":  WHERE city=? AND specialty=? AND id<>?
--   b) sibling-doctor fallback: WHERE city=? AND id<>? AND name LIKE '%..%'
-- NO existing index starts with `city` — idx_country_state_city leads with
-- (country, state) and idx_browse_city leads with country — so both queries
-- scan the whole table. Measured before this migration:
--   profile page WITH portal doctors (fallback skipped):  0.58s
--   profile page WITHOUT them        (fallback runs):     6.7-15.8s
--
-- (b)'s leading-wildcard LIKE can never use an index for the name match,
-- but with `city` indexed MySQL narrows to that city's rows first and only
-- LIKE-scans those, instead of all ~84k rows.
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_city_specialty',
    'city, is_active, status, specialty, is_claimed, rating'
);

-- --- 5. Retire the now-redundant idx_browse ---------------------------
-- idx_browse is a strict prefix of idx_browse_rank, so it can never be
-- chosen over it; keeping both just slows writes and wastes disk.
-- Guarded: only drops if the replacement actually exists.
SET @have_new := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'directory_doctors'
      AND index_name = 'idx_browse_rank'
);
SET @have_old := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'directory_doctors'
      AND index_name = 'idx_browse'
);
SET @ddl := IF(@have_new > 0 AND @have_old > 0,
    'ALTER TABLE directory_doctors DROP INDEX idx_browse',
    'DO 0');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

DROP PROCEDURE IF EXISTS ecp_add_index_if_missing;

-- =====================================================================
-- VERIFY after running (expect key=idx_browse_rank, NO "Using filesort"):
--
--   EXPLAIN SELECT dd.id FROM directory_doctors dd
--    WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL' AND dd.country = 'IN'
--    ORDER BY dd.is_claimed DESC, dd.has_photo DESC, dd.quality_score DESC,
--             dd.reviews DESC, dd.rating DESC
--    LIMIT 20;
--
-- Then from anywhere (should drop from ~8-15s to well under 1s):
--   curl -s -o /dev/null -w "%{time_starttransfer}\n" \
--     "https://eclinicpro.com/api/search_doctors?page=1"
--
-- And a detail page that has NO portal doctors (these were the 7-15s ones;
-- pick any clinic whose page shows no doctor cards):
--   curl -s -o /dev/null -w "%{time_starttransfer}\n" \
--     "https://eclinicpro.com/ahmedabad/clinic/aashish-s-clinic"
-- =====================================================================
