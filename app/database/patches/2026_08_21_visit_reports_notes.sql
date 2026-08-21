-- Lab / investigation findings captured next to the clinical notes on the
-- visit screen ("Reports - notes"). Separate from clinical_notes so reports
-- can be surfaced and printed on their own later.
ALTER TABLE `visits`
    ADD COLUMN `reports_notes` TEXT NULL DEFAULT NULL AFTER `clinical_notes`;
