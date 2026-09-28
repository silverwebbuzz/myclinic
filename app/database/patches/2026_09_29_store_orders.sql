-- /store marketplace — Batch 4 — Cart, orders, payments (P5–P6) · DRAFT · `2026_MM_DD_store_orders.sql`
-- Cart, addresses, coupons, shipping rules, orders, vendor sub-orders, payments, refunds (P5–P6).
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / guarded INSERTs).
--   phpMyAdmin: select database → Import → this file.

CREATE TABLE IF NOT EXISTS `store_customer_flags` (
    `identity_id`     BIGINT UNSIGNED NOT NULL,          -- patient_identities.id
    `is_blocked`      TINYINT(1)      NOT NULL DEFAULT 0,
    `blocked_reason`  VARCHAR(500)    DEFAULT NULL,
    `blocked_by`      BIGINT UNSIGNED DEFAULT NULL,
    `notes`           TEXT            DEFAULT NULL,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_addresses` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_id`  BIGINT UNSIGNED NOT NULL,
    `label`        VARCHAR(40)     DEFAULT NULL,         -- Home / Office
    `name`         VARCHAR(160)    NOT NULL,
    `phone`        VARCHAR(20)     NOT NULL,
    `line1`        VARCHAR(255)    NOT NULL,
    `line2`        VARCHAR(255)    DEFAULT NULL,
    `landmark`     VARCHAR(160)    DEFAULT NULL,
    `city`         VARCHAR(120)    NOT NULL,
    `state`        VARCHAR(120)    NOT NULL,
    `state_code`   CHAR(2)         DEFAULT NULL,
    `pincode`      CHAR(6)         NOT NULL,
    `country`      CHAR(2)         NOT NULL DEFAULT 'IN',
    `is_default`   TINYINT(1)      NOT NULL DEFAULT 0,
    `deleted_at`   TIMESTAMP       NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sad_identity` (`identity_id`, `deleted_at`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_carts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token`        CHAR(64)        NOT NULL,             -- guest cookie ecp_cart
    `identity_id`  BIGINT UNSIGNED DEFAULT NULL,         -- set on login (guest cart merged in)
    `coupon_code`  VARCHAR(40)     DEFAULT NULL,
    `expires_at`   TIMESTAMP       NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scart_token` (`token`),
    KEY `idx_scart_identity` (`identity_id`),
    KEY `idx_scart_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_cart_items` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id`           BIGINT UNSIGNED NOT NULL,
    `variant_id`        BIGINT UNSIGNED NOT NULL,
    `qty`               SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `price_seen_paise`  BIGINT UNSIGNED NOT NULL,        -- for "price changed" notice
    `added_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sci_cart_variant` (`cart_id`, `variant_id`),
    KEY `idx_sci_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_coupons` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`                VARCHAR(40)     NOT NULL,
    `type`                ENUM('percent','fixed','free_shipping') NOT NULL,
    `value`               INT UNSIGNED    NOT NULL DEFAULT 0,   -- bp for percent, paise for fixed
    `max_discount_paise`  BIGINT UNSIGNED DEFAULT NULL,
    `min_order_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `funded_by`           ENUM('platform','vendor') NOT NULL DEFAULT 'platform',
    `vendor_id`           BIGINT UNSIGNED DEFAULT NULL,         -- vendor-funded / vendor-only coupon
    `category_id`         BIGINT UNSIGNED DEFAULT NULL,
    `starts_at`           TIMESTAMP       NULL DEFAULT NULL,
    `ends_at`             TIMESTAMP       NULL DEFAULT NULL,
    `limit_total`         INT UNSIGNED    DEFAULT NULL,
    `limit_per_customer`  INT UNSIGNED    DEFAULT 1,
    `used_count`          INT UNSIGNED    NOT NULL DEFAULT 0,
    `is_active`           TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scp_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_coupon_redemptions` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `coupon_id`       BIGINT UNSIGNED NOT NULL,
    `order_id`        BIGINT UNSIGNED NOT NULL,
    `identity_id`     BIGINT UNSIGNED NOT NULL,
    `discount_paise`  BIGINT UNSIGNED NOT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scr_coupon_order` (`coupon_id`, `order_id`),
    KEY `idx_scr_identity` (`coupon_id`, `identity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- D5: flat fee per vendor sub-order, free above a threshold. vendor_id NULL = platform default.
CREATE TABLE IF NOT EXISTS `store_shipping_rules` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`         BIGINT UNSIGNED DEFAULT NULL,
    `flat_fee_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 4900,
    `free_above_paise`  BIGINT UNSIGNED DEFAULT 49900,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_ssr_vendor` (`vendor_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent order: one checkout = one Razorpay payment.
-- status: pending_payment | payment_failed | expired | paid | partially_shipped | shipped |
--         partially_delivered | delivered | completed | cancelled | partially_refunded | refunded
CREATE TABLE IF NOT EXISTS `store_orders` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no`              VARCHAR(24)     NOT NULL,          -- ECS-26-000123 (never expose id)
    `identity_id`           BIGINT UNSIGNED NOT NULL,
    `status`                VARCHAR(32)     NOT NULL DEFAULT 'pending_payment',
    `payment_status`        ENUM('pending','paid','failed','partially_refunded','refunded') NOT NULL DEFAULT 'pending',
    `contact_name`          VARCHAR(160)    NOT NULL,
    `contact_phone`         VARCHAR(20)     NOT NULL,
    `contact_email`         VARCHAR(190)    DEFAULT NULL,
    `ship_address_json`     JSON            NOT NULL,          -- snapshot
    `bill_address_json`     JSON            DEFAULT NULL,
    `items_subtotal_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `discount_paise`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `shipping_paise`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `tax_included_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0, -- GST portion (prices are GST-inclusive)
    `platform_fee_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `grand_total_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `client_total_paise`    BIGINT UNSIGNED DEFAULT NULL,      -- DIAGNOSTIC ONLY, never billed
    `coupon_id`             BIGINT UNSIGNED DEFAULT NULL,
    `coupon_code`           VARCHAR(40)     DEFAULT NULL,
    `checkout_key`          CHAR(36)        NOT NULL,          -- idempotency key from the checkout page
    `expires_at`            TIMESTAMP       NULL DEFAULT NULL, -- payment window
    `placed_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at`               TIMESTAMP       NULL DEFAULT NULL,
    `cancelled_at`          TIMESTAMP       NULL DEFAULT NULL,
    `source`                VARCHAR(20)     NOT NULL DEFAULT 'web',   -- web | mobile
    `created_ip`            VARBINARY(16)   DEFAULT NULL,
    `user_agent`            VARCHAR(255)    DEFAULT NULL,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_so_no` (`order_no`),
    UNIQUE KEY `uq_so_checkout` (`checkout_key`),
    KEY `idx_so_identity` (`identity_id`, `placed_at`),
    KEY `idx_so_status` (`status`, `placed_at`),
    KEY `idx_so_expiry` (`payment_status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One per vendor per order — the only order row a vendor can see.
-- status: pending_payment | new | accepted | packed | ready_to_ship | shipped | delivered | completed |
--         cancelled_by_vendor | cancelled_by_customer | cancelled_by_admin | auto_cancelled | rto
CREATE TABLE IF NOT EXISTS `store_vendor_orders` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sub_order_no`             VARCHAR(28)     NOT NULL,          -- ECS-26-000123-A
    `order_id`                 BIGINT UNSIGNED NOT NULL,
    `vendor_id`                BIGINT UNSIGNED NOT NULL,
    `status`                   VARCHAR(32)     NOT NULL DEFAULT 'pending_payment',
    `items_subtotal_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `vendor_discount_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- reduces vendor payable
    `platform_discount_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- platform absorbs
    `shipping_paise`           BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `tax_included_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `commission_paise`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `commission_gst_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- 18% GST on commission (VERIFY WITH CA)
    `tcs_paise`                BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- GST TCS sec. 52 (VERIFY WITH CA)
    `tds_paise`                BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- IT 194-O (VERIFY WITH CA)
    `vendor_payable_paise`     BIGINT          NOT NULL DEFAULT 0,  -- signed: can go negative after refunds
    `pickup_address_id`        BIGINT UNSIGNED DEFAULT NULL,
    `pickup_snapshot_json`     JSON            DEFAULT NULL,
    `accept_by`                TIMESTAMP       NULL DEFAULT NULL,   -- SLA deadline
    `accepted_at`              TIMESTAMP       NULL DEFAULT NULL,
    `packed_at`                TIMESTAMP       NULL DEFAULT NULL,
    `shipped_at`               TIMESTAMP       NULL DEFAULT NULL,
    `delivered_at`             TIMESTAMP       NULL DEFAULT NULL,
    `settle_after`             TIMESTAMP       NULL DEFAULT NULL,   -- delivered_at + return window
    `settlement_status`        ENUM('unsettled','on_hold','eligible','in_payout','paid') NOT NULL DEFAULT 'unsettled',
    `cancel_reason`            VARCHAR(500)    DEFAULT NULL,
    `cancelled_by_type`        ENUM('customer','vendor_user','admin','system') DEFAULT NULL,
    `cancelled_by_id`          BIGINT UNSIGNED DEFAULT NULL,
    `created_at`               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_svo_no` (`sub_order_no`),
    UNIQUE KEY `uq_svo_order_vendor` (`order_id`, `vendor_id`),
    KEY `idx_svo_vendor` (`vendor_id`, `status`, `created_at`),   -- vendor order list
    KEY `idx_svo_sla` (`status`, `accept_by`),                    -- auto-cancel cron
    KEY `idx_svo_settle` (`settlement_status`, `settle_after`)    -- payout eligibility cron
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order lines with name/price/commission SNAPSHOTS (catalog can change later).
CREATE TABLE IF NOT EXISTS `store_order_items` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`                 BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`          BIGINT UNSIGNED NOT NULL,
    `vendor_id`                BIGINT UNSIGNED NOT NULL,
    `product_id`               BIGINT UNSIGNED NOT NULL,          -- audit only
    `variant_id`               BIGINT UNSIGNED NOT NULL,          -- audit only
    `sku`                      VARCHAR(80)     NOT NULL,
    `name`                     VARCHAR(255)    NOT NULL,
    `variant_title`            VARCHAR(190)    DEFAULT NULL,
    `image_path`               VARCHAR(255)    DEFAULT NULL,
    `category_id`              BIGINT UNSIGNED DEFAULT NULL,      -- primary category at checkout
    `hsn_code`                 VARCHAR(8)      DEFAULT NULL,
    `gst_bp`                   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `qty`                      SMALLINT UNSIGNED NOT NULL,
    `mrp_paise`                BIGINT UNSIGNED NOT NULL,
    `unit_price_paise`         BIGINT UNSIGNED NOT NULL,
    `line_subtotal_paise`      BIGINT UNSIGNED NOT NULL,          -- unit × qty
    `vendor_discount_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `platform_discount_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `tax_included_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `line_total_paise`         BIGINT UNSIGNED NOT NULL,          -- what the customer paid for this line
    `commission_type`          ENUM('percent','fixed') NOT NULL,
    `commission_rate_bp`       INT UNSIGNED    NOT NULL DEFAULT 0,
    `commission_rule_id`       BIGINT UNSIGNED DEFAULT NULL,
    `commission_paise`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `vendor_payable_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `qty_cancelled`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `qty_returned`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `qty_refunded`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status`                   VARCHAR(32)     NOT NULL DEFAULT 'active',   -- active | cancelled | returned | partially_returned
    PRIMARY KEY (`id`),
    KEY `idx_soi_order` (`order_id`),
    KEY `idx_soi_vendor_order` (`vendor_order_id`),
    KEY `idx_soi_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_order_status_history` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`         BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`  BIGINT UNSIGNED DEFAULT NULL,
    `entity`           ENUM('order','vendor_order','shipment','return','payment') NOT NULL,
    `entity_id`        BIGINT UNSIGNED DEFAULT NULL,
    `from_status`      VARCHAR(32)     DEFAULT NULL,
    `to_status`        VARCHAR(32)     NOT NULL,
    `actor_type`       ENUM('system','customer','vendor_user','admin','webhook') NOT NULL,
    `actor_id`         BIGINT UNSIGNED DEFAULT NULL,
    `note`             VARCHAR(500)    DEFAULT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sosh_order` (`order_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_payments` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`         BIGINT UNSIGNED NOT NULL,
    `gateway`          VARCHAR(20)     NOT NULL DEFAULT 'razorpay',
    `rzp_order_id`     VARCHAR(40)     NOT NULL,
    `rzp_payment_id`   VARCHAR(40)     DEFAULT NULL,
    `amount_paise`     BIGINT UNSIGNED NOT NULL,
    `currency`         CHAR(3)         NOT NULL DEFAULT 'INR',
    `status`           ENUM('created','attempted','captured','failed','partially_refunded','refunded') NOT NULL DEFAULT 'created',
    `method`           VARCHAR(20)     DEFAULT NULL,       -- upi | card | netbanking | wallet
    `captured_at`      TIMESTAMP       NULL DEFAULT NULL,
    `failure_code`     VARCHAR(80)     DEFAULT NULL,
    `failure_reason`   VARCHAR(500)    DEFAULT NULL,
    `refunded_paise`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spay_rzp_order` (`rzp_order_id`),
    UNIQUE KEY `uq_spay_rzp_payment` (`rzp_payment_id`),
    KEY `idx_spay_order` (`order_id`),
    KEY `idx_spay_status` (`status`, `created_at`)       -- reconciliation cron
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every gateway event we act on; UNIQUE(type, gateway_ref) makes replays harmless.
CREATE TABLE IF NOT EXISTS `store_payment_transactions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id`    BIGINT UNSIGNED NOT NULL,
    `type`          ENUM('attempt','capture','refund','transfer','transfer_reversal') NOT NULL,
    `gateway_ref`   VARCHAR(40)     NOT NULL,          -- pay_… / rfnd_… / trf_…
    `amount_paise`  BIGINT UNSIGNED NOT NULL,
    `status`        VARCHAR(20)     NOT NULL,
    `payload`       JSON            DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spt_ref` (`type`, `gateway_ref`),
    KEY `idx_spt_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds are item-level; SUM(amount) <= captured is enforced under SELECT … FOR UPDATE on store_payments.
CREATE TABLE IF NOT EXISTS `store_refunds` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `refund_no`           VARCHAR(24)     NOT NULL,
    `order_id`            BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`     BIGINT UNSIGNED DEFAULT NULL,
    `return_id`           BIGINT UNSIGNED DEFAULT NULL,
    `payment_id`          BIGINT UNSIGNED NOT NULL,
    `rzp_refund_id`       VARCHAR(40)     DEFAULT NULL,
    `amount_paise`        BIGINT UNSIGNED NOT NULL,
    `reason`              VARCHAR(40)     NOT NULL,      -- cancel | return | late_payment | vendor_sla | admin
    `note`                VARCHAR(500)    DEFAULT NULL,
    `status`              ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
    `initiated_by_type`   ENUM('system','customer','vendor_user','admin') NOT NULL,
    `initiated_by_id`     BIGINT UNSIGNED DEFAULT NULL,
    `processed_at`        TIMESTAMP       NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sref_no` (`refund_no`),
    UNIQUE KEY `uq_sref_rzp` (`rzp_refund_id`),
    KEY `idx_sref_order` (`order_id`),
    KEY `idx_sref_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_refund_items` (
    `refund_id`       BIGINT UNSIGNED NOT NULL,
    `order_item_id`   BIGINT UNSIGNED NOT NULL,
    `qty`             SMALLINT UNSIGNED NOT NULL,
    `amount_paise`    BIGINT UNSIGNED NOT NULL,
    `shipping_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`refund_id`, `order_item_id`),
    KEY `idx_sri_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Platform default shipping rule (vendor_id NULL): ₹49 per seller sub-order, free above ₹499
-- from that seller. Editable in /admin/store/settings.
INSERT INTO `store_shipping_rules` (`vendor_id`, `flat_fee_paise`, `free_above_paise`, `is_active`)
SELECT NULL, 4900, 49900, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `store_shipping_rules` WHERE `vendor_id` IS NULL);
