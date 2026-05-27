-- =============================================================
-- eClinicPro — Phase 4 Migrations
-- =============================================================
-- Goal:
--   - follow_ups table + reasons lookup
--   - diet_templates library (12 system-shipped, plus clinic/personal)
--   - Link diet_plans → diet_templates
--   - Stripe column cleanup (4 columns across 4 tables)
--   - Rename module_catalog price columns (drop _usd suffix)
--
-- Pre-requisite:   Phases 1-3 migrations applied and verified.
--                  Verify with:
--                    SHOW TABLES LIKE 'symptoms_master';
--                    SHOW TABLES LIKE 'prescription_templates';
--                    SHOW COLUMNS FROM clinic_settings LIKE 'visible_modules';
--
-- Run order:       Block by block, top to bottom.
-- Rollback notes:  See each block.
-- Seed file:       phase4_diet_seed.sql (separate, run after Block 3)
-- =============================================================

-- USE silverwebbuzz_in_myclinic;

-- =============================================================
-- BLOCK 1 — Create follow_ups table
-- =============================================================
-- Separate from visits.follow_up_date (which stays as legacy free text).
-- This table is the canonical state machine for follow-ups.
--
-- Rollback: DROP TABLE follow_ups.

CREATE TABLE follow_ups (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  patient_id BIGINT(20) UNSIGNED NOT NULL,
  visit_id BIGINT(20) UNSIGNED NOT NULL,
  doctor_id BIGINT(20) UNSIGNED NULL,
  due_date DATE NOT NULL,
  reason VARCHAR(40) DEFAULT NULL,
  reason_other TEXT DEFAULT NULL,
  status ENUM('pending','done','missed','rescheduled','cancelled')
    NOT NULL DEFAULT 'pending',
  appointment_id BIGINT(20) UNSIGNED NULL,
  rescheduled_to_id BIGINT(20) UNSIGNED NULL,
  completed_visit_id BIGINT(20) UNSIGNED NULL,
  reminder_sent_at TIMESTAMP NULL,
  reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fu_clinic_due (clinic_id, status, due_date),
  INDEX idx_fu_patient (patient_id, status),
  INDEX idx_fu_visit (visit_id),
  CONSTRAINT fk_fu_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_patient
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_doctor
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_appointment
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_rescheduled
    FOREIGN KEY (rescheduled_to_id) REFERENCES follow_ups(id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_completed
    FOREIGN KEY (completed_visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 2 — Create follow_up_reasons lookup + seed 6 system reasons
-- =============================================================
-- clinic_id NULL = system-default reason available to all clinics.
-- Per-clinic customs go in same table with clinic_id set.
--
-- Rollback: DROP TABLE follow_up_reasons.

CREATE TABLE follow_up_reasons (
  reason_key VARCHAR(40) NOT NULL PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_fur_clinic_active (clinic_id, is_active, sort_order),
  CONSTRAINT fk_fur_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO follow_up_reasons (reason_key, label, clinic_id, sort_order) VALUES
  ('check_progress',         'Check progress',          NULL, 10),
  ('retest_labs',            'Retest labs',             NULL, 20),
  ('continue_treatment',     'Continue treatment',      NULL, 30),
  ('post_procedure_review',  'Post-procedure review',   NULL, 40),
  ('acute_followup',         'Acute episode follow-up', NULL, 50),
  ('other',                  'Other (specify)',         NULL, 99)
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  sort_order = VALUES(sort_order);


-- =============================================================
-- BLOCK 3 — Create diet_templates table
-- =============================================================
-- Scope rules:
--   clinic_id NULL + doctor_id NULL = system template (shipped)
--   clinic_id only                  = clinic-wide template
--   clinic_id + doctor_id           = doctor's personal template
--
-- plan_json shape mirrors existing diet_plans.plan_json.
-- Rollback: DROP TABLE diet_templates.

CREATE TABLE diet_templates (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT(20) UNSIGNED NULL,
  doctor_id BIGINT(20) UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(240) DEFAULT NULL,
  condition_tag VARCHAR(60) DEFAULT NULL,
  veg_type ENUM('veg','nonveg','vegan','eggetarian','any') DEFAULT 'any',
  plan_json LONGTEXT NOT NULL
    CHECK (JSON_VALID(plan_json)),
  use_count INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_diet_clinic_doctor (clinic_id, doctor_id, is_active),
  INDEX idx_diet_condition (condition_tag),
  CONSTRAINT fk_dt_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_dt_doctor
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 4 — Link diet_plans → diet_templates
-- =============================================================
-- When a doctor applies a template, the resulting diet_plans row
-- gets template_id set. Lets us measure template usage and rebuild
-- if a template changes (we don't auto-propagate, but we can offer
-- "this plan came from template X — refresh?").
--
-- Rollback: ALTER TABLE diet_plans DROP COLUMN template_id;

ALTER TABLE diet_plans
  ADD COLUMN template_id BIGINT(20) UNSIGNED NULL AFTER veg_type,
  ADD INDEX idx_diet_plans_template (template_id),
  ADD CONSTRAINT fk_diet_plans_template
    FOREIGN KEY (template_id) REFERENCES diet_templates(id) ON DELETE SET NULL;


-- =============================================================
-- BLOCK 5 — Add shareable token to diet_plans (for copy-link fallback)
-- =============================================================
-- When patient_connect add-on is NOT active, share works via a signed
-- public URL that the doctor copies into their own WhatsApp.
-- Token is null until generated. Expires 24h after generation.
--
-- Rollback: ALTER TABLE diet_plans DROP COLUMN share_token, DROP COLUMN share_token_expires;

ALTER TABLE diet_plans
  ADD COLUMN share_token VARCHAR(32) DEFAULT NULL AFTER pdf_path,
  ADD COLUMN share_token_expires TIMESTAMP NULL AFTER share_token,
  ADD UNIQUE INDEX uniq_diet_share_token (share_token);


-- =============================================================
-- BLOCK 6 — Visit screen completion checkbox tracking
-- =============================================================
-- When a doctor confirms "this visit closes the prior follow-up,"
-- we set follow_ups.completed_visit_id. Already in the table from
-- Block 1. No additional schema needed — this block is just a
-- placeholder for documentation flow.


-- =============================================================
-- BLOCK 7 — Final cleanup: drop Stripe columns
-- =============================================================
-- India-only product. Razorpay only. Stripe columns are dead weight.
-- Confirm zero application reads before running:
--   grep -rln 'stripe_customer_id\|stripe_sub_item_id\|stripe_payment_id\|stripe_invoice_id' app/
-- (expected output: empty after the code cleanup step in §11)
--
-- Rollback: re-ADD COLUMN (data is lost; only test if there was a typo).

ALTER TABLE tenants DROP COLUMN stripe_customer_id;
ALTER TABLE clinic_modules DROP COLUMN stripe_sub_item_id;
ALTER TABLE payments DROP COLUMN stripe_payment_id;

-- 1048 = stripe_invoice_id — find its table and drop
-- (line 1048 in the original schema dump; identify table by inspecting near that line)
-- ALTER TABLE <table_at_line_1048> DROP COLUMN stripe_invoice_id;
-- Replace <table_at_line_1048> after `grep -n stripe_invoice_id document/silverwebbuzz_in_myclinic.sql`
-- and confirm with the team before running this final DROP.


-- =============================================================
-- BLOCK 8 — Rename module_catalog price columns (drop _usd suffix)
-- =============================================================
-- These columns store INR (₹), not USD. Renaming removes the confusion.
-- All app code references must be renamed in the same release.
--
-- Rollback: CHANGE COLUMN back to *_usd.

ALTER TABLE module_catalog
  CHANGE COLUMN price_monthly_usd price_monthly DECIMAL(8,2) DEFAULT 0.00,
  CHANGE COLUMN price_yearly_usd  price_yearly  DECIMAL(8,2) DEFAULT 0.00;


-- =============================================================
-- BLOCK 9 — Old plan enum cleanup verification
-- =============================================================
-- Just a sanity SELECT, not a modification. Confirms that Phase 1
-- left the world tidy: no lingering 'free'/'clinic'/'practice'/
-- 'enterprise' references anywhere we know about.

-- SELECT DISTINCT plan FROM tenants;   -- expect: 'standard' only

-- Check feature_flags state
-- SELECT flag_key, is_enabled, scope FROM feature_flags ORDER BY flag_key;


-- =============================================================
-- BLOCK 10 — Sanity checks (run manually, verify expected output)
-- =============================================================

-- All 3 new tables exist
-- SHOW TABLES LIKE 'follow_%';
-- SHOW TABLES LIKE 'diet_templates';

-- 6 system follow-up reasons seeded
-- SELECT COUNT(*) FROM follow_up_reasons WHERE clinic_id IS NULL;
-- Expected: 6

-- 12 system diet templates (after seed file runs)
-- SELECT COUNT(*) FROM diet_templates
-- WHERE clinic_id IS NULL AND doctor_id IS NULL;
-- Expected: 12

-- diet_plans now has template_id + share_token
-- SHOW COLUMNS FROM diet_plans LIKE 'template_id';
-- SHOW COLUMNS FROM diet_plans LIKE 'share_token';

-- Stripe columns are gone
-- SHOW COLUMNS FROM tenants LIKE 'stripe%';        -- expect: empty
-- SHOW COLUMNS FROM clinic_modules LIKE 'stripe%'; -- expect: empty
-- SHOW COLUMNS FROM payments LIKE 'stripe%';       -- expect: empty

-- module_catalog renamed
-- SHOW COLUMNS FROM module_catalog LIKE 'price%';
-- Expected: price_monthly, price_yearly (no _usd)

-- The two launch add-ons still active
-- SELECT id, name, price_monthly FROM module_catalog WHERE is_active = 1;
-- Expected: patient_connect 499, clinic_network 999


-- =============================================================
-- END OF PHASE 4 MIGRATIONS
-- =============================================================
-- Next: run phase4_diet_seed.sql to populate the 12 system templates.
-- Then follow phase4_followup_diet_help_voice.md §11 for deploy.
-- After all of Phase 4 is verified, see EXECUTION_GUIDE.md
-- (to be written next) for the master cross-phase deploy order.
