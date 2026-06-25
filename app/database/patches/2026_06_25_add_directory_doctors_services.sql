-- Idempotent patch: add `services` (doctor-entered list of services offered)
-- to directory_doctors. Safe to run on an existing production DB that was
-- created before this column existed. install.sql already contains the column
-- for fresh installs; this patch brings older databases up to date.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_06_25_add_directory_doctors_services.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_doctors'
      AND COLUMN_NAME = 'services'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `directory_doctors`
        ADD COLUMN `services` longtext
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
        DEFAULT NULL
        CHECK (json_valid(`services`))
        AFTER `languages`',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
