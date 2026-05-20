# ManageClinic — QA / Tester Credentials

> **Confidential.** Working credentials for the test environment. Do not share publicly. This file is gitignored via the `document/internal-*` pattern.

---

## Environment

| | |
|---|---|
| **Main URL** | https://myclinic.silverwebbuzz.com/login |
| **Super admin URL** | https://myclinic.silverwebbuzz.com/admin/login |
| **Public booking** | https://myclinic.silverwebbuzz.com/book/wellness |
| **Public queue display** | https://myclinic.silverwebbuzz.com/queue/display?clinic=wellness |
| **Public doctor directory** | https://myclinic.silverwebbuzz.com/doctors |
| **Health check** | https://myclinic.silverwebbuzz.com/health |
| **Common clinic password** | `Password@123` |

The app is multi-tenant. Currently the domain `myclinic.silverwebbuzz.com` is mapped to the **Wellness Multispecialty** clinic (Practice tier). To test a different tier, the domain mapping must be swapped — see the "Swap clinic tier" section at the bottom.

---

## Platform / Super Admin

| URL | Email | Password | Role |
|---|---|---|---|
| `/admin/login` | `admin@manageclinic.com` | `ChangeMe!Admin123` | Super admin — all clinics, MRR/ARR, impersonation |

---

## Tier 1 — Free (Sunrise Family Clinic)

Slug: `sunrise` · 1 doctor · 1 receptionist · ~60 patients
Password for all: **`Password@123`**

| Email | Role | Notes |
|---|---|---|
| `owner@sunrise.test` | Admin/Owner | Full clinic admin |
| `drrohanmehta@sunrise.test` | Doctor | General Practitioner, ₹300 fee |
| `priyashah@sunrise.test` | Receptionist | Front desk |

**Test focus:** patient cap warning (~60/100), no EMR module, no WhatsApp, basic invoicing only.

---

## Tier 2 — Clinic (CarePoint Homeopathy)

Slug: `carepoint` · 1 doctor · 1 receptionist · ~250 patients
Password for all: **`Password@123`**

| Email | Role | Notes |
|---|---|---|
| `owner@carepoint.test` | Admin/Owner | Full clinic admin |
| `dranitasharma@carepoint.test` | Doctor | Homeopathy, ₹500, 10% incentive |
| `anjaliverma@carepoint.test` | Receptionist | Front desk |

**Test focus:** homeopathic Rx mode (remedies + potencies, not allopathic drugs), consent forms, discharge summaries, WhatsApp reminders, full EMR.

---

## Tier 3 — Practice (Wellness Multispecialty) — **currently active on this domain**

Slug: `wellness` · 5 doctors (3 senior + 2 junior) · 1 receptionist · ~600 patients
Password for all: **`Password@123`**

| Email | Role | Specialty / Notes |
|---|---|---|
| `owner@wellness.test` | Admin/Owner | Full clinic admin |
| `drsureshiyer@wellness.test` | Doctor | Cardiology, ₹800, 15% incentive |
| `drasifkhan@wellness.test` | Doctor | Dermatology, ₹700, 12% incentive |
| `drlatharao@wellness.test` | Doctor | Paediatrics, ₹600, 12% incentive |
| `drkavyanairjr@wellness.test` | Doctor | Junior/Resident, ₹400, 5% |
| `drvikrambosejr@wellness.test` | Doctor | Junior/Resident, ₹400, 5% |
| `nehakapoor@wellness.test` | Receptionist | Front desk |

**Test focus:** lab orders + results, pharmacy POS + inventory, CRM funnel, analytics dashboard with 4 months of charts, multi-doctor scheduling, doctor incentives Jan–Apr already calculated.

---

## Tier 4 — Enterprise (MetroHealth Group)

Slug: `metrohealth` · 7 doctors (4 senior + 3 junior) · 2 receptionists · ~1200 patients
Password for all: **`Password@123`**

| Email | Role | Specialty / Notes |
|---|---|---|
| `owner@metrohealth.test` | Admin/Owner | Full clinic admin |
| `drrajeshgupta@metrohealth.test` | Doctor | Orthopaedics, ₹1200, 18% |
| `drmeerajoshi@metrohealth.test` | Doctor | Gynaecology, ₹1000, 15% |
| `drtarundas@metrohealth.test` | Doctor | ENT, ₹900, 15% |
| `drsnehapillai@metrohealth.test` | Doctor | Dermatology, ₹1100, 15% |
| `draakashjainjr@metrohealth.test` | Doctor | Junior, ₹500, 6% |
| `drritusenjr@metrohealth.test` | Doctor | Junior, ₹500, 6% |
| `drmanishyadavjr@metrohealth.test` | Doctor | Junior, ₹500, 6% |
| `poojasingh@metrohealth.test` | Receptionist | Front desk |
| `reemadas@metrohealth.test` | Receptionist | Front desk |

**Test focus:** patient portal, telemedicine, white-label, before/after photos, diet plans, all addons enabled.

---

## What to test per tier

Use this as a starting checklist — not exhaustive.

### Free tier (sunrise)
- [ ] Patient cap warning when adding the 100th patient
- [ ] EMR menu item hidden / blocked
- [ ] WhatsApp option not available on appointment booking
- [ ] Basic invoicing works; export is restricted

### Clinic tier (carepoint)
- [ ] Prescription screen shows remedies (not drugs)
- [ ] Potency picker (6C, 30C, 200C, etc.)
- [ ] Consent form templates
- [ ] Discharge summary draft → finalize → PDF
- [ ] WhatsApp reminder fires on booking

### Practice tier (wellness)
- [ ] Lab catalog (CBC, LFT, KFT, etc.) and order workflow
- [ ] Lab report sharing link (24h token)
- [ ] Pharmacy inventory + POS sale + batch tracking
- [ ] Low stock alerts in dashboard
- [ ] CRM leads kanban, convert to patient
- [ ] Analytics dashboard charts (revenue, visits, P&L)
- [ ] Doctor incentives screen — Jan/Feb/Mar/Apr already calculated
- [ ] Multi-doctor scheduling, leave conflicts
- [ ] Staff attendance clock in/out
- [ ] Public booking page at `/book/wellness`

### Enterprise tier (metrohealth)
- [ ] Patient portal OTP login (OTP appears in `storage/logs/mail.log`)
- [ ] Telemedicine appointment → Google Meet link stub
- [ ] White-label tab in settings
- [ ] Photos timeline + before/after gallery
- [ ] Diet plan editor → PDF
- [ ] All addons visible in settings

### Cross-cutting
- [ ] Super admin can impersonate any clinic (30-min token + banner)
- [ ] Tenant isolation: a doctor in `wellness` cannot see `metrohealth` patients
- [ ] Public doctor directory at `/doctors`
- [ ] QR scan flow at `/qr/{token}`
- [ ] Forgot password → email/log → reset
- [ ] Sessions list at `/settings/sessions`, multi-device logout

---

## Swap clinic tier on this domain

Since the domain only routes to one clinic at a time, run this SQL to switch tiers (via phpMyAdmin or `mysql` CLI on the server):

```sql
-- Replace 'metrohealth' with: sunrise, carepoint, wellness, or metrohealth
UPDATE tenants SET custom_domain = NULL, custom_domain_verified = 0
  WHERE custom_domain = 'myclinic.silverwebbuzz.com';
UPDATE tenants SET custom_domain = 'myclinic.silverwebbuzz.com', custom_domain_verified = 1
  WHERE slug = 'metrohealth';
```

If Redis is running on the server, also clear the tenant cache (or wait ~10 min for it to expire):
```
redis-cli FLUSHDB
```

---

## Notes for the QA

- All seeded data is backdated **2026-01-01 → today (2026-05-19)** — about 140 days of realistic activity. Dashboards and charts should show populated data.
- Phone numbers are in a fake range (`+9170000xxxxx`) so any accidental SMS/WhatsApp send won't reach real humans.
- Photos in `metrohealth` reference placeholder file paths — broken image icons are expected; the DB records exist.
- WhatsApp / SMS / Email notifications are queued but **won't actually send** until Mailgun / Twilio credentials are configured in `.env`. To verify the queue is working, check the `notifications` table or `storage/logs/mail.log`.
- Trial expiry dates were not backdated; trial-related UI may not be exercised by the seed data.
- Patient portal OTPs are generated on demand at `/portal/login` — the OTP appears in `storage/logs/mail.log` since no SMS provider is configured.

---

## Reset to fresh test data

If the QA messes up the data and you want a clean slate:

```bash
cd /home/silverwebbuzz_in/public_html/silverwebbuzzcom/myclinic
php database/seeds/demo_data.php --wipe
```

Takes ~30–60 seconds. Reference data (drugs, remedies, modules, platform admin) is preserved. Only the 4 demo tenants and their data are dropped and recreated. **Note:** after a wipe, you must re-run the `custom_domain` UPDATE SQL above, since the new tenant rows will not have it set.

---

## Bug reports

When filing a bug, please include:
1. Which clinic (slug) and which user email was logged in
2. Exact URL and steps to reproduce
3. Screenshot
4. Browser console errors (F12 → Console tab)
5. Network tab response for any failed request

Server logs are at `storage/logs/` on the production server.
