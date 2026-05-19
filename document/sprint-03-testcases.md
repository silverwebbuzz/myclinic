# Sprint 3 — Test cases

## Prerequisites

- [ ] Sprints 0–2 complete; onboarding_step = 5
- [ ] Logged in as clinic admin
- [ ] `php -S localhost:8080 -t public`

## Layout shell

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Sidebar modules | Open dashboard | Sidebar shows Dashboard + modules from active `clinic_modules` grouped Clinical / Operations / Reports | ☐ |
| 2 | White-label | Clinic with logo + brand_color | Logo and CSS `--brand` color in header/sidebar | ☐ |
| 3 | Mobile nav | Narrow viewport | Hamburger opens sidebar overlay | ☐ |
| 4 | Avatar menu | Click avatar | Profile links: settings, password, sessions, logout | ☐ |

## Dashboard

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 5 | Stat tiles | Load `/dashboard` | 4 tiles: patients today, pending appointments, revenue today, follow-ups | ☐ |
| 6 | Queue | Appointments today in DB | Listed in Today's queue with time + status | ☐ |
| 7 | Auto-refresh | Wait 60s or call API | `GET /api/v1/dashboard/queue` updates stats/queue | ☐ |
| 8 | Low stock | `pharmacy` module active + low inventory | Amber low-stock widget visible | ☐ |
| 9 | Checklist | Incomplete setup items | Getting started checklist with % until 100% | ☐ |
| 10 | Dismiss checklist | 100% + POST dismiss | Checklist hidden on reload | ☐ |

## Settings

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 11 | Settings tabs | GET `/settings` | Tabs: General, Hours, Specialty, Notifications, Subscription, Team | ☐ |
| 12 | General save | Edit clinic name, save | tenants + specialty_configs updated | ☐ |
| 13 | Hours save | Change hours, save | `working_hours` JSON updated; doctor_schedules synced | ☐ |
| 14 | Specialty save | Change slot duration | `specialty_options` updated | ☐ |
| 15 | Notifications | Toggle + Razorpay keys | `notification_prefs` saved | ☐ |
| 16 | Test buttons | Test WhatsApp / Razorpay | Redirect with success or error message | ☐ |
| 17 | Subscription tab | View tab | Current plan, modules list, saas_invoices | ☐ |
| 18 | Team placeholder | Team tab | Placeholder text for Sprint 7 | ☐ |

## Portal

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 19 | Portal stub | GET `/portal/` with tenant context | Minimal patient portal layout (not admin shell) | ☐ |

## Sign-off

- Date:
- Notes:
