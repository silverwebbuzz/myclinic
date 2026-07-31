-- ============================================================================
-- Precompute per-product test-group breakdown into lab_products.test_groups_json
--
-- Cards on /lab/category and /lab/tests want to show the test GROUPS a package
-- covers (e.g. {"Complete Hemogram":28,"Toxic Elements":22,"Liver":12}). That
-- breakdown is derivable from lab_product_parameters + lab_parameters.group_name,
-- but joining/aggregating it per card on every page load is wasteful — the
-- catalog changes rarely. So we materialize it ONCE into a JSON column and just
-- read the column at render time. Re-run this patch after a catalog re-import.
--
-- JSON (not CSV) so we keep {group: count} pairs, stay future-proof, and can
-- query it natively with MySQL 8 JSON functions if ever needed.
--
-- Deploy:
--   mysql -u USER -p DB < app/database/patches/2026_07_21_lab_test_groups.sql
-- ============================================================================

SET NAMES utf8mb4;

-- 1) Add the column (idempotent guard) --------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'lab_products'
      AND COLUMN_NAME = 'test_groups_json'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `lab_products`
        ADD COLUMN `test_groups_json` JSON DEFAULT NULL AFTER `test_count`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Populate it: for each product, {group_name: count} ordered by count desc.
--    JSON_OBJECTAGG builds the object from the aggregated group counts.
UPDATE `lab_products` lp
JOIN (
    SELECT g.product_id,
           JSON_OBJECTAGG(g.grp, g.n) AS groups_json
    FROM (
        SELECT lpp.product_id,
               COALESCE(NULLIF(par.group_name, ''), 'Other') AS grp,
               COUNT(*) AS n
        FROM lab_product_parameters lpp
        JOIN lab_parameters par ON par.id = lpp.parameter_id
        GROUP BY lpp.product_id, grp
    ) g
    GROUP BY g.product_id
) x ON x.product_id = lp.id
SET lp.test_groups_json = x.groups_json;
