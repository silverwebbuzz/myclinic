-- =====================================================================
-- eClinicPro Store — customer WhatsApp order updates (2026-10-05)
--   Server: mysql silverwebbuzz_in_myclinic < app/database/patches/2026_10_05_store_whatsapp.sql
--
--  1. wa_templates: 8 store templates, seeded as 'draft'. Nothing is sent until a
--     template is approved in Meta Business Manager AND marked 'approved' on
--     /admin/messaging (WhatsAppService only sends approved templates).
--  2. store_email_log now logs WhatsApp attempts too (channel + notification_id;
--     statuses 'queued' and 'skipped'), shown on /admin/store/email.
--  3. platform_settings: store_wa_enabled (master switch), store_wa_disabled
--     (template keys switched off), store_default_pickup_pincode (checkout
--     pincode serviceability check).
--  4. notifications.clinic_id: drops the foreign key to tenants if it is still
--     there. Platform messages (store, directory leads) are queued with
--     clinic_id = 0, which no tenant row has, so the FK rejects them.
--
-- Safe to run more than once (MariaDB IF [NOT] EXISTS / WHERE NOT EXISTS / INSERT IGNORE).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. WhatsApp templates (category utility, language en)
--    `variables` = payload keys in {{1}}, {{2}}… order (StoreNotifier fills them).
-- ---------------------------------------------------------------------
INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_order_confirmed', 'store_order_confirmed', 'en', 'utility',
    'Hi {{1}}, thank you for shopping on eClinicPro Store.\n\nYour order {{2}} is confirmed and we have received your payment of {{3}}. It will reach you in {{4}} package(s), and we will message you as each one ships.\n\nView your order: {{5}}\n\nTeam eClinicPro Store',
    '["name","order_no","amount","packages","order_url"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_order_confirmed');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_order_shipped', 'store_order_shipped', 'en', 'utility',
    'Hi {{1}}, your package from {{2}} for order {{3}} has been shipped.\n\nCourier: {{4}}\nTracking number: {{5}}\nTrack your package: {{6}}\n\nTeam eClinicPro Store',
    '["name","seller","order_no","courier","awb","tracking_url"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_order_shipped');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_order_out_for_delivery', 'store_order_out_for_delivery', 'en', 'utility',
    'Hi {{1}}, your package from {{2}} for order {{3}} is out for delivery today. Please keep your phone reachable for the delivery agent.\n\nCourier: {{4}}\nTracking number: {{5}}\nTrack your package: {{6}}\n\nTeam eClinicPro Store',
    '["name","seller","order_no","courier","awb","tracking_url"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_order_out_for_delivery');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_order_delivered', 'store_order_delivered', 'en', 'utility',
    'Hi {{1}}, your package from {{2}} for order {{3}} has been delivered.\n\nIf an item is damaged, wrong or expired, you can request a return from your order page within the return window: {{4}}\n\nTeam eClinicPro Store',
    '["name","seller","order_no","order_url"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_order_delivered');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_order_cancelled', 'store_order_cancelled', 'en', 'utility',
    'Hi {{1}}, items in your order {{2}} were cancelled {{3}}.\n\nWe have refunded {{4}} to your original payment method (refund reference {{5}}). Refunds usually reach your account in 5 to 7 working days, depending on your bank.\n\nTeam eClinicPro Store',
    '["name","order_no","who","amount","refund_no"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_order_cancelled');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_refund_processed', 'store_refund_processed', 'en', 'utility',
    'Hi {{1}}, we have refunded {{2}} for your return {{3}} (order {{4}}) to your original payment method.\n\nRefunds usually reach your account in 5 to 7 working days, depending on your bank.\n\nTeam eClinicPro Store',
    '["name","amount","return_no","order_no"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_refund_processed');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_return_update', 'store_return_update', 'en', 'utility',
    'Hi {{1}}, there is an update on your return {{2}} for order {{3}}.\n\n{{4}}\n\nSee the details: {{5}}\n\nTeam eClinicPro Store',
    '["name","return_no","order_no","update","order_url"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_return_update');

INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'store_order_expired', 'store_order_expired', 'en', 'utility',
    'Hi {{1}}, we did not receive the payment for your eClinicPro Store order {{2}} of {{3}} in time, so the order was cancelled and nothing was charged.\n\nYour cart is still saved if you want to check out again: {{4}}\n\nTeam eClinicPro Store',
    '["name","order_no","amount","cart_url"]', NULL, 'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'store_order_expired');

-- ---------------------------------------------------------------------
-- 2. One log for store email + WhatsApp
-- ---------------------------------------------------------------------
ALTER TABLE `store_email_log`
    ADD COLUMN IF NOT EXISTS `channel` ENUM('email','whatsapp') NOT NULL DEFAULT 'email' AFTER `template_key`,
    ADD COLUMN IF NOT EXISTS `notification_id` BIGINT UNSIGNED DEFAULT NULL AFTER `error`,   -- notifications.id of a queued WhatsApp
    MODIFY `status` ENUM('sent','failed','disabled','queued','skipped') NOT NULL;

-- ---------------------------------------------------------------------
-- 3. Settings
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_wa_enabled', '1', 0),               -- customer WhatsApp order updates on/off
    ('store_wa_disabled', '', 0),               -- comma-separated template keys switched off
    ('store_default_pickup_pincode', '', 0);    -- pickup pincode for the checkout serviceability check (blank = first seller pickup)

-- ---------------------------------------------------------------------
-- 4. notifications.clinic_id: allow 0 (platform messages)
--    Check first:  SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
--                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications';
-- ---------------------------------------------------------------------
ALTER TABLE `notifications` DROP FOREIGN KEY IF EXISTS `fk_notif_clinic`;
