-- =====================================================================
-- Wellness Hub — Phase 1: Health Creators, per-type permissions,
-- Verified Doctor / Organisation badge.   Plan: document/wellness-hub-plan.md
--
-- RUN AFTER: 2026_10_09_health_tips.sql (copies health_tip_contributors).
-- Fresh-install style (no foreign keys), matching the other patches.
-- Safe to run more than once.
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

-- 1. One row per Health Creator. identity_id = patient_identities.id
--    (a creator is always a patient underneath). NULL only for the
--    "eClinicPro Desk" row the admin posts camps / events under.
CREATE TABLE IF NOT EXISTS `wh_creators` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_id`           BIGINT UNSIGNED DEFAULT NULL,
    `legacy_contributor_id` BIGINT UNSIGNED DEFAULT NULL,          -- health_tip_contributors.id (kept in sync)
    `slug`                  VARCHAR(80)     NOT NULL,
    `display_name`          VARCHAR(120)    NOT NULL,
    `creator_type`          ENUM('individual','doctor','clinic','ngo','government','company','platform') NOT NULL DEFAULT 'individual',
    `headline`              VARCHAR(120)    DEFAULT NULL,
    `bio`                   VARCHAR(600)    DEFAULT NULL,
    `avatar_path`           VARCHAR(255)    DEFAULT NULL,          -- relative to repo root (storage/health_tips/...)
    `email`                 VARCHAR(160)    DEFAULT NULL,
    `city`                  VARCHAR(80)     DEFAULT NULL,
    `state`                 VARCHAR(80)     DEFAULT NULL,
    `org_name`              VARCHAR(160)    DEFAULT NULL,          -- clinic / NGO / company / govt body
    `org_reg_no`            VARCHAR(80)     DEFAULT NULL,
    `gstin`                 VARCHAR(15)     DEFAULT NULL,
    `instagram_handle`      VARCHAR(60)     DEFAULT NULL,
    `youtube_url`           VARCHAR(255)    DEFAULT NULL,
    `website_url`           VARCHAR(255)    DEFAULT NULL,
    `status`                ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `status_reason`         VARCHAR(255)    DEFAULT NULL,
    `is_trusted`            TINYINT(1)      NOT NULL DEFAULT 0,    -- later: auto-publish
    `terms_accepted_at`     DATETIME        DEFAULT NULL,
    `terms_version`         VARCHAR(20)     DEFAULT NULL,
    -- Verified Doctor (creator_type doctor) / Verified Organisation (clinic, ngo, government, company)
    `verify_status`         ENUM('none','pending','verified','rejected','revoked') NOT NULL DEFAULT 'none',
    `verify_note`           VARCHAR(600)    DEFAULT NULL,          -- admin → creator
    `verify_requested_at`   DATETIME        DEFAULT NULL,
    `verified_at`           DATETIME        DEFAULT NULL,
    `verified_by`           BIGINT UNSIGNED DEFAULT NULL,
    `verify_register`       VARCHAR(120)    DEFAULT NULL,          -- which register the admin checked
    `med_council`           VARCHAR(80)     DEFAULT NULL,
    `med_reg_no`            VARCHAR(60)     DEFAULT NULL,          -- never shown publicly
    `med_qualification`     VARCHAR(120)    DEFAULT NULL,          -- "MBBS, MD (Medicine)"
    `specialty`             VARCHAR(120)    DEFAULT NULL,
    `admin_note`            VARCHAR(255)    DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whc_identity` (`identity_id`),
    UNIQUE KEY `uq_whc_slug` (`slug`),
    UNIQUE KEY `uq_whc_legacy` (`legacy_contributor_id`),
    KEY `idx_whc_status` (`status`),
    KEY `idx_whc_verify` (`verify_status`, `verify_requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. What a creator may post. Approved per content type by the admin.
CREATE TABLE IF NOT EXISTS `wh_creator_permissions` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`     BIGINT UNSIGNED NOT NULL,
    `content_type`   ENUM('recipe','exercise','tip','video','free_camp','event') NOT NULL,
    `status`         ENUM('applied','approved','rejected','suspended') NOT NULL DEFAULT 'applied',
    `applicant_note` VARCHAR(600)    DEFAULT NULL,                 -- creator → admin
    `review_note`    VARCHAR(600)    DEFAULT NULL,                 -- admin → creator
    `applied_at`     DATETIME        DEFAULT NULL,
    `reviewed_at`    DATETIME        DEFAULT NULL,
    `reviewed_by`    BIGINT UNSIGNED DEFAULT NULL,
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whp_creator_type` (`creator_id`, `content_type`),
    KEY `idx_whp_queue` (`status`, `applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Private proof files (certificates, ID). Stored in storage/wellness_docs/,
--    served only to the owner and the admin — never a public URL.
CREATE TABLE IF NOT EXISTS `wh_creator_documents` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`     BIGINT UNSIGNED NOT NULL,
    `purpose`        ENUM('permission','verification') NOT NULL,
    `content_type`   VARCHAR(20)     DEFAULT NULL,                 -- for purpose = permission
    `doc_type`       VARCHAR(40)     NOT NULL,
    `file_path`      VARCHAR(255)    NOT NULL,                     -- relative to storage/wellness_docs
    `original_name`  VARCHAR(255)    DEFAULT NULL,
    `file_mime`      VARCHAR(60)     NOT NULL,
    `status`         ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    `created_at`     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whd_creator` (`creator_id`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. History of admin decisions (badge verify / revoke, suspend, permissions).
CREATE TABLE IF NOT EXISTS `wh_creator_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`  BIGINT UNSIGNED NOT NULL,
    `action`      VARCHAR(40)     NOT NULL,
    `detail`      VARCHAR(600)    DEFAULT NULL,
    `admin_id`    BIGINT UNSIGNED DEFAULT NULL,                    -- NULL = the creator did it
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whl_creator` (`creator_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Existing Health Tips contributors become creators (same identity, slug,
--    profile), already approved for recipes, exercises and quick tips.
INSERT INTO `wh_creators`
    (`identity_id`, `legacy_contributor_id`, `slug`, `display_name`, `creator_type`, `headline`, `bio`,
     `avatar_path`, `email`, `instagram_handle`, `youtube_url`, `website_url`, `status`,
     `terms_accepted_at`, `terms_version`, `admin_note`, `created_at`)
SELECT c.`identity_id`, c.`id`, c.`slug`, c.`display_name`, 'individual', c.`headline`, c.`bio`,
       c.`avatar_path`, pi.`email`, c.`instagram_handle`, c.`youtube_url`, c.`website_url`, c.`status`,
       c.`terms_accepted_at`, c.`terms_version`, c.`admin_note`, c.`created_at`
FROM `health_tip_contributors` c
LEFT JOIN `patient_identities` pi ON pi.`id` = c.`identity_id`
WHERE NOT EXISTS (SELECT 1 FROM `wh_creators` w WHERE w.`legacy_contributor_id` = c.`id`)
  AND NOT EXISTS (SELECT 1 FROM `wh_creators` w WHERE w.`identity_id` = c.`identity_id`)
  AND NOT EXISTS (SELECT 1 FROM `wh_creators` w WHERE w.`slug` = c.`slug`);

INSERT IGNORE INTO `wh_creator_permissions` (`creator_id`, `content_type`, `status`, `applied_at`, `reviewed_at`, `review_note`)
SELECT w.`id`, t.`ct`, 'approved', w.`created_at`, NOW(), 'Existing Health Tips contributor'
FROM `wh_creators` w
JOIN (SELECT 'recipe' AS ct UNION ALL SELECT 'exercise' UNION ALL SELECT 'tip') t
WHERE w.`legacy_contributor_id` IS NOT NULL;

-- 6. "eClinicPro Desk": the admin lists government / public camps under it
--    (Phase 2). No login; approved for every type.
INSERT INTO `wh_creators` (`identity_id`, `slug`, `display_name`, `creator_type`, `headline`, `status`,
                           `terms_accepted_at`, `terms_version`, `verify_status`, `verified_at`)
SELECT NULL, 'eclinicpro-desk', 'eClinicPro Desk', 'platform',
       'Health camps and events listed by the eClinicPro team', 'active', NOW(), '2026-11-27', 'verified', NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `wh_creators` WHERE `slug` = 'eclinicpro-desk');

INSERT IGNORE INTO `wh_creator_permissions` (`creator_id`, `content_type`, `status`, `applied_at`, `reviewed_at`, `review_note`)
SELECT w.`id`, t.`ct`, 'approved', NOW(), NOW(), 'Platform account'
FROM `wh_creators` w
JOIN (SELECT 'recipe' AS ct UNION ALL SELECT 'exercise' UNION ALL SELECT 'tip' UNION ALL SELECT 'video'
      UNION ALL SELECT 'free_camp' UNION ALL SELECT 'event') t
WHERE w.`slug` = 'eclinicpro-desk';

-- 7. Pricing defaults for Phase 4 (editable in admin later). Prices are in
--    rupees BEFORE GST. Nothing reads these yet.
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`)
SELECT 'wh_free_monthly', '{"recipe":5,"exercise":5,"tip":5,"video":5,"event":5}', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'wh_free_monthly');

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`)
SELECT 'wh_packs', '[{"qty":1,"price":49},{"qty":5,"price":199},{"qty":10,"price":299}]', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'wh_packs');

INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`)
SELECT 'wh_gst_pct', '18', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'wh_gst_pct');
