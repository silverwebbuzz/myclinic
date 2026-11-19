-- =====================================================================
-- Workforce Health — Phase 1B: screening rules + due dates (2026-11-20)
--
-- Design: document/workforce-health-phase1-design.md §3
-- Requires: Phase 0 patches + 2026_11_19_workforce_phase1a.sql
--
--   oh_exam_types               platform exam / test catalogue
--   oh_rule_templates           platform programme templates (country × industry), versioned
--   oh_template_items           what each template asks for, how often
--   corp_programmes             a company's own programmes (adopted from a template, or its own)
--   corp_programme_items        their items (company may add / remove / tighten)
--   corp_programme_assignments  who a programme applies to
--   corp_screening_cases        one employee × one exam × one due date
--   corp_case_events            case history
--
-- Includes a SAMPLE rule pack (India: construction + office; UAE: maritime
-- + office) clearly marked is_sample = 1, "for testing, not legal advice".
--
-- Same rules as before: DATETIMEs UTC written by PHP (UTC_TIMESTAMP() in
-- seeds), no FKs, 0 not NULL for "none", safe to run more than once.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `oh_exam_types` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`         VARCHAR(40)  NOT NULL,
    `name`         VARCHAR(120) NOT NULL,
    `kind`         ENUM('exam','lab_test','imaging','questionnaire','vaccination') NOT NULL DEFAULT 'exam',
    `description`  VARCHAR(255) DEFAULT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`   SMALLINT     NOT NULL DEFAULT 100,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oh_rule_templates` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(40)  NOT NULL,                      -- stable across versions ('IN-CON-WELD')
    `version`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `country_code`  CHAR(2)      NOT NULL,
    `industry_id`   INT UNSIGNED NOT NULL DEFAULT 0,            -- 0 = any industry
    `name`          VARCHAR(150) NOT NULL,
    `audience`      VARCHAR(255) DEFAULT NULL,                  -- who it is for ('Welders, gas cutters, fitters')
    `description`   TEXT         DEFAULT NULL,
    `source_note`   VARCHAR(500) DEFAULT NULL,                  -- regulation / reference
    `status`        ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
    `is_sample`     TINYINT(1)   NOT NULL DEFAULT 0,
    `published_at`  DATETIME     DEFAULT NULL,
    `created_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,         -- platform_admins.id
    `created_at`    DATETIME     NOT NULL,
    `updated_at`    DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code_version` (`code`, `version`),
    KEY `idx_lookup` (`status`, `country_code`, `industry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `oh_template_items` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id`       INT UNSIGNED NOT NULL,
    `exam_type_id`      INT UNSIGNED NOT NULL,
    `category`          ENUM('pre_employment','periodic','exit') NOT NULL,
    `frequency_months`  SMALLINT UNSIGNED DEFAULT NULL,          -- periodic only
    `requirement`       ENUM('mandatory','recommended') NOT NULL DEFAULT 'recommended',
    `note`              VARCHAR(255) DEFAULT NULL,
    `sort_order`        SMALLINT     NOT NULL DEFAULT 100,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item` (`template_id`, `exam_type_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_programmes` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `name`              VARCHAR(150) NOT NULL,
    `description`       VARCHAR(500) DEFAULT NULL,
    `template_id`       INT UNSIGNED NOT NULL DEFAULT 0,        -- 0 = the company's own
    `template_code`     VARCHAR(40)  DEFAULT NULL,
    `template_version`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `first_round_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 60,  -- existing staff: first periodic due within N days
    `status`            ENUM('active','archived') NOT NULL DEFAULT 'active',
    `created_by`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        DATETIME     NOT NULL,
    `updated_at`        DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_programme_items` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`        BIGINT UNSIGNED NOT NULL,
    `programme_id`      BIGINT UNSIGNED NOT NULL,
    `exam_type_id`      INT UNSIGNED NOT NULL,
    `category`          ENUM('pre_employment','periodic','exit') NOT NULL,
    `frequency_months`  SMALLINT UNSIGNED DEFAULT NULL,
    `requirement`       ENUM('mandatory','recommended','company') NOT NULL DEFAULT 'company',
    `template_item_id`  INT UNSIGNED NOT NULL DEFAULT 0,
    `is_removed`        TINYINT(1)   NOT NULL DEFAULT 0,
    `removed_reason`    VARCHAR(500) DEFAULT NULL,
    `removed_by`        BIGINT UNSIGNED DEFAULT NULL,
    `removed_at`        DATETIME     DEFAULT NULL,
    `created_at`        DATETIME     NOT NULL,
    `updated_at`        DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_item` (`programme_id`, `exam_type_id`, `category`),
    KEY `idx_company` (`company_id`, `programme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_programme_assignments` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`    BIGINT UNSIGNED NOT NULL,
    `programme_id`  BIGINT UNSIGNED NOT NULL,
    `target_type`   ENUM('all','job_role','department','branch','employee') NOT NULL,
    `target_id`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_target` (`programme_id`, `target_type`, `target_id`),
    KEY `idx_company` (`company_id`, `target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_screening_cases` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`         BIGINT UNSIGNED NOT NULL,
    `employment_id`      BIGINT UNSIGNED NOT NULL,
    `exam_type_id`       INT UNSIGNED NOT NULL,
    `category`           ENUM('pre_employment','periodic','exit','return_to_work','post_incident') NOT NULL,
    `programme_item_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,    -- the item that set the frequency
    `due_on`             DATE         NOT NULL,
    `status`             ENUM('not_scheduled','scheduled','completed','cancelled','waived') NOT NULL DEFAULT 'not_scheduled',
    `completed_on`       DATE         DEFAULT NULL,
    `completion_source`  ENUM('eclinicpro','elsewhere') DEFAULT NULL,
    `note`               VARCHAR(500) DEFAULT NULL,
    `closed_reason`      VARCHAR(500) DEFAULT NULL,
    `created_at`         DATETIME     NOT NULL,
    `updated_at`         DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_company_status_due` (`company_id`, `status`, `due_on`),
    KEY `idx_company_employment` (`company_id`, `employment_id`, `exam_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `corp_case_events` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`   BIGINT UNSIGNED NOT NULL,
    `case_id`      BIGINT UNSIGNED NOT NULL,
    `event`        VARCHAR(40)  NOT NULL,                       -- created, redated, scheduled, completed, done_elsewhere, waived, cancelled
    `detail_json`  TEXT         DEFAULT NULL,
    `actor_type`   ENUM('corp_user','platform_admin','employee','system','provider') NOT NULL,
    `actor_id`     BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_case` (`company_id`, `case_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Screening permissions are now built: show them in the role editor.
UPDATE `corp_permissions` SET `phase` = 0 WHERE `code` IN ('screening.view_status', 'screening.manage', 'rules.customise');

-- ---------------------------------------------------------------------
-- Exam / test catalogue
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `oh_exam_types` (`code`, `name`, `kind`, `description`, `sort_order`) VALUES
    ('physical_exam',     'Physical examination (fitness)',        'exam',          'History, BP, pulse, height, weight, BMI, general and systemic examination', 10),
    ('vision_acuity',     'Vision test (distance & near)',         'exam',          'Snellen / near vision', 20),
    ('colour_vision',     'Colour vision test',                    'exam',          'Ishihara plates', 30),
    ('audiometry',        'Audiometry (hearing test)',             'exam',          'Pure-tone audiometry', 40),
    ('spirometry',        'Spirometry (lung function)',            'exam',          'FEV1, FVC, FEV1/FVC', 50),
    ('ecg',               'ECG',                                   'exam',          '12-lead resting ECG', 60),
    ('musculoskeletal',   'Musculoskeletal assessment',            'exam',          'Back, joints, manual handling fitness', 70),
    ('skin_exam',         'Skin examination',                      'exam',          'Dermatitis / exposure check', 80),
    ('psych_fitness',     'Psychological fitness assessment',      'exam',          'For safety-critical roles', 90),
    ('chest_xray',        'Chest X-ray (PA view)',                 'imaging',       NULL, 100),
    ('cbc',               'Complete blood count (CBC)',            'lab_test',      NULL, 110),
    ('blood_glucose',     'Blood sugar (fasting)',                 'lab_test',      NULL, 120),
    ('lipid_profile',     'Lipid profile',                         'lab_test',      NULL, 130),
    ('lft',               'Liver function test (LFT)',             'lab_test',      NULL, 140),
    ('kft',               'Kidney function test (KFT)',            'lab_test',      NULL, 150),
    ('urine_routine',     'Urine routine & microscopy',            'lab_test',      NULL, 160),
    ('stool_exam',        'Stool examination',                     'lab_test',      'Food handlers', 170),
    ('hbsag',             'Hepatitis B surface antigen (HBsAg)',   'lab_test',      NULL, 180),
    ('blood_lead',        'Blood lead level',                      'lab_test',      'Lead exposure', 190),
    ('cholinesterase',    'Cholinesterase',                        'lab_test',      'Pesticide exposure', 200),
    ('drug_screen',       'Drug screen (urine)',                   'lab_test',      NULL, 210),
    ('alcohol_test',      'Alcohol test',                          'lab_test',      NULL, 220),
    ('health_questionnaire', 'Health questionnaire',               'questionnaire', 'Pre-exam medical history and exposure questionnaire', 230),
    ('hep_b_vaccine',     'Hepatitis B vaccination',               'vaccination',   NULL, 240),
    ('tetanus_vaccine',   'Tetanus vaccination',                   'vaccination',   NULL, 250),
    ('typhoid_vaccine',   'Typhoid vaccination',                   'vaccination',   'Food handlers', 260);

-- ---------------------------------------------------------------------
-- SAMPLE rule pack — for testing only, NOT legal advice (design S13).
-- Replace with templates reviewed by an occupational health physician.
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `oh_rule_templates`
    (`code`, `version`, `country_code`, `industry_id`, `name`, `audience`, `description`, `source_note`, `status`, `is_sample`, `published_at`, `created_at`, `updated_at`)
SELECT t.code, 1, t.cc, COALESCE(i.id, 0), t.name, t.audience, t.descr,
       'SAMPLE for testing, not legal advice. Replace with reviewed rules.', 'published', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP()
  FROM (
    SELECT 'IN-ALL-OFFICE' AS code, 'IN' AS cc, '' AS ind, 'Office & general staff' AS name, 'Office, admin and other low-risk staff' AS audience, 'Basic fitness at joining and every two years.' AS descr
    UNION ALL SELECT 'IN-CON-SITE', 'IN', 'construction', 'Construction site workers', 'Everyone working on a construction site', 'Baseline for all site staff. Add the specific templates for height work, welding and plant operators.'
    UNION ALL SELECT 'IN-CON-HEIGHT', 'IN', 'construction', 'Work at height', 'Scaffolders, steel fixers, riggers, anyone working above 2 m', 'Vision, blood pressure and heart checks for people working at height.'
    UNION ALL SELECT 'IN-CON-WELD', 'IN', 'construction', 'Welding & hot work', 'Welders, gas cutters, fabricators', 'Lung, chest and eye checks for fume and radiation exposure.'
    UNION ALL SELECT 'IN-CON-PLANT', 'IN', 'construction', 'Crane, plant & vehicle operators', 'Crane operators, drivers, excavator and forklift operators', 'Vision, colour vision, hearing and heart checks for safety-critical operators.'
    UNION ALL SELECT 'AE-ALL-OFFICE', 'AE', '', 'Office & general staff', 'Office, admin and other low-risk staff', 'Basic fitness at joining and every two years.'
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'AE', 'maritime', 'Seafarers (deck & engine)', 'Deck officers, ratings, marine engineers', 'Seafarer medical fitness, valid two years (in line with ILO MLC).'
    UNION ALL SELECT 'AE-MAR-YARD', 'AE', 'maritime', 'Yard, welding & fabrication', 'Yard workers, welders, fitters, crane operators', 'Noise, fume and vision checks for yard work.'
  ) t
  LEFT JOIN `corp_industries` i ON i.code = t.ind;

INSERT IGNORE INTO `oh_template_items` (`template_id`, `exam_type_id`, `category`, `frequency_months`, `requirement`, `sort_order`)
SELECT rt.id, et.id, x.cat, x.freq, x.req, x.sort
  FROM (
              SELECT 'IN-ALL-OFFICE' AS tpl, 'physical_exam' AS ex, 'pre_employment' AS cat, NULL AS freq, 'recommended' AS req, 10 AS sort
    UNION ALL SELECT 'IN-ALL-OFFICE', 'vision_acuity',   'pre_employment', NULL, 'recommended', 20
    UNION ALL SELECT 'IN-ALL-OFFICE', 'physical_exam',   'periodic',       24,   'recommended', 30
    UNION ALL SELECT 'IN-CON-SITE',   'physical_exam',   'pre_employment', NULL, 'mandatory',   10
    UNION ALL SELECT 'IN-CON-SITE',   'vision_acuity',   'pre_employment', NULL, 'mandatory',   20
    UNION ALL SELECT 'IN-CON-SITE',   'cbc',             'pre_employment', NULL, 'recommended', 30
    UNION ALL SELECT 'IN-CON-SITE',   'tetanus_vaccine', 'pre_employment', NULL, 'recommended', 40
    UNION ALL SELECT 'IN-CON-SITE',   'physical_exam',   'periodic',       12,   'mandatory',   50
    UNION ALL SELECT 'IN-CON-SITE',   'physical_exam',   'exit',           NULL, 'recommended', 60
    UNION ALL SELECT 'IN-CON-HEIGHT', 'physical_exam',   'pre_employment', NULL, 'mandatory',   10
    UNION ALL SELECT 'IN-CON-HEIGHT', 'vision_acuity',   'pre_employment', NULL, 'mandatory',   20
    UNION ALL SELECT 'IN-CON-HEIGHT', 'ecg',             'pre_employment', NULL, 'mandatory',   30
    UNION ALL SELECT 'IN-CON-HEIGHT', 'vision_acuity',   'periodic',       12,   'mandatory',   40
    UNION ALL SELECT 'IN-CON-HEIGHT', 'ecg',             'periodic',       12,   'recommended', 50
    UNION ALL SELECT 'IN-CON-WELD',   'spirometry',      'pre_employment', NULL, 'mandatory',   10
    UNION ALL SELECT 'IN-CON-WELD',   'chest_xray',      'pre_employment', NULL, 'mandatory',   20
    UNION ALL SELECT 'IN-CON-WELD',   'vision_acuity',   'pre_employment', NULL, 'mandatory',   30
    UNION ALL SELECT 'IN-CON-WELD',   'spirometry',      'periodic',       12,   'mandatory',   40
    UNION ALL SELECT 'IN-CON-WELD',   'vision_acuity',   'periodic',       12,   'mandatory',   50
    UNION ALL SELECT 'IN-CON-WELD',   'chest_xray',      'periodic',       36,   'recommended', 60
    UNION ALL SELECT 'IN-CON-WELD',   'spirometry',      'exit',           NULL, 'recommended', 70
    UNION ALL SELECT 'IN-CON-PLANT',  'vision_acuity',   'pre_employment', NULL, 'mandatory',   10
    UNION ALL SELECT 'IN-CON-PLANT',  'colour_vision',   'pre_employment', NULL, 'mandatory',   20
    UNION ALL SELECT 'IN-CON-PLANT',  'audiometry',      'pre_employment', NULL, 'mandatory',   30
    UNION ALL SELECT 'IN-CON-PLANT',  'ecg',             'pre_employment', NULL, 'recommended', 40
    UNION ALL SELECT 'IN-CON-PLANT',  'drug_screen',     'pre_employment', NULL, 'recommended', 50
    UNION ALL SELECT 'IN-CON-PLANT',  'vision_acuity',   'periodic',       12,   'mandatory',   60
    UNION ALL SELECT 'IN-CON-PLANT',  'audiometry',      'periodic',       12,   'mandatory',   70
    UNION ALL SELECT 'IN-CON-PLANT',  'ecg',             'periodic',       24,   'recommended', 80
    UNION ALL SELECT 'AE-ALL-OFFICE', 'physical_exam',   'pre_employment', NULL, 'recommended', 10
    UNION ALL SELECT 'AE-ALL-OFFICE', 'chest_xray',      'pre_employment', NULL, 'recommended', 20
    UNION ALL SELECT 'AE-ALL-OFFICE', 'physical_exam',   'periodic',       24,   'recommended', 30
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'physical_exam', 'pre_employment', NULL, 'mandatory',   10
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'vision_acuity', 'pre_employment', NULL, 'mandatory',   20
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'colour_vision', 'pre_employment', NULL, 'mandatory',   30
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'audiometry',    'pre_employment', NULL, 'mandatory',   40
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'ecg',           'pre_employment', NULL, 'mandatory',   50
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'cbc',           'pre_employment', NULL, 'mandatory',   60
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'urine_routine', 'pre_employment', NULL, 'mandatory',   70
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'drug_screen',   'pre_employment', NULL, 'mandatory',   80
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'physical_exam', 'periodic',       24,   'mandatory',   90
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'vision_acuity', 'periodic',       24,   'mandatory',  100
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'audiometry',    'periodic',       24,   'mandatory',  110
    UNION ALL SELECT 'AE-MAR-SEAFARER', 'drug_screen',   'periodic',       12,   'recommended', 120
    UNION ALL SELECT 'AE-MAR-YARD',   'physical_exam',   'pre_employment', NULL, 'mandatory',   10
    UNION ALL SELECT 'AE-MAR-YARD',   'audiometry',      'pre_employment', NULL, 'mandatory',   20
    UNION ALL SELECT 'AE-MAR-YARD',   'spirometry',      'pre_employment', NULL, 'mandatory',   30
    UNION ALL SELECT 'AE-MAR-YARD',   'vision_acuity',   'pre_employment', NULL, 'mandatory',   40
    UNION ALL SELECT 'AE-MAR-YARD',   'audiometry',      'periodic',       12,   'mandatory',   50
    UNION ALL SELECT 'AE-MAR-YARD',   'spirometry',      'periodic',       12,   'mandatory',   60
    UNION ALL SELECT 'AE-MAR-YARD',   'vision_acuity',   'periodic',       12,   'recommended', 70
    UNION ALL SELECT 'AE-MAR-YARD',   'physical_exam',   'exit',           NULL, 'recommended', 80
  ) x
  JOIN `oh_rule_templates` rt ON rt.code = x.tpl AND rt.version = 1
  JOIN `oh_exam_types` et ON et.code = x.ex;

-- Check:  SELECT COUNT(*) FROM oh_exam_types;      -- 26
--         SELECT COUNT(*) FROM oh_rule_templates;  -- 8
--         SELECT COUNT(*) FROM oh_template_items;  -- 52
