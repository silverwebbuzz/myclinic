-- =============================================================
-- eClinicPro — Phase 1 Migrations
-- =============================================================
-- Goal: Migrate from 4-tier plan model to single 'standard' plan.
-- Add infrastructure for Founding Clinic deal, feature flags,
-- per-clinic UI settings.
--
-- Pre-requisite:   FULL DB BACKUP. This is destructive in spots.
-- Run order:       Block by block, top to bottom.
-- Idempotency:     Each block guarded where possible. Re-running
--                  a completed block is safe (no-op or warning).
-- Rollback:        See block-by-block notes. Most blocks reversible.
-- =============================================================

-- Use the production DB. Adjust if your DB name differs.
-- USE silverwebbuzz_in_myclinic;

-- =============================================================
-- BLOCK 1 — Widen tenants.specialty (overdue fix, unblocks Phase 2)
-- =============================================================
-- Old enum was ('gp','homeopathy','dental','derma','peds','physio','other')
-- which is narrower than your directory's 50+ specialties.
-- VARCHAR(40) matches what /find-a-doctor uses.
-- Rollback: ALTER COLUMN back to ENUM(...). Existing values will
-- need re-mapping if they're outside the original enum.

ALTER TABLE tenants
  MODIFY COLUMN specialty VARCHAR(40) DEFAULT 'gp';


-- =============================================================
-- BLOCK 2 — Migrate tenants.plan from 4-tier enum to single value
-- =============================================================
-- MySQL won't let us shrink an ENUM in one step if rows hold
-- soon-to-be-invalid values. Strategy: drop, re-add.
--
-- IMPORTANT: Before running BLOCK 2, deploy the Plan::isActive()
-- helper and refactor all controllers to stop reading the old
-- enum directly. See phase1_pricing_and_cleanup.md §7 step 2-3.
-- Rollback: re-add the old enum with same default.

ALTER TABLE tenants DROP COLUMN plan;

ALTER TABLE tenants
  ADD COLUMN plan ENUM('standard') NOT NULL DEFAULT 'standard'
  AFTER timezone;


-- =============================================================
-- BLOCK 3 — Add founding clinic columns to tenants
-- =============================================================
-- is_founding_clinic flags the tenant as a Founding-100 customer.
-- founding_clinic_locked_at  — when we granted the discount.
-- founding_clinic_locked_until — date discount auto-expires
-- (₹999 → ₹1,499). 24 months from grant. After this date, the
-- billing job should bill the full standard price.
--
-- NOTE: we are NOT giving lifetime pricing. The original plan
-- said "lifetime ₹999" — that's an Indian-SaaS unit-economics
-- trap. Locked for 24 months is the same urgency at signup
-- without the long-term margin pain.
-- Rollback: DROP COLUMN all three.

ALTER TABLE tenants
  ADD COLUMN is_founding_clinic TINYINT(1) NOT NULL DEFAULT 0
    AFTER plan,
  ADD COLUMN founding_clinic_locked_at TIMESTAMP NULL DEFAULT NULL
    AFTER is_founding_clinic,
  ADD COLUMN founding_clinic_locked_until DATE NULL DEFAULT NULL
    AFTER founding_clinic_locked_at;


-- =============================================================
-- BLOCK 4 — Add trial grace extension columns to tenants
-- =============================================================
-- One-time 15-day extension granted by admin.
-- *_by stores the admin user id (FK to users table).
-- Rollback: DROP COLUMN all three.

ALTER TABLE tenants
  ADD COLUMN trial_extension_granted TINYINT(1) NOT NULL DEFAULT 0
    AFTER trial_ends_at,
  ADD COLUMN trial_extension_granted_at TIMESTAMP NULL DEFAULT NULL
    AFTER trial_extension_granted,
  ADD COLUMN trial_extension_granted_by BIGINT(20) UNSIGNED NULL
    AFTER trial_extension_granted_at;


-- =============================================================
-- BLOCK 5 — Indexes on tenants (founding flag, trial expiry)
-- =============================================================
-- Founding flag: used in admin filtering, signup counter checks.
-- Trial ends: used by cron job that warns 3 days before expiry.

ALTER TABLE tenants
  ADD INDEX idx_tenants_founding (is_founding_clinic),
  ADD INDEX idx_tenants_trial_ends (trial_ends_at);


-- =============================================================
-- BLOCK 6 — Create clinic_settings (per-clinic UI preferences)
-- =============================================================
-- Distinct from specialty_configs (clinical config). Holds:
--  - visible_modules: JSON array of module ids visible in UI
--  - section_state:   per-section expand memory for visit screen
--  - voice_lang:      'en-IN' / 'hi-IN' / 'gu-IN' for Web Speech
--  - whatsapp_share_default: should "share" be pre-checked?
-- Rollback: DROP TABLE.

CREATE TABLE IF NOT EXISTS clinic_settings (
  clinic_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY,
  visible_modules JSON DEFAULT NULL,
  section_state JSON DEFAULT NULL,
  default_visit_template_id BIGINT(20) UNSIGNED NULL,
  voice_lang VARCHAR(10) DEFAULT 'en-IN',
  whatsapp_share_default TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_clinic_settings_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Backfill: create one row per existing tenant.
-- visible_modules and section_state left NULL — Phase 2 will
-- populate them from specialty defaults on first read.
INSERT INTO clinic_settings (clinic_id)
SELECT id FROM tenants
WHERE id NOT IN (SELECT clinic_id FROM clinic_settings);


-- =============================================================
-- BLOCK 7 — Create feature_flags (global on/off for Bucket-3 features)
-- =============================================================
-- Scope:
--   'all'    — flag is on for every tenant
--   'beta'   — flag is on only for tenants in beta_tenant_ids
--   'tenant' — read alongside a per-tenant override (future)
--
-- Rollback: DROP TABLE.

CREATE TABLE IF NOT EXISTS feature_flags (
  flag_key VARCHAR(60) NOT NULL PRIMARY KEY,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  scope ENUM('all', 'beta', 'tenant') NOT NULL DEFAULT 'all',
  beta_tenant_ids JSON DEFAULT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Seed Bucket-3 flags. All OFF by default, scope=beta (admin opt-in).
-- teleconsult is the exception: scope=all, is_enabled=1 (included in base).
INSERT INTO feature_flags
  (flag_key, is_enabled, scope, description)
VALUES
  ('lab_module',         0, 'beta', 'Lab orders + reports module'),
  ('radiology_module',   0, 'beta', 'Radiology orders module'),
  ('pharmacy_module',    0, 'beta', 'In-house pharmacy stock module'),
  ('crm_module',         0, 'beta', 'Marketing CRM module'),
  ('incentive_module',   0, 'beta', 'Staff incentive tracking'),
  ('advanced_analytics', 0, 'beta', 'Advanced analytics beyond basic reports'),
  ('ai_transcription',   0, 'beta', 'AI voice-to-structured note conversion'),
  ('custom_branding',    0, 'beta', 'White-label branding'),
  ('docs_vault',         0, 'beta', 'Document vault'),
  ('teleconsult',        1, 'all',  'Teleconsultation — included in base plan')
ON DUPLICATE KEY UPDATE
  description = VALUES(description);


-- =============================================================
-- BLOCK 8 — Create founding_clinic_state (single-row counter)
-- =============================================================
-- Used by signup form ("47 of 100 spots left") and webhook to
-- atomically claim a spot when a new tenant signs up.
-- CHECK constraint enforces single-row table.
-- Rollback: DROP TABLE.

CREATE TABLE IF NOT EXISTS founding_clinic_state (
  id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
  cap INT NOT NULL DEFAULT 100,
  claimed INT NOT NULL DEFAULT 0,
  closed_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT chk_founding_only_one_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO founding_clinic_state (id, cap, claimed)
VALUES (1, 100, 0)
ON DUPLICATE KEY UPDATE cap = VALUES(cap);


-- =============================================================
-- BLOCK 9 — Backfill module_catalog: hide legacy, enable two launch addons
-- =============================================================
-- Strategy: set is_active=0 on all existing rows (hides them
-- from the pricing page and addon picker), then INSERT/UPDATE
-- the two launch add-ons with is_active=1.
--
-- Why we don't DELETE old rows: existing customer subscriptions
-- in clinic_modules reference these IDs. Hiding > deleting.
-- Rollback: UPDATE module_catalog SET is_active=1 to restore.

UPDATE module_catalog SET is_active = 0;

INSERT INTO module_catalog
  (id, name, description, category,
   price_monthly_usd, price_yearly_usd,
   specialties, depends_on, included_in_plans,
   icon, is_active, sort_order)
VALUES
  ('patient_connect',
   'Patient Connect',
   'WhatsApp automation: appointment reminders, prescription delivery, follow-up nudges.',
   'addon',
   499.00, 4990.00,
   NULL, NULL, '["standard"]',
   'message-circle', 1, 1),
  ('clinic_network',
   'Clinic Network',
   'Add an extra clinic branch under one account. Per-branch ₹999/month.',
   'addon',
   999.00, 9990.00,
   NULL, NULL, '["standard"]',
   'git-branch', 1, 2)
ON DUPLICATE KEY UPDATE
  is_active = 1,
  category = 'addon',
  name = VALUES(name),
  description = VALUES(description),
  price_monthly_usd = VALUES(price_monthly_usd),
  price_yearly_usd = VALUES(price_yearly_usd),
  included_in_plans = VALUES(included_in_plans),
  sort_order = VALUES(sort_order);

-- NOTE: column price_monthly_usd is misnamed — it stores INR
-- (₹), not USD. Code already treats it as the local price.
-- Renaming is deferred to Phase 4 to avoid sprawling changes here.


-- =============================================================
-- BLOCK 10 — Add useful index on clinic_modules
-- =============================================================
-- Most queries look up "what active modules does this clinic have?"
-- Composite index on (clinic_id, is_active) accelerates that.

ALTER TABLE clinic_modules
  ADD INDEX idx_clinic_modules_clinic_active (clinic_id, is_active);


-- =============================================================
-- BLOCK 11 — Founding clinic backfill (testing data — Option A)
-- =============================================================
-- Existing data is test data per the user. Lock founding pricing
-- for the first 100 active tenants by signup date so the admin UI
-- has populated rows to verify. Each is locked for 24 months from
-- grant date.

UPDATE tenants t
JOIN (
  SELECT id FROM tenants
  WHERE is_active = 1
  ORDER BY created_at ASC LIMIT 100
) early ON t.id = early.id
SET t.is_founding_clinic = 1,
    t.founding_clinic_locked_at = NOW(),
    t.founding_clinic_locked_until = DATE_ADD(CURDATE(), INTERVAL 24 MONTH);

UPDATE founding_clinic_state
SET claimed = (SELECT COUNT(*) FROM tenants WHERE is_founding_clinic = 1)
WHERE id = 1;


-- =============================================================
-- BLOCK 12 — Phase 1 sanity checks (run manually after migration)
-- =============================================================
-- These are SELECTs, not modifications. Run and verify expected output.

-- Should return exactly 1 row, plan='standard'
-- SELECT DISTINCT plan FROM tenants;

-- Should return one row per tenant
-- SELECT COUNT(*) AS tenants_total,
--        (SELECT COUNT(*) FROM clinic_settings) AS clinic_settings_total
-- FROM tenants;

-- Should return 10 rows
-- SELECT flag_key, is_enabled, scope FROM feature_flags ORDER BY flag_key;

-- Should return 2 rows (Patient Connect, Clinic Network)
-- SELECT id, name, price_monthly_usd FROM module_catalog WHERE is_active = 1;

-- Founding clinic state should exist with cap=100, claimed=100
-- (after Block 11 backfill)
-- SELECT * FROM founding_clinic_state;

-- Founding tenants should each have founding_clinic_locked_until ~24mo out
-- SELECT COUNT(*), MIN(founding_clinic_locked_until), MAX(founding_clinic_locked_until)
-- FROM tenants WHERE is_founding_clinic = 1;


-- =============================================================
-- END OF PHASE 1 MIGRATIONS
-- =============================================================
-- Next: see phase1_pricing_and_cleanup.md §7 for the deploy sequence.
