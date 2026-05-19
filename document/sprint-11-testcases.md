# Sprint 11 — Test cases

## Prerequisites

- [ ] Sprints 0–10 complete
- [ ] Run migration `010_sprint11_platform.sql`
- [ ] `php database/seed.php` (platform admin)
- [ ] Env: `SUPERADMIN_JWT_SECRET`, `PLATFORM_ADMIN_EMAIL`, `PLATFORM_ADMIN_PASSWORD`

## Super admin

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Login | `/admin/login` with platform admin | Dashboard with MRR Chart.js | ☐ |
| 2 | Clinics list | `/admin/clinics` | Plans, MRR, churn flags | ☐ |
| 3 | Impersonate | Impersonate demo clinic | Redirect to app; amber banner; audit log row | ☐ |
| 4 | Exit impersonate | Click Exit on banner | Cookies cleared; back to admin | ☐ |
| 5 | Churn cron | POST Run churn scan on dashboard | Flags updated; emails in mail.log | ☐ |
| 6 | Review mod | `/admin/reviews` approve/reject | Rating recalculated on profile | ☐ |

## Doctors directory

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 7 | Sync cron | `php workers/directory_sync.php` | doctor_profiles + locations populated | ☐ |
| 8 | Index | `/doctors` | Cities + featured doctors | ☐ |
| 9 | City/specialty | `/doctors/mumbai/general-physician` | Doctor list | ☐ |
| 10 | Profile JSON-LD | `/doctors/profile/{slug}` | Physician schema in page source | ☐ |

## REST API v1

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 11 | Create key | Settings → API → scopes → Generate | `mc_live_…` shown once | ☐ |
| 12 | List patients | `GET /api/v1/rest/patients` Bearer key | JSON list | ☐ |
| 13 | Scope denied | Key without `patients:write` → POST patient | 403 insufficient scope | ☐ |
| 14 | OpenAPI | `/docs` and `/docs/openapi.json` | Swagger UI loads spec | ☐ |

## White-label

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 15 | Branding tab | Enterprise clinic → Settings → White-label | Domain + logo + color | ☐ |
| 16 | DNS verify | Start verify → Check DNS (local auto-passes) | `custom_domain_verified=1` | ☐ |
| 17 | Powered by | Enterprise vs free clinic UI | Hidden on Enterprise footer | ☐ |
