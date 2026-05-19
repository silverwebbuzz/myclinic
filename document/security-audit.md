# Security audit — OWASP Top 10 (ManageClinic)

Last reviewed: Sprint 12 go-live prep.

| # | Risk | Status | Notes |
|---|------|--------|-------|
| A01 | Broken access control | Mitigated | `QueryBuilder::forClinic()`, RBAC middleware, module gates; multi-tenant tests in `tests/MultiTenantIsolationTest.php`. |
| A02 | Cryptographic failures | Mitigated | Passwords `PASSWORD_BCRYPT`; API keys SHA-256; JWT HS256; secrets in `.env` only. |
| A03 | Injection | Mitigated | PDO prepared statements via QueryBuilder; no raw SQL from user input in services. |
| A04 | Insecure design | Partial | Impersonation audited; rate limit middleware on auth routes. |
| A05 | Security misconfiguration | Review | Set `APP_ENV=production`, `APP_DEBUG=false`; rotate all secrets. |
| A06 | Vulnerable components | Ongoing | Run `composer audit` before release. |
| A07 | Auth failures | Mitigated | Login lockout via Redis; CSRF on forms; refresh token rotation. |
| A08 | Data integrity failures | Partial | Webhook signatures for Stripe/Razorpay when configured. |
| A09 | Logging failures | Partial | `audit_log`, Sentry via `SENTRY_DSN`, local fallback `storage/logs/sentry.log`. |
| A10 | SSRF | Low | Outbound curl limited to configured webhooks (Mailgun, Twilio, Sentry). |

## Session cookies

| Cookie | Flags (production) | Purpose |
|--------|-------------------|---------|
| `mc_token` | `HttpOnly`, `Secure`, `SameSite=Strict`, path `/` | JWT access token |
| `mc_refresh` | `HttpOnly`, `Secure`, `SameSite=Strict`, path `/` | Refresh token |
| `mc_sa_token` | `HttpOnly`, `Secure`, `SameSite=Strict`, path `/admin` | Super admin JWT |

Set `APP_ENV=production` so `JwtService` and `SuperAdminJwtService` set `secure => true`.

## File uploads

Centralized in `App\Support\UploadValidator`:

- MIME sniff via `finfo` (not client extension).
- Size limits: logos/patient photos 2 MB; visit photos 5 MB.
- Stored filenames: UUID + extension (no user-supplied names).
- Allowed types: JPEG, PNG, WebP (context-dependent).

## GDPR stubs

- Export: `GET /patients/{id}/gdpr/export` (admin/owner) → ZIP under `storage/exports/`.
- Anonymize: `POST /patients/{id}/gdpr/anonymize` — scrubs PII, deactivates patient.

## Pre-production checklist

- [ ] Rotate `JWT_SECRET`, `SUPERADMIN_JWT_SECRET`, `APP_KEY`
- [ ] Enable HTTPS termination (Let's Encrypt)
- [ ] Cloudflare WAF in front of public endpoints
- [ ] Restrict `/admin` by IP or VPN if required
- [ ] Disable directory listing on `public/uploads/`
- [ ] Backup cron enabled (`workers/backup.sh`)
