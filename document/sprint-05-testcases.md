# Sprint 5 — Test cases

## Prerequisites

- [ ] Sprints 0–4 complete; logged in as clinic admin
- [ ] `appointments_basic` module active
- [ ] `patients` module active (for booking search)
- [ ] Working hours saved in Settings (generates `doctor_schedules`)
- [ ] At least one patient registered

## Doctor leaves

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Leaves tab | GET `/settings?tab=leaves` | Calendar + add form | ☐ |
| 2 | Add full-day leave | Select doctor, date, full day | Leave appears on calendar | ☐ |
| 3 | Conflict warning | Add leave on date with existing appointment | Warning lists patient names; leave not saved | ☐ |
| 4 | Remove leave | Remove from list | Leave deleted | ☐ |

## Slot engine

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 5 | Slots API | GET `/api/v1/slots?doctor_id=1&date=YYYY-MM-DD` | JSON slots with `available` flags | ☐ |
| 6 | Leave blocks slots | Day with full leave | No slots or blocked in UI | ☐ |
| 7 | Cache invalidate | Book appointment; re-fetch slots | Booked slot unavailable | ☐ |
| 8 | UI refresh | Open book form | Slot grid refreshes every 60s | ☐ |

## Book / edit / cancel

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 9 | Book form | GET `/appointments/new` | Patient search, doctor, date, slots | ☐ |
| 10 | Pre-booked | Book available slot | Success page; slip PDF link | ☐ |
| 11 | Walk-in token | Type walk-in, today | Token number assigned | ☐ |
| 12 | WhatsApp reminder | After book | Row in `notifications` ~24h before visit | ☐ |
| 13 | Edit | Change time on `/appointments/{id}/edit` | Updated; slots invalidated | ☐ |
| 14 | Cancel | Cancel appointment | Status cancelled; cancellation WhatsApp queued | ☐ |

## Queue

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 15 | Staff queue | GET `/queue` | Today's list with token, status | ☐ |
| 16 | Status update | Change status dropdown | Saved; audit log | ☐ |
| 17 | AJAX refresh | Wait 30s | List updates without full reload | ☐ |
| 18 | Display screen | GET `/queue/display?clinic=demo` (no login) | Public board; refreshes ~10s | ☐ |

## Calendar

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 19 | Calendar view | GET `/appointments` | FullCalendar month/week/day | ☐ |
| 20 | Filter doctor | Select doctor | Events filtered | ☐ |
| 21 | Event click | Click event | Opens edit page | ☐ |

## Sign-off

- Date:
- Notes:
