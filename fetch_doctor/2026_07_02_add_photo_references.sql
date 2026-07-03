-- Adds photo_references (JSON array, up to 5 Google photo tokens) to the
-- existing directory_doctors table. photo_reference (singular, first photo)
-- stays for backward compatibility; this is the gallery.
--
-- A photo_reference is a TOKEN, not a URL — exchange it via the Places Photo
-- API at display time (that render call is billed per image, so show 1 and
-- lazy-load the rest).
--
-- Run once on the live DB (the table already has ~42k rows, so ALTER, not CREATE):
--   mysql -u USER -p DB < fetch_doctor/2026_07_02_add_photo_references.sql
-- or paste into phpMyAdmin → SQL.

ALTER TABLE `directory_doctors`
    ADD COLUMN `photo_references` JSON NULL AFTER `photo_reference`;
