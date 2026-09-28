-- /store marketplace — Batch 2 — Vendors (P2) · `2026_MM_DD_store_vendors.sql`
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
--   phpMyAdmin: select database → Import → this file.

CREATE TABLE IF NOT EXISTS `store_vendors` (
    `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`                        VARCHAR(120)    NOT NULL,          -- /store/seller/{slug}
    `display_name`                VARCHAR(160)    NOT NULL,
    `legal_name`                  VARCHAR(190)    DEFAULT NULL,
    `business_type`               ENUM('proprietorship','partnership','llp','pvt_ltd','public_ltd','other') DEFAULT NULL,
    `gstin`                       VARCHAR(15)     DEFAULT NULL,
    `pan_enc`                     VARBINARY(255)  DEFAULT NULL,      -- libsodium secretbox (STORE_DATA_KEY)
    `pan_last4`                   CHAR(4)         DEFAULT NULL,
    `contact_name`                VARCHAR(160)    NOT NULL,
    `email`                       VARCHAR(190)    NOT NULL,
    `phone`                       VARCHAR(20)     NOT NULL,          -- E.164
    `logo_path`                   VARCHAR(255)    DEFAULT NULL,
    `banner_path`                 VARCHAR(255)    DEFAULT NULL,
    `description`                 TEXT            DEFAULT NULL,
    `status`                      ENUM('draft','pending_review','approved','rejected','suspended','closed') NOT NULL DEFAULT 'draft',
    `status_reason`               VARCHAR(500)    DEFAULT NULL,
    `handling_days`               TINYINT UNSIGNED NOT NULL DEFAULT 2,   -- days to dispatch, drives delivery estimate
    `default_return_window_days`  TINYINT UNSIGNED NOT NULL DEFAULT 7,
    `is_featured`                 TINYINT(1)      NOT NULL DEFAULT 0,
    `rating_avg`                  DECIMAL(3,2)    NOT NULL DEFAULT 0.00,
    `rating_count`                INT UNSIGNED    NOT NULL DEFAULT 0,
    `rzp_linked_account_id`       VARCHAR(40)     DEFAULT NULL,      -- Razorpay Route (D1), else NULL
    `submitted_at`                TIMESTAMP       NULL DEFAULT NULL, -- vendor clicked "Submit for review"
    `approved_by`                 BIGINT UNSIGNED DEFAULT NULL,
    `approved_at`                 TIMESTAMP       NULL DEFAULT NULL,
    `created_at`                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sv_slug` (`slug`),
    UNIQUE KEY `uq_sv_gstin` (`gstin`),                              -- NULLs allowed (multiple)
    KEY `idx_sv_status` (`status`, `is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logins. Separate from the vendor so one business can have staff users.
CREATE TABLE IF NOT EXISTS `store_vendor_users` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`      BIGINT UNSIGNED NOT NULL,
    `name`           VARCHAR(160)    NOT NULL,
    `email`          VARCHAR(190)    NOT NULL,
    `password_hash`  VARCHAR(255)    NOT NULL,          -- password_hash() argon2id/bcrypt
    `role`           ENUM('owner','staff') NOT NULL DEFAULT 'owner',
    `status`         ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `email_verified_at` TIMESTAMP    NULL DEFAULT NULL,
    `failed_logins`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`   TIMESTAMP       NULL DEFAULT NULL,
    `last_login_at`  TIMESTAMP       NULL DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_svu_email` (`email`),
    KEY `idx_svu_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Never edited in place: a change inserts a new row and deactivates the old one,
-- so shipments keep pointing at the address they were actually picked up from.
CREATE TABLE IF NOT EXISTS `store_vendor_addresses` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`         BIGINT UNSIGNED NOT NULL,
    `type`              ENUM('pickup','return','registered') NOT NULL,
    `contact_name`      VARCHAR(160)    NOT NULL,
    `phone`             VARCHAR(20)     NOT NULL,
    `email`             VARCHAR(190)    DEFAULT NULL,
    `line1`             VARCHAR(255)    NOT NULL,
    `line2`             VARCHAR(255)    DEFAULT NULL,
    `city`              VARCHAR(120)    NOT NULL,
    `state`             VARCHAR(120)    NOT NULL,
    `state_code`        CHAR(2)         DEFAULT NULL,      -- GST state code
    `pincode`           CHAR(6)         NOT NULL,
    `sr_pickup_name`    VARCHAR(60)     DEFAULT NULL,      -- Shiprocket pickup_location nickname
    `sr_pickup_status`  ENUM('none','pending','verified','failed') NOT NULL DEFAULT 'none',
    `is_default`        TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sva_srname` (`sr_pickup_name`),
    KEY `idx_sva_vendor` (`vendor_id`, `type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_vendor_bank_accounts` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`       BIGINT UNSIGNED NOT NULL,
    `holder_name`     VARCHAR(160)    NOT NULL,
    `account_no_enc`  VARBINARY(255)  NOT NULL,          -- encrypted; never selected into list views
    `account_last4`   CHAR(4)         NOT NULL,
    `ifsc`            CHAR(11)        NOT NULL,
    `bank_name`       VARCHAR(120)    DEFAULT NULL,
    `upi_id`          VARCHAR(100)    DEFAULT NULL,
    `is_primary`      TINYINT(1)      NOT NULL DEFAULT 1,
    `status`          ENUM('pending','verified','rejected','inactive') NOT NULL DEFAULT 'pending',
    `verified_at`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_svb_vendor` (`vendor_id`, `is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Files live in PRIVATE storage (outside the web root), served by an auth-checked endpoint.
CREATE TABLE IF NOT EXISTS `store_vendor_documents` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`      BIGINT UNSIGNED NOT NULL,
    `doc_type`       ENUM('gst_cert','pan','cancelled_cheque','fssai_license','ayush_license',
                          'medical_device_registration','drug_license','trade_license','other') NOT NULL,
    `doc_number`     VARCHAR(80)     DEFAULT NULL,       -- licence no. (shown on product pages where the law requires)
    `file_path`      VARCHAR(255)    NOT NULL,           -- relative to repo-root /storage/store_vendor_docs
    `original_name`  VARCHAR(255)    DEFAULT NULL,
    `status`         ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
    `valid_until`    DATE            DEFAULT NULL,       -- expiry alerts + auto-hide listings in that class
    `reviewed_by`    BIGINT UNSIGNED DEFAULT NULL,
    `reviewed_at`    TIMESTAMP       NULL DEFAULT NULL,
    `reject_reason`  VARCHAR(500)    DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_svd_vendor` (`vendor_id`, `doc_type`, `status`),
    KEY `idx_svd_expiry` (`status`, `valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
