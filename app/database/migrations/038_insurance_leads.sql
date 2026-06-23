-- 038_insurance_leads.sql
-- Lead capture for the marketing Health Insurance page (/health-insurance).
--
-- The quote form there is phone-first (no login): a visitor picks gender,
-- members, ages & city, then leaves a name + mobile for an advisor call-back.
-- health-insurance.php's POST handler calls ecp_save_insurance_lead()
-- (partials/db.php), which INSERTs into this table, and also emails the
-- eClinicPro inbox (+ a confirmation to the lead if an email was given).
--
-- Mirrors demo_requests in spirit. The save helper still uses
-- CREATE TABLE IF NOT EXISTS as a runtime fallback, but this migration is the
-- canonical schema — run it on deploy so the table exists before any traffic
-- and never depends on the app DB user holding CREATE privilege.
--
-- Safe to re-run: CREATE IF NOT EXISTS only, no seed data.
-- phpMyAdmin: select your app database in the left sidebar first.

CREATE TABLE IF NOT EXISTS insurance_leads (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120)  NOT NULL,
    phone         VARCHAR(40)   NOT NULL,
    email         VARCHAR(160)  NULL,
    gender        VARCHAR(20)   NULL,
    cover_for     VARCHAR(160)  NULL,            -- comma-joined members, e.g. "Self, Spouse, Son"
    age_band      VARCHAR(20)   NULL,            -- eldest member band, e.g. "36–45"
    children_ages VARCHAR(120)  NULL,            -- comma-joined child ages, e.g. "3, 7"
    city          VARCHAR(120)  NULL,
    call_time     VARCHAR(40)   NULL,            -- preferred call-back window
    plan_interest VARCHAR(120)  NULL,            -- which plan/CTA the lead clicked
    source        VARCHAR(40)   NOT NULL DEFAULT 'website',
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
