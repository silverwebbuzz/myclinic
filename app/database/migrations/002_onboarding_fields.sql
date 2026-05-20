USE manageclinic;

ALTER TABLE specialty_configs
  ADD COLUMN consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER invoice_tax_percent,
  ADD COLUMN notification_prefs JSON NULL AFTER google_calendar_token,
  ADD COLUMN specialty_options JSON NULL AFTER visit_fields;
