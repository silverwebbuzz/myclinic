# Sprint 2 — Test cases

## Prerequisites

- [ ] Sprint 1 complete (register, login, JWT)
- [ ] `php database/migrate.php` includes `002_onboarding_fields.sql`
- [ ] Logged in as clinic owner (admin)

## Manual tests

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | Plan selection | GET `/onboarding/plan-selection` | 4 plan cards, monthly/yearly toggle | ☐ |
| 2 | Free plan | Select Free → submit | Redirect `/onboarding/clinic-setup`; `tenants.plan=free`, `onboarding_step=2` | ☐ |
| 3 | Paid plan (dev) | Select Clinic without Stripe keys | Simulated activation → clinic-setup | ☐ |
| 4 | Clinic setup | Fill form, pick specialty, hours, submit | Redirect specialty-config; `specialty_configs` saved | ☐ |
| 5 | Specialty config | GP/homeo/dental fields shown per specialty | Options saved in `specialty_options` JSON | ☐ |
| 6 | Doctor schedules | After specialty step | Rows in `doctor_schedules` for owner/doctors | ☐ |
| 7 | Notifications | Toggle WhatsApp prefs, submit | `onboarding_step=5` path to complete | ☐ |
| 8 | Complete | View summary → Go to Dashboard | `onboarding_completed_at` set; dashboard checklist shown | ☐ |
| 9 | Step guard | Visit `/onboarding/plan-selection` after step 2 | Redirect to current step | ☐ |
| 10 | Stripe webhook | POST `/webhooks/stripe` with test payload | 200 JSON (signature optional if secret empty) | ☐ |

## Sign-off

- Date:
- Notes:
