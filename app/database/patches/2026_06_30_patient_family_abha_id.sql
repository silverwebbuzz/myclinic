-- Add abha_id to patient_family_members (idempotent).
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_06_30_patient_family_abha_id.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND COLUMN_NAME = 'abha_id'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `patient_family_members`
        ADD COLUMN `abha_id` VARCHAR(20) DEFAULT NULL
        AFTER `blood_group`',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
