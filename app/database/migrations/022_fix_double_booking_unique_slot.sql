-- 022_fix_double_booking_unique_slot.sql
-- Problem: nothing in the schema prevented two non-walk-in appointments for
--          the same doctor at the same time. The app now serializes bookings
--          with GET_LOCK, but a DB-level unique key is the real guarantee.
-- How: a STORED generated column that is NULL for walk-ins and cancelled/no-show
--      rows (walk-ins may share times; cancelling frees the slot), unique-indexed.
-- Safe to re-run: information_schema guard. Skips itself (with a notice) if
--      existing data already contains duplicate bookings — resolve those first,
--      then re-run. The application-level lock still protects either way.
-- phpMyAdmin: select your app database in the left sidebar first.

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'appointments'
                      AND COLUMN_NAME = 'slot_key');

SET @dup_count := (SELECT COUNT(*) FROM (
    SELECT clinic_id, doctor_id, scheduled_at
    FROM appointments
    WHERE type <> 'walkin' AND status NOT IN ('cancelled', 'no_show')
    GROUP BY clinic_id, doctor_id, scheduled_at
    HAVING COUNT(*) > 1
) dups);

SET @ddl := IF(@col_exists = 0 AND @dup_count = 0,
    'ALTER TABLE appointments
       ADD COLUMN slot_key VARCHAR(64)
         GENERATED ALWAYS AS (
           CASE WHEN type <> ''walkin'' AND status NOT IN (''cancelled'', ''no_show'')
                THEN CONCAT(clinic_id, ''-'', doctor_id, ''-'', scheduled_at)
           END
         ) STORED,
       ADD UNIQUE KEY uq_appt_slot (slot_key)',
    IF(@col_exists > 0,
       'SELECT ''slot_key already exists — nothing to do'' AS notice',
       'SELECT CONCAT(''SKIPPED: '', @dup_count, '' duplicate slot(s) exist — run the duplicate report below, fix them, re-run'') AS notice'));
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify (and duplicate report if it skipped):
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'slot_key';

SELECT clinic_id, doctor_id, scheduled_at, COUNT(*) AS copies
FROM appointments
WHERE type <> 'walkin' AND status NOT IN ('cancelled', 'no_show')
GROUP BY clinic_id, doctor_id, scheduled_at
HAVING COUNT(*) > 1
LIMIT 20;
