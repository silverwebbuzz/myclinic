-- =============================================================
-- eClinicPro — Phase 3 Migrations
-- =============================================================
-- Goal:
--   - Symptoms 3-layer system (master / personal / visit join)
--   - Prescription templates (header + items + usage log)
--   - drugs.usage_count + remedies.usage_count for ranked search
--
-- Pre-requisite:   Phase 2 migrations applied and verified.
--                  Verify with:
--                    SHOW COLUMNS FROM visits LIKE 'auto_save_data';
--                    SHOW COLUMNS FROM prescriptions LIKE 'frequency_preset';
--                    SHOW COLUMNS FROM prescriptions LIKE 'tapering_steps';
--
-- Run order:       Block by block, top to bottom.
-- Rollback notes:  Each block reversible (DROP TABLE / DROP COLUMN).
-- Seed file:       phase3_symptoms_seed.sql (separate, run after Block 1)
-- =============================================================

-- USE silverwebbuzz_in_myclinic;

-- =============================================================
-- BLOCK 1 — Symptoms master library (curated)
-- =============================================================
-- One row per canonical symptom. ~500 rows seeded separately.
-- synonyms     — JSON array of alt strings for search ("BP", "high temp")
-- specialties  — JSON array of relevant specialty keys (boosts ranking,
--                does NOT filter)
-- category     — broad bucket: "respiratory", "constitutional", "dental"
-- global_usage_count — sum across all clinics, for ranking
--
-- Rollback: DROP TABLE symptoms_master.
--           (May need to drop visit_symptoms FK first.)

CREATE TABLE symptoms_master (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  synonyms JSON DEFAULT NULL,
  specialties JSON DEFAULT NULL,
  category VARCHAR(40) DEFAULT NULL,
  global_usage_count INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_symptoms_master_label (label),
  INDEX idx_symptoms_master_active (is_active, global_usage_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 2 — Symptoms personal (per-doctor learned)
-- =============================================================
-- One row per (doctor, label) combination.
-- usage_count: bumped on each ON DUPLICATE KEY UPDATE on save.
-- promoted_to_master_id: filled when admin promotes a custom entry
-- so we can dedupe in search results.
--
-- Rollback: DROP TABLE symptoms_personal.

CREATE TABLE symptoms_personal (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id BIGINT(20) UNSIGNED NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  usage_count INT NOT NULL DEFAULT 1,
  last_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  promoted_to_master_id BIGINT(20) UNSIGNED NULL,
  UNIQUE KEY uniq_doctor_label (doctor_id, label),
  INDEX idx_personal_recent (doctor_id, last_used_at DESC),
  CONSTRAINT fk_personal_doctor
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_promoted
    FOREIGN KEY (promoted_to_master_id) REFERENCES symptoms_master(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 3 — Visit symptoms (many-to-many join)
-- =============================================================
-- label is denormalized — survives master deletion.
-- master_id is SET NULL if the master row is deleted, so visit
-- history stays readable.
--
-- Rollback: DROP TABLE visit_symptoms.

CREATE TABLE visit_symptoms (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT(20) UNSIGNED NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  master_id BIGINT(20) UNSIGNED NULL,
  label VARCHAR(120) NOT NULL,
  source ENUM('master','personal','custom') NOT NULL DEFAULT 'custom',
  severity ENUM('mild','moderate','severe') DEFAULT NULL,
  duration VARCHAR(40) DEFAULT NULL,
  sort_order TINYINT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vs_visit (visit_id, sort_order),
  INDEX idx_vs_master (master_id),
  CONSTRAINT fk_vs_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_vs_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_vs_master
    FOREIGN KEY (master_id) REFERENCES symptoms_master(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 4 — Prescription templates (header)
-- =============================================================
-- doctor_id NULL = clinic-wide template.
-- auto_discovered = 1 means cron suggested it; is_active = 0
-- until the doctor explicitly saves the suggestion.
--
-- Rollback: DROP TABLE prescription_templates.
--           (Must drop prescription_template_items + template_usage_log first.)

CREATE TABLE prescription_templates (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  doctor_id BIGINT(20) UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(240) DEFAULT NULL,
  mode ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  use_count INT NOT NULL DEFAULT 0,
  last_used_at TIMESTAMP NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  auto_discovered TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_templates_doctor (doctor_id, use_count DESC),
  INDEX idx_templates_clinic (clinic_id, use_count DESC),
  CONSTRAINT fk_templates_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_templates_doctor
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 5 — Prescription template items
-- =============================================================
-- Mirrors prescriptions columns (including Phase 2 additions).
-- When a template is applied to a visit, we INSERT INTO prescriptions
-- SELECTING from this table.
--
-- Rollback: DROP TABLE prescription_template_items.

CREATE TABLE prescription_template_items (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT(20) UNSIGNED NOT NULL,
  mode ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  drug_id BIGINT(20) UNSIGNED NULL,
  remedy_id BIGINT(20) UNSIGNED NULL,
  potency VARCHAR(10) DEFAULT NULL,
  dose_unit VARCHAR(20) DEFAULT NULL,
  dose_amount DECIMAL(7,2) DEFAULT NULL,
  frequency_preset VARCHAR(20) DEFAULT NULL,
  duration_days SMALLINT UNSIGNED DEFAULT NULL,
  food_timing ENUM('before','after','with','empty','bedtime','any') DEFAULT 'any',
  mix_with VARCHAR(40) DEFAULT NULL,
  tapering_steps LONGTEXT NULL
    CHECK (tapering_steps IS NULL OR JSON_VALID(tapering_steps)),
  instructions TEXT DEFAULT NULL,
  sort_order TINYINT UNSIGNED DEFAULT 0,
  INDEX idx_template_items (template_id, sort_order),
  CONSTRAINT fk_template_items
    FOREIGN KEY (template_id) REFERENCES prescription_templates(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_template_items_drug
    FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE SET NULL,
  CONSTRAINT fk_template_items_remedy
    FOREIGN KEY (remedy_id) REFERENCES remedies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 6 — Template usage log
-- =============================================================
-- One row per template application. Powers analytics and the
-- "you often use X — save as template" auto-discovery loop.
--
-- Rollback: DROP TABLE template_usage_log.

CREATE TABLE template_usage_log (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT(20) UNSIGNED NOT NULL,
  doctor_id BIGINT(20) UNSIGNED NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  visit_id BIGINT(20) UNSIGNED NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_template_log_doctor (doctor_id, applied_at DESC),
  INDEX idx_template_log_template (template_id, applied_at DESC),
  CONSTRAINT fk_tul_template
    FOREIGN KEY (template_id) REFERENCES prescription_templates(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_tul_doctor
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tul_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_tul_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- BLOCK 7 — Add usage_count to drugs + remedies for ranked search
-- =============================================================
-- Incremented on each prescription save (application code).
-- Weekly cron reconciles from prescriptions in case of drift.
--
-- Rollback: DROP COLUMN usage_count, DROP INDEX on both tables.

ALTER TABLE drugs
  ADD COLUMN usage_count INT NOT NULL DEFAULT 0 AFTER schedule,
  ADD INDEX idx_drugs_usage (usage_count DESC, name);

ALTER TABLE remedies
  ADD COLUMN usage_count INT NOT NULL DEFAULT 0 AFTER source,
  ADD INDEX idx_remedies_usage (usage_count DESC, name);


-- =============================================================
-- BLOCK 8 — Backfill usage_count from existing prescriptions
-- =============================================================
-- One-time reconciliation so existing popular drugs/remedies
-- already rank correctly when the new UI lights up.
--
-- Rollback: UPDATE drugs SET usage_count = 0;
--           UPDATE remedies SET usage_count = 0;

UPDATE drugs d
SET d.usage_count = COALESCE((
  SELECT COUNT(*) FROM prescriptions p WHERE p.drug_id = d.id
), 0);

UPDATE remedies r
SET r.usage_count = COALESCE((
  SELECT COUNT(*) FROM prescriptions p WHERE p.remedy_id = r.id
), 0);


-- =============================================================
-- BLOCK 9 — Sanity checks (run manually, verify expected output)
-- =============================================================

-- All 6 new tables should exist
-- SHOW TABLES LIKE 'symptoms_%';
-- SHOW TABLES LIKE 'prescription_template%';
-- SHOW TABLES LIKE 'template_usage_log';
-- SHOW TABLES LIKE 'visit_symptoms';

-- usage_count column on drugs + remedies
-- SHOW COLUMNS FROM drugs LIKE 'usage_count';
-- SHOW COLUMNS FROM remedies LIKE 'usage_count';

-- Top 10 most-prescribed drugs (should make sense)
-- SELECT name, usage_count FROM drugs ORDER BY usage_count DESC LIMIT 10;

-- symptoms_master is empty until seed runs
-- SELECT COUNT(*) FROM symptoms_master;
-- Then after seed: should be ~500
-- SELECT COUNT(*), COUNT(DISTINCT category) FROM symptoms_master;


-- =============================================================
-- END OF PHASE 3 MIGRATIONS
-- =============================================================
-- Next: run phase3_symptoms_seed.sql to populate symptoms_master.
-- Then follow phase3_symptoms_rx_templates.md §9 for the deploy sequence.
