-- =====================================================================
-- Wellness Hub — Phase 3: hub pages, videos, saves for camps/events,
-- sign-up source tracking.   Plan: document/wellness-hub-plan.md
--
-- RUN AFTER: 2026_11_28_wellness_hub_phase2.sql
-- MariaDB (ADD COLUMN IF NOT EXISTS). Safe to run more than once.
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

-- 1. Videos (YouTube / Instagram reels, embedded — never hosted) are a
--    Health Tips type, written in the same editor.
ALTER TABLE `health_tips`
    MODIFY `type` ENUM('recipe','exercise','tip','video') NOT NULL;

-- 2. Saved camps & events. (Saved recipes / exercises / tips stay in
--    health_tip_saves, which the mobile app already uses.)
CREATE TABLE IF NOT EXISTS `wh_saves` (
    `identity_id` BIGINT UNSIGNED NOT NULL,
    `item_type`   ENUM('event') NOT NULL,
    `item_id`     BIGINT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`identity_id`, `item_type`, `item_id`),
    KEY `idx_whs_item` (`item_type`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Which action made a new patient sign up (camp_contact, save_tip,
--    wellness_cta, …). Set once, when the account is created.
ALTER TABLE `patient_identities`
    ADD COLUMN IF NOT EXISTS `signup_source` VARCHAR(40) DEFAULT NULL AFTER `source`;
