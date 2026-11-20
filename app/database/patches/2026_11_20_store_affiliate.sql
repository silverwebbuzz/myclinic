-- =====================================================================
-- eClinicPro Store — affiliate products (2026-11-20)
--   Server: run in phpMyAdmin BEFORE uploading the PHP files (the store
--   listing query reads these columns; without them every store page fails).
--
--  1. store_vendors.vendor_type     'marketplace' (normal seller) | 'affiliate'
--     Set in /admin/store/vendors/{id} → "Affiliate seller". An affiliate
--     seller (e.g. "eClinicPro Picks") lists products that are bought on
--     the brand's / partner's own website; no cart, orders or payouts.
--  2. store_products.affiliate_url / affiliate_merchant
--     Where "Buy" sends the customer, and the site name on the button.
--  3. store_affiliate_clicks         one row per outbound click (/store/go/{id}),
--     to compare with the commission reports of each affiliate programme.
--
-- Safe to run more than once.
-- =====================================================================

ALTER TABLE `store_vendors`
    ADD COLUMN IF NOT EXISTS `vendor_type` ENUM('marketplace','affiliate') NOT NULL DEFAULT 'marketplace' AFTER `business_type`;

ALTER TABLE `store_products`
    ADD COLUMN IF NOT EXISTS `affiliate_url`      VARCHAR(1000) DEFAULT NULL AFTER `seo_description`,   -- NULL = normal product
    ADD COLUMN IF NOT EXISTS `affiliate_merchant` VARCHAR(80)   DEFAULT NULL AFTER `affiliate_url`;     -- "Buy on {merchant}"

CREATE TABLE IF NOT EXISTS `store_affiliate_clicks` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`   BIGINT UNSIGNED NOT NULL,
    `identity_id`  BIGINT UNSIGNED DEFAULT NULL,          -- patient_identities.id when signed in
    `source`       VARCHAR(20)     NOT NULL DEFAULT 'web', -- web | app
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sac_product` (`product_id`, `created_at`),
    KEY `idx_sac_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
