USE manageclinic;

ALTER TABLE patients
  ADD COLUMN qr_card_path VARCHAR(255) NULL AFTER photo_path;

CREATE TABLE IF NOT EXISTS patient_documents (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  clinic_id   BIGINT UNSIGNED NOT NULL,
  patient_id  BIGINT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  mime_type   VARCHAR(80) NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_patient_docs (clinic_id, patient_id, created_at),
  CONSTRAINT fk_patient_docs_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_patient_docs_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_patient_docs_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
