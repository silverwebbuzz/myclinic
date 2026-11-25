-- =====================================================================
-- Workforce Health — Phase 1E: reports, reminders, clinic statements
-- Design: document/workforce-health-phase1-design.md §6
-- Run AFTER 2026_11_22_workforce_phase1d_lite.sql. Safe to run twice. No FKs.
-- =====================================================================

-- Which reminders a company wants (R5). No row = all on.
CREATE TABLE IF NOT EXISTS `corp_reminder_settings` (
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `due_30`       TINYINT(1) NOT NULL DEFAULT 1,
    `due_7`        TINYINT(1) NOT NULL DEFAULT 1,
    `overdue`      TINYINT(1) NOT NULL DEFAULT 1,
    `booking`      TINYINT(1) NOT NULL DEFAULT 1,
    `cert_expiry`  TINYINT(1) NOT NULL DEFAULT 1,
    `hr_digest`    TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`   DATETIME NOT NULL,
    PRIMARY KEY (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every reminder sent, so nothing goes twice (R3).
CREATE TABLE IF NOT EXISTS `corp_reminder_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED NOT NULL,
    `kind`        VARCHAR(30)  NOT NULL,          -- due_30, due_7, overdue, booking, cert_expiry, hr_digest
    `ref_type`    VARCHAR(30)  NOT NULL,          -- case, booking, certificate, user
    `ref_id`      BIGINT UNSIGNED NOT NULL,
    `recipient`   VARCHAR(190) NOT NULL,
    `sent_on`     DATE         NOT NULL,          -- company local date
    `created_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_once` (`company_id`, `kind`, `ref_type`, `ref_id`, `sent_on`),
    KEY `idx_ref` (`company_id`, `kind`, `ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clinic → company monthly statements (records only, R6).
CREATE TABLE IF NOT EXISTS `corp_provider_statements` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `clinic_id`      BIGINT UNSIGNED NOT NULL,
    `statement_no`   VARCHAR(40)   NOT NULL,
    `period_from`    DATE          NOT NULL,
    `period_to`      DATE          NOT NULL,
    `currency_code`  CHAR(3)       NOT NULL,
    `total`          DECIMAL(15,3) NOT NULL DEFAULT 0,
    `lines_count`    INT UNSIGNED  NOT NULL DEFAULT 0,
    `status`         ENUM('draft','sent','paid','disputed','void') NOT NULL DEFAULT 'draft',
    `pdf_path`       VARCHAR(255)  DEFAULT NULL,
    `dispute_note`   VARCHAR(500)  DEFAULT NULL,
    `created_by`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sent_at`        DATETIME      DEFAULT NULL,
    `paid_at`        DATETIME      DEFAULT NULL,
    `created_at`     DATETIME      NOT NULL,
    `updated_at`     DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_no` (`statement_no`),
    KEY `idx_company` (`company_id`, `status`),
    KEY `idx_clinic` (`clinic_id`, `company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_statement_lines` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `statement_id`   BIGINT UNSIGNED NOT NULL,
    `booking_id`     BIGINT UNSIGNED NOT NULL,
    `case_id`        BIGINT UNSIGNED NOT NULL,
    `employment_id`  BIGINT UNSIGNED NOT NULL,
    `exam_type_id`   INT UNSIGNED    NOT NULL,
    `service_date`   DATE            NOT NULL,
    `price`          DECIMAL(15,3)   NOT NULL DEFAULT 0,
    `price_agreed`   TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_case` (`case_id`),                 -- a check is billed once
    KEY `idx_statement` (`company_id`, `statement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports are built: show the permissions in the role editor.
UPDATE `corp_permissions` SET `phase` = 0 WHERE `code` IN ('reports.view', 'reports.export');
