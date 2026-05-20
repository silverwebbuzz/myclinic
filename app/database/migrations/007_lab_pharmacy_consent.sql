-- Sprint 8: consent templates, pharmacy sales, narcotic register, discharge PDF

CREATE TABLE consent_templates (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id    BIGINT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  form_type    ENUM('general','surgical','anaesthesia','procedure','research','photography') DEFAULT 'procedure',
  content      LONGTEXT NOT NULL,
  merge_fields JSON NULL,
  is_active    TINYINT(1) DEFAULT 1,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_templates (clinic_id, is_active),
  CONSTRAINT fk_consent_tpl_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pharmacy_sales (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id     BIGINT UNSIGNED NOT NULL,
  patient_id    BIGINT UNSIGNED NULL,
  subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_mode  ENUM('cash','upi','card') DEFAULT 'cash',
  sold_by       BIGINT UNSIGNED NULL,
  sold_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_sales (clinic_id, sold_at),
  CONSTRAINT fk_pharm_sale_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_pharm_sale_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
  CONSTRAINT fk_pharm_sale_user FOREIGN KEY (sold_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pharmacy_sale_items (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id      BIGINT UNSIGNED NOT NULL,
  inventory_id BIGINT UNSIGNED NOT NULL,
  drug_id      BIGINT UNSIGNED NOT NULL,
  qty          INT NOT NULL,
  unit_price   DECIMAL(10,2) NOT NULL,
  total        DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sale (sale_id),
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES pharmacy_sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_sale_items_inv FOREIGN KEY (inventory_id) REFERENCES pharmacy_inventory(id),
  CONSTRAINT fk_sale_items_drug FOREIGN KEY (drug_id) REFERENCES drugs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pharmacy_narcotic_register (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id     BIGINT UNSIGNED NOT NULL,
  drug_id       BIGINT UNSIGNED NOT NULL,
  sale_id       BIGINT UNSIGNED NULL,
  patient_id    BIGINT UNSIGNED NULL,
  patient_name  VARCHAR(100) NULL,
  qty           INT NOT NULL,
  balance_after INT NOT NULL,
  schedule      ENUM('H','H1') NOT NULL DEFAULT 'H',
  recorded_by   BIGINT UNSIGNED NULL,
  recorded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clinic_narcotic (clinic_id, recorded_at),
  CONSTRAINT fk_narc_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id),
  CONSTRAINT fk_narc_drug FOREIGN KEY (drug_id) REFERENCES drugs(id),
  CONSTRAINT fk_narc_sale FOREIGN KEY (sale_id) REFERENCES pharmacy_sales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE discharge_summaries
  ADD COLUMN pdf_path VARCHAR(255) NULL AFTER doctor_signature_path,
  ADD COLUMN share_token VARCHAR(64) NULL AFTER pdf_path;

ALTER TABLE lab_orders
  ADD COLUMN share_expires_at TIMESTAMP NULL AFTER share_token;
