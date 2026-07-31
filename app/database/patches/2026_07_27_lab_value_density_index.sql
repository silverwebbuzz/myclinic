-- ============================================================================
-- Speed up the lab listing pages (/lab/tests, /lab/category/*, /lab/symptom/*).
--
-- PROBLEM
-- The listing default sort ranks by "value density" (tests per rupee):
--     ORDER BY is_featured DESC, booked_count DESC,
--              (lp.test_count / pr.offer_rate) DESC, lp.name ASC
-- That third key is a COMPUTED EXPRESSION spanning two tables, so MySQL cannot
-- satisfy it from an index. It has to materialise every matching row and
-- filesort — which is why the category pages got noticeably slower than when
-- the sort was plain column-only.
--
-- FIX
-- Materialise the density onto lab_product_pricing as a STORED generated column
-- and index it. Density only depends on (test_count, offer_rate), and both are
-- catalog data that changes on re-import, not per request — so precomputing is
-- free and always consistent.
--
-- test_count lives on lab_products and offer_rate on lab_product_pricing, and a
-- generated column may only reference columns in its OWN row. So we denormalise
-- test_count onto the pricing row (kept in sync by the trigger below + the
-- backfill here), then generate density from the two local columns.
--
-- Cost of the denormalisation: one extra SMALLINT per pricing row, and the
-- trigger. Worth it — pricing rows are written rarely (a price edit inserts one
-- dated row) and read on every listing page load.
--
-- Deploy:
--   mysql -u USER -p DB < app/database/patches/2026_07_27_lab_value_density_index.sql
-- Re-run after a catalog re-import (it is idempotent).
-- ============================================================================

SET NAMES utf8mb4;

-- 1) Denormalised test_count on the pricing row -------------------------------
SET @has_tc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lab_product_pricing'
       AND COLUMN_NAME = 'test_count'
);
SET @ddl := IF(@has_tc = 0,
    'ALTER TABLE `lab_product_pricing`
        ADD COLUMN `test_count` SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `product_id`',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill / resync from the parent product.
UPDATE `lab_product_pricing` pr
  JOIN `lab_products` lp ON lp.`id` = pr.`product_id`
   SET pr.`test_count` = lp.`test_count`;

-- 2) The generated density column --------------------------------------------
--    NULLIF guards divide-by-zero (offer_rate 0 rows are filtered out by the
--    query's WHERE anyway, but the column must be safe on every row).
SET @has_vd := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lab_product_pricing'
       AND COLUMN_NAME = 'value_density'
);
SET @ddl := IF(@has_vd = 0,
    'ALTER TABLE `lab_product_pricing`
        ADD COLUMN `value_density` DECIMAL(12,6)
        GENERATED ALWAYS AS (`test_count` / NULLIF(`offer_rate`, 0)) STORED',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Index it, with product_id so the "current price row" lookup still works. --
SET @has_ix := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lab_product_pricing'
       AND INDEX_NAME = 'idx_lpp_value_density'
);
SET @ddl := IF(@has_ix = 0,
    'ALTER TABLE `lab_product_pricing`
        ADD KEY `idx_lpp_value_density` (`value_density`, `product_id`)',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) Composite index for the listing's own filter+sort prefix ----------------
--    (is_active, is_featured, booked_count) matches the WHERE + first two
--    ORDER BY keys, so MySQL can walk it instead of sorting the whole set.
SET @has_ix2 := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'lab_products'
       AND INDEX_NAME = 'idx_lab_product_listing'
);
SET @ddl := IF(@has_ix2 = 0,
    'ALTER TABLE `lab_products`
        ADD KEY `idx_lab_product_listing` (`is_active`, `is_featured`, `booked_count`)',
    'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- 5) Keep the denormalised test_count in sync --------------------------------
--    Pricing rows are INSERTed on every admin price edit, so the trigger has to
--    populate test_count then; otherwise new rows would default to 1 and rank
--    as terrible value.
DROP TRIGGER IF EXISTS `trg_lpp_pricing_test_count_ins`;
CREATE TRIGGER `trg_lpp_pricing_test_count_ins`
BEFORE INSERT ON `lab_product_pricing`
FOR EACH ROW
    SET NEW.`test_count` = COALESCE(
        (SELECT `test_count` FROM `lab_products` WHERE `id` = NEW.`product_id`), 1
    );

-- A product's test_count changes only on re-import; resync pricing rows then.
DROP TRIGGER IF EXISTS `trg_lab_products_test_count_upd`;
CREATE TRIGGER `trg_lab_products_test_count_upd`
AFTER UPDATE ON `lab_products`
FOR EACH ROW
    UPDATE `lab_product_pricing`
       SET `test_count` = NEW.`test_count`
     WHERE `product_id` = NEW.`id`
       AND `test_count` <> NEW.`test_count`;

-- 6) Verify ------------------------------------------------------------------
SELECT COUNT(*) AS pricing_rows,
       SUM(`value_density` IS NULL) AS null_density,
       ROUND(MAX(`value_density`), 4) AS max_density
  FROM `lab_product_pricing`;
