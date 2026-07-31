-- ============================================================================
-- Remove lab catalog products the Thyrocare MERCHANT ACCOUNT cannot fulfil.
--
-- The catalog was seeded from the full Thyrocare master feed, but our merchant
-- account is only enabled for a subset of it. The 33 items below are not
-- available to us, so they must leave the catalog entirely — not merely be
-- deactivated — so they can never be searched, deep-linked or booked.
--
-- Child rows (pricing / parameters / categories / images / aliases) all declare
-- ON DELETE CASCADE against lab_products, but they're deleted explicitly here so
-- this patch stays correct even on a DB whose FKs were lost to an import.
--
-- Afterwards we sweep lab_parameters and lab_categories rows that no surviving
-- product references — cascade can't do that, since they're shared M:N targets.
--
-- Name matching normalises runs of whitespace: the feed contains doubled spaces
-- (e.g. "NEONATAL THYROID STIMULATING HORMONE #  (TSH)") that a copy-paste
-- collapses to one.
--
-- Equivalent to the /admin/lab/products/cleanup screen — this file exists so the
-- one-off production cleanup is reviewable and repeatable. Idempotent: re-running
-- deletes nothing more.
--
-- Deploy:
--   mysql -u USER -p DB < app/database/patches/2026_07_27_lab_remove_unavailable_products.sql
-- ============================================================================

SET NAMES utf8mb4;

-- 1) Collect the target ids once, so every later step works off the same set. --
DROP TEMPORARY TABLE IF EXISTS `tmp_lab_remove`;
CREATE TEMPORARY TABLE `tmp_lab_remove` (
  `id` INT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT INTO `tmp_lab_remove` (`id`)
SELECT `id` FROM `lab_products`
WHERE UPPER(TRIM(REGEXP_REPLACE(`name`, '[[:space:]]+', ' '))) IN (
    'COMPLETE THYROID CHECKUP',
    'AAROGYAM WINTER BASIC WITH UTSH',
    'AAROGYAM BASIC 2 WITH UTSH',
    'AAROGYAM BASIC 1 WITH UTSH',
    'AAROGYAM MALE WITH UTSH',
    'COAGULATION PROFILE',
    'COVID ANTIBODY IGG (QUANTITATIVE)',
    'AAROGYAM WINTER ADVANCED WITH UTSH',
    'AAROGYAM CAMP PROFILE 1',
    'AAROGYAM X WITH UTSH BUY1GET1FREE',
    'AFB DRUG SUSCEPTIBILITY - MOTT',
    'AAROGYAM CAMP PROFILE 2',
    'WINTER WELLNESS COUPLE PACKAGE WITH VITAMINS',
    'BETA THALASSEMIA',
    'COVID ANTIBODIES-TOTAL (CLIA)',
    'COVID ANTIBODY - 1',
    'COVID ANTIBODY IGM - ELISA',
    'COVID INFECTION MONITORING - ADVANCED',
    'TB - WHOLE GENOME SEQUENCING',
    'SPORTS FITNESS - COMPREHENSIVE',
    'SPORTS FITNESS - BASIC',
    'SPORTS FITNESS - ADVANCED',
    'DOCTOR RECOMMENDED FULL BODY CHECKUP ADVANCED',
    'FT3-FT4-USTSH',
    'FREEDOM HEALTHY COUPLE PACKAGE 2025 ECG',
    'FREEDOM HEALTHY PACKAGE 2025 ECG',
    'GLUCOSE TOLERANCE TEST (1 HOUR)',
    'GLUCOSE TOLERANCE TEST (2 HOUR)',
    'HEALTHY 2026 COUPLE PACKAGE WITH ECG',
    'HEALTHY 2026 PACKAGE WITH ECG',
    'MISCELLANEOUS CULTURE AND SUSCEPTIBILITY',
    'NEONATAL THYROID STIMULATING HORMONE # (TSH)',
    'ANTENATAL PROFILE - ADVANCED'
);

-- Sanity check — review this BEFORE the deletes run if you execute this file
-- statement by statement. Expect 33 rows (32 unique names + none duplicated).
SELECT COUNT(*) AS products_to_delete FROM `tmp_lab_remove`;
SELECT lp.id, lp.product_type, lp.code, lp.name
  FROM `lab_products` lp JOIN `tmp_lab_remove` t ON t.id = lp.id
 ORDER BY lp.name;

-- 2) Children first (explicit; cascade would also cover these). --------------
DELETE FROM `lab_product_pricing`    WHERE `product_id` IN (SELECT `id` FROM `tmp_lab_remove`);
DELETE FROM `lab_product_parameters` WHERE `product_id` IN (SELECT `id` FROM `tmp_lab_remove`);
DELETE FROM `lab_product_categories` WHERE `product_id` IN (SELECT `id` FROM `tmp_lab_remove`);
DELETE FROM `lab_product_images`     WHERE `product_id` IN (SELECT `id` FROM `tmp_lab_remove`);
DELETE FROM `lab_product_aliases`    WHERE `product_id` IN (SELECT `id` FROM `tmp_lab_remove`);

-- 3) The products themselves. ------------------------------------------------
DELETE FROM `lab_products` WHERE `id` IN (SELECT `id` FROM `tmp_lab_remove`);

-- 4) Sweep dictionary rows nothing references any more. ----------------------
--    These are shared M:N targets, so cascade never touches them; left behind
--    they pad admin category dropdowns and parameter counts with dead entries.
DELETE par FROM `lab_parameters` par
 WHERE NOT EXISTS (
     SELECT 1 FROM `lab_product_parameters` lpp WHERE lpp.`parameter_id` = par.`id`
 );

DELETE c FROM `lab_categories` c
 WHERE NOT EXISTS (
     SELECT 1 FROM `lab_product_categories` lpc WHERE lpc.`category_id` = c.`id`
 );

DROP TEMPORARY TABLE IF EXISTS `tmp_lab_remove`;

-- 5) Verify: all of these should return 0 rows. ------------------------------
SELECT COUNT(*) AS should_be_zero FROM `lab_products`
 WHERE UPPER(TRIM(REGEXP_REPLACE(`name`, '[[:space:]]+', ' '))) IN (
    'COMPLETE THYROID CHECKUP', 'AAROGYAM WINTER BASIC WITH UTSH',
    'AAROGYAM BASIC 2 WITH UTSH', 'AAROGYAM BASIC 1 WITH UTSH',
    'AAROGYAM MALE WITH UTSH', 'COAGULATION PROFILE',
    'COVID ANTIBODY IGG (QUANTITATIVE)', 'AAROGYAM WINTER ADVANCED WITH UTSH',
    'AAROGYAM CAMP PROFILE 1', 'AAROGYAM X WITH UTSH BUY1GET1FREE',
    'AFB DRUG SUSCEPTIBILITY - MOTT', 'AAROGYAM CAMP PROFILE 2',
    'WINTER WELLNESS COUPLE PACKAGE WITH VITAMINS', 'BETA THALASSEMIA',
    'COVID ANTIBODIES-TOTAL (CLIA)', 'COVID ANTIBODY - 1',
    'COVID ANTIBODY IGM - ELISA', 'COVID INFECTION MONITORING - ADVANCED',
    'TB - WHOLE GENOME SEQUENCING', 'SPORTS FITNESS - COMPREHENSIVE',
    'SPORTS FITNESS - BASIC', 'SPORTS FITNESS - ADVANCED',
    'DOCTOR RECOMMENDED FULL BODY CHECKUP ADVANCED', 'FT3-FT4-USTSH',
    'FREEDOM HEALTHY COUPLE PACKAGE 2025 ECG', 'FREEDOM HEALTHY PACKAGE 2025 ECG',
    'GLUCOSE TOLERANCE TEST (1 HOUR)', 'GLUCOSE TOLERANCE TEST (2 HOUR)',
    'HEALTHY 2026 COUPLE PACKAGE WITH ECG', 'HEALTHY 2026 PACKAGE WITH ECG',
    'MISCELLANEOUS CULTURE AND SUSCEPTIBILITY',
    'NEONATAL THYROID STIMULATING HORMONE # (TSH)', 'ANTENATAL PROFILE - ADVANCED'
 );
