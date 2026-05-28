# Plan — WhatsApp-first messaging (SMS fallback) + lead/booking flow

**Rewritten 2026-05-28** after thinking through the real-world failure modes.
This replaces the earlier draft.

---

## 0. Decisions locked

| Decision | Choice | Why |
|---|---|---|
| WhatsApp account | **Platform-owned** — one eClinicPro number | No per-clinic Meta setup; clinics just get value |
| Provider | **Meta Cloud API direct** | Cheapest; you own templates + webhooks; you have Claude for code |
| Channel order | **WhatsApp first → SMS fallback** | India prefers WhatsApp; SMS only when WhatsApp can't deliver |
| Booking model | **Lead delivery, NOT a confirmation gate** | A scraped/cold doctor confirming is a bonus, never a blocker. Patient is never left stuck. |
| Doctor action | **One-tap Confirm + Call patient only** | No "reschedule" link — cold doctors won't use it; they'll just call |
| Patient safety net | **Always show the clinic's phone number immediately** | If the doctor never responds, the patient can still call. The lead is never lost. |
| Joined-doctor alerts | **Only when action needed** | Bookings land in dashboard silently; WhatsApp only for new-patient leads / unconfirmed |
| OTP channel | **SMS direct (unchanged)** | OTP needs to arrive in seconds; WhatsApp delivery webhooks are too slow |

---

## 1. The core principle (read this first)

> **We are delivering a lead, not brokering a contract.**

The patient's experience does **not** depend on the doctor responding. The
moment a patient books:
1. They see: *"Request sent to Dr. X. They'll confirm shortly — you can also
   call them directly: 98xxxxxx."*
2. The doctor gets a WhatsApp alert with **[Confirm] [Call patient]**.
3. If the doctor confirms → patient gets a happy confirmation. Bonus.
4. If the doctor stays silent → patient already has the number, plus two gentle
   nudges (see §4). **Nothing breaks.**

This is what makes the flow work for **non-joined doctors** (the 95% scraped from
the directory who've never heard of you). Confirmation is upside, not a gate.

---

## 2. The four booking scenarios (practical)

### Scenario A — Doctor NOT joined (the common case)
1. Patient books on `/find-a-doctor` → `appointment_requests` row + secure
   `L/{token}` link created.
2. **Patient** immediately: WhatsApp/SMS *"Request sent to Dr. X for 28 May 9 AM.
   They'll confirm shortly. Call directly: 98xxxxxx."*
3. **Doctor** WhatsApp (utility template): *"New patient request — Bhavik, 28 May
   9 AM, acne. View & confirm: eclinicpro.com/L/abc123"* with [Confirm] [Call].
4. Doctor taps **Confirm** → patient gets *"✓ Confirmed by Dr. X for 28 May 9 AM."*
   AND this is your first **responsiveness signal** + a claim-funnel hook
   (*"You just got a patient via eClinicPro — claim your profile for more."*).
5. Doctor silent → §4 nudges fire. Patient still has the number.

### Scenario B — Doctor CLAIMED (has portal login, not deep SaaS user)
- Booking lands in their dashboard queue.
- WhatsApp alert fires (it's a new patient lead — action needed).
- They confirm from dashboard OR WhatsApp; either updates the same row.

### Scenario C — Doctor is a full SaaS user
- Booking lands silently in dashboard. **No WhatsApp by default** (reduces cost
  + noise). Reception works the queue.
- WhatsApp only fires if it's an unconfirmed lead near appointment time, or per
  the doctor's notification preference.

### Scenario D — Patient-initiated WhatsApp ("Chat on WhatsApp" button)
- `wa.me/<platform-number>?text=...` opens a chat the **patient** starts.
- This opens a 24-hour **service window** → replies inside it are **free**.
- Cheapest path; encourage it with a prominent "Chat on WhatsApp" button on
  doctor pages.

---

## 3. WhatsApp-first → SMS fallback (exactly how)

Two distinct fallback triggers:

1. **Delivery-failure fallback (primary path).** Send WhatsApp template → store
   Meta `wamid` → Meta posts a status webhook. If status is `failed` /
   `undelivered` with a "not a WhatsApp user" / hard-fail code **and** the
   template has `sms_fallback_text` → enqueue an SMS row (`fallback_of` = the WA
   row). The processor sends it next cycle. **SMS rows never fall back again.**

2. **No-number / opted-out (send-time).** If we somehow already know the number
   isn't WhatsApp-capable, skip straight to SMS.

**Timing reality:** WhatsApp status webhooks arrive in seconds–minutes. Fine for
confirmations/reminders. **NOT for OTP** → OTP stays SMS-direct.

**Cost note (your Meta pricing links):** Meta bills per 24-hour *conversation* by
category. All our transactional messages are **utility** (~₹0.11–0.18 in India) —
the cheap tier. Patient-initiated chats (Scenario D) are **service = free**.
We avoid marketing-category messages entirely.

---

## 4. The no-response safety net (your "nudge" question, solved)

A single nudge isn't enough — booking lead time varies from minutes to days. Two
capped, utility-category touchpoints:

| Touchpoint | When | Message | Skip if |
|---|---|---|---|
| **Soft nudge** | ~2 h after booking, only if still unconfirmed AND appointment is >3 h away | *"Your request is with Dr. X. Not heard back? Call directly: 98xxxxxx."* | Already confirmed, or appt <3 h away (the reminder covers it) |
| **Appointment reminder** | ~2 h before the appointment, always | *"Reminder: appointment with Dr. X today at 9 AM. Call: 98xxxxxx."* | Appointment cancelled |

Time-aware rules:
- Booked **overnight** → soft nudge waits until clinic opening hour (don't WhatsApp at 2 AM).
- Booked **same-day, <3 h out** → skip soft nudge, send only the reminder.
- Each touchpoint fires **at most once** per appointment (cost control).

Your instinct ("call before leaving home") = the 2-h-before reminder. Kept.
The soft nudge handles the 3-days-out silent-black-hole case you'd otherwise miss.

---

## 5. What already exists (reuse)

| Piece | Status |
|---|---|
| `notifications` queue table | ✅ channel/template/to_number/payload/status/attempts/scheduled_at |
| `WhatsAppService::send` | ✅ Meta Cloud API — but plain text + env creds. Needs: templates + DB creds + wamid capture |
| `TwilioSmsService` + `partials/sms.php` (MSG91) | ✅ SMS senders — unify behind one interface |
| `NotificationProcessor::processQueue` | ✅ drains queue, routes by channel. Needs: wamid storage, no inline SMS fallback |
| `NotificationTemplateService::render` | ✅ template key + payload → string |
| `L.php` short-link handler + `appointment_requests` concept | ✅ the doctor lead link |
| Lead/claim flow + directory | ✅ from earlier work |

**Gaps:** platform creds store, Meta template registry, status webhook, the
WhatsApp→SMS fallback, the nudge scheduler, admin UI.

---

## 6. Database changes

### 6.1 `platform_settings` (key/value, admin-editable creds)
```sql
CREATE TABLE platform_settings (
  setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
  setting_value TEXT DEFAULT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```
Seeded keys: `wa_access_token`, `wa_phone_number_id`, `wa_business_id`,
`wa_webhook_verify_token`, `wa_app_secret`, `sms_provider`, `sms_auth_key`,
`sms_sender_id`, `messaging_enabled`. (In admin, no redeploy to start WhatsApp.)

### 6.2 `wa_templates` (Meta template registry, admin-managed)
```sql
CREATE TABLE wa_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(60) NOT NULL UNIQUE,    -- internal: 'appointment_confirmed'
  meta_name VARCHAR(120) NOT NULL,             -- Meta-approved name
  language VARCHAR(10) NOT NULL DEFAULT 'en',
  category ENUM('utility','marketing','authentication') NOT NULL DEFAULT 'utility',
  body_text TEXT NOT NULL,                     -- with {{1}} {{2}} for preview
  variables JSON DEFAULT NULL,                 -- ["patient_name","date"] → {{n}} mapping
  sms_fallback_text TEXT DEFAULT NULL,         -- plain version for SMS fallback
  status ENUM('draft','submitted','approved','rejected','paused') NOT NULL DEFAULT 'draft',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### 6.3 Extend `notifications`
```sql
ALTER TABLE notifications
  ADD COLUMN provider_message_id VARCHAR(120) NULL AFTER status,   -- Meta wamid
  ADD COLUMN fallback_of BIGINT UNSIGNED NULL AFTER provider_message_id,
  ADD COLUMN delivery_status VARCHAR(30) NULL AFTER fallback_of,   -- sent/delivered/read/failed
  ADD COLUMN delivered_at TIMESTAMP NULL AFTER delivery_status,
  ADD INDEX idx_notif_provider_msg (provider_message_id),
  ADD INDEX idx_notif_status_sched (status, scheduled_at);
```

### 6.4 Extend `appointment_requests` (or appointments) for lead lifecycle
```sql
ALTER TABLE appointment_requests
  ADD COLUMN confirm_token VARCHAR(32) NULL,            -- the L/{token}
  ADD COLUMN lead_status ENUM('requested','confirmed','called','no_response','expired')
    NOT NULL DEFAULT 'requested' AFTER status,
  ADD COLUMN confirmed_at TIMESTAMP NULL,
  ADD COLUMN soft_nudge_sent_at TIMESTAMP NULL,
  ADD COLUMN reminder_sent_at TIMESTAMP NULL,
  ADD INDEX idx_ar_confirm_token (confirm_token),
  ADD INDEX idx_ar_lead_status (lead_status);
```
(Exact table name confirmed at build time against the live schema.)

---

## 7. Code

### 7.1 `MessagingSettings` (`App\Support`) — reads/caches `platform_settings`.
### 7.2 `WhatsAppService` (rewrite) — DB creds, **template** messages, capture `wamid`, dev-log stub when unconfigured.
### 7.3 `SmsService` — one interface over MSG91 + Twilio; provider from settings.
### 7.4 `NotificationProcessor` (extend) — store `wamid`; **no inline SMS fallback** (webhook drives it); respect `messaging_enabled` + per-clinic add-on for clinic-origin messages.
### 7.5 `WebhookController::whatsappStatus` (new) — verify token (GET) + app-secret signature (POST); match `wamid` → update `delivery_status`; on hard-fail enqueue SMS fallback.
### 7.6 `WebhookController::whatsappInbound` (new, light) — capture doctor [Confirm]/[Call] taps if done via WhatsApp interactive buttons; also opens service windows for patient-initiated chats.
### 7.7 `LeadFlowService` (new) — the brains of §2/§4:
- `createLeadRequest()` → token, queue patient msg + doctor msg.
- `confirm($token)` → set `lead_status=confirmed`, queue patient confirmation,
  fire claim-funnel hook.
- `runNudges()` (cron) → soft-nudge + reminder per §4 rules.
- `expireStale()` (cron) → mark `no_response`/`expired` past appointment time.
### 7.8 Marketing bridge `partials/notify.php` — `ecp_enqueue_notification(...)` so the marketing booking writes to the same `notifications` queue. OTP stays on `partials/sms.php`.
### 7.9 `L.php` (extend) — the `/L/{token}` page renders patient/appt details + **[Confirm] [Call patient]** buttons (no reschedule). Confirm posts to `LeadFlowService::confirm`.

---

## 8. Admin UI — `/admin/messaging` (super-admin, 3 tabs)

- **Connection** — WA token/phone-id/business-id/verify-token/app-secret
  (masked), SMS provider+key+sender, master enable toggle, **Send test message**,
  shows the webhook callback URL to paste into Meta.
- **Templates** — list `wa_templates` with status badges; create/edit body +
  `{{n}}` vars + SMS fallback text; "Mark approved" (after Meta approves);
  live preview.
- **Log / monitor** — recent `notifications` (channel, template, status,
  delivery_status, whether SMS fallback fired); filter failed. Watch spend +
  catch template rejections.

`MessagingAdminController` + 3 views + nav link.

---

## 9. Templates to ship (all utility unless noted)

| key | direction | trigger |
|---|---|---|
| `patient_request_sent` | → patient | on booking ("request sent, call directly") |
| `doctor_new_lead` | → doctor | on booking (Confirm/Call buttons) |
| `patient_confirmed` | → patient | doctor taps Confirm |
| `patient_soft_nudge` | → patient | §4 soft nudge |
| `appointment_reminder` | → patient | §4 reminder (~2h before) |
| `prescription_ready` | → patient | visit complete |
| `diet_plan_shared` | → patient | diet share |
| `follow_up_reminder` | → patient | follow-up cron |

(OTP / `authentication` = deferred; stays SMS.)

---

## 10. Cron jobs (admin POST triggers, like existing churn/followup)

| Cron | Frequency | Does |
|---|---|---|
| `notifications:process` | every 1–2 min | drain queue, send WA/SMS, store wamid |
| `leads:nudges` | every 15 min | soft-nudge + appointment reminders (§4) |
| `leads:expire` | hourly | mark past-time unconfirmed as no_response |

---

## 11. Build order

1. DB: `platform_settings`, `wa_templates`, `notifications` cols, `appointment_requests` cols + seed settings/templates (draft).
2. `MessagingSettings`.
3. Rewrite `WhatsAppService` (templates + DB creds + wamid).
4. `SmsService` consolidation.
5. `NotificationProcessor` (wamid, no inline fallback).
6. `WebhookController::whatsappStatus` + route + signature verify + SMS fallback enqueue.
7. `LeadFlowService` + extend `L.php` confirm page (Confirm + Call only).
8. Marketing bridge `partials/notify.php`; wire `/find-a-doctor` booking.
9. Nudge + expire crons.
10. Admin `/admin/messaging`.
11. Test in dev-stub (no creds → file log), then live with one approved template + test-send.

---

## 12. What this does NOT include

- Reschedule-via-WhatsApp (cut — doctors just call; reschedule lives in portal).
- Per-clinic BYO WhatsApp numbers (platform-owned chosen).
- BSP integration (Meta direct chosen).
- WhatsApp OTP (phase 2; OTP stays SMS).
- Two-way chat / AI receptionist (later).
- Patient/doctor mobile apps (premature — web + WhatsApp first).

---

## 13. Why this is practically sound (the honest summary)

- **Doctors never gate the patient.** Confirm is a one-tap bonus that triggers a
  nice patient message + your claim-funnel hook. Silence costs nothing — the
  patient already has the clinic number and gets two gentle nudges.
- **WhatsApp-first, SMS only on real delivery failure** → you don't pay twice,
  and patients on non-WhatsApp numbers still get reached.
- **All transactional messages are utility-category** (cheap); patient-initiated
  chats are free.
- **Works before any doctor onboards** — which is exactly the cold-start the
  whole marketplace strategy depends on. The confirm-tap is also your first
  doctor-engagement signal and the wedge into the claim → paid funnel.
