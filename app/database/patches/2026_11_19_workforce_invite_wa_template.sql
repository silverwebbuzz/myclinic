-- =====================================================================
-- Workforce Health — WhatsApp template for the employee invitation (1A-4)
--
-- Sent only to numbers with a recorded opt-in (they verified an OTP with
-- eClinicPro before) and not opted out. Never cold-messages a number HR
-- typed in (that was the cause of the 2026 WABA ban).
--
-- Status starts 'draft'. Submit "corp_employee_invite" in Meta WhatsApp
-- Manager (category Utility, English), then set status to 'approved' in
-- /admin/messaging once Meta approves it. Until then no WhatsApp is sent.
--
-- Variable order:
--   {{1}} employee_name
--   {{2}} company_name
--   {{3}} link
-- Safe to run more than once.
-- =====================================================================

INSERT INTO `wa_templates`
    (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT
    'corp_employee_invite',
    'corp_employee_invite',
    'en',
    'utility',
    'Hello {{1}},\n\n{{2}} has added you to eClinicPro Workforce Health to arrange the health checks your job needs.\n\nPlease sign in with this mobile number and confirm it is you in the "Work health" tab:\n{{3}}\n\nYour own health records stay private to you. Not you? Choose "This isn\'t me" after signing in.\n\neClinicPro',
    '["employee_name","company_name","link"]',
    '',
    'draft',
    1
WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'corp_employee_invite');
