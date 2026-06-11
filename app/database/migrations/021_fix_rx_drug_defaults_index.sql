-- 021_fix_rx_drug_defaults_index.sql
-- Problem: the prescription builder now pre-fills frequency/duration/dose from
--          the doctor's last-used values per drug ("smart defaults"). That
--          lookup is (clinic_id, drug_id) → latest row, which had no index.
-- Safe to re-run: information_schema guard (MySQL has no CREATE INDEX IF NOT EXISTS).
-- phpMyAdmin: select your app database in the left sidebar first.

SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'prescriptions'
                      AND INDEX_NAME = 'idx_rx_clinic_drug');
SET @ddl := IF(@idx_exists = 0,
    'CREATE INDEX idx_rx_clinic_drug ON prescriptions (clinic_id, drug_id, id)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify:
SELECT INDEX_NAME FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'prescriptions' AND INDEX_NAME = 'idx_rx_clinic_drug'
LIMIT 1;
