-- 031_tenants_plan_fk.sql
-- Make tenants.plan reference the plans catalog (migration 030).
--
-- tenants.plan was an ENUM ('free','clinic','practice','enterprise') later
-- collapsed to ENUM('standard'). That can't hold arbitrary plan_ids and can't
-- be a foreign key. This converts it to VARCHAR(40) matching plans.plan_id and
-- adds an FK so a tenant can only sit on a plan that actually exists.
--
-- Order matters: widen the column → remap orphaned values → add the FK last
-- (the constraint fails if any tenant points at a missing plan_id).
-- Safe to re-run: column type change is idempotent; FK is guarded.
-- phpMyAdmin: select your app database in the left sidebar first.

-- 1. Convert ENUM → VARCHAR(40), same charset/collation as plans.plan_id so
--    the FK is allowed. utf8mb4 matches both tables (ENGINE=InnoDB).
ALTER TABLE tenants
  MODIFY COLUMN plan VARCHAR(40)
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci
  NOT NULL DEFAULT 'standard';

-- 2. Remap any tenant whose plan isn't in the catalog (retired tiers like
--    clinic/practice/enterprise, or blanks) to the surviving paid plan.
--    Without this, step 3's FK can't be created — it rejects orphan rows.
--    The only valid values after this are 'free' and 'standard'.
UPDATE tenants SET plan = 'standard'
 WHERE plan NOT IN (SELECT plan_id FROM plans);

-- 3. Add the foreign key (only if it isn't already there). ON UPDATE CASCADE
--    so renaming a plan_id propagates; ON DELETE RESTRICT so a plan with
--    tenants on it can't be deleted out from under them (matches the guard in
--    PlanAdminController::delete()).
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tenants'
              AND CONSTRAINT_NAME = 'fk_tenants_plan');
SET @s := IF(@fk = 0,
  'ALTER TABLE tenants
     ADD CONSTRAINT fk_tenants_plan FOREIGN KEY (plan)
     REFERENCES plans (plan_id) ON UPDATE CASCADE ON DELETE RESTRICT',
  'SELECT 1');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Verify:
SELECT plan, COUNT(*) AS tenants FROM tenants GROUP BY plan;
