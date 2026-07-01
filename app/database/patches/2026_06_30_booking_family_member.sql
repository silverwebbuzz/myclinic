-- Link clinic charts and appointments to patient_family_members when booking
-- on behalf of a family member.
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_06_30_booking_family_member.sql

SET @pat_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patients'
      AND COLUMN_NAME = 'family_member_id'
);

SET @pat_ddl := IF(
    @pat_col = 0,
    'ALTER TABLE `patients`
        ADD COLUMN `family_member_id` BIGINT UNSIGNED DEFAULT NULL AFTER `identity_id`,
        ADD KEY `idx_patients_family_member` (`clinic_id`, `family_member_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @pat_ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @appt_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'appointments'
      AND COLUMN_NAME = 'family_member_id'
);

SET @appt_ddl := IF(
    @appt_col = 0,
    'ALTER TABLE `appointments`
        ADD COLUMN `family_member_id` BIGINT UNSIGNED DEFAULT NULL AFTER `patient_id`,
        ADD KEY `idx_appt_family_member` (`family_member_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @appt_ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
