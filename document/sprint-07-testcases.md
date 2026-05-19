# Sprint 7 — Test cases

## Prerequisites

- [ ] Sprints 0–6 complete
- [ ] `composer install` (includes `phpoffice/phpspreadsheet`)
- [ ] Run migration `006_billing_team.sql`
- [ ] Modules: `billing_pro`, `whatsapp` (optional: `sms_email`)
- [ ] Set `consultation_fee` in Settings → General or specialty config

## Billing

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Draft on visit complete | Complete a visit | Draft invoice in `/billing` with consultation line | ☐ |
| 2 | Edit invoice | Open `/billing/{id}` | Line items, discount %, tax %, live total | ☐ |
| 3 | Apply advance | Patient with advance → check Apply advance → save | `advance_paid` updated | ☐ |
| 4 | Cash pay | Cash payment button | Status `paid`; PDF path; `invoice.paid` event | ☐ |
| 5 | Razorpay dev | Generate UPI → Simulate pay | Invoice paid | ☐ |
| 6 | Check payment API | GET `/api/v1/billing/{id}/check-payment` | JSON `{paid: bool}` | ☐ |
| 7 | Export Excel | `/billing/export/excel` | File download | ☐ |
| 8 | Tally XML | `/billing/export/tally` | XML download | ☐ |

## Patient advance

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 9 | Record advance | Patient → Invoices tab → amount | `advance_balance` increases | ☐ |

## WhatsApp / notifications

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 10 | Worker | `php workers/notification_worker.php` | Queued rows → `sent`; log in `storage/logs/whatsapp.log` | ☐ |
| 11 | Daily cron | `php workers/notification_worker.php --daily` | Reminders queued | ☐ |
| 12 | Templates | Book appointment / complete visit with Rx | Rows in `notifications` table | ☐ |

## Team

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 13 | Seat usage | Settings → Team | Used/limit displayed | ☐ |
| 14 | Invite | Invite staff (within limit) | Email/log; pending invite listed | ☐ |
| 15 | Accept invite | Open `/accept-invite/{token}` | Set password → logged in | ☐ |
| 16 | Seat limit | Invite when full | Error + upgrade CTA | ☐ |
| 17 | Edit staff | Change role / deactivate | Saved | ☐ |
| 18 | Revoke invite | Revoke pending | Status revoked | ☐ |

## Sign-off

- Date:
- Notes:
