<?php
// =====================================================================
// corporate.php — eClinicPro Workforce Health marketing page (/corporate).
//
// Sells the company product (employer portal at app…/company): workforce,
// screening rules + due dates, clinic bookings, fitness certificates,
// reminders, reports. CTAs → free trial (/company/register on the portal)
// and /book-a-demo. Claims kept to what is built (Phase 1): no lab
// integration, no compliance guarantee, no payment handling.
//
// Standalone marketing page, same pattern as for-patients.php.
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Workforce Health — Pre-employment & Periodic Health Checks for Companies | eClinicPro';
$metaDesc  = 'Run occupational health screening for your whole workforce: screening rules by job, automatic due dates, bookings at partner clinics, verifiable fitness certificates and reminders. Employers see fitness outcomes only, never medical records. Free 30-day trial.';
$activePage = 'corporate';
$trialUrl = ecp_portal_url('/company/register');
$loginUrl = ecp_portal_url('/company/login');

$cpFeatures = [
    ['👷', 'Your workforce in one place',
        'Branches, departments, job roles and employees, including contractors and temporary staff. Import everyone from Excel in minutes; mistakes are shown before anything is saved.'],
    ['📋', 'Screening rules by job',
        'Start from eClinicPro templates for your country and industry (welders, crane operators, work at height, office staff…), then adjust them. Mandatory checks are flagged, and removing one needs a written reason.'],
    ['⏰', 'Due dates that run themselves',
        'Pre-employment, periodic and exit checks are worked out for every person automatically: new joiners, job changes and leavers included. See what is overdue, due soon or done, by branch.'],
    ['🏥', 'Book real clinic slots',
        'Add the clinics that serve each branch, record the prices you agreed, and book a free slot from the doctor’s actual calendar. The clinic gets a normal appointment; the employee gets the details by email.'],
    ['✅', 'Fitness certificates you can verify',
        'The doctor records the outcome (fit, fit with restrictions, temporarily unfit, unfit) and signs. Every certificate has a QR code anyone can scan to confirm it is genuine and still valid.'],
    ['🔒', 'Medical privacy, built in',
        'You see the fitness outcome, work restrictions and validity, and only when the employee agrees. Diagnoses, test results and notes stay with the doctor. It is enforced in the software, not just promised.'],
    ['📨', 'Reminders without chasing',
        'Employees get an email before a check is due, when it is overdue and the day before their booking. Your HR team gets a Monday summary per branch.'],
    ['📊', 'Reports and spend',
        'Compliance by branch, department, job role or programme, exported to Excel or PDF. Clinics send monthly statements of the checks they did at your agreed prices.'],
];

$cpSteps = [
    ['Add your people', 'Import employees from Excel and set up branches and job roles.'],
    ['Choose the rules', 'Adopt screening templates for your industry and apply them to job roles, departments or branches.'],
    ['Book the checks', 'Due checks appear automatically. Book them at a clinic in your network in a few clicks.'],
    ['Stay compliant', 'Certificates come back after the visit, next due dates are set, and reports are always current.'],
];

$cpIndustries = ['Construction & infrastructure', 'Manufacturing & plants', 'Maritime & shipyards', 'Oil, gas & energy', 'Logistics & warehousing', 'Food handling & hospitality', 'Facilities & security', 'Offices with field teams'];

$cpFaqs = [
    ['Can we see our employees’ medical results?',
        'No. Your team sees whether each check is due, booked or done, and the fitness outcome with any work restrictions and its validity, only if the employee agrees to share it. Diagnoses, test values, prescriptions and notes stay between the employee and the doctor. This is enforced in the software.'],
    ['Do the screening templates make us legally compliant?',
        'They are a starting point for your country and industry, not legal advice. Your occupational health physician or safety adviser should confirm the rules for your sites. You can add checks, change frequencies, and remove recommended checks; removing a mandatory one needs a written reason that stays on record.'],
    ['Which clinics can we use?',
        'Clinics on eClinicPro that do occupational health checks. You choose which clinics serve which branches. If you already work with a clinic, ask them to join eClinicPro, or tell us and we will bring them on.'],
    ['Does eClinicPro collect payment for the clinics?',
        'No. You pay your clinics directly under your own agreement. eClinicPro records the prices you agreed and the checks done, so clinics can send you monthly statements and you can see your spend.'],
    ['Do employees need to install an app?',
        'No. Employees get booking details and reminders by email. If they want, they can sign in to eClinicPro with their phone number to see their checks, upload test reports and download their certificates.'],
    ['What about lab tests and X-rays?',
        'Employees (or the clinic) can upload test reports for lab and X-ray checks. Only the examining doctor sees them; you are told that a report was received.'],
    ['Which countries do you support?',
        'India first, with branches in other countries supported (for example a Gulf company with a site in India). UAE is next.'],
    ['How do we start, and what does it cost?',
        'Register your company; once we have checked your details your free 30-day trial starts. Talk to us about pricing for your workforce size before the trial ends.'],
];

$faqLd = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static fn (array $f): array => [
        '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $cpFaqs),
];

ob_start(); ?>
<script type="application/ld+json">
<?= json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>
<?php
$extraHead = ob_get_clean();

require __DIR__ . '/partials/header.php';
?>

<style>
    .cp-hero { padding: 104px 0 68px; text-align: center; position: relative; overflow: hidden; background: #f0f4f1; }
    .cp-hero-glow { position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(26, 122, 78, 0.08) 0%, transparent 65%); pointer-events: none; }
    .cp-hero-inner { position: relative; max-width: 820px; margin: 0 auto; }
    .cp-hero h1 { font-size: clamp(30px, 5vw, 48px); line-height: 1.08; letter-spacing: -0.02em; margin: 0 0 18px; }
    .cp-hero h1 .accent { color: #1a7a4e; }
    .cp-hero .lede { font-size: clamp(16px, 2.2vw, 19px); color: #475569; max-width: 660px; margin: 0 auto 30px; line-height: 1.55; }
    .cp-ctas { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .cp-note { margin-top: 16px; font-size: 13px; color: #64748b; }
    .cp-signin { margin-top: 6px; font-size: 13px; color: #64748b; }
    .cp-signin a { color: #1a7a4e; }

    .cp-section { padding: 66px 0; }
    .cp-section.alt { background: #f7faf8; }
    .cp-head { text-align: center; max-width: 660px; margin: 0 auto 44px; }
    .cp-head h2 { font-size: clamp(24px, 3.4vw, 34px); letter-spacing: -0.02em; margin: 8px 0 12px; }
    .cp-head p { color: #55606f; font-size: 16px; line-height: 1.55; margin: 0; }

    .cp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
    .cp-card { background: #fff; border: 1px solid #e6ece8; border-radius: 16px; padding: 24px 22px; transition: transform .18s ease, box-shadow .18s ease; }
    .cp-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(26,122,78,0.08); }
    .cp-ic { width: 50px; height: 50px; display: grid; place-items: center; font-size: 25px; border-radius: 13px; background: linear-gradient(135deg, #e8f5ee, #d6ebdf); margin-bottom: 14px; }
    .cp-card h3 { font-size: 17.5px; margin: 0 0 8px; letter-spacing: -0.01em; }
    .cp-card p { color: #55606f; font-size: 14.5px; line-height: 1.55; margin: 0; }

    .cp-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; counter-reset: step; }
    .cp-step { padding: 24px 22px 22px; background: #fff; border: 1px solid #e6ece8; border-radius: 16px; }
    .cp-step::before { counter-increment: step; content: counter(step); display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; background: #1a7a4e; color: #fff; font-weight: 700; font-size: 17px; margin-bottom: 14px; }
    .cp-step h3 { font-size: 17px; margin: 0 0 7px; }
    .cp-step p { color: #55606f; font-size: 14px; line-height: 1.5; margin: 0; }

    .cp-split { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; max-width: 920px; margin: 0 auto; }
    .cp-box { border-radius: 16px; padding: 24px 24px 18px; border: 1px solid #e6ece8; background: #fff; }
    .cp-box h3 { font-size: 17px; margin: 0 0 12px; }
    .cp-box ul { margin: 0; padding: 0; list-style: none; }
    .cp-box li { padding: 7px 0 7px 26px; position: relative; color: #334155; font-size: 15px; border-top: 1px solid #f1f5f9; }
    .cp-box li:first-child { border-top: 0; }
    .cp-box.yes li::before { content: '✓'; position: absolute; left: 2px; color: #047857; font-weight: 700; }
    .cp-box.no li::before { content: '✕'; position: absolute; left: 2px; color: #b91c1c; font-weight: 700; }
    .cp-box.yes { border-color: #bbe5d2; }
    .cp-box.no { border-color: #f3c8c8; background: #fffafa; }

    .cp-chips { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; max-width: 860px; margin: 0 auto; }
    .cp-chip { background: #fff; border: 1px solid #e6ece8; border-radius: 999px; padding: 9px 16px; font-size: 14.5px; color: #334155; }

    .cp-clinic { max-width: 860px; margin: 0 auto; display: flex; gap: 20px; align-items: center; justify-content: space-between; flex-wrap: wrap;
                 background: #fff; border: 1px solid #e6ece8; border-radius: 18px; padding: 26px 28px; }
    .cp-clinic h3 { margin: 0 0 6px; font-size: 19px; }
    .cp-clinic p { margin: 0; color: #55606f; font-size: 15px; line-height: 1.5; max-width: 560px; }

    .cp-faq { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 12px; }
    .cp-faq details { background: #fff; border: 1px solid #e6ece8; border-radius: 14px; overflow: hidden; }
    .cp-faq details[open] { border-color: #cfe0d6; box-shadow: 0 8px 24px rgba(26,122,78,0.06); }
    .cp-faq summary { list-style: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 18px 20px; font-size: 16px; font-weight: 600; color: #0f172a; }
    .cp-faq summary::-webkit-details-marker { display: none; }
    .cp-faq .chev { flex-shrink: 0; color: #64748b; transition: transform .2s ease; }
    .cp-faq details[open] .chev { transform: rotate(180deg); color: #1a7a4e; }
    .cp-faq .a { padding: 0 20px 18px; }
    .cp-faq .a p { margin: 0; color: #55606f; font-size: 15px; line-height: 1.6; }

    .cp-final { text-align: center; background: linear-gradient(135deg, #10603b, #1a7a4e); color: #fff; border-radius: 22px; padding: 56px 28px; }
    .cp-final h2 { font-size: clamp(24px, 3.4vw, 34px); margin: 0 0 12px; letter-spacing: -0.02em; color: #fff; }
    .cp-final p { color: rgba(255,255,255,0.9); font-size: 17px; margin: 0 auto 26px; max-width: 540px; line-height: 1.5; }
    .cp-final .btn-primary { background: #fff; color: #10603b; }
    .cp-final .btn-ghost { color: #fff; border-color: rgba(255,255,255,0.5); }

    @media (max-width: 720px) {
        .cp-hero { padding: 84px 0 52px; }
        .cp-section { padding: 48px 0; }
        .cp-split { grid-template-columns: 1fr; }
        .cp-final { padding: 40px 20px; border-radius: 18px; }
        .cp-faq summary { font-size: 15px; padding: 16px; }
        .cp-faq .a { padding: 0 16px 16px; }
    }
</style>

<!-- ═══════════════ HERO ═══════════════ -->
<section class="cp-hero">
    <div class="cp-hero-glow"></div>
    <div class="wrap cp-hero-inner reveal">
        <span class="hp-eyebrow">For companies · Workforce Health</span>
        <h1>Every worker’s health check, <span class="accent">on time</span>, without seeing anyone’s medical records</h1>
        <p class="lede">
            Pre-employment, periodic and exit checks for your whole workforce: the right checks for each job,
            due dates that run themselves, bookings at partner clinics, and fitness certificates you can verify.
        </p>
        <div class="cp-ctas">
            <a href="<?= e($trialUrl) ?>" class="btn btn-primary btn-lg">Start free 30-day trial</a>
            <a href="/book-a-demo" class="btn btn-ghost btn-lg">Book a demo →</a>
        </div>
        <p class="cp-note">No card needed · India first, Gulf-ready · One site or many branches</p>
        <p class="cp-signin">Already registered? <a href="<?= e($loginUrl) ?>">Sign in to your company account</a></p>
    </div>
</section>

<!-- ═══════════════ FEATURES ═══════════════ -->
<section class="cp-section">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">What you get</span>
            <h2>Occupational health screening, without the spreadsheets</h2>
            <p>Everything HR and safety teams need to keep every person fit for their job, and to prove it.</p>
        </div>
        <div class="cp-grid">
            <?php foreach ($cpFeatures as [$ic, $title, $body]): ?>
                <div class="cp-card reveal">
                    <div class="cp-ic"><?= $ic ?></div>
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ HOW IT WORKS ═══════════════ -->
<section class="cp-section alt">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">How it works</span>
            <h2>From spreadsheet to compliant in four steps</h2>
        </div>
        <div class="cp-steps">
            <?php foreach ($cpSteps as [$title, $body]): ?>
                <div class="cp-step reveal"><h3><?= e($title) ?></h3><p><?= e($body) ?></p></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ PRIVACY ═══════════════ -->
<section class="cp-section" id="privacy">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Medical privacy</span>
            <h2>You see fitness for work. The doctor keeps the medical details.</h2>
            <p>Employees trust the process, and you never hold health data you shouldn’t.</p>
        </div>
        <div class="cp-split reveal">
            <div class="cp-box yes">
                <h3>What your company sees</h3>
                <ul>
                    <li>Which checks are due, booked, overdue or done</li>
                    <li>Fitness outcome, if the employee agrees</li>
                    <li>Work restrictions, e.g. “no work at height”</li>
                    <li>Certificate validity and expiry</li>
                    <li>That a test report was received</li>
                </ul>
            </div>
            <div class="cp-box no">
                <h3>What stays with the doctor</h3>
                <ul>
                    <li>Diagnoses and medical history</li>
                    <li>Test results and lab values</li>
                    <li>Examination findings and notes</li>
                    <li>Prescriptions</li>
                    <li>Uploaded lab and X-ray reports</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ INDUSTRIES ═══════════════ -->
<section class="cp-section alt">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Who it’s for</span>
            <h2>Built for workplaces where fitness matters</h2>
        </div>
        <div class="cp-chips reveal">
            <?php foreach ($cpIndustries as $ind): ?><span class="cp-chip"><?= e($ind) ?></span><?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FOR CLINICS ═══════════════ -->
<section class="cp-section">
    <div class="wrap">
        <div class="cp-clinic reveal">
            <div>
                <h3>Run an occupational health clinic?</h3>
                <p>Join the eClinicPro network: companies book your free slots, you examine and sign certificates from the visit screen you already use, and send them monthly statements.</p>
            </div>
            <a href="/contact" class="btn btn-ghost">Talk to us →</a>
        </div>
    </div>
</section>

<!-- ═══════════════ FAQ ═══════════════ -->
<section class="cp-section alt" id="faq">
    <div class="wrap">
        <div class="cp-head reveal">
            <span class="hp-eyebrow">Questions</span>
            <h2>Frequently asked questions</h2>
        </div>
        <div class="cp-faq reveal">
            <?php foreach ($cpFaqs as $i => [$q, $a]): ?>
                <details<?= $i === 0 ? ' open' : '' ?>>
                    <summary>
                        <span><?= e($q) ?></span>
                        <svg class="chev" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </summary>
                    <div class="a"><p><?= e($a) ?></p></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════ FINAL CTA ═══════════════ -->
<section class="cp-section">
    <div class="wrap">
        <div class="cp-final reveal">
            <h2>Get your workforce screening under control</h2>
            <p>Start with a free 30-day trial, or let us show you around with your own job roles.</p>
            <div class="cp-ctas">
                <a href="<?= e($trialUrl) ?>" class="btn btn-primary btn-lg">Start free trial</a>
                <a href="/book-a-demo" class="btn btn-ghost btn-lg">Book a demo</a>
            </div>
        </div>
    </div>
</section>

<?php
$hideFinalCta = true;
require __DIR__ . '/partials/footer.php';
?>
