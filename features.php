<?php
// =====================================================================
// features.php — full feature catalog
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Features — eClinicPro';
$metaDesc = 'Everything to run your clinic — patient records, prescriptions, appointments, billing, WhatsApp/SMS, and more. All included in one simple plan.';
$activePage = 'features';

$cats = [
    ['records', 'Patient records',
     'Encrypted, structured, searchable — and built around how doctors actually think, not how databases work.',
     [
        ['📋', 'Structured visit notes', 'SOAP, free-form, or specialty-shaped templates. Auto-save every 2 seconds.'],
        ['📱', 'QR patient cards', 'Print or send. Tap to load any chart in under 200ms.'],
        ['📎', 'Attachments', 'PDFs, X-rays, lab reports, voice memos. Encrypted, viewable inline.'],
        ['🛡️', 'Allergy & alert flags', 'Drug allergies and history conditions surface across every screen.'],
        ['👨‍👩‍👧', 'Family history graph', 'Visual family tree with hereditary condition tagging.'],
        ['📈', 'Chronic care tracking', 'Long-term condition timelines with target band visuals.'],
     ]],
    ['visits', 'Appointments & visits',
     'A booking system that respects walk-ins, no-shows, and the messiness of real clinical workflow.',
     [
        ['📅', 'Smart scheduling', 'Multi-doctor, multi-room. Drag to reschedule. Conflicts auto-blocked.'],
        ['💬', 'WhatsApp + SMS reminders', 'Auto-sent 24h and 1h before. Patient can reply to confirm or cancel.'],
        ['⚡', 'Walk-in triage', 'Quick-add a walk-in, place them in the queue with severity.'],
        ['📊', 'No-show analytics', 'Which patients no-show, which slots, which time of week — track it all.'],
        ['🌐', 'Online booking page', 'Branded booking link patients can share. Sync to your calendar live.'],
        ['🎥', 'Telemedicine slots', 'Mix in-person and video slots in one calendar. Patients pick what works.'],
     ]],
    ['rx', 'Prescriptions & pharmacy',
     'From the moment you write a script to the moment your patient picks it up — fully tracked.',
     [
        ['℞', 'Digital prescriptions', '200,000-drug DB with dosage, interaction, and pediatric warnings.'],
        ['💬', 'WhatsApp Rx delivery', 'Signed PDF sent to patient before they leave the chair.'],
        ['💊', 'Pharmacy inventory', 'Batch numbers, expiry alerts, low-stock auto-orders.'],
        ['🛡️', 'Controlled substance log', 'Schedule-II compliant audit trail with DEA-ready exports.'],
        ['✓', 'Refill management', 'Patients request refills via WhatsApp. Approve with one tap.'],
        ['📊', 'Adherence tracking', "See who refilled, who didn't, and follow up automatically."],
     ]],
    ['clinical', 'Clinical tools',
     'The specialty-specific toolkit — the right tools appear automatically for your specialty.',
     [
        ['📈', 'Vitals trend charts', 'BP, HR, glucose, weight — visual time series with target bands.'],
        ['🧪', 'Lab orders & results', 'Order, receive, attach. Auto-flagged abnormals.'],
        ['🦷', 'Dental charting', 'FDI/Palmer/Universal. Per-tooth notes, images, and treatment plans.'],
        ['🖼️', 'Skin imaging', 'Side-by-side before/after with lesion measurement.'],
        ['🌱', 'WHO growth charts', 'Pediatric percentile tracking for weight, height, head circumference.'],
        ['🧫', 'Homeo remedy DB', '3,200 remedies, potency picker, antidote rules, miasm tags.'],
     ]],
    ['business', 'Billing & business',
     'Run the business of medicine without spreadsheets — and with no enterprise tax.',
     [
        ['🧾', 'Invoicing', 'Multi-currency, GST/VAT-ready, tax codes per region.'],
        ['💳', 'Payments', 'Card, UPI, Apple/Google Pay, bank transfer, cash. Reconciled automatically.'],
        ['📊', 'Revenue & cohort reports', 'New vs repeat, by doctor, by procedure, exportable to CSV.'],
        ['📋', 'Insurance claims', 'Submit, track, and reconcile claims (region-dependent: US, UK, India, UAE).'],
        ['✓', 'Patient packages', 'Sell prepaid visit/treatment packages. Auto-deducted at each visit.'],
        ['🌐', 'Multi-location', 'One brand, many branches. Roll-up reporting, cross-branch records.'],
     ]],
    ['patient', 'Patient experience',
     'The patient-facing layer your front desk wishes they could build. White-labeled and beautiful.',
     [
        ['🌐', 'Patient web portal', 'Records, prescriptions, bills, upcoming visits — all in one tab.'],
        ['🎥', 'Video consults', 'HD, browser-based, no app install. Works on 3G.'],
        ['💬', 'WhatsApp summaries', 'After every visit: a clean summary plus next-step instructions.'],
        ['🥗', 'Diet & exercise plans', 'Templated programs with daily WhatsApp check-ins.'],
        ['℞', 'Refill requests', 'Patients tap once. You approve or revise.'],
        ['⭐', 'Feedback collection', 'Post-visit NPS via WhatsApp. Scores roll into your reports.'],
     ]],
    ['platform', 'Platform & integrations',
     'Everything underneath the surface — engineered for clinics, not enterprises.',
     [
        ['🛡️', 'India DPDP ready', 'Encrypted at rest & in transit, per-clinic isolation, real audit logs.'],
        ['⚡', 'Fast & reliable', 'Built for Indian clinics and networks — quick even on patchy connections.'],
        ['🌐', 'Multi-language', 'Interface and prescriptions in English, Hindi, Gujarati and more.'],
        ['🔄', 'Easy migration', 'Import from Practo, spreadsheets, or your existing system.'],
        ['🔌', 'API & webhooks', 'Full REST API. Webhook on every meaningful event.'],
        ['🔐', 'Roles & audit logs', 'Granular staff roles and a signed audit trail on every record.'],
     ]],
];

require __DIR__ . '/partials/header.php';
?>

<!-- ============ Hero ============ -->
<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">Everything eClinicPro does</span>
        <h1 class="h-display" style="font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px;">Everything to run your clinic.</h1>
        <p class="lede" style="font-size: 19px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            Forty-plus features across seven areas — patient records, prescriptions, billing, WhatsApp/SMS and more. All included in one simple ₹16,000/year plan.
        </p>
    </div>
</section>

<!-- ============ Stats ============ -->
<section style="padding: 64px 0; border-top: 0.5px solid var(--line); border-bottom: 0.5px solid var(--line); background: var(--bg-2);">
    <div class="wrap">
        <div class="stats">
            <div class="stat"><div class="stat-num">42</div><div class="stat-label">Features included</div></div>
            <div class="stat"><div class="stat-num">50+</div><div class="stat-label">Specialties</div></div>
            <div class="stat"><div class="stat-num">1</div><div class="stat-label">Simple plan</div></div>
            <div class="stat"><div class="stat-num">₹16,000</div><div class="stat-label">Per year</div></div>
        </div>
    </div>
</section>

<!-- ============ Category nav ============ -->
<div style="position: sticky; top: 56px; z-index: 50; background: rgba(255,255,255,0.85); backdrop-filter: saturate(180%) blur(20px); border-bottom: 0.5px solid var(--line); padding: 14px 0;">
    <div class="wrap" style="display: flex; gap: 4px; overflow-x: auto; justify-content: center; flex-wrap: wrap;">
        <?php foreach ($cats as [$id, $title]): ?>
        <a href="#<?= e($id) ?>" class="spec-tab" style="white-space: nowrap;"><?= e($title) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============ Categories ============ -->
<section style="padding-top: 80px; padding-bottom: 40px;">
    <div class="wrap">
        <?php foreach ($cats as [$id, $title, $blurb, $items]): ?>
        <section id="<?= e($id) ?>" class="feat-category" style="padding: 60px 0; border-top: 0.5px solid var(--line);">
            <div class="feat-cat-head reveal" style="display: grid; grid-template-columns: 1fr 1.4fr; gap: 60px; margin-bottom: 36px;">
                <div>
                    <span class="eyebrow"><?= count($items) ?> features</span>
                    <h2 style="margin-top: 10px; font-size: 32px; letter-spacing: -0.8px;"><?= e($title) ?></h2>
                </div>
                <p class="lede"><?= e($blurb) ?></p>
            </div>
            <div class="feat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 28px;">
                <?php foreach ($items as $i => [$ic, $n, $d]): ?>
                <div class="feat-item reveal" style="transition-delay: <?= ($i % 3) * 60 ?>ms;">
                    <div class="ico" style="width: 40px; height: 40px; border-radius: 10px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; font-size: 18px; margin-bottom: 14px;"><?= $ic ?></div>
                    <h4 style="font-size: 15px; font-weight: 500; margin-bottom: 6px;"><?= e($n) ?></h4>
                    <p style="font-size: 13.5px; color: var(--mute); line-height: 1.55;"><?= e($d) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ Pricing ============ -->
<section id="pricing" class="feat-pricing">
    <div class="wrap">
        <div class="feat-pricing-head reveal">
            <span class="eyebrow">Pricing</span>
            <h2 class="h-section">One plan. Everything included.</h2>
            <p class="lede center">
                No tiers, no per-seat games, no surprise upsells. One annual price gets you
                the whole clinic system — and you start with a 30-day free trial, no card needed.
            </p>
        </div>

        <div class="feat-plan-grid reveal">
            <!-- Plan card -->
            <div class="plan-card primary">
                <div class="plan-head">
                    <span class="plan-name">Standard</span>
                    <h3 class="plan-price">
                        <span class="currency">₹</span>16,000<span class="per">/year</span>
                    </h3>
                    <p class="plan-yearly">
                        <span class="plan-strike">₹17,988</span>
                        <span class="plan-save">Save 10%</span>
                        <br>+ 18% GST at checkout
                    </p>
                </div>
                <ul class="plan-features">
                    <li>✓ Patient records, visits, prescriptions</li>
                    <li>✓ Appointments &amp; walk-in queue</li>
                    <li>✓ Billing &amp; invoicing (GST-ready)</li>
                    <li>✓ Vitals, diagnosis, follow-up tracking</li>
                    <li>✓ Specialty-aware forms (50+ specialties)</li>
                    <li>✓ Teleconsultation built in</li>
                    <li>✓ Public doctor profile on eclinicpro.com</li>
                    <li>✓ Daily reports &amp; analytics</li>
                    <li>✓ Unlimited patients, unlimited staff users</li>
                    <li>✓ 30-day free trial — no credit card</li>
                </ul>
                <a href="https://app.eclinicpro.com/register" class="btn btn-primary btn-lg btn-block">
                    Start 30-day free trial
                </a>
                <p class="plan-fineprint">No card needed. Cancel anytime during trial.</p>
            </div>

            <!-- Add-ons + FAQ column -->
            <div class="addon-column">
                <h3 class="addon-heading">Optional add-ons</h3>
                <div class="addon-card">
                    <div class="addon-icon">💬</div>
                    <div>
                        <h4 class="addon-name">Patient Connect</h4>
                        <p class="addon-desc">WhatsApp automation: appointment reminders, prescription delivery, follow-up nudges. Cuts no-show rates in half.</p>
                        <div class="addon-price">+₹499/month</div>
                    </div>
                </div>
                <div class="addon-card">
                    <div class="addon-icon">🌿</div>
                    <div>
                        <h4 class="addon-name">Clinic Network</h4>
                        <p class="addon-desc">Add an extra clinic branch under one account. Unified patient records, separate queues per branch.</p>
                        <div class="addon-price">+₹999/month per branch</div>
                    </div>
                </div>
                <p class="addon-tease">
                    GST (18%) is added at checkout. After the 30-day trial you decide whether to
                    continue — no automatic charges and no card taken upfront.
                </p>
            </div>
        </div>
    </div>
</section>

<style>
@media (max-width: 800px) {
    .feat-cat-head { grid-template-columns: 1fr !important; gap: 16px !important; }
    .feat-grid { grid-template-columns: 1fr !important; }
}

/* ---- Pricing block (self-contained; mirrors old pricing.php styles) ---- */
.feat-pricing { padding: 72px 0 96px; border-top: 0.5px solid var(--line); background: var(--bg-2); }
.feat-pricing-head { text-align: center; margin-bottom: 40px; }
.feat-pricing-head .lede.center { max-width: 640px; margin: 16px auto 0; }
.feat-plan-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 32px; max-width: 1100px; margin: 0 auto; }
@media (max-width: 900px) { .feat-plan-grid { grid-template-columns: 1fr; } }

.plan-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 32px; box-shadow: 0 4px 16px rgba(0,0,0,0.04); }
.plan-card.primary { border: 2px solid var(--teal-600); }
.plan-name { display: inline-block; background: var(--teal-50); color: var(--teal-700); font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; padding: 4px 10px; border-radius: 999px; }
.plan-price { font-size: 48px; font-weight: 300; letter-spacing: -1.2px; margin: 12px 0 4px; }
.plan-price .currency { font-size: 24px; vertical-align: super; opacity: 0.7; margin-right: 2px; }
.plan-price .per { font-size: 16px; font-weight: 400; color: var(--mute); margin-left: 4px; }
.plan-yearly { color: var(--ink-2); margin: 0 0 24px; font-size: 14px; line-height: 1.6; }
.plan-strike { text-decoration: line-through; color: var(--mute); margin-right: 8px; }
.plan-save { display: inline-block; background: var(--teal-50); color: var(--teal-700); font-size: 11px; font-weight: 700; letter-spacing: 0.04em; padding: 2px 8px; border-radius: 999px; }
.plan-features { list-style: none; padding: 0; margin: 0 0 24px; }
.plan-features li { padding: 6px 0; font-size: 14.5px; color: var(--ink-2); }
.btn-block { display: block; text-align: center; width: 100%; }
.plan-fineprint { font-size: 12px; color: var(--mute); margin: 10px 0 0; text-align: center; }

.addon-column { display: flex; flex-direction: column; gap: 16px; }
.addon-heading { font-size: 16px; font-weight: 600; margin: 0 0 4px; color: var(--ink-2); }
.addon-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 20px; display: flex; gap: 16px; transition: border-color .15s; }
.addon-card:hover { border-color: var(--teal-400); }
.addon-icon { font-size: 28px; flex-shrink: 0; }
.addon-name { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
.addon-desc { font-size: 13.5px; color: var(--ink-2); line-height: 1.55; margin: 0 0 8px; }
.addon-price { font-size: 14px; font-weight: 600; color: var(--teal-700); }
.addon-tease { font-size: 12.5px; color: var(--mute); padding: 0 4px; line-height: 1.5; }
@media (max-width: 600px) { .plan-card { padding: 22px; } .plan-price { font-size: 36px; } }
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
