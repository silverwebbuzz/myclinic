# Sprint 10 — Test cases

## Prerequisites

- [ ] Sprints 0–9 complete
- [ ] Run migration `009_sprint10_portal.sql`
- [ ] Modules: `patient_portal`, `telemedicine`, `diet`, `before_after`
- [ ] Patient with phone on file for portal OTP
- [ ] Optional: `TWILIO_*` env vars (dev logs to `storage/logs/sms.log`)

## Patient portal

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Login OTP | `/portal/login` → phone → Send OTP | SMS/log shows code; dev OTP on screen in local | ☐ |
| 2 | Verify | Enter OTP → Verify | Redirect to `/portal/dashboard` | ☐ |
| 3 | Dashboard | View dashboard | Visits, invoices, labs, upcoming appointments | ☐ |
| 4 | Rx download | Click Rx PDF link | 72h signed download works | ☐ |
| 5 | Book link | Book appointment CTA | Opens `/book/{slug}` if scheduling module active | ☐ |
| 6 | Logout | Log out | Cookie cleared; login required again | ☐ |
| 7 | No self-register | Unknown phone | Error: contact clinic | ☐ |

## Telemedicine

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 8 | Online booking | Book appointment type Online | `meet_link` set; WhatsApp queued | ☐ |
| 9 | Confirm | Edit appointment → status Confirmed | Meet link + notifications if missing | ☐ |
| 10 | Portal Meet | Patient dashboard upcoming | Join Meet link visible | ☐ |

## Diet

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 11 | Diet tab | Visit → Diet → fill week → Save | `diet_plans` draft row | ☐ |
| 12 | Homeo warnings | Check include homeo warnings with Rx remedies | Antidote text populated | ☐ |
| 13 | Share | Share PDF + WhatsApp | PDF path; status shared; notification queued | ☐ |

## Before/after photos

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 14 | Upload visit | Visit → Photos → upload before/after | File in `patient_photos` | ☐ |
| 15 | Patient timeline | Patient → Photos tab | Grid + lightbox comparison | ☐ |
| 16 | Public webhook | Upload with is_public | Entry in `storage/logs/photo_webhook.log` or POST to `PHOTO_PUBLIC_WEBHOOK_URL` | ☐ |
| 17 | Inbound webhook | POST `/webhooks/photo-published` | JSON `{received: true}` | ☐ |
