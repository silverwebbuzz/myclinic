# Phase 3 — Symptoms System + Prescription Builder + Templates

**Goal:** Make the three most-used cards on the visit screen feel instant. Symptoms entry that learns. A prescription builder that handles 1-0-1 in seconds and tapering when needed. Templates that auto-discover themselves from doctor behavior — no separate templates page to manage.

**Why this is Phase 3:** Phase 2 redesigned the screen layout. Now we fix the data entry inside it. Phase 4 (follow-up, diet, voice) sits on top of these primitives.

**Dependency:** Phase 2 visit screen must be live (`visits.status='draft'`, `clinic_settings.visible_modules` populated, new layout deployed).

---

## 1. Strategy recap (locked in)

From the strategy discussions:

### Symptoms — 3 layers
1. **Master** (curated, specialty-tagged, ~500 entries seeded)
2. **Personal** (per-doctor history — auto-learned, no admin needed)
3. **Custom** (visit-only free text; promote-on-frequency signal only)
- No admin approval queue. Background promotion when 10+ doctors use the same term.

### Prescription Builder
- **Frequency presets as chips** — `1-0-0`, `0-0-1`, `1-0-1`, `1-1-1`, `0-1-0`, `SOS`, `Custom`
- **Row-level `[⋮]` drawer** for advanced (tapering, mix-with, dose unit) — NOT a global "switch to advanced" toggle
- **Tapering as step list** — not visual calendar
- **Food timing** = chip on the row itself (Before / After / Empty / Bedtime)

### Templates
- **No separate templates page** in the nav. Templates appear inline at diagnosis and at the Add-medicine input.
- **Two scopes:** Personal (this doctor) + Clinic (all doctors here)
- **Auto-discovery:** if a doctor prescribes the same set of medicines together 5+ times, offer "Save as template?"
- **"Same as last visit"** is treated as a special template — always available, one tap.

---

## 2. Audit findings — what already exists

### Tables we KEEP (and extend)

| Table | What's there | Phase 3 changes |
|---|---|---|
| `drugs` | name, generic, class, strength, form, contraindications, schedule | Add `usage_count` for ranked autocomplete |
| `remedies` | name, abbreviation, source, indications | Add `usage_count` |
| `consent_templates` | per-clinic name + content + merge_fields | None — but used as the design pattern for prescription_templates |
| `visits` | chief_complaint TEXT column | Reused — symptoms join via new table; chief_complaint stays as the patient's standing complaint |
| `prescriptions` | one row per medicine (with Phase 2 dosing extensions) | None new — `prescription_template_items` mirrors this shape |
| `users` | doctor identity | Used as `doctor_id` foreign key on personal/template tables |

### Tables we CREATE in Phase 3

| Table | Purpose |
|---|---|
| `symptoms_master` | Curated library, specialty-tagged, synonym-aware |
| `symptoms_personal` | Per-doctor learned symptoms (auto-populated) |
| `visit_symptoms` | Many-to-many join — symptom appearances on each visit |
| `prescription_templates` | Header table: name, scope, owner |
| `prescription_template_items` | One row per medicine in a template (mirrors `prescriptions` columns) |
| `template_usage_log` | Tracks template applications (for analytics + auto-discovery thresholds) |

### Tables we DO NOT create

- No `icd10_codes` table. `icd10Api` keeps fetching from external source — out of scope here.
- No `symptom_diagnosis_map` table. Earlier strategy explicitly deferred this (medico-legal risk).

---

## 3. Symptoms — the 3-layer model in detail

### Layer 1: Master library

Centrally curated, ships with the product. ~500 symptoms tagged by specialty(ies). Lives in `symptoms_master`.

```sql
CREATE TABLE symptoms_master (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(120) NOT NULL,            -- "Fever", "Tooth pain", "Brain fog"
  slug VARCHAR(120) NOT NULL UNIQUE,      -- "fever", "tooth-pain" — search key
  synonyms JSON DEFAULT NULL,             -- ["high temp", "pyrexia", "BP"]
  specialties JSON DEFAULT NULL,          -- ["gp","peds"] — for ranking
  category VARCHAR(40) DEFAULT NULL,      -- "constitutional", "respiratory", "dental"
  global_usage_count INT NOT NULL DEFAULT 0,  -- rank signal
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_symptoms_master_label (label),
  INDEX idx_symptoms_master_active (is_active, global_usage_count)
);
```

- **`synonyms`** — JSON array of alternate strings the search should match. Critical for India: doctor types `BP` → suggests `Hypertension`. Type `loose motion` → suggests `Diarrhea`.
- **`specialties`** — JSON array. The query ranks symptoms tagged with the doctor's specialty higher, never *filters them out*. A dentist searching "fever" still finds it; it's just ranked below dental symptoms.
- **`global_usage_count`** — incremented every time the symptom is selected (across all clinics). Used to rank "Fever" above rare entries.
- **Seeded with ~500 rows** during Phase 3 migration. The seed file is `phase3_symptoms_seed.sql` (referenced but not inlined here — would be ~500 INSERTs).

### Layer 2: Doctor-personal

Auto-learned from custom entries. No admin approval, no doctor effort.

```sql
CREATE TABLE symptoms_personal (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id BIGINT(20) UNSIGNED NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  usage_count INT NOT NULL DEFAULT 1,
  last_used_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  promoted_to_master_id BIGINT(20) UNSIGNED NULL,  -- set when admin promotes
  UNIQUE KEY uniq_doctor_label (doctor_id, label),
  INDEX idx_personal_recent (doctor_id, last_used_at DESC),
  CONSTRAINT fk_personal_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_personal_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

- **UNIQUE (doctor_id, label)** — each doctor has one row per symptom they've used. On each use we `INSERT … ON DUPLICATE KEY UPDATE usage_count = usage_count + 1, last_used_at = NOW()`.
- **`promoted_to_master_id`** — when a doctor's custom symptom gets promoted to master, this links the personal row to the master row so we don't suggest the same thing twice in autocomplete.

### Layer 3: Custom (visit-only)

Not a table. Just plain text on the join row. If a doctor types "Brain fog" and the search returns no hit, we still store it on the visit. After it accumulates personally, it auto-shows as Layer 2 on next visit.

### The join — `visit_symptoms`

Every symptom on every visit, regardless of layer.

```sql
CREATE TABLE visit_symptoms (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  visit_id BIGINT(20) UNSIGNED NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,        -- denorm for fast multi-tenant queries
  master_id BIGINT(20) UNSIGNED NULL,            -- set if matched to master library
  label VARCHAR(120) NOT NULL,                   -- always populated for display
  source ENUM('master','personal','custom') NOT NULL DEFAULT 'custom',
  severity ENUM('mild','moderate','severe') DEFAULT NULL,
  duration VARCHAR(40) DEFAULT NULL,             -- "3 days", "since morning"
  sort_order TINYINT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vs_visit (visit_id, sort_order),
  INDEX idx_vs_master (master_id),
  CONSTRAINT fk_vs_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_vs_master FOREIGN KEY (master_id) REFERENCES symptoms_master(id) ON DELETE SET NULL
);
```

- **`label` always denormalized** — survives master row deletion. Visit history must always be readable.
- **`severity` and `duration`** — optional. Doctor adds them inline by tapping the chip after creation. Not required.
- **`source`** — analytics tag. Lets us measure how often the master library is used vs custom.

### Autocomplete UX

When the doctor types in the symptom input:

```
Input: "fe"

Suggestions (ranked):
  1. Fever          [master]     ← global_usage_count: 24,891
  2. Fever, low-grade [master]   ← global_usage_count: 3,012
  3. Feeling weak   [personal]   ← doctor's own, used 47 times
  4. Femur pain     [master]     ← dropdown end, lower global count

If still no match: "+ Add 'Fever spikes evening' as custom symptom"
```

**Ranking formula** (in the SQL query):
```
ORDER BY
  is_personal DESC,                              -- doctor's own first
  (specialty_match * 1000) + global_usage_count DESC
```

Specialty match: 1 if the symptom's `specialties` JSON includes the doctor's specialty, else 0. The `* 1000` weighting puts specialty-relevant symptoms at the top of master results.

### Auto-promotion signal (background)

A nightly cron query:

```sql
SELECT label, COUNT(DISTINCT doctor_id) AS doctors, SUM(usage_count) AS total_uses
FROM symptoms_personal
WHERE promoted_to_master_id IS NULL
GROUP BY LOWER(label)
HAVING doctors >= 10 AND total_uses >= 30
ORDER BY doctors DESC;
```

Result is dropped into an admin queue (a new admin page `/admin/symptom-promotions`). Admin reviews, one-click promotes → INSERT into `symptoms_master` and UPDATE the personal rows to set `promoted_to_master_id`.

**Notification to creator:** the first doctor who introduced the label gets a soft in-app toast: *"Your symptom 'Brain fog' is now available to all doctors."* Implemented as a `notifications` row (existing table — already there).

---

## 4. Prescription Builder — UX implementation

### Inline form (the 95% case)

Each medicine is a single row:

```
[ Medicine name ▾]  [Preset chip ▾]  [Days]  [Food ▾]   [⋮]   [×]
```

- **Medicine name input** — autocomplete from `drugs` (allopathic mode) or `remedies` (homeopathic mode), ranked by `usage_count` DESC then name.
- **Preset chip** — clicking opens a tiny popover with: `1-0-0`, `0-0-1`, `1-0-1`, `1-1-1`, `0-1-0`, `SOS`, `Custom`. "Custom" reveals 4 text inputs for the slot dosage values.
- **Days** — number input, range 1-90. Empty = "till next visit."
- **Food chip** — Before / After / Empty / Bedtime / Any. Default: Any.
- **[⋮]** — opens row-level drawer (see below).
- **[×]** — removes the medicine row.

### The [⋮] drawer

Reveals additional fields *for that medicine only*:

```
┌─ Advanced for Paracetamol ─────────────────┐
│ Dose unit:  ○ Tablet  ● ml  ○ Drops  ○ Sachet │
│ Dose amount: [2.5]                          │
│ Mix with:   ○ Water  ○ Milk  ○ Nothing      │
│                                             │
│ ▸ Tapering schedule                         │
│   Step 1: For [3] days, take [1-1-1], [After food]   │
│   Step 2: For [3] days, take [1-0-1], [After food]   │
│   Step 3: For [3] days, take [0-0-1], [After food]   │
│   [+ Add step]                              │
│                                             │
│ Free-text instructions:                     │
│ [...... up to 240 chars ......]             │
│                                             │
│ [Collapse]                                  │
└─────────────────────────────────────────────┘
```

When tapering steps exist, the simple chips at the row level become **disabled and show a summary chip**: `Tapering · 3 steps · 9 days total`. Doctor still sees the per-row drawer to edit.

### Frontend wiring

A new Alpine component `prescriptionRow(initial)` that owns the row's state, drawer toggle, and tapering steps array. The parent `visitScreen` collects all rows on save. Drawer state is persisted into the auto-save blob from Phase 2.

### Backend save logic

`PrescriptionController::saveItem` accepts:

```json
{
  "visit_id": 123,
  "items": [
    {
      "mode": "allopathic",
      "drug_id": 405,
      "frequency_preset": "1-0-1",
      "duration_days": 5,
      "food_timing": "after",
      "dose_unit": null,
      "dose_amount": null,
      "mix_with": null,
      "tapering_steps": null,
      "instructions": null
    },
    {
      "mode": "allopathic",
      "drug_id": 506,
      "tapering_steps": [
        { "days": 3, "preset": "1-1-1", "food": "after" },
        { "days": 3, "preset": "1-0-1", "food": "after" },
        { "days": 3, "preset": "0-0-1", "food": "after" }
      ],
      "food_timing": "after"
    }
  ]
}
```

Items are written to `prescriptions` (one row per item) — uses columns added in Phase 2 (`frequency_preset`, `tapering_steps`, etc.). Existing `frequency` enum is populated by deriving from `frequency_preset` so legacy reports keep working.

### Drug autocomplete improvements

```sql
ALTER TABLE drugs ADD COLUMN usage_count INT NOT NULL DEFAULT 0
  AFTER schedule;
ALTER TABLE drugs ADD INDEX idx_drugs_usage (usage_count DESC, name);

ALTER TABLE remedies ADD COLUMN usage_count INT NOT NULL DEFAULT 0
  AFTER source;
ALTER TABLE remedies ADD INDEX idx_remedies_usage (usage_count DESC, name);
```

Bumped on each `prescriptions.INSERT` via either a trigger or application code. Application code is simpler (no DB triggers to maintain) — I recommend a single `UPDATE drugs SET usage_count = usage_count + 1 WHERE id = ?` per saved item.

### Cloning from last visit

`VisitController::cloneLastVisit` (defined in Phase 2 §6.2): now also clones `visit_symptoms`. Cloned data is editable; saving creates a new visit with new rows.

---

## 5. Templates — auto-discovered, inline

### Schema

```sql
CREATE TABLE prescription_templates (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  doctor_id BIGINT(20) UNSIGNED NULL,           -- NULL = clinic-wide scope
  name VARCHAR(120) NOT NULL,
  description VARCHAR(240) DEFAULT NULL,
  mode ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  use_count INT NOT NULL DEFAULT 0,
  last_used_at TIMESTAMP NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  auto_discovered TINYINT(1) NOT NULL DEFAULT 0, -- 1 = system suggested, then saved
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_templates_doctor (doctor_id, use_count DESC),
  INDEX idx_templates_clinic (clinic_id, use_count DESC),
  CONSTRAINT fk_templates_clinic FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fk_templates_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE prescription_template_items (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT(20) UNSIGNED NOT NULL,
  mode ENUM('allopathic','homeopathic') NOT NULL DEFAULT 'allopathic',
  drug_id BIGINT(20) UNSIGNED NULL,
  remedy_id BIGINT(20) UNSIGNED NULL,
  potency VARCHAR(10) DEFAULT NULL,
  dose_unit VARCHAR(20) DEFAULT NULL,
  dose_amount DECIMAL(7,2) DEFAULT NULL,
  frequency_preset VARCHAR(20) DEFAULT NULL,
  duration_days SMALLINT UNSIGNED DEFAULT NULL,
  food_timing ENUM('before','after','with','empty','bedtime','any') DEFAULT 'any',
  mix_with VARCHAR(40) DEFAULT NULL,
  tapering_steps LONGTEXT NULL
    CHECK (tapering_steps IS NULL OR JSON_VALID(tapering_steps)),
  instructions TEXT DEFAULT NULL,
  sort_order TINYINT UNSIGNED DEFAULT 0,
  INDEX idx_template_items (template_id, sort_order),
  CONSTRAINT fk_template_items FOREIGN KEY (template_id)
    REFERENCES prescription_templates(id) ON DELETE CASCADE
);

CREATE TABLE template_usage_log (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  template_id BIGINT(20) UNSIGNED NOT NULL,
  doctor_id BIGINT(20) UNSIGNED NOT NULL,
  clinic_id BIGINT(20) UNSIGNED NOT NULL,
  visit_id BIGINT(20) UNSIGNED NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_template_log_doctor (doctor_id, applied_at DESC),
  INDEX idx_template_log_template (template_id, applied_at DESC),
  CONSTRAINT fk_tul_template FOREIGN KEY (template_id)
    REFERENCES prescription_templates(id) ON DELETE CASCADE
);
```

### Why two tables (template + items)

Same shape as `prescriptions` + `prescription_template_items`. One template = many items, mirroring how a visit has many prescriptions. Cloning is then trivial: `INSERT INTO prescriptions SELECT FROM prescription_template_items`.

### Auto-discovery query

Runs as a daily cron. Looks for medicine groupings repeated 5+ times by the same doctor:

```sql
-- Group recent prescriptions by (doctor, visit) → bag of drug_ids.
-- Find bags that recur 5+ times across different visits.
-- Conceptually:
WITH visit_bags AS (
  SELECT v.doctor_id, v.id AS visit_id,
         GROUP_CONCAT(p.drug_id ORDER BY p.drug_id) AS drug_set
  FROM visits v
  JOIN prescriptions p ON p.visit_id = v.id
  WHERE v.created_at > NOW() - INTERVAL 90 DAY
    AND v.status = 'completed'
    AND p.drug_id IS NOT NULL
  GROUP BY v.id
)
SELECT doctor_id, drug_set, COUNT(*) AS times_prescribed
FROM visit_bags
GROUP BY doctor_id, drug_set
HAVING times_prescribed >= 5
ORDER BY times_prescribed DESC
LIMIT 50;
```

For each row, the cron INSERTs a *suggestion* (a `prescription_templates` row with `auto_discovered=1`, `is_active=0`). The doctor sees: *"You often prescribe these 3 medicines together. Save as template?"* — one-click → flip `is_active=1` and rename.

### Inline surfacing UX

Templates appear in TWO places, never in a separate page:

**At the medicine input on the Prescription card:**
```
+ Add medicine... | Templates: [Fever 5-day] [Allergy combo] [+ More]
```
Tap a chip → all template items append to the current prescription list (editable).

**At the diagnosis field:**
```
Diagnosis: [ Viral fever ▾]
              ↓ autocomplete suggests both diagnosis text and matching template
              "Viral fever  →  Apply Fever 5-day template"
```

If a doctor types a diagnosis that matches a template's `name` keyword, the autocomplete row offers to apply the template.

### "Same as last visit" — the implicit template

This is **always** the first chip on the template row. Backed by the `cloneLastVisit` API from Phase 2 §6.2. No actual `prescription_templates` row; computed on the fly from the patient's previous completed visit.

### Templates page (minimal)

`/templates` exists but is **management-only**: rename, delete, change scope. Not for browsing-then-applying. We hide it from the main nav and put a small link in clinic settings: *"Manage prescription templates."*

---

## 6. Application code changes

### 6.1 New controller: `SymptomsController`

Handles symptom search, save, and admin promotions.

```
GET  /api/symptoms/search?q=fev          → search master + personal, ranked
POST /api/visits/{id}/symptoms           → save symptoms to visit_symptoms
DEL  /api/visits/{id}/symptoms/{vsId}    → remove one
GET  /admin/symptom-promotions           → admin: review candidates
POST /admin/symptom-promotions/{id}      → promote to master
```

### 6.2 Extended `PrescriptionController`

```
POST /api/prescriptions/templates                       → create
GET  /api/prescriptions/templates?scope=mine|clinic     → list
POST /api/prescriptions/templates/{id}/apply/{visitId}  → instantiate into visit
DEL  /api/prescriptions/templates/{id}
GET  /api/drugs/search?q=para                           → autocomplete
GET  /api/remedies/search?q=arn                         → autocomplete (homeo)
```

### 6.3 Extended `VisitController`

`cloneLastVisit` (defined in Phase 2): now also pulls `visit_symptoms`.

### 6.4 New helper: `app/Support/SymptomSearch.php`

Encapsulates the ranking query so SymptomsController stays thin:

```php
public static function search(int $doctorId, int $clinicId,
                              string $specialty, string $q, int $limit = 8): array
{
    // 1. Personal hits (doctor_id matches)
    // 2. Master hits with specialty boost
    // 3. Synonym matches
    // Returns: [['id','label','source','master_id'], ...]
}
```

### 6.5 New helper: `app/Support/TemplateDiscovery.php`

Runs the auto-discovery query as a cron job. Idempotent — if a suggestion was already created and rejected, don't recreate.

### 6.6 New cron jobs

| Cron | Frequency | Purpose |
|---|---|---|
| `cron/symptoms-promote-candidates` | nightly | Builds admin queue from `symptoms_personal` |
| `cron/templates-discover` | weekly | Builds template suggestions from prescription patterns |
| `cron/drugs-usage-recount` | weekly | Rebuild `drugs.usage_count` from `prescriptions` (cheap reconciliation in case in-app increments drift) |

### 6.7 Frontend (Alpine.js)

| Component | Lives in |
|---|---|
| `symptomPicker()` | `assets/js/visit-screen.js` |
| `prescriptionRow()` | `assets/js/visit-screen.js` |
| `taperingSteps()` | `assets/js/visit-screen.js` |
| `templateApplier()` | `assets/js/visit-screen.js` |

All extensions of the `visitScreen()` parent from Phase 2.

---

## 7. Admin changes

### 7.1 New admin page: `/admin/symptom-promotions`

Lists candidates from the nightly cron with: label, doctor count, total uses, last seen. Each row has:
- **Promote** → inserts into `symptoms_master` (with admin filling specialties + category), updates personal rows.
- **Ignore** → adds the label to a denylist so it doesn't reappear in the queue.
- **Merge with existing** → maps the personal entries to an existing master row (for spelling variants).

### 7.2 New admin page: `/admin/symptoms-master`

CRUD on `symptoms_master`. Lets admin manually add entries, edit synonyms/specialties, deactivate stale ones. Read-only `global_usage_count` shown for context.

### 7.3 Tenant detail additions

On a tenant's admin page, add a tab: **Prescription templates**. Lists this clinic's templates with use_count. Admin can deactivate problematic ones for support cases.

### 7.4 Seed data UI (not needed)

The master library seed is a one-time SQL file. No admin UI needed for the initial 500 symptoms.

---

## 8. Code to DELETE in Phase 3

Confirm by grep before removal. Most things in Phase 3 are *additions*, not replacements — fewer deletions than Phase 2.

| File / Code | Why kill it |
|---|---|
| Free-text `visits.chief_complaint` form rendering (replaced by symptom chips) — but **keep the column** for legacy data display | New UX uses chips; column stays for back-compat |
| The OD/BD/TDS-only frequency dropdown in the old prescription UI | Replaced by chip preset picker |
| Any "templates" link in the main sidebar (`app/views/_nav.php`) | Templates are inline now; sidebar link confuses |
| Stale ICD-10 lookups that block the UI when icd10Api is slow | Make ICD-10 fully optional/async — never block the save |

---

## 9. Order of operations

1. **Phase 2 must be complete.** Verify checklist in `phase2_visit_screen.md` §11.
2. **Run schema migrations** (`phase3_migrations.sql`) — creates 6 new tables, adds `usage_count` columns to drugs/remedies, indexes.
3. **Seed `symptoms_master`** from `phase3_symptoms_seed.sql` (separate file — ~500 INSERTs). The seed groups symptoms by category and tags them with relevant specialties.
4. **Deploy `SymptomSearch` + `TemplateDiscovery` helpers** — pure PHP, no UI change yet.
5. **Deploy `SymptomsController` + extended `PrescriptionController`** — JSON endpoints live, but front-end still uses old paths.
6. **Deploy the new symptom picker + prescription row + tapering UI** behind a `?new_rx=1` flag so you can smoke-test in production.
7. **Flip the new UI on by default** for all tenants. Beta clinics get it first, full rollout in 1 week.
8. **Deploy admin pages**: `/admin/symptom-promotions`, `/admin/symptoms-master`, template management tab.
9. **Schedule the 3 cron jobs**: nightly symptom promotions, weekly template discovery, weekly drug usage recount.
10. **Backfill `drugs.usage_count` / `remedies.usage_count`** from existing `prescriptions` (Block 7 of SQL).
11. **Verify manually**:
    - Type "fe" → fever appears.
    - Type a custom term → on save, appears in personal list on the next visit.
    - Add a row with tapering 3 steps → renders correctly.
    - Apply a template → all items populate, editable.
    - "Same as last visit" populates symptoms + prescriptions.
12. **Delete deprecated code** listed in §8.

---

## 10. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Symptom search is slow due to JSON synonym scanning | LIKE-based prefix match on `label` first (uses idx_symptoms_master_label). Synonym fallback only when prefix returns < 3 results. Cap at 8 suggestions. |
| Auto-promote creates duplicate master entries (spelling variants) | Admin queue groups candidates by LOWER(label) with case-insensitive grouping. Final promotion still requires admin click. |
| Auto-discovered templates flood the doctor's UI | Suggestions sit in `prescription_templates` with `is_active=0`. They never appear in the inline chip row until the doctor explicitly saves. |
| Tapering JSON malformed by manual edits | Renderer falls back to the flat preset/duration. Doctor sees a simpler schedule, never a crash. |
| usage_count drift if app-level increment misses | Weekly cron rebuilds counts from `prescriptions`. Numbers stay correct within 7 days. |
| Doctor abandons mid-tapering edit, draft saves stale steps | Auto-save (Phase 2) handles it. Doctor returns, sees the partial schedule. |
| Symptoms accumulate per-doctor forever | No active mitigation needed. `symptoms_personal` is small (a doctor has maybe 200-300 personal entries at most). Add a UI "clean up unused symptoms" tool later if it grows. |
| icd10Api outage breaks the diagnosis row | Make ICD-10 a separate field that loads async after the diagnosis text is entered. Form save never depends on it. |
| Templates table accidentally cluttered with auto-discovery rows that doctors didn't save | The `is_active=0` filter on listing hides them. UI surfaces them as ephemeral suggestions, not real templates, until claimed. |

---

## 11. Phase 3 completion checklist

- [ ] `symptoms_master` table created and seeded with ~500 entries
- [ ] `symptoms_personal` table created with FKs to users + tenants
- [ ] `visit_symptoms` table created with FKs to visits + symptoms_master
- [ ] `prescription_templates` table created with personal/clinic scope
- [ ] `prescription_template_items` table created mirroring prescription columns
- [ ] `template_usage_log` table created
- [ ] `drugs.usage_count` + `remedies.usage_count` columns added
- [ ] Indexes added on all new tables
- [ ] `drugs.usage_count` and `remedies.usage_count` backfilled from prescriptions history
- [ ] `SymptomsController` deployed and wired to the new symptom picker
- [ ] `PrescriptionController` extended with template + autocomplete endpoints
- [ ] `cloneLastVisit` now includes symptoms
- [ ] New chip-based frequency presets work in the visit screen
- [ ] Tapering schedule UI works on a complex prescription
- [ ] Template auto-discovery cron runs weekly
- [ ] Symptom promotion cron runs nightly
- [ ] `/admin/symptom-promotions` page works end-to-end
- [ ] `/admin/symptoms-master` CRUD works
- [ ] Templates management UI accessible from clinic settings
- [ ] One symptom successfully promoted from personal → master in admin
- [ ] One template auto-discovered and saved by a real prescription pattern
- [ ] "Same as last visit" button populates both symptoms and prescriptions
- [ ] Deprecated code/files deleted (§8)

When all green, move to **Phase 4 — Follow-up + Diet + Voice + Help page + final cleanup**.
