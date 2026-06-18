-- 032_drop_founding_clinic.sql
-- Remove the founding-clinic feature. It was an admin-only flag that displayed
-- a "₹999 locked" rate but never fed any pricing/checkout logic (no code read
-- Plan::isFoundingClinic()), so dropping it changes no billing behaviour.
-- Discounts, if needed later, will be handled separately.
--
-- Drops: founding_clinic_state table + tenants.is_founding_clinic and the two
-- founding_clinic_locked_* columns (and their index).
-- Safe to re-run: every drop is guarded on information_schema.
-- phpMyAdmin: select your app database in the left sidebar first.

-- 1. Drop the index on is_founding_clinic (must go before the column).
SET @i := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants'
             AND INDEX_NAME = 'idx_tenants_founding');
SET @s := IF(@i > 0, 'ALTER TABLE tenants DROP INDEX idx_tenants_founding', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2. Drop founding_clinic_locked_until.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants'
             AND COLUMN_NAME = 'founding_clinic_locked_until');
SET @s := IF(@c > 0, 'ALTER TABLE tenants DROP COLUMN founding_clinic_locked_until', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 3. Drop founding_clinic_locked_at.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants'
             AND COLUMN_NAME = 'founding_clinic_locked_at');
SET @s := IF(@c > 0, 'ALTER TABLE tenants DROP COLUMN founding_clinic_locked_at', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 4. Drop is_founding_clinic.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants'
             AND COLUMN_NAME = 'is_founding_clinic');
SET @s := IF(@c > 0, 'ALTER TABLE tenants DROP COLUMN is_founding_clinic', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 5. Drop the single-row state table.
DROP TABLE IF EXISTS founding_clinic_state;

-- Verify (both should return 0 rows):
SELECT COLUMN_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants'
   AND COLUMN_NAME LIKE 'founding%' OR COLUMN_NAME = 'is_founding_clinic';
SELECT TABLE_NAME FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'founding_clinic_state';
