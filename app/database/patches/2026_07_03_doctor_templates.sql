-- ============================================================================
-- Two new wa_templates rows:
--   1. doctor_approved     — sent when an admin APPROVES a doctor's claim.
--                            (The code already calls WhatsAppService::send(...,
--                            'doctor_approved', ...) in DoctorClaimService, but
--                            no template row existed → "no approved WhatsApp
--                            template for 'doctor_approved' — send held".)
--   2. doctor_credentials  — sends the doctor their LOGIN URL + username +
--                            temporary password (doctors have no email; they
--                            log in and change the password).
--
-- Variable order MUST match the payload the app passes:
--   doctor_approved:   {{1}} doctor_name, {{2}} clinic_name, {{3}} phone, {{4}} login_url
--   doctor_credentials:{{1}} doctor_name, {{2}} login_url, {{3}} username, {{4}} password
--
-- STATUS: both inserted as 'draft'. WhatsApp will NOT send them until:
--   (a) you create the matching template in Meta Business Manager, AND
--   (b) you set this row's status to 'approved' (via /admin/messaging or SQL).
-- Until then the system uses the sms_fallback_text over SMS.
--
-- ⚠️ doctor_credentials is NOT wired into the code yet — the current approval
-- flow is passwordless (phone OTP), so nothing generates a username/password or
-- calls this template. This row makes the template READY; sending it needs a
-- code change (generate temp password + call WhatsAppService::send(...,
-- 'doctor_credentials', ...)). See DoctorClaimService::notify* .
--
-- Idempotent: uses INSERT ... ON DUPLICATE KEY UPDATE keyed on template_key so
-- re-running won't duplicate. (Assumes a UNIQUE index on template_key; if not,
-- the WHERE-NOT-EXISTS variant below is safe either way.)
--
-- Run once:
--   mysql -u USER -p DB < app/database/patches/2026_07_03_doctor_templates.sql
-- ============================================================================

-- 1) doctor_approved ---------------------------------------------------------
INSERT INTO `wa_templates`
    (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT
    'doctor_approved',
    'doctor_approved',
    'en',
    'utility',
    'Hello {{1}}, your eClinicPro clinic panel for {{2}} is now active. ✅ Sign in at {{3}} using your phone {{4}} — we send a one-time code, no password needed.',
    '["doctor_name","clinic_name","login_url","phone"]',
    'Hello {{1}}, your eClinicPro panel for {{2}} is active. Sign in at {{3}} with phone {{4}} (one-time code). — eClinicPro',
    'draft',
    1
WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'doctor_approved');

-- 2) doctor_credentials ------------------------------------------------------
-- Category 'authentication' because it delivers a password — Meta handles
-- credential templates under authentication and rejects marketing/utility ones
-- that contain passwords.
INSERT INTO `wa_templates`
    (`template_key`, `meta_name`, `language`, `category`, `body_text`, `variables`, `sms_fallback_text`, `status`, `is_active`)
SELECT
    'doctor_credentials',
    'doctor_credentials',
    'en',
    'authentication',
    'Hello {{1}}, your eClinicPro login is ready. 🔐 URL: {{2}} Username: {{3}} Temporary password: {{4}} Please sign in and change your password immediately.',
    '["doctor_name","login_url","username","password"]',
    'eClinicPro login — URL: {{2}} Username: {{3}} Temp password: {{4}} Please change it after signing in. — eClinicPro',
    'draft',
    1
WHERE NOT EXISTS (SELECT 1 FROM `wa_templates` WHERE `template_key` = 'doctor_credentials');
