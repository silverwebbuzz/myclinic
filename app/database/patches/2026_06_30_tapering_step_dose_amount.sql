-- Tapering step schema extension (JSON in prescriptions.tapering_steps)
--
-- Each element in the tapering_steps JSON array now includes dose_amount:
--   [{"days":3,"preset":"1-0-1","food":"before","dose_amount":1.0}, ...]
--
-- dose_amount is per tapering step (may differ from the line-level dose_amount).
-- Backfill existing rows with:
--   php app/database/patches/2026_06_30_tapering_step_dose_amount.php
--
-- No ALTER needed — tapering_steps is already JSON/TEXT on prescriptions and
-- prescription_template_items.

SELECT 'tapering_steps JSON schema now includes dose_amount per step' AS info;
