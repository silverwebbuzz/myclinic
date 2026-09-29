-- =====================================================================
-- eClinicPro Store — eClinicPro's monthly GST invoice to each seller (2026-10-05)
--
-- As the marketplace, eClinicPro supplies services TO the seller: commission,
-- courier/logistics recovered from the seller, and other charges under the
-- seller rules. Once a month each seller gets ONE tax invoice for the month's
-- charges (and a credit note if reversals exceed charges), so they can claim
-- the GST as input credit. Built from store_vendor_ledger (entries dated in the month).
--
-- Numbers: ECP-S{fy}-{n} (invoice) / ECP-SC{fy}-{n} (credit note), gap-free per FY,
-- sharing store_tax_doc_sequences (issuer_key 'ps').
-- Safe to run more than once.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `store_seller_invoices` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `doc_type`         ENUM('invoice','credit_note') NOT NULL,
    `doc_no`           VARCHAR(16)     NOT NULL,
    `fy`               CHAR(4)         NOT NULL,
    `seq`              INT UNSIGNED    NOT NULL,
    `vendor_id`        BIGINT UNSIGNED NOT NULL,
    `period`           CHAR(7)         NOT NULL,          -- YYYY-MM the charges belong to
    `refers_to_id`     BIGINT UNSIGNED DEFAULT NULL,      -- credit note → the seller's latest earlier invoice
    `supply_type`      ENUM('intra','inter') NOT NULL,    -- eClinicPro state vs seller's state
    `place_of_supply`  CHAR(2)         DEFAULT NULL,      -- seller's GST state code (B2B)
    `issuer_json`      JSON            NOT NULL,          -- eClinicPro legal name, GSTIN, address (snapshot)
    `buyer_json`       JSON            NOT NULL,          -- the seller (snapshot)
    `taxable_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `cgst_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sgst_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `igst_paise`       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_paise`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `dedupe_key`       VARCHAR(60)     NOT NULL,          -- sinv:v12:2026-10 / scn:v12:2026-10
    `issued_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ssi_no` (`doc_no`),
    UNIQUE KEY `uq_ssi_dedupe` (`dedupe_key`),
    KEY `idx_ssi_vendor` (`vendor_id`, `period`),
    KEY `idx_ssi_period` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_seller_invoice_lines` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_id`     BIGINT UNSIGNED NOT NULL,
    `kind`           ENUM('commission','courier','other') NOT NULL,
    `description`    VARCHAR(300)    NOT NULL,
    `sac`            VARCHAR(8)      DEFAULT NULL,
    `gst_bp`         SMALLINT UNSIGNED NOT NULL DEFAULT 1800,
    `taxable_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `cgst_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sgst_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `igst_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `total_paise`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_ssil_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_commission_sac', '998599', 0),   -- SAC for marketplace commission (VERIFY WITH CA)
    ('store_other_charges_sac', '998599', 0);  -- SAC for other seller charges (VERIFY WITH CA)
