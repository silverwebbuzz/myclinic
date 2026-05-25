-- =====================================================================
-- 013_patient_identities.sql
--
-- Adds a global, patient-owned identity layer above the existing clinic-
-- scoped `patients` table.
--
-- The existing `patients` table stays as-is. We add ONE nullable FK column
-- (`identity_id`) so a clinic chart can be linked to a global identity
-- when we know who the person is. Rows without an identity_id keep
-- working exactly as today.
--
-- Why two layers (read this before changing anything):
--   - `patient_identities` = THE PERSON. One row per human, ever.
--     Owned by the patient. Holds data that doesn't change across
--     clinics: blood group, allergies, chronic conditions, DOB.
--   - `patients` = THE CLINIC CHART. One row per (clinic, person).
--     Owned by the clinic. Holds clinic-private data: UHID, notes,
--     insurance, source, referral.
--
-- A walk-in with no phone still works — identity_id stays NULL, chart
-- behaves like before. The moment the same person provides a phone
-- number anywhere in the system, we either find the existing identity
-- or create one and link.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) patient_identities — the global "passport" for a human
-- ---------------------------------------------------------------------
CREATE TABLE patient_identities (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Contact (at least one of phone/email is required at app level)
  phone               VARCHAR(20)  NULL,
  email               VARCHAR(160) NULL,
  phone_verified_at   DATETIME     NULL,
  email_verified_at   DATETIME     NULL,

  -- Core identity (the things that don't change clinic-to-clinic)
  name                VARCHAR(120) NOT NULL,
  dob                 DATE         NULL,
  gender              ENUM('M','F','Other') NULL,
  blood_group         ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NULL,
  veg_type            ENUM('veg','nonveg','vegan','eggetarian') NULL,
  languages           JSON         NULL,             -- ['English','Hindi']

  -- Carried health context (patient-curated, visible to any treating clinic
  -- with consent). Free-text for now; structured tables can come later.
  allergies           TEXT NULL,
  chronic_conditions  TEXT NULL,

  -- Optional avatar (path or external URL)
  photo_path          VARCHAR(255) NULL,

  -- Account state
  source              ENUM('self_signup','clinic_created','imported') NOT NULL DEFAULT 'self_signup',
  is_active           TINYINT(1) NOT NULL DEFAULT 1,

  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  -- Phone/email are the two ways we find an existing person. Both unique
  -- when present (NULL allowed for walk-ins who only gave us one).
  UNIQUE KEY uq_phone (phone),
  UNIQUE KEY uq_email (email),

  KEY idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2) Link the existing `patients` table to the new identity layer
--    (nullable — existing rows stay untouched and keep working)
-- ---------------------------------------------------------------------
ALTER TABLE patients
  ADD COLUMN identity_id BIGINT UNSIGNED NULL AFTER user_id,
  ADD KEY idx_patients_identity (identity_id),
  ADD CONSTRAINT fk_patients_identity
    FOREIGN KEY (identity_id) REFERENCES patient_identities(id) ON DELETE SET NULL;


-- ---------------------------------------------------------------------
-- 3) Sessions for the patient panel (separate from clinic-staff sessions)
-- ---------------------------------------------------------------------
CREATE TABLE patient_sessions (
  id              CHAR(64) NOT NULL,              -- random opaque token
  identity_id     BIGINT UNSIGNED NOT NULL,
  user_agent      VARCHAR(255) NULL,
  ip              VARCHAR(45)  NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at      DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_identity (identity_id),
  KEY idx_expires (expires_at),
  CONSTRAINT fk_patient_sessions_identity
    FOREIGN KEY (identity_id) REFERENCES patient_identities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------
-- 4) OTP codes for phone/email verification
--    (used at signup AND at every passwordless sign-in)
-- ---------------------------------------------------------------------
CREATE TABLE patient_otp_codes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  handle          VARCHAR(160) NOT NULL,          -- phone or email (lowercased)
  channel         ENUM('sms','email') NOT NULL,
  code_hash       CHAR(64) NOT NULL,              -- SHA-256 of the 6-digit code
  attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at      DATETIME NOT NULL,
  consumed_at     DATETIME NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_handle (handle),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------
-- 5) Patient wishlist — saved doctors from the public directory
--    Belongs to the IDENTITY (follows the person across clinics, devices).
-- ---------------------------------------------------------------------
CREATE TABLE patient_wishlist (
  identity_id     BIGINT UNSIGNED NOT NULL,
  doctor_id       BIGINT UNSIGNED NOT NULL,       -- directory_doctors.id
  added_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (identity_id, doctor_id),
  KEY idx_doctor (doctor_id),
  CONSTRAINT fk_wishlist_identity
    FOREIGN KEY (identity_id) REFERENCES patient_identities(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlist_doctor
    FOREIGN KEY (doctor_id) REFERENCES directory_doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


COMMIT;

-- =====================================================================
-- Optional follow-up (NOT run by this migration — do it once you've
-- decided the dedup rules are safe for your data):
--
-- Backfill identity_id on existing `patients` rows by matching phone.
-- This collapses N clinic charts of the same person under one identity.
--
-- INSERT INTO patient_identities (phone, name, dob, gender, blood_group,
--   veg_type, allergies, chronic_conditions, photo_path, source)
-- SELECT phone, MIN(name), MIN(dob), MIN(gender), MIN(blood_group),
--        MIN(veg_type), MIN(allergies), MIN(chronic_conditions),
--        MIN(photo_path), 'imported'
-- FROM patients
-- WHERE phone IS NOT NULL AND phone <> ''
-- GROUP BY phone
-- ON DUPLICATE KEY UPDATE id = id;  -- skip if phone already in identities
--
-- UPDATE patients p
-- JOIN patient_identities pi ON pi.phone = p.phone
-- SET p.identity_id = pi.id
-- WHERE p.identity_id IS NULL;
-- =====================================================================
