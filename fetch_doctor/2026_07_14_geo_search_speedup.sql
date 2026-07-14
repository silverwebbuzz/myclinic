-- =====================================================================
-- 2026_07_14_geo_search_speedup.sql
-- Make the "near me" (lat/lng) search on /find-a-doctor fast.
--
-- Problem: sorting by distance ran a Haversine calc + TWO correlated
-- subqueries into directory_cities FOR EVERY ROW (~all doctors), then
-- filesorted the whole set, then kept 20. No index could help, so it did
-- a full scan on every geolocated page load. Cost scaled with row count,
-- which is why it got slow as the directory doubled.
--
-- This migration does two things:
--   1. Backfill dd.lat / dd.lng from directory_cities for rows that are
--      missing them, so the per-row city subquery disappears at query time.
--   2. Add a composite (lat, lng) index so the new bounding-box prefilter
--      in ecp_search_doctors() can shrink the candidate set to nearby rows
--      BEFORE the Haversine sort.
--
-- Safe to run repeatedly. The UPDATE only touches NULL rows; the index add
-- is guarded so a re-run is a no-op instead of a duplicate-key error.
--
-- Run on the portal DB:
--   mysql -u USER -p DB < fetch_doctor/2026_07_14_geo_search_speedup.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Backfill missing doctor coordinates from the city catalog.
--    Mirrors the exact match logic the runtime query used:
--      LOWER(c.name) = LOWER(dd.city)
--      AND (dd.state empty OR LOWER(c.state) = LOWER(dd.state))
--      AND c.is_active = 1     -- (drop this line if your directory_cities
--                                 has no is_active column)
--    Only fills rows where dd.lat/dd.lng are NULL; never overwrites a
--    doctor's own real coordinates.
-- ---------------------------------------------------------------------
UPDATE directory_doctors dd
JOIN directory_cities c
  ON  LOWER(c.name) = LOWER(dd.city)
  AND ( dd.state IS NULL OR dd.state = ''
        OR LOWER(c.state) = LOWER(dd.state) )
  AND c.is_active = 1
  AND c.lat IS NOT NULL
  AND c.lng IS NOT NULL
SET dd.lat = c.lat,
    dd.lng = c.lng
WHERE dd.lat IS NULL
   OR dd.lng IS NULL;

-- ---------------------------------------------------------------------
-- 2. Composite index for the bounding-box prefilter
--    (WHERE dd.lat BETWEEN ... AND dd.lng BETWEEN ...).
--    Guarded add so re-running is a no-op.
-- ---------------------------------------------------------------------
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'directory_doctors'
    AND index_name   = 'idx_geo_box'
);
SET @ddl := IF(@idx_exists = 0,
  'ALTER TABLE directory_doctors ADD INDEX idx_geo_box (lat, lng)',
  'SELECT ''idx_geo_box already exists — skipped'' AS note'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
