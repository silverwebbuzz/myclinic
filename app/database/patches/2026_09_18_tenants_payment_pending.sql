-- Add payment_pending to tenants (idempotent).
--
-- Registration now ends with Checkout → Payment (no free trial). A clinic
-- created through /register is flagged payment_pending = 1 until its first
-- Razorpay payment is captured; SubscriptionMiddleware keeps such clinics on
-- /register/checkout. Existing tenants default to 0 and are unaffected.
--
-- Until this patch runs, new signups fall back to the old 30-day trial.
--
-- Run:
--   mysql -u root myclinic < app/database/patches/2026_09_18_tenants_payment_pending.sql

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tenants'
      AND COLUMN_NAME = 'payment_pending'
);

SET @ddl := IF(
    @col_exists = 0,
    "ALTER TABLE `tenants`
        ADD COLUMN `payment_pending` TINYINT(1) NOT NULL DEFAULT 0
        AFTER `razorpay_order_id`",
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
