-- Patient-owned lab report vault (Lab reports tab in the patient panel).
--
-- Patient-uploaded only for today: the doctor panel has no lab module, so
-- there is nothing on the clinic side to share from. The `source` column is
-- kept anyway (only 'upload' is ever written right now) so a future
-- doctor-share phase drops in WITHOUT another migration, exactly the way
-- patient_prescriptions works.
--
-- lab_name / doctor_name are TEXT SNAPSHOTS (not foreign keys) on purpose:
-- most labs are outside eClinicPro entirely, and a record must still open and
-- still read correctly years later regardless of who is on the platform.
--
-- Fresh-install style (no foreign keys) to match the live-server family and
-- prescription patches and avoid errno 150 when parent column types differ.
--
--   phpMyAdmin: select database → Import → this file ONLY.

CREATE TABLE IF NOT EXISTS `patient_lab_reports` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_identity_id`  BIGINT UNSIGNED NOT NULL,           -- whose panel it lives in (patient-owned, forever)
    `family_member_id`   BIGINT UNSIGNED DEFAULT NULL,       -- family_member_identities.id; NULL = self
    `label`              VARCHAR(160)    NOT NULL,           -- "Complete Blood Count — Jul 2026"
    `test_type`          VARCHAR(80)     DEFAULT NULL,       -- free text w/ suggestions: Blood, Urine, X-Ray, MRI…
    `lab_name`           VARCHAR(160)    DEFAULT NULL,       -- text snapshot ("Thyrocare", "SRL Diagnostics")
    `doctor_name`        VARCHAR(160)    DEFAULT NULL,       -- text snapshot (who ordered it), optional
    `notes`              VARCHAR(255)    DEFAULT NULL,       -- patient-typed, optional
    `reported_on`        DATE            DEFAULT NULL,       -- date printed on the report
    `file_path`          VARCHAR(255)    DEFAULT NULL,       -- stored PDF/photo (relative to repo root)
    `file_mime`          VARCHAR(60)     DEFAULT NULL,       -- application/pdf | image/jpeg | image/png | image/webp
    `source`             ENUM('upload','clinic') NOT NULL DEFAULT 'upload',
    `source_visit_id`    BIGINT UNSIGNED DEFAULT NULL,       -- audit only (reserved for clinic shares); NOT a hard FK
    `source_clinic_id`   BIGINT UNSIGNED DEFAULT NULL,       -- audit only (reserved)
    `is_active`          TINYINT(1)      NOT NULL DEFAULT 1, -- soft-delete
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_plr_owner_active` (`owner_identity_id`, `is_active`),
    KEY `idx_plr_owner_member` (`owner_identity_id`, `family_member_id`),
    -- reserved: prevents a future clinic share duplicating the same visit's report
    UNIQUE KEY `uq_plr_clinic_share` (`owner_identity_id`, `source_visit_id`, `source_clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
