-- =====================================================================
-- Workforce Health — Phase 2D: pre-exam health questionnaires
-- Design: document/workforce-health-phase2-design.md §2D (H5)
-- Run AFTER 2026_11_24_workforce_phase2b.sql. Safe to run twice. No FKs.
-- Answers are CLINICAL (occ_questionnaire_responses): only the examining
-- clinic and the employee see them; the company sees "questionnaire done"
-- (corp_bookings.questionnaire_done_at). Firewalled by CorpFirewallGuardTest.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `oh_questionnaires` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(40)  NOT NULL,
    `version`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `name`          VARCHAR(150) NOT NULL,
    `intro`         VARCHAR(500) DEFAULT NULL,
    `status`        ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
    `is_sample`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL,
    `updated_at`    DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code_version` (`code`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oh_questionnaire_items` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `questionnaire_id` INT UNSIGNED NOT NULL,
    `code`             VARCHAR(40)  NOT NULL,
    `section`          VARCHAR(80)  NOT NULL,
    `type`             ENUM('yes_no','choice','text') NOT NULL,
    `text`             VARCHAR(300) NOT NULL,
    `options_json`     VARCHAR(1000) DEFAULT NULL,           -- choice: ["Never","In the past","Now"]
    `detail_prompt`    VARCHAR(200) DEFAULT NULL,            -- yes_no: asked when the answer is Yes
    `required`         TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_q_code` (`questionnaire_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One response per booking (the employee fills it before the visit, or the clinic with the employee).
CREATE TABLE IF NOT EXISTS `occ_questionnaire_responses` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`       BIGINT UNSIGNED NOT NULL,
    `employment_id`    BIGINT UNSIGNED NOT NULL,
    `booking_id`       BIGINT UNSIGNED NOT NULL,
    `questionnaire_id` INT UNSIGNED    NOT NULL,
    `answers_json`     TEXT            NOT NULL,             -- {code: {"v": "...", "d": "detail"}} + question text snapshot
    `has_yes`          TINYINT(1)      NOT NULL DEFAULT 0,   -- any "Yes" (for the doctor's attention)
    `submitted_by`     ENUM('employee','clinic') NOT NULL,
    `submitter_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,   -- patient_identities.id or users.id
    `submitted_at`     DATETIME        NOT NULL,
    `updated_at`       DATETIME        NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_booking` (`booking_id`),
    KEY `idx_company_emp` (`company_id`, `employment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- corp_bookings.questionnaire_done_at — what the company may know ("done"), never the answers
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corp_bookings' AND COLUMN_NAME = 'questionnaire_done_at');
SET @ddl := IF(@c = 0,
    'ALTER TABLE `corp_bookings` ADD COLUMN `questionnaire_done_at` DATETIME DEFAULT NULL AFTER `status`',
    'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- SAMPLE questionnaire (H5) — for testing; replace with one reviewed by an
-- occupational health physician (same rule as the sample rule templates).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `oh_questionnaires` (`code`, `version`, `name`, `intro`, `status`, `is_sample`, `created_at`, `updated_at`) VALUES
    ('PRE-EXAM', 1, 'Pre-exam health questionnaire',
     'Please answer honestly. Your answers go only to the examining doctor, never to your employer.', 'published', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT IGNORE INTO `oh_questionnaire_items` (`questionnaire_id`, `code`, `section`, `type`, `text`, `options_json`, `detail_prompt`, `required`, `sort_order`)
SELECT q.id, x.code, x.section, x.type, x.text, x.opts, x.detail, x.req, x.sort
  FROM `oh_questionnaires` q
  JOIN (
              SELECT 'long_term'  AS code, 'Your health' AS section, 'yes_no' AS type, 'Do you have any long-term illness (for example diabetes, high blood pressure, asthma, epilepsy or heart disease)?' AS text, NULL AS opts, 'Which, and since when?' AS detail, 1 AS req, 10 AS sort
    UNION ALL SELECT 'medicines',  'Your health', 'yes_no', 'Do you take any medicines regularly?', NULL, 'Which medicines?', 1, 20
    UNION ALL SELECT 'surgery',    'Your health', 'yes_no', 'Have you had an operation or a hospital stay in the last 5 years?', NULL, 'What and when?', 1, 30
    UNION ALL SELECT 'allergies',  'Your health', 'yes_no', 'Do you have any allergies?', NULL, 'To what?', 1, 40
    UNION ALL SELECT 'fits',       'Your health', 'yes_no', 'Have you ever had fits, fainting or blackouts?', NULL, 'When was the last one?', 1, 50
    UNION ALL SELECT 'senses',     'Your health', 'yes_no', 'Do you have problems with your hearing or eyesight (including glasses or contact lenses)?', NULL, 'Please describe', 1, 60
    UNION ALL SELECT 'msk',        'Your health', 'yes_no', 'Do you have back, joint or muscle problems?', NULL, 'Where, and does it limit your work?', 1, 70
    UNION ALL SELECT 'tobacco',    'Your health', 'choice', 'Do you smoke or use tobacco?', '["Never","In the past","Now"]', NULL, 1, 80
    UNION ALL SELECT 'alcohol',    'Your health', 'choice', 'How often do you drink alcohol?', '["Never","Occasionally","Weekly","Daily"]', NULL, 1, 90
    UNION ALL SELECT 'exposure',   'Work and exposure', 'yes_no', 'Have you worked with noise, dust, fumes, chemicals or radiation in a previous job?', NULL, 'Which job, what exposure, how many years?', 1, 110
    UNION ALL SELECT 'work_injury','Work and exposure', 'yes_no', 'Have you had a work injury or an illness caused by work before?', NULL, 'What and when?', 1, 120
    UNION ALL SELECT 'symptoms',   'Work and exposure', 'yes_no', 'Do you get breathing problems, cough, skin rash or chest pain at work?', NULL, 'Please describe', 1, 130
    UNION ALL SELECT 'shifts',     'Work and exposure', 'yes_no', 'Do you work night shifts or rotating shifts?', NULL, NULL, 1, 140
    UNION ALL SELECT 'other',      'Anything else', 'text', 'Is there anything else the doctor should know?', NULL, NULL, 0, 150
  ) x
 WHERE q.code = 'PRE-EXAM' AND q.version = 1;
