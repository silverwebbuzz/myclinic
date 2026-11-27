-- =====================================================================
-- Wellness Hub — Phase 4: free monthly posts + paid credits + GST invoices.
-- Plan: document/wellness-hub-plan.md §4
--
-- RUN AFTER: 2026_11_29_wellness_hub_phase3.sql
-- (Pricing settings wh_free_monthly / wh_packs / wh_gst_pct were seeded in Phase 1.)
-- Fresh-install style (no foreign keys). Safe to run more than once.
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

-- 1. Paid credit balance per creator. 1 credit = 1 extra post of any type the
--    creator is approved for. Credits never expire.
--    free_monthly_override: NULL = platform default per type.
CREATE TABLE IF NOT EXISTS `wh_credit_accounts` (
    `creator_id`            BIGINT UNSIGNED   NOT NULL,
    `paid_balance`          INT UNSIGNED      NOT NULL DEFAULT 0,
    `free_monthly_override` SMALLINT UNSIGNED DEFAULT NULL,
    `updated_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. What each post cost. One row per post (tip or event):
--    reserved on submit (free slot first, else 1 credit) → used on approval,
--    released (slot / credit returned) on reject or withdraw.
--    period_ym = month of SUBMISSION (IST). Free camps never get a row.
CREATE TABLE IF NOT EXISTS `wh_post_charges` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`    BIGINT UNSIGNED NOT NULL,
    `item_type`     ENUM('tip','event') NOT NULL,
    `item_id`       BIGINT UNSIGNED NOT NULL,
    `content_type`  VARCHAR(20)     NOT NULL,                      -- recipe | exercise | tip | video | event
    `period_ym`     CHAR(7)         NOT NULL,                      -- 2026-12
    `kind`          ENUM('free','paid') NOT NULL,
    `status`        ENUM('reserved','used','released') NOT NULL DEFAULT 'reserved',
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whpc_item` (`item_type`, `item_id`),
    KEY `idx_whpc_quota` (`creator_id`, `content_type`, `period_ym`, `kind`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Credit ledger. qty is signed from the creator's side (+ in, − out).
CREATE TABLE IF NOT EXISTS `wh_ledger` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`    BIGINT UNSIGNED NOT NULL,
    `entry`         ENUM('purchase','reserve','release','admin_grant','admin_deduct') NOT NULL,
    `qty`           INT             NOT NULL,
    `balance_after` INT             NOT NULL,
    `item_type`     VARCHAR(10)     DEFAULT NULL,
    `item_id`       BIGINT UNSIGNED DEFAULT NULL,
    `order_id`      BIGINT UNSIGNED DEFAULT NULL,
    `note`          VARCHAR(255)    DEFAULT NULL,
    `created_by`    BIGINT UNSIGNED DEFAULT NULL,                  -- admin id; NULL = creator / system
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whl_creator` (`creator_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Credit pack purchases (Razorpay). Credited exactly once, when status
--    flips to 'paid'. invoice_no is issued at that moment (series ECW-YYYY-NNNNN).
CREATE TABLE IF NOT EXISTS `wh_orders` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`          BIGINT UNSIGNED NOT NULL,
    `pack_qty`            INT UNSIGNED    NOT NULL,
    `base_amount`         DECIMAL(10,2)   NOT NULL,                -- before GST
    `gst_pct`             DECIMAL(5,2)    NOT NULL,
    `gst_amount`          DECIMAL(10,2)   NOT NULL,
    `total_amount`        DECIMAL(10,2)   NOT NULL,
    `razorpay_order_id`   VARCHAR(64)     NOT NULL,
    `razorpay_payment_id` VARCHAR(64)     DEFAULT NULL,
    `status`              ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    `paid_at`             DATETIME        DEFAULT NULL,
    `invoice_no`          VARCHAR(30)     DEFAULT NULL,
    `buyer_name`          VARCHAR(160)    DEFAULT NULL,            -- legal name on the invoice
    `buyer_gstin`         VARCHAR(15)     DEFAULT NULL,
    `buyer_state`         VARCHAR(80)     DEFAULT NULL,            -- place of supply
    `buyer_email`         VARCHAR(160)    DEFAULT NULL,
    `pdf_path`            VARCHAR(255)    DEFAULT NULL,
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_who_rzp` (`razorpay_order_id`),
    UNIQUE KEY `uq_who_invoice` (`invoice_no`),
    KEY `idx_who_creator` (`creator_id`, `created_at`),
    KEY `idx_who_paid` (`status`, `paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
