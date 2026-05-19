# Sprint 8 — Test cases

## Prerequisites

- [ ] Sprints 0–7 complete
- [ ] `composer install` (includes `picqer/php-barcode-generator`)
- [ ] Run migration `007_lab_pharmacy_consent.sql`
- [ ] Modules: `lab`, `pharmacy`, `consent`, `discharge`
- [ ] Demo seed includes lab tests (`database/seeds/lab_tests.php`)

## Lab LIS

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Catalog | GET `/lab/catalog` | Seeded tests with parameters | ☐ |
| 2 | Order from visit | Visit → Lab tab → order test | Redirect to order detail; barcode assigned | ☐ |
| 3 | Sample collected | POST collect on order | Status `sample_collected` | ☐ |
| 4 | Enter results | Enter values; include one critical | Status `resulted`; doctor WhatsApp queued | ☐ |
| 5 | Finalize report | Finalize | PDF path; `share_token`; patient WhatsApp | ☐ |
| 6 | Public share link | GET `/lab/report/{token}` (no login) | PDF redirect or HTML results; expires after 24h | ☐ |
| 7 | Barcode label | GET `/lab/orders/{id}/barcode` | Printable label HTML | ☐ |

## Pharmacy

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 8 | Add batch | `/pharmacy/inventory` → add batch | Stock row created | ☐ |
| 9 | POS FIFO | Sell qty spanning batches | Oldest expiry deducted first | ☐ |
| 10 | Narcotic register | Sell Schedule H drug | Entry in `/pharmacy/narcotic` | ☐ |
| 11 | Daily alerts | `php workers/pharmacy_alerts.php` | Notification queued if low/expiry | ☐ |
| 12 | Dashboard widget | Low stock module active | Dashboard shows low stock tiles | ☐ |

## Consent

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 13 | Template | `/settings?tab=consent-forms` → save | Template listed | ☐ |
| 14 | Sign on visit | Visit → Consent → canvas sign | PDF + SHA-256 hash; verification OK | ☐ |

## Discharge

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 15 | Draft | Visit → Discharge → save | Draft persisted from visit fields | ☐ |
| 16 | Finalize | Doctor signature → finalize | PDF; WhatsApp; portal link | ☐ |
| 17 | Portal stub | GET `/portal/discharge/{token}` | Summary or PDF redirect | ☐ |
