-- =====================================================================
-- Workforce Health — Phase 2E: risk register (HSE, non-clinical)
-- Design: document/workforce-health-phase2-design.md §2E (H7)
-- Run AFTER 2026_11_25_workforce_phase2d.sql. Safe to run twice. No FKs.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `corp_risk_entries` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`          BIGINT UNSIGNED NOT NULL,
    `ref`                 VARCHAR(20)  NOT NULL,                  -- R-0001
    `branch_id`           BIGINT UNSIGNED NOT NULL DEFAULT 0,     -- 0 = all branches
    `job_role_id`         BIGINT UNSIGNED NOT NULL DEFAULT 0,     -- 0 = not tied to one job role
    `activity`            VARCHAR(200) NOT NULL,                  -- task / area, e.g. "Welding in fabrication bay"
    `hazard`              VARCHAR(200) NOT NULL,                  -- e.g. "Welding fume"
    `category`            ENUM('physical','chemical','biological','ergonomic','psychosocial','safety') NOT NULL,
    `hazard_id`           INT UNSIGNED NOT NULL DEFAULT 0,        -- link to oh_hazards when Phase 2A exists
    `who_exposed`         VARCHAR(300) DEFAULT NULL,
    `likelihood`          TINYINT UNSIGNED NOT NULL,              -- 1–5, before controls
    `severity`            TINYINT UNSIGNED NOT NULL,              -- 1–5
    `controls`            VARCHAR(2000) DEFAULT NULL,             -- existing controls
    `actions`             VARCHAR(2000) DEFAULT NULL,             -- further actions planned
    `residual_likelihood` TINYINT UNSIGNED NOT NULL DEFAULT 0,    -- after controls (0 = not assessed)
    `residual_severity`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `owner_user_id`       BIGINT UNSIGNED NOT NULL DEFAULT 0,     -- corp_users.id
    `review_on`           DATE         DEFAULT NULL,
    `status`              ENUM('open','controlled','closed') NOT NULL DEFAULT 'open',
    `created_by`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_by`          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`          DATETIME     NOT NULL,
    `updated_at`          DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ref` (`company_id`, `ref`),
    KEY `idx_company` (`company_id`, `status`, `branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The risk register is built: show the permission in the role editor.
UPDATE `corp_permissions` SET `phase` = 0 WHERE `code` = 'risk_register.manage';
