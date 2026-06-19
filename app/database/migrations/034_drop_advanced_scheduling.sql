-- Remove Advanced Scheduling module (duplicate of Settings → Working hours).
-- Appointments still use doctor_schedules — synced automatically from working hours.
-- Deploy application code first, then run this migration.

-- 1. Deactivate / remove module assignments
DELETE FROM clinic_modules WHERE module_id = 'advanced_scheduling';

-- 2. Remove from module catalog
DELETE FROM module_catalog WHERE id = 'advanced_scheduling';

-- 3. Drop waiting list (only used by the removed Scheduling page)
DROP TABLE IF EXISTS waiting_list;
