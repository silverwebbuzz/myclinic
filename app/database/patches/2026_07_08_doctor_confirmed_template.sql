-- ============================================================================
-- Replace doctor_approved + doctor_credentials with doctor_confirmed.
--
-- Sent when an admin approves a doctor's listing/claim request.
-- Variable order MUST match the payload DoctorClaimService passes:
--   {{1}} doctor_name, {{2}} registered_email
--
-- Run once:
--   mysql -u USER -p DB < app/database/patches/2026_07_08_doctor_confirmed_template.sql
-- ============================================================================

DELETE FROM `wa_templates`
WHERE `template_key` IN ('doctor_approved', 'doctor_credentials');

INSERT INTO `wa_templates`
    (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT
    'doctor_confirmed',
    'doctor_confirmed',
    'en',
    'utility',
    'Hello {{1}}, your eClinicPro account has been set up successfully. 🎉\n\nYour sign-in details have been sent to your registered email address ({{2}}).\n\nPlease use the information in that email to sign in to eClinicPro.\n\nIf you can''t find the email, please check your Spam or Junk folder.',
    '["doctor_name","registered_email"]',
    'Hello {{1}}, your eClinicPro account is ready. Sign-in details were sent to {{2}}. Check Spam/Junk if you don''t see it. — eClinicPro',
    'draft',
    1
WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'doctor_confirmed');
