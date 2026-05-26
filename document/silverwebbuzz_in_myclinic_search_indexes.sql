-- =====================================================================
-- silverwebbuzz_in_myclinic_search_indexes.sql
--
-- Indexes that the new /api/search_doctors and /api/locations endpoints
-- need to be fast. Without these the new endpoints fall back to full
-- table scans on ~100k+ rows.
--
-- Safe to run on a live DB:
--   - All ADD INDEX / ALTER TABLE ... ADD are non-blocking on InnoDB
--   - FULLTEXT index build runs once; on a few-hundred-thousand-row
--     table it's a few seconds
--   - If a name collides ("Duplicate key name"), just skip that line
--
-- Wrap in a transaction is NOT needed — DDL auto-commits in MySQL/MariaDB.
-- =====================================================================

-- 1. FULLTEXT search over doctor + clinic names.
--    Enables fast "WHERE MATCH(name, doctor_name) AGAINST(:q)" queries
--    for the search box. Falls back to LIKE if you ever drop this.
ALTER TABLE `directory_doctors`
  ADD FULLTEXT INDEX `ft_dir_names` (`name`, `doctor_name`);


-- 2. Compound covering index for the most common filter combo:
--    "OPERATIONAL clinics in country X with specialty Y, ranked".
--    Covers the WHERE + ORDER BY of every default-search query.
ALTER TABLE `directory_doctors`
  ADD INDEX `idx_dir_country_spec_quality`
    (`country`, `specialty`, `is_active`, `status`, `quality_score`);


-- 3. Compound for "filter by claimed status, then rank".
--    Patient-facing default sort always shows claimed first.
ALTER TABLE `directory_doctors`
  ADD INDEX `idx_dir_claimed_quality`
    (`is_claimed`, `quality_score`, `rating`);


-- 4. Helpers for the locations autocomplete endpoint.
--    These are case-insensitive prefix searches; an index on the column
--    alone is enough since MySQL can use the leftmost-prefix.
ALTER TABLE `directory_doctors`
  ADD INDEX `idx_dir_area_city` (`area`, `city`);


-- 5. Lat/lng spatial-ish lookup for "nearest doctor" sort.
--    True spatial indexes need POINT columns; for now a covering index
--    on (lat, lng) is enough because we filter doctors to a country
--    first, then compute Haversine in SQL.
ALTER TABLE `directory_doctors`
  ADD INDEX `idx_dir_latlng` (`lat`, `lng`);
