-- Adds:
--   doctor_schedules.extended_end_time  -- optional later end time, admin walk-in only
--   specialty_configs.booking_window_days  -- how far ahead patients can self-book

ALTER TABLE doctor_schedules
    ADD COLUMN extended_end_time TIME NULL AFTER end_time;

ALTER TABLE specialty_configs
    ADD COLUMN booking_window_days SMALLINT UNSIGNED DEFAULT 30 AFTER slot_duration_min;
