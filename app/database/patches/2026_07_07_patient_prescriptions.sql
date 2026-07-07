-- Patient-owned prescription vault (E-prescriptions tab in the patient panel).
--
-- ONE table holds BOTH sources so the data always stays with the patient:
--   source = 'upload' → patient added it themselves (photo of an outside Rx)
--   source = 'clinic' → a doctor on eClinicPro shared it during a visit
--
-- doctor_name / clinic_name are TEXT SNAPSHOTS (not foreign keys) on purpose:
-- if the doctor or clinic later leaves the platform, the patient's record and
-- PDF still open and still read correctly.
--
-- Fresh-install style (no foreign keys) to match the live-server family patches
-- and avoid errno 150 when parent column types differ.
--
--   phpMyAdmin: select database → Import → this file ONLY.

CREATE TABLE IF NOT EXISTS `patient_prescriptions` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_identity_id`  BIGINT UNSIGNED NOT NULL,           -- whose panel it lives in (patient-owned, forever)
    `family_member_id`   BIGINT UNSIGNED DEFAULT NULL,       -- family_member_identities.id; NULL = self
    `label`              VARCHAR(160)    NOT NULL,           -- "Dr. Jayesh — 20 May 2026 — BP"
    `doctor_name`        VARCHAR(160)    DEFAULT NULL,       -- text snapshot
    `clinic_name`        VARCHAR(160)    DEFAULT NULL,       -- text snapshot
    `notes`              VARCHAR(255)    DEFAULT NULL,       -- e.g. reason "BP" (patient-typed, optional)
    `issued_on`          DATE            DEFAULT NULL,       -- date on the prescription
    `file_path`          VARCHAR(255)    DEFAULT NULL,       -- stored PDF/photo (relative to storage root)
    `file_mime`          VARCHAR(60)     DEFAULT NULL,       -- application/pdf | image/jpeg | image/png
    `source`             ENUM('upload','clinic') NOT NULL DEFAULT 'upload',
    `source_visit_id`    BIGINT UNSIGNED DEFAULT NULL,       -- audit only (clinic shares); NOT a hard FK
    `source_clinic_id`   BIGINT UNSIGNED DEFAULT NULL,       -- audit only
    `is_active`          TINYINT(1)      NOT NULL DEFAULT 1, -- soft-delete
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pp_owner_active` (`owner_identity_id`, `is_active`),
    KEY `idx_pp_owner_member` (`owner_identity_id`, `family_member_id`),
    -- prevents a doctor double-sharing the same visit's Rx to the same person
    UNIQUE KEY `uq_pp_clinic_share` (`owner_identity_id`, `source_visit_id`, `source_clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
