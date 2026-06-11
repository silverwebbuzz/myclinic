-- 020_fix_queue_token_logic.sql
-- No USE statement: cPanel DB names are prefixed (not "manageclinic").
-- In phpMyAdmin, click your app database in the left sidebar FIRST, then
-- paste this into the SQL tab. migrate.php targets DB_DATABASE from .env.
-- Problem 1: Walk-in token numbers were assigned with SELECT MAX(token_number)+1
--            (read-then-insert). Two concurrent bookings could claim the same
--            token. A per-clinic per-day counter row makes the claim atomic via
--            INSERT ... ON DUPLICATE KEY UPDATE last_token = LAST_INSERT_ID(last_token + 1).
-- Problem 2: appointments had created_at but no updated_at, so status changes
--            (queue transitions) left no timestamp trail.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS + information_schema guard.

CREATE TABLE IF NOT EXISTS appointment_token_counters (
  clinic_id   BIGINT UNSIGNED NOT NULL,
  token_date  DATE NOT NULL,
  last_token  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (clinic_id, token_date),
  CONSTRAINT fk_token_counter_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed today's counters from tokens already handed out, so the switch to the
-- counter table cannot re-issue a number already on a printed slip.
INSERT INTO appointment_token_counters (clinic_id, token_date, last_token)
SELECT clinic_id, CURDATE(), COALESCE(MAX(token_number), 0)
FROM appointments
WHERE scheduled_at >= CURDATE()
  AND scheduled_at < CURDATE() + INTERVAL 1 DAY
  AND token_number IS NOT NULL
GROUP BY clinic_id
ON DUPLICATE KEY UPDATE last_token = GREATEST(last_token, VALUES(last_token));

-- appointments.updated_at (guarded: plain MySQL has no ADD COLUMN IF NOT EXISTS)
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'appointments'
                      AND COLUMN_NAME = 'updated_at');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE appointments ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify:
SELECT COUNT(*) AS counter_rows FROM appointment_token_counters;
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'updated_at';
