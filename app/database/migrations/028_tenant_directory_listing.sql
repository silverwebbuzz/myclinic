-- 028_tenant_directory_listing.sql
-- Doctor claim approval links an approved clinic to its public directory row.
-- DoctorClaimService::approve() sets is_directory_listed=1 and directory_doctor_id
-- after creating (or claiming) a directory_doctors row. Without these columns
-- the UPDATE fails and the whole approval transaction rolls back.
-- Safe to re-run: information_schema guards.

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'tenants'
                      AND COLUMN_NAME = 'is_directory_listed');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN is_directory_listed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'tenants'
                      AND COLUMN_NAME = 'directory_doctor_id');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN directory_doctor_id BIGINT UNSIGNED NULL AFTER is_directory_listed',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'tenants'
                      AND INDEX_NAME = 'idx_tenants_directory_doctor');
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE tenants ADD INDEX idx_tenants_directory_doctor (directory_doctor_id)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
