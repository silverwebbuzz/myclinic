-- =====================================================================
-- Health Tips — invite-only contributor marketplace (2026-10-09)
--
-- Friends / creators the admin trusts post recipes, exercises and quick tips.
-- Contributors are ordinary patient identities (OTP login on /patient); the
-- admin turns one into a contributor from /admin/signups/{id} or
-- /admin/health-tips/contributors. Every post is reviewed by the admin before
-- it goes public at /health-tips/{slug}.
--
--   health_tip_contributors  one row per identity granted contributor access
--   health_tips              the post (recipe / exercise / tip) + review state
--   health_tip_ingredients   recipe lines, ordered
--   health_tip_steps         method / exercise steps, ordered
--   health_tip_media         uploaded photos (sort 0 = cover)
--   health_tip_tags          diet / goal / meal / focus tags (seeded below)
--   health_tip_tag_map       post ↔ tag
--
-- Files live under repo-root storage/health_tips/{contributor_id}/ and are
-- served by api/health_tip_image.php (published posts only, or the owner).
--
-- Fresh-install style (no foreign keys), matching the patient-panel patches.
-- Safe to run more than once.
--   phpMyAdmin: select database → Import → this file ONLY.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `health_tip_contributors` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_id`        BIGINT UNSIGNED NOT NULL,                 -- patient_identities.id
    `slug`               VARCHAR(80)     NOT NULL,                 -- /health-tips/by/{slug}
    `display_name`       VARCHAR(120)    NOT NULL,
    `headline`           VARCHAR(120)    DEFAULT NULL,             -- "Protein-rich vegetarian recipes"
    `bio`                VARCHAR(600)    DEFAULT NULL,
    `avatar_path`        VARCHAR(255)    DEFAULT NULL,             -- public once they have a published post
    `instagram_handle`   VARCHAR(60)     DEFAULT NULL,             -- without the @
    `youtube_url`        VARCHAR(255)    DEFAULT NULL,
    `website_url`        VARCHAR(255)    DEFAULT NULL,
    `status`             ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `terms_accepted_at`  DATETIME        DEFAULT NULL,             -- contributor terms ticked (required before submitting)
    `terms_version`      VARCHAR(20)     DEFAULT NULL,
    `granted_by`         BIGINT UNSIGNED DEFAULT NULL,             -- super admin id
    `granted_at`         DATETIME        DEFAULT NULL,
    `admin_note`         VARCHAR(255)    DEFAULT NULL,             -- private, e.g. "Pooja — @paushtikpalette"
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_htc_identity` (`identity_id`),
    UNIQUE KEY `uq_htc_slug` (`slug`),
    KEY `idx_htc_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tips` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contributor_id`     BIGINT UNSIGNED NOT NULL,
    `type`               ENUM('recipe','exercise','tip') NOT NULL,
    `title`              VARCHAR(160)    NOT NULL,
    `slug`               VARCHAR(200)    NOT NULL,                 -- frozen once first published (SEO)
    `summary`            VARCHAR(300)    DEFAULT NULL,             -- card text + meta description
    `body`               TEXT            DEFAULT NULL,             -- intro / notes / the tip itself
    `video_url`          VARCHAR(255)    DEFAULT NULL,             -- Instagram reel or YouTube link (embedded, never hosted)
    -- recipe
    `servings`           TINYINT UNSIGNED  DEFAULT NULL,
    `prep_minutes`       SMALLINT UNSIGNED DEFAULT NULL,
    `cook_minutes`       SMALLINT UNSIGNED DEFAULT NULL,
    `protein_g`          DECIMAL(5,1)      DEFAULT NULL,           -- per serving, contributor's estimate
    `calories_kcal`      SMALLINT UNSIGNED DEFAULT NULL,           -- per serving, contributor's estimate
    -- exercise
    `duration_minutes`   SMALLINT UNSIGNED DEFAULT NULL,
    `difficulty`         ENUM('easy','moderate','hard') DEFAULT NULL,
    `reps_sets`          VARCHAR(80)     DEFAULT NULL,             -- "3 sets × 12 reps"
    `equipment`          VARCHAR(160)    DEFAULT NULL,
    `body_area`          VARCHAR(80)     DEFAULT NULL,
    `precautions`        VARCHAR(600)    DEFAULT NULL,             -- "Avoid if…" (exercise), shown prominently
    -- review workflow
    `status`             ENUM('draft','submitted','changes_requested','rejected','published','unpublished') NOT NULL DEFAULT 'draft',
    `review_note`        VARCHAR(1000)   DEFAULT NULL,             -- admin → contributor (shown in their studio)
    `submitted_at`       DATETIME        DEFAULT NULL,
    `reviewed_at`        DATETIME        DEFAULT NULL,
    `reviewed_by`        BIGINT UNSIGNED DEFAULT NULL,             -- super admin id
    `published_at`       DATETIME        DEFAULT NULL,             -- first publish; kept across unpublish/republish
    `view_count`         INT UNSIGNED    NOT NULL DEFAULT 0,
    `is_deleted`         TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ht_slug` (`slug`),
    KEY `idx_ht_public` (`status`, `is_deleted`, `published_at`),
    KEY `idx_ht_type_public` (`type`, `status`, `is_deleted`, `published_at`),
    KEY `idx_ht_contributor` (`contributor_id`, `is_deleted`, `status`),
    KEY `idx_ht_queue` (`status`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tip_ingredients` (
    `id`        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tip_id`    BIGINT UNSIGNED  NOT NULL,
    `sort`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `quantity`  VARCHAR(40)      DEFAULT NULL,                     -- "1 cup", "2 tbsp"
    `item`      VARCHAR(160)     NOT NULL,                         -- "roasted chana"
    PRIMARY KEY (`id`),
    KEY `idx_hti_tip` (`tip_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tip_steps` (
    `id`        BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tip_id`    BIGINT UNSIGNED  NOT NULL,
    `sort`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `body`      VARCHAR(1000)    NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_hts_tip` (`tip_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tip_media` (
    `id`              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tip_id`          BIGINT UNSIGNED  NOT NULL,
    `contributor_id`  BIGINT UNSIGNED  NOT NULL,
    `sort`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,            -- 0 = cover
    `file_path`       VARCHAR(255)     NOT NULL,                       -- relative to repo root
    `file_mime`       VARCHAR(60)      NOT NULL,
    `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_htm_tip` (`tip_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tip_tags` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(60)      NOT NULL,
    `name`        VARCHAR(60)      NOT NULL,
    `tag_group`   ENUM('diet','goal','meal','focus') NOT NULL,     -- focus = exercise focus
    `sort`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_htt_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `health_tip_tag_map` (
    `tip_id`  BIGINT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED    NOT NULL,
    PRIMARY KEY (`tip_id`, `tag_id`),
    KEY `idx_httm_tag` (`tag_id`, `tip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tags are descriptive, never therapeutic: no "cures", "for diabetes",
-- "thyroid remedy" etc. (same rule as the lab section — we don't make
-- health claims we can't stand behind).
INSERT IGNORE INTO `health_tip_tags` (`slug`, `name`, `tag_group`, `sort`) VALUES
    ('vegetarian',     'Vegetarian',      'diet', 10),
    ('vegan',          'Vegan',           'diet', 20),
    ('jain',           'Jain',            'diet', 30),
    ('eggetarian',     'Eggetarian',      'diet', 40),
    ('gluten-free',    'Gluten-free',     'diet', 50),
    ('dairy-free',     'Dairy-free',      'diet', 60),
    ('high-protein',   'High protein',    'goal', 10),
    ('high-fibre',     'High fibre',      'goal', 20),
    ('low-sugar',      'Low sugar',       'goal', 30),
    ('low-oil',        'Low oil',         'goal', 40),
    ('iron-rich',      'Iron rich',       'goal', 50),
    ('calcium-rich',   'Calcium rich',    'goal', 60),
    ('kids-friendly',  'Kids friendly',   'goal', 70),
    ('breakfast',      'Breakfast',       'meal', 10),
    ('lunch',          'Lunch',           'meal', 20),
    ('dinner',         'Dinner',          'meal', 30),
    ('snack',          'Snack',           'meal', 40),
    ('drink',          'Drink',           'meal', 50),
    ('dessert',        'Healthy dessert', 'meal', 60),
    ('strength',       'Strength',        'focus', 10),
    ('yoga',           'Yoga & stretching', 'focus', 20),
    ('cardio',         'Cardio',          'focus', 30),
    ('posture',        'Posture',         'focus', 40),
    ('desk-friendly',  'Desk friendly',   'focus', 50),
    ('beginner',       'Beginner',        'focus', 60);
