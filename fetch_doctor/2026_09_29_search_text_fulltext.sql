-- =====================================================================
-- 2026_09_29_search_text_fulltext.sql
-- Make free-text doctor search (/api/search_doctors?q=...) use an index.
--
-- Problem: partials/search_doctors_query.php ORs eight `LIKE '%q%'`
-- conditions (name, doctor_name, area, city, specialty and the
-- specialty_master label / plural_label / url_slug). A leading-wildcard LIKE
-- can't use an index, so every q= search reads all ~84k rows (plus the
-- specialty_master join for each). Measured on production: ?q=dental ~10s.
--
-- Fix: one searchable text column holding exactly those fields, with a
-- FULLTEXT index. The search then runs
--     MATCH(dd.search_text) AGAINST('dental*' IN BOOLEAN MODE)
-- which is an index lookup. Until this file has been run (column or index
-- missing) the PHP keeps using the old LIKE query, so deploy order doesn't
-- matter.
--
-- search_text is kept current three ways:
--   1. this file fills every existing row (section 3);
--   2. BEFORE INSERT / BEFORE UPDATE triggers (section 2) recompute it on
--      every write, so rows added or edited by ANY code path — the importer,
--      claim approval (new_listing), clinic settings — are searchable at once;
--   3. fetch_doctor/build_clinic_siblings.php re-derives it per city, which
--      also picks up edits to specialty_master labels (the triggers only fire
--      on directory_doctors writes).
--
-- The expression is written out in three places; keep them identical:
--   - this file, section 2 (triggers) and section 3 (backfill)
--   - FD_SEARCH_TEXT_EXPR in fetch_doctor/_clinic_siblings.php
--
-- Safe to run repeatedly: column/index adds are guarded, triggers are
-- dropped and recreated, and the backfill only touches rows whose value
-- differs. updated_at is deliberately left unchanged. Run once:
--   mysql DBNAME < fetch_doctor/2026_09_29_search_text_fulltext.sql
--
-- NOTE: the ALTERs rebuild directory_doctors and hold a metadata lock for a
-- short while; run it in a quiet window. Needs the TRIGGER privilege (root
-- has it).
-- =====================================================================

-- --- helpers: add a column / index only if it does not already exist ---
DROP PROCEDURE IF EXISTS ecp_add_column_if_missing;
DROP PROCEDURE IF EXISTS ecp_add_index_if_missing;
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
-- kind = '' for a normal index, 'FULLTEXT' for a fulltext one.
CREATE PROCEDURE ecp_add_index_if_missing(
    IN tbl VARCHAR(64), IN idx VARCHAR(64), IN kind VARCHAR(16), IN cols VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = tbl
          AND index_name = idx
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', tbl, '` ADD ', kind, ' INDEX `', idx, '` (', cols, ')');
        PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

-- --- 1. The column -----------------------------------------------------
-- name + doctor_name + area + city + specialty slug (underscores -> spaces,
-- since InnoDB treats '_' as part of a word) + specialty label, plural
-- label and URL slug. Same fields the old LIKE query looked at.
CALL ecp_add_column_if_missing(
    'directory_doctors', 'search_text', 'TEXT NULL DEFAULT NULL'
);

-- --- 2. Keep it current on every write ----------------------------------
-- Scalar subqueries (not SELECT ... INTO) so a row with no / an inactive
-- specialty just gets NULL labels instead of a "no data" warning.
DROP TRIGGER IF EXISTS trg_directory_doctors_search_text_ins;
DROP TRIGGER IF EXISTS trg_directory_doctors_search_text_upd;
DELIMITER //
CREATE TRIGGER trg_directory_doctors_search_text_ins
BEFORE INSERT ON directory_doctors FOR EACH ROW
BEGIN
    SET NEW.search_text = CONCAT_WS(' ',
        NEW.name, NEW.doctor_name, NEW.area, NEW.city,
        REPLACE(NEW.specialty, '_', ' '),
        (SELECT sm.label        FROM specialty_master sm WHERE sm.slug = NEW.specialty AND sm.is_active = 1 LIMIT 1),
        (SELECT sm.plural_label FROM specialty_master sm WHERE sm.slug = NEW.specialty AND sm.is_active = 1 LIMIT 1),
        (SELECT sm.url_slug     FROM specialty_master sm WHERE sm.slug = NEW.specialty AND sm.is_active = 1 LIMIT 1));
END //
CREATE TRIGGER trg_directory_doctors_search_text_upd
BEFORE UPDATE ON directory_doctors FOR EACH ROW
BEGIN
    SET NEW.search_text = CONCAT_WS(' ',
        NEW.name, NEW.doctor_name, NEW.area, NEW.city,
        REPLACE(NEW.specialty, '_', ' '),
        (SELECT sm.label        FROM specialty_master sm WHERE sm.slug = NEW.specialty AND sm.is_active = 1 LIMIT 1),
        (SELECT sm.plural_label FROM specialty_master sm WHERE sm.slug = NEW.specialty AND sm.is_active = 1 LIMIT 1),
        (SELECT sm.url_slug     FROM specialty_master sm WHERE sm.slug = NEW.specialty AND sm.is_active = 1 LIMIT 1));
END //
DELIMITER ;

-- --- 3. Backfill existing rows ------------------------------------------
-- Before the FULLTEXT index exists, so the index is built once over the
-- finished data instead of being updated row by row.
UPDATE directory_doctors dd
LEFT JOIN specialty_master sm ON sm.slug = dd.specialty AND sm.is_active = 1
SET dd.search_text = CONCAT_WS(' ',
        dd.name, dd.doctor_name, dd.area, dd.city,
        REPLACE(dd.specialty, '_', ' '),
        sm.label, sm.plural_label, sm.url_slug),
    dd.updated_at = dd.updated_at
WHERE NOT (dd.search_text <=> CONCAT_WS(' ',
        dd.name, dd.doctor_name, dd.area, dd.city,
        REPLACE(dd.specialty, '_', ' '),
        sm.label, sm.plural_label, sm.url_slug));

-- --- 4. The FULLTEXT index ----------------------------------------------
-- partials/search_doctors_query.php only switches to MATCH(search_text)
-- once this index exists (SHOW INDEX ... 'ft_search_text').
CALL ecp_add_index_if_missing(
    'directory_doctors', 'ft_search_text', 'FULLTEXT', 'search_text'
);

DROP PROCEDURE IF EXISTS ecp_add_column_if_missing;
DROP PROCEDURE IF EXISTS ecp_add_index_if_missing;

-- =====================================================================
-- VERIFY after running:
--
--   SHOW INDEX FROM directory_doctors WHERE Key_name = 'ft_search_text';
--   SHOW TRIGGERS WHERE `Table` = 'directory_doctors';
--   SELECT COUNT(*) FROM directory_doctors WHERE search_text IS NULL;   -- expect 0
--
--   -- expect type=fulltext, key=ft_search_text:
--   EXPLAIN SELECT dd.id FROM directory_doctors dd
--    WHERE dd.is_active = 1 AND dd.status = 'OPERATIONAL' AND dd.country = 'IN'
--      AND MATCH(dd.search_text) AGAINST('dental*' IN BOOLEAN MODE)
--    LIMIT 20;
--
-- Then (should drop from ~10s to well under 1.5s):
--   curl -s -o /dev/null -w "%{time_starttransfer}\n" \
--     "https://eclinicpro.com/api/search_doctors?page=1&q=dental"
-- =====================================================================
