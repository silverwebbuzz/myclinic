-- /store marketplace — Batch 3b — Catalog (P3) · `2026_MM_DD_store_catalog.sql`
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
--   phpMyAdmin: select database → Import → this file.

CREATE TABLE IF NOT EXISTS `store_brands` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`         VARCHAR(120)    NOT NULL,
    `name`         VARCHAR(160)    NOT NULL,
    `logo_path`    VARCHAR(255)    DEFAULT NULL,
    `is_featured`  TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sb_slug` (`slug`),
    KEY `idx_sb_featured` (`is_active`, `is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_collections` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`         VARCHAR(120)    NOT NULL,
    `name`         VARCHAR(160)    NOT NULL,
    `description`  TEXT            DEFAULT NULL,
    `image_path`   VARCHAR(255)    DEFAULT NULL,
    `sort_order`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scol_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_collection_products` (
    `collection_id`  BIGINT UNSIGNED NOT NULL,
    `product_id`     BIGINT UNSIGNED NOT NULL,
    `sort_order`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`collection_id`, `product_id`),
    KEY `idx_scp_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_attributes` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`             VARCHAR(60)     NOT NULL,           -- size, flavour, pack_size, colour
    `name`             VARCHAR(120)    NOT NULL,
    `type`             ENUM('select','text','number') NOT NULL DEFAULT 'select',
    `is_filterable`    TINYINT(1)      NOT NULL DEFAULT 1,
    `is_variant_axis`  TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sa_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_attribute_values` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attribute_id`  BIGINT UNSIGNED NOT NULL,
    `value`         VARCHAR(120)    NOT NULL,
    `slug`          VARCHAR(120)    NOT NULL,
    `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sav_attr_slug` (`attribute_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_category_attributes` (
    `category_id`   BIGINT UNSIGNED NOT NULL,
    `attribute_id`  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`category_id`, `attribute_id`),
    KEY `idx_sca_attribute` (`attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_products` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id`           BIGINT UNSIGNED NOT NULL,
    `category_id`         BIGINT UNSIGNED NOT NULL,          -- PRIMARY subcategory (mirrors store_product_categories)
    `brand_id`            BIGINT UNSIGNED DEFAULT NULL,
    `slug`                VARCHAR(190)    NOT NULL,          -- /store/p/{slug}
    `name`                VARCHAR(255)    NOT NULL,
    `short_desc`          VARCHAR(500)    DEFAULT NULL,
    `description`         MEDIUMTEXT      DEFAULT NULL,      -- sanitised HTML
    `specs_json`          JSON            DEFAULT NULL,      -- [{label, value}]
    `status`              ENUM('draft','pending_review','live','rejected','disabled','archived') NOT NULL DEFAULT 'draft',
    `review_note`         VARCHAR(500)    DEFAULT NULL,
    `regulatory_class`    ENUM('general','cosmetic','food_fssai','nutraceutical_fssai','ayush',
                               'medical_device','drug_restricted') NOT NULL DEFAULT 'general', -- final class set at approval
    `license_number`      VARCHAR(80)     DEFAULT NULL,      -- FSSAI / device reg no. shown on PDP where required
    `hsn_code`            VARCHAR(8)      DEFAULT NULL,
    `gst_bp`              SMALLINT UNSIGNED NOT NULL DEFAULT 1800,
    `is_returnable`       TINYINT(1)      NOT NULL DEFAULT 1,
    `return_window_days`  TINYINT UNSIGNED DEFAULT NULL,     -- NULL = vendor default
    `has_expiry`          TINYINT(1)      NOT NULL DEFAULT 0,
    `manufacturer`        VARCHAR(190)    DEFAULT NULL,
    `country_of_origin`   VARCHAR(60)     DEFAULT 'India',
    `is_featured`         TINYINT(1)      NOT NULL DEFAULT 0,
    `min_price_paise`     BIGINT UNSIGNED NOT NULL DEFAULT 0,  -- denormalised from variants for listing sort
    `in_stock`            TINYINT(1)      NOT NULL DEFAULT 0,  -- denormalised
    `rating_avg`          DECIMAL(3,2)    NOT NULL DEFAULT 0.00,
    `rating_count`        INT UNSIGNED    NOT NULL DEFAULT 0,
    `sold_count`          INT UNSIGNED    NOT NULL DEFAULT 0,
    `seo_title`           VARCHAR(190)    DEFAULT NULL,
    `seo_description`     VARCHAR(300)    DEFAULT NULL,
    `published_at`        TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`          TIMESTAMP       NULL DEFAULT NULL,
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sp_slug` (`slug`),
    KEY `idx_sp_listing` (`status`, `category_id`, `in_stock`, `min_price_paise`),  -- category page + price sort
    KEY `idx_sp_popular` (`status`, `category_id`, `sold_count`),                   -- "popular" sort
    KEY `idx_sp_vendor` (`vendor_id`, `status`),
    KEY `idx_sp_brand` (`brand_id`, `status`),
    KEY `idx_sp_featured` (`status`, `is_featured`),
    FULLTEXT KEY `ft_sp_search` (`name`, `short_desc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primary + secondary placement (a probiotic can sit under Gut Health AND Senior Wellness).
CREATE TABLE IF NOT EXISTS `store_product_categories` (
    `product_id`   BIGINT UNSIGNED NOT NULL,
    `category_id`  BIGINT UNSIGNED NOT NULL,
    `is_primary`   TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`product_id`, `category_id`),
    KEY `idx_spc_category` (`category_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_product_concerns` (
    `product_id`  BIGINT UNSIGNED NOT NULL,
    `concern_id`  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`, `concern_id`),
    KEY `idx_spco_concern` (`concern_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every product has >= 1 variant; cart, stock and orders only ever reference variants.
CREATE TABLE IF NOT EXISTS `store_product_variants` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`           BIGINT UNSIGNED NOT NULL,
    `vendor_id`            BIGINT UNSIGNED NOT NULL,          -- denormalised for SKU uniqueness + vendor queries
    `sku`                  VARCHAR(80)     NOT NULL,
    `title`                VARCHAR(190)    DEFAULT NULL,      -- "60 tablets", "Size L"; NULL for single-variant
    `mrp_paise`            BIGINT UNSIGNED NOT NULL,
    `price_paise`          BIGINT UNSIGNED NOT NULL,          -- selling price, GST-inclusive; must be <= mrp (service-enforced)
    `sale_price_paise`     BIGINT UNSIGNED DEFAULT NULL,
    `sale_starts_at`       TIMESTAMP       NULL DEFAULT NULL,
    `sale_ends_at`         TIMESTAMP       NULL DEFAULT NULL,
    `stock_qty`            INT UNSIGNED    NOT NULL DEFAULT 0,
    `reserved_qty`         INT UNSIGNED    NOT NULL DEFAULT 0, -- held by pending_payment orders
    `low_stock_threshold`  INT UNSIGNED    NOT NULL DEFAULT 5,
    `weight_g`             INT UNSIGNED    NOT NULL DEFAULT 0, -- packed weight, drives Shiprocket
    `length_mm`            INT UNSIGNED    NOT NULL DEFAULT 0,
    `breadth_mm`           INT UNSIGNED    NOT NULL DEFAULT 0,
    `height_mm`            INT UNSIGNED    NOT NULL DEFAULT 0,
    `barcode`              VARCHAR(40)     DEFAULT NULL,
    `is_active`            TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_spv_vendor_sku` (`vendor_id`, `sku`),
    KEY `idx_spv_product` (`product_id`, `is_active`),
    KEY `idx_spv_lowstock` (`vendor_id`, `stock_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_variant_options` (
    `variant_id`          BIGINT UNSIGNED NOT NULL,
    `attribute_value_id`  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`variant_id`, `attribute_value_id`),
    KEY `idx_svo_value` (`attribute_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_product_images` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`  BIGINT UNSIGNED NOT NULL,
    `variant_id`  BIGINT UNSIGNED DEFAULT NULL,
    `path`        VARCHAR(255)    NOT NULL,              -- base path; thumb/card/zoom sizes derived
    `alt`         VARCHAR(190)    DEFAULT NULL,
    `width`       SMALLINT UNSIGNED DEFAULT NULL,
    `height`      SMALLINT UNSIGNED DEFAULT NULL,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_spi_product` (`product_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only stock ledger: explains every change to stock_qty / reserved_qty.
CREATE TABLE IF NOT EXISTS `store_inventory_movements` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `variant_id`  BIGINT UNSIGNED NOT NULL,
    `delta`       INT             NOT NULL,
    `reason`      ENUM('reserve','release','commit','restock_return','manual') NOT NULL,
    `ref_type`    VARCHAR(30)     DEFAULT NULL,          -- order | return | csv_upload
    `ref_id`      BIGINT UNSIGNED DEFAULT NULL,
    `actor_type`  ENUM('system','vendor_user','admin') NOT NULL DEFAULT 'system',
    `actor_id`    BIGINT UNSIGNED DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sim_variant` (`variant_id`, `created_at`),
    KEY `idx_sim_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resolution: product > vendor_category > vendor > category > default. The winning
-- rule is COPIED onto store_order_items at checkout and never recomputed.
CREATE TABLE IF NOT EXISTS `store_commission_rules` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scope`        ENUM('default','category','vendor','vendor_category','product') NOT NULL,
    `vendor_id`    BIGINT UNSIGNED DEFAULT NULL,
    `category_id`  BIGINT UNSIGNED DEFAULT NULL,
    `product_id`   BIGINT UNSIGNED DEFAULT NULL,
    `type`         ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    `rate_bp`      INT UNSIGNED    NOT NULL DEFAULT 0,       -- percent rules
    `fixed_paise`  BIGINT UNSIGNED NOT NULL DEFAULT 0,       -- fixed rules (per unit)
    `valid_from`   DATE            DEFAULT NULL,
    `valid_to`     DATE            DEFAULT NULL,
    `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`   BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_scr_lookup` (`scope`, `is_active`, `vendor_id`, `category_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `store_commission_rules` (`scope`, `type`, `rate_bp`)
SELECT 'default', 'percent', 1000 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `store_commission_rules` WHERE `scope` = 'default');

-- Customer wishlist (storefront, P4). identity_id = patient_identities.id.
CREATE TABLE IF NOT EXISTS `store_wishlist` (
    `identity_id`  BIGINT UNSIGNED NOT NULL,
    `product_id`   BIGINT UNSIGNED NOT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`identity_id`, `product_id`),
    KEY `idx_swl_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
