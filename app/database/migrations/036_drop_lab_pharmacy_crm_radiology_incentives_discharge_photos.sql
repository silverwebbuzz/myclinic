-- Remove Lab, Pharmacy, CRM, Radiology, Incentives, Discharge, Before/After photos.
-- Deploy application code first, then run this migration.
-- Patient portal module is kept.

-- 1. Deactivate / remove module assignments
DELETE FROM clinic_modules WHERE module_id IN (
  'lab', 'pharmacy', 'radiology', 'crm', 'incentives',
  'discharge', 'before_after'
);

-- 2. Remove from module catalog
DELETE FROM module_catalog WHERE id IN (
  'lab', 'pharmacy', 'radiology', 'crm', 'incentives',
  'discharge', 'before_after'
);

-- 3. Remove staged-rollout feature flags
DELETE FROM feature_flags WHERE flag_key IN (
  'lab_module', 'radiology_module', 'pharmacy_module',
  'crm_module', 'incentive_module'
);

-- 4. Drop dependent tables (FK order)
DROP TABLE IF EXISTS lab_results;
DROP TABLE IF EXISTS lab_orders;
DROP TABLE IF EXISTS lab_tests_catalog;
DROP TABLE IF EXISTS pharmacy_sale_items;
DROP TABLE IF EXISTS pharmacy_narcotic_register;
DROP TABLE IF EXISTS pharmacy_sales;
DROP TABLE IF EXISTS pharmacy_inventory;
DROP TABLE IF EXISTS radiology_orders;
DROP TABLE IF EXISTS discharge_summaries;
DROP TABLE IF EXISTS patient_photos;
DROP TABLE IF EXISTS crm_leads;
DROP TABLE IF EXISTS doctor_incentives;

-- 5. (Manual, on server) Delete generated upload folders if present:
--    rm -rf public/uploads/lab/ public/uploads/incentives/
