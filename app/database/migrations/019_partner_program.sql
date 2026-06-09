USE manageclinic;

-- =====================================================================
-- Partner / Affiliate Program (platform-level, NOT tenant-scoped).
--
-- A Partner is a global actor (like platform_admins) who refers doctors/
-- clinics to the SaaS product. When a referred clinic pays for a yearly
-- subscription — and on every renewal — the partner earns a commission.
--
-- IMPORTANT: none of these tables carry clinic_id as a tenant scope, so
-- they must NOT be queried through QueryBuilder::forClinic(). They live
-- alongside tenants/saas_invoices/platform_admins.
-- =====================================================================

-- 01 partners — the affiliate account
CREATE TABLE IF NOT EXISTS partners (
  id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                        VARCHAR(120) NOT NULL,
  email                       VARCHAR(150) NOT NULL,
  phone                       VARCHAR(20) NULL,
  password_hash               VARCHAR(255) NOT NULL,
  country_code                CHAR(2) DEFAULT 'IN',
  city                        VARCHAR(80) NULL,
  state                       VARCHAR(80) NULL,
  referral_code               VARCHAR(20) NOT NULL,
  -- NULL = use partner_settings.default_commission_percent
  commission_percent_override DECIMAL(5,2) NULL,
  status                      ENUM('pending','active','suspended','rejected') DEFAULT 'pending',
  payout_method               ENUM('upi','bank') NULL,
  upi_id                      VARCHAR(100) NULL,
  bank_account_name           VARCHAR(120) NULL,
  bank_account_no             VARCHAR(40) NULL,
  bank_ifsc                   VARCHAR(20) NULL,
  pan_number                  VARCHAR(20) NULL,
  is_active                   TINYINT(1) DEFAULT 1,
  approved_at                 TIMESTAMP NULL,
  approved_by                 BIGINT UNSIGNED NULL,
  last_login_at               TIMESTAMP NULL,
  created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partner_email (email),
  UNIQUE KEY uq_partner_referral_code (referral_code),
  KEY idx_partner_status (status),
  CONSTRAINT fk_partner_approved_by FOREIGN KEY (approved_by) REFERENCES platform_admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 02 partner_documents — KYC uploads, reviewed by admin
CREATE TABLE IF NOT EXISTS partner_documents (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id    BIGINT UNSIGNED NOT NULL,
  doc_type      ENUM('id_proof','pan','bank_proof','agreement','other') NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  status        ENUM('pending','verified','rejected') DEFAULT 'pending',
  reviewed_by   BIGINT UNSIGNED NULL,
  reviewed_at   TIMESTAMP NULL,
  uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_partner_docs (partner_id, doc_type),
  CONSTRAINT fk_partner_docs_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  CONSTRAINT fk_partner_docs_reviewer FOREIGN KEY (reviewed_by) REFERENCES platform_admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 03 partner_referrals — which partner brought which clinic (first-touch)
-- Unique on tenant_id: a clinic is attributed to at most one partner.
CREATE TABLE IF NOT EXISTS partner_referrals (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id         BIGINT UNSIGNED NOT NULL,
  tenant_id          BIGINT UNSIGNED NOT NULL,
  referral_code_used VARCHAR(20) NULL,
  attributed_via     ENUM('link','code') DEFAULT 'link',
  status             ENUM('pending','converted','churned') DEFAULT 'pending',
  registered_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  converted_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_referral_tenant (tenant_id),
  KEY idx_referral_partner (partner_id, status),
  CONSTRAINT fk_referral_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  CONSTRAINT fk_referral_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 04 partner_commissions — earnings ledger, one row per qualifying payment
CREATE TABLE IF NOT EXISTS partner_commissions (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id         BIGINT UNSIGNED NOT NULL,
  referral_id        BIGINT UNSIGNED NOT NULL,
  tenant_id          BIGINT UNSIGNED NOT NULL,
  -- The paid SaaS subscription invoice that triggered this commission.
  -- May be NULL when the paid conversion path doesn't write a saas_invoice
  -- row (e.g. simulated/dev plan) — source/reference identifies it instead.
  saas_invoice_id    BIGINT UNSIGNED NULL,
  source             VARCHAR(40) DEFAULT 'subscription',
  reference          VARCHAR(80) NULL,
  base_amount        DECIMAL(12,2) NOT NULL,
  commission_percent DECIMAL(5,2) NOT NULL,
  commission_amount  DECIMAL(12,2) NOT NULL,
  currency           CHAR(3) DEFAULT 'INR',
  type               ENUM('initial','renewal') DEFAULT 'initial',
  status             ENUM('pending','approved','reversed','paid') DEFAULT 'pending',
  payout_request_id  BIGINT UNSIGNED NULL,
  earned_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  approved_at        TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_commission_partner (partner_id, status),
  KEY idx_commission_tenant (tenant_id),
  KEY idx_commission_payout (payout_request_id),
  -- Guard against double-counting the same paid invoice.
  UNIQUE KEY uq_commission_invoice (saas_invoice_id),
  CONSTRAINT fk_commission_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  CONSTRAINT fk_commission_referral FOREIGN KEY (referral_id) REFERENCES partner_referrals(id) ON DELETE CASCADE,
  CONSTRAINT fk_commission_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_commission_invoice FOREIGN KEY (saas_invoice_id) REFERENCES saas_invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 05 partner_payout_requests — partner requests, admin processes within 7 days
CREATE TABLE IF NOT EXISTS partner_payout_requests (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id        BIGINT UNSIGNED NOT NULL,
  amount            DECIMAL(12,2) NOT NULL,
  currency          CHAR(3) DEFAULT 'INR',
  status            ENUM('requested','processing','paid','rejected') DEFAULT 'requested',
  payout_method     ENUM('upi','bank') NULL,
  payment_reference VARCHAR(120) NULL,
  admin_note        VARCHAR(255) NULL,
  processed_by      BIGINT UNSIGNED NULL,
  requested_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_payout_partner (partner_id, status),
  KEY idx_payout_status (status),
  CONSTRAINT fk_payout_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  CONSTRAINT fk_payout_processor FOREIGN KEY (processed_by) REFERENCES platform_admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 06 partner_settings — single-row global config (id=1)
CREATE TABLE IF NOT EXISTS partner_settings (
  id                         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  default_commission_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  commission_on_renewals     TINYINT(1) NOT NULL DEFAULT 1,
  clearance_days             SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  min_payout_amount          DECIMAL(12,2) NOT NULL DEFAULT 1000.00,
  cookie_window_days         SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  updated_at                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO partner_settings (id, default_commission_percent, commission_on_renewals, clearance_days, min_payout_amount, cookie_window_days)
VALUES (1, 10.00, 1, 15, 1000.00, 30)
ON DUPLICATE KEY UPDATE id = id;
