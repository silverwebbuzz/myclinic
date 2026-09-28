-- /store marketplace — Batch 1 — Foundation (P1) · `2026_MM_DD_store_foundation.sql`
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
--   phpMyAdmin: select database → Import → this file.

-- Every admin / vendor write, incl. impersonation. The app's audit_log is
-- clinic-scoped (clinic_id/user_id, fixed action enum) so the store keeps its own.
CREATE TABLE IF NOT EXISTS `store_audit_log` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_type`       ENUM('admin','vendor_user','customer','system','webhook') NOT NULL,
    `actor_id`         BIGINT UNSIGNED DEFAULT NULL,
    `impersonator_id`  BIGINT UNSIGNED DEFAULT NULL,        -- admin id when acting as a vendor
    `action`           VARCHAR(60)     NOT NULL,            -- e.g. vendor.approve, product.update
    `entity_type`      VARCHAR(40)     NOT NULL,            -- vendor | product | order | payout ...
    `entity_id`        BIGINT UNSIGNED DEFAULT NULL,
    `before_json`      JSON            DEFAULT NULL,
    `after_json`       JSON            DEFAULT NULL,
    `ip`               VARCHAR(45)     DEFAULT NULL,
    `user_agent`       VARCHAR(255)    DEFAULT NULL,
    `created_at`       TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sal_entity` (`entity_type`, `entity_id`, `created_at`),
    KEY `idx_sal_actor` (`actor_type`, `actor_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw inbound webhooks (Razorpay, Shiprocket). UNIQUE(provider, event_id) makes
-- a duplicate delivery a no-op INSERT IGNORE.
CREATE TABLE IF NOT EXISTS `store_webhook_events` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `provider`      ENUM('razorpay','shiprocket') NOT NULL,
    `event_id`      VARCHAR(120)    NOT NULL,   -- x-razorpay-event-id / awb+status+time for Shiprocket
    `event_type`    VARCHAR(80)     NOT NULL,
    `signature_ok`  TINYINT(1)      NOT NULL DEFAULT 0,
    `payload`       JSON            NOT NULL,
    `status`        ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
    `attempts`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `error`         TEXT            DEFAULT NULL,
    `received_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at`  TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_swe_event` (`provider`, `event_id`),
    KEY `idx_swe_status` (`status`, `received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- In-app bell for customers, vendor users and admins. Email/SMS use existing channels.
CREATE TABLE IF NOT EXISTS `store_notifications` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_type`  ENUM('customer','vendor_user','admin') NOT NULL,
    `recipient_id`    BIGINT UNSIGNED NOT NULL,
    `event`           VARCHAR(60)     NOT NULL,   -- order.placed, shipment.delivered, payout.paid ...
    `title`           VARCHAR(190)    NOT NULL,
    `body`            VARCHAR(500)    DEFAULT NULL,
    `link`            VARCHAR(255)    DEFAULT NULL,
    `read_at`         TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sn_recipient` (`recipient_type`, `recipient_id`, `read_at`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store settings live in the existing platform_settings table (editable in admin).
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_enabled',                    '0',  0),  -- master switch; storefront 404s / admin hidden while 0
    ('store_require_product_approval',   '1',  0),  -- D6
    ('store_payment_window_minutes',     '30', 0),  -- pending_payment orders expire after this
    ('store_vendor_accept_sla_hours',    '48', 0),  -- unaccepted sub-orders auto-cancel after this
    ('store_default_return_window_days', '7',  0),
    ('store_default_commission_bp',      '1000', 0),-- 10% fallback when no commission rule matches
    ('store_shiprocket_email',           '',   0),
    ('store_shiprocket_password',        '',   1),
    ('store_shiprocket_token',           '',   1),  -- cached auth token (refreshed automatically)
    ('store_shiprocket_webhook_key',     '',   1),  -- x-api-key we give Shiprocket (VERIFY)
    ('store_preview_key',                '',   1);  -- lets admins preview /store while store_enabled = 0
