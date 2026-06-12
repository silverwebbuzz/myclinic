-- 023_fix_billing_partial_payments.sql
-- Problems:
--   1. Partial payments existed in the status enum but nothing could record
--      one — invoices.amount_paid did not exist and balance_due ignored the
--      payments table entirely (it was total - advance_paid only).
--   2. payments.method had no 'insurance' value (invoices.payment_mode does).
--   3. payments had no (clinic_id, created_at) index for the daily revenue stat.
-- Safe to re-run: information_schema guards; MODIFYs are idempotent.
-- phpMyAdmin: select your app database in the left sidebar first.

-- 1a. invoices.amount_paid
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'amount_paid');
SET @ddl := IF(@col_exists = 0,
    'ALTER TABLE invoices ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER advance_paid',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1b. Backfill amount_paid from recorded payments
UPDATE invoices i
SET i.amount_paid = COALESCE(
    (SELECT SUM(p.amount) FROM payments p WHERE p.invoice_id = i.id), 0)
WHERE i.amount_paid = 0;

-- Older paid invoices that predate payment rows: treat as fully settled.
UPDATE invoices
SET amount_paid = GREATEST(total - advance_paid, 0)
WHERE status = 'paid' AND amount_paid = 0;

-- 1c. balance_due now accounts for recorded payments too
ALTER TABLE invoices
  MODIFY COLUMN balance_due DECIMAL(12,2)
    GENERATED ALWAYS AS (total - advance_paid - amount_paid) STORED;

-- 2. Allow 'insurance' as a payment method (idempotent MODIFY)
ALTER TABLE payments
  MODIFY COLUMN method ENUM('cash','upi','card','bank_transfer','online','insurance');

-- 3. Index for daily revenue (SUM of payments per clinic per day)
SET @idx_exists := (SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payments_clinic_date');
SET @ddl := IF(@idx_exists = 0,
    'CREATE INDEX idx_payments_clinic_date ON payments (clinic_id, paid_at)',
    'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify:
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'amount_paid';
SELECT COLUMN_TYPE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'method';
