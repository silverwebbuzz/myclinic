-- =============================================================
-- eClinicPro — WhatsApp/SMS messaging migrations
-- =============================================================
-- CENTRALIZED design: NO new patient/booking/doctor entity tables.
--   - Non-joined-doctor bookings reuse `directory_leads`
--   - Joined-clinic bookings reuse `appointments`
--   - Patient link stays on `patient_identities` (global, by phone)
--   - WhatsApp-capable status cached on identities + directory_doctors
-- Only 2 genuinely new tables, both config/registry (not entities):
--   - platform_settings  (creds, key/value)
--   - wa_templates       (Meta template registry)
--
-- Run block by block. Each guarded / idempotent where possible.
-- =============================================================

-- USE silverwebbuzz_in_myclinic;

-- =============================================================
-- Idempotency note — this is MariaDB (10.9).
-- =============================================================
-- MariaDB supports native ADD COLUMN IF NOT EXISTS / ADD INDEX IF NOT EXISTS
-- and CREATE TABLE IF NOT EXISTS, so the whole file is safe to re-run after a
-- partial run — no stored procedures needed.


-- =============================================================
-- BLOCK 1 — platform_settings (admin-editable creds; no redeploy to start WA)
-- =============================================================
-- Rollback: DROP TABLE platform_settings;

CREATE TABLE IF NOT EXISTS platform_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT DEFAULT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the keys empty; fill them from /admin/messaging.
INSERT INTO platform_settings (setting_key, setting_value, is_secret) VALUES
  ('messaging_enabled',       '0',  0),
  ('wa_access_token',         NULL, 1),
  ('wa_phone_number_id',      NULL, 0),
  ('wa_business_id',          NULL, 0),
  ('wa_webhook_verify_token', NULL, 1),
  ('wa_app_secret',           NULL, 1),
  ('sms_provider',            'msg91', 0),
  ('sms_auth_key',            NULL, 1),
  ('sms_sender_id',           NULL, 0)
ON DUPLICATE KEY UPDATE setting_key = setting_key;  -- no-op if already seeded


-- =============================================================
-- (further blocks appended as we build each item)
-- =============================================================

-- =============================================================
-- BLOCK 2 — wa_templates (Meta template registry, admin-managed)
-- =============================================================
-- Rollback: DROP TABLE wa_templates;

CREATE TABLE IF NOT EXISTS wa_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(60) NOT NULL UNIQUE,
  meta_name VARCHAR(120) NOT NULL,
  language VARCHAR(10) NOT NULL DEFAULT 'en',
  category ENUM('utility','marketing','authentication') NOT NULL DEFAULT 'utility',
  body_text TEXT NOT NULL,
  variables JSON DEFAULT NULL,
  sms_fallback_text TEXT DEFAULT NULL,
  status ENUM('draft','submitted','approved','rejected','paused') NOT NULL DEFAULT 'draft',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the 8 transactional templates as draft (utility category).
-- {{1}}.. are positional Meta variables; sms_fallback_text is the plain
-- version sent if WhatsApp delivery fails.
INSERT INTO wa_templates
  (template_key, meta_name, language, category, body_text, variables, sms_fallback_text, status)
VALUES
  ('patient_request_sent','patient_request_sent','en','utility',
   'Hi {{1}}, your appointment request with {{2}} for {{3}} has been sent. They will confirm shortly. You can also call directly: {{4}}',
   '["patient_name","doctor_name","datetime","clinic_phone"]',
   'Hi {{1}}, request sent to {{2}} for {{3}}. Call directly: {{4}} — eClinicPro', 'draft'),

  ('doctor_new_lead','doctor_new_lead','en','utility',
   'New patient request via eClinicPro: {{1}} for {{2}} ({{3}}). View & confirm: {{4}}',
   '["patient_name","datetime","reason","link"]',
   'New patient {{1}} for {{2}}. Confirm: {{4}} — eClinicPro', 'draft'),

  ('patient_confirmed','patient_confirmed','en','utility',
   'Good news {{1}} — {{2}} confirmed your appointment for {{3}}. Clinic: {{4}}',
   '["patient_name","doctor_name","datetime","clinic_phone"]',
   '{{2}} confirmed your appointment for {{3}}. Clinic: {{4}} — eClinicPro', 'draft'),

  ('patient_soft_nudge','patient_soft_nudge','en','utility',
   'Hi {{1}}, your request with {{2}} is still awaiting confirmation. You can call them directly: {{3}}',
   '["patient_name","doctor_name","clinic_phone"]',
   'Hi {{1}}, {{2}} not confirmed yet. Call directly: {{3}} — eClinicPro', 'draft'),

  ('appointment_reminder','appointment_reminder','en','utility',
   'Reminder {{1}}: your appointment with {{2}} is today at {{3}}. Clinic: {{4}}',
   '["patient_name","doctor_name","time","clinic_phone"]',
   'Reminder: appointment with {{2}} today at {{3}}. Clinic: {{4}} — eClinicPro', 'draft'),

  ('prescription_ready','prescription_ready','en','utility',
   'Hi {{1}}, your prescription from {{2}} is ready: {{3}}',
   '["patient_name","doctor_name","link"]',
   'Hi {{1}}, prescription from {{2}}: {{3}} — eClinicPro', 'draft'),

  ('diet_plan_shared','diet_plan_shared','en','utility',
   'Hi {{1}}, {{2}} shared a diet plan with you: {{3}}',
   '["patient_name","doctor_name","link"]',
   'Hi {{1}}, diet plan from {{2}}: {{3}} — eClinicPro', 'draft'),

  ('follow_up_reminder','follow_up_reminder','en','utility',
   'Hi {{1}}, this is a follow-up reminder from {{2}} ({{3}}). Book again: {{4}}',
   '["patient_name","doctor_name","reason","clinic_phone"]',
   'Hi {{1}}, follow-up reminder from {{2}}. Call: {{4}} — eClinicPro', 'draft'),

  -- Legacy template-key aliases used by existing portal callers
  -- (VisitService/DietService use 'rx_delivery'; AppointmentService uses
  --  'appointment_reminder' which is already seeded above). These keep the
  --  old call sites working as first-class approved templates.
  ('rx_delivery','rx_delivery','en','utility',
   'Hi {{1}}, your prescription from {{2}} is ready: {{3}}',
   '["patient_name","clinic_name","rx_url"]',
   'Hi {{1}}, prescription from {{2}}: {{3}} — eClinicPro', 'draft')
ON DUPLICATE KEY UPDATE template_key = template_key;


-- =============================================================
-- BLOCK 3 — notifications: delivery + fallback tracking
-- =============================================================
-- Rollback: ALTER TABLE notifications DROP COLUMN provider_message_id,
--   DROP COLUMN fallback_of, DROP COLUMN delivery_status, DROP COLUMN delivered_at;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS provider_message_id VARCHAR(120) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS fallback_of BIGINT UNSIGNED NULL AFTER provider_message_id,
  ADD COLUMN IF NOT EXISTS delivery_status VARCHAR(30) NULL AFTER fallback_of,
  ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL AFTER delivery_status,
  ADD INDEX IF NOT EXISTS idx_notif_provider_msg (provider_message_id),
  ADD INDEX IF NOT EXISTS idx_notif_status_sched (status, scheduled_at);


-- =============================================================
-- BLOCK 4 — directory_leads: lead lifecycle (NON-joined-doctor bookings)
-- =============================================================
-- Reuses the existing `view_token` as the L/{token} confirm link.
-- Adds the 'book_confirmed' state + confirm/nudge timestamps.
-- Rollback: revert the enum + DROP the added columns.

-- The enum MODIFY is idempotent (re-running sets the same set).
ALTER TABLE directory_leads
  MODIFY COLUMN type ENUM('view','book_intent','book_submitted','book_confirmed','call') NOT NULL,
  ADD COLUMN IF NOT EXISTS confirmed_at DATETIME NULL AFTER doctor_contacted_patient,
  ADD COLUMN IF NOT EXISTS soft_nudge_sent_at DATETIME NULL AFTER confirmed_at,
  ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME NULL AFTER soft_nudge_sent_at;


-- =============================================================
-- BLOCK 5 — appointments: WhatsApp confirm link + soft nudge
-- =============================================================
-- appointments already has source='whatsapp', status lifecycle, reminder_sent.
-- We add only a confirm token + soft-nudge timestamp.
-- Rollback: DROP both columns.

ALTER TABLE appointments
  ADD COLUMN IF NOT EXISTS confirm_token VARCHAR(32) NULL AFTER token_number,
  ADD COLUMN IF NOT EXISTS soft_nudge_sent_at DATETIME NULL AFTER reminder_sent,
  ADD INDEX IF NOT EXISTS idx_appt_confirm_token (confirm_token);


-- =============================================================
-- BLOCK 6 — WhatsApp-capable cache (3-state) — centralized
-- =============================================================
-- On patient_identities (global, by phone → known across ALL clinics)
-- and directory_doctors (doctor side). 'unknown'|'yes'|'no' + last-checked.
-- Updated for free by the delivery-status webhook (no extra API calls).
-- Re-probe 'no' after 90 days (people install WhatsApp later).
-- Rollback: DROP the columns on both tables.

ALTER TABLE patient_identities
  ADD COLUMN IF NOT EXISTS whatsapp_status ENUM('unknown','yes','no') NOT NULL DEFAULT 'unknown' AFTER phone_verified_at,
  ADD COLUMN IF NOT EXISTS whatsapp_checked_at DATETIME NULL AFTER whatsapp_status;

ALTER TABLE directory_doctors
  ADD COLUMN IF NOT EXISTS whatsapp_status ENUM('unknown','yes','no') NOT NULL DEFAULT 'unknown' AFTER phone,
  ADD COLUMN IF NOT EXISTS whatsapp_checked_at DATETIME NULL AFTER whatsapp_status;


-- =============================================================
-- BLOCK 7 — CENTRALIZATION FIX: link every notification to the global identity
-- =============================================================
-- notifications.patient_id points at the per-clinic `patients` table, but a
-- directory lead (non-joined doctor) only has a GLOBAL patient_identity_id.
-- Adding patient_identity_id means EVERY message (marketing or portal) ties to
-- the one global person → when a non-joined doctor later joins, message history
-- resolves to the same identity. No mismatch anywhere.
-- Rollback: ALTER TABLE notifications DROP COLUMN patient_identity_id;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS patient_identity_id BIGINT(20) UNSIGNED NULL AFTER patient_id,
  ADD INDEX IF NOT EXISTS idx_notif_identity (patient_identity_id);


-- =============================================================
-- BLOCK 8 — messaging_rules: per (audience, event, plan_tier) control
-- =============================================================
-- The cost-control grid. Each row decides, for a given audience + event +
-- plan tier: which channel fires (whatsapp/sms/push/off) and how often
-- (per-day / per-week / per-month caps; NULL = unlimited within clinic quota).
-- Platform-admin edits this at /admin/messaging. One plan, but trial vs paid
-- still distinguished so free trials can be throttled to cheap/off.
-- Rollback: DROP TABLE messaging_rules;

CREATE TABLE IF NOT EXISTS messaging_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  audience ENUM('patient','doctor') NOT NULL,
  event_key VARCHAR(60) NOT NULL,             -- matches template_key / lead events
  plan_tier ENUM('trial','paid') NOT NULL,
  channel ENUM('whatsapp','sms','push','off') NOT NULL DEFAULT 'off',
  per_day_cap SMALLINT UNSIGNED NULL,         -- NULL = no per-event/day cap
  per_week_cap SMALLINT UNSIGNED NULL,
  per_month_cap SMALLINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_rule (audience, event_key, plan_tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sensible defaults. Trial = cheap/off; Paid = WhatsApp.
-- (event_key matches wa_templates.template_key + lead/doctor events.)
INSERT INTO messaging_rules (audience, event_key, plan_tier, channel, per_day_cap, per_month_cap) VALUES
  -- Patient — paid clinics get WhatsApp, trial gets SMS or off
  ('patient','patient_request_sent','trial','sms',  NULL, NULL),
  ('patient','patient_request_sent','paid', 'whatsapp', NULL, NULL),
  ('patient','patient_confirmed',   'trial','sms',  NULL, NULL),
  ('patient','patient_confirmed',   'paid', 'whatsapp', NULL, NULL),
  ('patient','patient_soft_nudge',  'trial','off',  NULL, NULL),
  ('patient','patient_soft_nudge',  'paid', 'sms',  1, NULL),
  ('patient','appointment_reminder','trial','sms',  1, NULL),
  ('patient','appointment_reminder','paid', 'whatsapp', 1, NULL),
  ('patient','prescription_ready',  'trial','off',  NULL, NULL),
  ('patient','prescription_ready',  'paid', 'whatsapp', NULL, NULL),
  ('patient','diet_plan_shared',    'trial','off',  NULL, NULL),
  ('patient','diet_plan_shared',    'paid', 'whatsapp', NULL, NULL),
  ('patient','follow_up_reminder',  'trial','off',  NULL, NULL),
  ('patient','follow_up_reminder',  'paid', 'whatsapp', NULL, 4),
  -- Doctor — lead alerts matter even on trial (that's the conversion hook)
  ('doctor','doctor_new_lead',      'trial','whatsapp', NULL, NULL),
  ('doctor','doctor_new_lead',      'paid', 'whatsapp', NULL, NULL),
  ('doctor','quota_warning',        'trial','sms',  1, 2),
  ('doctor','quota_warning',        'paid', 'sms',  1, 2)
ON DUPLICATE KEY UPDATE event_key = event_key;


-- =============================================================
-- BLOCK 9 — messaging_quota: per-clinic monthly allowance + add-on top-ups
-- =============================================================
-- One plan = common base quota for every clinic (editable in admin).
-- whatsapp_limit / sms_limit = included in the ₹1,499 plan.
-- *_addon = top-up purchased when base runs out.
-- *_used  = counters for the current period (reset monthly by cron).
-- Seeded default: 300 WhatsApp + 300 SMS (~₹90/clinic/mo cost; tune in admin).
-- Rollback: DROP TABLE messaging_quota;

CREATE TABLE IF NOT EXISTS messaging_quota (
  clinic_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY,
  period_ym CHAR(7) NOT NULL,                 -- 'YYYY-MM' of the current window
  whatsapp_limit INT NOT NULL DEFAULT 300,
  sms_limit INT NOT NULL DEFAULT 300,
  whatsapp_addon INT NOT NULL DEFAULT 0,
  sms_addon INT NOT NULL DEFAULT 0,
  whatsapp_used INT NOT NULL DEFAULT 0,
  sms_used INT NOT NULL DEFAULT 0,
  quota_warned_at TIMESTAMP NULL,             -- so we send the "finished" alert once
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_mq_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default base quota also lives in platform_settings so the value is editable
-- in one place and applied to new clinics.
INSERT INTO platform_settings (setting_key, setting_value, is_secret) VALUES
  ('quota_whatsapp_base', '300', 0),
  ('quota_sms_base',      '300', 0),
  ('messaging_quiet_start','21',  0),   -- 9 PM
  ('messaging_quiet_end',  '7',   0),   -- 7 AM
  ('messaging_global_monthly_cap', '0', 0)  -- 0 = no global cap; set to throttle platform-wide
ON DUPLICATE KEY UPDATE setting_key = setting_key;


-- =============================================================
-- BLOCK 10 — messaging_usage_log: per-message tally (enforcement + spend view)
-- =============================================================
-- One row per send attempt that consumed quota. Drives the admin spend view
-- and the per-event frequency caps in messaging_rules.
-- Rollback: DROP TABLE messaging_usage_log;

CREATE TABLE IF NOT EXISTS messaging_usage_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  notification_id BIGINT(20) UNSIGNED NULL,
  audience ENUM('patient','doctor') NOT NULL,
  event_key VARCHAR(60) NOT NULL,
  channel ENUM('whatsapp','sms','push') NOT NULL,
  period_ym CHAR(7) NOT NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mul_clinic_period (clinic_id, period_ym, channel),
  INDEX idx_mul_event (clinic_id, event_key, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- Sanity checks
-- =============================================================
-- SELECT COUNT(*) FROM platform_settings;                 -- 14 (9 + 5 quota/quiet)
-- SELECT COUNT(*) FROM wa_templates WHERE status='draft'; -- 8
-- SELECT COUNT(*) FROM messaging_rules;                   -- 18
-- SHOW COLUMNS FROM notifications LIKE 'patient_identity_id';
-- SHOW COLUMNS FROM notifications LIKE 'provider_message_id';
-- SHOW COLUMNS FROM directory_leads LIKE 'confirmed_at';
-- SHOW COLUMNS FROM appointments LIKE 'confirm_token';
-- SHOW COLUMNS FROM patient_identities LIKE 'whatsapp_status';
-- SHOW COLUMNS FROM directory_doctors LIKE 'whatsapp_status';
-- SHOW TABLES LIKE 'messaging_%';                          -- 3 tables
