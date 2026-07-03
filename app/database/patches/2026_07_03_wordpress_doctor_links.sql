-- Idempotent patch: wordpress_doctor_links — maps portal doctors to WordPress authors.
-- Run: mysql -u USER -p DB < app/database/patches/2026_07_03_wordpress_doctor_links.sql

SET @tbl_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordpress_doctor_links'
);

SET @ddl := IF(
    @tbl_exists = 0,
    'CREATE TABLE `wordpress_doctor_links` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` bigint(20) UNSIGNED NOT NULL,
        `clinic_id` bigint(20) UNSIGNED NOT NULL,
        `directory_doctor_id` bigint(20) UNSIGNED DEFAULT NULL,
        `wp_user_id` bigint(20) UNSIGNED NOT NULL,
        `wp_username` varchar(60) NOT NULL,
        `wp_email` varchar(191) NOT NULL,
        `bridge_token_hash` char(64) NOT NULL,
        `linked_by_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
        `status` enum(''active'',''revoked'') NOT NULL DEFAULT ''active'',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_user_id` (`user_id`),
        UNIQUE KEY `uq_wp_user_id` (`wp_user_id`),
        KEY `idx_clinic_id` (`clinic_id`),
        KEY `idx_directory_doctor_id` (`directory_doctor_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
