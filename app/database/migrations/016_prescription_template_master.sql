-- ============================================================
-- Master prescription templates (global, system-provided),
-- mirroring the symptoms_master / symptoms_personal pattern.
--
-- Every clinic draws from these master templates, the same way
-- they draw from symptoms_master. A clinic/doctor's own templates
-- continue to live in the existing `prescription_templates` table.
--
-- Master rows are NOT tied to any clinic_id. They are tagged with a
-- `specialty` slug (from specialty_master) so the prescription panel
-- can show the templates relevant to the clinic's specialty.
-- ============================================================

CREATE TABLE prescription_templates_master (
  id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  specialty     VARCHAR(40) NOT NULL,                 -- slug from specialty_master (e.g. 'gp')
  name          VARCHAR(120) NOT NULL,                -- condition title, e.g. "Acute URI / Common Cold"
  description   VARCHAR(240) DEFAULT NULL,
  mode          ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  global_usage_count INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tpl_master_specialty (specialty, is_active),
  INDEX idx_tpl_master_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prescription_template_master_items (
  id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id     BIGINT(20) UNSIGNED NOT NULL,
  mode            ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  drug_id         BIGINT(20) UNSIGNED NULL,
  remedy_id       BIGINT(20) UNSIGNED NULL,
  -- The generic/name we matched on at seed time. Kept so that if a drug
  -- row is ever re-imported with a new id, we can re-link by name.
  match_name      VARCHAR(150) NULL,
  potency         VARCHAR(10) DEFAULT NULL,
  dose_unit       VARCHAR(20) DEFAULT NULL,
  dose_amount     DECIMAL(7,2) DEFAULT NULL,
  frequency_preset VARCHAR(20) DEFAULT NULL,
  duration_days   SMALLINT UNSIGNED DEFAULT NULL,
  food_timing     ENUM('before','after','with','empty','bedtime','any') DEFAULT 'any',
  mix_with        VARCHAR(40) DEFAULT NULL,
  tapering_steps  LONGTEXT NULL CHECK (tapering_steps IS NULL OR JSON_VALID(tapering_steps)),
  instructions    TEXT DEFAULT NULL,
  sort_order      TINYINT UNSIGNED DEFAULT 0,
  INDEX idx_tpl_master_items (template_id, sort_order),
  CONSTRAINT fk_tpl_master_items FOREIGN KEY (template_id) REFERENCES prescription_templates_master(id) ON DELETE CASCADE,
  CONSTRAINT fk_tpl_master_items_drug FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE SET NULL,
  CONSTRAINT fk_tpl_master_items_remedy FOREIGN KEY (remedy_id) REFERENCES remedies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
