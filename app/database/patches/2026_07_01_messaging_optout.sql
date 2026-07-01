-- Idempotent patch: messaging_optout — the recipient-driven opt-out ledger.
--
-- WhatsApp/Meta's Acceptable Use policy requires an easy, honoured opt-out.
-- Any inbound STOP/UNSUBSCRIBE (WhatsApp or SMS) writes a row here; the
-- NotificationProcessor blocks all further business-initiated WhatsApp + SMS
-- to that number. One row per (phone_e164, channel); channel 'all' blocks both.
--
-- Phone is stored as the last-10-digit tail (canonical India match), mirroring
-- how whatsapp_status is matched elsewhere, so +91 prefix variance never lets a
-- blocked number through.
--
-- Run on a live DB with:
--   mysql -u USER -p DB < app/database/patches/2026_07_01_messaging_optout.sql

SET @tbl_exists := (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messaging_optout'
);

SET @ddl := IF(
    @tbl_exists = 0,
    'CREATE TABLE `messaging_optout` (
        `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `phone_tail` varchar(10) NOT NULL COMMENT ''last 10 digits, canonical match'',
        `channel` enum(''all'',''whatsapp'',''sms'') NOT NULL DEFAULT ''all'',
        `source` varchar(32) NOT NULL DEFAULT ''inbound_stop'' COMMENT ''inbound_stop | admin | api'',
        `keyword` varchar(64) DEFAULT NULL COMMENT ''the word that triggered it (STOP/UNSUBSCRIBE)'',
        `raw_from` varchar(32) DEFAULT NULL COMMENT ''the full number as received'',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_phone_channel` (`phone_tail`,`channel`),
        KEY `idx_phone_tail` (`phone_tail`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
