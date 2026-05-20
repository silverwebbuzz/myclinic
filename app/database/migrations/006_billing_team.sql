-- Sprint 7: invoice PDF path, patient advance balance

ALTER TABLE invoices
  ADD COLUMN pdf_path VARCHAR(255) NULL AFTER notes;

ALTER TABLE patients
  ADD COLUMN advance_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER is_active;
