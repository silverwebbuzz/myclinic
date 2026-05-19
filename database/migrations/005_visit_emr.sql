-- Sprint 6: visit status, Rx PDF, admin unlock

ALTER TABLE visits
  ADD COLUMN status ENUM('in_progress','completed','cancelled') NOT NULL DEFAULT 'in_progress' AFTER visit_number,
  ADD COLUMN rx_pdf_path VARCHAR(255) NULL AFTER clinical_notes,
  ADD COLUMN unlocked_by BIGINT UNSIGNED NULL AFTER follow_up_notes,
  ADD COLUMN unlocked_at TIMESTAMP NULL AFTER unlocked_by,
  ADD KEY idx_visit_status (clinic_id, status, visited_at);
