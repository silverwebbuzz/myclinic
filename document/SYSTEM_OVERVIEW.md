# eClinicPro — System Overview

**The single source of truth for how the system works today.** Updated 2026-05-28
after the Phase 1–4 redesign. If something here disagrees with an older doc,
this wins (older docs were deleted to avoid confusion).

---

## 1. What eClinicPro is

A multi-tenant clinic operating system for Indian clinics:

- **Marketing site** — `eclinicpro.com` (plain PHP, `partials/` + root `*.php`)
- **Doctor portal** — `app.eclinicpro.com` (PHP MVC in `app/`)
- **Public doctor directory** — `/find-a-doctor` (+ SEO city/specialty pages)
- **Patient panel** — `/patient` (passwordless OTP login)

Single product, single plan. "Simple by default, powerful when needed."

---

## 2. Pricing model

- **One plan**: ₹1,499/month or ₹14,999/year. Everything to run a clinic.
- **30-day free trial**, no card. Admin can grant a one-time 15-day extension.
- **Founding Clinic deal**: first 100 sign-ups → ₹999/month locked for 24 months,
  then auto-converts to ₹1,499.
- **Two paid add-ons**:
  - **Patient Connect** (₹499/mo) — WhatsApp automation
  - **Clinic Network** (₹999/mo per branch) — extra branches
- **Bucket-3 features** (Lab, Radiology, Pharmacy, CRM, Incentives, Advanced
  Analytics, AI transcription, Custom branding, Docs vault) are **built but
  hidden** behind `feature_flags` (all off, scope=beta). Flip on per-tenant
  from `/admin/feature-flags` when ready to launch.

**Code:** `App\Support\Plan` (isActive / isInTrial / hasAddon / hasFeatureFlag),
`App\Gates\FeatureGate`, `config/plans.php` (only `standard` is canonical).
**Payments:** Razorpay only. Stripe fully removed (the `/webhooks/stripe` route
returns 410 Gone for one release cycle, then delete).

---

## 3. The visit screen (the heart of the product)

Single-screen, no tabs. Reached at `/visits/{id}`. Two layouts coexist during
rollout:
- **Legacy** tab view (`views/visits/show.php`) — current default.
- **New** single-screen (`views/visits/show_v2.php`) — shown when `?new=1` in
  the URL, or set `ECP_NEW_VISIT_SCREEN=1` in env to make it default for all.

### Layout (new screen, top → bottom)
1. **Sticky patient header** — name, age/gender, phone, ID, visit count, allergies.
2. **Today's visit card** with the **4 fundamentals always visible**:
   - **Symptoms** — chip picker with 3-layer autocomplete (see §4)
   - **Diagnosis** — free text + optional ICD-10 lookup
   - **Prescription** — see §5
   - **Notes & next visit** — free text + voice dictation + follow-up (see §6)
3. **Optional sections** (Vitals, Labs, Photos, Diet, Consent, Case-taking) —
   shown only if in the clinic's `visible_modules`, as collapsible `<details>`.
4. **Ghost-link strip** — "+ Add: Vitals / Labs / …" reveals a hidden section
   for this visit. Reveal a section 3 times → it auto-promotes to always-visible.
5. **Visit history** — list of prior visits below the form.

### Specialty Smart Defaults
On clinic creation, `config/specialty_defaults.php` maps the tenant's specialty →
which optional sections show by default. A homeopath sees only the case form; a
diabetologist sees Vitals + Labs + Diet. Stored per-clinic in
`clinic_settings.visible_modules`; doctors toggle anytime.
**Code:** `App\Support\VisitView`.

### Auto-save & drafts
Form auto-saves every 30s and on tab-blur to `visits.auto_save_data` (status
`draft`). Doctor never loses work. Draft cleanup is a future cron.

### "Same as last visit"
One button clones the previous completed visit's symptoms + prescription into the
current draft. **Code:** `VisitController::cloneLastVisit`.

---

## 4. Symptoms (3-layer)

- **Master** — curated library (`symptoms_master`, ~290 seeded), synonym-aware,
  specialty-ranked. Seed: `document/seeds/phase3_symptoms_seed.sql`.
- **Personal** — auto-learned per doctor (`symptoms_personal`). Custom entries a
  doctor types become their personal suggestions next time.
- **Custom** — free text stored on the visit (`visit_symptoms`).
- **Promotion** — when 10+ doctors use the same custom label (30+ total uses), it
  appears in `/admin/symptom-promotions` for one-click promotion to master.
- **No approval gate** — doctors never wait. Promotion is a background signal.

**Code:** `App\Support\SymptomSearch`, `SymptomsController`.

---

## 5. Prescription builder

- **Inline row**: medicine (autocomplete, ranked by `usage_count`), frequency
  preset (`1-0-1` notation), days, food timing.
- **`⋮` drawer** (per row): dose unit/amount, mix-with, and a **tapering step
  list** ("3 days 1-1-1, then 3 days 1-0-1, …"). When tapering is set, the simple
  frequency/days fields are disabled and a summary chip shows.
- **Templates** — chips above the rows apply a saved combo in one tap. Personal
  + clinic scope. **Auto-discovery**: if a doctor prescribes the same combo 5+
  times in 90 days, a "Save as template?" suggestion appears (weekly cron).
- Legacy `prescriptions.frequency` enum (OD/BD/TDS) is kept in sync for old
  reports; new UI uses `frequency_preset`.

**Code:** `PrescriptionController` (templates + apply + autocomplete),
`App\Support\TemplateDiscovery`, `PrescriptionService::syncForVisit`.

---

## 6. Follow-ups

- Set at the bottom of the Notes card: date chips (`+3d / +5d / +1w / +2w`) +
  custom date + reason dropdown (6 system reasons + custom).
- Canonical store: `follow_ups` (state: pending → done / missed / rescheduled /
  cancelled). Legacy `visits.follow_up_date` kept for back-compat.
- **Dashboard widget** — overdue list + due-this-week + done-this-month.
- **Reception queue badge** — patients with a pending follow-up due ≤7 days show
  a badge in the queue (batched lookup, no N+1).
- **Reminders** — daily cron queues WhatsApp reminders (only if the clinic has
  the Patient Connect add-on). Stale follow-ups (>30 days) auto-marked missed.

**Code:** `App\Services\FollowUpService`, `FollowUpController`.

---

## 7. Diet plans (lightweight)

- 12 system templates (`diet_templates`, seed:
  `document/seeds/phase4_diet_seed.sql`): diabetic, hypertension, weight loss,
  thyroid, PCOS, IBS, low cholesterol, post-surgery, pregnancy, pediatric, gout,
  anemia.
- Apply to a visit → writes the visit's `diet_plans` row. Doctor tweaks, saves a
  personal copy, shares (WhatsApp if Patient Connect, else copy-link fallback).

**Code:** `DietTemplateController`, `DietService`.

---

## 8. Voice input

- Web Speech API (browser-native), language from `clinic_settings.voice_lang`
  (`en-IN` / `hi-IN` / `gu-IN`).
- Wired on the **Notes** field only — dictation appends, doctor edits.
- **No AI transcription** (the `ai_transcription` flag stays off — deferred).

---

## 9. Help page

`/help` — role + module aware. Doctors see clinical sections; reception sees the
operational subset. Sections render only for the clinic's `visible_modules`.
**Code:** `HelpController`, `views/help/index.php`.

---

## 10. Admin (super-admin) area `/admin`

- **Clinics** + per-clinic detail: trial extension, founding toggle, add-on
  management, feature-flag status.
- **Founding clinics** — roster + cap control + "expiring soon" filter.
- **Feature flags** — toggle Bucket-3 features per scope / beta tenant list.
- **Symptom promotions** — promote/ignore custom symptom candidates.
- **Cron triggers** (POST, call from system cron):
  - `/admin/cron/template-discovery` — weekly
  - `/admin/cron/followup-reminders` — daily 09:00 IST
  - `/admin/cron/followup-mark-missed` — daily 03:00 IST
  - `/admin/churn/run` — existing churn job

---

## 11. Database & install

- **Live schema**: `document/silverwebbuzz_in_myclinic(3).sql` (current dump).
- **Fresh install**: `app/database/install.sql` — complete schema including the
  Phase 1–4 section appended at the end. Then run the two seed files in
  `document/seeds/`.
- **Reference seeds** (run after install.sql):
  - `document/seeds/phase3_symptoms_seed.sql` — ~290 master symptoms
  - `document/seeds/phase4_diet_seed.sql` — 12 system diet templates
- Install steps + cron setup: `document/installation-guide.md`.
- Test credentials: `document/internal-tester-credentials.md`.

---

## 12. Key code map

| Area | Location |
|---|---|
| Plan / pricing gates | `app/app/Support/Plan.php`, `app/app/Gates/FeatureGate.php` |
| Visit screen logic | `app/app/Support/VisitView.php`, `app/app/Controllers/VisitController.php` |
| Visit screen view | `app/views/visits/show_v2.php` |
| Symptoms | `app/app/Support/SymptomSearch.php`, `SymptomsController.php` |
| Prescriptions | `PrescriptionController.php`, `PrescriptionService.php`, `App\Support\TemplateDiscovery` |
| Follow-ups | `App\Services\FollowUpService`, `FollowUpController.php` |
| Diet | `DietTemplateController.php`, `DietService.php` |
| Specialty defaults | `app/config/specialty_defaults.php` |
| Routes | `app/routes/web.php` |
| Fresh-install schema | `app/database/install.sql` |
