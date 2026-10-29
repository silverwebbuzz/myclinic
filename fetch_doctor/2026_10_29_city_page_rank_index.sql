-- =====================================================================
-- 2026_10_29_city_page_rank_index.sql
-- Fix the slow /find-a-doctor/{city} SEO pages for SMALL cities.
--
-- Found by the 2026-10-29 SEO crawl: 6 city pages (amritsar, bhopal,
-- lucknow, ludhiana, ranchi, udaipur) timed out at 15s and 15 more took
-- 3-5s. Measured on production with the page cache bypassed:
--     mumbai 0.61s   ahmedabad 0.63s          <-- big cities fast
--     kochi 3.2s  agra 4.0s  ranchi 4.3s  udaipur 4.9s  amritsar 5.3s
--     lucknow 9.6s                              <-- small cities slow
-- The page cache (10 min) hides this from repeat visitors, but Google and
-- the crawler mostly hit cold pages, so they see the slow render.
--
-- Cause: a city page runs
--     WHERE is_active=1 AND status='OPERATIONAL' AND country=? AND city=?
--     ORDER BY is_claimed DESC, has_photo DESC, quality_score DESC,
--              reviews DESC, rating DESC
--     LIMIT 20
-- No index matches this. idx_browse_city is
--     (country, city, is_active, status, specialty, is_claimed, quality_score)
-- and with no specialty filter everything after `specialty` is unusable
-- for the sort (it also lacks has_photo/reviews/rating). So MySQL walks the
-- national idx_browse_rank in rank order until it collects 20 rows from
-- this city: instant for Mumbai (they are everywhere near the top), almost
-- a full scan for a city with few listings. That is why it gets SLOWER as
-- the city gets SMALLER.
--
-- Fix: city-leading versions of idx_browse_rank, so MySQL jumps straight
-- to the city's rows already in ranking order and stops after 20.
--
-- Safe to run repeatedly (each ADD INDEX is guarded) and purely additive:
-- no data or column changes. Needs the has_photo column from
-- 2026_07_09_add_search_indexes.sql (already on the server).
--
-- directory_doctors is large: each ALTER takes a few minutes. InnoDB
-- builds the index online, so the site keeps working; run it in a quiet
-- hour anyway.
--   mysql -u USER -p DBNAME < fetch_doctor/2026_10_29_city_page_rank_index.sql
-- =====================================================================

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

-- --- 1. City page: /find-a-doctor/{city} -----------------------------
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_city_rank',
    'country, city, is_active, status, is_claimed, has_photo, quality_score, reviews, rating'
);

-- --- 2. City + specialty page: /find-a-doctor/{spec}-in-{city} ---------
CALL ecp_add_index_if_missing(
    'directory_doctors', 'idx_city_spec_rank',
    'country, city, is_active, status, specialty, is_claimed, has_photo, quality_score, reviews, rating'
);

DROP PROCEDURE IF EXISTS ecp_add_index_if_missing;

-- =====================================================================
-- VERIFY after running.
--
-- a) Both new indexes exist (expect 2 rows):
--   SELECT DISTINCT index_name FROM information_schema.statistics
--    WHERE table_schema = DATABASE() AND table_name = 'directory_doctors'
--      AND index_name IN ('idx_city_rank', 'idx_city_spec_rank');
--
-- b) The city query uses it (expect key=idx_city_rank, NO "Using filesort"):
--   EXPLAIN SELECT dd.id FROM directory_doctors dd
--    WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL'
--      AND dd.country = 'IN' AND dd.city = 'Lucknow'
--    ORDER BY dd.is_claimed DESC, dd.has_photo DESC, dd.quality_score DESC,
--             dd.reviews DESC, dd.rating DESC
--    LIMIT 20;
--
-- c) From any terminal (the ?nc= bypasses the page cache; was 9.6s):
--   curl -s -o /dev/null -w "%{time_starttransfer}\n" \
--     "https://eclinicpro.com/find-a-doctor/lucknow?nc=1"
-- =====================================================================
