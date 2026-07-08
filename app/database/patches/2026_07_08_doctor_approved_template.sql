-- ============================================================================
-- Doctor approval WhatsApp template (single source of truth).
--
-- Removes legacy variants and inserts the active template key:
--   doctor_approved
--
-- Variable order:
--   {{1}} doctor_name
--   {{2}} login_url
--   {{3}} support_phone
-- ============================================================================

INSERT INTO `wa_templates`
    (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT
    'doctor_approved',
    'doctor_approved',
    'en',
    'utility',
    '👋 Hello {{1}},\n\n🎉 Your eClinicPro account has been successfully approved and activated.\n\n🚀 You can now sign in to your clinic portal:\n🔗 {{2}}\n\n📅 Please let us know your preferred date and time for a short demo session, and our team will arrange it accordingly.\n\n📞 Need help? Connect with us on WhatsApp or call: {{3}}\n\nBest regards,\n💙 The eClinicPro Team',
    '["doctor_name","login_url","support_phone"]',
    'Hello {{1}}, your eClinicPro account is approved. Portal: {{2}}. Help: {{3}}',
    'draft',
    1
WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'doctor_approved');

