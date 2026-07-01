-- Split family data: private panel rows vs clinic-shared snapshots.
--
--   family_member_identities  — patient panel (private, editable anytime)
--   patient_family_members    — snapshot shared with a clinic when booking
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_06_30_family_member_identities.sql

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
    KEY `idx_fmi_owner_active` (`owner_identity_id`, `is_active`),
    CONSTRAINT `fk_fmi_owner` FOREIGN KEY (`owner_identity_id`)
        REFERENCES `patient_identities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @pfm_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
);

SET @pfm_has_clinic := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND COLUMN_NAME = 'clinic_id'
);

-- One-time copy from legacy patient_family_members (skip if table never existed on this server).
SET @copy_legacy := IF(
    @pfm_exists > 0,
    'INSERT INTO `family_member_identities`
        (`id`, `owner_identity_id`, `relation`, `is_self`, `sort_order`, `name`, `dob`, `gender`, `blood_group`, `abha_id`, `is_active`, `created_at`, `updated_at`)
     SELECT
        `id`, `owner_identity_id`, `relation`, `is_self`, `sort_order`, `name`, `dob`, `gender`, `blood_group`, `abha_id`, `is_active`, `created_at`, `updated_at`
     FROM `patient_family_members` pfm
     WHERE NOT EXISTS (SELECT 1 FROM `family_member_identities` fmi WHERE fmi.id = pfm.id)',
    'SELECT 1'
);
PREPARE stmt FROM @copy_legacy;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Reshape only when old panel table exists but has not been migrated yet.
-- If patient_family_members does not exist, use 2026_06_30_family_tables_fresh_install.sql instead.
SET @reshape := IF(@pfm_exists > 0 AND @pfm_has_clinic = 0, 1, 0);

SET @unlink_patients := IF(@reshape = 1,
    'UPDATE `patients` SET `family_member_id` = NULL WHERE `family_member_id` IS NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @unlink_patients;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @unlink_appts := IF(@reshape = 1,
    'UPDATE `appointments` SET `family_member_id` = NULL WHERE `family_member_id` IS NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @unlink_appts;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @unlink_insurance := IF(@reshape = 1,
    'UPDATE `patient_insurance_policies` SET `family_member_id` = NULL WHERE `family_member_id` IS NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @unlink_insurance;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @unlink_docs := IF(@reshape = 1,
    'UPDATE `patient_owned_documents` SET `family_member_id` = NULL WHERE `family_member_id` IS NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @unlink_docs;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @clear_pfm := IF(@reshape = 1, 'DELETE FROM `patient_family_members`', 'SELECT 1');
PREPARE stmt FROM @clear_pfm;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_clinic := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND COLUMN_NAME = 'clinic_id'
);
SET @ddl_clinic := IF(
    @add_clinic = 0,
    'ALTER TABLE `patient_family_members`
        ADD COLUMN `clinic_id` BIGINT UNSIGNED DEFAULT NULL AFTER `owner_identity_id`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_clinic;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_identity_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND COLUMN_NAME = 'family_member_identity_id'
);
SET @ddl_identity_col := IF(
    @add_identity_col = 0,
    'ALTER TABLE `patient_family_members`
        ADD COLUMN `family_member_identity_id` BIGINT UNSIGNED DEFAULT NULL AFTER `clinic_id`,
        ADD KEY `idx_pfm_clinic_identity` (`clinic_id`, `family_member_identity_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_identity_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_uq := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND INDEX_NAME = 'uq_pfm_clinic_identity'
);
SET @ddl_uq := IF(
    @add_uq = 0,
    'ALTER TABLE `patient_family_members`
        ADD UNIQUE KEY `uq_pfm_clinic_identity` (`clinic_id`, `family_member_identity_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_uq;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_clinic := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND CONSTRAINT_NAME = 'fk_pfm_clinic'
);
SET @ddl_fk_clinic := IF(
    @add_fk_clinic = 0,
    'ALTER TABLE `patient_family_members`
        ADD CONSTRAINT `fk_pfm_clinic` FOREIGN KEY (`clinic_id`)
            REFERENCES `tenants` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_fk_clinic;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_fk_identity := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'patient_family_members'
      AND CONSTRAINT_NAME = 'fk_pfm_identity'
);
SET @ddl_fk_identity := IF(
    @add_fk_identity = 0,
    'ALTER TABLE `patient_family_members`
        ADD CONSTRAINT `fk_pfm_identity` FOREIGN KEY (`family_member_identity_id`)
            REFERENCES `family_member_identities` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_fk_identity;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
