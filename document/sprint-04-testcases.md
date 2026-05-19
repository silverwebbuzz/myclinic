# Sprint 4 — Test cases

## Prerequisites

- [ ] Sprints 0–3 complete; logged in as admin
- [ ] `composer install` (includes `endroid/qr-code`, `mpdf/mpdf`)
- [ ] Run migration `004_patients_qr_documents.sql`
- [ ] `patients` module active for clinic

## Registration wizard

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 1 | New patient page | GET `/patients/new` | 3-step wizard with step indicator | ☐ |
| 2 | Step 1 validation | Submit without name/phone | Browser validation blocks | ☐ |
| 3 | Draft autosave | Fill step 1, wait 30s, reload | localStorage restores draft | ☐ |
| 4 | Duplicate phone | Register existing phone | Modal warns; can view existing or register anyway | ☐ |
| 5 | Full registration | Complete all 3 steps | Redirect to profile; UHID `PREFIX-00001` format | ☐ |
| 6 | QR card | After register | Print QR opens PDF; PNG in uploads | ☐ |

## Patient list

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 7 | List page | GET `/patients` | Table with pagination (20/page) | ☐ |
| 8 | Live search | Type in search box | Debounced API refresh (~300ms) | ☐ |
| 9 | Filters | Gender, blood, diet, last visit | Filtered results | ☐ |
| 10 | QR scan | Scan QR button + camera | Redirects to patient profile | ☐ |

## Profile

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 11 | Overview tab | Open patient | Photo, UHID, badges, contact info | ☐ |
| 12 | Edit | Edit → save | Data updated; audit log INSERT/UPDATE | ☐ |
| 13 | Regenerate QR | Confirm regenerate | New token; old QR invalid | ☐ |
| 14 | Tabs | Visits, Vitals, Rx, Invoices | Data or empty state | ☐ |
| 15 | Lab tab | Without lab module | Tab hidden or empty | ☐ |

## QR resolve

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 16 | Valid QR | GET `/qr/{token}` same clinic | Redirect `/patients/{id}` | ☐ |
| 17 | Invalid QR | Random token | 404 not found page | ☐ |
| 18 | Wrong clinic | Token for other clinic tenant | Wrong-clinic message | ☐ |

## API

| # | Scenario | Steps | Expected | Pass |
|---|----------|-------|----------|------|
| 19 | Search API | GET `/api/v1/patients/search?q=` | JSON rows + total | ☐ |
| 20 | Check phone | GET `/api/v1/patients/check-phone?phone=` | `{exists, patient?}` | ☐ |

## Sign-off

- Date:
- Notes:
