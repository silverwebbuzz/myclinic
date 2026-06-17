-- 029_directory_doctors_self_source.sql
-- New listing approvals insert directory_doctors with source='self'.
-- Older tables may only allow 'google'. Safe to re-run.
-- phpMyAdmin: select your app database first.

SET @col_type := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_doctors'
      AND COLUMN_NAME = 'source'
    LIMIT 1
);
SET @ddl := IF(
    @col_type IS NOT NULL AND @col_type NOT LIKE '%self%',
    'ALTER TABLE directory_doctors MODIFY COLUMN source ENUM(''google'',''self'',''manual'') NOT NULL DEFAULT ''google''',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
