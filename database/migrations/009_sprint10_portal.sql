-- Sprint 10: diet plan PDF path

ALTER TABLE diet_plans
  ADD COLUMN pdf_path VARCHAR(255) NULL AFTER plan_json;
