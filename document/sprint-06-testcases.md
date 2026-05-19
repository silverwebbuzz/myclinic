# Sprint 6 — Test cases

## Prerequisites

- [ ] Sprints 0–5 complete
- [ ] Run migration `005_visit_emr.sql`
- [ ] `emr`, `vitals`, `prescription` modules active
- [ ] `php database/seed.php` (drugs + remedies catalogs)
- [ ] Patient with optional allergies recorded

## Start visit

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | From queue | Queue → Start consultation | Visit created `in_progress`; appointment `in_progress` | ☐ |
| 2 | From patient | Profile → Start visit | New visit opens | ☐ |
| 3 | Resume | Start again same appointment | Opens existing in-progress visit | ☐ |

## Visit screen

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 4 | Tabs visible | Open visit | Vitals, History, Case, Diagnosis, Rx, Consent, Notes, Discharge | ☐ |
| 5 | Auto-save | Edit field, wait 30s | Status shows saved; data persisted | ☐ |
| 6 | Recent visits | Right panel | Last 3 completed visits listed | ☐ |
| 7 | Vitals warnings | Enter BP 180/120 | Non-blocking amber warning | ☐ |
| 8 | Chart | Vitals tab | Sparkline from prior vitals | ☐ |

## Case taking (specialty)

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 9 | GP clinic | Case tab | GP fields (PMH, family, etc.) | ☐ |
| 10 | Homeopathy | Switch specialty, new visit | Homeopathy case fields | ☐ |
| 11 | Derma | Case tab | Body map SVG + notes | ☐ |

## Prescription

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 12 | Drug search | Type drug name in Rx | FULLTEXT results; line added | ☐ |
| 13 | Allergy warn | Patient allergic to drug class | Warning shown (non-blocking) | ☐ |
| 14 | Homeo remedy | Homeopathy clinic | Remedy search + potency | ☐ |
| 15 | ICD-10 | Diagnosis tab search | Common codes listed | ☐ |

## Complete visit

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 16 | Complete | Complete visit → confirm | Redirect patient profile; visit `completed` | ☐ |
| 17 | Appointment | Visit linked to appointment | Appointment `completed` | ☐ |
| 18 | Rx PDF | Complete with Rx lines | PDF at `/uploads/rx/...`; WhatsApp queued | ☐ |
| 19 | EventBus | Check `events` table | `visit.completed` row | ☐ |
| 20 | Read-only | Reopen completed visit | Fields disabled | ☐ |
| 21 | Admin unlock | Owner unlocks | Editable again; audit logged | ☐ |

## API

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 22 | Autosave | POST `/api/v1/visits/{id}/autosave` JSON | `{ok: true}` | ☐ |
| 23 | Drugs API | GET `/api/v1/drugs/search?q=para` | JSON drugs array | ☐ |

## Sign-off

- Date:
- Notes:
