-- =====================================================================
-- Wellness Hub — Phase 5: comments & Q/A, reports, camp alerts.
-- Plan: document/wellness-hub-plan.md §7
--
-- RUN AFTER: 2026_11_30_wellness_hub_phase4.sql
-- Fresh-install style (no foreign keys). Safe to run more than once.
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

-- 1. Comments and questions on recipes / exercises / tips / videos (item_type
--    'tip') and camps / events ('event'). Signed-in patients only.
--    One level of replies: parent_id points at a top-level comment.
--    status: visible | held (waiting for admin: link / phone / blocked word /
--    reported) | hidden (by admin or the item's creator) | deleted (by author).
CREATE TABLE IF NOT EXISTS `wh_comments` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_type`         ENUM('tip','event') NOT NULL,
    `item_id`           BIGINT UNSIGNED NOT NULL,
    `identity_id`       BIGINT UNSIGNED NOT NULL,                  -- patient_identities.id (the author)
    `parent_id`         BIGINT UNSIGNED DEFAULT NULL,
    `kind`              ENUM('comment','question','reply') NOT NULL DEFAULT 'comment',
    `body`              VARCHAR(2000)   NOT NULL,
    `status`            ENUM('visible','held','hidden','deleted') NOT NULL DEFAULT 'visible',
    `hold_reason`       VARCHAR(60)     DEFAULT NULL,
    `hidden_by`         ENUM('admin','creator') DEFAULT NULL,
    `is_creator_answer` TINYINT(1)      NOT NULL DEFAULT 0,        -- written by the item's own creator
    `author_creator_id` BIGINT UNSIGNED DEFAULT NULL,              -- set when the author is a Health Creator (badge, name)
    `report_count`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `notified_at`       DATETIME        DEFAULT NULL,              -- "your question was answered" sent
    `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whc_item` (`item_type`, `item_id`, `status`, `created_at`),
    KEY `idx_whc_parent` (`parent_id`),
    KEY `idx_whc_queue` (`status`, `created_at`),
    KEY `idx_whc_author` (`identity_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Reports on comments (signed-in only; one per person per comment).
--    3 open reports hold the comment for admin review automatically.
CREATE TABLE IF NOT EXISTS `wh_comment_reports` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `comment_id`   BIGINT UNSIGNED NOT NULL,
    `identity_id`  BIGINT UNSIGNED NOT NULL,
    `reason`       ENUM('abusive','spam','medical_misinfo','personal_info','other') NOT NULL,
    `status`       ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whcr_once` (`comment_id`, `identity_id`),
    KEY `idx_whcr_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. "Email me new free camps / events in my city" (email only — no
--    WhatsApp / SMS). token = one-click unsubscribe link.
CREATE TABLE IF NOT EXISTS `wh_camp_alerts` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_id`   BIGINT UNSIGNED NOT NULL,
    `kind`          ENUM('free_camp','event') NOT NULL DEFAULT 'free_camp',
    `city_slug`     VARCHAR(80)     NOT NULL,
    `city_name`     VARCHAR(80)     NOT NULL,
    `email`         VARCHAR(160)    NOT NULL,
    `token`         CHAR(32)        NOT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `last_sent_at`  DATETIME        DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wha_sub` (`identity_id`, `kind`, `city_slug`),
    UNIQUE KEY `uq_wha_token` (`token`),
    KEY `idx_wha_city` (`is_active`, `kind`, `city_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Which listing went to which alert (never email the same camp twice).
CREATE TABLE IF NOT EXISTS `wh_camp_alert_sent` (
    `alert_id`  BIGINT UNSIGNED NOT NULL,
    `event_id`  BIGINT UNSIGNED NOT NULL,
    `sent_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`alert_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Words / phrases that hold a comment for review (admin-editable later).
INSERT INTO `platform_settings` (`setting_key`, `setting_value`, `is_secret`)
SELECT 'wh_blocked_words', '["whatsapp me","call me on","dm me","guaranteed cure","100% cure","miracle cure","stop your medicine","stop medicine"]', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `platform_settings` WHERE `setting_key` = 'wh_blocked_words');
