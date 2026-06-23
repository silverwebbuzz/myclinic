-- 041_appointment_flow_times.sql
-- Track the patient's journey through the visit so the appointments listing
-- can show "In" (called into consult) and "Out" (consult finished) times —
-- the doctor-friendly flow modelled on drfeelgoods' queue.
--
--   arrived_at         — patient marked Arrived / In Clinic (status=confirmed)
--   consult_started_at — doctor called the patient in (status=in_progress) = "In"
--   completed_at       — consult finished (status=completed)              = "Out"
--
-- All nullable; backfilled going forward as statuses change. No data migration
-- needed for existing rows (they simply show "—" until they move again).
--
-- Safe to re-run: ADD COLUMN IF NOT EXISTS (MariaDB 10.0.2+/MySQL 8 guard).
-- phpMyAdmin: select your app database first.

ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `arrived_at`         DATETIME NULL DEFAULT NULL AFTER `token_number`,
    ADD COLUMN IF NOT EXISTS `consult_started_at` DATETIME NULL DEFAULT NULL AFTER `arrived_at`,
    ADD COLUMN IF NOT EXISTS `completed_at`       DATETIME NULL DEFAULT NULL AFTER `consult_started_at`;
