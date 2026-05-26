-- =====================================================================
-- 015_directory_leads.sql
--
-- Lead-generation pipeline: a LOGGED-IN patient on eclinicpro.com/find-a-doctor
-- clicks "Book" on an UNCLAIMED doctor. We:
--   1) record the lead
--   2) SMS the doctor a short message + unique landing URL (subject to
--      per-doctor quota + quiet hours)
--   3) doctor opens the URL → sees patient name/phone/preferred slot, plus a
--      "Claim your clinic" CTA
--
-- Because the patient is already OTP-verified by patient_identities + session,
-- we DON'T duplicate name/phone here. We FK to patient_identities and read
-- name/phone live. Cleaner, and one source of truth.
--
-- Three tables:
--   directory_leads          — every view / book intent / submission
--   directory_sms_quotas     — per-doctor SMS limits (overrides defaults)
--   directory_sms_settings   — global SMS template + defaults + master toggle
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) directory_leads — append-only log of patient activity on unclaimed
--    listings. Patient is ALWAYS logged in for type='book_submitted'.
--    For 'view' we may record without an identity (just analytics).
-- ---------------------------------------------------------------------
CREATE TABLE `directory_leads` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `directory_doctor_id`      BIGINT UNSIGNED NOT NULL,
  `type`                     ENUM('view','book_intent','book_submitted','call') NOT NULL,

  -- Patient — required for book_submitted, optional for view/call
  `patient_identity_id`      BIGINT UNSIGNED NULL,

  -- Booking specifics (only for book_submitted)
  `preferred_date`           DATE NULL,
  `preferred_time`           VARCHAR(10) NULL,        -- e.g. "17:30"
  `reason`                   TEXT NULL,

  -- Opaque token for the SMS landing URL (eclnp.in/L/{token})
  `view_token`               CHAR(16) NULL,

  -- Tracking
  `source`                   VARCHAR(40) NOT NULL DEFAULT 'find-a-doctor',
  `referrer`                 VARCHAR(255) NULL,
  `ip`                       VARCHAR(45)  NULL,
  `user_agent`               VARCHAR(255) NULL,

  -- SMS dispatch state
  `sms_sent_at`              DATETIME NULL,
  `sms_provider_id`          VARCHAR(60) NULL,
  `sms_status`               ENUM('pending','sent','suppressed_quota','suppressed_quiet','suppressed_paused','failed','not_applicable')
                               NOT NULL DEFAULT 'pending',

  -- Conversion tracking — set when doctor opens the lead URL
  `doctor_viewed_at`         DATETIME NULL,
  `doctor_view_count`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `doctor_contacted_patient` TINYINT(1) NOT NULL DEFAULT 0,

  `created_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dl_view_token`  (`view_token`),
  KEY `idx_dl_doctor_created`    (`directory_doctor_id`, `created_at`),
  KEY `idx_dl_identity`          (`patient_identity_id`),
  KEY `idx_dl_type_created`      (`type`, `created_at`),
  KEY `idx_dl_sms_status`        (`sms_status`),
  CONSTRAINT `fk_dl_doctor`
    FOREIGN KEY (`directory_doctor_id`) REFERENCES `directory_doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dl_identity`
    FOREIGN KEY (`patient_identity_id`) REFERENCES `patient_identities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2) directory_sms_quotas — per-doctor overrides for SMS limits.
--    Most doctors use the global defaults (no row here). A row appears
--    when admin custom-tunes a quota OR when a doctor texts STOP and we
--    auto-flip is_paused.
-- ---------------------------------------------------------------------
CREATE TABLE `directory_sms_quotas` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `directory_doctor_id` BIGINT UNSIGNED NOT NULL,
  `per_day`             TINYINT UNSIGNED NULL,        -- NULL = use global default
  `per_week`            TINYINT UNSIGNED NULL,
  `per_month`           SMALLINT UNSIGNED NULL,
  `is_paused`           TINYINT(1) NOT NULL DEFAULT 0,
  `paused_at`           DATETIME NULL,
  `pause_reason`        VARCHAR(120) NULL,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dsq_doctor` (`directory_doctor_id`),
  CONSTRAINT `fk_dsq_doctor`
    FOREIGN KEY (`directory_doctor_id`) REFERENCES `directory_doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3) directory_sms_settings — single-row global config table.
--    Admin edits this from /admin/lead-settings.
-- ---------------------------------------------------------------------
CREATE TABLE `directory_sms_settings` (
  `id`                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `enabled`             TINYINT(1) NOT NULL DEFAULT 1,
  `default_per_day`     TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `default_per_week`    TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `default_per_month`   SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `provider_template_id` VARCHAR(60) NULL,
  `template_body`       TEXT NOT NULL DEFAULT
    'eClinicPro: {patient_name} wants to book you {date} at {time}. View: {url} Reply STOP to opt out.',
  `quiet_hours_start`   TIME NOT NULL DEFAULT '21:00:00',
  `quiet_hours_end`     TIME NOT NULL DEFAULT '08:00:00',
  `lead_landing_base`   VARCHAR(120) NOT NULL DEFAULT 'https://eclinicpro.com/L/',
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `directory_sms_settings` (`id`) VALUES (1)
ON DUPLICATE KEY UPDATE `id` = 1;


COMMIT;
