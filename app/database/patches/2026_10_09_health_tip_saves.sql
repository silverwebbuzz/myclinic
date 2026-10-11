-- =====================================================================
-- Health Tips — patient saves (2026-10-09)
--   Run AFTER 2026_10_09_health_tips.sql. Safe to run more than once.
--
-- A signed-in patient can save a post (it doubles as a "like"): the post
-- shows its save count, and the patient finds saved posts at
-- /health-tips?saved=1 (linked from the patient panel).
-- health_tips.save_count is a denormalised counter kept in step by
-- partials/health_tips.php (ecp_ht_toggle_save).
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `health_tip_saves` (
    `tip_id`       BIGINT UNSIGNED NOT NULL,
    `identity_id`  BIGINT UNSIGNED NOT NULL,                 -- patient_identities.id
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tip_id`, `identity_id`),
    KEY `idx_hts_identity` (`identity_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `health_tips`
    ADD COLUMN IF NOT EXISTS `save_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `view_count`;
