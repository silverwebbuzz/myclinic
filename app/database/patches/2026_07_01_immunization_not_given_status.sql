-- Add "Not Given" status to patient_immunizations (run on live if table already exists).
--   mysql -u USER -p DATABASE < app/database/patches/2026_07_01_immunization_not_given_status.sql

ALTER TABLE `patient_immunizations`
    MODIFY COLUMN `status` ENUM(
        'unknown',
        'due',
        'overdue',
        'given',
        'skipped',
        'not_given'
    ) NOT NULL DEFAULT 'due';
