-- Add blood_group to patient_family_members (idempotent).
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_06_30_patient_family_blood_group.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND COLUMN_NAME = 'blood_group'
);

SET @ddl := IF(
    @col_exists = 0,
    "ALTER TABLE `patient_family_members`
        ADD COLUMN `blood_group` ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL
        AFTER `gender`",
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
