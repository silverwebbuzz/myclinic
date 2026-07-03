-- Idempotent: make wordpress_doctor_links directory-doctor centric.
-- Run: mysql -u USER -p DB < app/database/patches/2026_07_03_wordpress_links_directory_primary.sql

SET @col_nullable := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordpress_doctor_links'
      AND COLUMN_NAME = 'user_id'
    LIMIT 1
);

SET @ddl_user := IF(
    @col_nullable = 'NO',
    'ALTER TABLE `wordpress_doctor_links` MODIFY `user_id` bigint(20) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_user;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_clinic := (
    SELECT IS_NULLABLE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordpress_doctor_links'
      AND COLUMN_NAME = 'clinic_id'
    LIMIT 1
);

SET @ddl_clinic := IF(
    @col_clinic = 'NO',
    'ALTER TABLE `wordpress_doctor_links` MODIFY `clinic_id` bigint(20) UNSIGNED NULL',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_clinic;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordpress_doctor_links'
      AND INDEX_NAME = 'uq_directory_doctor_id'
);

SET @ddl_idx := IF(
    @idx_exists = 0,
    'ALTER TABLE `wordpress_doctor_links` ADD UNIQUE KEY `uq_directory_doctor_id` (`directory_doctor_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
