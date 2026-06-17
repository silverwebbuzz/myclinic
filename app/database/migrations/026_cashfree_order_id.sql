-- 026_cashfree_order_id.sql
-- Adds tenants.cashfree_order_id so the Cashfree return-URL can correlate the
-- redirect back to the pending subscription order. Optional: the gateway code
-- guards every write, so Cashfree still works without this (the order_id also
-- encodes clinic+plan). Run it to persist the pending-order marker cleanly.
-- Safe to re-run: information_schema guard.
-- phpMyAdmin: select your app database in the left sidebar first.

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'tenants'
                      AND COLUMN_NAME = 'cashfree_order_id');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE tenants ADD COLUMN cashfree_order_id VARCHAR(80) NULL AFTER razorpay_customer_id',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify:
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'cashfree_order_id';
