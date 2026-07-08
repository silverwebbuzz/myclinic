-- ============================================================================
-- WhatsApp OTP template for Doctor registration (/register).
--
-- Naming convention (collision-safe):
--   auth_doctor_register_otp
--     ^domain ^actor  ^flow   ^purpose
--
-- Variable order:
--   {{1}} code
--
-- NOTE: WhatsAppService requires templates to be APPROVED before sending.
-- Create/approve the template in Meta Business Manager, then set status='approved'
-- in wa_templates (via /admin/messaging or SQL).
-- ============================================================================

INSERT INTO `wa_templates`
    (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT
    'auth_doctor_register_otp',
    'auth_doctor_register_otp',
    'en',
    'authentication',
    '{{1}} is your verification code. For your security, do not share this code.',
    '["code"]',
    '{{1}} is your verification code. For your security, do not share this code.',
    'draft',
    1
WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'auth_doctor_register_otp');

