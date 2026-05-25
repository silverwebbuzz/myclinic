-- =====================================================================
-- silverwebbuzz_in_myclinic_new.sql
--
-- Recommended changes to be applied ON TOP OF the current schema
-- (silverwebbuzz_in_myclinic.sql, exported 2026-05-25).
--
-- This file is INCREMENTAL — run it once after taking a backup.
-- It does NOT recreate existing tables. It:
--   1) Adds new tables that should exist (patient_wishlist,
--      patient_clinic_consents, prescription_shares, audit_data_access).
--   2) Adds columns that are missing on existing tables.
--   3) Adds indexes that will pay for themselves once you have real load.
--   4) Documents (in comments) tables that I think can eventually be
--      dropped or merged — but does NOT drop them, so you can review
--      first.
--
-- Reading order:
--   §1   Patient-owned layer (wishlist, consent, prescription sharing)
--   §2   Indexes worth adding now
--   §3   Identity dedup helper (commented out; review before running)
--   §4   Cleanup candidates (review notes only, no destructive SQL)
--
-- Safety:
--   - Every statement is idempotent where SQL allows (IF NOT EXISTS).
--   - No DROPs. No data deletion. No destructive ALTERs.
--   - Wrap your run in a transaction:  START TRANSACTION; … ; COMMIT;
-- =====================================================================


-- =====================================================================
-- §1 — Patient-owned layer
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1.1 patient_wishlist — "saved doctors" from the public directory.
--     Belongs to the IDENTITY, so it follows the patient across
--     devices, browsers, and clinic visits.
--     App-level limit of 5 is enforced in PHP, not the DB, so we don't
--     reject legitimate adds during a race.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patient_wishlist` (
  `identity_id` bigint(20) UNSIGNED NOT NULL,
  `doctor_id`   bigint(20) UNSIGNED NOT NULL,    -- directory_doctors.id
  `note`        varchar(200) DEFAULT NULL,        -- "for mom's diabetes follow-up"
  `added_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`identity_id`, `doctor_id`),
  KEY `idx_wishlist_doctor` (`doctor_id`),
  CONSTRAINT `fk_pwl_identity`
    FOREIGN KEY (`identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pwl_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `directory_doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 1.2 patient_clinic_consents — who can see the patient's identity data
--     "I, the patient, allow Clinic X to read my blood group / allergies /
--      chronic conditions until I revoke."
--     This is the DPDP Act / HIPAA-compliant way to share identity data.
--     Without a row here, a clinic must collect everything fresh — same
--     as today.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `patient_clinic_consents` (
  `identity_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id`   bigint(20) UNSIGNED NOT NULL,
  `scope`       longtext DEFAULT NULL
                CHECK (json_valid(`scope`)),     -- {"basic":1,"allergies":1,"chronic":1,"prescriptions":1}
  `granted_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at`  datetime DEFAULT NULL,
  `granted_by_ip` varchar(45) DEFAULT NULL,
  `granted_by_user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`identity_id`, `clinic_id`),
  KEY `idx_consent_clinic` (`clinic_id`),
  KEY `idx_consent_revoked` (`revoked_at`),
  CONSTRAINT `fk_pcc_identity`
    FOREIGN KEY (`identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pcc_clinic`
    FOREIGN KEY (`clinic_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 1.3 prescription_shares — explicit record of "doctor shared this
--     prescription with the patient." Lets a patient pull up their
--     prescription history in the patient panel without exposing the
--     entire clinic chart.
--     The prescription itself stays in `prescriptions` (clinic-owned).
--     This table only records the share action + access token.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prescription_shares` (
  `id`              bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `prescription_id` bigint(20) UNSIGNED NOT NULL,
  `identity_id`     bigint(20) UNSIGNED NULL,        -- known patient
  `share_token`     char(48) NOT NULL,                -- for unknown-patient links / SMS
  `shared_at`       timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at`      datetime DEFAULT NULL,            -- NULL = never
  `revoked_at`      datetime DEFAULT NULL,
  `last_viewed_at`  datetime DEFAULT NULL,
  `view_count`      int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_share_token` (`share_token`),
  KEY `idx_share_prescription` (`prescription_id`),
  KEY `idx_share_identity` (`identity_id`),
  CONSTRAINT `fk_psh_prescription`
    FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psh_identity`
    FOREIGN KEY (`identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 1.4 audit_data_access — DPDP Act §11 requires you to log who read
--     patient personal data. This is the minimum-viable audit trail:
--     identity row read by which user/clinic, when, why.
--     Append-only; never UPDATE or DELETE rows here (handle retention
--     via a separate archive job, not in-place edits).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_data_access` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `identity_id`   bigint(20) UNSIGNED NOT NULL,
  `accessed_by_user_id`   bigint(20) UNSIGNED DEFAULT NULL,
  `accessed_by_clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action`        enum('view','export','print','share') NOT NULL,
  `fields`        longtext DEFAULT NULL
                  CHECK (json_valid(`fields`)),    -- ["allergies","blood_group"]
  `purpose`       varchar(120) DEFAULT NULL,        -- "appointment_check_in"
  `ip`            varchar(45)  DEFAULT NULL,
  `user_agent`    varchar(255) DEFAULT NULL,
  `accessed_at`   timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_identity_time` (`identity_id`, `accessed_at`),
  KEY `idx_audit_clinic_time` (`accessed_by_clinic_id`, `accessed_at`),
  CONSTRAINT `fk_ada_identity`
    FOREIGN KEY (`identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- §2 — Indexes worth adding now
--      All ADD INDEX statements use a name; if MariaDB reports "duplicate
--      key" you can safely skip that statement.
-- =====================================================================

-- 2.1 Patient lookup by phone across the entire system (not just one
--     clinic). Used by the identity-linker when a walk-in is registered.
ALTER TABLE `patients`
  ADD INDEX `idx_patients_phone_global` (`phone`);

-- 2.2 Find all clinic charts for one identity in one shot.
--     (identity_id is already a FK so this just makes the lookup fast.)
ALTER TABLE `patients`
  ADD INDEX `idx_patients_identity_clinic` (`identity_id`, `clinic_id`);

-- 2.3 directory_doctors: the public directory is sorted by quality_score
--     in the find-a-doctor query — give it an index.
ALTER TABLE `directory_doctors`
  ADD INDEX `idx_dir_active_quality` (`is_active`, `status`, `quality_score`);

-- 2.4 directory_doctors: the new doctor_name column should be searchable.
ALTER TABLE `directory_doctors`
  ADD INDEX `idx_dir_doctor_name` (`doctor_name`);


-- =====================================================================
-- §3 — Identity dedup helper (COMMENTED OUT)
--      Run this AFTER you spot-check a sample of phone duplicates in
--      `patients`. The script:
--        a) creates an identity row per distinct verified phone
--        b) links every clinic chart with that phone to that identity
--      Safe to re-run — `ON DUPLICATE KEY UPDATE id = id` is a no-op for
--      already-linked rows.
--
--      Before running:
--        SELECT phone, COUNT(*) AS clinics
--        FROM patients
--        WHERE phone IS NOT NULL AND phone <> ''
--        GROUP BY phone
--        HAVING clinics > 1
--        ORDER BY clinics DESC LIMIT 50;
--      That preview shows you the worst dedup cases — verify a few are
--      actually the same person before unlocking the block below.
-- =====================================================================
/*
INSERT INTO patient_identities
  (phone, name, dob, gender, blood_group, veg_type,
   allergies, chronic_conditions, photo_path, source)
SELECT
  p.phone,
  MIN(p.name),
  MIN(p.dob),
  MIN(p.gender),
  MIN(p.blood_group),
  MIN(p.veg_type),
  MIN(p.allergies),
  MIN(p.chronic_conditions),
  MIN(p.photo_path),
  'imported'
FROM patients p
WHERE p.phone IS NOT NULL AND p.phone <> ''
GROUP BY p.phone
ON DUPLICATE KEY UPDATE id = id;

UPDATE patients p
JOIN patient_identities pi ON pi.phone = p.phone
SET p.identity_id = pi.id
WHERE p.identity_id IS NULL;
*/


-- =====================================================================
-- §4 — Cleanup notes (NO destructive SQL — just my recommendations)
-- =====================================================================
--
-- Tables I'd consider revisiting in a future sprint:
--
--   • `otp_tokens` vs `patient_otp_codes`
--       You now have two OTP tables. `otp_tokens` was for staff/users,
--       `patient_otp_codes` is for the new patient panel. Long-term:
--       merge into one `otp_codes` table with a `purpose` enum
--       ('staff_login','patient_login','password_reset',...) and a
--       nullable `user_id` / `identity_id`. Not urgent.
--
--   • `password_reset_tokens` overlaps with `otp_tokens`
--       If you converge on OTP everywhere (recommended), password
--       resets become "send a fresh OTP" and this table can go away.
--
--   • `patient_allergies` (structured) vs `patient_identities.allergies`
--       (free text)
--       Keep both for now — the free-text field on identity is the
--       patient's self-reported summary. The structured `patient_allergies`
--       rows are clinical observations (substance, severity, reaction).
--       Doctor enters the structured row; an UPDATE trigger or app
--       hook copies a human-readable summary back to the identity
--       on patient consent.
--
--   • `patient_photos` is clinic-scoped (before/after photos).
--       Don't merge with `patient_identities.photo_path` — that one
--       is the patient's avatar.
--
--   • `directory_cities`
--       Used for the public directory's location autocomplete. Currently
--       small. Once you have >100k rows it'll need a FULLTEXT index on
--       its display column.
--
--   • `crm_leads`
--       If patient self-signup flows fill this table, add a column
--       `converted_identity_id` so a CRM lead → real patient conversion
--       is traceable.
--
--
-- Things I would NOT change right now:
--   - `patients` keeps its phone/name/dob/blood_group columns even
--     after identity_id links to identity. They act as a clinic-cached
--     copy so a clinic's records still resolve if the patient deletes
--     their global identity. (Right-to-erasure under DPDP Act applies
--     to the identity row, NOT to clinical records the clinic must
--     retain by law.)
--
--   - All "visit", "prescription", "lab_*" tables stay clinic-owned.
--     Sharing happens via `prescription_shares` (§1.3) and the future
--     equivalents for lab results / discharge summaries.
--
-- =====================================================================
-- End of file. Take a backup, run inside a transaction, and verify
-- `SHOW TABLES;` lists the four new tables before letting traffic in.
-- =====================================================================
