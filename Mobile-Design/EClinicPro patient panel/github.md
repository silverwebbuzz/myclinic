repo: silverwebbuzz/myclinic
branch: main

## Last sync
date: 2026-08-21T06:24:19Z

### Updated in this project
- Built "Doctor App.dc.html" — iOS doctor-panel prototype grounded in document/eclinicpro_doctor_panel_master_prompt.md and SYSTEM_OVERVIEW.md (token queue, visit screen, Rx builder with 1-0-1 presets + templates, follow-ups, schedule, billing)
- Earlier: "Patient App.dc.html" — patient-panel prototype (OTP login, booking + token queue, labs, records, family, promos)

## Screen map
| Screen | Repo files |
| --- | --- |
| Login / OTP | api/patient_auth.php, partials/patient_auth.php |
| Home | patient.php, document/SYSTEM_OVERVIEW.md |
| Find a doctor / profile | find-a-doctor.php, api/search_doctors.php, partials/find-doctor-data.php |
| Booking + queue | api/patient_bookings.php, partials/patient_appointments.php, partials/book_bridge.php |
| Labs | lab.php, lab-listing.php, partials/lab_catalog.php, api/lab_book.php |
| Records (Rx/reports/bills) | api/patient_prescriptions.php, api/patient_lab_reports.php, partials/patient_prescriptions.php |
| Profile & family | api/patient_profile.php, api/family.php, partials/patient_family.php |
| Health reads | fetch_blog/blogs.json, partials/wordpress_blogs.php |
| Doctor: Today/queue | document/eclinicpro_doctor_panel_master_prompt.md, document/SYSTEM_OVERVIEW.md |
| Doctor: Visit screen | app/views/visits/show_v2.php (spec), document/SYSTEM_OVERVIEW.md §3–6 |
| Doctor: Schedule/billing | document/eclinicpro_doctor_panel_master_prompt.md (Priority 2) |
