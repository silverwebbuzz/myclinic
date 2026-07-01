-- Fresh install for live servers (e.g. silverwebbuzz) — NO foreign keys.
-- Avoids errno 150 when parent column types differ (INT vs BIGINT, etc.).
--
-- phpMyAdmin: select database → Import → this file ONLY.
-- Skip: blood_group.sql, abha_id.sql, family_member_identities.sql (reshape).

-- 1) Private panel profiles
CREATE TABLE IF NOT EXISTS `family_member_identities` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_identity_id` BIGINT UNSIGNED NOT NULL,
    `relation`          VARCHAR(20)     NOT NULL DEFAULT 'other',
    `is_self`           TINYINT(1)      NOT NULL DEFAULT 0,
    `sort_order`        INT             NOT NULL DEFAULT 100,
    `name`              VARCHAR(120)    NOT NULL,
    `dob`               DATE            DEFAULT NULL,
    `gender`            ENUM('M','F','Other') DEFAULT NULL,
    `blood_group`       ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
    `abha_id`           VARCHAR(20)     DEFAULT NULL,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fmi_owner` (`owner_identity_id`),
    KEY `idx_fmi_owner_active` (`owner_identity_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Clinic snapshots (filled when patient books)
CREATE TABLE IF NOT EXISTS `patient_family_members` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `clinic_id`                   BIGINT UNSIGNED NOT NULL,
    `family_member_identity_id`   BIGINT UNSIGNED NOT NULL,
    `owner_identity_id`           BIGINT UNSIGNED NOT NULL,
    `relation`                    VARCHAR(20)     NOT NULL DEFAULT 'other',
    `is_self`                     TINYINT(1)      NOT NULL DEFAULT 0,
    `sort_order`                  INT             NOT NULL DEFAULT 100,
    `name`                        VARCHAR(120)    NOT NULL,
    `dob`                         DATE            DEFAULT NULL,
    `gender`                      ENUM('M','F','Other') DEFAULT NULL,
    `blood_group`                 ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') DEFAULT NULL,
    `abha_id`                     VARCHAR(20)     DEFAULT NULL,
    `is_active`                   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pfm_clinic_identity` (`clinic_id`, `family_member_identity_id`),
    KEY `idx_pfm_owner` (`owner_identity_id`),
    KEY `idx_pfm_clinic` (`clinic_id`),
    KEY `idx_pfm_identity` (`family_member_identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Booking links on patients + appointments
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
