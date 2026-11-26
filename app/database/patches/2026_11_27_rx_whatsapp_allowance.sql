-- =====================================================================
-- Prescription on WhatsApp: free monthly allowance + paid packs + secure links
-- 2026-11-27
--
-- Run ONCE on the server BEFORE uploading the PHP:
--   mysql -u USER -p DBNAME < app/database/patches/2026_11_27_rx_whatsapp_allowance.sql
-- Idempotent (IF NOT EXISTS / NOT EXISTS guards): safe to re-run.
--
--  1. rx_share_links    — one secure, expiring link per visit (/rx/{token})
--  2. rx_wa_accounts    — per clinic: purchased balance, free override, on/off
--  3. rx_wa_monthly     — per clinic per month: free messages used
--  4. rx_wa_ledger      — every use / refund / purchase / admin grant
--  5. rx_wa_orders      — Razorpay pack purchases
--  6. platform_settings — default free per month (100) + pack prices
--  7. wa_templates      — 'rx_whatsapp_doc' (document header), seeded DRAFT.
--                         Nothing sends until it is approved in Meta AND
--                         marked 'approved' in /admin/messaging.
-- =====================================================================

-- 1. Secure prescription links. The URL token is "{id}-{HMAC(JWT_SECRET, id|nonce)}":
--    only the random nonce is stored, so a leaked DB dump alone can't be turned
--    into working links. Changing the nonce revokes the old link.
CREATE TABLE IF NOT EXISTS `rx_share_links` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clinic_id`     BIGINT UNSIGNED NOT NULL,
  `visit_id`      BIGINT UNSIGNED NOT NULL,
  `patient_id`    BIGINT UNSIGNED NOT NULL,
  `token_nonce`   CHAR(32)        NOT NULL,
  `file_path`     VARCHAR(255)    NOT NULL,
  `expires_at`    DATETIME        NOT NULL,
  `view_count`    INT UNSIGNED    NOT NULL DEFAULT 0,
  `last_viewed_at` DATETIME       DEFAULT NULL,
  `revoked_at`    DATETIME        DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rx_share_visit` (`clinic_id`, `visit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Per-clinic account. monthly_free_override NULL = use the platform default.
CREATE TABLE IF NOT EXISTS `rx_wa_accounts` (
  `clinic_id`             BIGINT UNSIGNED NOT NULL,
  `paid_balance`          INT UNSIGNED    NOT NULL DEFAULT 0,
  `monthly_free_override` INT UNSIGNED    DEFAULT NULL,
  `is_enabled`            TINYINT(1)      NOT NULL DEFAULT 1,
  `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Free messages used per clinic per calendar month (resets by new row).
CREATE TABLE IF NOT EXISTS `rx_wa_monthly` (
  `clinic_id`  BIGINT UNSIGNED NOT NULL,
  `period_ym`  CHAR(7)         NOT NULL,
  `free_used`  INT UNSIGNED    NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`clinic_id`, `period_ym`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Ledger. qty is signed from the clinic's point of view (+ credit, - use).
CREATE TABLE IF NOT EXISTS `rx_wa_ledger` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clinic_id`       BIGINT UNSIGNED NOT NULL,
  `entry`           ENUM('free_use','paid_use','free_refund','paid_refund','purchase','admin_grant','admin_deduct') NOT NULL,
  `qty`             INT             NOT NULL,
  `period_ym`       CHAR(7)         NOT NULL,
  `visit_id`        BIGINT UNSIGNED DEFAULT NULL,
  `notification_id` BIGINT UNSIGNED DEFAULT NULL,
  `order_id`        BIGINT UNSIGNED DEFAULT NULL,
  `note`            VARCHAR(255)    DEFAULT NULL,
  `created_by`      BIGINT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rx_wa_ledger_clinic` (`clinic_id`, `created_at`),
  KEY `idx_rx_wa_ledger_visit` (`clinic_id`, `visit_id`),
  KEY `idx_rx_wa_ledger_notif` (`notification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Pack purchases (Razorpay). Credited once, when status flips to 'paid'.
CREATE TABLE IF NOT EXISTS `rx_wa_orders` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clinic_id`           BIGINT UNSIGNED NOT NULL,
  `user_id`             BIGINT UNSIGNED DEFAULT NULL,
  `qty`                 INT UNSIGNED    NOT NULL,
  `base_amount`         DECIMAL(10,2)   NOT NULL,
  `gst_amount`          DECIMAL(10,2)   NOT NULL,
  `total_amount`        DECIMAL(10,2)   NOT NULL,
  `razorpay_order_id`   VARCHAR(64)     NOT NULL,
  `razorpay_payment_id` VARCHAR(64)     DEFAULT NULL,
  `status`              ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `paid_at`             DATETIME        DEFAULT NULL,
  `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rx_wa_order_rzp` (`razorpay_order_id`),
  KEY `idx_rx_wa_orders_clinic` (`clinic_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Platform defaults (editable in /admin/rx-whatsapp).
--    Pack prices are in rupees, BEFORE 18% GST.
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`)
SELECT 'rx_wa_free_monthly', '100', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'rx_wa_free_monthly');

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`)
SELECT 'rx_wa_packs', '[{"qty":100,"price":99},{"qty":500,"price":399},{"qty":1000,"price":699}]', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'rx_wa_packs');

-- 7. The WhatsApp template. Create it in Meta (Business Manager → WhatsApp
--    Manager → Message templates) EXACTLY as:
--      Name: rx_prescription_doc   Category: Utility   Language: English (en)
--      Header: Document
--      Body: the body_text below
--      Footer: Sent via eClinicPro · Reply STOP to opt out
--    After Meta approves it, set status = 'approved' in /admin/messaging.
INSERT INTO `wa_templates` (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT 'rx_whatsapp_doc', 'rx_prescription_doc', 'en', 'utility',
    'Hello {{1}},\n\nYour prescription from Dr. {{2}} at {{3}}, dated {{4}}, is attached.\n\nPlease take your medicines as advised. For any questions, call the clinic at {{5}}.\n\nGet well soon!\n\neClinicPro.com\nSupport Team.',
    '["patient_name","doctor_name","clinic_name","visit_date","clinic_phone"]',
    NULL,
    'draft', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'rx_whatsapp_doc');

-- 8. GST tax invoices for pack purchases reuse saas_invoices (same ECP-YYYY-NNNNN
--    series, Admin → Payments, doctor's billing history). Packs carry their own
--    line description instead of a plan name. (MariaDB: ADD COLUMN IF NOT EXISTS.)
ALTER TABLE `saas_invoices`
  ADD COLUMN IF NOT EXISTS `item_description` VARCHAR(255) DEFAULT NULL AFTER `billing_cycle`;
