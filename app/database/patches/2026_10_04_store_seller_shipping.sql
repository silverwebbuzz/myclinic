-- =====================================================================
-- eClinicPro Store — sellers pay the forward courier (2026-10-04)
--
--  * The customer delivery fee is now charged once PER ORDER (store_shipping_rules
--    default row: flat fee, free above a threshold) and split across the order's
--    packages in proportion to their value.
--  * At delivery each seller is charged the courier cost of their package, minus
--    the delivery fee the customer paid towards it (store_vendor_ledger shipping_debit).
--  * Courier estimate rate card (settings below) drives the seller's earnings
--    calculator; the real Shiprocket charge is used when we have it.
--  * Sellers attach a photo of the packed parcel on a scale when booking the courier
--    (evidence for courier weight disputes).
--
-- Safe to run more than once (MariaDB IF NOT EXISTS / INSERT IGNORE).
-- =====================================================================

ALTER TABLE `store_vendor_orders`
    ADD COLUMN IF NOT EXISTS `courier_charge_paise` BIGINT UNSIGNED DEFAULT NULL,   -- forward courier cost for this package
    ADD COLUMN IF NOT EXISTS `seller_shipping_paise` BIGINT UNSIGNED DEFAULT NULL,  -- charged to the seller = courier − customer fee share
    ADD COLUMN IF NOT EXISTS `parcel_photo_path` VARCHAR(255) DEFAULT NULL;         -- storage/store_parcel_photos/...

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`) VALUES
    ('store_courier_est_base_paise', '6500', 0),   -- estimate: first 500 g, GST included
    ('store_courier_est_addl_paise', '4000', 0);   -- estimate: each extra 500 g, GST included

-- Default customer delivery fee: ₹49 per order, free from ₹499 (keeps any value already set).
INSERT INTO `store_shipping_rules` (`vendor_id`, `flat_fee_paise`, `free_above_paise`, `is_active`)
SELECT NULL, 4900, 49900, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `store_shipping_rules` WHERE `vendor_id` IS NULL);
