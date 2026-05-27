# Phase 1 — Pricing Model + Codebase Cleanup

**Goal:** Migrate from the legacy four-tier `free/clinic/practice/enterprise` plan model to a single ₹1,499/month plan with two paid add-ons. Hide all Bucket-3 features behind feature flags. Clean up dead code and admin UI before Phase 2 starts.

**Why this is Phase 1:** Every other phase (visit screen, symptoms, templates, follow-up) reads `tenant.plan` or `clinic_modules` somewhere. If we redesign the visit screen first and *then* change pricing, we'll have to retrofit billing checks twice. Fix the spine first.

---

## 1. Pricing decisions (locked in)

| Item | Value |
|---|---|
| Base plan name | `standard` |
| Monthly | ₹1,499 |
| Yearly | ₹14,999 (one month free) |
| Free trial | 30 days |
| Grace extension (one-time) | 15 days, admin-granted |
| Founding Clinic deal | First 100 sign-ups → ₹999/month locked for **24 months**, then auto-converts to ₹1,499 |
| Launch add-on #1 | **Patient Connect** (WhatsApp) — ₹499/month |
| Launch add-on #2 | **Clinic Network** (extra branch) — ₹999/month each |

All other add-ons currently coded (Lab, Radiology, Pharmacy, CRM, Incentive, Analytics, AI features, etc.) are **kept in code, hidden from UI** behind feature flags. Their controllers stay, their views stay, their routes stay — they just don't appear in pricing or the doctor's sidebar at launch.

### Currency policy (Phase 1)

**Phase 1 ships INR-only.** `tenants.currency` column stays in the schema (already exists, default `INR`), but every checkout, invoice, and price display hardcodes `₹`. The directory at `/find-a-doctor` remains globally indexable, but **paid features are India-only at launch**.

Why defer multi-currency: doing it well is a ~2-week project (FX caching, country-routed payment gateways, per-country tax rules, invoice templates per currency) and we have zero data on which non-INR currency to prioritize. When the first 5 foreign customers sign up organically, we'll know which currency to add next. Don't pre-build.

---

## 2. Audit of existing infrastructure (what we keep vs. retire)

### Tables we KEEP (and repurpose)

| Table | Status | Why |
|---|---|---|
| `tenants` | Modify columns | Plan enum collapses to single value |
| `module_catalog` | Reuse as-is | Already supports per-module pricing |
| `clinic_modules` | Reuse as-is | Already tracks per-clinic activation + billing cycle + Razorpay sub IDs |
| `specialty_configs` | Reuse as-is | Already stores per-clinic visit/vitals/prescription config |
| `payments` | Reuse as-is | Existing payment audit table |

### Tables we ADD (only what's truly new)

| Table | Purpose |
|---|---|
| `clinic_settings` | Per-clinic UI preferences (visible modules, expand state memory). Distinct from `specialty_configs`, which is clinical config. |
| `feature_flags` | Global on/off for Bucket-3 features. Lets us flip a module visible to all clinics without re-running migrations. |

### Tables we KILL

None in Phase 1. Don't drop tables until Phase 4 — too many side-effects. We just stop reading from them in the UI.

### Controllers we KEEP but HIDE

These stay in `/app/app/Controllers/` and on their routes, but their nav entries and dashboard cards are removed:

- `LabController.php`
- `LabReportController.php`
- `RadiologyController.php`
- `PharmacyController.php`
- `CrmController.php`
- `IncentiveController.php`
- `AnalyticsController.php` (the advanced report half — basic reports stay)
- `DocsController.php` (if it powers a "docs vault" feature that's bucket 3)

Their routes return `403 Feature not available on your plan` unless `feature_flags.<name>` is on for the requesting tenant. (Implementation: a single middleware check at controller entry.)

### Controllers we KEEP (always-on)

- `AuthController`, `DashboardController`, `PatientController`, `VisitController`, `PrescriptionController`, `VitalsController`, `AppointmentController`, `QueueController`, `BookController`, `BillingController`, `SettingsController`, `ClinicSettingsController`, `StaffController`, `OnboardingController`, `PortalController`, `DirectoryController`, `DoctorClaimController`, `DoctorOtpLoginController`, `GetListedController`, `LandingController`, `HealthController`, `WebhookController`, `QrController`, `ImpersonateController`, `AcceptInviteController`, `SchedulingController`, `ApiV1Controller`, `LeadAdminController`, `LeadSettingsController`, `SuperAdminController`

### Code to DELETE outright

These are stale or unused. (We'll confirm by grep before deleting, but listing them now as candidates.)

- The `enum('free','clinic','practice','enterprise')` on `tenants.plan` — must be migrated to a varchar with a single value or to a new enum with just `standard`.
- The `included_in_plans` JSON column on `module_catalog` — references the old plan enum. Either nuke or set to `["standard"]` for everything.
- Pricing-page comparison tables on the marketing site that show "Basic vs Pro vs Enterprise."
- Any `if ($tenant['plan'] === 'free')` or similar conditional code in controllers/views — replace with `if (!ecp_has_active_plan($tenant))` (trial expiry check).

---

## 3. Database migrations (MySQL)

The full SQL is in `phase1_migrations.sql` in the same folder. Below is the rationale for each block; the SQL itself is split into one block per concern so you can run them independently.

### 3.1 Modify `tenants` table

**Change plan enum to single value:**

```sql
ALTER TABLE tenants
  MODIFY COLUMN plan ENUM('standard') NOT NULL DEFAULT 'standard';
```

**Migration step BEFORE the ALTER** (required — MySQL will reject if existing rows hold old values):

```sql
-- Migrate all existing plans to 'standard'. Trial users keep their trial_ends_at.
UPDATE tenants SET plan = 'free';   -- temporary holding state
-- Then drop the column and re-add (safer than enum modification):
ALTER TABLE tenants DROP COLUMN plan;
ALTER TABLE tenants
  ADD COLUMN plan ENUM('standard') NOT NULL DEFAULT 'standard' AFTER timezone;
```

**Add founding clinic flag:**

```sql
ALTER TABLE tenants
  ADD COLUMN is_founding_clinic TINYINT(1) NOT NULL DEFAULT 0 AFTER plan,
  ADD COLUMN founding_clinic_locked_at TIMESTAMP NULL DEFAULT NULL AFTER is_founding_clinic;
```

**Add grace extension tracking:**

```sql
ALTER TABLE tenants
  ADD COLUMN trial_extension_granted TINYINT(1) NOT NULL DEFAULT 0
    AFTER trial_ends_at,
  ADD COLUMN trial_extension_granted_at TIMESTAMP NULL DEFAULT NULL
    AFTER trial_extension_granted,
  ADD COLUMN trial_extension_granted_by BIGINT(20) UNSIGNED NULL
    AFTER trial_extension_granted_at;
```

**Specialty enum needs widening** (this is overdue — your directory has 50+ but tenants is limited to 7):

```sql
ALTER TABLE tenants
  MODIFY COLUMN specialty VARCHAR(40) DEFAULT 'gp';
```

This unblocks all the new specialty smart defaults in Phase 2.

### 3.2 Create `clinic_settings`

Per-clinic UI preferences. Separate from `specialty_configs` (which holds clinical settings like vitals fields). This table holds **what the doctor's screen looks like**, not clinical defaults.

```sql
CREATE TABLE clinic_settings (
  clinic_id BIGINT(20) UNSIGNED NOT NULL PRIMARY KEY,
  visible_modules JSON DEFAULT NULL,
  -- Per-section memory: { "vitals": "expanded", "labs": "collapsed", ... }
  section_state JSON DEFAULT NULL,
  default_visit_template_id BIGINT(20) UNSIGNED NULL,
  voice_lang VARCHAR(10) DEFAULT 'en-IN',
  whatsapp_share_default TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_clinic_settings_clinic
    FOREIGN KEY (clinic_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**One-row-per-clinic** by primary key on `clinic_id`. Created lazily on first read or eagerly on clinic creation — pick eager to avoid null-checks everywhere.

### 3.3 Create `feature_flags`

Global on/off switches for hidden Bucket-3 modules. Read at controller entry to short-circuit hidden features.

```sql
CREATE TABLE feature_flags (
  flag_key VARCHAR(60) NOT NULL PRIMARY KEY,
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  -- 'all' = on for everyone, 'beta' = on for tenants in beta_tenant_ids, 'tenant' = per-tenant
  scope ENUM('all', 'beta', 'tenant') NOT NULL DEFAULT 'all',
  beta_tenant_ids JSON DEFAULT NULL,
  description TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed with the Bucket-3 features (all OFF by default for new tenants)
INSERT INTO feature_flags (flag_key, is_enabled, scope, description) VALUES
  ('lab_module',             0, 'beta', 'Lab orders + reports module'),
  ('radiology_module',       0, 'beta', 'Radiology orders module'),
  ('pharmacy_module',        0, 'beta', 'In-house pharmacy stock module'),
  ('crm_module',             0, 'beta', 'Marketing CRM module'),
  ('incentive_module',       0, 'beta', 'Staff incentive tracking'),
  ('advanced_analytics',     0, 'beta', 'Advanced analytics beyond basic reports'),
  ('ai_transcription',       0, 'beta', 'AI voice-to-structured note conversion'),
  ('custom_branding',        0, 'beta', 'White-label branding'),
  ('docs_vault',             0, 'beta', 'Document vault'),
  ('teleconsult',            1, 'all',  'Teleconsultation — included in base plan');
```

**Why a table and not a config file:** lets admin flip a flag for a specific beta tenant without a deploy.

### 3.4 Backfill `module_catalog` for the new pricing

The table already exists. Strategy: keep all existing rows for historical reference, but only **two** rows have `category='addon'` and visible-on-pricing:

```sql
-- Hide every legacy addon from the pricing page.
-- We use is_active as the "show on pricing page" signal.
UPDATE module_catalog SET is_active = 0;

-- Re-enable the two launch add-ons. Insert if missing.
INSERT INTO module_catalog
  (id, name, description, category, price_monthly_usd, price_yearly_usd,
   specialties, depends_on, included_in_plans, icon, is_active, sort_order)
VALUES
  ('patient_connect', 'Patient Connect',
   'WhatsApp automation: appointment reminders, prescription delivery, follow-up nudges.',
   'addon', 6.00, 60.00, NULL, NULL, '["standard"]', 'message-circle', 1, 1),
  ('clinic_network', 'Clinic Network',
   'Add an extra clinic branch under one account. Per-branch ₹999/month.',
   'addon', 12.00, 120.00, NULL, NULL, '["standard"]', 'git-branch', 1, 2)
ON DUPLICATE KEY UPDATE
  is_active = 1, category = 'addon',
  name = VALUES(name), description = VALUES(description),
  price_monthly_usd = VALUES(price_monthly_usd),
  price_yearly_usd = VALUES(price_yearly_usd),
  included_in_plans = VALUES(included_in_plans),
  sort_order = VALUES(sort_order);
```

**INR vs USD note:** `module_catalog.price_monthly_usd` is misnamed — it's actually used as the local currency price (your existing code reads it as INR). Don't rename the column in Phase 1 — too many call sites. Add a comment in the migration. Rename in Phase 4 cleanup.

### 3.5 Founding Clinic counter

Track headroom centrally so the signup form can show "47 of 100 spots left":

```sql
CREATE TABLE founding_clinic_state (
  id TINYINT NOT NULL PRIMARY KEY DEFAULT 1,
  cap INT NOT NULL DEFAULT 100,
  claimed INT NOT NULL DEFAULT 0,
  closed_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT chk_only_one_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO founding_clinic_state (id, cap, claimed) VALUES (1, 100, 0);
```

`claimed` is incremented atomically when a new tenant signs up and `claimed < cap`.

### 3.6 Indexes

```sql
ALTER TABLE tenants ADD INDEX idx_tenants_founding (is_founding_clinic);
ALTER TABLE tenants ADD INDEX idx_tenants_trial_ends (trial_ends_at);
ALTER TABLE clinic_modules ADD INDEX idx_clinic_modules_clinic_active (clinic_id, is_active);
```

---

## 4. Application code changes

### 4.1 New helper: `app/Support/Plan.php`

Central source of truth for "is this clinic active / what can they access":

```php
namespace App\Support;

class Plan
{
    public static function isActive(array $tenant): bool {
        // Active if paid OR within trial window OR within grace extension.
        if ($tenant['plan_expires_at'] && $tenant['plan_expires_at'] >= date('Y-m-d')) return true;
        if ($tenant['trial_ends_at']   && $tenant['trial_ends_at']   >= date('Y-m-d')) return true;
        return false;
    }

    public static function isInTrial(array $tenant): bool { /* ... */ }
    public static function trialDaysLeft(array $tenant): int { /* ... */ }
    public static function isFoundingClinic(array $tenant): bool { /* ... */ }
    public static function hasAddon(int $clinicId, string $moduleId): bool { /* read clinic_modules */ }
    public static function hasFeatureFlag(int $clinicId, string $flag): bool { /* read feature_flags */ }
}
```

Every controller that currently does `if ($tenant['plan'] === 'enterprise')` → replaced with `Plan::hasAddon($clinicId, 'patient_connect')` or `Plan::hasFeatureFlag($clinicId, 'lab_module')`.

### 4.2 New middleware: `App/Middleware/FeatureGate.php`

Applied to controllers in the Bucket-3 list. Returns 403 if the flag is off for this tenant.

```php
// In routes/web.php (or wherever you register Bucket-3 routes):
$router->group(['middleware' => ['feature:lab_module']], function() use ($router) {
    $router->get('/lab', [LabController::class, 'index']);
    // ...
});
```

### 4.3 Navigation cleanup

In `app/views/_nav.php` (and `app/views/admin/_nav.php`), wrap Bucket-3 nav items with `Plan::hasFeatureFlag()` checks. Lab, Radiology, Pharmacy, CRM, Incentive, Advanced Analytics — all hidden by default.

### 4.4 Dashboard cleanup

In `DashboardController` (and `app/views/dashboard/index.php`), remove or feature-flag dashboard tiles for hidden modules. Today's tiles (Patients today, Appointments pending, Revenue today, Follow-ups due) stay — they're base-plan.

### 4.5 Marketing site `/pricing` page

Rewrite [pricing.php](../../pricing.php) to show one plan + two add-ons + founding clinic banner. The current "Basic / Pro / Enterprise" comparison table is deleted. Replaced with:

- Hero card: ₹1,499/month with everything-included bullet list
- "₹999 for founding clinics — `<span id="fc-remaining">47</span> spots left"` banner (fed from `founding_clinic_state`)
- Two add-on cards (Patient Connect, Clinic Network)
- "Coming soon" teaser strip (4-5 bucket-3 features named without prices)

### 4.6 Onboarding flow

`OnboardingController` currently asks the doctor to pick a plan. After Phase 1, the only plan choice is `standard` — the plan-picker step is removed entirely. Trial starts automatically on signup with `trial_ends_at = now + 30 days`.

If founding clinic spots are still open at signup, the doctor's `is_founding_clinic = 1`, `founding_clinic_locked_until = today + 24 months`. They get a celebratory toast: *"You're founding clinic #47 — ₹999/month locked until <date>."*

After `founding_clinic_locked_until`, the billing job charges the standard ₹1,499. We notify them 30 days in advance via email so it's never a surprise.

---

## 5. Admin area changes

The admin currently manages plan upgrades manually. After Phase 1:

### 5.1 Tenant detail page (`SuperAdminController::clinics`)

Replace the plan-edit dropdown with:

- **Trial controls:** "Extend trial by 15 days" button (one-time, sets `trial_extension_granted=1`).
- **Plan controls:** "Mark as paid" (sets `plan_expires_at`) with date picker. "Mark as expired" reverts.
- **Founding clinic toggle:** Manual override for edge cases. When set, requires a `locked_until` date — admin can extend or revoke.
- **Add-on management:** List of active add-ons from `clinic_modules` with toggle to manually activate/deactivate (for support cases where Razorpay payment didn't flow back cleanly).
- **Feature flag overrides:** Read-only display of which `feature_flags` are on for this tenant.

### 5.2 New admin page: `/admin/feature-flags`

Simple CRUD. Toggle scope between `all`/`beta`/`tenant`. Edit `beta_tenant_ids` JSON list. Critical for staged rollouts of Bucket-3 features.

### 5.3 New admin page: `/admin/founding-clinics`

Shows the list of all `is_founding_clinic=1` tenants with `founding_clinic_locked_at`, `founding_clinic_locked_until`, and days remaining. Filter by "expiring soon" so we can email those tenants 30 days before their discount ends. Adjust `founding_clinic_state.cap` from this page if you decide to extend the program.

### 5.4 Pricing analytics (later)

Defer to Phase 4. Phase 1 just needs the operational controls.

---

## 6. Things to DELETE in Phase 1

These are dead now that pricing is unified. Confirm with `grep` before deleting each one:

| File / Code | Why kill it |
|---|---|
| Any markup in `app/views/onboarding/` showing plan comparison | One plan only |
| Pricing comparison table component on `/pricing` marketing page | Same |
| `$plan = $tenant['plan']` conditionals in `DashboardController`, `BillingController`, `SettingsController` that check for tier-specific behavior | Tier no longer exists |
| Any `if ($plan === 'free' || $plan === 'clinic')` in views — replace with `Plan::isActive($tenant)` | Same |
| Stripe integration code (`stripe_customer_id`, `stripe_sub_item_id`) | India-only product. Razorpay only. Drop Stripe columns in Phase 4. Mark as dead code in Phase 1. |
| Any "Upgrade your plan" CTAs that send to a tier selection page | Replace with "Upgrade to paid" → straight to Razorpay checkout for ₹1,499 |

---

## 7. Order of operations (do these in order)

Sequence matters because controllers read plan state on every request.

1. **Backup the production DB.** Non-negotiable.
2. **Deploy code changes for `Plan::isActive()` helper** — but keep it reading the old enum at this point. This is a no-op deploy; gives us the seam.
3. **Update all controllers** to use `Plan::isActive()` and `Plan::hasFeatureFlag()` instead of direct plan-string comparisons. Deploy. Still backward-compatible.
4. **Run schema migrations** (`phase1_migrations.sql`) — drops plan enum, adds new columns, creates new tables, seeds feature flags.
5. **Run data migrations** — backfill `clinic_settings` rows for existing clinics, set `is_founding_clinic=1` on the first 100 by signup date (or hold for genuine future signups — your call).
6. **Deploy the navigation/dashboard cleanup** — hides Bucket-3 modules from UI.
7. **Deploy the new `/pricing` page + onboarding flow**.
8. **Manually verify** one trial tenant, one paid tenant, one founding clinic in the admin.
9. **Send announcement email** to existing customers explaining the new pricing (and grandfather them if they're currently on something else).

---

## 8. What this does NOT include (deferred to later phases)

- **Visit screen redesign** — Phase 2.
- **Symptoms / prescription / template UX** — Phase 3.
- **Diet / follow-up / help page / voice** — Phase 4.
- **Stripe column removal, USD column rename, old plan enum cleanup** — Phase 4 final cleanup.
- **Removing the actual `LabController` / `PharmacyController` etc.** — they stay in code, just gated by flags. We re-evaluate which to delete after 6 months of usage data.

---

## 9. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Existing customer is mid-month on the old `enterprise` plan | Grandfather: their `clinic_modules` rows preserve their access. Their `tenant.plan` becomes `standard` but their existing modules stay activated. Bill them their old amount or migrate to new pricing — your business call. |
| Razorpay webhook references the old `module_id` for a feature now hidden | Webhook handler should still accept and activate that module — just don't expose it on the upgrade UI. We're hiding the *sales*, not the *capability*. |
| A doctor in a deleted-tier accidentally gets locked out mid-session | The `Plan::isActive()` migration in step 2 happens *before* the schema change. By the time the enum drops, no code is reading the enum. |
| `feature_flags` table grows out of hand | Hard cap: only Bucket-3 features get flags. Bucket-1 (base) features never get flags — they're always on. |
| Marketing site sitemap still references old plan-comparison anchors | Re-cache sitemap (delete `storage/cache/sitemap.xml`) after deploy. Already covered in earlier work. |

---

## 10. Phase 1 completion checklist

Phase 1 is done when **every** box is checked. Don't move to Phase 2 with any of these open.

- [ ] `tenants.plan` enum migrated to single `standard` value
- [ ] `tenants.specialty` widened to VARCHAR(40)
- [ ] `clinic_settings` table created and backfilled for all existing clinics
- [ ] `feature_flags` table created and seeded with Bucket-3 entries (all off)
- [ ] `founding_clinic_state` table created with cap=100
- [ ] First 100 test tenants flagged `is_founding_clinic=1` with `locked_until` ~24 months out (Block 11)
- [ ] `module_catalog` cleaned — only Patient Connect + Clinic Network active
- [ ] `Plan::isActive()` helper deployed and called from every plan-gated controller
- [ ] FeatureGate middleware deployed and applied to Bucket-3 routes
- [ ] Nav and dashboard show only base-plan items for normal tenants
- [ ] `/pricing` page rewritten — one plan + two add-ons + founding clinic banner
- [ ] Onboarding plan-picker step removed; trial auto-starts on signup
- [ ] Admin tenant page has trial extension, founding clinic toggle, add-on management
- [ ] `/admin/feature-flags` page exists and works
- [ ] `/admin/founding-clinics` page exists and works
- [ ] All `$tenant['plan'] === '<tier>'` string comparisons in code are gone (grep confirms)
- [ ] One trial tenant, one paid tenant, and one founding clinic verified end-to-end
- [ ] Announcement email sent to existing customers
- [ ] Sitemap cache cleared
- [ ] Backup of pre-migration DB stored

When all of these are green, we move to **Phase 2 — Visit Screen Redesign**.
