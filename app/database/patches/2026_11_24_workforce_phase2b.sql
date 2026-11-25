-- =====================================================================
-- Workforce Health — Phase 2B: absences, work incidents, return-to-work
-- and post-incident checks.  Design: document/workforce-health-phase2-design.md §2B
-- Run AFTER 2026_11_23_workforce_phase1e.sql. Safe to run twice. No FKs.
-- Company-side, NON-clinical: reason categories and short summaries only,
-- never diagnoses (H3 / H6).
-- =====================================================================

-- Exam types for the ad-hoc checks (same catalogue as everything else).
INSERT IGNORE INTO `oh_exam_types` (`code`, `name`, `kind`, `description`, `sort_order`) VALUES
    ('rtw_assessment',      'Return-to-work fitness assessment', 'exam', 'After an absence: fit to resume the job, with any restrictions', 5),
    ('incident_assessment', 'Post-incident fitness assessment',  'exam', 'After a work incident or exposure: fit to continue the job',     6);

CREATE TABLE IF NOT EXISTS `corp_absences` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `employment_id`    BIGINT UNSIGNED NOT NULL,
    `from_on`          DATE         NOT NULL,
    `to_on`            DATE         DEFAULT NULL,                -- expected / actual return date (NULL = not known yet)
    `reason_category`  ENUM('illness','injury','maternity','other') NOT NULL,
    `note`             VARCHAR(255) DEFAULT NULL,                -- admin note only, no diagnosis
    `case_id`          BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- return-to-work check, 0 = none asked
    `status`           ENUM('active','removed') NOT NULL DEFAULT 'active',
    `created_by`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME     NOT NULL,
    `updated_at`       DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company_emp` (`company_id`, `employment_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_incidents` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `ref`              VARCHAR(30)  NOT NULL,                    -- INC-2611-0001
    `employment_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- 0 = nobody named (e.g. near miss)
    `branch_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `occurred_on`      DATE         NOT NULL,
    `type`             ENUM('injury','near_miss','exposure','illness_at_work','other') NOT NULL,
    `days_lost`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `summary`          VARCHAR(1000) NOT NULL,                   -- what happened, no medical detail
    `case_id`          BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- post-incident check, 0 = none
    `status`           ENUM('open','closed','removed') NOT NULL DEFAULT 'open',
    `created_by`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME     NOT NULL,
    `updated_at`       DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ref` (`company_id`, `ref`),
    KEY `idx_company` (`company_id`, `status`, `occurred_on`),
    KEY `idx_company_emp` (`company_id`, `employment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission to record incidents (HR, HSE, Company Admin).
INSERT IGNORE INTO `corp_permissions` (`code`, `module`, `label`, `is_clinical`, `phase`, `sort_order`) VALUES
    ('incidents.manage', 'hse', 'Record absences, work incidents and return-to-work checks', 0, 0, 215);
INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, 'incidents.manage' FROM `corp_roles` r WHERE r.company_id = 0 AND r.code IN ('company_admin', 'hr_manager', 'hse_manager');
