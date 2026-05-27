-- =============================================================
-- eClinicPro — Phase 2 Migrations
-- =============================================================
-- Goal: Wire up the new single-screen visit redesign.
--   - Add draft status + auto-save to visits
--   - Extend prescriptions with modern dosing fields
--   - Backfill clinic_settings.visible_modules from specialty
--   - Add indexes for the new query patterns
--
-- Pre-requisite:   Phase 1 migrations applied and verified.
--                  Verify with:
--                    SELECT DISTINCT plan FROM tenants;   -- must be 'standard'
--                    SELECT COUNT(*) FROM clinic_settings; -- must equal tenants count
--
-- Run order:       Block by block, top to bottom.
-- Rollback notes:  Each block reversible. See block-level comments.
-- =============================================================

-- USE silverwebbuzz_in_myclinic;

-- =============================================================
-- BLOCK 1 — Extend visits.status with 'draft' and add auto-save columns
-- =============================================================
-- 'draft' is used by auto-save; never appears in patient history or billing.
-- auto_save_data holds the entire in-progress form as JSON.
-- last_autosave_at is used by the cleanup cron to purge orphaned drafts.
--
-- Existing rows (status in_progress/completed/cancelled) are unaffected.
-- Rollback:
--   ALTER TABLE visits DROP COLUMN last_autosave_at, DROP COLUMN auto_save_data;
--   ALTER TABLE visits MODIFY COLUMN status ENUM('in_progress','completed','cancelled')
--     NOT NULL DEFAULT 'in_progress';
--   (Pre-rollback: ensure no rows hold status='draft' — DELETE or UPDATE them first.)

ALTER TABLE visits
  MODIFY COLUMN status ENUM('draft','in_progress','completed','cancelled')
    NOT NULL DEFAULT 'draft',
  ADD COLUMN auto_save_data LONGTEXT NULL
    CHECK (auto_save_data IS NULL OR JSON_VALID(auto_save_data))
    AFTER specialty_data,
  ADD COLUMN last_autosave_at TIMESTAMP NULL DEFAULT NULL
    AFTER auto_save_data;


-- =============================================================
-- BLOCK 2 — Extend prescriptions for modern dosing
-- =============================================================
-- We KEEP the legacy `frequency` enum (OD/BD/TDS/QID) for back-compat —
-- existing rows still render correctly. New rows write both `frequency`
-- AND `frequency_preset`. Display layer prefers preset when set.
--
-- tapering_steps JSON shape:
--   [
--     {"days": 3, "preset": "1-1-1", "food": "after"},
--     {"days": 3, "preset": "1-0-1", "food": "after"},
--     {"days": 3, "preset": "0-0-1", "food": "after"}
--   ]
-- When tapering_steps IS NOT NULL, frequency_preset + duration_days
-- are ignored at print time.
--
-- Rollback: DROP COLUMN for each new column.

ALTER TABLE prescriptions
  ADD COLUMN frequency_preset VARCHAR(20) DEFAULT NULL
    AFTER frequency,
  ADD COLUMN tapering_steps LONGTEXT NULL
    CHECK (tapering_steps IS NULL OR JSON_VALID(tapering_steps))
    AFTER duration_days,
  ADD COLUMN dose_unit VARCHAR(20) DEFAULT NULL
    AFTER potency,
  ADD COLUMN dose_amount DECIMAL(7,2) DEFAULT NULL
    AFTER dose_unit,
  ADD COLUMN food_timing ENUM('before','after','with','empty','bedtime','any')
    DEFAULT 'any' AFTER dose_amount,
  ADD COLUMN mix_with VARCHAR(40) DEFAULT NULL
    AFTER food_timing;


-- =============================================================
-- BLOCK 3 — Indexes for the new query patterns
-- =============================================================
-- idx_visits_patient_status: "show me this patient's history"
--                            (filters by patient_id, ranks by status)
-- idx_visits_draft_cleanup:  nightly cron to purge stale drafts
-- idx_prescriptions_visit_sort: render medicine list in order

ALTER TABLE visits
  ADD INDEX idx_visits_patient_status (patient_id, status),
  ADD INDEX idx_visits_draft_cleanup (status, last_autosave_at);

ALTER TABLE prescriptions
  ADD INDEX idx_prescriptions_visit_sort (visit_id, sort_order);


-- =============================================================
-- BLOCK 4 — Backfill clinic_settings.visible_modules from specialty
-- =============================================================
-- One-time backfill. Mirrors the PHP config at app/config/specialty_defaults.php.
-- After this runs, every clinic has a populated visible_modules JSON.
-- New clinics created via OnboardingController will populate this column
-- using the PHP config at creation time (Phase 2 application code).
--
-- We only touch rows where visible_modules IS NULL — never overwrite an
-- existing customization. This makes the migration idempotent.
--
-- Rollback:
--   UPDATE clinic_settings SET visible_modules = NULL;

UPDATE clinic_settings cs
JOIN tenants t ON cs.clinic_id = t.id
SET cs.visible_modules = CASE
  -- Pure-talking specialties: only case form, nothing else
  WHEN t.specialty IN ('homeo','ayurveda','siddha','unani','naturopathy',
                       'acupuncturist','physio','psychologist','psychiatrist',
                       'speech','audiologist','eye','ent','sexology')
       THEN JSON_ARRAY('case_specialty')

  -- Visual specialties: photos default on
  WHEN t.specialty IN ('derma','trichology')
       THEN JSON_ARRAY('photos','case_specialty')

  -- Procedure specialties: photos + consent
  WHEN t.specialty IN ('cosmetology','plastic_surgery','dental',
                       'orthodontist','endodontist','implantologist',
                       'prosthodontist','pediatric_dentist')
       THEN JSON_ARRAY('photos','consent','case_specialty')

  -- Cardio family: vitals + labs heavy
  WHEN t.specialty IN ('cardio','endocrinology','nephrology',
                       'hepatology','pulmonology','hematology','oncology')
       THEN JSON_ARRAY('vitals','labs','case_specialty')

  -- Diabetes: everything including diet
  WHEN t.specialty IN ('diabetology')
       THEN JSON_ARRAY('vitals','labs','diet','case_specialty')

  -- Surgery family: vitals + consent
  WHEN t.specialty IN ('general_surgery','neurosurgery','gi_surgery',
                       'bariatric','vascular','spine','urologist',
                       'fertility','andrology')
       THEN JSON_ARRAY('vitals','consent','case_specialty')

  -- Ortho / pain: photos + vitals
  WHEN t.specialty IN ('ortho','sports_medicine','pain_management',
                       'rheumatology')
       THEN JSON_ARRAY('vitals','photos','case_specialty')

  -- Diet/nutrition
  WHEN t.specialty = 'dietitian'
       THEN JSON_ARRAY('vitals','diet')

  -- Critical care / radiology
  WHEN t.specialty IN ('critical_care','radiology')
       THEN JSON_ARRAY('vitals','labs','case_specialty')

  -- Default for everything not listed (GP, peds, gyno, family_medicine,
  -- gastro, allergy, neuro, etc.)
  ELSE JSON_ARRAY('vitals','case_specialty')
END
WHERE cs.visible_modules IS NULL;


-- =============================================================
-- BLOCK 5 — Initialize section_state to empty JSON for all clinics
-- =============================================================
-- Phase 2 app code reads section_state to decide expanded/collapsed.
-- NULL would force constant null-checks; empty JSON is cleaner.
--
-- Rollback:
--   UPDATE clinic_settings SET section_state = NULL WHERE section_state = JSON_OBJECT();

UPDATE clinic_settings
SET section_state = JSON_OBJECT()
WHERE section_state IS NULL;


-- =============================================================
-- BLOCK 6 — Backfill prescription frequency_preset from legacy frequency enum
-- =============================================================
-- For existing prescriptions, derive a sensible preset so the new UI
-- can render them in chip notation. Mapping is approximate but safe
-- (the legacy `frequency` column stays intact as the canonical value).
--
-- Rollback:
--   UPDATE prescriptions SET frequency_preset = NULL;

UPDATE prescriptions
SET frequency_preset = CASE frequency
  WHEN 'OD'      THEN '1-0-0'
  WHEN 'BD'      THEN '1-0-1'
  WHEN 'TDS'     THEN '1-1-1'
  WHEN 'QID'     THEN '1-1-1-1'
  WHEN 'SOS'     THEN 'SOS'
  WHEN 'PRN'     THEN 'SOS'
  WHEN 'weekly'  THEN 'WEEKLY'
  WHEN 'monthly' THEN 'MONTHLY'
  ELSE NULL
END
WHERE frequency_preset IS NULL AND frequency IS NOT NULL;


-- =============================================================
-- BLOCK 7 — Sanity checks (run manually, verify expected output)
-- =============================================================

-- Visit status enum should include 'draft'
-- SHOW COLUMNS FROM visits LIKE 'status';

-- Every clinic should have a populated visible_modules
-- SELECT COUNT(*) AS clinics_total,
--        SUM(CASE WHEN visible_modules IS NOT NULL THEN 1 ELSE 0 END) AS populated
-- FROM clinic_settings;

-- Sample: what does a homeopath clinic see?
-- SELECT t.name, t.specialty, cs.visible_modules
-- FROM clinic_settings cs
-- JOIN tenants t ON t.id = cs.clinic_id
-- WHERE t.specialty = 'homeo' LIMIT 5;

-- Prescriptions backfill check
-- SELECT frequency, frequency_preset, COUNT(*) AS rows
-- FROM prescriptions
-- GROUP BY frequency, frequency_preset
-- ORDER BY frequency;


-- =============================================================
-- END OF PHASE 2 MIGRATIONS
-- =============================================================
-- Next: see phase2_visit_screen.md §9 for the deploy sequence.
