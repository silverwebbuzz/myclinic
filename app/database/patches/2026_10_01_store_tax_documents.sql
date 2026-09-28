-- /store marketplace — Batch 6 — GST documents + seller policies (P11b) · `2026_10_01_store_tax_documents.sql`
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
--   phpMyAdmin: select database → Import → this file.

-- Tax invoices and credit notes. Rows are NEVER edited or deleted after issue:
-- a correction is a credit note that points at the invoice (refers_to_id).
--   issuer = vendor   → the seller's goods invoice (seller of record, in their name/GSTIN)
--   issuer = platform → eClinicPro's own invoice for delivery charges
CREATE TABLE IF NOT EXISTS `store_tax_documents` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doc_type`            ENUM('invoice','credit_note') NOT NULL,
    `issuer`              ENUM('vendor','platform') NOT NULL,
    `vendor_id`           BIGINT UNSIGNED DEFAULT NULL,      -- the seller the package belongs to (also on platform docs)
    `doc_no`              VARCHAR(16)     NOT NULL,          -- GST rule 46: max 16 chars, unique per FY
    `fy`                  CHAR(4)         NOT NULL,          -- 2627 = Apr 2026 – Mar 2027
    `seq`                 INT UNSIGNED    NOT NULL,
    `order_id`            BIGINT UNSIGNED NOT NULL,
    `vendor_order_id`     BIGINT UNSIGNED NOT NULL,
    `refers_to_id`        BIGINT UNSIGNED DEFAULT NULL,      -- credit note → its invoice
    `refund_id`           BIGINT UNSIGNED DEFAULT NULL,      -- credit note → the refund that caused it
    `reason`              VARCHAR(40)     DEFAULT NULL,      -- return | cancel | rto | lost | damaged | missing
    `is_bill_of_supply`   TINYINT(1)      NOT NULL DEFAULT 0, -- seller without GSTIN: no tax charged
    `supply_type`         ENUM('intra','inter') NOT NULL,     -- intra = CGST+SGST, inter = IGST
    `place_of_supply`     CHAR(2)         DEFAULT NULL,      -- GST state code of the delivery address
    `issuer_json`         JSON            NOT NULL,          -- legal name, GSTIN, address, state code (snapshot)
    `buyer_json`          JSON            NOT NULL,          -- name, delivery address, state (snapshot)
    `taxable_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `cgst_paise`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sgst_paise`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `igst_paise`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_paise`         BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- positive on credit notes too (the doc type says it's a credit)
    `dedupe_key`          VARCHAR(80)     NOT NULL,
    `issued_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_std_no` (`doc_no`),                   -- numbers embed issuer + FY, so globally unique
    UNIQUE KEY `uq_std_dedupe` (`dedupe_key`),
    KEY `idx_std_vendor` (`vendor_id`, `issued_at`),
    KEY `idx_std_issued` (`issued_at`),
    KEY `idx_std_vo` (`vendor_order_id`),
    KEY `idx_std_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_tax_document_lines` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_id`     BIGINT UNSIGNED NOT NULL,
    `order_item_id`   BIGINT UNSIGNED DEFAULT NULL,        -- NULL on the delivery-charge line
    `kind`            ENUM('goods','delivery') NOT NULL DEFAULT 'goods',
    `description`     VARCHAR(300)    NOT NULL,
    `hsn_sac`         VARCHAR(8)      DEFAULT NULL,
    `qty`             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `unit_from`       SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- invoice: first unit covered (units before it were cancelled pre-invoice)
    `gst_bp`          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `taxable_paise`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `cgst_paise`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sgst_paise`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `igst_paise`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_stdl_doc` (`document_id`),
    KEY `idx_stdl_item` (`order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gap-free numbering per issuer + document type + financial year (row locked while numbering).
CREATE TABLE IF NOT EXISTS `store_tax_doc_sequences` (
    `issuer_key`  VARCHAR(24)  NOT NULL,     -- 'v:12' (seller 12) or 'p' (eClinicPro)
    `doc_type`    ENUM('invoice','credit_note') NOT NULL,
    `fy`          CHAR(4)      NOT NULL,
    `last_seq`    INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`issuer_key`, `doc_type`, `fy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin-editable policy pages (seller rules & terms now; more slugs later). Each save bumps version.
CREATE TABLE IF NOT EXISTS `store_policy_pages` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(60)     NOT NULL,
    `title`       VARCHAR(190)    NOT NULL,
    `body`        MEDIUMTEXT      NOT NULL,          -- simple markdown (## headings, - bullets, **bold**)
    `version`     INT UNSIGNED    NOT NULL DEFAULT 1,
    `updated_by`  BIGINT UNSIGNED DEFAULT NULL,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spp_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every version of every policy, so we can show what a seller actually accepted.
CREATE TABLE IF NOT EXISTS `store_policy_versions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(60)     NOT NULL,
    `version`     INT UNSIGNED    NOT NULL,
    `title`       VARCHAR(190)    NOT NULL,
    `body`        MEDIUMTEXT      NOT NULL,
    `created_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spv` (`slug`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_policy_acceptances` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`       BIGINT UNSIGNED NOT NULL,
    `vendor_user_id`  BIGINT UNSIGNED NOT NULL,
    `slug`            VARCHAR(60)     NOT NULL,
    `version`         INT UNSIGNED    NOT NULL,
    `ip`              VARBINARY(16)   DEFAULT NULL,
    `accepted_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spa` (`vendor_id`, `slug`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoicing defaults (fill the rest in /admin/store/settings → Invoicing).
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_delivery_sac', '996812', 0),     -- SAC for delivery charges (VERIFY WITH CA)
    ('store_delivery_gst_bp', '1800', 0),    -- 18% GST inside the delivery charge
    ('store_require_gstin', '1', 0);         -- sellers need a GSTIN to be approved
