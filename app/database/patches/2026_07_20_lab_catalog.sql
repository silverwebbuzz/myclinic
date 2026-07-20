-- ============================================================================
-- Lab test catalog (Thyrocare-sourced) — normalized, reusable schema.
--
-- Source data:
--   * MAster-data.json          — Thyrocare master (tests / profiles / offers)
--   * thyrocare-tests.json      — panel scrape (MRP + incentive)
--   * thyrocare_discount_analysis.xlsx — incentive % + max discount you can offer
--
-- Pricing model:
--   Public display  = Thyrocare MRP (struck) + offer_rate (shown).  Same as
--                     thyrocare.com for parity.
--   Logged-in bonus = coupon (5/10/15/20/25 %) applied ON TOP of offer_rate,
--                     capped per-product by max_discount_pct.
--
-- The "Test Code" (e.g. PROJ1035272) is stored in lab_products.thyrocare_code.
--
-- Design notes:
--   * lab_parameters is a dictionary of the 662 analyte codes. Package
--     compositions reference it — NOT lab_products — because 291 sub-tests
--     have no standalone sellable entry.
--   * Prices live in lab_product_pricing (history-friendly, re-importable)
--     so the catalog itself never churns when Thyrocare changes rates.
--
-- Idempotent (CREATE TABLE IF NOT EXISTS). Seed data ships separately in
--   2026_07_20_lab_catalog_seed.sql (generated from the sources).
--
-- Deploy:
--   mysql -u USER -p DB < app/database/patches/2026_07_20_lab_catalog.sql
--   mysql -u USER -p DB < app/database/patches/2026_07_20_lab_catalog_seed.sql
-- ============================================================================

SET NAMES utf8mb4;

-- 1) Categories (organ / disease groups — from groupName) ---------------------
CREATE TABLE IF NOT EXISTS `lab_categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(120) NOT NULL,
  `slug`        VARCHAR(140) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`  INT          NOT NULL DEFAULT 100,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_category_slug` (`slug`),
  KEY `idx_lab_category_active` (`is_active`, `sort_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Parameters (the 662 analyte dictionary — reusable atoms) -----------------
CREATE TABLE IF NOT EXISTS `lab_parameters` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`           VARCHAR(40)  NOT NULL,          -- Thyrocare child code (FBS, APOA…)
  `name`           VARCHAR(255) NOT NULL,
  `group_name`     VARCHAR(120) DEFAULT NULL,      -- CARDIAC RISK MARKERS…
  `units`          VARCHAR(60)  DEFAULT NULL,
  `normal_value`   VARCHAR(255) DEFAULT NULL,
  `specimen_type`  VARCHAR(40)  DEFAULT NULL,      -- SERUM / EDTA / FLUORIDE…
  `fasting`        ENUM('CF','NF') DEFAULT NULL,   -- CF = compulsory fasting, NF = non-fasting
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_parameter_code` (`code`),
  KEY `idx_lab_parameter_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Products (sellable items: TEST | PROFILE | OFFER) ------------------------
CREATE TABLE IF NOT EXISTS `lab_products` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_type`   ENUM('TEST','PROFILE','OFFER') NOT NULL,
  `code`           VARCHAR(40)  NOT NULL,          -- Thyrocare master code (P1522, FBS, PROJ…)
  `thyrocare_code` VARCHAR(40)  DEFAULT NULL,      -- external "Test Code" (PROJ1035272) — unique when set
  `name`           VARCHAR(255) NOT NULL,
  `slug`           VARCHAR(280) NOT NULL,
  `test_count`     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `specimen_type`  VARCHAR(40)  DEFAULT NULL,
  `fasting`        ENUM('CF','NF') DEFAULT NULL,
  `disease_group`  VARCHAR(255) DEFAULT NULL,      -- raw groupName (THYROID,INFERTILITY)
  `description`    TEXT         DEFAULT NULL,
  `banner_image`   VARCHAR(500) DEFAULT NULL,      -- imageLocation (primary banner)
  `booked_count`   INT UNSIGNED DEFAULT 0,         -- popularity signal
  `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
  `is_featured`    TINYINT(1)   NOT NULL DEFAULT 0,
  `sort_order`     INT          NOT NULL DEFAULT 100,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_product_type_code` (`product_type`, `code`),
  UNIQUE KEY `uq_lab_product_slug` (`slug`),
  UNIQUE KEY `uq_lab_product_thyrocare_code` (`thyrocare_code`),
  KEY `idx_lab_product_active` (`is_active`, `product_type`, `sort_order`),
  KEY `idx_lab_product_featured` (`is_featured`, `is_active`),
  FULLTEXT KEY `ft_lab_product_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Product -> Parameter composition (M:N; from childs[]) --------------------
CREATE TABLE IF NOT EXISTS `lab_product_parameters` (
  `product_id`   INT UNSIGNED NOT NULL,
  `parameter_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `parameter_id`),
  KEY `idx_lpp_parameter` (`parameter_id`),
  CONSTRAINT `fk_lpp_product`   FOREIGN KEY (`product_id`)   REFERENCES `lab_products`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lpp_parameter` FOREIGN KEY (`parameter_id`) REFERENCES `lab_parameters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Product -> Category (M:N) ------------------------------------------------
CREATE TABLE IF NOT EXISTS `lab_product_categories` (
  `product_id`  INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `category_id`),
  KEY `idx_lpc_category` (`category_id`),
  CONSTRAINT `fk_lpc_product`  FOREIGN KEY (`product_id`)  REFERENCES `lab_products`   (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lpc_category` FOREIGN KEY (`category_id`) REFERENCES `lab_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) Pricing (1:N history — current row is the one with the latest effective_from) --
CREATE TABLE IF NOT EXISTS `lab_product_pricing` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`            INT UNSIGNED NOT NULL,
  `mrp`                   DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- b2C — struck-through
  `offer_rate`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- offerRate — displayed price (= thyrocare.com)
  `incentive_amt`         DECIMAL(10,2) DEFAULT NULL,            -- ₹ Thyrocare pays you
  `incentive_pct`         DECIMAL(5,2)  DEFAULT NULL,            -- incentive % (from xlsx)
  `max_discount_pct`      TINYINT UNSIGNED DEFAULT 0,            -- ceiling for the login coupon
  `currency`              CHAR(3)       NOT NULL DEFAULT 'INR',
  `effective_from`        DATE          NOT NULL DEFAULT (CURRENT_DATE),
  `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lpp_pricing_current` (`product_id`, `effective_from`),
  CONSTRAINT `fk_lpp_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `lab_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7) Product images (1:N — imageMaster[]) ------------------------------------
CREATE TABLE IF NOT EXISTS `lab_product_images` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `url`        VARCHAR(500) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_lpi_product` (`product_id`),
  CONSTRAINT `fk_lpi_product` FOREIGN KEY (`product_id`) REFERENCES `lab_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8) Aliases / SEO keywords (1:N — split from aliasName) ---------------------
CREATE TABLE IF NOT EXISTS `lab_product_aliases` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `alias`      VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lpa_product` (`product_id`),
  KEY `idx_lpa_alias` (`alias`),
  CONSTRAINT `fk_lpa_product` FOREIGN KEY (`product_id`) REFERENCES `lab_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9) Coupons (login bonus discount — the "order from here, get more" hook) ----
CREATE TABLE IF NOT EXISTS `lab_coupons` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(40)  NOT NULL,
  `discount_pct`     TINYINT UNSIGNED NOT NULL,     -- 5 / 10 / 15 / 20 / 25
  `description`      VARCHAR(255) DEFAULT NULL,
  `requires_login`   TINYINT(1)   NOT NULL DEFAULT 1,
  `is_active`        TINYINT(1)   NOT NULL DEFAULT 1,
  `starts_at`        DATE         DEFAULT NULL,
  `ends_at`          DATE         DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lab_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The coupon's effective discount on any product is:
--   LEAST(lab_coupons.discount_pct, lab_product_pricing.max_discount_pct)
-- so the per-product ceiling from the xlsx is always respected.
