-- =============================================================
-- One-off data migration: unify the homeopathy specialty slug.
-- =============================================================
-- The codebase historically had two slugs for homeopathy: 'homeo'
-- (directory, symptoms, scraper) and 'homeopathy' (portal). We standardised
-- on 'homeopathy' everywhere. This updates already-imported live rows.
--
-- MariaDB-native + idempotent: re-running is a no-op (rows already converted
-- simply won't match the WHERE / JSON predicate again).
-- =============================================================

-- 1. Symptom master: specialties JSON array tag "homeo" → "homeopathy".
--    (290-row seed; only ~20 rows carry the homeo tag.)
UPDATE `symptoms_master`
SET `specialties` = JSON_REPLACE(
        `specialties`,
        JSON_UNQUOTE(JSON_SEARCH(`specialties`, 'one', 'homeo')),
        'homeopathy'
    )
WHERE JSON_SEARCH(`specialties`, 'one', 'homeo') IS NOT NULL;

-- 2. Scraped directory doctors: specialty slug 'homeo' → 'homeopathy'.
UPDATE `directory_doctors`
SET `specialty` = 'homeopathy'
WHERE `specialty` = 'homeo';

-- 3. Tenants that somehow stored 'homeo' (defensive; portal already uses 'homeopathy').
UPDATE `tenants`
SET `specialty` = 'homeopathy'
WHERE `specialty` = 'homeo';

-- 4. specialty_configs has no specialty slug column (only prescription_mode) — nothing to do.
-- 5. doctor_profiles.specialty_primary stores display labels ("Homeopathy"), not slugs — nothing to do.
