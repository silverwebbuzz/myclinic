# Go-live checklist

## Infrastructure

- [ ] Production server / container orchestration ready
- [ ] MySQL 8 primary + automated backups (`workers/backup.sh` in cron)
- [ ] Redis for sessions, rate limits, module cache
- [ ] `php database/migrate.php` through `011_sprint12_perf_indexes.sql`
- [ ] `php database/seed.php` only on staging (not production)
- [ ] `.env` from `.env.production.example` — no secrets in git
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Health monitor: `GET /health` → 200, UptimeRobot or equivalent

## Payments

- [ ] Stripe **live** keys + webhook endpoint `POST /webhooks/stripe`
- [ ] Razorpay **live** keys + webhook `POST /webhooks/razorpay`
- [ ] Test one real subscription upgrade on staging

## Communications

- [ ] Twilio / WhatsApp Business templates approved
- [ ] Mailgun domain verified; `MAILGUN_*` production values
- [ ] Appointment reminder templates match Meta policy

## Security & compliance

- [ ] Cloudflare WAF + TLS
- [ ] OWASP review: `document/security-audit.md`
- [ ] Sentry `SENTRY_DSN` configured
- [ ] GDPR process documented for clinic customers

## Product verification

- [ ] `composer test` passes (or documented skips without DB)
- [ ] k6 baseline recorded in `document/performance-k6.md`
- [ ] Sprint test cases 00–12 spot-checked on staging
- [ ] Custom domain + white-label verified for one Enterprise clinic

## Launch day

- [ ] DNS cutover for `app.manageclinic.com`
- [ ] Super admin `/admin` accessible to ops team only
- [ ] On-call runbook: restore from backup, disable clinic via `is_active=0`
