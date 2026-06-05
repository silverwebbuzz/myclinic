-- =====================================================================
-- 017_extend_existing_trials_to_30_days.sql
--
-- Context: the trial length changed from 14 → 30 days, but existing
-- clinics were registered under the old 14-day logic (created_at + 14).
-- This one-time backfill extends those still-active trials to 30 days
-- from their original signup date, so existing users get the full 30
-- the marketing now promises.
--
-- SAFE BY DESIGN — only updates rows that are:
--   * currently on a trial            (trial_ends_at IS NOT NULL)
--   * NOT paying                      (plan_expires_at IS NULL)
--   * whose trial has NOT yet expired (trial_ends_at >= today)
--   * and only when +30 from signup is LATER than the current end
--     (GREATEST guard => never shortens anyone's trial)
--
-- Idempotent: re-running changes nothing once trials already reach
-- created_at + 30 days.
-- =====================================================================

UPDATE tenants
   SET trial_ends_at = GREATEST(
           trial_ends_at,
           DATE_ADD(DATE(created_at), INTERVAL 30 DAY)
       )
 WHERE trial_ends_at   IS NOT NULL
   AND plan_expires_at IS NULL
   AND trial_ends_at  >= CURDATE();
