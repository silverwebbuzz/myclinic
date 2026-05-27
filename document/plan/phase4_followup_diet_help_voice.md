# Phase 4 — Follow-up + Diet + Help page + Voice + Final Cleanup

**Goal:** Wire up the operational glue (follow-up workflow, diet attachments, voice input, Help page), then sweep the codebase clean. After Phase 4, the product is in its target state and there's no half-built scaffolding left from earlier phases.

**Why this is Phase 4:** Each piece here sits on top of primitives built earlier. Follow-ups need the new visit screen (Phase 2). Diet attachments need the WhatsApp share infrastructure that comes online when `patient_connect` add-on (Phase 1) is purchased. Voice needs the auto-save field (Phase 2). Help needs all the above to document.

**Dependency:** Phases 1–3 complete. Every checklist item green.

---

## 1. Strategy recap (locked in)

From the discussions:

### Follow-up
- 6 dropdown reasons + free-text fallback
- Smart date chips (`+3d`, `+5d`, `+1w`, `+2w`, custom)
- Dashboard widget: "Overdue follow-ups"
- Reception queue flag: incoming patient with overdue follow-up shows a badge
- Auto-reminder via WhatsApp/SMS if `patient_connect` add-on is active

### Diet
- 12 starter templates shipped (diabetic, hypertension, weight loss, thyroid, PCOS, IBS, low salt, low cholesterol, post-surgery, pregnancy, pediatric, low purine)
- Attach to visit, customize inline, save as personal template
- WhatsApp share button (requires `patient_connect` add-on or falls back to copy-to-clipboard link)
- **No** calorie calculator, per-meal builder, or macros — explicitly out of scope

### Help
- Modeled on Dr. Feelgood's structure (TOC + role-permission table + workflow scenarios + FAQ)
- **Per-module sections** — only render docs for modules this clinic has visible
- Doctor-role-only by default (reception sees a slimmer version)

### Voice
- Web Speech API (browser-native), Hindi / Gujarati / English
- Phase 2 wired the basic mic button on Notes; Phase 4 finalizes language detection + Symptoms field integration
- **No** AI transcription, **no** "convert to structured fields" — deferred indefinitely

### Final cleanup
- Drop Stripe everywhere (India-only product)
- Rename `module_catalog.price_monthly_usd` → `price_monthly` (it stores ₹)
- Delete old plan enum dead code references
- Drop the deferred Bucket-3 features that still aren't shipping after 6 months (revisit at that time — Phase 4 just lays out the criteria)

---

## 2. Audit findings

### Tables we KEEP and EXTEND

| Table | Current | Phase 4 changes |
|---|---|---|
| `visits` | Has `follow_up_date` + `follow_up_notes` columns (free text) | Keep columns for back-compat. Real state goes to new `follow_ups` table. |
| `appointments` | Has `type='followup'` and `is_followup` flag | Keep. We link new `follow_ups.appointment_id` when reception books one. |
| `diet_plans` | Per-visit attached diet with JSON | Keep — add `template_id` column to link to the new template library |
| `notifications` | Multi-channel queue (whatsapp/sms/email/push) | Keep — we add `follow_up_reminder` template. No schema change. |

### Tables we CREATE

| Table | Purpose |
|---|---|
| `follow_ups` | Per-visit follow-up with state (pending/done/missed/rescheduled) |
| `follow_up_reasons` | 6 standard reasons + per-clinic custom (small lookup) |
| `diet_templates` | 12 shipped templates + per-clinic + per-doctor custom |

### Tables we DROP (final cleanup)

None outright. Stripe **columns** drop, but the tables stay. Bucket-3 evaluation is deferred — we just document the criteria.

### Code files affected by Stripe cleanup

| File | What to remove |
|---|---|
| `app/app/Controllers/WebhookController.php` | Stripe webhook handlers — drop entirely. Keep Razorpay. |
| `app/app/Services/BillingGatewayService.php` | Stripe code paths. Keep Razorpay. |
| `app/app/Services/ChurnRiskService.php` | Stripe-customer-status checks — replace with Razorpay equivalents (or remove if unused) |
| `app/app/Services/DirectoryService.php` | Stripe references (likely vestigial) — investigate and remove |
| `app/app/Services/ChecklistService.php` | Stripe-related checklist items — replace with Razorpay |
| `app/app/Middleware/RefreshTokenMiddleware.php` | Stripe SDK references — drop |

### Help page — no existing infrastructure

The Help page doesn't exist anywhere in the portal. New route, new controller, new view.

---

## 3. Follow-up workflow — design

### Schema

```sql
CREATE TABLE follow_ups (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  patient_id BIGINT(20) UNSIGNED NOT NULL,
  visit_id BIGINT(20) UNSIGNED NOT NULL,           -- which visit triggered it
  doctor_id BIGINT(20) UNSIGNED NULL,              -- who set it (NULL if auto)
  due_date DATE NOT NULL,
  reason VARCHAR(40) DEFAULT NULL,                 -- 'check_progress','retest_labs', etc.
  reason_other TEXT DEFAULT NULL,                  -- free-text when reason='other'
  status ENUM('pending','done','missed','rescheduled','cancelled')
    NOT NULL DEFAULT 'pending',
  appointment_id BIGINT(20) UNSIGNED NULL,         -- when reception books, link here
  rescheduled_to_id BIGINT(20) UNSIGNED NULL,      -- if rescheduled, points to new row
  completed_visit_id BIGINT(20) UNSIGNED NULL,     -- which later visit closed it
  reminder_sent_at TIMESTAMP NULL,                 -- last WhatsApp/SMS sent
  reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fu_clinic_due (clinic_id, status, due_date),
  INDEX idx_fu_patient (patient_id, status),
  INDEX idx_fu_visit (visit_id),
  CONSTRAINT fk_fu_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_fu_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_rescheduled FOREIGN KEY (rescheduled_to_id) REFERENCES follow_ups(id) ON DELETE SET NULL,
  CONSTRAINT fk_fu_completed FOREIGN KEY (completed_visit_id) REFERENCES visits(id) ON DELETE SET NULL
);

CREATE TABLE follow_up_reasons (
  reason_key VARCHAR(40) NOT NULL PRIMARY KEY,
  label VARCHAR(80) NOT NULL,
  -- NULL = system-default reason available to all clinics
  -- non-NULL = clinic-specific custom reason
  clinic_id BIGINT(20) UNSIGNED NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_fur_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_fur_clinic_active (clinic_id, is_active, sort_order)
);

-- Seed the 6 system reasons
INSERT INTO follow_up_reasons (reason_key, label, clinic_id, sort_order) VALUES
  ('check_progress',           'Check progress',            NULL, 10),
  ('retest_labs',              'Retest labs',               NULL, 20),
  ('continue_treatment',       'Continue treatment',        NULL, 30),
  ('post_procedure_review',    'Post-procedure review',     NULL, 40),
  ('acute_followup',           'Acute episode follow-up',   NULL, 50),
  ('other',                    'Other (specify)',           NULL, 99);
```

### UX on the visit screen

Inside the "Notes & next visit" card (defined in Phase 2):

```
┌─ NOTES & NEXT VISIT ─────────────────────────────────────────────┐
│ Follow-up in 5 days if fever persists                            │
│                                                                  │
│ Next visit:  [+3d] [+5d] [+1w] [+2w] [Custom date]               │
│ Reason: [Check progress ▾]                                       │
│                                              [🎙 Voice]          │
└──────────────────────────────────────────────────────────────────┘
```

- **Date chips** are buttons that pre-fill `due_date = today + N days`.
- **Reason dropdown** is populated from `follow_up_reasons` (system + this clinic's customs).
- If reason = `other`, a text field appears below for `reason_other`.
- On Save Visit, a `follow_ups` row is created (or updated if one already exists for this visit).

### Smart date suggestions

If the prescription contains a 5-day antibiotic, the `+5d` chip gets a subtle highlight ring. Computed on the frontend from `prescriptions.duration_days` of antibiotic-class drugs (`drugs.drug_class LIKE 'antibiotic%'`). No backend change.

### Dashboard widget

On the doctor's dashboard, new card:

```
┌─ Follow-ups ──────────────────────────────────────────────┐
│ ⚠ 3 overdue          [View all]                           │
│   • Rajesh Patel — 5 days overdue (Check progress)       │
│   • Anita Sharma — 2 days overdue (Retest labs)          │
│   • Mohan Mehta — 1 day overdue (Continue treatment)     │
│                                                           │
│ 📅 8 due this week                                        │
│ ✓ 12 completed this month                                 │
└───────────────────────────────────────────────────────────┘
```

Query backing it:
- Overdue: `status='pending' AND due_date < CURDATE()`
- This week: `status='pending' AND due_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY`
- Done this month: `status='done' AND updated_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')`

### Reception queue flag

When a patient walks in or calls, reception sees their row in the queue. If `SELECT 1 FROM follow_ups WHERE patient_id = ? AND status='pending' AND due_date <= CURDATE() + INTERVAL 7 DAY LIMIT 1` returns a row, the queue card shows a badge:

```
[B] Bhavik Patel — 9374249829
    Last visit: 17 Apr · ⏰ Follow-up due today (Check progress)
```

Reception can tap the badge to confirm "this is the follow-up visit" — this links the new appointment to the pending follow-up row by setting `follow_ups.appointment_id`.

### Reminder loop

A daily cron `/cron/followup-reminders`:

1. Find rows where `status='pending'`, `due_date IN (CURDATE() - 1, CURDATE())`, `reminder_count < 3`.
2. For each, enqueue a `notifications` row with `template='follow_up_reminder'` and the patient's phone.
3. If `patient_connect` add-on is active → WhatsApp via existing notification worker. Otherwise → no reminder sent (we don't fall back to free SMS because SMS costs money too).
4. Increment `reminder_count`, set `reminder_sent_at`.

### Closing a follow-up

When a doctor completes a new visit for a patient with a pending follow-up:
- On Save Visit, if `follow_ups.status='pending'` exists for this patient, present an inline checkbox: *"Is this the follow-up from 17 Apr?"*
- If yes → set `follow_ups.status='done'`, `completed_visit_id=<new visit id>`.
- If the doctor ignores, the follow-up stays pending.

A nightly cron marks follow-ups as `status='missed'` when `due_date < CURDATE() - INTERVAL 30 DAY AND status='pending'`. They drop off the overdue widget but stay queryable.

---

## 4. Diet templates — design

### Schema

```sql
CREATE TABLE diet_templates (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  -- Scope: NULL = system template (shipped with product)
  --        clinic_id only = clinic-wide
  --        clinic_id + doctor_id = personal
  clinic_id BIGINT(20) UNSIGNED NULL,
  doctor_id BIGINT(20) UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(240) DEFAULT NULL,
  condition_tag VARCHAR(60) DEFAULT NULL,        -- "diabetes", "hypertension"
  veg_type ENUM('veg','nonveg','vegan','eggetarian','any') DEFAULT 'any',
  plan_json LONGTEXT NOT NULL
    CHECK (JSON_VALID(plan_json)),
  use_count INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_diet_clinic_doctor (clinic_id, doctor_id, is_active),
  INDEX idx_diet_condition (condition_tag),
  CONSTRAINT fk_dt_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_dt_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### plan_json structure

Same shape as `diet_plans.plan_json` (already used in the existing diet UI):

```json
{
  "instructions": "Stick to small meals 5 times a day.",
  "encouraged": [
    "Whole grains, oats, brown rice",
    "Leafy vegetables",
    "Lentils, dals, sprouts"
  ],
  "avoid": [
    "Sugar, sweets, sweetened drinks",
    "White flour (maida) products",
    "Deep-fried foods"
  ],
  "sample_day": {
    "breakfast": "Oats porridge with fruit",
    "midmorning": "Buttermilk or coconut water",
    "lunch": "2 chapati + dal + sabzi + salad",
    "evening": "Roasted chana / nuts",
    "dinner": "Multigrain chapati + sabzi"
  },
  "notes": "Walk for 30 minutes daily after dinner."
}
```

### The 12 starter templates (clinic_id NULL, doctor_id NULL)

Shipped in `phase4_diet_seed.sql`. Each is one INSERT with a hand-written `plan_json`. The 12:

1. **Diabetic diet** (condition_tag: `diabetes`)
2. **Hypertension diet — low salt** (condition_tag: `hypertension`)
3. **Weight loss diet** (condition_tag: `obesity`)
4. **Thyroid support diet** (condition_tag: `thyroid`)
5. **PCOS diet** (condition_tag: `pcos`)
6. **IBS-friendly diet** (condition_tag: `ibs`)
7. **Low cholesterol diet** (condition_tag: `cholesterol`)
8. **Post-surgery recovery diet** (condition_tag: `post_surgery`)
9. **Pregnancy nutrition** (condition_tag: `pregnancy`)
10. **Pediatric general** (condition_tag: `pediatric`)
11. **Gout / low purine diet** (condition_tag: `gout`)
12. **Anemia / iron-rich diet** (condition_tag: `anemia`)

These cover the 80% of diet recommendations Indian clinics actually use. Doctors can tweak after applying and save as personal templates.

### UX on the visit screen

Diet is one of the optional modules (visible when `diet` is in `clinic_settings.visible_modules` — set by specialty defaults in Phase 2 for diabetology, dietitian, cardio, gastro).

```
┌─ DIET PLAN ──────────────────────────────────────────────────────┐
│ Templates: [Diabetic diet] [Low salt] [Weight loss] [+ More]     │
│                                                                  │
│ Selected: Diabetic diet                                          │
│ ▾ Customize this plan                                            │
│   Encouraged: [edit list]                                        │
│   Avoid:      [edit list]                                        │
│   ...                                                            │
│                                                                  │
│ [💾 Save as my template]   [📱 Share via WhatsApp]               │
└──────────────────────────────────────────────────────────────────┘
```

When a template is applied, the existing `diet_plans` row for the visit is populated by copying `plan_json` from the template + incrementing `diet_templates.use_count`. Doctor edits inline; saving updates the visit's `diet_plans` row, not the template (templates only change via "Save as my template").

### WhatsApp share

Calls existing notification worker with `channel='whatsapp'`, `template='diet_plan_share'`, `payload={plan_json, patient_phone}`. The notification worker formats it (markdown bullets) and sends.

If the clinic doesn't have `patient_connect` add-on active:
- The share button is replaced with **[📋 Copy link to clipboard]**.
- Clicking generates a signed, short-lived URL like `/p/d/abc123` that renders the plan as a public read-only page.
- Doctor copies the link and pastes it into their own WhatsApp Web. Same end result; we just don't automate it.

### Admin: diet template management

`/admin/diet-templates` — CRUD on the 12 system templates. Admin can edit them globally (changes ripple to new applications, doesn't touch existing visit `diet_plans` rows). Admin can also disable any that don't fit Indian dietary norms after early feedback.

---

## 5. Help page — design

### Route + controller

```
GET  /help                           → portal Help index page
GET  /help/<section>                 → deep-link to a section (anchor)
```

New controller: `HelpController` (~80 lines).

### Layout (modeled on Dr. Feelgood)

```
┌────────────────────────────────────────────────────────────┐
│ TOC sidebar (sticky)        │  CONTENT                     │
│                             │                              │
│ GETTING STARTED             │  ── System Overview ──       │
│  System Overview            │                              │
│  Roles & Permissions        │  eClinicPro is a clinic OS…  │
│  Typical Clinic Day         │                              │
│                             │  ── Roles & Permissions ──   │
│ PAGES                       │                              │
│  Dashboard                  │  Doctor / Asst / Reception   │
│  Patient Visit Screen       │  [permission matrix table]   │
│  Appointments               │                              │
│  ...                        │  ...                         │
│                             │                              │
│ REFERENCE                   │                              │
│  FAQ                        │                              │
└────────────────────────────────────────────────────────────┘
```

### Sections (in order)

1. **System Overview** — what eClinicPro does, what the 3 core areas are
2. **Roles & Permissions** — full role matrix (Doctor / Asst Doctor / Receptionist / Nurse / Lab Tech)
3. **Typical Clinic Day** — step-by-step scenario (Morning → Consultation → End of Day)
4. **Dashboard** — what each tile means, how to interpret the follow-up widget
5. **Patient & Visit Screen** — the redesigned single-screen, what each card does
6. **Symptoms** — the 3-layer system explained, how custom symptoms graduate
7. **Prescription** — frequency presets, tapering, templates, "Same as last visit"
8. **Follow-ups** — how to set them, dashboard widget, reception flag
9. **Diet plans** — using templates, customizing, WhatsApp share
10. **Appointments & Queue** — booking, walk-ins, status flow
11. **Billing & Invoicing** — invoices, GST, payment collection
12. **Modules & Add-ons** — visible_modules, Patient Connect, Clinic Network
13. **Settings** — clinic settings, specialty defaults, working hours
14. **FAQ** — top 20 questions (assembled over time)

### Per-module conditional rendering

Each section's HTML is wrapped in a conditional:

```php
<?php if (in_array('diet', $visibleModules)): ?>
  <section id="diet"> … </section>
<?php endif; ?>
```

So a homeopath clinic doesn't see the Vitals / Labs / Diet sections in their Help page TOC. **Cleaner experience, no irrelevant docs.**

Sections always visible: Overview, Roles, Typical Day, Dashboard, Patient & Visit, Symptoms, Prescription, Follow-ups, Appointments, Billing, Modules, Settings, FAQ.

### Help page visibility

- Doctor role: full Help.
- Asst Doctor: same as Doctor.
- Reception: slimmer version (no clinical sections — only Dashboard, Appointments, Walk-in, Patient list, Billing, Settings, FAQ).
- Patient: no Help link in their panel.

### Help page is a single PHP file

`app/views/help/index.php` — about 1000 lines of HTML + Tailwind classes, embedded styles modeled on the Feelgood page (TOC sidebar, permission table, scenario boxes, FAQ accordion, callout boxes — `tip`, `note`, `warn`).

Hard-coded content. No CMS. We update help by editing the file in git.

### First-time tooltips (deferred, optional)

The strategy doc mentioned 30-second product tours for first-time users (Shepherd.js). **Deferred to a Phase 5 wishlist.** Not included in Phase 4 — the Help page is the primary education backstop. We can add tooltips later as a frosting.

---

## 6. Voice input — finalize

Phase 2 wired the mic button on the Notes textarea. Phase 4 extends it.

### Language detection

`clinic_settings.voice_lang` (column added in Phase 1) holds the BCP-47 code: `en-IN`, `hi-IN`, `gu-IN`, etc. Doctor sets this once in clinic settings → all voice inputs use it.

If `voice_lang` is NULL → fall back to `en-IN`.

### Where voice is wired in Phase 4

- **Notes textarea** (Phase 2 — already done)
- **Symptoms input** — voice → speech-to-text → search the symptom library with the result and offer top 3 matches as chips
- **Diet customization** — voice → append to "encouraged" or "avoid" list (user picks which list before tapping mic)
- **Free-text reason in follow-up** — voice → text in `reason_other`

### Critical UX rule

Voice is **never** the primary input. It's always a chip / button that *populates* a text field that the doctor can edit. The doctor can keep typing if voice misfires. **Voice doesn't auto-save** until the doctor confirms — we don't want a misheard "Brain fog" auto-becoming "rainbow."

### No AI transcription

Repeat from earlier discussion: Web Speech API only. **No** `ai_transcription` feature flag flip. The `feature_flags` row for `ai_transcription` stays at `is_enabled=0` indefinitely until Phase 5+ when we have a model evaluation + regulatory plan.

---

## 7. Final cleanup

### 7.1 Drop Stripe everywhere

**Schema:**

```sql
-- Drop Stripe FK references in tables
ALTER TABLE tenants DROP COLUMN stripe_customer_id;
ALTER TABLE clinic_modules DROP COLUMN stripe_sub_item_id;
ALTER TABLE payments DROP COLUMN stripe_payment_id;
-- (Add others as discovered)
```

**Code:**

| File | Action |
|---|---|
| `WebhookController` | Remove `handleStripeWebhook()` method + route |
| `BillingGatewayService` | Remove Stripe driver, keep only Razorpay |
| `ChurnRiskService` | Replace any Stripe lookups with Razorpay subscription status |
| `DirectoryService` | Investigate Stripe references (likely vestigial) — remove |
| `ChecklistService` | Replace Stripe checklist items with Razorpay |
| `RefreshTokenMiddleware` | Drop Stripe SDK imports |
| `composer.json` | Remove `stripe/stripe-php` dependency, run `composer update` |

### 7.2 Rename `module_catalog.price_monthly_usd` → `price_monthly`

```sql
ALTER TABLE module_catalog
  CHANGE COLUMN price_monthly_usd price_monthly DECIMAL(8,2) DEFAULT 0.00,
  CHANGE COLUMN price_yearly_usd  price_yearly  DECIMAL(8,2) DEFAULT 0.00;
```

Then sweep code: `grep -rn 'price_monthly_usd' app/` and rename all references. Should be ~5-10 hits.

### 7.3 Delete legacy plan-string references

```bash
# Confirm no remaining usages before deleting
grep -rn "plan.*=.*'free'" app/
grep -rn "plan.*=.*'clinic'" app/
grep -rn "plan.*=.*'practice'" app/
grep -rn "plan.*=.*'enterprise'" app/
```

Every hit should already be removed in Phase 1. If anything remains, fix it now.

### 7.4 Bucket-3 evaluation criteria

Phase 1 hid 9 controllers behind feature flags. After 6 months of production, run this query to decide what to do with each:

```sql
SELECT ff.flag_key,
       (SELECT COUNT(*) FROM clinic_modules cm
        WHERE cm.module_id = ff.flag_key AND cm.is_active = 1) AS active_clinics,
       (SELECT COUNT(*) FROM module_catalog mc
        WHERE mc.id = ff.flag_key) AS in_catalog
FROM feature_flags ff
WHERE ff.flag_key LIKE '%_module'
   OR ff.flag_key IN ('ai_transcription','custom_branding','docs_vault','advanced_analytics');
```

Decision rules:
- **>20 active clinics requesting** → promote to launched add-on (`is_active=1` in module_catalog, flip flag to `scope='all'`)
- **0–5 active clinics, no inbound requests** → delete the controller, views, flag, and `module_catalog` row
- **Anything in between** → keep as beta for another 6 months

**This is documented but not executed in Phase 4** — it's a checkpoint for the future.

### 7.5 Delete deprecated files (final sweep)

After Phase 4 deploys, do a real cleanup pass:

| File / Block | Why kill it |
|---|---|
| Old visit `show.php` tab block (left in code from Phase 2's gradual rollout) | New screen has been live for a release cycle |
| `?new=1` and `?new_rx=1` query-flag handling | Phase 2/3 staged rollout is done |
| The `wizardData()` helper in `PatientController` (already deleted in Phase 2 — verify) | Should be gone |
| Any commented-out Stripe code | Belt-and-suspenders cleanup |
| Stripe-themed UI strings ("Stripe customer ID", "Connect Stripe") in admin views | Done |

---

## 8. Application code changes

### 8.1 New controller: `FollowUpController`

```
POST /api/visits/{visitId}/follow-up        → create/update follow-up row
GET  /api/follow-ups/dashboard              → JSON for the dashboard widget
POST /api/follow-ups/{id}/complete          → mark done, link completed_visit_id
POST /api/follow-ups/{id}/reschedule        → create new row, set rescheduled_to_id
POST /api/follow-ups/{id}/cancel
GET  /follow-ups                            → "All follow-ups" page
```

### 8.2 New controller: `DietTemplateController`

```
GET  /api/diet-templates?scope=all|mine|clinic|system
POST /api/diet-templates                    → create from current visit's diet
POST /api/visits/{visitId}/apply-diet/{tid} → instantiate template into diet_plans
DEL  /api/diet-templates/{id}
GET  /p/d/{token}                           → public diet plan share page
```

### 8.3 New controller: `HelpController`

```
GET  /help                                  → render help page (role-aware)
```

Reads `clinic_settings.visible_modules` to decide which sections to render. Reads the session role to decide doctor vs reception variant.

### 8.4 Cron jobs added

| Cron | Frequency | Purpose |
|---|---|---|
| `cron/followup-reminders` | daily 09:00 IST | Queue WhatsApp reminders for today/tomorrow's pending follow-ups |
| `cron/followup-mark-missed` | daily 03:00 IST | Flip status to `missed` for follow-ups overdue >30 days |
| `cron/stripe-cleanup-verify` | one-shot | Sanity-check that no Stripe references remain in code paths used in production |

### 8.5 Dashboard changes

`DashboardController::index` adds a new tile + widget:

- **Tile:** "Follow-ups overdue" (count of pending+overdue)
- **Widget:** full overdue list (top 5) with quick actions (reschedule / mark done)

The follow-up widget appears only if the doctor's `clinic_settings.visible_modules` includes any clinical module (always true).

### 8.6 Queue / Reception integration

`QueueController::index` adds a follow-up flag check per row:

```php
// Per-patient lookup (batched)
$followUpFlags = FollowUpService::pendingForPatients($patientIds);
```

Then the queue view shows the badge for flagged patients.

### 8.7 Patient visit screen updates

In `app/views/visits/show.php` (the redesigned screen from Phase 2):

- **Notes & next visit card** — adds reason dropdown + date chips wired to follow-up API
- **Save Visit** — if a pending follow-up exists for this patient, show a checkbox: "Is this the follow-up from <date>?"

### 8.8 New view: `app/views/help/index.php`

The full Help page. About 1000 lines. Hand-written content, Tailwind classes, embedded styles.

---

## 9. Admin changes

### 9.1 New admin page: `/admin/follow-ups`

Cross-tenant view of all follow-up activity. Filters: overdue / due this week / completed / missed. Per-clinic drill-down.

### 9.2 New admin page: `/admin/diet-templates`

CRUD on system-level diet templates (the 12 shipped). Each admin edit ripples to new template applications; existing `diet_plans` rows are unaffected.

### 9.3 Bucket-3 evaluation dashboard

`/admin/feature-flag-usage` — shows the SQL from §7.4 as a UI. Helps decide which Bucket-3 features to launch / keep / delete after 6 months.

### 9.4 Stripe cleanup admin task

`/admin/stripe-cleanup` (temporary, deletable after Phase 4) — a one-page tool that lists any remaining Stripe-referencing data so the admin can manually clean it up before the schema drops.

---

## 10. Code to DELETE in Phase 4

After Phase 4 deploys, remove:

| File / Code | Why kill it |
|---|---|
| All Stripe code paths (see §7.1) | India-only, Razorpay only |
| `stripe_customer_id`, `stripe_sub_item_id`, `stripe_payment_id`, `stripe_invoice_id` columns | Schema drop |
| `composer.json` `stripe/stripe-php` dependency | Unused |
| The old `?new=1` / `?new_rx=1` query flag branches | Staged rollout complete |
| The deprecated old visit screen template (kept around in Phase 2 for fallback) | New screen has been the default for 2+ release cycles |
| `module_catalog.price_monthly_usd` references | Renamed in §7.2 |
| Stale `LeadAdminController` / `LeadSettingsController` Stripe checks (if any) | Cleanup sweep |

---

## 11. Order of operations

1. **Phases 1, 2, 3 complete.** All checklists green.
2. **Run schema migrations** (`phase4_migrations.sql`) — creates 3 new tables, drops Stripe columns, renames module_catalog columns, seeds follow_up_reasons.
3. **Seed diet templates** (`phase4_diet_seed.sql`) — 12 system rows.
4. **Deploy `FollowUpController` + `DietTemplateController` + `HelpController`** — endpoints live but UI not yet pointing to them.
5. **Deploy updated visit screen view** — follow-up card, diet card now wired to new APIs.
6. **Deploy dashboard widget changes**.
7. **Deploy reception queue badge integration**.
8. **Deploy the Help page view** at `/help`.
9. **Schedule follow-up cron jobs** (reminders + mark-missed).
10. **Run Stripe cleanup**:
    - Sweep code, remove Stripe-referencing files / methods
    - Drop columns (Block 8 of SQL)
    - Drop composer dependency
    - Run `/admin/stripe-cleanup` to verify nothing left
11. **Rename `price_monthly_usd`** (Block 9 of SQL) + grep-replace in code.
12. **Manually verify**:
    - Create a follow-up on a visit → appears on dashboard widget the next day
    - Reception sees the badge when patient walks in
    - WhatsApp reminder sends (with `patient_connect` add-on active)
    - Diet template applies cleanly, customizes, saves as personal
    - WhatsApp share works (or copy-link fallback)
    - Help page renders correctly, hides modules the clinic doesn't have
    - Voice input on Notes works in Hindi (if clinic_settings.voice_lang='hi-IN')
13. **Delete deprecated code** (§10).
14. **Mark Phase 4 complete in the checklist.**

---

## 12. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Follow-up reminder spam if cron loops on a stuck row | `reminder_count < 3` cap in the cron. After 3 sends, no more reminders. |
| Reception badge query too slow on large queues | Single batched lookup `WHERE patient_id IN (...)` instead of N+1. Index `idx_fu_patient` supports it. |
| Diet template `plan_json` malformed by hand-editing | JSON_VALID constraint catches at write time. Renderer falls back to "no plan structure" if NULL. |
| WhatsApp share fails silently when add-on inactive | Frontend explicitly checks the add-on, swaps the button text. No silent failures. |
| Help page becomes stale as features evolve | Hand-edited markdown-style content with version footer ("Last updated <date>"). PR review enforces help-page diff for any feature change. |
| Web Speech API unavailable on Safari iOS | Detect at boot. Hide mic button if unavailable. No crash. |
| Stripe code path removal breaks a corner case (e.g., a stuck webhook) | One-shot `cron/stripe-cleanup-verify` runs first. Webhook endpoint returns 410 Gone for one release cycle before being deleted. |
| `price_monthly_usd` rename misses a SQL reference | Grep before renaming. Search `app/`, `partials/`, sitemap.php, marketing pages. Confirm zero hits remain. |
| Follow-up overdue cron marks live customer as missed | Cron checks `due_date < CURDATE() - INTERVAL 30 DAY`. A patient missing for 30 days is genuinely missed; this is correct behavior. |
| Patient privacy: public diet share link leaks data | Token is signed, short-lived (24h), random 16+ chars. Auto-expires. Plain HTML render with no patient PII besides name. |

---

## 13. Phase 4 completion checklist

- [ ] `follow_ups` table created with all FKs and indexes
- [ ] `follow_up_reasons` table created and seeded with 6 system reasons
- [ ] `diet_templates` table created with FKs and indexes
- [ ] 12 system diet templates seeded
- [ ] `diet_plans.template_id` column added (links to applied template)
- [ ] Stripe columns dropped from `tenants`, `clinic_modules`, `payments`, etc.
- [ ] `module_catalog.price_monthly_usd` renamed to `price_monthly`
- [ ] `FollowUpController` deployed and tested
- [ ] `DietTemplateController` deployed and tested
- [ ] `HelpController` deployed and `/help` accessible
- [ ] Dashboard follow-up widget renders correctly
- [ ] Reception queue shows follow-up badge for incoming patients
- [ ] Daily follow-up reminder cron scheduled
- [ ] Mark-missed cron scheduled
- [ ] WhatsApp diet share works end-to-end (with add-on)
- [ ] Copy-link fallback works (without add-on)
- [ ] Voice input on Notes works in en-IN, hi-IN, gu-IN
- [ ] Voice input on Symptoms suggests chips
- [ ] Help page renders different sections per visible_modules
- [ ] Help page renders different sections per role
- [ ] All Stripe code paths removed from controllers/services/middleware
- [ ] `composer.json` Stripe dependency removed
- [ ] `/admin/follow-ups` cross-tenant page works
- [ ] `/admin/diet-templates` CRUD works
- [ ] `/admin/feature-flag-usage` evaluation dashboard works
- [ ] One real follow-up created, reminded, completed end-to-end
- [ ] One diet template applied, customized, saved as personal
- [ ] One Help page rendered and screenshot for both Doctor and Reception roles
- [ ] All deprecated code (§10) deleted and confirmed by grep

When all green, **eClinicPro is in its target Phase-4 state.** Next step: I write the **master Execution Guide** consolidating all 4 phases into a deploy-ready run order.
