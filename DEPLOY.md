# eClinicPro Deployment Playbook

Consolidated from the whole conversation. Run these on **your cPanel server** (`panel` host you ssh-ed into).

Replace `USER` below with your actual cPanel username (probably `silverwebbuzz_in` or similar — check with `whoami`).

---

## 0. One-time DNS + cPanel setup

In your domain registrar's DNS panel for **eclinicpro.com**, point both:
- `A` record `eclinicpro.com` → your server IP
- `A` record `app.eclinicpro.com` → same server IP (or use a CNAME to `eclinicpro.com`)

In **cPanel → Domains → Domains** (or the older "Addon Domains" + "Subdomains" UIs):
1. Add `eclinicpro.com` as the primary/addon domain. Document root: `/home/USER/public_html/`
2. Add `app.eclinicpro.com` as a subdomain. Document root: `/home/USER/public_html/app/public/`

In **cPanel → SSL/TLS → AutoSSL** (or Let's Encrypt plugin): issue certificates for both `eclinicpro.com` and `app.eclinicpro.com`.

---

## 1. Get the code on the server

```bash
ssh USER@your-server.com

# First time only:
cd ~/public_html
git clone <YOUR-GIT-REPO-URL> .
# OR if you'd rather rsync from your laptop:
#   rsync -avz --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
#         /Users/apple/myclinic/ USER@server:~/public_html/

# Subsequent updates (every deploy):
cd ~/public_html
git pull
```

---

## 2. Composer (PHP dependencies for the portal)

```bash
cd ~/public_html/app
composer install --no-dev --optimize-autoloader
# If composer isn't on PATH on your cPanel server:
#   /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
#   OR ask hosting to enable it.
```

---

## 3. Create writable directories

The portal writes uploads (QR cards, invoice PDFs, prescription PDFs, GDPR zips, etc.) and exports.

```bash
cd ~/public_html/app

# Storage for non-public files (exports, GDPR zips):
mkdir -p storage/exports storage/gdpr
chmod -R 0755 storage

# Public uploads (served directly by Apache):
mkdir -p public/uploads/qr public/uploads/invoices public/uploads/prescriptions public/uploads/photos
chmod -R 0755 public/uploads
```

On cPanel the files you create over SSH are already owned by your cPanel user (which is also the PHP user), so you do **not** need `chown`. Skip the `chown -R www-data:www-data` advice from earlier — that's for plain VPS setups, not cPanel.

---

## 4. .env file (server-side configuration)

```bash
cd ~/public_html/app
cp .env.example .env   # if you have one; else create from scratch below
nano .env
```

Required values:

```env
APP_ENV=production
APP_URL=https://app.eclinicpro.com
APP_BASE_DOMAIN=app.eclinicpro.com

DB_HOST=localhost
DB_DATABASE=silverwebbuzz_in_eclinicpro   # whatever you created in cPanel MySQL
DB_USERNAME=...
DB_PASSWORD=...

JWT_SECRET=<a long random string — generate with: openssl rand -hex 32>
JWT_TTL_MINUTES=15

# Optional:
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
STRIPE_SECRET=...
RAZORPAY_KEY=...
RAZORPAY_SECRET=...
```

Lock down the .env file:

```bash
chmod 0600 ~/public_html/app/.env
```

---

## 5. Database

In **cPanel → MySQL Databases**:
1. Create database `silverwebbuzz_in_eclinicpro` (or whatever you put in DB_DATABASE).
2. Create user, set password, **add user to database with ALL privileges**.

Then load the schema + migrations:

```bash
cd ~/public_html/app

# Initial schema (only if database is empty):
mysql -u DB_USERNAME -p DB_DATABASE < database/install.sql

# Apply all migrations in order:
for m in database/migrations/*.sql; do
  echo "Applying $m"
  mysql -u DB_USERNAME -p DB_DATABASE < "$m"
done

# OR if there's a migrate.php runner:
php database/migrate.php
```

⚠️ The schema includes the new columns from this conversation: `doctor_schedules.extended_end_time` and `specialty_configs.booking_window_days` (migration `012_extended_hours_booking_window.sql`). If your DB was set up before that migration, run just that file:

```bash
mysql -u DB_USERNAME -p DB_DATABASE < database/migrations/012_extended_hours_booking_window.sql
```

---

## 6. (Optional) Seed demo data

```bash
cd ~/public_html/app
php database/seed.php
```

Skip this on production with real clinics.

---

## 7. Verify

In a browser:
- `https://eclinicpro.com` → marketing landing page (index.html)
- `https://eclinicpro.com/pricing` → clean URL works
- `https://app.eclinicpro.com/health` → JSON like `{"status":"ok","checks":{"db":"ok",...}}`
- `https://app.eclinicpro.com/login` → portal login page
- `https://app.eclinicpro.com/book/<clinic-slug>` → public booking wizard

If `/health` says `"storage":"fail"`, re-run step 3.

---

## 8. Common day-2 operations

```bash
# Pull latest code:
cd ~/public_html && git pull

# Re-install composer if dependencies changed:
cd ~/public_html/app && composer install --no-dev --optimize-autoloader

# Apply a new migration:
mysql -u DB_USERNAME -p DB_DATABASE < ~/public_html/app/database/migrations/0XX_whatever.sql

# Clear OPcache (after a deploy, if changes don't show):
# Easiest: cPanel → Restart Services → PHP-FPM
# Or via PHP:
php -r 'opcache_reset();'

# Tail PHP errors when debugging:
tail -n 100 ~/logs/app.eclinicpro.com.error.log
# OR check cPanel → Metrics → Errors

# Clear Redis cache (if a clinic gets stuck on old data):
redis-cli FLUSHDB
```

---

## 9. File structure after this reorganization

```
~/public_html/                     ← eclinicpro.com document root
├── .htaccess                      ← marketing site rewrites + HTTPS + clean URLs
├── index.html                     ← homepage (was "My Clinic.html")
├── book-a-demo.html
├── customer-stories.html
├── features.html
├── for-dentists.html / for-gps.html / for-homeopaths.html / ...
├── pricing.html
├── product-tour.html
├── security.html
├── assets/
│   ├── css/styles.css
│   ├── js/  *.jsx (React via Babel-in-browser)
│   └── img/  (your images)
└── app/                           ← the PHP portal
    ├── public/                    ← app.eclinicpro.com document root
    │   ├── .htaccess              ← front-controller routing + HTTPS
    │   ├── index.php
    │   └── uploads/               ← writable, created in step 3
    ├── app/                       ← PHP source: Controllers, Services, etc.
    ├── views/
    ├── config/
    ├── database/
    ├── routes/web.php
    ├── storage/                   ← writable, created in step 3
    ├── vendor/                    ← composer install fills this
    ├── composer.json
    └── .env                       ← step 4
```

---

## 10. Things that surprised us during this conversation (so we don't repeat)

- **HY093 SQL errors**: never re-use the same named placeholder twice in one prepared statement (e.g. `WHERE name LIKE :q OR phone LIKE :q`). PDO with emulated prepares OFF (the cPanel default) rejects this. Use distinct names: `:q1`, `:q2`, `:q3`, bind each.
- **Tenant resolution**: the portal picks the active clinic from the JWT cookie first, slug only as fallback. If a user lands on the wrong clinic's dashboard, check `users.clinic_id` is correct in the DB.
- **mpdf temp dir**: on shared hosting, mpdf needs a UID-scoped temp directory (`sys_get_temp_dir() . '/mpdf-' . posix_getuid()`). Already in the code.
- **Slot service caches today's slots only for non-today dates** — past slots fall off minute-by-minute, so cache would defeat the purpose.
- **Working hours partial path** was once wrong (`/../partials/...` resolved to project root) — currently fixed.
- **Inline patient creation during booking**: if the staff form has empty `patient_id` and only `new_patient_name + new_patient_phone`, the controller creates the patient first (or reuses by phone) and then books. FK constraint violation = patient_id was 0 because neither was set.

---

## 11. Backup before every deploy

```bash
# DB backup:
mysqldump -u DB_USERNAME -p DB_DATABASE > ~/backups/eclinicpro-$(date +%F-%H%M).sql

# File backup (just the writable dirs + .env):
tar czf ~/backups/uploads-$(date +%F-%H%M).tar.gz \
    ~/public_html/app/public/uploads \
    ~/public_html/app/storage \
    ~/public_html/app/.env
```
