-- =====================================================================
-- eClinicPro Store — eClinicPro Points: welcome, Refer & Earn, loyalty (2026-10-07)
--   Server: mysql silverwebbuzz_in_myclinic < app/database/patches/2026_10_07_store_points.sql
--   Plan: document/store-rewards-plan.md
--
--  1. store_point_lots    — one row per earning (welcome 100 / referral 100 / loyalty 10%).
--  2. store_point_txns    — append-only ledger (earn, spend, refund, expire, reverse, adjust).
--  3. store_referral_codes + store_referrals — Refer & Earn (used from batch 2).
--  4. store_orders        — points used on the order + their state.
--  5. platform_settings   — store_points_* (all OFF / empty launch date until you switch it on).
--  6. wa_templates        — store_points_ready, seeded 'draft' (nothing is sent until it's approved
--                           in Meta Business Manager AND marked 'approved' on /admin/messaging).
--
-- 1 point = ₹1 off. Points are a PLATFORM-funded discount: they sit in
-- store_order_items.platform_discount_paise like a platform coupon, so the seller is unaffected.
--
-- Safe to run more than once (IF NOT EXISTS / INSERT IGNORE).
-- =====================================================================

CREATE TABLE IF NOT EXISTS `store_point_lots` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_id`   BIGINT UNSIGNED NOT NULL,                     -- patient_identities.id
    `kind`          ENUM('welcome','referral','loyalty','admin') NOT NULL,
    `uniq_key`      VARCHAR(64)     NOT NULL,                     -- welcome | ref:{referral_id} | loyalty:{order_id} | admin:{txn}
    `points`        INT UNSIGNED    NOT NULL,                     -- as granted
    `points_left`   INT UNSIGNED    NOT NULL,                     -- still spendable
    `status`        ENUM('pending','available','expired','reversed') NOT NULL DEFAULT 'pending',
    `referral_id`   BIGINT UNSIGNED DEFAULT NULL,
    `order_id`      BIGINT UNSIGNED DEFAULT NULL,                 -- the order that earned it (loyalty / referral)
    `note`          VARCHAR(255)    DEFAULT NULL,                 -- "Waiting for Rahul's first order to be delivered"
    `available_at`  DATETIME        DEFAULT NULL,                 -- pending → available at this time (NULL = not decided yet)
    `expires_at`    DATETIME        DEFAULT NULL,                 -- set when it becomes available
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spl_once` (`identity_id`, `uniq_key`),
    KEY `idx_spl_spend` (`identity_id`, `status`, `expires_at`),
    KEY `idx_spl_due` (`status`, `available_at`),
    KEY `idx_spl_expiry` (`status`, `expires_at`),
    KEY `idx_spl_order` (`order_id`),
    KEY `idx_spl_referral` (`referral_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_point_txns` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_id`  BIGINT UNSIGNED NOT NULL,
    `lot_id`       BIGINT UNSIGNED NOT NULL,
    `order_id`     BIGINT UNSIGNED DEFAULT NULL,
    `type`         ENUM('earn','available','spend','refund','expire','reverse','adjust') NOT NULL,
    `points`       INT             NOT NULL,                      -- + adds to points_left, − takes away
    `note`         VARCHAR(255)    DEFAULT NULL,
    `actor_type`   ENUM('system','customer','admin') NOT NULL DEFAULT 'system',
    `actor_id`     BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_spt_identity` (`identity_id`, `created_at`),
    KEY `idx_spt_order` (`order_id`, `type`),
    KEY `idx_spt_lot` (`lot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Code unlocks (activated_at) after the person's own first order is fully delivered.
CREATE TABLE IF NOT EXISTS `store_referral_codes` (
    `identity_id`   BIGINT UNSIGNED NOT NULL,
    `code`          VARCHAR(20)     NOT NULL,                     -- ECP-RAHUL7
    `activated_at`  DATETIME        DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`identity_id`),
    UNIQUE KEY `uq_src_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_referrals` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer_identity_id`  BIGINT UNSIGNED NOT NULL,
    `referee_identity_id`   BIGINT UNSIGNED NOT NULL,
    `code`                  VARCHAR(20)     NOT NULL,
    `status`                ENUM('signed_up','qualified','rewarded','rejected') NOT NULL DEFAULT 'signed_up',
    `qualifying_order_id`   BIGINT UNSIGNED DEFAULT NULL,
    `flag_reason`           VARCHAR(255)    DEFAULT NULL,         -- soft fraud flag for admin review (never blocks)
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sr_referee` (`referee_identity_id`),           -- a person can be referred only once
    KEY `idx_sr_referrer` (`referrer_identity_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- points_state: none | reserved (unpaid order holds them) | redeemed (paid) | released (given back)
ALTER TABLE `store_orders`
    ADD COLUMN IF NOT EXISTS `points_used`           INT UNSIGNED    NOT NULL DEFAULT 0      AFTER `coupon_code`,
    ADD COLUMN IF NOT EXISTS `points_discount_paise` BIGINT UNSIGNED NOT NULL DEFAULT 0      AFTER `points_used`,
    ADD COLUMN IF NOT EXISTS `points_kind`           ENUM('welcome','standard') DEFAULT NULL AFTER `points_discount_paise`,
    ADD COLUMN IF NOT EXISTS `points_state`          ENUM('none','reserved','redeemed','released') NOT NULL DEFAULT 'none' AFTER `points_kind`;

-- store_points_enabled stays 0 and store_points_launch_at empty until you switch them on in phpMyAdmin
-- (batch 3 adds an admin screen). Only self-signups created AFTER the launch time get welcome points.
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_points_enabled',                 '0',     0),
    ('store_points_launch_at',               '',      0),   -- e.g. 2026-10-10 00:00:00
    ('store_points_welcome',                 '100',   0),
    ('store_points_welcome_min_order_paise', '50000', 0),   -- welcome points need a ₹500+ order
    ('store_points_referral_each',           '100',   0),
    ('store_points_redeem_cap_bp',           '1000',  0),   -- referral/loyalty points: max 10% of the order
    ('store_points_loyalty_earn_bp',         '1000',  0),   -- 10% back on orders that used no points
    ('store_points_expiry_days',             '180',   0);

-- 6. "Points ready" message (sent from the store cron when pending points become usable).
INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_points_ready', 'store_points_ready', 'en', 'utility',
    'Hi {{1}}, {{2}} eClinicPro Points are now ready to use on the eClinicPro Store. Your balance is {{3}} points, applied automatically at checkout.\n\nSee your points: {{4}}\n\nTeam eClinicPro Store',
    '["name","points","balance","points_url"]',
    'Hi {{1}}, {{2}} eClinicPro Points are ready to use. Balance: {{3}} points. {{4}} - eClinicPro',
    'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_points_ready');
