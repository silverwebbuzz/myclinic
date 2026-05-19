# ManageClinic

Multi-tenant clinic SaaS — PHP 8.2, MySQL 8, Redis.

## Setup

```bash
cp .env.example .env
composer install
php database/migrate.php
php database/seed.php
```

## Run locally

```bash
php -S localhost:8080 -t public
```

- Health: http://localhost:8080/health
- API ping (tenant): set `DEV_CLINIC_SLUG=demo` in `.env`, then http://localhost:8080/api/v1/ping

## Auth (Sprint 1)

- Register / login / logout with JWT cookies + CSRF on all POST forms
- Forgot password → email (or `storage/logs/mail.log` without Mailgun)
- Reset password, change password at `/settings/password`
- Multi-device sessions at `/settings/sessions`
- Google OAuth: set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`
- Run migration `003_auth_sessions_oauth.sql` if upgrading an existing DB

## QA & go-live (Sprint 12)

- `composer test` — PHPUnit (SeatService, ModuleGate, QueryBuilder, EventBus, integration, tenant isolation)
- `document/security-audit.md` — OWASP Top 10 checklist
- `document/go-live-checklist.md` — Stripe/Razorpay live, WAF, UptimeRobot, etc.
- `scripts/k6/load-health.js` — 1000 VU load test; results in `document/performance-k6.md`
- `/health` — production checks (db, redis, storage, version)
- `SENTRY_DSN` — error reporting (fallback log if unset)
- GDPR stubs: `GET /patients/{id}/gdpr/export`, `POST /patients/{id}/gdpr/anonymize`
- `workers/backup.sh` — daily MySQL dump (cron)
- `.env.production.example` — production template
- Run migration `011_sprint12_perf_indexes.sql` if k6 p99 &gt; 300ms

## Platform layer (Sprint 11)

- `/admin/login` — super admin (separate JWT secret `SUPERADMIN_JWT_SECRET`)
- `/admin/dashboard` — MRR/ARR Chart.js, churn scan
- `/admin/clinics` — impersonate clinic (30 min token + audit + banner)
- `/doctors` — public SEO directory; `php workers/directory_sync.php` nightly
- `/api/v1/rest/*` — Bearer API keys (Settings → API); OpenAPI at `/docs`
- Enterprise **White-label** tab — custom domain DNS TXT verify, hide “Powered by”
- `php workers/churn_risk.php` — daily churn flags + Mailgun outreach
- Run migration `010_sprint11_platform.sql`

### Custom domain + TLS

1. Add TXT at `_manageclinic.yourdomain.com` with token from Settings → White-label.
2. Click **Check DNS** (auto-verified on `APP_ENV=local`).
3. Point `yourdomain.com` to your app server; use **Caddy** or **certbot** for Let's Encrypt HTTPS.
4. `TenantMiddleware` resolves the clinic via `tenants.custom_domain`.

## Patient portal, telemedicine, diet & photos (Sprint 10)

- `/portal/login` — phone OTP (Twilio or dev log); no self-registration
- `/portal/dashboard` — visits, Rx/lab/invoice downloads (72h signed URLs), book link
- Online appointments → Google Meet link stub + WhatsApp + email
- Visit **Diet** tab — 7-day plan, homeo antidote warnings, PDF + WhatsApp
- Visit **Photos** tab + patient **Photos** timeline with lightbox
- `POST /webhooks/photo-published` — inbound stub for public photos
- Run migration `009_sprint10_portal.sql`

## Analytics, CRM, staff & scheduling (Sprint 9)

- `/analytics` — revenue/expense charts, P&amp;L, doctor performance, expense entry
- `/crm` — leads kanban, convert to patient, source Chart.js report
- `/staff/attendance`, `/staff/leaves` — clock in/out, leave approve/reject
- `/billing/incentives` — doctor % / flat config, monthly calculation, payslip PDF
- `/scheduling` — per-doctor schedule blocks, waiting list, Google Calendar stub
- `/book/{clinic-slug}` — public online booking (no login)
- `php workers/analytics_snapshot.php` — nightly metrics (cron 2 AM)
- `php workers/crm_followups.php` — daily CRM follow-ups (cron 9 AM)
- `php workers/doctor_incentives.php` — monthly incentive run (1st of month)
- Run migration `008_sprint9_ops.sql`

## Lab, pharmacy, consent & discharge (Sprint 8)

- `/lab/catalog`, `/lab/orders` — LIS workflow, barcodes, critical alerts, 24h report share
- `/lab/report/{token}` — public patient report link (no login)
- `/pharmacy/pos`, `/pharmacy/inventory`, `/pharmacy/narcotic` — FIFO POS, batches, H/H1 register
- `/settings?tab=consent-forms` — consent templates with merge fields
- Visit tabs: Lab orders, Consent (canvas + SHA-256 PDF), Discharge (draft → finalize)
- `/portal/discharge/{token}` — patient portal stub
- `php workers/pharmacy_alerts.php` — daily low-stock / expiry alerts (cron 8 AM)
- Run migration `007_lab_pharmacy_consent.sql`

## Billing, notifications & team (Sprint 7)

- `/billing` — invoice list, editor, cash/UPI payment, Excel + Tally export
- `visit.completed` → auto draft invoice with consultation fee
- `/settings?tab=team` — invites, seats, staff roles
- `/accept-invite/{token}` — staff onboarding
- `php workers/notification_worker.php` — cron every 5 min
- `php workers/notification_worker.php --daily` — 7 AM reminders (run via cron)
- Run migration `006_billing_team.sql`

## Visits / EMR (Sprint 6)

- `/visits` — recent visits list
- `/visits/new?appointment_id=` or `?patient_id=` — start consultation
- `/visits/{id}` — EMR screen (vitals, case taking, diagnosis, Rx, auto-save 30s)
- Run migration `005_visit_emr.sql`
- Modules: `emr`, `vitals`, `prescription`

## Appointments & queue (Sprint 5)

- `/appointments` — FullCalendar view
- `/appointments/new` — book with patient search, slot picker, walk-in token
- `/queue` — today's queue (30s AJAX refresh)
- `/queue/display?clinic=SLUG` — public waiting room (no login)
- `/settings?tab=leaves` — doctor leave calendar

## Patients (Sprint 4)

- `/patients` — list, search, filters, QR scanner
- `/patients/new` — 3-step wizard (personal → medical → specialty)
- `/patients/{id}` — profile with tabs
- `/qr/{token}` — tenant-scoped patient lookup
- Run migration `004_patients_qr_documents.sql`

## Dashboard & settings (Sprint 3)

- Authenticated app shell: sidebar (modules from Redis), topbar, toast/modal Alpine components
- `/dashboard` — stat tiles (Redis cache 5 min), today's queue (60s refresh), low stock if pharmacy module, getting-started checklist
- `/settings` — tabs: General, Working hours, Specialty, Doctor leaves, Notifications, Subscription, Team (placeholder)
- `/portal/` — minimal patient-facing layout stub

## Onboarding flow (Sprint 2)

After register/login: `/onboarding/plan-selection` → clinic setup → specialty → notifications → complete → dashboard.

Run migrations `002_onboarding_fields.sql` and `003_auth_sessions_oauth.sql` if upgrading an existing DB.

## Sprint progress

See `document/manageclinic-sprint-prompts.md` for sprint-by-sprint build prompts. Test cases: `document/sprint-00-testcases.md` through `sprint-12-testcases.md`.
