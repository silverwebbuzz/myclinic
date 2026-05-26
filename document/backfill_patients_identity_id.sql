-- =====================================================================
-- backfill_patients_identity_id.sql
--
-- One-shot: link existing `patients` rows to their `patient_identities`
-- counterpart by phone match. Run AFTER deploying the code changes that
-- start populating `identity_id` for new rows.
--
-- Safe to re-run — only touches rows where identity_id IS NULL.
--
-- Strategy:
--   1. Normalize both sides — strip spaces, dashes, +, and leading "91"
--      so "+91 98765 43210", "9876543210", and "919876543210" all match.
--   2. Match on the last 10 digits (Indian-mobile centric; tweak if you
--      have international patients).
-- =====================================================================

START TRANSACTION;

-- Quick preview — see how many rows we're about to update.
SELECT
    'Patients without identity (phone present)' AS metric,
    COUNT(*) AS n
FROM patients
WHERE identity_id IS NULL AND phone IS NOT NULL AND phone <> ''
UNION ALL
SELECT
    'Patient identities with phone',
    COUNT(*)
FROM patient_identities
WHERE phone IS NOT NULL AND phone <> '';

-- The link. Uses RIGHT(digits-only-phone, 10) on both sides.
-- Force a single collation on both sides — `patients` and
-- `patient_identities` were created with different defaults
-- (general_ci vs unicode_ci) and MySQL refuses cross-collation `=`.
UPDATE patients p
JOIN patient_identities pi
  ON RIGHT(REGEXP_REPLACE(p.phone,  '[^0-9]', ''), 10) COLLATE utf8mb4_unicode_ci
   = RIGHT(REGEXP_REPLACE(pi.phone, '[^0-9]', ''), 10) COLLATE utf8mb4_unicode_ci
SET p.identity_id = pi.id
WHERE p.identity_id IS NULL
  AND p.phone IS NOT NULL  AND p.phone  <> ''
  AND pi.phone IS NOT NULL AND pi.phone <> '';

-- Report what got linked.
SELECT
    'Patients now linked to an identity' AS metric,
    COUNT(*) AS n
FROM patients
WHERE identity_id IS NOT NULL;

COMMIT;
