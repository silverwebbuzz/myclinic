-- =============================================================
-- eClinicPro — Phase 4 Migrations (CORRECTED / REMAINING)
-- =============================================================
-- The original phase4_migrations.sql assumed Stripe columns lived on
-- tenants / clinic_modules / payments. Against the ACTUAL production DB
-- (silverwebbuzz_in_myclinic(2).sql) that's wrong. This file reflects
-- reality and only runs what's still needed.
--
-- VERIFIED STATE of the live DB:
--   diet_templates                       — ALREADY EXISTS  → skip
--   diet_plans.template_id/share_token/... — ALREADY EXIST  → skip
--   tenants.stripe_customer_id            — DOES NOT EXIST  → skip
--   clinic_modules.stripe_sub_item_id     — DOES NOT EXIST  → skip
--   payments.stripe_payment_id            — DOES NOT EXIST  → skip
--   invoices.stripe_payment_id            — EXISTS          → DROP
--   saas_invoices.stripe_invoice_id       — EXISTS          → DROP
--   module_catalog.price_monthly_usd      — EXISTS          → RENAME
--   follow_ups                            — MISSING         → CREATE
--   follow_up_reasons                     — MISSING         → CREATE + seed
--
-- Run order: top to bottom. Each block guarded / safe to re-run.
-- =============================================================

-- USE silverwebbuzz_in_myclinic;


-- =============================================================
-- BLOCK 1 — Create follow_ups (state machine)
-- =============================================================
-- Rollback: DROP TABLE follow_ups;

CREATE TABLE IF NOT EXISTS follow_ups (
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
-- BLOCK 2 — Create follow_up_reasons + seed 6 system reasons
-- =============================================================
-- Rollback: DROP TABLE follow_up_reasons;

CREATE TABLE IF NOT EXISTS follow_up_reasons (
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
  ('check_progress',        'Check progress',          NULL, 10),
  ('retest_labs',           'Retest labs',             NULL, 20),
  ('continue_treatment',    'Continue treatment',      NULL, 30),
  ('post_procedure_review', 'Post-procedure review',   NULL, 40),
  ('acute_followup',        'Acute episode follow-up', NULL, 50),
  ('other',                 'Other (specify)',         NULL, 99)
ON DUPLICATE KEY UPDATE label = VALUES(label), sort_order = VALUES(sort_order);


-- =============================================================
-- BLOCK 3 — diet_templates + diet_plans columns
-- =============================================================
-- ALREADY APPLIED in the live DB. Nothing to do.
-- (diet_templates exists; diet_plans has template_id, share_token,
--  share_token_expires.) Left here as a no-op marker for the record.


-- =============================================================
-- BLOCK 4 — Drop Stripe columns (CORRECTED locations)
-- =============================================================
-- Stripe columns live on `invoices` and `saas_invoices`, NOT on
-- tenants/clinic_modules/payments. Confirm app code is Stripe-free first:
--   grep -rn 'stripe_payment_id\|stripe_invoice_id' app/   (expect empty)
--
-- Rollback: re-ADD the columns (data lost — only if dropped by mistake).

ALTER TABLE invoices DROP COLUMN stripe_payment_id;
ALTER TABLE saas_invoices DROP COLUMN stripe_invoice_id;

-- NOTE: tenants.stripe_customer_id, clinic_modules.stripe_sub_item_id,
-- payments.stripe_payment_id are NOT present in this DB — do NOT attempt
-- to drop them (that's the #1091 error you hit). Skip entirely.


-- =============================================================
-- BLOCK 5 — Rename module_catalog price columns — SKIPPED
-- =============================================================
-- DECISION: NOT renaming price_monthly_usd → price_monthly.
-- The column holds INR despite the _usd suffix, but it is read in
-- multiple live code paths (SuperAdminController, ClinicSettingsService,
-- settings/subscription view, admin/clinic_detail view) AND written by
-- install.sql / seeds / migrations that still use the _usd name.
-- Renaming is pure cosmetics and adds breakage risk for zero functional
-- gain. Leave the column as price_monthly_usd. (Documentation-only debt.)


-- =============================================================
-- BLOCK 6 — Sanity checks (run manually)
-- =============================================================

-- follow_ups + follow_up_reasons exist
-- SHOW TABLES LIKE 'follow_%';

-- 6 system reasons seeded
-- SELECT COUNT(*) FROM follow_up_reasons WHERE clinic_id IS NULL;   -- expect 6

-- Stripe columns gone from both invoice tables
-- SHOW COLUMNS FROM invoices LIKE 'stripe%';        -- expect empty
-- SHOW COLUMNS FROM saas_invoices LIKE 'stripe%';   -- expect empty

-- diet_templates ready for seed (currently 0 system rows until seed runs)
-- SELECT COUNT(*) FROM diet_templates WHERE clinic_id IS NULL;


-- =============================================================
-- END — then run phase4_diet_seed.sql for the 12 system diet templates.
-- =============================================================
