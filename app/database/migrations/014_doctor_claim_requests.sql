-- =====================================================================
-- 014_doctor_claim_requests.sql
--
-- Two intake points for doctors landing on the marketing site:
--   (a) "Claim this listing"  — they found themselves in our directory
--   (b) "I'm not listed"      — they want to be added
--
-- Both feed ONE admin review queue. SuperAdmin approves → we create a
-- tenant + user row, doctor logs in via phone OTP at /doctor/login,
-- their listing flips to is_claimed = 1.
--
-- No SMTP needed. OTP is the only verification at submission time.
-- Document upload (medical registration certificate) is recorded for
-- the admin to review manually.
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) doctor_claim_requests — the review queue
-- ---------------------------------------------------------------------
CREATE TABLE `doctor_claim_requests` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`                ENUM('claim','new_listing') NOT NULL,

  -- For type='claim': which Google-scraped listing they're claiming.
  -- NULL for type='new_listing'.
  `directory_doctor_id` BIGINT UNSIGNED NULL,

  -- Doctor-supplied info
  `full_name`           VARCHAR(120) NOT NULL,
  `phone`               VARCHAR(20)  NOT NULL,
  `phone_verified_at`   DATETIME     NULL,        -- set as soon as OTP is verified
  `email`               VARCHAR(160) NULL,        -- optional
  `clinic_name`         VARCHAR(180) NULL,
  `city`                VARCHAR(80)  NULL,
  `state`               VARCHAR(80)  NULL,
  `specialty`           VARCHAR(80)  NULL,        -- slug matching directory_doctors.specialty
  `reg_number`          VARCHAR(60)  NULL,        -- medical-council registration #
  `reg_council`         VARCHAR(80)  NULL,        -- "MCI", "Gujarat Medical Council", etc.
  `document_path`       VARCHAR(255) NULL,        -- uploaded certificate (under storage/claims/)
  `message`             TEXT NULL,                -- free text from the doctor

  -- Admin workflow
  `status`              ENUM('pending','phone_verified','approved','rejected','duplicate')
                          NOT NULL DEFAULT 'pending',
  `reviewed_by`         BIGINT UNSIGNED NULL,     -- platform_admins.id
  `reviewed_at`         DATETIME NULL,
  `reviewer_notes`      TEXT NULL,

  -- On approval, link to what we created
  `created_tenant_id`   BIGINT UNSIGNED NULL,
  `created_user_id`     BIGINT UNSIGNED NULL,

  -- Tracking
  `ip`                  VARCHAR(45)  NULL,
  `user_agent`          VARCHAR(255) NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_dcr_status_created` (`status`, `created_at`),
  KEY `idx_dcr_directory_doctor` (`directory_doctor_id`),
  KEY `idx_dcr_phone` (`phone`),
  KEY `idx_dcr_type_status` (`type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2) doctor_otp_codes — same structure as patient_otp_codes but separate
--    so doctor login throttling is independent of patient login.
--    'purpose' lets one row serve both signup (claim form OTP) and
--    sign-in (doctor logs in to portal).
-- ---------------------------------------------------------------------
CREATE TABLE `doctor_otp_codes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone`          VARCHAR(20) NOT NULL,
  `purpose`        ENUM('claim','login') NOT NULL DEFAULT 'login',
  `code_hash`      CHAR(64) NOT NULL,
  `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`     DATETIME NOT NULL,
  `consumed_at`    DATETIME NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_otp_phone_purpose` (`phone`, `purpose`),
  KEY `idx_doc_otp_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3) Index users.phone so doctor OTP login can find the account fast.
--    (The login flow does WHERE phone = ? AND role = 'doctor'.)
-- ---------------------------------------------------------------------
ALTER TABLE `users` ADD INDEX `idx_users_phone_role` (`phone`, `role`);


COMMIT;
