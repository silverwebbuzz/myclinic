-- Idempotent patch: add the non-joined-doctor WhatsApp alert cap columns to
-- directory_leads, plus the platform default cap setting. Safe to run on an
-- existing production DB created before these existed. install.sql already
-- contains them for fresh installs; this brings older databases up to date.
--
-- Without this patch /admin/leads errors out, because LeadAnalyticsService
-- queries reference doctor_alert_capped / doctor_alert_sent_at.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_06_26_add_directory_lead_doctor_alert_cap.sql

-- 1) directory_leads.doctor_alert_sent_at -----------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_leads'
      AND COLUMN_NAME = 'doctor_alert_sent_at'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `directory_leads`
        ADD COLUMN `doctor_alert_sent_at` datetime DEFAULT NULL
        AFTER `doctor_contacted_patient`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) directory_leads.doctor_alert_capped ------------------------------------
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'directory_leads'
      AND COLUMN_NAME = 'doctor_alert_capped'
);
SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `directory_leads`
        ADD COLUMN `doctor_alert_capped` tinyint(1) NOT NULL DEFAULT 0
        AFTER `doctor_alert_sent_at`',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) platform_settings.directory_doctor_wa_cap (default 10) -----------------
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`, `updated_at`)
SELECT 'directory_doctor_wa_cap', '10', 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'directory_doctor_wa_cap'
);
