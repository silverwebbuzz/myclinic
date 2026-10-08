-- =====================================================================
-- eClinicPro Store — two-way seller trust (2026-10-08)
--   Server: run in phpMyAdmin AFTER 2026_10_08_store_platform_legal.sql
--
--  1. store_charge_disputes  — seller disputes a deduction; admin accepts (charge reversed) or rejects.
--  2. store_policy_versions / store_policy_pages — effective_at (new terms apply after the notice period).
--  3. platform_settings — every number the seller terms promise, editable in
--     /admin/store/settings → "Seller terms & payouts" (no code change needed to change them):
--       store_return_window_hours           24   one return window for every product (hours)
--       store_payout_weekday                2    1 = Monday … 7 = Sunday (2 = Tuesday)
--       store_payout_auto_batch             1    worker creates the payout batch on that day
--       store_charge_dispute_days           7    seller can dispute a deduction within N days
--       store_charge_dispute_response_days  7    eClinicPro answers a dispute within N days
--       store_terms_notice_days             15   new seller terms apply N days after publishing
--       store_suspension_notice_days        7    days a seller gets to respond before suspension
--       store_final_settlement_days         30   final payout after closing (after last return window)
--       store_grievance_ack_hours           48
--       store_grievance_resolve_days        30
--
-- Safe to run more than once.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `store_charge_disputes` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`        BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`  BIGINT UNSIGNED NOT NULL,
    `ledger_id`        BIGINT UNSIGNED NOT NULL,                    -- the disputed store_vendor_ledger row
    `amount_paise`     BIGINT UNSIGNED NOT NULL,                    -- charge amount (positive) at the time
    `reason`           VARCHAR(1000)   NOT NULL,
    `status`           ENUM('open','accepted','rejected') NOT NULL DEFAULT 'open',
    `resolution_note`  VARCHAR(1000)   DEFAULT NULL,
    `created_by`       BIGINT UNSIGNED NOT NULL,                    -- store_vendor_users.id
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `respond_by`       DATETIME        NOT NULL,                    -- promised answer date
    `resolved_by`      BIGINT UNSIGNED DEFAULT NULL,                -- super admin id
    `resolved_at`      DATETIME        DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scd_ledger` (`ledger_id`),                       -- one dispute per charge
    KEY `idx_scd_status` (`status`, `respond_by`),
    KEY `idx_scd_vendor` (`vendor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `store_policy_versions`
    ADD COLUMN IF NOT EXISTS `effective_at` DATETIME DEFAULT NULL AFTER `body`;
ALTER TABLE `store_policy_pages`
    ADD COLUMN IF NOT EXISTS `effective_at` DATETIME DEFAULT NULL AFTER `version`;

-- Return window: one platform rule, in hours (decided 2026-10-08: 24 hours).
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_return_window_hours', '24', 0)
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_payout_weekday',                '2',  0),
    ('store_payout_auto_batch',             '1',  0),
    ('store_payout_last_auto_batch',        '',   0),
    ('store_charge_dispute_days',           '7',  0),
    ('store_charge_dispute_response_days',  '7',  0),
    ('store_terms_notice_days',             '15', 0),
    ('store_suspension_notice_days',        '7',  0),
    ('store_final_settlement_days',         '30', 0),
    ('store_grievance_ack_hours',           '48', 0),
    ('store_grievance_resolve_days',        '30', 0);
