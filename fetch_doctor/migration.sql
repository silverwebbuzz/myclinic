-- =====================================================================
-- directory_doctors — one-time table setup
--
-- Run this once in phpMyAdmin → SQL tab
-- (or: mysql -u USER -p DB_NAME < migration.sql)
--
-- If you already ran an older version, this script also includes
-- ALTER statements at the bottom that add the new columns safely.
-- =====================================================================

CREATE TABLE IF NOT EXISTS directory_doctors (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Identity + source
    place_id        VARCHAR(180) NOT NULL,                       -- Google Place ID (unique dedup key)
    source          ENUM('google','self','manual') NOT NULL DEFAULT 'google',
    is_claimed      TINYINT(1) NOT NULL DEFAULT 0,               -- 1 once a clinic claims their profile
    claimed_tenant_id BIGINT UNSIGNED NULL,                      -- soft link to tenants.id

    -- Basic
    name            VARCHAR(255) NOT NULL,
    specialty       VARCHAR(40)  NULL,
    country         CHAR(2)      NOT NULL DEFAULT 'IN',
    state           VARCHAR(80)  NULL,
    city            VARCHAR(80)  NOT NULL,
    area            VARCHAR(120) NULL,
    address         TEXT NULL,
    lat             DECIMAL(10,7) NULL,
    lng             DECIMAL(10,7) NULL,
    plus_code       VARCHAR(40)  NULL,

    -- Contact
    phone           VARCHAR(40)  NULL,
    intl_phone      VARCHAR(40)  NULL,
    website         VARCHAR(255) NULL,
    gmaps_url       VARCHAR(255) NULL,

    -- Business signals from Google
    status          ENUM('OPERATIONAL','CLOSED_TEMPORARILY','CLOSED_PERMANENTLY') NOT NULL DEFAULT 'OPERATIONAL',
    rating          DECIMAL(3,2) NULL,
    reviews         INT UNSIGNED NOT NULL DEFAULT 0,
    price_level     TINYINT NULL,                                -- Google's 0-4 (rarely set for healthcare)
    last_review_at  TIMESTAMP NULL,                              -- timestamp of most recent review (for staleness)
    types           JSON NULL,
    opening_hours   JSON NULL,                                   -- weekday_text[]

    -- Photo (NEVER store the photo bytes; just the reference. Build URL at display time.)
    photo_reference VARCHAR(500) NULL,

    -- Self-submitted fields (filled in only when a clinic claims their profile)
    consultation_fee         DECIMAL(10,2) NULL,
    consultation_fee_currency CHAR(3)       NULL,
    doctor_name              VARCHAR(160)  NULL,                 -- separate from clinic name
    bio                      TEXT          NULL,
    languages                JSON          NULL,                 -- ["English","Hindi"]

    -- Quality + lifecycle
    quality_score   SMALLINT NULL,                               -- computed at import; higher = better
    is_active       TINYINT(1) NOT NULL DEFAULT 1,               -- soft-disable (don't show on /find-a-doctor)
    dropped_reason  VARCHAR(80) NULL,                            -- why we de-activated (low_reviews, no_hours, ...)

    fetched_at      TIMESTAMP NULL,                              -- when Google was last queried
    refreshed_at    TIMESTAMP NULL,                              -- when this DB row was last touched
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_place (place_id),
    KEY idx_country_state_city (country, state, city),
    KEY idx_specialty (specialty),
    KEY idx_quality (is_active, quality_score),
    KEY idx_claimed (is_claimed),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
-- IF YOU ALREADY HAVE AN OLDER VERSION of this table, run these instead
-- (they're harmless if columns already exist):
-- =====================================================================

-- ALTER TABLE directory_doctors
--     ADD COLUMN IF NOT EXISTS photo_reference VARCHAR(500) NULL AFTER opening_hours,
--     ADD COLUMN IF NOT EXISTS price_level TINYINT NULL AFTER reviews,
--     ADD COLUMN IF NOT EXISTS last_review_at TIMESTAMP NULL AFTER price_level,
--     ADD COLUMN IF NOT EXISTS consultation_fee DECIMAL(10,2) NULL AFTER photo_reference,
--     ADD COLUMN IF NOT EXISTS consultation_fee_currency CHAR(3) NULL AFTER consultation_fee,
--     ADD COLUMN IF NOT EXISTS doctor_name VARCHAR(160) NULL AFTER consultation_fee_currency,
--     ADD COLUMN IF NOT EXISTS bio TEXT NULL AFTER doctor_name,
--     ADD COLUMN IF NOT EXISTS languages JSON NULL AFTER bio,
--     ADD COLUMN IF NOT EXISTS quality_score SMALLINT NULL AFTER languages,
--     ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER quality_score,
--     ADD COLUMN IF NOT EXISTS dropped_reason VARCHAR(80) NULL AFTER is_active,
--     MODIFY COLUMN name VARCHAR(255) NOT NULL,
--     ADD INDEX IF NOT EXISTS idx_quality (is_active, quality_score);
