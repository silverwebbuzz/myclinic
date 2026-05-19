# Sprint 9 — Test cases

## Prerequisites

- [ ] Sprints 0–8 complete
- [ ] Run migration `008_sprint9_ops.sql` (adds `users.incentive_flat_fee`)
- [ ] Modules: `analytics`, `crm`, `staff`, `incentives`, `advanced_scheduling`
- [ ] Demo seed includes Sprint 9 modules (re-seed or insert `clinic_modules` manually)

## Analytics

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Snapshots cron | `php workers/analytics_snapshot.php` | Rows in `analytics_snapshots` for yesterday | ☐ |
| 2 | Dashboard charts | GET `/analytics` | 12-mo revenue bar, patient flow line, no-show heatmap | ☐ |
| 3 | P&amp;L | Set date range → apply | Revenue, expenses, profit shown | ☐ |
| 4 | Doctor performance | Table lists doctors with visits + revenue | ☐ |
| 5 | Add expense | Submit expense form | Listed; included in P&amp;L | ☐ |
| 6 | Export | Excel / Tally links | File download or redirect | ☐ |

## CRM

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 7 | Kanban counts | `/crm` status pills | Counts per status | ☐ |
| 8 | Add/edit lead | `/crm/new`, save | Lead in list | ☐ |
| 9 | Convert | Convert lead | Patient created; status `converted` | ☐ |
| 10 | Source chart | Doughnut on CRM page | Breakdown by source | ☐ |
| 11 | Follow-up cron | `php workers/crm_followups.php` | WhatsApp queued for today’s follow-ups | ☐ |

## Staff

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 12 | Clock in/out | `/staff/attendance` | Times recorded for today | ☐ |
| 13 | Monthly report | Change month/year | Attendance rows listed | ☐ |
| 14 | Leave request | Submit leave | Status `pending` | ☐ |
| 15 | Approve/reject | Admin actions | Status updated; WhatsApp to staff | ☐ |

## Doctor incentives

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 16 | Config | `/billing/incentives` save % and flat | Stored on `users` | ☐ |
| 17 | Calculate | Run calculate for month | `doctor_incentives` rows | ☐ |
| 18 | Payslip PDF | Open payslip link | PDF under `/uploads/incentives/` | ☐ |
| 19 | Monthly cron | `php workers/doctor_incentives.php` | Previous month calculated | ☐ |

## Advanced scheduling

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 20 | Schedule config | `/scheduling` add block | Row in `doctor_schedules` | ☐ |
| 21 | Public book | GET `/book/demo` (no login) | Form with slots | ☐ |
| 22 | Book appointment | Submit booking | Appointment created; patient if new | ☐ |
| 23 | Waiting list notify | Cancel appointment with waitlist entry | Patient WhatsApp queued | ☐ |
| 24 | Google Calendar | Scheduling page | Stub message shown (TODO) | ☐ |
