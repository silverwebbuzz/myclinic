-- =====================================================================
-- Workforce Health — Phase 0 foundations: schema (2026-11-18)
--
-- Design: document/workforce-health-phase0-design.md §3
--
--   geo_countries          country master (currency, phone, timezone, labels)
--   geo_regions            states / emirates / counties
--   geo_currencies         ISO 4217 currencies (KWD/BHD/OMR = 3 decimals)
--   corp_industries        industry master (axis for screening rule templates)
--   corp_companies         employer accounts (app.eclinicpro.com/company)
--   corp_company_documents trade licence etc. uploaded for approval
--   corp_users             company portal logins (separate from clinic `users`)
--   corp_permissions       permission catalogue
--   corp_roles             system roles (company_id = 0) + company custom roles
--   corp_role_permissions  role → permission
--   corp_user_roles        user → role (+ branch scope from Phase 1)
--   corp_invitations       invite a colleague by email
--   corp_password_resets   forgot-password tokens
--   corp_sessions          one row per login (device list + revoke)
--   corp_audit_log         who did what, per company
--
--   + platform_admins.role / country_codes   (default 'super' = today's behaviour)
--   + tenants.provider_kinds / corporate_enabled  (unused until Phase 1)
--   + platform_settings corp_* keys
--
-- RULES for every corp_* table:
--   * DATETIME columns are UTC, written by PHP (gmdate). This DB session runs
--     in IST, so do NOT use NOW()/CURRENT_TIMESTAMP for them.
--   * Money (from Phase 1) = DECIMAL(15,3) + CHAR(3) currency.
--   * "System" rows use company_id = 0 / scope_id = 0 (never NULL) so the
--     UNIQUE keys hold and re-running the seed can't duplicate them.
--
-- No foreign keys (house style). Safe to run more than once.
--   phpMyAdmin: select database → Import → this file, THEN
--               2026_11_18_workforce_phase0_seed.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- Locale master data
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `geo_countries` (
    `code`              CHAR(2)      NOT NULL,                    -- ISO 3166-1 alpha-2
    `name`              VARCHAR(80)  NOT NULL,
    `market`            ENUM('india','middle_east','africa','europe','other') NOT NULL DEFAULT 'other',
    `phone_code`        VARCHAR(5)   NOT NULL,                    -- '971' (no +)
    `phone_nsn_lengths` VARCHAR(20)  NOT NULL DEFAULT '6,7,8,9,10,11,12', -- allowed national-number lengths, CSV
    `currency_code`     CHAR(3)      NOT NULL,
    `default_timezone`  VARCHAR(50)  NOT NULL,
    `date_format`       VARCHAR(20)  NOT NULL DEFAULT 'd M Y',     -- PHP date() format
    `time_format`       VARCHAR(20)  NOT NULL DEFAULT 'h:i A',
    `week_start`        TINYINT      NOT NULL DEFAULT 1,          -- 0=Sun 1=Mon 6=Sat
    `region_label`      VARCHAR(30)  NOT NULL DEFAULT 'State',    -- State / Emirate / County / Province
    `tax_label`         VARCHAR(20)  DEFAULT NULL,                -- GST / VAT / NULL (no tax)
    `tax_id_label`      VARCHAR(40)  DEFAULT NULL,                -- GSTIN / TRN / VAT number
    `company_id_label`  VARCHAR(60)  DEFAULT NULL,                -- CIN / Trade licence no. / Company number
    `postal_label`      VARCHAR(30)  DEFAULT 'Postal code',       -- PIN code / Postcode / NULL (none)
    `is_active`         TINYINT(1)   NOT NULL DEFAULT 0,          -- offered at sign-up only when 1
    `sort_order`        SMALLINT     NOT NULL DEFAULT 100,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`code`),
    KEY `idx_active` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geo_regions` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `country_code` CHAR(2)      NOT NULL,
    `code`         VARCHAR(10)  DEFAULT NULL,                     -- ISO 3166-2 suffix when known
    `name`         VARCHAR(80)  NOT NULL,
    `timezone`     VARCHAR(50)  DEFAULT NULL,                     -- only if different from the country
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`   SMALLINT     NOT NULL DEFAULT 100,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_country_name` (`country_code`, `name`),
    KEY `idx_country` (`country_code`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `geo_currencies` (
    `code`         CHAR(3)     NOT NULL,                          -- ISO 4217
    `name`         VARCHAR(40) NOT NULL,
    `symbol`       VARCHAR(8)  NOT NULL,                          -- '₹', '£', or the code itself ('AED')
    `minor_units`  TINYINT     NOT NULL DEFAULT 2,
    `is_active`    TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_industries` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(40)  NOT NULL,                           -- 'construction', 'oil_gas'
    `name`       VARCHAR(100) NOT NULL,
    `parent_id`  INT UNSIGNED DEFAULT NULL,
    `isic_code`  VARCHAR(10)  DEFAULT NULL,                       -- UN ISIC Rev.4 section / division
    `sort_order` SMALLINT     NOT NULL DEFAULT 100,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Companies
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `corp_companies` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `public_ref`         VARCHAR(16)  NOT NULL,                   -- 'ECW-7K3M2Q'; never expose id
    `legal_name`         VARCHAR(200) NOT NULL,
    `trade_name`         VARCHAR(200) DEFAULT NULL,
    `country_code`       CHAR(2)      NOT NULL,
    `region_id`          INT UNSIGNED DEFAULT NULL,               -- geo_regions.id
    `city`               VARCHAR(100) DEFAULT NULL,
    `address_line1`      VARCHAR(200) DEFAULT NULL,
    `address_line2`      VARCHAR(200) DEFAULT NULL,
    `postal_code`        VARCHAR(20)  DEFAULT NULL,
    `registration_no`    VARCHAR(80)  DEFAULT NULL,               -- label: geo_countries.company_id_label
    `licence_no`         VARCHAR(80)  DEFAULT NULL,
    `licence_expiry`     DATE         DEFAULT NULL,
    `tax_reg_no`         VARCHAR(40)  DEFAULT NULL,               -- label: geo_countries.tax_id_label
    `industry_id`        INT UNSIGNED DEFAULT NULL,               -- corp_industries.id
    `business_type`      ENUM('private','public_listed','government','ngo','other') DEFAULT NULL,
    `employee_size_band` ENUM('1-49','50-249','250-999','1000-4999','5000+') DEFAULT NULL,
    `contact_name`       VARCHAR(120) DEFAULT NULL,
    `contact_email`      VARCHAR(190) DEFAULT NULL,
    `contact_phone`      VARCHAR(20)  DEFAULT NULL,               -- E.164 '+971501234567'
    `website`            VARCHAR(190) DEFAULT NULL,
    `logo_path`          VARCHAR(255) DEFAULT NULL,
    `timezone`           VARCHAR(50)  NOT NULL,
    `currency_code`      CHAR(3)      NOT NULL,
    `data_region`        VARCHAR(20)  NOT NULL DEFAULT 'in-1',    -- which deployment holds the data
    `status`             ENUM('pending','active','suspended','rejected','closed') NOT NULL DEFAULT 'pending',
    `status_reason`      VARCHAR(255) DEFAULT NULL,
    `approved_by`        BIGINT UNSIGNED DEFAULT NULL,            -- platform_admins.id
    `approved_at`        DATETIME     DEFAULT NULL,               -- UTC
    `trial_ends_at`      DATE         DEFAULT NULL,               -- set on approval
    `trial_extension_granted` TINYINT(1) NOT NULL DEFAULT 0,
    `plan_id`            VARCHAR(40)  DEFAULT NULL,
    `plan_expires_at`    DATE         DEFAULT NULL,               -- set by admin after payment
    `is_demo`            TINYINT(1)   NOT NULL DEFAULT 0,         -- demo seed; excluded from metrics
    `created_at`         DATETIME     NOT NULL,                   -- UTC
    `updated_at`         DATETIME     NOT NULL,                   -- UTC
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ref` (`public_ref`),
    KEY `idx_status_country` (`status`, `country_code`),
    KEY `idx_demo` (`is_demo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_company_documents` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED NOT NULL,
    `doc_type`    ENUM('trade_licence','registration_cert','tax_cert','other') NOT NULL,
    `file_path`   VARCHAR(255) NOT NULL,                          -- repo-root storage/corporate/{company_id}/
    `original_name` VARCHAR(190) DEFAULT NULL,
    `mime_type`   VARCHAR(80)  DEFAULT NULL,
    `size_bytes`  INT UNSIGNED DEFAULT NULL,
    `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `review_note` VARCHAR(255) DEFAULT NULL,
    `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,                   -- platform_admins.id
    `reviewed_at` DATETIME     DEFAULT NULL,
    `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,                   -- corp_users.id
    `created_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Users, roles, permissions
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `corp_users` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `name`              VARCHAR(160) NOT NULL,
    `email`             VARCHAR(190) NOT NULL,                    -- stored lower-case
    `phone`             VARCHAR(20)  DEFAULT NULL,                -- E.164
    `password_hash`     VARCHAR(255) DEFAULT NULL,                -- NULL while status='invited'
    `timezone`          VARCHAR(50)  DEFAULT NULL,                -- NULL = company timezone
    `status`            ENUM('invited','active','disabled') NOT NULL DEFAULT 'active',
    `is_owner`          TINYINT(1)   NOT NULL DEFAULT 0,          -- registering user; can't be removed
    `email_verified_at` DATETIME     DEFAULT NULL,
    `mfa_secret_enc`    VARCHAR(255) DEFAULT NULL,                -- encrypted TOTP secret
    `mfa_enabled_at`    DATETIME     DEFAULT NULL,
    `mfa_last_step`     BIGINT UNSIGNED DEFAULT NULL,             -- last accepted TOTP step (replay block)
    `mfa_recovery`      TEXT         DEFAULT NULL,                -- JSON array of password_hash()ed codes
    `failed_logins`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`      DATETIME     DEFAULT NULL,
    `last_login_at`     DATETIME     DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL,
    `updated_at`        DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),                              -- one login = one company
    KEY `idx_company` (`company_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_permissions` (
    `code`        VARCHAR(60)  NOT NULL,                          -- 'employees.import'
    `module`      VARCHAR(30)  NOT NULL,
    `label`       VARCHAR(120) NOT NULL,
    `is_clinical` TINYINT(1)   NOT NULL DEFAULT 0,                -- only for clinical system roles
    `phase`       TINYINT      NOT NULL DEFAULT 0,                -- hidden in UI until that phase ships
    `sort_order`  SMALLINT     NOT NULL DEFAULT 100,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_roles` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,             -- 0 = system role (all companies)
    `code`        VARCHAR(40)  NOT NULL,
    `name`        VARCHAR(80)  NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `scope_type`  ENUM('company','branch') NOT NULL DEFAULT 'company',
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,                -- permissions not editable by companies
    `is_clinical` TINYINT(1)   NOT NULL DEFAULT 0,                -- may hold is_clinical permissions
    `sort_order`  SMALLINT     NOT NULL DEFAULT 100,
    `created_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_company_code` (`company_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_role_permissions` (
    `role_id`         BIGINT UNSIGNED NOT NULL,
    `permission_code` VARCHAR(60)     NOT NULL,
    PRIMARY KEY (`role_id`, `permission_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_user_roles` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,                        -- denormalised for scoping
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `role_id`    BIGINT UNSIGNED NOT NULL,
    `scope_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0,              -- branch id for branch roles (Phase 1); 0 = whole company
    `granted_by` BIGINT UNSIGNED DEFAULT NULL,                    -- corp_users.id
    `created_at` DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_role_scope` (`user_id`, `role_id`, `scope_id`),
    KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_invitations` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED NOT NULL,
    `email`       VARCHAR(190) NOT NULL,
    `name`        VARCHAR(160) DEFAULT NULL,
    `role_id`     BIGINT UNSIGNED NOT NULL,
    `scope_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `token_hash`  CHAR(64)     NOT NULL,                          -- sha256 of the emailed token
    `invited_by`  BIGINT UNSIGNED NOT NULL,                       -- corp_users.id
    `expires_at`  DATETIME     NOT NULL,                          -- +7 days
    `accepted_at` DATETIME     DEFAULT NULL,
    `revoked_at`  DATETIME     DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token_hash`),
    KEY `idx_company_email` (`company_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_password_resets` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `purpose`    ENUM('reset','verify_email') NOT NULL DEFAULT 'reset',
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,                               -- reset +60 min, verify +48 h
    `used_at`    DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token` (`token_hash`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_sessions` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `user_id`      BIGINT UNSIGNED NOT NULL,
    `jti`          CHAR(32)     NOT NULL,                         -- JWT id
    `mfa_passed`   TINYINT(1)   NOT NULL DEFAULT 0,
    `ip`           VARCHAR(45)  DEFAULT NULL,
    `user_agent`   VARCHAR(255) DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL,
    `last_seen_at` DATETIME     NOT NULL,
    `expires_at`   DATETIME     NOT NULL,
    `revoked_at`   DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_jti` (`jti`),
    KEY `idx_user` (`user_id`, `revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_audit_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,             -- 0 = platform-level event
    `actor_type`  ENUM('corp_user','platform_admin','employee','system') NOT NULL,
    `actor_id`    BIGINT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(60)  NOT NULL,                          -- 'login', 'company_approved', 'role_granted'
    `entity_type` VARCHAR(40)  DEFAULT NULL,
    `entity_id`   BIGINT UNSIGNED DEFAULT NULL,
    `details`     TEXT         DEFAULT NULL,                      -- JSON; never passwords / secrets / tokens
    `ip`          VARCHAR(45)  DEFAULT NULL,
    `user_agent`  VARCHAR(255) DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL,                          -- UTC
    PRIMARY KEY (`id`),
    KEY `idx_company_time` (`company_id`, `created_at`),
    KEY `idx_actor` (`actor_type`, `actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Additive changes to existing tables (idempotent)
-- ---------------------------------------------------------------------

-- platform_admins.role — default 'super' keeps every existing admin a full super admin
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_admins' AND COLUMN_NAME = 'role');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `platform_admins` ADD COLUMN `role` ENUM(''super'',''country_admin'',''billing'',''support'') NOT NULL DEFAULT ''super'' AFTER `name`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- platform_admins.country_codes — JSON ["AE","SA"], only used for country_admin
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_admins' AND COLUMN_NAME = 'country_codes');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `platform_admins` ADD COLUMN `country_codes` VARCHAR(255) DEFAULT NULL AFTER `role`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.provider_kinds — what a provider tenant offers to companies (read from Phase 1)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'provider_kinds');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `tenants` ADD COLUMN `provider_kinds` SET(''clinic'',''lab'') NOT NULL DEFAULT ''clinic''',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.corporate_enabled — provider accepts corporate (employer) work (read from Phase 1)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'corporate_enabled');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `tenants` ADD COLUMN `corporate_enabled` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Settings (admin-editable later at /admin/settings/corporate)
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
    ('corp_trial_days',     '30'),   -- trial length; clock starts when admin approves the company
    ('corp_signup_enabled', '1'),    -- 0 hides /company/register
    ('corp_mfa_required',   '1');    -- 1 = every company user must set up an authenticator app
