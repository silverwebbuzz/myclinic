-- =====================================================================
-- Workforce Health — Phase 1C: clinics, bookings, fitness certificates
-- Design: document/workforce-health-phase1-design.md §4
-- Run AFTER 2026_11_20_workforce_phase1b.sql. Safe to run twice.
-- No foreign keys (same rule as Phase 0/1A/1B); "none" = 0.
-- =====================================================================

-- A company's clinic network: request (company) + accept (clinic owner). C2.
CREATE TABLE IF NOT EXISTS `corp_provider_links` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`      BIGINT UNSIGNED NOT NULL,
    `clinic_id`       BIGINT UNSIGNED NOT NULL,                 -- tenants.id
    `status`          ENUM('requested','active','declined','ended') NOT NULL DEFAULT 'requested',
    `branch_ids`      VARCHAR(2000) DEFAULT NULL,               -- JSON [ids]; NULL = all branches
    `note`            VARCHAR(500)  DEFAULT NULL,               -- company's message to the clinic
    `requested_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- corp_users.id
    `responded_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- users.id (clinic)
    `responded_at`    DATETIME      DEFAULT NULL,
    `ended_by`        VARCHAR(20)   DEFAULT NULL,               -- company | clinic | admin
    `ended_reason`    VARCHAR(500)  DEFAULT NULL,
    `created_at`      DATETIME      NOT NULL,
    `updated_at`      DATETIME      NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_company_clinic` (`company_id`, `clinic_id`),
    KEY `idx_clinic` (`clinic_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agreed prices (records only, no money moves). C3.
CREATE TABLE IF NOT EXISTS `corp_rate_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`      BIGINT UNSIGNED NOT NULL,
    `link_id`         BIGINT UNSIGNED NOT NULL,
    `exam_type_id`    INT UNSIGNED    NOT NULL,
    `price`           DECIMAL(15,3)   NOT NULL,
    `currency_code`   CHAR(3)         NOT NULL,
    `valid_from`      DATE            DEFAULT NULL,
    `valid_to`        DATE            DEFAULT NULL,
    `created_by`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`      DATETIME        NOT NULL,
    `updated_at`      DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_link_exam` (`link_id`, `exam_type_id`),
    KEY `idx_company` (`company_id`, `link_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One booking = one employee at one clinic appointment, covering one or more cases. C4 (used from 1C-2).
CREATE TABLE IF NOT EXISTS `corp_bookings` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `employment_id`    BIGINT UNSIGNED NOT NULL,
    `clinic_id`        BIGINT UNSIGNED NOT NULL,
    `doctor_id`        BIGINT UNSIGNED NOT NULL,                -- users.id
    `patient_id`       BIGINT UNSIGNED NOT NULL,                -- clinic chart (patients.id)
    `appointment_id`   BIGINT UNSIGNED NOT NULL,
    `scheduled_at`     DATETIME        NOT NULL,                -- clinic local time, as appointments.scheduled_at
    `status`           ENUM('booked','attended','cancelled','no_show') NOT NULL DEFAULT 'booked',
    `cancelled_reason` VARCHAR(500)    DEFAULT NULL,
    `booked_by`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME        NOT NULL,
    `updated_at`       DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company_status` (`company_id`, `status`, `scheduled_at`),
    KEY `idx_company_employment` (`company_id`, `employment_id`),
    KEY `idx_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doctor registration / licence, entered by the clinic, verified by eClinicPro. C10.
CREATE TABLE IF NOT EXISTS `occ_doctor_credentials` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clinic_id`       BIGINT UNSIGNED NOT NULL,
    `user_id`         BIGINT UNSIGNED NOT NULL,                 -- the doctor (users.id)
    `reg_number`      VARCHAR(60)     NOT NULL,
    `authority`       VARCHAR(150)    NOT NULL,                 -- e.g. Maharashtra Medical Council, DHA
    `country_code`    CHAR(2)         NOT NULL,
    `valid_to`        DATE            DEFAULT NULL,             -- NULL = no expiry stated
    `document_path`   VARCHAR(255)    DEFAULT NULL,             -- storage/occ_credentials/{clinic_id}/…
    `document_name`   VARCHAR(190)    DEFAULT NULL,
    `status`          ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    `review_note`     VARCHAR(255)    DEFAULT NULL,
    `verified_by`     BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- platform_admins.id
    `verified_at`     DATETIME        DEFAULT NULL,
    `created_at`      DATETIME        NOT NULL,
    `updated_at`      DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_clinic_doctor` (`clinic_id`, `user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fitness certificate: ONLY what may be printed / shown to the employer. C8–C13 (used from 1C-3).
CREATE TABLE IF NOT EXISTS `corp_fitness_certificates` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `employment_id`     BIGINT UNSIGNED NOT NULL,
    `booking_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `clinic_id`         BIGINT UNSIGNED NOT NULL,
    `doctor_id`         BIGINT UNSIGNED NOT NULL,
    `visit_id`          BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- clinic-side link only; never joined from the company side
    `cert_no`           VARCHAR(30)     NOT NULL,
    `exam_date`         DATE            NOT NULL,
    `outcome`           ENUM('fit','fit_restricted','temp_unfit','unfit','further_assessment') NOT NULL,
    `restrictions_json` VARCHAR(2000)   DEFAULT NULL,
    `restrictions_note` VARCHAR(500)    DEFAULT NULL,
    `valid_from`        DATE            NOT NULL,
    `valid_to`          DATE            DEFAULT NULL,
    `review_by`         DATE            DEFAULT NULL,
    `checks_json`       VARCHAR(4000)   DEFAULT NULL,             -- exam names only
    `consent_shared`    TINYINT(1)      NOT NULL DEFAULT 0,
    `consent_by`        BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- users.id who recorded it
    `consent_at`        DATETIME        DEFAULT NULL,
    `status`            ENUM('valid','revoked','replaced') NOT NULL DEFAULT 'valid',
    `replaced_by`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `revoked_reason`    VARCHAR(500)    DEFAULT NULL,
    `verify_token`      CHAR(48)        NOT NULL,
    `sha256`            CHAR(64)        NOT NULL,
    `pdf_path`          VARCHAR(255)    DEFAULT NULL,
    `doctor_reg`        VARCHAR(220)    DEFAULT NULL,             -- reg number + authority at signing time
    `signed_at`         DATETIME        NOT NULL,
    `created_at`        DATETIME        NOT NULL,
    `updated_at`        DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`verify_token`),
    UNIQUE KEY `uq_cert_no` (`cert_no`),
    KEY `idx_company_employment` (`company_id`, `employment_id`, `status`),
    KEY `idx_clinic` (`clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Additive changes to existing tables (idempotent)
-- ---------------------------------------------------------------------

-- corp_screening_cases.booking_id — the booking a scheduled case belongs to (0 = none)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corp_screening_cases' AND COLUMN_NAME = 'booking_id');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `corp_screening_cases` ADD COLUMN `booking_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `programme_item_id`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- appointments.source += 'corporate' (appends to whatever values the live column has; skipped if already there or not an ENUM)
SET @t := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'source');
SET @n := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'source');
SET @ddl := IF(@t IS NULL OR @t NOT LIKE 'enum(%' OR @t LIKE '%''corporate''%',
    'SELECT 1',
    CONCAT('ALTER TABLE `appointments` MODIFY `source` ', LEFT(@t, CHAR_LENGTH(@t) - 1), ',''corporate'')',
           IF(@n = 'YES', ' NULL', ' NOT NULL'), ' DEFAULT ''reception'''));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- corp_audit_log.actor_type += 'provider' (clinic staff acting on a company's link / booking)
SET @t := (SELECT COLUMN_TYPE FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corp_audit_log' AND COLUMN_NAME = 'actor_type');
SET @ddl := IF(@t IS NULL OR @t LIKE '%''provider''%',
    'SELECT 1',
    'ALTER TABLE `corp_audit_log` MODIFY `actor_type` ENUM(''corp_user'',''platform_admin'',''employee'',''system'',''provider'') NOT NULL');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Provider and booking permissions are now built: show them in the role editor.
UPDATE `corp_permissions` SET `phase` = 0 WHERE `code` IN ('providers.manage', 'bookings.manage');

-- HR Manager also manages the clinic network (Company Admin already has it).
INSERT IGNORE INTO `corp_role_permissions` (`role_id`, `permission_code`)
SELECT r.id, 'providers.manage' FROM `corp_roles` r WHERE r.company_id = 0 AND r.code = 'hr_manager';
