-- =====================================================================
-- silverwebbuzz_in_myclinic_specialty_columns.sql
--
-- Widens specialty-related ENUM columns so the new 17 specialties
-- (diabetology, endocrinology, plastic_surgery, sexology, etc.) save
-- without needing future schema migrations.
--
-- The fetcher already writes these values to directory_doctors.specialty
-- (which is VARCHAR(40), no issue). The blocker was tenants.specialty
-- which is an ENUM of just 7 values from the original schema.
--
-- Safe to run. Existing rows preserved.
-- =====================================================================

-- 1. tenants.specialty — the clinic's primary modality.
--    Convert from ENUM to VARCHAR(40). Existing values ('gp', 'homeopathy', etc.)
--    are preserved as strings.
ALTER TABLE `tenants`
    MODIFY `specialty` VARCHAR(40) NOT NULL DEFAULT 'gp';

-- 2. specialty_configs.prescription_mode — STAYS ENUM.
--    The 4 modes (allopathic, homeopathic, dental, both) cover every
--    real prescription format. Even cardiologists write allopathic Rx.
--    Sexology / diabetology / endocrinology / plastic surgery → all 'allopathic'.
--    Ayurveda / homeo / unani / siddha / naturopathy → 'homeopathic' or 'both'.
--    No change needed.

-- 3. prescriptions.mode — STAYS ENUM.
--    Per-prescription mode flag. Same reasoning as above.
--    No change needed.

-- 4. directory_doctors.specialty — already VARCHAR(40).
--    No change needed.

-- 5. users.specialization — already VARCHAR(100) free-text.
--    No change needed.
