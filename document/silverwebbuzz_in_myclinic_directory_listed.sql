-- =====================================================================
-- silverwebbuzz_in_myclinic_directory_listed.sql
--
-- Tracks whether a tenant has been APPROVED for the public directory.
-- Decouples "I have a portal account" (trial signups) from "I'm visible
-- to patients on eclinicpro.com/find-a-doctor".
--
-- New tenants from /register start with is_directory_listed = 0.
-- The Claim / List-me approval flow flips it to 1.
-- =====================================================================

ALTER TABLE `tenants`
    ADD COLUMN `is_directory_listed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
    ADD COLUMN `directory_doctor_id` BIGINT UNSIGNED NULL AFTER `is_directory_listed`,
    ADD KEY `idx_tenants_directory` (`is_directory_listed`),
    ADD CONSTRAINT `fk_tenants_directory_doctor`
        FOREIGN KEY (`directory_doctor_id`) REFERENCES `directory_doctors` (`id`) ON DELETE SET NULL;

-- Backfill: any tenant that already owns a claimed directory listing gets
-- is_directory_listed = 1 + the FK back-pointer. Safe to re-run.
UPDATE `tenants` t
JOIN `directory_doctors` dd ON dd.claimed_tenant_id = t.id
SET t.is_directory_listed = 1,
    t.directory_doctor_id = dd.id
WHERE t.is_directory_listed = 0;

-- =====================================================================
-- Track WHERE each claim request originated. Useful for analytics
-- ("which intake funnel converts best?") and for showing the right
-- status copy back to the user.
-- =====================================================================
ALTER TABLE `doctor_claim_requests`
    ADD COLUMN `source` VARCHAR(40) NOT NULL DEFAULT 'find_a_doctor' AFTER `user_agent`;
