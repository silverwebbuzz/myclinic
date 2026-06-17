-- 027_saas_invoice_payment.sql
-- Subscription (SaaS) invoices for doctor/clinic plan purchases via Cashfree.
-- The saas_invoices table existed but nothing populated it and it lacked the
-- fields a real GST invoice + pending→paid timeline need. This adds them.
-- Safe to re-run: information_schema guards on every column.
-- phpMyAdmin: select your app database in the left sidebar first.

-- Add 'pending' to the status enum (created at checkout, before payment).
ALTER TABLE saas_invoices
  MODIFY COLUMN status ENUM('draft','pending','open','paid','failed','void','uncollectable') DEFAULT 'pending';

-- invoice_no — human-facing sequential number (e.g. ECP-2026-00001)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices' AND COLUMN_NAME = 'invoice_no');
SET @s := IF(@c = 0, 'ALTER TABLE saas_invoices ADD COLUMN invoice_no VARCHAR(40) NULL AFTER id', 'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- plan + billing cycle bought
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices' AND COLUMN_NAME = 'plan_id');
SET @s := IF(@c = 0,
  'ALTER TABLE saas_invoices ADD COLUMN plan_id VARCHAR(40) NULL AFTER modules_billed, ADD COLUMN billing_cycle VARCHAR(10) NULL AFTER plan_id',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- amount in INR + currency (total_usd legacy stays; we record real charged amount here)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices' AND COLUMN_NAME = 'amount');
SET @s := IF(@c = 0,
  'ALTER TABLE saas_invoices ADD COLUMN amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER billing_cycle, ADD COLUMN currency CHAR(3) NOT NULL DEFAULT ''INR'' AFTER amount',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- gateway + order/payment ids (correlate webhook/return to the row)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices' AND COLUMN_NAME = 'gateway');
SET @s := IF(@c = 0,
  'ALTER TABLE saas_invoices ADD COLUMN gateway VARCHAR(20) NULL AFTER razorpay_inv_id, ADD COLUMN gateway_order_id VARCHAR(80) NULL AFTER gateway, ADD COLUMN gateway_payment_id VARCHAR(80) NULL AFTER gateway_order_id, ADD KEY idx_gateway_order (gateway_order_id)',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- generated PDF path + whether the receipt email was sent
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices' AND COLUMN_NAME = 'pdf_path');
SET @s := IF(@c = 0,
  'ALTER TABLE saas_invoices ADD COLUMN pdf_path VARCHAR(255) NULL, ADD COLUMN emailed_at TIMESTAMP NULL',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- GST breakdown. `amount` above is the GROSS charged total (incl. tax). These
-- record the split for the tax invoice (base + CGST + SGST).
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices' AND COLUMN_NAME = 'base_amount');
SET @s := IF(@c = 0,
  'ALTER TABLE saas_invoices ADD COLUMN base_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount, ADD COLUMN tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER base_amount, ADD COLUMN tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER tax_amount',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Verify:
SELECT COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'saas_invoices'
  AND COLUMN_NAME IN ('invoice_no','plan_id','amount','gateway','gateway_order_id','pdf_path','status');
