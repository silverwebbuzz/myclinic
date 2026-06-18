-- 030_plans_table.sql
-- A real `plans` catalog so pricing tiers can be managed from /admin/plans
-- instead of hand-editing config/plans.php on the server.
--
-- PlanService reads this table first and falls back to config/plans.php when
-- the table is empty or missing, so onboarding/checkout never break.
--
-- Columns mirror the config keys 1:1. *_inr fields hold INR (the config's
-- *_usd names were legacy; values were always INR). modules/highlights/limits
-- are JSON. `modules` may be the string "all_paid" (resolved by PlanService)
-- or a JSON array of module ids.
--
-- Safe to re-run: CREATE IF NOT EXISTS + INSERT IGNORE seed.
-- phpMyAdmin: select your app database in the left sidebar first.

CREATE TABLE IF NOT EXISTS plans (
    plan_id       VARCHAR(40)   NOT NULL,
    name          VARCHAR(80)   NOT NULL,
    tagline       VARCHAR(160)  NULL,
    monthly_inr   DECIMAL(10,2) NOT NULL DEFAULT 0,
    yearly_inr    DECIMAL(10,2) NOT NULL DEFAULT 0,
    seat_limit    SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    patient_limit INT UNSIGNED  NULL,
    trial_days    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    modules       JSON          NULL,
    highlights    JSON          NULL,
    limits        JSON          NULL,
    featured      TINYINT(1)    NOT NULL DEFAULT 0,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order    SMALLINT      NOT NULL DEFAULT 100,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (plan_id),
    KEY idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed from the current config/plans.php so behaviour is unchanged on day one.
-- Two-plan model: a Free starter and the paid Clinic plan (₹16,000/year).
-- The paid plan keeps plan_id='standard' (what checkout, the gateway, and
-- tenants.plan already reference) but is labelled "Clinic".
-- INSERT IGNORE: re-running won't clobber edits made in the admin UI.
INSERT IGNORE INTO plans
    (plan_id, name, tagline, monthly_inr, yearly_inr, seat_limit, patient_limit, trial_days, modules, highlights, limits, featured, is_active, sort_order)
VALUES
    ('standard', 'Clinic', 'Everything to run your clinic', 1499, 16000, 255, NULL, 30,
        '"all_paid"',
        '["Patient records, visits, prescriptions","Appointments + walk-in queue","Billing & GST invoicing","Teleconsultation built in","Unlimited patients & users"]',
        '[]', 1, 1, 10),
    ('free', 'Free', 'Solo practice starter', 0, 0, 2, 100, 0,
        '["patients","appointments_basic","invoicing_basic"]',
        '["Patient management (100 patients)","Basic appointments & queue","Basic invoicing","2 team seats"]',
        '["No WhatsApp reminders","No EMR / prescriptions"]', 0, 1, 20);

-- Cleanup for DBs already seeded with the old 5-plan set: label the paid plan
-- "Clinic" and drop the unused tiers. Idempotent — safe to re-run.
UPDATE plans SET name = 'Clinic' WHERE plan_id = 'standard';
DELETE FROM plans WHERE plan_id IN ('clinic', 'practice', 'enterprise');

-- Verify:
SELECT plan_id, name, monthly_inr, yearly_inr, seat_limit, is_active FROM plans ORDER BY sort_order;
