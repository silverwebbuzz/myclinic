# ManageClinic — Installation Guide

Target server: **myclinic.silverwebbuzz.com**
Stack: PHP 8.2, MySQL 8, Redis, Apache (with `mod_rewrite`) or Nginx.

---

## 1. Project verification (already done)

The project is structured correctly:

- `public/` — web root (`index.php` + `.htaccess` rewrite to front controller)
- `app/` — PSR-4 application code under `App\` namespace
- `config/` — module, navigation, plan, middleware config
- `routes/web.php` — HTTP routes
- `database/migrations/` — 11 SQL migrations (`001_schema.sql` … `011_sprint12_perf_indexes.sql`)
- `database/migrate.php`, `database/seed.php` — runners
- `workers/` — background jobs (notifications, analytics, CRM, pharmacy alerts, churn, incentives, backup)
- `views/` — server-rendered templates
- `document/` — specs (HTML plans + sprint test cases)
- `composer.json` — PHP deps (phpdotenv, predis, jwt, oauth2-google, mpdf, phpspreadsheet, qr-code, barcode)
- `.env.production.example` — production env template

HTML files in `document/` are spec/planning documents (`manageclinic_final_complete_plan.html`, `manageclinic_sprint_todo.html`, `manageclinic_user_management.html`) — they are not served by the app; they are reference material.

---

## 2. Server prerequisites

On the cPanel / VPS hosting **myclinic.silverwebbuzz.com**, ensure:

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `zip`, `bcmath`, `intl`, `redis` (if using native), `xml`
- MySQL 8 (database already provisioned — see step 4)
- Redis 6+ (if Redis is not available on shared hosting, the app supports a file-cache fallback through Predis errors — verify with hosting)
- Composer 2.x
- Apache `mod_rewrite` enabled, or Nginx with try_files
- SSL certificate for `myclinic.silverwebbuzz.com` (Let's Encrypt via cPanel AutoSSL)

---

## 3. Upload code

### Option A — via Git (recommended)
```bash
ssh user@silverwebbuzz.com
cd ~
git clone <your-repo-url> myclinic
cd myclinic
composer install --no-dev --optimize-autoloader
```

### Option B — via cPanel File Manager / FTP
Upload the entire project to `~/myclinic` (outside `public_html`). Then run `composer install --no-dev --optimize-autoloader` over SSH.

**Important:** the document root must point to `~/myclinic/public`, not the project root. This protects `.env`, `app/`, `database/`, etc.

In cPanel: **Domains → myclinic.silverwebbuzz.com → Document Root** = `/home/USER/myclinic/public`.

---

## 4. Configure `.env`

```bash
cd ~/myclinic
cp .env.production.example .env
nano .env
```

Set these values:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://myclinic.silverwebbuzz.com
APP_BASE_DOMAIN=myclinic.silverwebbuzz.com
APP_KEY=<generate: openssl rand -hex 16>

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=silverwebbuzz_in_myclinic
DB_USERNAME=silverwebbuzz_in_myclinic
DB_PASSWORD=<your-db-password>   # set in cPanel/Webuzo, NEVER commit the real value

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

JWT_SECRET=<generate: openssl rand -hex 32>
JWT_TTL_MINUTES=15
JWT_REFRESH_TTL_DAYS=30

SUPERADMIN_JWT_SECRET=<generate: openssl rand -hex 32>
SUPERADMIN_JWT_TTL_MINUTES=480
PLATFORM_ADMIN_EMAIL=admin@silverwebbuzz.com
PLATFORM_ADMIN_PASSWORD=<strong password>

# Fill in later when you have keys:
MAILGUN_DOMAIN=
MAILGUN_API_KEY=
MAILGUN_FROM=noreply@silverwebbuzz.com

STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=

SENTRY_DSN=
```

Permissions:
```bash
chmod 640 .env
mkdir -p storage/logs storage/backups
chmod -R 775 storage
```

---

## 4a. JWT secrets — what they do and how to generate

The app uses JWTs (no Laravel sessions) for auth. There are **three** secret keys in `.env`, each protecting a different surface:

| Env var | Used by | What it signs |
|---|---|---|
| `JWT_SECRET` | [app/Services/JwtService.php](../app/Services/JwtService.php), [app/Services/PortalAuthService.php](../app/Services/PortalAuthService.php), [app/Services/SignedDownloadService.php](../app/Services/SignedDownloadService.php) | Staff/doctor access + refresh tokens (cookies), patient portal tokens, signed file download URLs (lab reports, invoices, Rx) |
| `SUPERADMIN_JWT_SECRET` | [app/Services/SuperAdminJwtService.php](../app/Services/SuperAdminJwtService.php) | Platform super-admin session at `/admin/*` — **must be different** from `JWT_SECRET` so a compromised tenant token can't escalate to platform admin |
| `APP_KEY` | encryption helpers | Symmetric encryption of small secrets at rest |

### Generate them (one time, on the server)

```bash
# 32-char app key
openssl rand -hex 16

# 64-char JWT secret (used everywhere a token is signed)
openssl rand -hex 32

# 64-char super-admin secret (must be different from JWT_SECRET)
openssl rand -hex 32
```

Paste each value into `.env`:

```ini
APP_KEY=<output of first command>
JWT_SECRET=<output of second command>
SUPERADMIN_JWT_SECRET=<output of third command>

JWT_TTL_MINUTES=15          # access token lifetime (short, refreshed silently)
JWT_REFRESH_TTL_DAYS=30     # refresh token lifetime (drives "remember me")
SUPERADMIN_JWT_TTL_MINUTES=480   # 8 hours — admin re-login each workday
```

### Rules

- **Never reuse the same secret across `JWT_SECRET` and `SUPERADMIN_JWT_SECRET`.** If you do, the super-admin service falls back to `JWT_SECRET` (see [SuperAdminJwtService.php:61-62](../app/Services/SuperAdminJwtService.php#L61-L62)) and any forged tenant token could become an admin token.
- **Never commit `.env`.** It's already in `.gitignore`.
- **Rotating secrets logs everyone out.** If you must rotate (suspected leak), do it during a maintenance window.
- Min length 32 characters. Shorter keys make HS256 brute-forceable.

---

## 4b. Google login — setup steps

The app supports Google OAuth for staff/doctor login (not patients). It's optional — if `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are empty, the "Sign in with Google" button is hidden ([GoogleOAuthService.php:12-15](../app/Services/GoogleOAuthService.php#L12-L15)).

### Step 1 — Create credentials in Google Cloud Console

1. Go to https://console.cloud.google.com/ → create a project (e.g. "ManageClinic Prod").
2. Open **APIs & Services → OAuth consent screen**:
   - User type: **External**
   - App name: `ManageClinic`
   - User support email: your email
   - App domain: `myclinic.silverwebbuzz.com`
   - Authorized domain: `silverwebbuzz.com`
   - Developer contact: your email
   - Scopes: add `.../auth/userinfo.email` and `.../auth/userinfo.profile` (the app requests `email` + `profile` only — see [GoogleOAuthService.php:39](../app/Services/GoogleOAuthService.php#L39))
   - Publish the app (or keep in Testing and add test users)
3. Open **APIs & Services → Credentials → Create credentials → OAuth client ID**:
   - Application type: **Web application**
   - Name: `ManageClinic Web`
   - **Authorized JavaScript origins**: `https://myclinic.silverwebbuzz.com`
   - **Authorized redirect URIs**: `https://myclinic.silverwebbuzz.com/auth/google/callback`
     (must match exactly — the app builds this URL from `APP_URL` in [GoogleOAuthService.php:19,24](../app/Services/GoogleOAuthService.php#L19-L24))
4. Copy the **Client ID** and **Client secret**.

### Step 2 — Put credentials in `.env`

```ini
APP_URL=https://myclinic.silverwebbuzz.com   # used to build the redirect URI
GOOGLE_CLIENT_ID=<your-client-id>.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=<your-client-secret>
```

### Step 3 — Ensure the DB column exists

The Google login flow stores the Google user ID in `users.google_id`. This column is added by migration `003_auth_sessions_oauth.sql` — already covered when you run `php database/migrate.php`. No extra step.

### Step 4 — Test

1. Visit `https://myclinic.silverwebbuzz.com/login`.
2. Click **Sign in with Google** → redirects to Google → consent → returns to `/auth/google/callback`.
3. First time: links the Google account to a new or existing user (matched by email).
4. Subsequent logins: matched by `google_id`.

### Routes involved

- `GET /auth/google` → starts the flow ([routes/web.php:40](../routes/web.php#L40))
- `GET /auth/google/callback` → exchanges code for token, looks up / creates the user

### Common Google login problems

- **`redirect_uri_mismatch`**: the URI in Google Console must be **exactly** `https://myclinic.silverwebbuzz.com/auth/google/callback` — no trailing slash, must be HTTPS, must match `APP_URL` in `.env`.
- **"App not verified" warning**: normal while the OAuth consent screen is in Testing. Submit for verification, or stay in Testing if usage is internal.
- **`invalid_client`**: Client ID or Secret wrong/swapped in `.env`.
- **State mismatch (silent fail)**: PHP sessions must work — confirm `session.save_path` is writable. The flow uses `$_SESSION['oauth_state']` for CSRF protection ([GoogleOAuthService.php:36,51](../app/Services/GoogleOAuthService.php#L36)).
- **Nothing happens on click**: `GOOGLE_CLIENT_ID` or `GOOGLE_CLIENT_SECRET` is empty in `.env` — the button is hidden.

---

## 5. Database setup

The DB and user are already created in the hosting panel (cPanel / Webuzo):

- Database: `silverwebbuzz_in_myclinic`
- User: `silverwebbuzz_in_myclinic`
- Password: stored in the panel; copy from there into `.env` only — do **not** paste it into this document or any committed file.

Run migrations (creates all tables across all 11 sprints):
```bash
cd ~/myclinic
php database/migrate.php
```

You should see `001_schema.sql` … `011_sprint12_perf_indexes.sql` each printed with `Done.`

**Do NOT run `php database/seed.php` on production** — it inserts demo data. Use it only on staging if needed.

### Optional — load full demo dataset (4 clinics × all plan tiers)

For UAT / scenario testing only. Seeds 4 clinics with realistic data backdated **2026-01-01 → today**:

| Slug | Name | Plan | Doctors | Receptionist | Patients |
|---|---|---|---|---|---|
| `sunrise` | Sunrise Family Clinic | free | 1 | 1 | ~60 |
| `carepoint` | CarePoint Homeopathy | clinic | 1 | 1 | ~250 |
| `wellness` | Wellness Multispecialty | practice | 3 + 2 juniors | 1 | ~600 |
| `metrohealth` | MetroHealth Group | enterprise | 4 + 3 juniors | 2 | ~1200 |

Each clinic gets: doctor schedules, appointments (with completed/cancelled/no-show mix), visits + vitals + ICD-10 diagnoses + Rx, invoices + payments (85% paid / 10% partial / 5% open), monthly expenses, plus per-plan extras (lab orders, pharmacy stock, CRM leads, staff attendance, doctor incentives Jan–Apr, consent + discharge forms, photos, diet plans).

Run:

```bash
composer demo                # seed (skips clinics that already exist)
composer demo -- --wipe      # truncate the 4 demo tenants and reseed
```

Login (all users): password **`Password@123`**. Try `owner@wellness.test` to see the Practice tier with full lab/pharmacy/CRM features.

Prerequisites: `composer seed` must have been run once first (it populates `module_catalog`, drugs, remedies, lab tests). The demo seeder reads from those tables — without them it will print an error and exit.

The seeder is deterministic (`mt_srand(20260101)`) so reruns after `--wipe` produce identical data — useful for comparing app states across testing sessions.

---

## 6. Web server config

### Apache (cPanel default)
`public/.htaccess` already exists and routes everything to `index.php`. Just make sure:
- Document root → `~/myclinic/public`
- `AllowOverride All` is set (cPanel does this by default)

### Nginx (if VPS)
```nginx
server {
    listen 443 ssl http2;
    server_name myclinic.silverwebbuzz.com;
    root /home/USER/myclinic/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/myclinic.silverwebbuzz.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/myclinic.silverwebbuzz.com/privkey.pem;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    location ~ /\.(env|git) { deny all; }
}
```

---

## 7. Verify install

Visit:
- `https://myclinic.silverwebbuzz.com/health` → should return JSON `200` with db/redis/storage checks
- `https://myclinic.silverwebbuzz.com/` → landing / login

Super admin login: `https://myclinic.silverwebbuzz.com/admin/login` (uses `PLATFORM_ADMIN_EMAIL` / `PLATFORM_ADMIN_PASSWORD`).

---

## 8. Cron jobs — what to set and why

Add these via **cPanel → Cron Jobs** (or `crontab -e`). Replace `/home/USER/myclinic` with your real path; use the absolute PHP binary path (often `/usr/local/bin/php` on cPanel, or whichever PHP 8.2 alias the host provides, e.g. `/opt/cpanel/ea-php82/root/usr/bin/php`).

| Schedule | Command | Why |
|---|---|---|
| `*/5 * * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/notification_worker.php` | Sends queued appointment/visit notifications (SMS/WhatsApp/email) every 5 minutes so patients get reminders in near real-time. |
| `0 7 * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/notification_worker.php --daily` | Daily 7 AM digest — tomorrow's appointment reminders to patients and doctors. |
| `0 8 * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/pharmacy_alerts.php` | Daily 8 AM low-stock and drug-expiry alerts to pharmacy staff. Runs before clinic opens so they can reorder. |
| `0 9 * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/crm_followups.php` | Daily 9 AM CRM lead follow-up nudges to sales/front-desk. |
| `0 2 * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/analytics_snapshot.php` | Nightly 2 AM rollup of revenue/visits/expenses into snapshot tables — keeps the `/analytics` dashboard fast (no live aggregation on 100k+ rows). |
| `0 4 * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/churn_risk.php` | Daily 4 AM scan of inactive clinics → flags churn risk and triggers Mailgun outreach. Platform-level retention. |
| `0 3 1 * *` | `/usr/local/bin/php /home/USER/myclinic/workers/doctor_incentives.php` | 1st of each month at 3 AM — calculates doctor incentive payouts (% or flat) from previous month's billing. |
| `30 2 * * *` | `/usr/local/bin/php /home/USER/myclinic/workers/directory_sync.php` | Nightly 2:30 AM rebuild of public `/doctors` SEO directory cache. |
| `0 3 * * *` | `/bin/bash /home/USER/myclinic/workers/backup.sh` | Daily 3 AM `mysqldump` gzip → `storage/backups/`, keeps 14 days. **Critical** — without this, a corrupt write or accidental delete is unrecoverable. |

### Why these specific times?

- **Every-5-min** for notifications: anything longer feels broken to patients ("I booked 10 minutes ago, no SMS").
- **Pre-clinic-hours (2–4 AM)** for heavy jobs (analytics, backup, churn): no user traffic, DB is idle, mysqldump locks are harmless.
- **7–9 AM** for outbound communications: legitimate business hours so SMS/WhatsApp aren't flagged as spam and don't wake patients.
- **1st-of-month** for incentives: matches payroll cycle.
- **Backup at 3 AM, after analytics at 2 AM**: snapshot tables are populated first, so the backup contains the freshest derived data.

---

## 9. Post-install checklist

- [ ] `https://myclinic.silverwebbuzz.com/health` returns 200 with all green
- [ ] `php database/migrate.php` ran clean
- [ ] `.env` is `chmod 640` and outside `public/`
- [ ] All 9 cron jobs added
- [ ] SSL active (padlock in browser)
- [ ] Test register → onboarding → dashboard flow
- [ ] Super admin login works at `/admin/login`
- [ ] Run `workers/backup.sh` once manually to confirm `storage/backups/manageclinic_*.sql.gz` is written
- [ ] Configure Mailgun + Twilio + Stripe/Razorpay keys when ready (see `document/go-live-checklist.md`)
- [ ] Set up UptimeRobot pinging `/health` every 5 min

---

## 10. Troubleshooting

- **500 error on first load**: check `storage/logs/` and Apache error log. Most common — `vendor/` missing (run `composer install`), or `.env` permission denied.
- **`PDOException SQLSTATE[HY000] [1045] Access denied`**: re-check DB credentials in `.env` and that the DB user has ALL PRIVILEGES on `silverwebbuzz_in_myclinic` (cPanel → MySQL Databases → Add user to DB).
- **Redis connection refused**: if shared hosting doesn't offer Redis, ask support to enable it, or run the app without it (some features like module cache will hit DB more often).
- **`mod_rewrite` 404s on routes**: confirm `AllowOverride All` in Apache vhost.
- **Cron not firing**: check output is being logged — append `>> /home/USER/myclinic/storage/logs/cron.log 2>&1` to each cron line.
