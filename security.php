<?php
// =====================================================================
// security.php — security & compliance
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Security & Compliance — eClinicPro';
$metaDesc = 'Real encryption, real compliance, real audit logs. HIPAA, GDPR, DPDP and more — built so cautious doctors stay cautious about everything except us.';
$activePage = 'security';

$pillars = [
    ['🛡️', 'Encryption everywhere', 'AES-256 at rest. TLS 1.3 in transit. Per-clinic keys, rotated quarterly. Field-level encryption for the most sensitive data (allergies, diagnoses, mental health notes).'],
    ['✓', 'Compliant by default', 'HIPAA (US), GDPR (EU/UK), DPDP (India), PIPEDA (Canada), POPIA (South Africa), HDS (France). Region-aware data residency.'],
    ['📋', 'You own your data', 'Export everything as portable JSON, CSV, or HL7 FHIR — anytime, free. Delete your account and we erase within 30 days, audit-logged.'],
    ['🔐', 'Granular access control', 'Roles for doctor, nurse, receptionist, accountant, owner. Per-action permissions. Time-limited access for locums.'],
    ['📊', 'Audit trail forever', 'Every read, write, and export is logged with user, IP, device, and timestamp. Tamper-evident, exportable on demand.'],
    ['🌐', 'Data residency you choose', 'Pick where your data lives: US, EU, India, UAE, Singapore. It never leaves that region — not for backups, not for analytics.'],
    ['⚡', 'Resilient infrastructure', '99.95% uptime SLA (Hospital plan). Three-region failover. Backups every 15 minutes, restorable to any point in the last 90 days.'],
    ['🧪', 'Independently tested', 'Quarterly third-party penetration tests. Annual SOC 2 Type II audit. Public bug bounty up to $25,000 per critical finding.'],
    ['📜', 'Vendor & sub-processor list', 'A short, public list of every vendor that touches your data. We notify you 30 days before any change.'],
];

$certs = ['HIPAA', 'GDPR', 'DPDP 2023', 'SOC 2 Type II', 'ISO 27001', 'HL7 FHIR R4', 'PIPEDA', 'POPIA', 'HDS (FR)'];

$facts = [
    ['256-bit', 'AES encryption'],
    ['99.95%', 'Uptime SLA'],
    ['15 min', 'Backup interval'],
    ['<24h', 'Critical patch SLA'],
];

$practices = [
    ['Background checks for every employee', 'Every Clinic engineer with production access undergoes a criminal background check and signs an enforceable confidentiality agreement.'],
    ['Zero-trust internal network', 'No long-lived credentials. Production access is mediated through a session-based broker with mandatory MFA, full session recording, and per-action approval for sensitive operations.'],
    ['Quarterly disaster recovery drills', 'Every quarter we simulate a full region failure, restore from backups, and measure RTO/RPO. The results are published to enterprise customers.'],
    ['Annual SOC 2 Type II audit', 'Independent audit covering security, availability, confidentiality, and privacy. Reports available under NDA.'],
    ['Subprocessor transparency', 'A public list of every vendor that touches customer data. We notify in advance of any change with a 30-day window to object.'],
    ['Phishing-resistant MFA mandatory', 'WebAuthn / passkeys for all employees. SMS-only MFA is not permitted internally and not recommended for clinics.'],
];

$faqs = [
    ['Where is my data stored?', 'You choose your region at signup — US, EU (Frankfurt), India (Mumbai), UAE, or Singapore. Data, backups, and analytics never leave that region.'],
    ['Who can see my patients\' data?', 'Only the people you grant access to in your clinic, plus a tiny on-call team of engineers when responding to a support ticket you opened — and only with your explicit consent for that ticket. Every access is logged.'],
    ['What if a patient asks for their data to be deleted?', 'GDPR Article 17 / DPDP "right to erasure" is built in. Click delete on the patient record — the data is purged from production within 24 hours and from backups within 30 days, with a tamper-evident certificate.'],
    ['How does export work if I want to leave?', 'Go to Settings → Export. You get a signed ZIP containing every patient record, prescription, invoice, and attachment as portable JSON + HL7 FHIR R4 + PDF. No fee, no lock-in.'],
    ['Do you train AI on my data?', 'No. Patient data is never used to train models — ours or anyone else\'s. AI assistants that work on your data run inside your data residency region and forget after each session.'],
    ['Can I get a signed Business Associate Agreement (BAA) or DPA?', 'Yes. BAA (HIPAA), DPA (GDPR), and India DPDP processor agreement are available on all paid plans. Download instantly from your dashboard — no sales call.'],
];

$docs = [
    ['Business Associate Agreement (HIPAA)', 'Auto-countersigned PDF · 12 pages'],
    ['Data Processing Agreement (GDPR)', 'Standard Contractual Clauses included · 18 pages'],
    ['India DPDP Processor Agreement', 'Section 8 compliant · 9 pages'],
    ['Subprocessor List', 'Live — 14 vendors, last updated Apr 2026'],
    ['SOC 2 Type II Report', 'Q1 2026 · under NDA, 1-click request'],
    ['Penetration Test Summary', 'Q1 2026 · public summary'],
];

require __DIR__ . '/partials/header.php';
?>

<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">Trust & security</span>
        <h1 class="h-display" style="font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px;">Patient data deserves better than "trust us".</h1>
        <p class="lede" style="font-size: 19px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            Real encryption, real compliance, real audit logs — and a real list of every vendor that touches your data. Built so cautious doctors stay cautious about everything except us.
        </p>
    </div>
</section>

<section style="padding: 40px 0; border-top: 0.5px solid var(--line); border-bottom: 0.5px solid var(--line); background: var(--bg-2);">
    <div class="wrap">
        <div class="stats">
            <?php foreach ($facts as [$num, $label]): ?>
            <div class="stat reveal">
                <div class="stat-num" style="font-size: 36px;"><?= e($num) ?></div>
                <div class="stat-label"><?= e($label) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="compliance" style="padding: 80px 0 40px;">
    <div class="wrap">
        <div class="section-head reveal" style="margin-bottom: 32px;">
            <span class="eyebrow">Certifications & frameworks</span>
            <h2 class="h-section">Compliant in the regions you operate.</h2>
            <p class="lede">eClinicPro is built to the highest healthcare privacy standards in every region we serve. Reports and DPAs available on demand.</p>
        </div>
        <div class="cert-row reveal" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
            <?php foreach ($certs as $c): ?>
            <div class="cert" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: var(--bg-2); border-radius: 999px; font-size: 13px; font-weight: 500;">
                <span class="ico">🛡️</span><?= e($c) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding-top: 40px; padding-bottom: 100px;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Nine pillars of trust</span>
            <h2 class="h-section">How we protect every record.</h2>
        </div>
        <div class="sec-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;">
            <?php foreach ($pillars as $i => [$ic, $t, $d]): ?>
            <div class="sec-card reveal" style="background: #fff; border: 0.5px solid var(--line); border-radius: 16px; padding: 28px; transition-delay: <?= ($i % 3) * 60 ?>ms;">
                <div class="ico" style="width: 40px; height: 40px; border-radius: 10px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; font-size: 18px; margin-bottom: 14px;"><?= $ic ?></div>
                <h4 style="font-size: 16px; font-weight: 500; margin-bottom: 8px;"><?= e($t) ?></h4>
                <p style="font-size: 13.5px; color: var(--mute); line-height: 1.6;"><?= e($d) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="bg-grey">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Internal practices</span>
            <h2 class="h-section">What we do behind the scenes.</h2>
            <p class="lede">Security isn't a feature — it's the daily operating system. Here's how the team works.</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;" class="practice-grid">
            <?php foreach ($practices as [$t, $d]): ?>
            <div class="reveal" style="background: #fff; border-radius: 16px; padding: 28px; border: 0.5px solid var(--line);">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 28px; height: 28px; border-radius: 8px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center;">✓</div>
                    <h4 style="font-size: 16px; font-weight: 500;"><?= e($t) ?></h4>
                </div>
                <p style="font-size: 14px; color: var(--mute); line-height: 1.6;"><?= e($d) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section>
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Documents you can download</span>
            <h2 class="h-section">No NDAs. No sales calls.</h2>
            <p class="lede">The documents your compliance officer wants — available instantly from your dashboard.</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; max-width: 800px; margin: 0 auto;" class="docs-grid">
            <?php foreach ($docs as [$title, $sub]): ?>
            <a href="#" class="reveal" style="background: #fff; border: 0.5px solid var(--line); border-radius: 12px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit;">
                <div style="width: 32px; height: 40px; background: var(--bg-2); border-radius: 4px; display: grid; place-items: center; font-size: 9px; font-weight: 700; color: var(--red, #c00); flex-shrink: 0;">PDF</div>
                <div style="flex: 1;">
                    <div style="font-size: 14px; font-weight: 500;"><?= e($title) ?></div>
                    <div style="font-size: 12px; color: var(--mute); margin-top: 2px;"><?= e($sub) ?></div>
                </div>
                <span>→</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="bg-grey" x-data="{ open: 0 }">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Security FAQ</span>
            <h2 class="h-section">The hard questions, answered honestly.</h2>
        </div>
        <div class="faq-list reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
            <div class="faq-item" :class="open === <?= $i ?> ? 'open' : ''">
                <button type="button" class="faq-q" @click="open = open === <?= $i ?> ? -1 : <?= $i ?>">
                    <span><?= e($q) ?></span><span class="plus"></span>
                </button>
                <div class="faq-a" x-show="open === <?= $i ?>" x-collapse><?= e($a) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
@media (max-width: 800px) {
    .sec-grid, .practice-grid, .docs-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
