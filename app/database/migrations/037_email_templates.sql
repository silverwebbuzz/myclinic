-- 037_email_templates.sql
-- Admin-editable email templates. The application code (MailService) holds
-- the built-in defaults; a row here OVERRIDES the default content for that
-- template_key when is_active = 1. Empty/absent rows => code defaults apply,
-- so this table is safe to leave empty.
--
-- Content uses {{placeholder}} tokens filled from the send payload at render
-- time (e.g. {{doctor_name}}, {{clinic_name}}, {{login_url}}). Unknown tokens
-- are left blank. The branded HTML layout (logo, footer) is always applied by
-- the code — admins edit the CONTENT, not the wrapper.

CREATE TABLE IF NOT EXISTS email_templates (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_key VARCHAR(60)  NOT NULL UNIQUE,         -- matches MailService keys
  subject      VARCHAR(255) NOT NULL DEFAULT '',
  greeting     VARCHAR(255) NOT NULL DEFAULT '',     -- e.g. "Hello {{doctor_name}},"
  body         TEXT NULL,                            -- paragraphs, one per blank-line-separated block
  bullets      TEXT NULL,                            -- optional list, one item per line
  cta_label    VARCHAR(120) NULL,                    -- optional button label
  cta_url      VARCHAR(255) NULL,                    -- optional button URL (supports {{login_url}} etc.)
  sign_off     TEXT NULL,                            -- closing lines
  is_active    TINYINT(1) NOT NULL DEFAULT 1,        -- 0 = ignore this row, use code default
  updated_by   VARCHAR(120) NULL,                    -- admin email who last saved
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the editable defaults: mirrors MailService's built-in content, with
-- the dynamic bits expressed as {{placeholders}} so admins can edit copy while
-- values still fill at send time. ON DUPLICATE KEY UPDATE = re-runnable; it
-- WON'T clobber admin edits (it only re-touches the unique key, a no-op).
-- To actually push fresh defaults over existing rows, delete the row first.
INSERT INTO email_templates
  (template_key, subject, greeting, body, bullets, cta_label, cta_url, sign_off, is_active)
VALUES
  ('welcome',
   'Welcome to eClinicPro',
   'Hello,',
   'Welcome to eClinicPro — your clinic "{{clinic_name}}" is ready.\n\nManage appointments, prescriptions, patient records and billing — all from your dashboard.',
   NULL,
   'Open dashboard',
   '{{login_url}}',
   'Best regards,\nThe eClinicPro Team',
   1),

  ('doctor_approved',
   'Your clinic is now listed on eClinicPro',
   'Hello {{doctor_name}},',
   'Your eClinicPro account has been successfully approved and activated.\n\nYou can sign in to your clinic portal with your verified phone number ({{phone}}). No password is needed — we''ll send you a one-time code by SMS.\n\nWe would be happy to provide a personalized demo of the platform and help you get started with features such as:',
   'Online Appointment Management\nDigital Prescriptions\nElectronic Medical Records (EMR)\nPatient Management\nClinic Profile & Online Presence',
   'Sign in to your portal',
   '{{login_url}}',
   'Please let us know your preferred date and time for a short demo session, and our team will arrange it accordingly. We look forward to supporting your practice.\n\nYou can connect with us on WhatsApp or call: +91 9998010029\n\nBest regards,\nThe eClinicPro Team',
   1),

  ('password_reset',
   'Reset your eClinicPro password',
   'Hello,',
   'Use the button below to reset your password. This link is valid for 1 hour.',
   NULL,
   'Reset password',
   '{{reset_url}}',
   'If you didn''t request this, you can safely ignore this email.\n\n— The eClinicPro Team',
   1),

  ('staff_invite',
   'You are invited to join {{clinic_name}}',
   'Hello {{name}},',
   '{{clinic_name}} has invited you to join as {{role}} on eClinicPro.\n\nThis invitation expires in 7 days.',
   NULL,
   'Accept invitation',
   '{{accept_url}}',
   'Best regards,\nThe eClinicPro Team',
   1)
ON DUPLICATE KEY UPDATE template_key = template_key;
