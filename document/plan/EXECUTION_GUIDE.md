# eClinicPro — Execution Guide

Flat checklist for running all 4 phases. Refer to each phase's `.md` and `.sql` files for full detail.

---

## Before you start

1. Back up the production DB. Full `mysqldump`.
2. Be on a clean git branch.
3. Have phpMyAdmin or MySQL CLI ready.

---

## Phase 1 — Pricing & Cleanup

### Code first (no DB break)
1. Build `App\Support\Plan` helper class.
2. Replace every `if ($tenant['plan'] === 'free')` style check with `Plan::isActive()` / `Plan::hasAddon()` / `Plan::hasFeatureFlag()`. Grep: `grep -rn "\['plan'\]" app/`.
3. Build `FeatureGate` middleware.
4. Deploy. Site still works on old enum — no-op deploy.

### DB
5. Run `phase1_migrations.sql` blocks 1–10.
6. Run Block 11 (founding clinic backfill).

### UI
7. Wrap Bucket-3 nav items with `Plan::hasFeatureFlag()` checks. Hide from sidebar.
8. Remove Bucket-3 dashboard tiles.
9. Rewrite `/pricing` page — one plan + two add-ons + founding clinic banner.
10. Remove plan-picker step from `OnboardingController`.
11. Update admin tenant page (trial extension, founding clinic toggle, add-on management).
12. Build `/admin/feature-flags` and `/admin/founding-clinics`.

### Verify
13. Run Block 12 sanity SELECTs.
14. Manually test one trial, one paid, one founding clinic.
15. Tick every box in `phase1_pricing_and_cleanup.md` §10.

---

## Phase 2 — Visit Screen Redesign

### Code first
1. Create `app/config/specialty_defaults.php`.
2. Build `App\Support\VisitView` helper.
3. Build `VisitController::cloneLastVisit`.
4. Update `VisitController::autosaveApi` for JSON blob.
5. Build `ClinicSettingsController::toggleModule` + `recordSectionState`.
6. Deploy.

### DB
7. Run `phase2_migrations.sql` blocks 1–6.

### UI
8. Rewrite `app/views/visits/show.php` (single-screen). Ship behind `?new=1`.
9. Rewrite `app/views/patients/show.php` — extract header partial.
10. Simplify `app/views/patients/wizard.php` — remove specialty step.
11. Create `assets/js/visit-screen.js` Alpine components.
12. Test with `?new=1` on a friendly tenant.

### Roll out
13. Flip `?new=1` default ON.
14. Build `/admin/specialty-defaults`.
15. Add modules toggle to tenant admin page.
16. Deploy nightly draft cleanup cron.

### Verify
17. Block 7 sanity checks.
18. Test homeopath / GP / dentist see correct cards.
19. Test auto-save, draft reload, "Same as last visit".
20. Delete deprecated code (§8).
21. Tick checklist (§11).

---

## Phase 3 — Symptoms + Rx Builder + Templates

### Code first
1. Build `App\Support\SymptomSearch`.
2. Build `App\Support\TemplateDiscovery`.
3. Create `SymptomsController`.
4. Extend `PrescriptionController` (templates + autocomplete).
5. Extend `VisitController::cloneLastVisit` to include symptoms.
6. Deploy.

### DB
7. Run `phase3_migrations.sql` blocks 1–8.
8. Run `phase3_symptoms_seed.sql` (~500 entries).

### UI
9. `symptomPicker()` Alpine component.
10. `prescriptionRow()` with `[⋮]` drawer.
11. `taperingSteps()` component.
12. `templateApplier()` component.
13. Wire behind `?new_rx=1`, test, flip default ON.

### Admin
14. `/admin/symptom-promotions`.
15. `/admin/symptoms-master` CRUD.
16. Templates tab on tenant admin page.

### Cron
17. `cron/symptoms-promote-candidates` (nightly).
18. `cron/templates-discover` (weekly).
19. `cron/drugs-usage-recount` (weekly).

### Verify
20. Block 9 sanity checks.
21. Test symptom search, custom→personal flow, tapering, template apply.
22. Delete deprecated code (§8).
23. Tick checklist (§11).

---

## Phase 4 — Follow-up + Diet + Help + Voice + Cleanup

### Code first
1. Build `FollowUpController`.
2. Build `DietTemplateController`.
3. Build `HelpController`.
4. Deploy.

### DB
5. Run `phase4_migrations.sql` blocks 1–6.
6. Run `phase4_diet_seed.sql` (12 system templates).

### UI
7. Follow-up section in visit screen's Notes card.
8. Diet card in visit screen (when `diet` in visible_modules).
9. Follow-up widget on dashboard.
10. Follow-up badge on reception queue.
11. Create `app/views/help/index.php`.
12. Mic button on Symptoms input + Diet customize.

### Cron
13. `cron/followup-reminders` (daily 09:00 IST).
14. `cron/followup-mark-missed` (daily 03:00 IST).

### Stripe cleanup
15. Sweep code — remove Stripe from WebhookController, BillingGatewayService, ChurnRiskService, DirectoryService, ChecklistService, RefreshTokenMiddleware.
16. Remove `stripe/stripe-php` from `composer.json`, run `composer update`.
17. Confirm: `grep -rn 'stripe' app/` returns zero.
18. Run Block 7 (drops Stripe columns).

### Rename
19. `grep -rn 'price_monthly_usd' app/ partials/`.
20. Rename all hits to `price_monthly`.
21. Run Block 8.

### Admin
22. `/admin/follow-ups`.
23. `/admin/diet-templates` CRUD.
24. `/admin/feature-flag-usage` evaluation dashboard.

### Verify
25. Block 10 sanity checks.
26. Test create follow-up → dashboard widget → WhatsApp reminder → diet template apply + share.
27. Test Help page (doctor vs reception, hides irrelevant modules).
28. Delete deprecated code (§10).
29. Tick checklist (§13).

---

## After all 4 phases

- `grep -rn "'free'\|'clinic'\|'practice'\|'enterprise'" app/` — should return zero plan strings.
- `grep -rn 'stripe' app/ composer.json` — should return zero.
- Fresh DB backup as new baseline.
- Update CLAUDE.md with new state.

---

## Rules of thumb

- **One phase at a time.** Don't start the next with the previous checklist half-green.
- **Code deploy before DB migration** when additive. Code deploy after DB migration when destructive — but only after code stops reading the dropped thing.
- **Query flags** (`?new=1`, `?new_rx=1`) let you test in production with one tenant.
- **Every SQL block has a rollback comment.** Read it before running.
- **Cron schedules LAST.** Table → code → verify → cron.
- **Save seeds for last.** Empty tables don't break anything.
