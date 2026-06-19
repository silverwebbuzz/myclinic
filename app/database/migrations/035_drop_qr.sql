-- Remove patient QR cards module (lookup by phone/UHID remains).
-- Deploy application code first, then run this migration.

-- 1. Deactivate / remove module assignments
DELETE FROM clinic_modules WHERE module_id = 'qr';

-- 2. Remove from module catalog
DELETE FROM module_catalog WHERE id = 'qr';

-- 3. Drop patient QR columns
ALTER TABLE patients DROP INDEX uq_qr;
ALTER TABLE patients DROP COLUMN qr_token;
ALTER TABLE patients DROP COLUMN qr_card_path;

-- 4. (Manual, on server) Delete generated QR card files:
--    rm -rf public/uploads/qr/
