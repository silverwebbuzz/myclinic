-- Add razorpay_order_id to tenants (idempotent).
--
-- The subscription gateway moved from Cashfree to the Razorpay Orders API.
-- BillingGatewayService stores the pending Razorpay order id here so the
-- return-URL verify can look it up. The legacy `cashfree_order_id` and
-- `razorpay_customer_id` columns are left in place (untouched) — dropping
-- them would lose historical references and they're harmless.
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_07_15_tenants_razorpay_order_id.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tenants'
      AND COLUMN_NAME = 'razorpay_order_id'
);

SET @ddl := IF(
    @col_exists = 0,
    "ALTER TABLE `tenants`
        ADD COLUMN `razorpay_order_id` VARCHAR(80) DEFAULT NULL
        AFTER `razorpay_customer_id`",
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
