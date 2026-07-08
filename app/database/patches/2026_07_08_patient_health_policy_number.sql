-- Add health_policy_number to patient_identities (idempotent).
--
-- Replaces the patient-panel "Gov ID (last 4)" field with a full
-- health/insurance policy number. The old gov_id_last4 char(4) column is
-- left in place (untouched) so no existing data is lost; the UI simply
-- stops writing to it.
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_07_08_patient_health_policy_number.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_identities'
      AND COLUMN_NAME = 'health_policy_number'
);

SET @ddl := IF(
    @col_exists = 0,
    "ALTER TABLE `patient_identities`
        ADD COLUMN `health_policy_number` VARCHAR(40) DEFAULT NULL
        AFTER `gov_id_last4`",
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
