-- =====================================================================
-- Workforce Health — Phase 1D-lite: test reports uploaded by the employee or the clinic
-- Design: document/workforce-health-phase1-design.md §5 (1D-lite)
-- Run AFTER 2026_11_21_workforce_phase1c.sql. Safe to run twice.
--
-- The report FILE and its details are clinical: they live in occ_case_reports,
-- which company code may never query (CorpFirewallGuardTest). The company only
-- sees corp_screening_cases.report_received_at ("Report received").
-- =====================================================================

CREATE TABLE IF NOT EXISTS `occ_case_reports` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `employment_id`    BIGINT UNSIGNED NOT NULL,
    `case_id`          BIGINT UNSIGNED NOT NULL,
    `identity_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- employee's patient identity (0 = not linked)
    `uploaded_by`      ENUM('employee','clinic') NOT NULL,
    `uploader_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- patient_identities.id or users.id
    `clinic_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- clinic that uploaded (0 = employee)
    `file_path`        VARCHAR(255)    NOT NULL,                 -- relative to repo root: storage/occ_reports/{company_id}/…
    `file_mime`        VARCHAR(60)     NOT NULL,
    `original_name`    VARCHAR(190)    DEFAULT NULL,
    `lab_name`         VARCHAR(160)    DEFAULT NULL,
    `report_date`      DATE            DEFAULT NULL,
    `note`             VARCHAR(255)    DEFAULT NULL,
    `vault_report_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- copy in patient_lab_reports (0 = none)
    `is_active`        TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`       DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company_case` (`company_id`, `case_id`),
    KEY `idx_identity` (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- corp_screening_cases.report_received_at — "Report received" for the company (no file, no values)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corp_screening_cases' AND COLUMN_NAME = 'report_received_at');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `corp_screening_cases` ADD COLUMN `report_received_at` DATETIME DEFAULT NULL AFTER `completion_source`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
