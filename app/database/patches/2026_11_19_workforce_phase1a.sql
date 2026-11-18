-- =====================================================================
-- Workforce Health — Phase 1A: workforce structure + employees (2026-11-19)
--
-- Design: document/workforce-health-phase1-design.md §2
-- Requires: 2026_11_18_workforce_phase0.sql + _seed.sql
--
--   corp_branches               work locations (may be in another country)
--   corp_departments            departments / cost centres
--   corp_job_roles              job titles + risk level (screening rules hang off these in 1B)
--   corp_employments           the company's record of one person working for it
--   corp_employment_events      join / transfer / role change / terminate … history
--   corp_employee_imports       one bulk-import file
--   corp_employee_import_rows   its rows after the dry-run check
--
-- Same rules as Phase 0: DATETIMEs are UTC written by PHP, no FKs,
-- system rows use 0 not NULL, safe to run more than once.
--   phpMyAdmin: select database → Import → this file.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `corp_branches` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`    BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `code`          VARCHAR(40)  NOT NULL,                     -- company's own short code ('JAFZ-YARD')
    `type`          ENUM('office','factory','warehouse','site','yard','vessel','clinic','other') NOT NULL DEFAULT 'office',
    `country_code`  CHAR(2)      NOT NULL,                     -- may differ from the company's country
    `region_id`     INT UNSIGNED DEFAULT NULL,
    `city`          VARCHAR(100) DEFAULT NULL,
    `address_line1` VARCHAR(200) DEFAULT NULL,
    `address_line2` VARCHAR(200) DEFAULT NULL,
    `postal_code`   VARCHAR(20)  DEFAULT NULL,
    `timezone`      VARCHAR(50)  NOT NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL,
    `updated_at`    DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_company_code` (`company_id`, `code`),
    KEY `idx_company_active` (`company_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_departments` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `name`         VARCHAR(150) NOT NULL,
    `code`         VARCHAR(40)  DEFAULT NULL,
    `cost_centre`  VARCHAR(60)  DEFAULT NULL,
    `head_name`    VARCHAR(120) DEFAULT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL,
    `updated_at`   DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_company_name` (`company_id`, `name`),
    KEY `idx_company_active` (`company_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_job_roles` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `title`        VARCHAR(150) NOT NULL,
    `code`         VARCHAR(40)  DEFAULT NULL,
    `description`  VARCHAR(500) DEFAULT NULL,
    `risk_level`   ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    `isco_code`    VARCHAR(10)  DEFAULT NULL,                  -- ILO ISCO-08 occupation code, optional
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   DATETIME     NOT NULL,
    `updated_at`   DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_company_title` (`company_id`, `title`),
    KEY `idx_company_active` (`company_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_employments` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `identity_id`         BIGINT UNSIGNED DEFAULT NULL,         -- patient_identities.id (the person's own account)
    `employee_no`         VARCHAR(40)  NOT NULL,
    `full_name`           VARCHAR(160) NOT NULL,                -- as the COMPANY entered it
    `work_email`          VARCHAR(190) DEFAULT NULL,
    `work_phone`          VARCHAR(20)  DEFAULT NULL,            -- E.164; used to find / create the identity
    `dob`                 DATE         DEFAULT NULL,
    `gender`              ENUM('M','F','Other') DEFAULT NULL,
    `nationality`         CHAR(2)      DEFAULT NULL,
    `id_type`             VARCHAR(30)  DEFAULT NULL,            -- 'emirates_id', 'passport', 'national_id' …
    `id_number_enc`       VARCHAR(255) DEFAULT NULL,            -- CorpCrypto; never Aadhaar
    `id_last4`            VARCHAR(4)   DEFAULT NULL,
    `branch_id`           BIGINT UNSIGNED DEFAULT NULL,
    `department_id`       BIGINT UNSIGNED DEFAULT NULL,
    `job_role_id`         BIGINT UNSIGNED DEFAULT NULL,
    `employment_type`     ENUM('employee','contractor','temporary','intern') NOT NULL DEFAULT 'employee',
    `contractor_company`  VARCHAR(160) DEFAULT NULL,
    `status`              ENUM('active','on_leave','terminated') NOT NULL DEFAULT 'active',
    `joined_on`           DATE         DEFAULT NULL,
    `left_on`             DATE         DEFAULT NULL,
    `leave_reason`        VARCHAR(255) DEFAULT NULL,
    `link_status`         ENUM('pending','confirmed','declined') NOT NULL DEFAULT 'pending',
    `link_confirmed_at`   DATETIME     DEFAULT NULL,
    `invited_at`          DATETIME     DEFAULT NULL,
    `created_by`          BIGINT UNSIGNED DEFAULT NULL,         -- corp_users.id (0 = import / system)
    `created_at`          DATETIME     NOT NULL,
    `updated_at`          DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_company_empno` (`company_id`, `employee_no`),
    KEY `idx_identity` (`identity_id`, `link_status`),
    KEY `idx_company_status_branch` (`company_id`, `status`, `branch_id`),
    KEY `idx_company_phone` (`company_id`, `work_phone`),
    KEY `idx_company_jobrole` (`company_id`, `job_role_id`),
    KEY `idx_company_dept` (`company_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_employment_events` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `employment_id`  BIGINT UNSIGNED NOT NULL,
    `event`          ENUM('joined','updated','transferred','role_changed','contract_changed','on_leave','returned',
                          'terminated','rehired','linked','link_declined','invited') NOT NULL,
    `from_json`      TEXT         DEFAULT NULL,
    `to_json`        TEXT         DEFAULT NULL,
    `effective_on`   DATE         DEFAULT NULL,
    `actor_type`     ENUM('corp_user','platform_admin','employee','system') NOT NULL,
    `actor_id`       BIGINT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_employment` (`company_id`, `employment_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_employee_imports` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`    BIGINT UNSIGNED NOT NULL,
    `file_name`     VARCHAR(190) NOT NULL,
    `file_path`     VARCHAR(255) DEFAULT NULL,                  -- storage/corporate_imports/{company}/…; removed after commit
    `status`        ENUM('uploaded','checked','committing','committed','failed','cancelled') NOT NULL DEFAULT 'uploaded',
    `mapping_json`  TEXT         DEFAULT NULL,
    `total_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
    `create_rows`   INT UNSIGNED NOT NULL DEFAULT 0,
    `update_rows`   INT UNSIGNED NOT NULL DEFAULT 0,
    `error_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
    `done_rows`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`    BIGINT UNSIGNED NOT NULL,
    `created_at`    DATETIME     NOT NULL,
    `committed_at`  DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_employee_import_rows` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `import_id`      BIGINT UNSIGNED NOT NULL,
    `company_id`     BIGINT UNSIGNED NOT NULL,
    `row_no`         INT UNSIGNED NOT NULL,
    `data_json`      TEXT         NOT NULL,                     -- cleaned values (ID number encrypted, never plain)
    `action`         ENUM('create','update','skip','error') NOT NULL,
    `errors_json`    TEXT         DEFAULT NULL,
    `employment_id`  BIGINT UNSIGNED DEFAULT NULL,
    `done`           TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_import` (`import_id`, `done`, `row_no`),
    KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure + employee permissions are now built: show them in the role editor.
UPDATE `corp_permissions` SET `phase` = 0
 WHERE `code` IN ('branches.manage', 'departments.manage', 'jobroles.manage',
                  'employees.view', 'employees.manage', 'employees.import', 'employees.export');

-- Branch Manager: 'employees.view' + 'screening.view_status' + 'bookings.manage' + 'reports.view' + 'company.view'
-- already granted in the Phase 0 seed; nothing to change.
