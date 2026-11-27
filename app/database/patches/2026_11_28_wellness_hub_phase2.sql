-- =====================================================================
-- Wellness Hub — Phase 2: health camps & events.   Plan: document/wellness-hub-plan.md
--
-- RUN AFTER: 2026_11_27_wellness_hub_phase1.sql
-- Fresh-install style (no foreign keys). Safe to run more than once.
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

-- 1. Categories (admin can rename / hide later; slugs are used in city URLs).
CREATE TABLE IF NOT EXISTS `wh_event_categories` (
    `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `kind`       ENUM('free_camp','event') NOT NULL,
    `slug`       VARCHAR(60)      NOT NULL,
    `name`       VARCHAR(80)      NOT NULL,
    `sort`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`  TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whec_kind_slug` (`kind`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `wh_event_categories` (`kind`, `slug`, `name`, `sort`) VALUES
('free_camp', 'general-checkup',      'General health checkup',        10),
('free_camp', 'eye-camp',             'Eye camp',                      20),
('free_camp', 'dental-camp',          'Dental camp',                   30),
('free_camp', 'blood-donation',       'Blood donation',                40),
('free_camp', 'vaccination',          'Vaccination drive',             50),
('free_camp', 'diabetes-bp',          'Diabetes & BP screening',       60),
('free_camp', 'cancer-screening',     'Cancer screening',              70),
('free_camp', 'womens-health',        'Women''s & maternal health',    80),
('free_camp', 'child-health',         'Child health',                  90),
('free_camp', 'bone-ortho',           'Bone & joint',                 100),
('free_camp', 'yoga-wellness',        'Yoga & wellness',              110),
('free_camp', 'awareness-talk',       'Health awareness talk',        120),
('event',     'health-expo',          'Health expo / fair',            10),
('event',     'workshop',             'Workshop',                      20),
('event',     'webinar',              'Webinar',                       30),
('event',     'conference',           'Conference',                    40),
('event',     'cme',                  'CME (for doctors)',             50),
('event',     'marathon-walkathon',   'Marathon / walkathon',          60),
('event',     'yoga-fitness',         'Yoga & fitness session',        70);

-- 2. Camps + events. "Ended" is not a status: lists hide rows whose end_at
--    has passed, detail pages show "This event has ended".
CREATE TABLE IF NOT EXISTS `wh_events` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creator_id`      BIGINT UNSIGNED NOT NULL,
    `kind`            ENUM('free_camp','event') NOT NULL,
    `category_slug`   VARCHAR(60)     NOT NULL,
    `title`           VARCHAR(160)    NOT NULL,
    `slug`            VARCHAR(200)    NOT NULL,                    -- frozen once first published (SEO)
    `summary`         VARCHAR(300)    DEFAULT NULL,
    `description`     TEXT            DEFAULT NULL,
    `poster_path`     VARCHAR(255)    DEFAULT NULL,                -- repo-root relative (storage/wellness/posters/…)
    `start_at`        DATETIME        NOT NULL,                    -- IST
    `end_at`          DATETIME        NOT NULL,
    `mode`            ENUM('in_person','online','hybrid') NOT NULL DEFAULT 'in_person',
    `venue_name`      VARCHAR(160)    DEFAULT NULL,
    `address`         VARCHAR(300)    DEFAULT NULL,
    `city_name`       VARCHAR(80)     DEFAULT NULL,
    `city_slug`       VARCHAR(80)     DEFAULT NULL,
    `state`           VARCHAR(80)     DEFAULT NULL,
    `pincode`         VARCHAR(10)     DEFAULT NULL,
    `map_url`         VARCHAR(500)    DEFAULT NULL,
    `online_url`      VARCHAR(500)    DEFAULT NULL,                -- shown to signed-in visitors only
    `fee_amount`      DECIMAL(10,2)   NOT NULL DEFAULT 0,          -- 0 = free; always 0 for free camps
    `fee_note`        VARCHAR(160)    DEFAULT NULL,                -- "Early bird ₹299 till 10 Dec"
    `join_mode`       ENUM('walk_in','call','register') NOT NULL DEFAULT 'walk_in',
    `register_url`    VARCHAR(500)    DEFAULT NULL,                -- signed-in only
    `contact_name`    VARCHAR(120)    DEFAULT NULL,
    `contact_phone`   VARCHAR(20)     DEFAULT NULL,                -- signed-in only
    `audience`        ENUM('all','women','children','seniors','doctors') NOT NULL DEFAULT 'all',
    `languages`       VARCHAR(120)    DEFAULT NULL,
    `organised_by`    VARCHAR(160)    DEFAULT NULL,                -- admin posts on behalf (eClinicPro Desk)
    `status`          ENUM('draft','submitted','changes_requested','rejected','published','unpublished','cancelled') NOT NULL DEFAULT 'draft',
    `review_note`     VARCHAR(1000)   DEFAULT NULL,
    `cancel_reason`   VARCHAR(300)    DEFAULT NULL,
    `submitted_at`    DATETIME        DEFAULT NULL,
    `reviewed_at`     DATETIME        DEFAULT NULL,
    `reviewed_by`     BIGINT UNSIGNED DEFAULT NULL,
    `published_at`    DATETIME        DEFAULT NULL,                -- first publish
    `view_count`      INT UNSIGNED    NOT NULL DEFAULT 0,
    `contact_views`   INT UNSIGNED    NOT NULL DEFAULT 0,          -- signed-in visitors who saw contact / register
    `is_deleted`      TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_whe_slug` (`slug`),
    KEY `idx_whe_public` (`status`, `is_deleted`, `kind`, `end_at`),
    KEY `idx_whe_city` (`city_slug`, `status`, `is_deleted`, `end_at`),
    KEY `idx_whe_creator` (`creator_id`, `is_deleted`, `status`),
    KEY `idx_whe_queue` (`status`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. "Report this listing" — from anyone (signed in or not).
CREATE TABLE IF NOT EXISTS `wh_item_reports` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_type`    ENUM('event','tip') NOT NULL,
    `item_id`      BIGINT UNSIGNED NOT NULL,
    `identity_id`  BIGINT UNSIGNED DEFAULT NULL,
    `reason`       ENUM('fake','hidden_fees','medical_claim','wrong_details','copyright','other') NOT NULL,
    `details`      VARCHAR(1000)   DEFAULT NULL,
    `ip`           VARCHAR(45)     DEFAULT NULL,
    `status`       ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    `admin_note`   VARCHAR(500)    DEFAULT NULL,
    `resolved_at`  DATETIME        DEFAULT NULL,
    `resolved_by`  BIGINT UNSIGNED DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_whr_status` (`status`, `created_at`),
    KEY `idx_whr_item` (`item_type`, `item_id`),
    KEY `idx_whr_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
