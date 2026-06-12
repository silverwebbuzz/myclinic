-- 024_fix_rx_pad_customization.sql
-- Problem: no way to put the clinic/doctor registration number or custom
--          header/footer text on the printed prescription pad — required for
--          a legally usable Indian Rx (medical council reg. no., disclaimers).
-- Safe to re-run: information_schema guards.
-- phpMyAdmin: select your app database in the left sidebar first.

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'registration_number');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN registration_number VARCHAR(60) NULL AFTER gstin',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'specialty_configs' AND COLUMN_NAME = 'rx_header_text');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE specialty_configs
       ADD COLUMN rx_header_text VARCHAR(255) NULL,
       ADD COLUMN rx_footer_text VARCHAR(255) NULL',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify:
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'tenants' AND COLUMN_NAME = 'registration_number')
    OR (TABLE_NAME = 'specialty_configs' AND COLUMN_NAME IN ('rx_header_text', 'rx_footer_text')));
