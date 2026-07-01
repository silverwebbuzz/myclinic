-- Idempotent patch: messaging_consent — the recipient opt-IN ledger (positive
-- mirror of messaging_optout).
--
-- Meta's Acceptable Use policy requires a demonstrable opt-in for
-- business-initiated WhatsApp. We record consent at the moment a person proves
-- control of their number and chooses to use the service:
--   - OTP verification on /patient  (source 'otp_verify')  — strongest basis
--   - a directory booking submission (source 'booking')
--
-- One durable row per phone_tail (last 10 digits, canonical match). We keep the
-- FIRST opt-in (ON DUPLICATE ... = created_at) so the timestamp is the true
-- moment consent was first given, which is what an audit/appeal needs.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_07_01_messaging_consent.sql

SET @tbl_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messaging_consent'
);

SET @ddl := IF(
    @tbl_exists = 0,
    'CREATE TABLE `messaging_consent` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `phone_tail` varchar(10) NOT NULL COMMENT ''last 10 digits, canonical match'',
        `source` varchar(32) NOT NULL COMMENT ''otp_verify | booking | admin'',
        `patient_identity_id` bigint(20) UNSIGNED DEFAULT NULL,
        `raw_phone` varchar(32) DEFAULT NULL COMMENT ''the full number as provided'',
        `ip` varchar(45) DEFAULT NULL COMMENT ''IP at time of opt-in, for audit'',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_phone_tail` (`phone_tail`),
        KEY `idx_identity` (`patient_identity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
