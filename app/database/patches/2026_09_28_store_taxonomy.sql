-- /store marketplace — Batch 3a — Taxonomy (P3, can run now) · `2026_09_28_store_taxonomy.sql`
-- Source of truth: document/store-marketplace-plan.md, Appendix A (keep in sync).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS / INSERT IGNORE).
--   phpMyAdmin: select database → Import → this file.

-- Two levels: departments (parent_id = 0) -> subcategories. Seeded by Appendix B.
CREATE TABLE IF NOT EXISTS `store_categories` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`         BIGINT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = department
    `slug`              VARCHAR(120)    NOT NULL,
    `name`              VARCHAR(160)    NOT NULL,
    `group_key`         VARCHAR(40)     DEFAULT NULL,         -- 'pregnancy' | 'baby' inside Mother & Baby
    `description`       TEXT            DEFAULT NULL,
    `compliance_note`   VARCHAR(255)    DEFAULT NULL,         -- from sheet B (departments)
    `regulatory_class`  ENUM('general','cosmetic','food_fssai','nutraceutical_fssai','ayush',
                             'medical_device','drug_restricted') NOT NULL DEFAULT 'general',
    `required_vendor_doc` VARCHAR(40)   DEFAULT NULL,         -- store_vendor_documents.doc_type needed to list here
    `listing_mode`      ENUM('open','review','blocked') NOT NULL DEFAULT 'open',
    `review_reason`     VARCHAR(255)    DEFAULT NULL,
    `no_promotion`      TINYINT(1)      NOT NULL DEFAULT 0,   -- IMS Act: never discounted/promoted
    `image_path`        VARCHAR(255)    DEFAULT NULL,
    `icon`              VARCHAR(60)     DEFAULT NULL,
    `seo_title`         VARCHAR(190)    DEFAULT NULL,
    `seo_description`   VARCHAR(300)    DEFAULT NULL,
    `default_hsn`       VARCHAR(8)      DEFAULT NULL,
    `default_gst_bp`    SMALLINT UNSIGNED DEFAULT NULL,       -- 1800 = 18%
    `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sc_parent_slug` (`parent_id`, `slug`),     -- same sub name may repeat across departments
    KEY `idx_sc_tree` (`parent_id`, `is_active`, `sort_order`),
    KEY `idx_sc_class` (`regulatory_class`, `listing_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Shop by need" menu + homepage tiles. url set => links out (Preventive Health -> /lab).
CREATE TABLE IF NOT EXISTS `store_nav_items` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `section`              ENUM('need') NOT NULL DEFAULT 'need',
    `label`                VARCHAR(120)    NOT NULL,
    `slug`                 VARCHAR(120)    NOT NULL,
    `url`                  VARCHAR(255)    DEFAULT NULL,
    `icon`                 VARCHAR(60)     DEFAULT NULL,
    `image_path`           VARCHAR(255)    DEFAULT NULL,
    `homepage_tile_order`  TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = not a homepage tile
    `sort_order`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`            TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sni_slug` (`slug`),
    KEY `idx_sni_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A menu item shows products from these categories (department or subcategory).
CREATE TABLE IF NOT EXISTS `store_nav_item_targets` (
    `nav_item_id`  BIGINT UNSIGNED NOT NULL,
    `category_id`  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`nav_item_id`, `category_id`),
    KEY `idx_snit_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Shop by health goal".
CREATE TABLE IF NOT EXISTS `store_concerns` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(120)    NOT NULL,
    `name`        VARCHAR(120)    NOT NULL,
    `description` TEXT            DEFAULT NULL,
    `icon`        VARCHAR(60)     DEFAULT NULL,
    `image_path`  VARCHAR(255)    DEFAULT NULL,
    `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sco_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_concern_categories` (
    `concern_id`   BIGINT UNSIGNED NOT NULL,
    `category_id`  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`concern_id`, `category_id`),
    KEY `idx_scc_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
