-- /store marketplace — Batch 8 — store emails, seller password reset, payout requests · `2026_10_03_store_emails.sql`
-- Safe to re-run (CREATE TABLE IF NOT EXISTS only; no changes to existing tables).
--   Server: mysql silverwebbuzz_in_myclinic < app/database/patches/2026_10_03_store_emails.sql
--
-- store_email_templates: admin edits to store email wording + on/off per email
--   (/admin/store/email-templates). No row = built-in default text, email on.
-- store_email_log:       every store email attempt (sent / failed / switched off),
--   shown on /admin/store/email.
-- store_vendor_password_resets: "Forgot password?" links for seller logins
--   (only a SHA-256 of the token is stored; links expire after 60 minutes).
-- store_payout_requests: seller clicked "Request payout" → a draft payout is created
--   and this row says who asked and when.

CREATE TABLE IF NOT EXISTS `store_email_templates` (
    `template_key` VARCHAR(60)     NOT NULL,
    `subject`      VARCHAR(255)    DEFAULT NULL,        -- NULL/'' = default
    `title`        VARCHAR(255)    DEFAULT NULL,
    `body`         TEXT            DEFAULT NULL,        -- paragraphs, blank line between
    `cta_label`    VARCHAR(80)     DEFAULT NULL,
    `note`         TEXT            DEFAULT NULL,        -- extra text shown after the details
    `is_enabled`   TINYINT(1)      NOT NULL DEFAULT 1,
    `updated_by`   VARCHAR(190)    DEFAULT NULL,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_email_log` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_key` VARCHAR(60)     NOT NULL,
    `recipient`    VARCHAR(190)    NOT NULL,
    `subject`      VARCHAR(255)    NOT NULL,
    `status`       ENUM('sent','failed','disabled') NOT NULL,
    `error`        VARCHAR(500)    DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sel_created` (`created_at`),
    KEY `idx_sel_template` (`template_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_vendor_password_resets` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash`     CHAR(64)        NOT NULL,          -- sha256(token); the token itself is only in the email
    `expires_at`     DATETIME        NOT NULL,
    `used_at`        DATETIME        DEFAULT NULL,
    `created_ip`     VARCHAR(45)     DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_svpr_token` (`token_hash`),
    KEY `idx_svpr_user` (`vendor_user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_payout_requests` (
    `payout_id`      BIGINT UNSIGNED NOT NULL,
    `vendor_id`      BIGINT UNSIGNED NOT NULL,
    `vendor_user_id` BIGINT UNSIGNED DEFAULT NULL,
    `note`           VARCHAR(500)    DEFAULT NULL,      -- optional message from the seller
    `decline_reason` VARCHAR(500)    DEFAULT NULL,      -- set when admin declines
    `requested_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`payout_id`),
    KEY `idx_spr_vendor` (`vendor_id`, `requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
