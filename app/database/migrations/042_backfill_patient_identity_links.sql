-- 042_backfill_patient_identity_links.sql
-- One-time data backfill: link existing clinic charts (`patients`) to the
-- global person (`patient_identities`) by phone, so a patient who signed up /
-- logs in on /patient sees their appointments across ALL clinics in their panel.
--
-- WHY THIS IS NEEDED
-- Phones are stored in different shapes across the two layers:
--   - patient_identities.phone : E.164 (e.g. +919374249829) from ecp_normalize_phone
--   - patients.phone           : whatever was typed (e.g. 9374249829, 91 9374...)
-- Exact-match linking missed the same person, so charts created BEFORE the
-- patient signed up (or under a different phone format) kept identity_id = NULL
-- and their appointments never appeared in the patient panel.
--
-- The only key both layers reliably share is the LAST 10 DIGITS. This migration
-- links every NULL chart whose last-10-digits match exactly ONE identity. Charts
-- that are ambiguous (last-10 matches multiple identities) are left untouched to
-- avoid linking the wrong person — those are vanishingly rare and handled at
-- login time.
--
-- Idempotent: only touches rows where identity_id IS NULL. Safe to re-run.
-- Runtime normalization is also fixed (PublicBookingService::resolveIdentityByPhone
-- and ecp_patient_backfill_clinic_charts) so this does not recur for new data.
-- phpMyAdmin: select your app database first.

-- Helper expression: digits-only last 10 of a phone column. Inlined below
-- because MySQL has no stored helper here. Uses nested REPLACE (works on
-- MySQL 5.7/8 and MariaDB) — phones contain no letters.

-- NOTE on COLLATE: `patients.phone` and `patient_identities.phone` were created
-- with only CHARSET=utf8mb4 (no explicit COLLATE), so each inherited whatever the
-- server default was at creation time — and those differed (utf8mb4_unicode_ci vs
-- utf8mb4_general_ci). Comparing them directly raises
--   #1267 Illegal mix of collations ... for operation '='
-- We force BOTH sides of the join key to utf8mb4_unicode_ci so the match works
-- regardless of each column's stored collation.

UPDATE `patients` p
JOIN (
    -- Map each distinct last-10 key to a single identity, but ONLY when that
    -- key is unambiguous (maps to exactly one identity id).
    SELECT
        RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), 10)
            COLLATE utf8mb4_unicode_ci AS l10,
        MIN(id) AS identity_id,
        COUNT(DISTINCT id) AS id_count
    FROM `patient_identities`
    WHERE LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')) >= 10
    GROUP BY l10
    HAVING id_count = 1
) ident
  ON ident.l10 = RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', ''), 10)
       COLLATE utf8mb4_unicode_ci
SET p.identity_id = ident.identity_id
WHERE p.identity_id IS NULL
  AND LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '')) >= 10;
