-- Hybrid staff onboarding: username login for staff without email

ALTER TABLE users
  MODIFY email VARCHAR(150) NULL,
  ADD COLUMN username VARCHAR(50) NULL AFTER email,
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
  ADD UNIQUE KEY uq_username (username);
