# eClinicPro Visit Screen Architecture — Claude Discussion Prompt

## Context
We're redesigning the eClinicPro patient visit screen, inspired by Dr. Feelgood's single-screen pattern. I have strategic questions about structure, not implementation yet.

---

## 1. Visit Screen Anatomy — What Should Always Be Visible vs Progressive?

**Current thinking:**
- **Always visible:** Patient info + visit date + basic action (Add Visit / Finish Visit)
- **Collapsible sections:** Symptoms, Medicines, Diagnosis, Notes
- **Progressive unlock:** Vitals, Photos, Diet, Consent (only for specialties that need them)

**Questions for you:**
1. For a doctor moving fast (5-minute visit), what's the absolute minimum fields they must see before adding a visit?
2. If sections are collapsible, should they remember the doctor's preferences (e.g., "Doctor always opens Vitals first") or reset each visit?
3. On mobile, should we stack these sections vertically or use a horizontal scroll / tab-like layout (but NOT traditional tabs)?

---

## 2. Specialty-Aware Field Visibility — How Should This Work?

**Current thinking:**
When a doctor selects specialty during clinic setup:
- Homeopath clinic → sees Symptoms + Medicines + Notes only
- GP clinic → sees Symptoms + Diagnosis + Vitals + Medicines
- Dentist clinic → sees Symptoms + Tooth Chart + Photos + Consent
- Hospital → all fields unlocked

**Questions for you:**
1. Should a doctor be able to override their specialty visibility for one-off visits (e.g., "Today I need the diagnosis field" for a homeopath)?
2. If yes, should that override be a permanent setting change or one-time action?
3. How do we avoid the doctor feeling "stuck" in their specialty preset without overwhelming them with toggles?

---

## 3. Symptoms System — Structured Yet Flexible

**Proposed 3-layer approach:**

**Layer 1: Master Symptom Library** (curated by admin)
- Common symptoms: Fever, Cough, Headache, Abdominal pain, etc.
- Searchable, auto-complete dropdown with checkboxes

**Layer 2: Doctor Personal Symptoms** (private to that doctor)
- Symptoms they use frequently but aren't in master library
- Auto-suggest based on their history

**Layer 3: Custom One-Time Symptom**
- Doctor types free text for unusual cases
- Flagged for admin review if used >3 times across visits

**Questions for you:**
1. Should custom symptoms trigger a modal approval workflow, or silent admin notification in background?
2. If a custom symptom gets approved globally, should the doctor who created it get notified?
3. For symptom display in visit history, should we show: checkbox + text, or just text?
4. Should there be a limit on how many symptoms can be added per visit, or unlimited?

---

## 4. Medicine Prescription — Complexity Sequencing

**Challenge:** Prescriptions vary wildly.
- Simple: "Aspirin 500mg, 2 tablets daily for 5 days"
- Complex: "Day 1-3: 3 times daily, Day 4-6: 2 times daily, Day 7-10: 1 time daily, dilute in water"

**Current thinking:**
- **Quick path:** Medicine name + Quantity + Days (auto-calculates daily dose)
- **Advanced path:** Expandable section with dose schedule per day

**Questions for you:**
1. Should dose scheduling use a visual calendar/timeline (Day 1-3, Day 4-6, etc.) or text-based?
2. For dose instructions like "mix with water" or "take with food," where should these live: medicine field or notes?
3. Should we allow creating multi-step dose templates that doctors can save and reuse?
4. On mobile, how do we show a 10-day prescription schedule without the form becoming huge?

---

## 5. Diagnosis — Specialty-Aware Requirement

**Challenge:** Some specialties *need* diagnosis, others don't.

**Proposal:**
- Homeopath / Physio / Counselor → Diagnosis optional or hidden
- GP / Pediatrician / Dermatologist → Diagnosis required (field highlighted in red if missing)
- Hospital → Diagnosis required + ICD-10 coding

**Questions for you:**
1. Should we use "required by specialty" vs. a one-time "skip diagnosis this visit" checkbox?
2. If a doctor from a "diagnosis-optional" clinic *wants* to use diagnosis, how do they enable it?
3. Should diagnosis suggestions come from symptom-diagnosis mapping (e.g., "Fever + Cough" → suggests "Common Cold")?

---

## 6. Diet & Follow-Up — Operational Intelligence

**For Diet:**
- Doctor attaches a diet recommendation to the visit
- Template library (pre-made diet plans by condition)
- One-click WhatsApp/SMS share to patient

**For Follow-Up:**
- At visit save: Doctor specifies follow-up date + reason
- Auto-reminder to patient (WhatsApp/SMS)
- Clinic dashboard: "Patient overdue by 7 days" alert
- Doctor can mark: "Follow-up completed" or "Patient missed"

**Questions for you:**
1. Should follow-up reason be a dropdown (standard reasons like "Check progress," "Retest blood work") or free text?
2. Should the system auto-suggest follow-up dates based on diagnosis/medicine (e.g., antibiotics → follow-up in 5 days)?
3. On the patient screen, where should follow-up status appear: top of visit history, or inline with past visits?
4. Should a missed follow-up patient be flagged in the reception queue?

---

## 7. Visit Screen Layout — Mobile-First Design Questions

**Key constraint:** Doctor does 5-minute visits. No scrolling fatigue.

**Questions for you:**
1. **Desktop (wide):** Should visit form stay on the left (side-by-side with history), or should history collapse into a drawer?
2. **Mobile:** Should we use:
   - (a) Vertical stack: Visit form first, then history below
   - (b) Tabs: "Add Visit" tab + "History" tab
   - (c) Collapsible: Form visible, history behind "View History" button
3. **Doctor speed:** If a doctor has already prescribed "Aspirin 500mg" to this patient 3 times, should we show a "Reuse last prescription" button?
4. Should the form auto-save drafts as the doctor types, or only save on explicit "Add Visit" click?

---

## 8. Status Badges & Workflow — What Should the Doctor See?

**Current thinking (from Dr. Feelgood):**
- "In Consultation — add visit notes below, then mark as complete"
- "Completed — history view only"
- "Follow-up due — 2 days overdue, schedule follow-up"

**Questions for you:**
1. What other visit states should exist in eClinicPro? (Just in-progress / completed, or more?)
2. Should the status badge tell the doctor *what to do next* or just *what state it's in*?
3. Should reception see visit status differently than the doctor?

---

## 9. Voice Input — Phased Approach

**Phase 1 (immediate):** Voice → Notes field only
**Phase 2 (later):** AI summarization of voice notes into structured fields

**Questions for you:**
1. For Phase 1, should voice recording be auto-transcribed (real-time) or post-visit (batch)?
2. Should doctor be able to edit the transcription before visit save?
3. Should voice notes have a "Convert to structured" button that tries to extract symptoms/medicines?

---

## 10. Experience Profiles vs Tiers — Final Call

**Your suggestion:** Internal "experience profiles" (not called tiers), customer-facing "modules"

**Profiles in my mind:**
- Homeopath profile
- Physiotherapist profile
- GP profile
- Specialist profile
- Hospital profile

**Questions for you:**
1. Should profiles be clinic-wide, or per-doctor?
2. Can a clinic have mixed profiles (e.g., one homeopath, one GP)?
3. Should profile change be a one-time setup decision, or changeable anytime?
4. Should switching profiles warn the doctor ("This will hide Diagnosis field, OK?")?

---

## What I need from Claude:
1. **Clarity on tradeoffs** — Where does single-screen break? When should we accept complexity?
2. **Mobile-first sequencing** — What features make sense on phone vs. desktop?
3. **Doctor behavior patterns** — What do fast doctors actually need vs. nice-to-have?
4. **Standardization vs. flexibility** — How far can we bend eClinicPro before it becomes chaos?
5. **Phasing wisdom** — What should be Phase 1, Phase 2, Phase 3?


**But not before.**


Now I understand your thinking better.

You are talking about **two different things**:

1. **Product UX / feature visibility** (what doctor sees in UI)
2. **Pricing / package model** (what customer pays for)

Claude was talking about **#1 (UX)**, not pricing.

Let me explain with your business model.

## Your pricing idea (which I think is correct)

### One simple plan

```text
₹999/month
₹9999/year
```

Everything needed to run clinic.

No confusion.

No:

❌ Basic
❌ Pro
❌ Premium
❌ Gold
❌ Enterprise

This is smart for Indian clinics because doctors hate complexity.

Especially in Tier 2/3 cities.

### Then optional paid addons

Example:

Base Plan ₹999

Addons:

* WhatsApp Automation → ₹299
* Advanced Lab Management → ₹399
* Teleconsultation → ₹299
* AI Transcription → ₹499
* Multi-Branch → ₹599
* Advanced Marketing / Follow-up CRM → ₹399
* Custom Branding → ₹199
* Advanced Analytics → ₹299

This is actually a **very strong SaaS model**.

Because:

* Low entry friction
* Easy sales
* Higher ARPU later
* Upsell possible

I like this direction.

---

## What Claude meant by:

> “Modules per clinic, auto-enabled by specialty”

This is **NOT pricing**.

This is only:

### What appears in UI automatically

Example:

Doctor signs up as:

### Dentist

System automatically shows:

✅ Symptoms
✅ Prescription
✅ Photos
✅ Consent

But hides:

❌ Diet
❌ Lab section
❌ Diabetes tracking

Why?

Because dentist doesn't need clutter.

---

### Homeopath

Auto shows:

✅ Symptoms
✅ Medicines
✅ Notes

Hidden:

❌ Labs
❌ Photos
❌ Consent

Cleaner UI.

---

### GP

Auto shows:

✅ Symptoms
✅ Diagnosis
✅ Prescription
✅ Vitals

Hidden:

❌ Dental chart
❌ Cosmetic photos

---

Important:

### Hidden ≠ unavailable

Doctor can still enable anytime.

Example:

Homeopath wants vitals.

Settings → Enable vitals.

Done.

No pricing impact.

No package change.

Just UI simplification.

Think of it like:

> Smart default UI

Not package selection.

---

## My recommendation for EclinicPro

### Pricing → keep dead simple

You are right.

I would do:

## Base Plan

₹999/month

Everything required for clinic operations.

No restrictions.

No "starter/pro/premium".

Very strong positioning.

### Add-ons only for premium value

Only charge for things that:

1. Cost infra money
2. Save real staff time
3. Increase clinic revenue

Examples:

#### AI Addons

* Voice transcription
* Smart follow-up AI
* AI summaries

#### Communication Addons

* WhatsApp automation
* SMS reminders

#### Growth Addons

* Public doctor profile
* Patient acquisition
* Review generation

#### Scale Addons

* Multi-branch
* Advanced reports
* API integration

---

### Product UX → specialty smart defaults

But yes:

Keep:

> modules auto-enabled by specialty

Why?

Because this massively improves:

**Trial activation**

Doctor logs in and feels:

> "This software understands my clinic."

instead of:

> "Too many things. Confusing."

This is especially important because your onboarding is self-serve.

---

### My recommendation

#### Pricing

One plan only ✅

#### UI

Smart specialty defaults ✅

#### Addons

Only premium/high-value things ✅

#### Avoid

Per-feature billing nightmare ❌

Don't become:

> “Select 19 checkboxes”

Doctors hate that.

Simple wins in India.

If I were building EclinicPro, I would 100% go:

```text
₹999/month
Everything included

+ Optional premium addons
```

This aligns with your product philosophy:

> **Simple by default, powerful when needed**
