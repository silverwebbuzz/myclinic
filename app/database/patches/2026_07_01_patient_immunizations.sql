-- Pediatric immunization register (patient-level, single source of truth).
-- Run on live:
--   mysql -u USER -p DATABASE < app/database/patches/2026_07_01_patient_immunizations.sql
--
-- Replaces visit-level specialty_data.pediatric_vaccines checklists.

CREATE TABLE IF NOT EXISTS `patient_immunizations` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clinic_id`       BIGINT UNSIGNED NOT NULL,
    `patient_id`      BIGINT UNSIGNED NOT NULL,
    `vaccine_key`     VARCHAR(80)     NOT NULL,
    `age_label`       VARCHAR(40)     NOT NULL DEFAULT '',
    `vaccine_name`    VARCHAR(120)    NOT NULL,
    `due_date`        DATE            NOT NULL,
    `given_date`      DATE            DEFAULT NULL,
    `status`          ENUM('unknown','due','overdue','given','skipped','not_given') NOT NULL DEFAULT 'due',
    `notes`           VARCHAR(255)    DEFAULT NULL,
    `last_visit_id`   BIGINT UNSIGNED DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_patient_immunization` (`patient_id`, `vaccine_key`),
    KEY `idx_pi_clinic_patient_due` (`clinic_id`, `patient_id`, `due_date`),
    KEY `idx_pi_patient_status` (`patient_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional cleanup: remove legacy per-visit vaccine checklists (JSON).
-- Safe to run; only touches rows that had the old field.
UPDATE `visits`
SET `specialty_data` = JSON_REMOVE(`specialty_data`, '$.pediatric_vaccines')
WHERE `specialty_data` IS NOT NULL
  AND JSON_CONTAINS_PATH(`specialty_data`, 'one', '$.pediatric_vaccines');
