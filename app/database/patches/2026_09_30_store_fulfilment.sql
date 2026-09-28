-- /store marketplace — Batch 5 — Shipping, returns, settlement, reviews (P8–P11) · DRAFT · `2026_MM_DD_store_fulfilment.sql`
-- Shipments + tracking (P8), returns (P10), vendor ledger + payouts (P9), reviews, banners.
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run.   phpMyAdmin: select database → Import → this file.

-- status: created | sr_order_created | awb_assigned | pickup_scheduled | picked_up | in_transit |
--         out_for_delivery | delivered | awb_failed | pickup_failed | ndr | rto_initiated |
--         rto_delivered | cancelled | lost | damaged
CREATE TABLE IF NOT EXISTS `store_shipments` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_no`            VARCHAR(28)     NOT NULL,
    `direction`              ENUM('forward','return') NOT NULL DEFAULT 'forward',
    `order_id`               BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`        BIGINT UNSIGNED NOT NULL,
    `vendor_id`              BIGINT UNSIGNED NOT NULL,
    `return_id`              BIGINT UNSIGNED DEFAULT NULL,
    `status`                 VARCHAR(32)     NOT NULL DEFAULT 'created',
    `status_rank`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- monotonic guard vs out-of-order webhooks
    `sr_order_id`            VARCHAR(40)     DEFAULT NULL,
    `sr_shipment_id`         VARCHAR(40)     DEFAULT NULL,
    `awb_code`               VARCHAR(40)     DEFAULT NULL,
    `courier_id`             INT UNSIGNED    DEFAULT NULL,
    `courier_name`           VARCHAR(120)    DEFAULT NULL,
    `pickup_location_name`   VARCHAR(60)     DEFAULT NULL,
    `pickup_snapshot_json`   JSON            DEFAULT NULL,
    `delivery_snapshot_json` JSON            DEFAULT NULL,
    `weight_g`               INT UNSIGNED    NOT NULL DEFAULT 0,
    `length_mm`              INT UNSIGNED    NOT NULL DEFAULT 0,
    `breadth_mm`             INT UNSIGNED    NOT NULL DEFAULT 0,
    `height_mm`              INT UNSIGNED    NOT NULL DEFAULT 0,
    `declared_value_paise`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `actual_charge_paise`    BIGINT UNSIGNED DEFAULT NULL,         -- what Shiprocket billed us
    `label_url`              VARCHAR(500)    DEFAULT NULL,
    `manifest_url`           VARCHAR(500)    DEFAULT NULL,
    `invoice_url`            VARCHAR(500)    DEFAULT NULL,
    `pickup_scheduled_for`   DATE            DEFAULT NULL,
    `picked_up_at`           TIMESTAMP       NULL DEFAULT NULL,
    `delivered_at`           TIMESTAMP       NULL DEFAULT NULL,
    `rto_at`                 TIMESTAMP       NULL DEFAULT NULL,
    `last_raw_status`        VARCHAR(80)     DEFAULT NULL,
    `last_event_at`          TIMESTAMP       NULL DEFAULT NULL,
    `next_poll_at`           TIMESTAMP       NULL DEFAULT NULL,
    `attempts`               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`             TEXT            DEFAULT NULL,
    `created_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ssh_no` (`shipment_no`),
    UNIQUE KEY `uq_ssh_sr_shipment` (`sr_shipment_id`),
    UNIQUE KEY `uq_ssh_awb` (`awb_code`),
    KEY `idx_ssh_vendor_order` (`vendor_order_id`),
    KEY `idx_ssh_vendor` (`vendor_id`, `status`),
    KEY `idx_ssh_poll` (`status`, `next_poll_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_shipment_items` (
    `shipment_id`    BIGINT UNSIGNED NOT NULL,
    `order_item_id`  BIGINT UNSIGNED NOT NULL,
    `qty`            SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`shipment_id`, `order_item_id`),
    KEY `idx_sshi_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_shipment_events` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_id`      BIGINT UNSIGNED NOT NULL,
    `raw_status`       VARCHAR(80)     NOT NULL,
    `raw_code`         VARCHAR(20)     DEFAULT NULL,
    `internal_status`  VARCHAR(32)     DEFAULT NULL,
    `location`         VARCHAR(190)    DEFAULT NULL,
    `activity`         VARCHAR(500)    DEFAULT NULL,
    `event_at`         TIMESTAMP       NULL DEFAULT NULL,
    `source`           ENUM('webhook','poll','manual') NOT NULL,
    `dedupe_key`       CHAR(64)        NOT NULL,          -- sha256(awb|code|event_at)
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sse_dedupe` (`dedupe_key`),
    KEY `idx_sse_shipment` (`shipment_id`, `event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable mapping so a new/renamed Shiprocket status needs no deploy. Seed rows once
-- the current status list is confirmed (VERIFY WITH PROVIDER).
CREATE TABLE IF NOT EXISTS `store_shiprocket_status_map` (
    `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `raw_code`         VARCHAR(20)     DEFAULT NULL,
    `raw_status`       VARCHAR(80)     NOT NULL,
    `internal_status`  VARCHAR(32)     NOT NULL,
    `status_rank`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- ordering for the monotonic guard
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sssm_raw` (`raw_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- status: requested | approved | rejected | pickup_scheduled | picked_up | received |
--         qc_passed | qc_failed | refund_initiated | refunded | closed
CREATE TABLE IF NOT EXISTS `store_returns` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `return_no`             VARCHAR(24)     NOT NULL,
    `order_id`              BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`       BIGINT UNSIGNED NOT NULL,
    `identity_id`           BIGINT UNSIGNED NOT NULL,
    `status`                VARCHAR(32)     NOT NULL DEFAULT 'requested',
    `reason_code`           VARCHAR(40)     NOT NULL,     -- damaged | wrong_item | not_as_described | expired | other
    `customer_note`         VARCHAR(1000)   DEFAULT NULL,
    `photos_json`           JSON            DEFAULT NULL,
    `vendor_decision`       ENUM('approved','rejected') DEFAULT NULL,
    `vendor_decided_at`     TIMESTAMP       NULL DEFAULT NULL,
    `vendor_note`           VARCHAR(500)    DEFAULT NULL,
    `admin_override_by`     BIGINT UNSIGNED DEFAULT NULL,
    `refund_amount_paise`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `return_shipment_id`    BIGINT UNSIGNED DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sret_no` (`return_no`),
    KEY `idx_sret_vendor_order` (`vendor_order_id`, `status`),
    KEY `idx_sret_identity` (`identity_id`, `created_at`),
    KEY `idx_sret_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_return_items` (
    `return_id`             BIGINT UNSIGNED NOT NULL,
    `order_item_id`         BIGINT UNSIGNED NOT NULL,
    `qty`                   SMALLINT UNSIGNED NOT NULL,
    `reason`                VARCHAR(255)    DEFAULT NULL,
    `condition_on_receipt`  ENUM('ok','damaged','used','missing') DEFAULT NULL,
    PRIMARY KEY (`return_id`, `order_item_id`),
    KEY `idx_sreti_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- APPEND-ONLY money ledger per vendor. Never UPDATE amounts; corrections are new
-- 'adjustment' rows. Balance = SUM(amount_paise) grouped by status.
CREATE TABLE IF NOT EXISTS `store_vendor_ledger` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`        BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`  BIGINT UNSIGNED DEFAULT NULL,
    `order_item_id`    BIGINT UNSIGNED DEFAULT NULL,
    `entry_type`       ENUM('sale_credit','commission_debit','commission_gst_debit','tcs_debit','tds_debit',
                            'shipping_debit','refund_reversal','rto_charge','penalty','adjustment','payout') NOT NULL,
    `amount_paise`     BIGINT          NOT NULL,          -- signed: credits +, debits −
    `status`           ENUM('pending','available','in_payout','paid','on_hold') NOT NULL DEFAULT 'pending',
    `available_at`     TIMESTAMP       NULL DEFAULT NULL,
    `payout_id`        BIGINT UNSIGNED DEFAULT NULL,
    `dedupe_key`       VARCHAR(120)    NOT NULL,          -- e.g. sale_credit:item:123 — makes writers idempotent
    `memo`             VARCHAR(255)    DEFAULT NULL,
    `created_by_type`  ENUM('system','admin') NOT NULL DEFAULT 'system',
    `created_by_id`    BIGINT UNSIGNED DEFAULT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_svl_dedupe` (`dedupe_key`),
    KEY `idx_svl_vendor` (`vendor_id`, `status`, `available_at`),
    KEY `idx_svl_payout` (`payout_id`),
    KEY `idx_svl_vendor_order` (`vendor_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_payouts` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payout_no`         VARCHAR(24)     NOT NULL,
    `vendor_id`         BIGINT UNSIGNED NOT NULL,
    `period_from`       DATE            NOT NULL,
    `period_to`         DATE            NOT NULL,
    `gross_paise`       BIGINT          NOT NULL DEFAULT 0,
    `deductions_paise`  BIGINT          NOT NULL DEFAULT 0,
    `net_paise`         BIGINT          NOT NULL DEFAULT 0,
    `status`            ENUM('draft','approved','processing','paid','failed','cancelled') NOT NULL DEFAULT 'draft',
    `method`            ENUM('manual_bank','route_transfer') NOT NULL DEFAULT 'manual_bank',
    `reference`         VARCHAR(80)     DEFAULT NULL,      -- UTR / transfer id
    `bank_last4`        CHAR(4)         DEFAULT NULL,      -- snapshot at payout time
    `bank_ifsc`         CHAR(11)        DEFAULT NULL,
    `approved_by`       BIGINT UNSIGNED DEFAULT NULL,
    `approved_at`       TIMESTAMP       NULL DEFAULT NULL,
    `paid_at`           TIMESTAMP       NULL DEFAULT NULL,
    `failure_reason`    VARCHAR(500)    DEFAULT NULL,
    `statement_path`    VARCHAR(255)    DEFAULT NULL,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spo_no` (`payout_no`),
    KEY `idx_spo_vendor` (`vendor_id`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verified purchases only: one review per order line.
CREATE TABLE IF NOT EXISTS `store_reviews` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`     BIGINT UNSIGNED NOT NULL,
    `vendor_id`      BIGINT UNSIGNED NOT NULL,
    `identity_id`    BIGINT UNSIGNED NOT NULL,
    `order_item_id`  BIGINT UNSIGNED NOT NULL,
    `rating`         TINYINT UNSIGNED NOT NULL,          -- 1..5 (service-enforced)
    `title`          VARCHAR(190)    DEFAULT NULL,
    `body`           TEXT            DEFAULT NULL,
    `status`         ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
    `vendor_reply`   TEXT            DEFAULT NULL,
    `replied_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_srev_item` (`order_item_id`),
    KEY `idx_srev_product` (`product_id`, `status`, `created_at`),
    KEY `idx_srev_vendor` (`vendor_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_banners` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `placement`   VARCHAR(40)     NOT NULL,             -- home_hero | home_mid | category_top
    `title`       VARCHAR(190)    DEFAULT NULL,
    `image_path`  VARCHAR(255)    NOT NULL,
    `link`        VARCHAR(255)    DEFAULT NULL,
    `starts_at`   TIMESTAMP       NULL DEFAULT NULL,
    `ends_at`     TIMESTAMP       NULL DEFAULT NULL,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_sban_placement` (`placement`, `is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shiprocket status name → our internal status. rank 0 = exception (applies without advancing progress).
-- VERIFY WITH PROVIDER: compare with Shiprocket's current status list; unknown statuses are logged, not applied.
INSERT INTO `store_shiprocket_status_map` (`raw_status`, `internal_status`, `status_rank`) VALUES
    ('NEW', 'sr_order_created', 10),
    ('AWB ASSIGNED', 'awb_assigned', 20),
    ('LABEL GENERATED', 'awb_assigned', 20),
    ('PICKUP SCHEDULED', 'pickup_scheduled', 30),
    ('PICKUP GENERATED', 'pickup_scheduled', 30),
    ('PICKUP QUEUED', 'pickup_scheduled', 30),
    ('OUT FOR PICKUP', 'pickup_scheduled', 30),
    ('PICKUP RESCHEDULED', 'pickup_scheduled', 30),
    ('PICKUP EXCEPTION', 'pickup_failed', 0),
    ('PICKUP ERROR', 'pickup_failed', 0),
    ('PICKED UP', 'picked_up', 40),
    ('SHIPPED', 'picked_up', 40),
    ('IN TRANSIT', 'in_transit', 50),
    ('REACHED AT DESTINATION HUB', 'in_transit', 50),
    ('REACHED DESTINATION HUB', 'in_transit', 50),
    ('MISROUTED', 'in_transit', 50),
    ('DELAYED', 'in_transit', 50),
    ('IN TRANSIT-AT DESTINATION HUB', 'in_transit', 50),
    ('OUT FOR DELIVERY', 'out_for_delivery', 60),
    ('UNDELIVERED', 'ndr', 0),
    ('DELIVERED', 'delivered', 100),
    ('RTO INITIATED', 'rto_initiated', 110),
    ('RTO IN TRANSIT', 'rto_initiated', 110),
    ('RTO ACKNOWLEDGED', 'rto_initiated', 110),
    ('RTO OFD', 'rto_initiated', 110),
    ('RTO NDR', 'rto_initiated', 110),
    ('RTO DELIVERED', 'rto_delivered', 120),
    ('CANCELED', 'cancelled', 130),
    ('CANCELLED', 'cancelled', 130),
    ('CANCELLATION REQUESTED', 'cancelled', 130),
    ('LOST', 'lost', 140),
    ('DAMAGED', 'damaged', 140),
    ('DESTROYED', 'damaged', 140)
ON DUPLICATE KEY UPDATE `internal_status` = VALUES(`internal_status`), `status_rank` = VALUES(`status_rank`);

-- Shiprocket credentials / cache (password stored ENCRYPTED with STORE_DATA_KEY).
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_shiprocket_token_expires', '', 0),
    ('store_shiprocket_enabled', '0', 0);
