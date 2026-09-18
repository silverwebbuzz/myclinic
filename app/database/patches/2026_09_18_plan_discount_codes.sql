-- Plan discount codes (idempotent).
--
-- Admin-managed codes (/admin/discounts) a doctor can apply on the signup
-- checkout (/register/checkout) before paying. A redemption row is written
-- only once the payment is captured (or immediately for a 100%-off code),
-- keyed by the gateway order id so webhook + return-URL can't double count.
--
-- DiscountService::ensureSchema() creates the same tables on first use, so
-- running this by hand is optional.
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_09_18_plan_discount_codes.sql

CREATE TABLE IF NOT EXISTS `plan_discount_codes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(40) NOT NULL,
    `description` VARCHAR(190) DEFAULT NULL,
    `discount_type` ENUM('percent','flat') NOT NULL DEFAULT 'percent',
    `discount_value` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `max_uses` INT UNSIGNED DEFAULT NULL,
    `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plan_discount_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_discount_redemptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `discount_code_id` INT UNSIGNED NOT NULL,
    `clinic_id` INT UNSIGNED NOT NULL,
    `gateway_order_id` VARCHAR(80) NOT NULL,
    `base_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_plan_discount_order` (`gateway_order_id`),
    KEY `idx_plan_discount_code` (`discount_code_id`),
    KEY `idx_plan_discount_clinic` (`clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
