-- Idempotent patch: directory_outreach — per-clinic acquisition outreach
-- tracking for the /admin/outreach worklist (non-joined directory doctors who
-- are receiving patient leads). One row per directory_doctors.id.
--
-- Phase 1 uses: status, last_contacted_at, contacted_count, last_channel,
-- notes, opted_out. Phase 2 (bulk send) will increment contacted_count and
-- respect opted_out + a monthly contact cap.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_06_26_add_directory_outreach.sql

SET @tbl_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_outreach'
);

SET @ddl := IF(
    @tbl_exists = 0,
    'CREATE TABLE `directory_outreach` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `directory_doctor_id` bigint(20) UNSIGNED NOT NULL,
        `status` enum(''not_contacted'',''contacted'',''interested'',''joined'',''not_now'',''opted_out'') NOT NULL DEFAULT ''not_contacted'',
        `last_channel` enum(''whatsapp'',''sms'',''email'',''phone'') DEFAULT NULL,
        `last_contacted_at` datetime DEFAULT NULL,
        `contacted_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
        `opted_out` tinyint(1) NOT NULL DEFAULT 0,
        `opted_out_at` datetime DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_directory_doctor` (`directory_doctor_id`),
        KEY `idx_status` (`status`),
        KEY `idx_last_contacted` (`last_contacted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
