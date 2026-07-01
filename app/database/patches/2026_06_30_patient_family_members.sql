-- Clinic-shared family snapshots (populated when a patient books an appointment).
-- Private panel data lives in family_member_identities.
--
-- LIVE: use 2026_06_30_family_tables_fresh_install.sql instead (no FK errno 150).
-- Local with matching schema:
--   mysql -u root myclinic < app/database/patches/2026_06_30_family_tables_fresh_install.sql

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
