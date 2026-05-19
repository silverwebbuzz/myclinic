# ManageClinic — Sprint-wise build prompts

Master prompt guide for building **ManageClinic** (manageclinic.com) sprint by sprint.  
Source documents: `manageclinic_final_complete_plan.html`, `manageclinic_sprint_todo.html`, `manageclinic_user_management.html`.

---

## How to use this file

1. **Before Sprint 0:** Ensure MySQL 8, Redis, PHP 8.2, Composer, and Node (for Tailwind build if used) are available locally.
2. **Run one sprint at a time:** Copy the **Sprint prompt** block for the current sprint into Cursor Agent. Do not start the next sprint until the current sprint’s testcases pass.
3. **After each sprint:** Create a testcase file:
   - `document/sprint-00-testcases.md` (Foundation)
   - `document/sprint-01-testcases.md` … `document/sprint-12-testcases.md`
4. **Test file format** (create when sprint is done):

```markdown
# Sprint N — Test cases

## Prerequisites
- [ ] ...

## Manual tests
| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | ... | ... | ... | ☐ |

## Automated tests (if any)
- [ ] `vendor/bin/phpunit` — ...

## Sign-off
- Date:
- Notes:
```

5. **Global rules for every sprint** (include mentally or paste with each prompt):

---

## Global build rules (apply to every sprint)

| Area | Rule |
|------|------|
| Stack | PHP 8.2, MySQL 8, Redis, custom MVC (no Laravel/Symfony), Tailwind + Alpine.js |
| Multi-tenant | `{slug}.app.manageclinic.com`; `TenantMiddleware` resolves clinic; **every** DB query uses `QueryBuilder::forClinic($clinicId)` |
| Security | PDO prepared statements only; CSRF on state-changing web routes; JWT in HttpOnly cookies; bcrypt passwords; `htmlspecialchars` on output |
| Modules | `ModuleGate::check('module_id')` before module features; inactive → 402 |
| Specialty | `specialty_configs` + `SpecialtyAdapter` — GP, homeopathy, dental, dermatology, pediatrics, physio |
| Files | PDFs/photos → Cloudflare R2; path `/{clinic_id}/{type}/{id}` |
| Events | `EventBus::fire('event.name', $payload)` for cross-module side effects |
| Seats | Owner (`is_owner=1`) never counts as seat; see `manageclinic_user_management.html` |
| Scope | Only build what the sprint specifies; no drive-by refactors |

**Project layout (target):**

```
/app/{Controllers,Models,Services,Middleware,Gates}
/public/index.php
/views
/config
/routes
/database/migrations
/workers
/storage
```

**Key services:** `SeatService`, `ModuleGate`, `RbacService`, `NotificationService`, `PdfService`, `QrService`, `EventBus`, `StripeService`, `RazorpayService`

**Plans:** Free (2 seats, 100 patients) · Clinic $29 (3 seats) · Practice $79 (8 seats) · Enterprise $199 (unlimited)

---

## Sprint map (20 weeks)

| Sprint | Weeks | Title | Testcase file (create after) |
|--------|-------|-------|------------------------------|
| 0 | Pre-1 | Foundation — DB, router, tenant, EventBus | `sprint-00-testcases.md` |
| 1 | 1–2 | Auth — register, login, logout, password, sessions | `sprint-01-testcases.md` |
| 2 | 2–3 | Onboarding — plan, clinic setup, specialty config | `sprint-02-testcases.md` |
| 3 | 3–4 | Dashboard + layout shell + settings | `sprint-03-testcases.md` |
| 4 | 4–5 | Patient management — registration, QR, profile, search | `sprint-04-testcases.md` |
| 5 | 5–7 | Appointments + queue + doctor scheduling | `sprint-05-testcases.md` |
| 6 | 7–9 | Clinical visit / EMR + vitals + prescription | `sprint-06-testcases.md` |
| 7 | 9–11 | Billing Pro + WhatsApp + team management | `sprint-07-testcases.md` |
| 8 | 11–13 | Lab LIS + pharmacy POS + consent + discharge | `sprint-08-testcases.md` |
| 9 | 13–15 | Analytics + CRM + staff + incentives + advanced scheduling | `sprint-09-testcases.md` |
| 10 | 15–17 | Patient portal + telemedicine + diet + before/after | `sprint-10-testcases.md` |
| 11 | 17–19 | Super admin + directory + REST API + white-label | `sprint-11-testcases.md` |
| 12 | 19–20 | QA, performance, security audit, go-live | `sprint-12-testcases.md` |

---

# Sprint 0 — Foundation

**Duration:** Week 0–1 (before Sprint 1)  
**Goal:** Database, routing, tenant isolation, module cache, EventBus — no user-facing clinical features yet.

## Sprint prompt (copy below)

```
Build ManageClinic Sprint 0 — Foundation only.

CONTEXT: Global clinic SaaS, PHP 8.2 + MySQL 8 + Redis, multi-tenant subdomains.
Apply all Global build rules from manageclinic-sprint-prompts.md.

DELIVERABLES:
1. Composer project: PSR-4 autoload, vlucas/phpdotenv, folder structure as documented.
2. Migrations: all 46 tables in FK-safe order (tenants, module_catalog, clinic_modules, specialty_configs, users, patients, …). Include seat columns on tenants (seat_limit, extra_seats_purchased) and users (is_owner, custom_permissions) and staff_invitations table per user_management doc.
3. Seeds: module_catalog (29 modules + prices), sample drugs (1000+), remedies (500+).
4. Custom router: named routes, groups /api/v1/*, /portal/*, /admin/*, /qr/*; middleware chain TenantDetect → Auth → RBAC → ModuleGate → CSRF → RateLimit.
5. TenantMiddleware: subdomain or custom_domain → tenants row → Redis cache 10min → inject $clinic.
6. ModuleGate: Redis clinic active modules, 402 if inactive, cache invalidation hook.
7. QueryBuilder: PDO wrapper with forClinic() always appending clinic_id.
8. EventBus: fire() inserts events table; subscribers in /config/events.php.
9. GET /health → JSON {status, db, redis, timestamp}.
10. Basic .env.example (no secrets committed).

OUT OF SCOPE: Auth UI, patients, appointments, billing.

When done, list migration files created and how to run them.
```

## Acceptance criteria

- [ ] `php public/index.php` or web server serves `/health` with 200
- [ ] Migrations run clean on empty DB
- [ ] Unit test: `forClinic()` blocks cross-tenant query (or documented test in sprint-00-testcases)

---

# Sprint 1 — Auth

**Duration:** Week 1–2  
**Depends on:** Sprint 0  
**Tags:** PHP 8.2, JWT, bcrypt, MySQL, Mailgun, Redis

## Sprint prompt

```
Build ManageClinic Sprint 1 — Authentication & security foundation.

Prerequisites: Sprint 0 complete (router, tenants, users table, QueryBuilder, Redis).

REGISTRATION (/register):
- Fields: clinic name, email, password, confirm password; client validation (Alpine/vanilla).
- Slug auto-gen from clinic name; GET /api/check-slug?slug= → available/taken.
- Password strength indicator (8+ chars, 1 upper, 1 number).
- Transaction: INSERT tenants (slug, plan=free, seat_limit=2, trial_ends_at=+14d) + users (is_owner=1, role=admin).
- Welcome email via Mailgun; log to notifications table.
- Auto-login: JWT HttpOnly cookie → redirect /onboarding/plan-selection (not dashboard).

LOGIN (/login):
- Email + password; remember me; forgot password link.
- bcrypt verify; JWT 15min + refresh token 30d (HttpOnly cookies, users.remember_token).
- Redis brute force: INCR auth:failed:{email}, TTL 900s; 5 fails → 429; CAPTCHA after 3.
- Redirect: onboarding_step on tenants → plan-selection / clinic-setup / dashboard.
- Google OAuth (league/oauth2-google) optional but scaffold.
- Multi-device sessions in settings (list + sign out others).

PASSWORD:
- /forgot-password — same message always (no enumeration); queue email.
- password_reset_tokens table; Mailgun link /reset-password/{token}; 1hr expiry; SHA-256 hash.
- /reset-password/{token}; invalidate sessions on success.
- /settings/password — change password logged-in.

LOGOUT: POST /logout — clear cookies, null remember_token, redirect /login.

SECURITY:
- CSRF on all POST/PUT/DELETE web forms; exempt JWT API routes.
- RateLimitMiddleware: /login 10/min/IP, /forgot-password 3/hr/IP, /api/* 60/min/JWT.
- Input sanitization; PDO only; htmlspecialchars output.
- audit_log: LOGIN and LOGOUT events.

Do not build onboarding steps beyond redirect targets. Create sprint-01-testcases.md when done.
```

## Task groups (from sprint todo)

- Registration flow (6 tasks)
- Login flow (7 tasks)
- Password management (5 tasks)
- Logout (2 tasks)
- Security essentials (4 tasks)

---

# Sprint 2 — Onboarding

**Duration:** Week 2–3  
**Depends on:** Sprint 1  
**Tags:** Alpine.js, Stripe, Razorpay, PHP, MySQL

## Sprint prompt

```
Build ManageClinic Sprint 2 — Onboarding wizard (signup → working clinic).

Prerequisites: Sprint 1 auth; user lands on /onboarding/plan-selection after register.

STEP 1 — /onboarding/plan-selection:
- 4 plan cards: Free, Clinic $29, Practice $79, Enterprise $199; yearly toggle (-20%).
- Free → UPDATE tenants plan=free, seat_limit=2, onboarding_step=2 → /onboarding/clinic-setup.
- Paid: Stripe Checkout (global) or Razorpay (IN/SG/MY) with 14-day trial.
- Webhooks POST /webhooks/stripe and /webhooks/razorpay — verify signatures; sync tenants.plan + seat_limit.

STEP 2 — /onboarding/clinic-setup:
- Clinic name, address, phone, email; logo upload → R2 /logos/{clinic_id}; specialty grid (6 specialties, single select).
- UHID prefix preview; currency/tax; working hours JSON; consultation fee.

STEP 3 — Specialty config (conditional UI per specialty):
- GP, homeopathy, dental, dermatology, pediatrics, physio — fields per manageclinic_sprint_todo.html.
- Save specialty_configs; prescription_mode; default doctor_schedules from working hours.

STEP 4 — Notifications:
- WhatsApp Business setup (own number or shared); preference toggles; optional Razorpay UPI for patient billing.

STEP 5 — Complete:
- Confetti/summary screen; tenants.onboarding_step=5, onboarding_completed_at=NOW().
- Dashboard getting-started checklist widget.

Module marketplace UI is NOT required this sprint (only plan selection). Create sprint-02-testcases.md when done.
```

---

# Sprint 3 — Dashboard + layout + settings

**Duration:** Week 3–4  
**Depends on:** Sprint 2  
**Tags:** Tailwind, Alpine.js, Chart.js, PHP

## Sprint prompt

```
Build ManageClinic Sprint 3 — Layout shell, dashboard, clinic settings.

Prerequisites: Onboarding complete flag; authenticated admin user.

LAYOUT (views/layouts/base.php):
- Sidebar from active clinic_modules (Redis); grouped Clinical / Operations / Reports; white-label logo + name.
- Topbar: hamburger, title, notification bell, avatar dropdown (profile, settings, logout).
- Responsive: 240px sidebar desktop; overlay tablet; hamburger mobile.
- Global Alpine toast + modal components; skeleton loaders; brand_color CSS variables.

DASHBOARD:
- 4 stat tiles (patients today, appointments pending, revenue today, follow-ups) — Redis cache 5min.
- Today's queue widget; auto-refresh 60s.
- Low stock widget if pharmacy module active (ModuleGate).
- Onboarding checklist until 100%.

SETTINGS (/settings) — tabs:
- General: clinic fields from tenants + specialty_configs.
- Working hours: same as onboarding; regenerate doctor_schedules.
- Specialty settings: onboarding step 3 fields; change specialty warning.
- Notifications: WhatsApp test, toggles, Razorpay test.
- Subscription: current plan, modules, upgrade, seat add, saas_invoices, cancel flow.
- Team tab placeholder OK if Sprint 7 builds full team UI.

Separate layout stub for /portal/* (patient-facing, minimal).

Create sprint-03-testcases.md when done.
```

---

# Sprint 4 — Patient management

**Duration:** Week 4–5  
**Depends on:** Sprint 3  
**Tags:** PHP, MySQL, mPDF, QR, R2, Alpine.js

## Sprint prompt

```
Build ManageClinic Sprint 4 — Patient registration, list, profile, QR system.

3-STEP WIZARD (/patients/new):
- Step 1: personal (name*, phone*, DOB, gender, email, address, blood group, veg type, photo→R2); localStorage draft 30s.
- Step 2: medical history (allergies tags, chronic conditions, surgeries, family, insurance).
- Step 3: specialty_data JSON per specialty (homeo constitution, derma skin type, peds birth data, etc.).
- UHID: {prefix}-{5-digit} with transaction + SELECT FOR UPDATE on uhid_seq.
- qr_token UUID; QR PNG (endroid/qr-code); A6 mPDF card → R2; print button.
- Duplicate phone warning modal (soft block).
- Step indicator + back preserves Alpine state.

LIST (/patients):
- Table paginated 20; sort; live search debounce 300ms GET /api/patients/search.
- Filters: gender, blood, veg, last visit, referring doctor, source.
- QR scan button: jsQR + camera → load profile.

PROFILE (/patients/{id}):
- Header: photo, UHID, badges, actions (edit, book, visit, print QR).
- Tabs: Overview, Visits, Vitals (Chart.js), Prescriptions, Lab/Radiology (ModuleGate), Invoices, Documents.
- Edit modal: 3-step pre-filled; audit log on save.

QR:
- GET /qr/{token} — tenant-scoped redirect or wrong-clinic message.
- Regenerate QR with confirm (invalidates old).

Create sprint-04-testcases.md when done.
```

---

# Sprint 5 — Appointments + queue

**Duration:** Week 5–7  
**Depends on:** Sprint 4  
**Tags:** PHP, MySQL, Redis, Alpine.js, AJAX

## Sprint prompt

```
Build ManageClinic Sprint 5 — Appointments, scheduling, queue.

DOCTOR SCHEDULES:
- Populate doctor_schedules from specialty_configs.working_hours (on onboarding/settings save).
- /settings/leaves — calendar mark full/half day; doctor_leaves; warn existing appointments.

SLOT ENGINE:
- GET /api/slots?doctor_id&date&clinic_id — generate slots, subtract bookings/leaves; Redis cache 5min; invalidate on book/cancel.
- UI: grid of time buttons; refresh 60s.

BOOK APPOINTMENT (/appointments/new):
- Patient search-as-you-type; doctor; date picker; slot picker; type (pre-booked/walk-in/online/follow-up).
- Walk-in: auto token_number for today.
- On save: INSERT appointments; queue WhatsApp reminder -24h; success message + optional slip PDF.
- Follow-up pre-fill from visit screen hook (stub EventBus subscriber OK).
- Edit/cancel with Redis invalidation + cancellation WhatsApp.

QUEUE (/queue):
- Today's appointments all doctors; token, status, actions; AJAX 30s.
- /queue/display — public waiting room screen (no login); 10s refresh.
- Status flow: scheduled → confirmed → in_progress → completed / no_show; audit_log.
- /appointments calendar view (FullCalendar.js or equivalent).

Create sprint-05-testcases.md when done.
```

---

# Sprint 6 — Clinical visit / EMR

**Duration:** Week 7–9  
**Depends on:** Sprint 5  
**Tags:** PHP, MySQL, Chart.js, FULLTEXT, Alpine.js, mPDF

## Sprint prompt

```
Build ManageClinic Sprint 6 — Visit screen, vitals, case taking, prescription (specialty-aware).

VISIT (/visits/new, /visits/{id}):
- Open from queue "Start consultation"; CREATE visits row in_progress.
- Tabs: Vitals | Chief Complaint & History | Case Taking | Diagnosis | Prescription | Consent | Notes | Discharge (lazy AJAX).
- Auto-save every 30s POST /api/visits/{id}/autosave; status indicator.
- Right panel: last 3 visits summary.

VITALS (SpecialtyAdapter):
- Standard: BP, sugar, weight, height, BMI, temp, SpO2, pulse.
- Specialty extras: GP HbA1c; homeo TSH/T3/T4 + skin score; derma BSA; peds head circ + percentile; physio pain/ROM.
- Chart.js sparklines + normal range warnings (non-blocking).

CASE TAKING: GP / homeopathy / dental / dermatology (body map SVG) / physio — per sprint todo.

PRESCRIPTION:
- GP: FULLTEXT drug search; allergy check; interaction check; rows OD/BD/TDS etc.
- Homeo: remedy search; potency; dietary/antidote warnings.
- mPDF Rx PDF → R2; WhatsApp queue rx_delivery.

DIAGNOSIS & COMPLETE:
- ICD-10 AJAX for GP; condition score slider; follow-up date.
- Complete visit: confirm → appointment completed → EventBus visit.completed → redirect patient profile.
- Completed visits read-only; admin unlock with audit.

Consent/discharge tabs may be stubs if Sprint 8 — but tab shell must exist.

Create sprint-06-testcases.md when done.
```

---

# Sprint 7 — Billing + WhatsApp + team

**Duration:** Week 9–11  
**Depends on:** Sprint 6  
**Tags:** PHP, Stripe, Razorpay, mPDF, WhatsApp, Mailgun

## Sprint prompt

```
Build ManageClinic Sprint 7 — Billing Pro, notification worker, team/seats.

BILLING (ModuleGate billing_pro):
- EventBus subscriber visit.completed → draft invoice with consultation fee line.
- /billing/{id} editor: line items, discount %, tax from specialty_configs, live total.
- Razorpay UPI QR + poll /api/check-payment; Stripe PaymentElement; cash payment.
- Invoice mPDF → R2; invoice.paid event.
- /billing list, filters, Excel export (PhpSpreadsheet), Tally XML export.
- Advance payments on patient profile.

WHATSAPP:
- notifications table + worker /workers/notification_worker.php cron */5.
- Daily cron 7AM: appointment reminders 24h/1h; follow_up_reminder.
- Templates: appointment_reminder, rx_delivery, follow_up_reminder, lab_report_ready, invoice_paid (stub sends OK in dev).
- SMS fallback via Twilio if module sms_email active.

TEAM (/settings/team) — per manageclinic_user_management.html:
- SeatService: canAddStaff, getSeatUsage (active staff + pending invites).
- Invite modal → staff_invitations → Mailgun accept-invite email.
- /accept-invite/{token} — set password, create user, auto-login.
- Edit role + custom_permissions JSON; deactivate staff; seat limit UI + upgrade CTA.
- Extra seat Stripe SubscriptionItem webhook → tenants.extra_seats_purchased.

Create sprint-07-testcases.md when done.
```

---

# Sprint 8 — Lab + pharmacy + consent + discharge

**Duration:** Week 11–13  
**Depends on:** Sprint 7  
**Tags:** PHP, mPDF, R2, Barcode

## Sprint prompt

```
Build ManageClinic Sprint 8 — Lab LIS, pharmacy POS, consent forms, discharge summary.

LAB (ModuleGate lab):
- /lab/catalog — test catalog JSON parameters, seeded common tests.
- Order from visit; lab_orders; barcode label mPDF (picqer/php-barcode-generator).
- Lab tech: sample collected, result entry with normal/critical flags; critical → WhatsApp doctor.
- Report PDF mPDF → R2; share_token 24hr + WhatsApp patient.

PHARMACY (ModuleGate pharmacy):
- /pharmacy/pos — FIFO from pharmacy_inventory; cash/UPI.
- /pharmacy/inventory — batches, expiry, low stock.
- Daily cron stock/expiry alerts; dashboard widget.
- Narcotic register for Schedule H/H1 drugs.

CONSENT:
- /settings/consent-forms templates (TipTap merge fields).
- Visit Consent tab: canvas signature, witness, SHA-256 hash, signed PDF R2, verification on view.

DISCHARGE:
- Structured form from visit data; draft → finalized; doctor signature canvas; mPDF → R2; WhatsApp + portal stub.

Create sprint-08-testcases.md when done.
```

---

# Sprint 9 — Analytics + CRM + staff + incentives + scheduling

**Duration:** Week 13–15  
**Depends on:** Sprint 8  
**Tags:** PHP, Chart.js, D3.js, PhpSpreadsheet

## Sprint prompt

```
Build ManageClinic Sprint 9 — Operations intelligence & growth tools.

ANALYTICS (ModuleGate analytics):
- Nightly cron → analytics_snapshots per clinic/day.
- Dashboard: 12-month revenue bar, patient flow line, no-show heatmap (D3.js).
- Revenue & expense tracker + P&L; doctor performance table; Excel/Tally export.

CRM (/crm):
- Leads list + kanban counts; add/edit lead; convert to patient; follow-up cron WhatsApp to staff.
- Source conversion Chart.js report.

STAFF:
- /staff/attendance clock in/out; monthly calendar report.
- Leave requests approve/reject + WhatsApp.

DOCTOR INCENTIVES:
- Config per doctor (% or flat); monthly cron doctor_incentives; /billing/incentives report + payslip PDF.

ADVANCED SCHEDULING (ModuleGate advanced_scheduling):
- Per-doctor schedule config; public /book/{clinic-slug}; waiting_list + cancellation notify cron.
- Google Calendar sync OAuth stub acceptable with TODO if timeboxed.

Create sprint-09-testcases.md when done.
```

---

# Sprint 10 — Portal + telemedicine + diet + photos

**Duration:** Week 15–17  
**Depends on:** Sprint 9  
**Tags:** PHP, Twilio, Google Meet, mPDF, R2

## Sprint prompt

```
Build ManageClinic Sprint 10 — Patient-facing & enterprise differentiators.

PATIENT PORTAL (portal.{slug} or /portal/* layout):
- Login: phone → Twilio OTP (otp_tokens); no self-registration.
- Dashboard: visits, Rx download (signed R2 URL 72h), invoices, lab reports, book appointment if module active.

TELEMEDICINE (ModuleGate telemedicine):
- Online appointment type; Google Meet API create meet_link on confirm; WhatsApp + email delivery; visit screen unchanged.

DIET (ModuleGate diet):
- Visit Diet tab: condition + veg_type + 7-day template; homeo antidote restrictions; mPDF; WhatsApp.

BEFORE/AFTER (ModuleGate before_after):
- Photo upload per visit → R2; patient timeline + comparison lightbox; optional is_public webhook stub.

Create sprint-10-testcases.md when done.
```

---

# Sprint 11 — Super admin + directory + API + white-label

**Duration:** Week 17–19  
**Depends on:** Sprint 10  
**Tags:** PHP, Stripe, SEO, API, Let's Encrypt

## Sprint prompt

```
Build ManageClinic Sprint 11 — Platform layer.

SUPER ADMIN (admin.manageclinic.com — separate JWT secret):
- superadmin login only; clinics list (MRR, plan, churn flags); MRR dashboard Chart.js.
- Impersonate clinic one-time token 30min + audit + banner.
- Churn risk cron + Mailgun outreach.

DOCTORS DIRECTORY (manageclinic.com/doctors):
- Nightly cron populate doctor_profiles from doctors.
- SEO routes /doctors/{city}/{specialty} and doctor profile JSON-LD.
- Reviews moderation; featured listing Stripe (optional stub).

REST API v1 (api.manageclinic.com/v1):
- /settings/api — API keys bcrypt, scopes.
- Bearer middleware; core endpoints patients, appointments, visits, invoices.
- OpenAPI/Swagger stub at /docs.

WHITE-LABEL (Enterprise):
- Custom domain DNS verify + Let's Encrypt note in README; brand_color + logo; hide powered-by on Enterprise.

Create sprint-11-testcases.md when done.
```

---

# Sprint 12 — QA & go-live

**Duration:** Week 19–20  
**Depends on:** All sprints  
**Tags:** PHPUnit, k6, OWASP, Sentry

## Sprint prompt

```
Build ManageClinic Sprint 12 — QA, hardening, go-live checklist (no new features).

TESTING:
- PHPUnit: SeatService, ModuleGate, QueryBuilder forClinic, EventBus — 80% Services coverage target.
- Integration test: register → onboarding → patient → appointment → visit → Rx → invoice → payment → notification queued.
- Cross-specialty smoke: GP, homeopathy, dental, dermatology visit flows.
- Multi-tenant isolation test: Clinic A cannot access Clinic B resources (404/403).

SECURITY:
- OWASP Top 10 checklist document in /document/security-audit.md.
- File upload MIME/size/UUID names; session cookie flags documented.
- GDPR stubs: export ZIP + anonymize patient PII.

PERFORMANCE:
- k6 script 1000 VU 10min — document results; add indexes if p99 > 300ms.

GO-LIVE:
- /health production-ready; backup cron documented; Sentry integration; .env.production.example.
- Checklist: Stripe live, Razorpay live, WhatsApp templates, Cloudflare WAF, UptimeRobot — as markdown checklist in document/go-live-checklist.md.

Create sprint-12-testcases.md when done.
```

---

## Reference: seat model & RBAC (Sprint 7+)

From `manageclinic_user_management.html`:

- **Owner** (`is_owner=1`): free, full access, cannot be removed.
- **Seats:** `effective_limit = seat_limit + extra_seats_purchased`; usage = active non-owner users + pending non-expired invitations.
- **Roles:** admin (owner), doctor, nurse, receptionist, labtech — permission keys in `RbacService::can($user, 'permission.key')`.
- **SQL:** See user management doc for `ALTER tenants`, `ALTER users`, `CREATE staff_invitations`, full `SeatService` sample.

---

## Reference: specialty modes

| Specialty | Key differentiators |
|-----------|---------------------|
| GP | Drugs DB, ICD-10, standard Rx |
| Homeopathy | Remedies, potency, case-taking fields, antidote/diet warnings |
| Dental | Tooth chart FDI/Universal, procedure codes |
| Dermatology | Body map, skin score 1–10, before/after |
| Pediatrics | Growth charts, vaccine schedule |
| Physiotherapy | ROM, pain scale, exercise library |

Stored in `patients.specialty_data` and `visits` clinical JSON columns; UI via `SpecialtyAdapter`.

---

## Deferred (Phase 2 — do not build in Sprints 0–12)

Hospital modules (IPD, OT, blood bank, TPA, ABDM, NABH, etc.) and full HR/Payroll. Schema may exist; features wait until post-launch.

---

## Next step

**Start with Sprint 0** — paste the Sprint 0 prompt into Agent, implement, then create `document/sprint-00-testcases.md` and run through every checkbox before Sprint 1.
