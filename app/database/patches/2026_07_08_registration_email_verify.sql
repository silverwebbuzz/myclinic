-- ============================================================================
-- Email verification tokens for /register dual-method signup.
-- Doctor enters email first → verify link → then completes clinic details.
--
-- Run once:
--   mysql -u USER -p DB < app/database/patches/2026_07_08_registration_email_verify.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_verify_token_hash` (`token_hash`),
  KEY `idx_email_verify_email` (`email`),
  KEY `idx_email_verify_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
