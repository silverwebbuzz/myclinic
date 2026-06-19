-- Remove digital consent forms module (doctors handle consent on paper).
-- Deploy the application code first, then run this migration.

-- 1. Deactivate / remove module assignments
DELETE FROM clinic_modules WHERE module_id = 'consent';

-- 2. Remove from module catalog
DELETE FROM module_catalog WHERE id = 'consent';

-- 3. Strip "consent" from per-clinic visible_modules JSON (if present)
UPDATE clinic_settings
SET visible_modules = JSON_REMOVE(
    visible_modules,
    JSON_UNQUOTE(JSON_SEARCH(visible_modules, 'one', 'consent'))
)
WHERE visible_modules IS NOT NULL
  AND JSON_CONTAINS(visible_modules, '"consent"', '$');

-- 4. Drop signed consent data tables
DROP TABLE IF EXISTS consent_forms;
DROP TABLE IF EXISTS consent_templates;

-- 5. (Manual, on server) Delete uploaded consent PDFs/signatures:
--    rm -rf public/uploads/consent/
