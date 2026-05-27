# Phase 2 — Visit Screen Redesign + Specialty Smart Defaults

**Goal:** Replace the current 10-tab visit screen with a single-screen layout inspired by Dr. Feelgood. Make the screen specialty-aware so each clinic sees only relevant cards by default, but never feels locked in.

**Why this is Phase 2:** Phase 1 set up the data foundation (clinic_settings, plan, feature flags). Now we redesign the screen that doctors actually spend 90% of their day on. Symptoms (Phase 3) and follow-up workflows (Phase 4) plug into the structure built here.

**Dependency on Phase 1:** This phase reads `clinic_settings.visible_modules` (created in Phase 1 Block 6). If Phase 1 hasn't run, do not start Phase 2.

---

## 1. Design philosophy (locked in)

From the strategy discussions:

1. **One screen per patient.** No tabs. No separate visit-edit page.
2. **4 fundamentals always visible:** Symptoms · Diagnosis · Prescription · Notes.
3. **Optional sections collapsed:** Vitals, Labs, Photos, Diet, Consent, Custom.
4. **Specialty Smart Defaults:** auto-pick which optional sections appear based on `tenants.specialty`. Doctor can override individually.
5. **Ghost-link pattern** for hidden sections: small "+ Add vitals" link at bottom reveals card for THIS visit only.
6. **Section state memory:** if doctor expanded Vitals 3 times in a row → auto-show it from now on.
7. **"Same as last visit"** button on the Prescription card.
8. **Auto-save drafts** — silent, 30s debounced.
9. **Single-screen mobile-first.** Form on top, history below. Side-by-side ONLY ≥1280px.

---

## 2. Audit of existing infrastructure

### Tables we KEEP

| Table | Purpose | Phase 2 changes |
|---|---|---|
| `visits` | One row per consultation. Already has chief_complaint, history, examination, diagnosis, icd10_code, clinical_notes, follow_up_date, specialty_data JSON. | Add `draft` status, `auto_save_data` JSON, `last_autosave_at` |
| `prescriptions` | One row per medicine on a visit. | Add `frequency_preset` (1-0-1 notation), `tapering_steps` JSON, `dose_unit`, `mix_with`, `food_timing` |
| `vitals` | Per-visit vitals — already comprehensive (BP, sugar, weight, temp, SpO2, pulse, TSH/T3/T4, extra_vitals JSON) | None — already good |
| `drugs` / `remedies` | Master medicine tables | None — Phase 3 may add usage_count for ranking |
| `clinic_settings` | Per-clinic UI prefs (Phase 1) | Populate `visible_modules` from specialty defaults |
| `specialty_configs` | Per-clinic clinical config (already exists) | None — keep as-is |

### Existing views we KEEP

| File | Purpose | Phase 2 action |
|---|---|---|
| `app/views/visits/partials/case_gp.php` | GP case form fields | Reuse — pull into new layout |
| `app/views/visits/partials/case_dental.php` | Dental case fields | Reuse |
| `app/views/visits/partials/case_derma.php` | Derma case fields | Reuse |
| `app/views/visits/partials/case_homeopathy.php` | Homeopathy fields | Reuse |
| `app/views/visits/partials/case_physio.php` | Physio fields | Reuse |
| `app/views/visits/partials/lab.php` | Lab orders panel | Reuse — gated by `lab_module` flag |
| `app/views/visits/partials/photos.php` | Photo upload | Reuse |
| `app/views/visits/partials/diet.php` | Diet attachment | Reuse — refined in Phase 4 |
| `app/views/visits/partials/consent.php` | Consent form | Reuse |
| `app/views/visits/partials/discharge.php` | Discharge summary | Reuse |

### Views we REPLACE

| File | Status |
|---|---|
| `app/views/visits/show.php` | **Rewrite.** 380+ lines of tab logic. Replaced with single-screen layout. |
| `app/views/patients/show.php` | **Rewrite.** Currently a separate "patient profile" page. Merged into the new visit screen — patient profile becomes the *header* of the visit screen, like Dr. Feelgood. |
| `app/views/patients/wizard.php` | **Keep, but simplify.** 409 lines. Wizard collapses to a single create-patient form. The wizard's specialty step (`_wizard_specialty.php`) is removed — specialty data is now collected inline in the visit screen via `specialty_data` JSON, not at registration. |

### Controllers we MODIFY

| Controller | Phase 2 changes |
|---|---|
| `VisitController` | New `autosave` API. New `cloneLastVisit` action. Section-state memory updates. |
| `PatientController` | `show()` now redirects to the new combined patient+visit screen, or renders it directly. |
| `PrescriptionController` | New tapering schedule support. Frequency preset handling. |
| `ClinicSettingsController` | New endpoint to toggle individual module visibility. |

### Controllers we ARE NOT TOUCHING in Phase 2

`AppointmentController`, `QueueController`, `BookController`, `BillingController`, `SettingsController`, `StaffController`, etc. — they're orthogonal to the visit screen.

---

## 3. The Specialty Smart Defaults — the core decision

This is a **config file**, not a database table. Stored at `app/config/specialty_defaults.php`. Format:

```php
return [
    // Each specialty maps to the modules that should be VISIBLE by default
    // when a clinic of this specialty is created. Doctor can toggle anytime.
    //
    // Available module keys:
    //   vitals, labs, photos, diet, consent, case_specialty
    //   (symptoms, diagnosis, prescription, notes are ALWAYS visible)

    'gp'            => ['vitals', 'case_specialty'],
    'family_medicine'=> ['vitals', 'case_specialty'],
    'peds'          => ['vitals', 'case_specialty'],
    'gyno'          => ['vitals', 'case_specialty'],
    'cardio'        => ['vitals', 'labs', 'case_specialty'],
    'diabetology'   => ['vitals', 'labs', 'diet', 'case_specialty'],
    'endocrinology' => ['vitals', 'labs', 'case_specialty'],
    'nephrology'    => ['vitals', 'labs', 'case_specialty'],

    'derma'         => ['photos', 'case_specialty'],
    'cosmetology'   => ['photos', 'consent', 'case_specialty'],
    'trichology'    => ['photos', 'case_specialty'],
    'plastic_surgery'=> ['photos', 'consent', 'case_specialty'],

    'dental'        => ['photos', 'consent', 'case_specialty'],
    'orthodontist'  => ['photos', 'consent', 'case_specialty'],
    'endodontist'   => ['photos', 'consent', 'case_specialty'],
    'implantologist'=> ['photos', 'consent', 'case_specialty'],
    'prosthodontist'=> ['photos', 'consent', 'case_specialty'],
    'pediatric_dentist' => ['photos', 'consent', 'case_specialty'],

    'ortho'         => ['photos', 'case_specialty'],
    'spine'         => ['photos', 'case_specialty'],
    'sports_medicine'=> ['vitals', 'case_specialty'],

    'eye'           => ['case_specialty'],
    'ent'           => ['case_specialty'],

    'physio'        => ['case_specialty'],
    'psychologist'  => ['case_specialty'],
    'psychiatrist'  => ['case_specialty'],
    'speech'        => ['case_specialty'],
    'audiologist'   => ['case_specialty'],

    'homeo'         => ['case_specialty'],
    'ayurveda'      => ['case_specialty'],
    'siddha'        => ['case_specialty'],
    'unani'         => ['case_specialty'],
    'naturopathy'   => ['case_specialty'],
    'acupuncturist' => ['case_specialty'],

    'dietitian'     => ['vitals', 'diet'],

    // Surgery specialties — all get consent by default
    'general_surgery'=> ['vitals', 'consent', 'case_specialty'],
    'neurosurgery'  => ['vitals', 'consent', 'case_specialty'],
    'gi_surgery'    => ['vitals', 'consent', 'case_specialty'],
    'bariatric'     => ['vitals', 'consent', 'case_specialty'],
    'vascular'      => ['vitals', 'consent', 'case_specialty'],

    // Fallback for anything not listed
    '__default'     => ['vitals', 'case_specialty'],
];
```

**Why a PHP config file and not a DB table:**
- Specialty defaults rarely change. Diff in git is more valuable than admin UI for editing.
- Loading the config is a single `require` — no DB round trip on every visit page load.
- Adding a new specialty = add one line to the file, no migration.

**Per-clinic override path:** when a clinic toggles a module on/off in settings, we write the result to `clinic_settings.visible_modules` as a JSON array. The visit screen reads `clinic_settings.visible_modules` first; if NULL, falls back to the specialty default.

**On clinic creation:** `OnboardingController` looks up the tenant's specialty in the config file and copies the array into `clinic_settings.visible_modules`. From then on, that clinic's preferences live in the DB.

---

## 4. The new single-screen layout (final)

```
┌────────────────────────────────────────────────────────────────────┐
│ ▸ Back to patient list                                             │
│                                                                    │
│ ┌──── PATIENT HEADER (sticky on scroll) ──────────────────────────┐│
│ │ [B]  BHAVIKBHAI KORADIYA · 37y · Male · 9374249829              ││
│ │      ID 11886 · 12 visits · Reg 16 Sep 2022                     ││
│ │      Chief complaint: Recurring indigestion ▾    [Edit] [Show]  ││
│ └─────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ ┌──── TODAY'S VISIT ──────────────  [💾 Auto-saved 12s ago] ─────┐│
│ │                                                                ││
│ │ Date: [27/05/2026]                  Amount (₹): [500]          ││
│ │                                                                ││
│ │ ┌── SYMPTOMS ─────────────────────────────────────────────────┐││
│ │ │ [Fever ×] [Cough ×] [Body ache ×]      + Add symptom...     │││
│ │ └────────────────────────────────────────────────────────────┘ ││
│ │                                                                ││
│ │ ┌── DIAGNOSIS ─────────────────────────────────────────────── ┐││
│ │ │ Viral fever                                       [ICD-10 ▸]│││
│ │ └────────────────────────────────────────────────────────────┘ ││
│ │                                                                ││
│ │ ┌── PRESCRIPTION ───────────────  [↻ Same as last visit] ─── ┐││
│ │ │ ▸ Apply template: [Fever 5-day] [Custom ▾]                  │││
│ │ │                                                             │││
│ │ │ • Paracetamol 500mg · 1-0-1 · 5d · After food          [⋮] │││
│ │ │ • Cetirizine 10mg · 0-0-1 · 3d · After food            [⋮] │││
│ │ │ + Add medicine...                                           │││
│ │ └────────────────────────────────────────────────────────────┘ ││
│ │                                                                ││
│ │ ┌── NOTES & NEXT VISIT ───────────────────────────────────── ┐││
│ │ │ Follow-up in 5 days if fever persists                       │││
│ │ │ Next visit: [+3d] [+5d] [+1w] [Custom date]                 │││
│ │ │                                                  [🎙 Voice] │││
│ │ └────────────────────────────────────────────────────────────┘ ││
│ │                                                                ││
│ │ ── Optional sections (specialty-defaults shown below) ──       ││
│ │                                                                ││
│ │ ▾ VITALS (expanded — specialty default for GP)                 ││
│ │   BP: [120]/[80]  · Pulse: [72] · Temp: [98.6] · Wt: [65]      ││
│ │   ...                                                          ││
│ │                                                                ││
│ │ ▸ CASE: GP HISTORY (collapsed)                                 ││
│ │                                                                ││
│ │ + Add: Vitals · Labs · Photos · Diet · Consent · Custom field  ││
│ │                                                                ││
│ │                                            [💾 Save Visit]     ││
│ └────────────────────────────────────────────────────────────────┘│
│                                                                    │
│ ┌──── VISIT HISTORY ────────────────────────────  12 total ──────┐│
│ │  17 Apr 2026 — Indigestion · BRY-30  ₹1000           [Open]    ││
│ │   ↳ Come again next week                                       ││
│ │  15 Apr 2026 — Indigestion · BRY-30  ₹500            [Open]    ││
│ │   ↳ Come after 2 days                                          ││
│ │  ...                                                           ││
│ └────────────────────────────────────────────────────────────────┘│
└────────────────────────────────────────────────────────────────────┘
```

### Key behaviors

**Sticky header.** Patient identity stays visible as the doctor scrolls through the form. Phone number tappable. "Show" button expands the full patient info card.

**Auto-save indicator.** Top-right of the visit card. States: `Saving...` → `Auto-saved 12s ago` → idle. Color-coded subtly (gray idle, blue saving, green saved). Never blocks the form.

**Same as last visit button.** Top-right of the Prescription card. Tooltip: *"Copy medicines from your previous visit on 17 Apr"*. Click → all medicines from prior visit populate the form (editable). A small badge appears: *"Cloned from 17 Apr · Undo"*.

**Ghost-link strip.** Just before the Save Visit button. Lists modules NOT currently visible. Click → reveals the section for this visit only. Next visit, hidden again (unless used 3+ times in a row, then auto-promote to permanent).

**Section state memory.** When the doctor expands/collapses a section, we POST to `/api/clinic-settings/section-state` with `{section: "vitals", state: "expanded"}`. After 3 consecutive expansions, the system auto-promotes to "always expanded for this clinic." Stored in `clinic_settings.section_state` JSON.

**Voice button.** On the Notes textarea only (Phase 2 scope). Tap → Web Speech API listens → text appends to notes. Phase 3 may add it to Symptoms.

**Mobile layout.** Same components, vertical stack. Patient header sticky. Visit form full-width. History below, scroll-revealed. Side-by-side ONLY ≥1280px (wide laptop / desktop).

---

## 5. Database changes

Full SQL in `phase2_migrations.sql`. Below is the rationale.

### 5.1 Extend `visits` for drafts

```sql
ALTER TABLE visits
  MODIFY COLUMN status ENUM('draft','in_progress','completed','cancelled')
    NOT NULL DEFAULT 'draft',
  ADD COLUMN auto_save_data LONGTEXT NULL
    CHECK (auto_save_data IS NULL OR JSON_VALID(auto_save_data))
    AFTER specialty_data,
  ADD COLUMN last_autosave_at TIMESTAMP NULL DEFAULT NULL
    AFTER auto_save_data;
```

**Why:** Auto-save needs a place to stash in-progress form state without committing it as a "real" visit. A `draft` status keeps it out of patient history and out of billing. On Save Visit click, status flips to `in_progress` (or `completed` if the doctor finishes the visit).

`auto_save_data` is the entire form's current state as JSON. Restored on page reload — doctor never loses work to a closed tab.

**Backfill:** Existing rows have `status` in `in_progress|completed|cancelled` — they stay as-is. The new `draft` value only applies to new rows.

### 5.2 Extend `prescriptions` for modern dosing

```sql
ALTER TABLE prescriptions
  ADD COLUMN frequency_preset VARCHAR(20) DEFAULT NULL
    AFTER frequency,                       -- e.g. "1-0-1", "1-1-1", "SOS"
  ADD COLUMN tapering_steps LONGTEXT NULL
    CHECK (tapering_steps IS NULL OR JSON_VALID(tapering_steps))
    AFTER duration_days,                   -- multi-step dosage schedule
  ADD COLUMN dose_unit VARCHAR(20) DEFAULT NULL
    AFTER potency,                         -- tablet, ml, drops, sachet
  ADD COLUMN dose_amount DECIMAL(7,2) DEFAULT NULL
    AFTER dose_unit,                       -- e.g. 2.5 for 2.5 ml
  ADD COLUMN food_timing ENUM('before','after','with','empty','bedtime','any')
    DEFAULT 'any' AFTER dose_amount,
  ADD COLUMN mix_with VARCHAR(40) DEFAULT NULL
    AFTER food_timing;                     -- water, milk, nothing
```

**Why keep the old `frequency` enum:** existing prescriptions already use OD/BD/TDS. We don't migrate them — both columns coexist. New rows can have `frequency_preset='1-0-1'` AND `frequency='BD'` (we can compute one from the other for display). Reports that group by frequency keep working.

`tapering_steps` JSON format:

```json
[
  { "days": 3, "preset": "1-1-1", "food": "after" },
  { "days": 3, "preset": "1-0-1", "food": "after" },
  { "days": 3, "preset": "0-0-1", "food": "after" }
]
```

When `tapering_steps` is NOT NULL, the simple `frequency_preset` + `duration_days` are ignored at print time. Both modes never conflict because we pick one per medicine.

### 5.3 `clinic_settings.section_state` (already created Phase 1)

Phase 1 already created the column. Phase 2 just populates it. JSON format:

```json
{
  "vitals":  { "state": "expanded", "expand_count": 5 },
  "labs":    { "state": "collapsed", "expand_count": 0 },
  "photos":  { "state": "expanded", "expand_count": 12 },
  "diet":    { "state": "collapsed", "expand_count": 1 }
}
```

`expand_count` is incremented on every expand. When it reaches 3, the section auto-promotes from "ghost-link" to "always visible" by being added to `visible_modules`.

### 5.4 `clinic_settings.visible_modules` population

Phase 1 created the column. Phase 2 needs a backfill SQL that maps each tenant's specialty → default modules:

```sql
-- Backfill via a CASE statement (since the config file is in PHP,
-- we mirror it here for the one-time SQL backfill).
UPDATE clinic_settings cs
JOIN tenants t ON cs.clinic_id = t.id
SET cs.visible_modules = CASE
  WHEN t.specialty IN ('homeo','ayurveda','siddha','unani','naturopathy',
                       'acupuncturist','physio','psychologist','psychiatrist',
                       'speech','audiologist','eye','ent')
       THEN JSON_ARRAY('case_specialty')

  WHEN t.specialty IN ('derma','trichology')
       THEN JSON_ARRAY('photos','case_specialty')

  WHEN t.specialty IN ('cosmetology','plastic_surgery')
       THEN JSON_ARRAY('photos','consent','case_specialty')

  WHEN t.specialty IN ('dental','orthodontist','endodontist',
                       'implantologist','prosthodontist','pediatric_dentist')
       THEN JSON_ARRAY('photos','consent','case_specialty')

  WHEN t.specialty IN ('cardio','endocrinology','nephrology')
       THEN JSON_ARRAY('vitals','labs','case_specialty')

  WHEN t.specialty = 'diabetology'
       THEN JSON_ARRAY('vitals','labs','diet','case_specialty')

  WHEN t.specialty IN ('general_surgery','neurosurgery','gi_surgery',
                       'bariatric','vascular','spine')
       THEN JSON_ARRAY('vitals','consent','case_specialty')

  WHEN t.specialty IN ('ortho','sports_medicine')
       THEN JSON_ARRAY('vitals','case_specialty')

  WHEN t.specialty = 'dietitian'
       THEN JSON_ARRAY('vitals','diet')

  ELSE JSON_ARRAY('vitals','case_specialty')   -- default
END
WHERE cs.visible_modules IS NULL;
```

After this backfill, every clinic has a populated `visible_modules` array. Doctors can toggle individual modules anytime in settings.

### 5.5 Indexes for the new query patterns

```sql
ALTER TABLE visits ADD INDEX idx_visits_patient_status (patient_id, status);
ALTER TABLE visits ADD INDEX idx_visits_draft_cleanup (status, last_autosave_at);
ALTER TABLE prescriptions ADD INDEX idx_prescriptions_visit_sort (visit_id, sort_order);
```

The draft cleanup index supports a nightly cron that purges draft visits older than 7 days where the doctor never came back.

---

## 6. Application code changes

### 6.1 New helper: `app/Support/VisitView.php`

Single source of truth for "which sections should this visit screen render?":

```php
namespace App\Support;

class VisitView
{
    /**
     * Returns the array of visible module keys for this clinic.
     * Falls back to specialty_defaults config if clinic_settings is empty.
     */
    public static function visibleModules(int $clinicId, string $specialty): array {
        // 1. Read clinic_settings.visible_modules
        // 2. If null, fall back to specialty_defaults config
        // 3. If still nothing, return ['vitals', 'case_specialty']
    }

    public static function sectionState(int $clinicId, string $section): string {
        // Returns 'expanded', 'collapsed', or 'ghost' (not in visible_modules)
    }

    public static function recordSectionExpand(int $clinicId, string $section): void {
        // Increment expand_count; auto-promote to visible_modules at 3+
    }
}
```

### 6.2 New action: `VisitController::cloneLastVisit`

Endpoint `/api/visits/{patientId}/clone-last`:

```
GET → returns JSON of the last completed visit's prescriptions
      + symptoms (if Phase 3 done) + diagnosis + notes
POST → creates a new draft visit pre-populated with that data
```

Frontend wires this to the "↻ Same as last visit" button.

### 6.3 New action: `VisitController::autosaveApi`

Already exists at line 279 in your current `VisitController` (good — saw it in earlier audit). **Phase 2 changes:** instead of saving individual fields, accept the full form blob and store in `visits.auto_save_data`. Debounced client-side at 30s.

### 6.4 New action: `ClinicSettingsController::toggleModule`

Endpoint `/api/clinic-settings/modules/{moduleKey}`:

```
POST {state: "show" | "hide"} → updates clinic_settings.visible_modules
```

### 6.5 New action: `ClinicSettingsController::recordSectionState`

Endpoint `/api/clinic-settings/section-state`:

```
POST {section: "vitals", state: "expanded"} → calls VisitView::recordSectionExpand
```

### 6.6 View rewrite

- `app/views/visits/show.php` — full rewrite. Tab system removed. Single-screen layout. Each module renders only if `VisitView::visibleModules()` contains it OR doctor revealed it this visit via ghost-link.
- `app/views/patients/show.php` — becomes a thin "patient detail" page that mostly delegates to the visit screen. The "patient profile" header is extracted into a partial `_patient_header.php` shared by both the patient detail page and the visit screen.
- `app/views/patients/wizard.php` — collapses the multi-step wizard to a single form. The "specialty fields" step is removed (specialty data is collected at visit time, not patient registration time).

### 6.7 Frontend (Alpine.js components)

Each section gets its own Alpine x-data component. State is local to the section. Auto-save is a parent component that watches all children and POSTs the combined state. Roughly:

```html
<div x-data="visitScreen({
  visitId: 123,
  visibleModules: ['vitals', 'case_specialty', 'photos'],
  lastAutosaveAt: null
})" x-init="initAutosave()">
  <!-- patient header -->
  <!-- form sections -->
</div>
```

The `visitScreen()` function lives in `assets/js/visit-screen.js` (new file). About 200 lines. Handles auto-save debouncing, section state tracking, voice input, clone-last-visit.

---

## 7. Admin area changes

### 7.1 New admin page: `/admin/specialty-defaults`

Read-only display of the config file. Shows: "Cardio → vitals, labs, case_specialty." Nothing editable here — changes go through git. The page exists so support can quickly tell a doctor "your defaults are X."

### 7.2 Tenant detail page

Add a section: **Active modules for this clinic**. Lists `visible_modules` with toggles. Lets support manually fix a stuck section state. Read-only `section_state` display below.

### 7.3 Nightly cleanup cron

`/cron/visit-drafts-cleanup` — purges `visits` rows where `status='draft' AND last_autosave_at < NOW() - INTERVAL 7 DAY`. Removes orphan auto-save junk. Logged for audit.

---

## 8. Code to DELETE in Phase 2

These are dead with the new layout. Confirm by grep before removing:

| File / Code | Why kill it |
|---|---|
| Tab navigation logic in `app/views/visits/show.php` (currently lines 254–261) | No tabs anymore |
| `partials/_wizard_specialty.php` (patient wizard specialty step) | Specialty data is captured at visit time, not registration |
| `wizardData()` helper in `PatientController` (~lines 319+) | Wizard collapsed to single form |
| Any `?tab=` query param routing in JS / PHP for visits | No tabs anymore |
| Old "Add visit" link from patient list that opens a separate visit creation page | Visit creation is inline on the patient screen now |
| Hard-coded specialty checks like `if ($spec === 'homeopathy')` in views — replace with checks against `clinic_settings.visible_modules` | Centralizing specialty logic |

---

## 9. Order of operations

1. **Phase 1 must be complete.** Verify the checklist in `phase1_pricing_and_cleanup.md` §10.
2. **Deploy `VisitView` helper** — no DB change yet. Just the PHP class. Confirms it can read `clinic_settings.visible_modules` from existing data (which is currently NULL — falls back to specialty config).
3. **Deploy the new visit screen view** behind a `?new=1` query flag. Doctors who don't pass the flag still see the old tabbed view. Lets you smoke-test in production with one or two friendly tenants.
4. **Run schema migrations** (`phase2_migrations.sql`) — adds draft status, prescription columns, indexes.
5. **Run the `visible_modules` backfill** (Block 4 of the SQL).
6. **Flip the visit screen flag to ON by default.** Old tabbed view is now dead code — leave it for one release cycle, then delete.
7. **Deploy admin changes** — `/admin/specialty-defaults`, module toggle on tenant detail.
8. **Deploy the cleanup cron.**
9. **Manually verify**:
   - Homeopath clinic sees only Symptoms / Diagnosis / Rx / Notes (+ case_homeopathy).
   - GP clinic sees Vitals expanded by default.
   - Dentist clinic sees Photos + Consent expanded.
   - Doctor can toggle a hidden section via ghost-link.
   - Auto-save fires on edits, draft persists after page reload.
   - "Same as last visit" button populates correctly.
10. **Delete deprecated files** listed in §8.

---

## 10. Risks & mitigations

| Risk | Mitigation |
|---|---|
| A clinic uses the old tabbed flow heavily and isn't ready for redesign | The `?new=1` flag in step 3 lets you ship gradually. Beta test with 5 friendly clinics before flipping the default. |
| Auto-save floods the API on slow networks | Debounce 30s client-side. Also reject autosave payloads larger than 64KB (form too large = bug). |
| Draft visits accumulate as junk | Nightly cron purges drafts older than 7 days. |
| `visible_modules` JSON is malformed by manual DB edits | `VisitView::visibleModules()` defensively falls back to the specialty default if JSON is invalid. Never crashes the page. |
| Existing prescriptions don't have `frequency_preset` set | Display layer derives it from the `frequency` enum (OD → "1-0-0-0", BD → "1-0-1", TDS → "1-1-1", QID → "1-1-1-1"). Old rows render correctly; new rows write both columns. |
| Tapering steps mis-rendered if `tapering_steps` JSON corrupt | Renderer falls back to `frequency_preset` + `duration_days`. Worst case: doctor sees the simple schedule instead of tapering. |
| Section state memory promotes a section the doctor didn't want | "+3 expansions = auto-promote" is reversible — doctor can hide it again in settings. No data lost. |

---

## 11. Phase 2 completion checklist

Don't move to Phase 3 with any open.

- [ ] `visits.status` enum extended with `draft`
- [ ] `visits.auto_save_data` + `last_autosave_at` columns added
- [ ] `prescriptions.frequency_preset`, `tapering_steps`, `dose_unit`, `dose_amount`, `food_timing`, `mix_with` columns added
- [ ] `specialty_defaults.php` config file created with all 50+ specialties
- [ ] `clinic_settings.visible_modules` backfilled for every existing clinic
- [ ] Indexes added on `visits` and `prescriptions`
- [ ] `VisitView` helper deployed and tested
- [ ] New visit screen view replaces old tab-based view (old removed after one release)
- [ ] `cloneLastVisit` API endpoint working
- [ ] `autosaveApi` updated to use `auto_save_data` JSON
- [ ] `toggleModule` + `recordSectionState` endpoints working
- [ ] Patient wizard simplified — specialty step removed
- [ ] Voice input wired to Notes field (browser-native Web Speech)
- [ ] Mobile layout verified on phone — single column, sticky header
- [ ] Desktop layout verified ≥1280px — optional side-by-side
- [ ] `/admin/specialty-defaults` page exists
- [ ] Module toggle UI on tenant detail page
- [ ] Nightly draft cleanup cron deployed
- [ ] One homeopath, one GP, one dentist tenant verified end-to-end
- [ ] Deprecated code/files deleted (§8)
- [ ] Tab navigation code removed
- [ ] `?tab=` query routing removed

When all green, move to **Phase 3 — Symptoms System + Prescription Builder + Templates**.
