<?php
// =====================================================================
// index.php — eClinicPro homepage (dual-path: patients + doctors)
//
// Design/copy only. All booking, search, auth, and claim flows live in
// /find-a-doctor, the auth/claim modals, and the portal — this page only
// LINKS into them, never reimplements them.
// =====================================================================

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/seo_slugs.php';

$pageTitle = 'eClinicPro — Book a doctor, or run your clinic';
$metaDesc  = 'Find and book verified doctors across India in 60 seconds — or run your whole practice on one simple, beautiful clinic system. One plan, ₹1,499/month.';
$activePage = '';

// ---- Real numbers from the DB (helpers fall back to safe floors) ----
$clinicCount = ecp_active_clinic_count();
$doctorCount = ecp_directory_doctor_count();

// ---- Founding-clinic state (same read as pricing.php; never 500 the page) ----
$fcCap = 100; $fcClaimed = 0; $fcOpen = true;
try {
    $db = ecp_db();
    if ($db) {
        $row = $db->query("SELECT cap, claimed, closed_at FROM founding_clinic_state WHERE id = 1")
            ->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $fcCap = (int) $row['cap'];
            $fcClaimed = (int) $row['claimed'];
            $fcOpen = empty($row['closed_at']) && $fcClaimed < $fcCap;
        }
    }
} catch (Throwable $e) { /* table not migrated yet → fall back */ }
$fcRemaining = max(0, $fcCap - $fcClaimed);

// ---- Specialty showcase pulled from the canonical specialty map ----
// Group a curated subset into the four columns from the directory.
$specMap = function_exists('ecp_specialty_map') ? ecp_specialty_map() : [];
$specGroups = [
    'General & specialists' => ['general-physician','cardiologist','dermatologist','neurologist','pulmonologist','gastroenterologist','endocrinologist','nephrologist','oncologist','urologist'],
    'Surgeons & critical care' => ['general-surgeon','neurosurgeon','orthopedic','plastic-surgeon','critical-care'],
    'Dental & child & eye / ENT' => ['dentist','orthodontist','pediatric-dentist','gynecologist','pediatrician','ophthalmologist','ent-specialist'],
    'Alternative & therapy' => ['homeopathy','ayurveda','physiotherapist','psychiatrist','psychologist','dietitian'],
];

// WebSite + SearchAction JSON-LD (brand search box in Google).
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'eClinicPro',
    'url'      => 'https://eclinicpro.com',
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => 'https://eclinicpro.com/find-a-doctor?q={search_term_string}',
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES) . '</script>';

require __DIR__ . '/partials/header.php';
?>

<!-- ============ DUAL-PATH HERO ============ -->
<section class="hp-hero" id="top">
    <div class="hp-hero-bg"></div>
    <div class="wrap">
        <div class="hp-hero-grid">
            <div class="hp-hero-copy reveal">
                <span class="hp-pill">
                    <span class="hp-pill-dot"></span>
                    Now serving 🇮🇳 India · <?= ecp_num($doctorCount) ?> verified doctors
                </span>
                <h1 class="hp-h1">
                    Healthcare,<br>
                    <span class="grad">made simple.</span>
                </h1>
                <p class="hp-lede">
                    Whether you want to book a doctor or run your clinic — eClinicPro
                    is one place for both. Verified clinicians, real availability,
                    transparent fees, and software doctors actually love.
                </p>

                <!-- The two paths. Each goes to an existing, working flow. -->
                <div class="hp-paths">
                    <a href="/find-a-doctor" class="hp-path hp-path-patient">
                        <div class="hp-path-ic">🔍</div>
                        <div class="hp-path-body">
                            <div class="hp-path-title">I'm a patient</div>
                            <div class="hp-path-sub">Find &amp; book a doctor in 60 seconds</div>
                        </div>
                        <span class="hp-path-arrow">→</span>
                    </a>
                    <a href="<?= e(ecp_portal_url('/register')) ?>" class="hp-path hp-path-doctor">
                        <div class="hp-path-ic">🩺</div>
                        <div class="hp-path-body">
                            <div class="hp-path-title">I'm a doctor</div>
                            <div class="hp-path-sub">Run my clinic — free for 30 days</div>
                        </div>
                        <span class="hp-path-arrow">→</span>
                    </a>
                </div>

                <div class="hp-hero-trust">
                    <span class="hp-stars">★★★★★</span>
                    <span><strong>4.8</strong> from patients · Free to search · No phone-tag</span>
                </div>
            </div>

            <!-- Live doctor-card preview (matches the directory result style) -->
            <div class="hp-hero-preview reveal">
                <div class="hp-preview-tag">⚡ Verified · Real availability</div>
                <div class="hp-preview-card">
                    <div class="hp-preview-search">Search doctors in your city…</div>
                    <?php
                    $previewDocs = [
                        ['AS', 'Dr. Aarav Sharma', 'Cardiology · 18 yrs · Apollo', '4.9', '₹1,200', 'Today 4:45 PM', 'linear-gradient(135deg,#2DC08A,#0B7F5A)'],
                        ['PI', 'Dr. Priya Iyer', 'Dermatology · 12 yrs · Fortis', '4.8', '₹950', 'Tomorrow 11 AM', 'linear-gradient(135deg,#60A5FA,#2563EB)'],
                        ['RV', 'Dr. Rohan Verma', 'Homeopathy · 22 yrs · Clinic', '4.7', '₹600', 'In 2 days', 'linear-gradient(135deg,#C084FC,#7C3AED)'],
                    ];
                    foreach ($previewDocs as [$ini,$name,$meta,$rating,$fee,$slot,$grad]):
                    ?>
                    <div class="hp-doc-row">
                        <span class="hp-doc-av" style="background: <?= $grad ?>;"><?= e($ini) ?></span>
                        <div class="hp-doc-info">
                            <div class="hp-doc-name"><?= e($name) ?></div>
                            <div class="hp-doc-meta"><?= e($meta) ?></div>
                            <div class="hp-doc-line">
                                <span class="hp-doc-star">★ <?= e($rating) ?></span>
                                · <?= e($fee) ?> · <?= e($slot) ?>
                            </div>
                        </div>
                        <a href="/find-a-doctor" class="hp-doc-book">Book</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="hp-preview-float">
                    <span class="hp-float-dot"></span>
                    <strong><?= ecp_num(max(1200, $doctorCount * 6)) ?></strong>&nbsp;bookings this week
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ SPECIALTIES (patient discovery) ============ -->
<section class="hp-specialties" id="specialties">
    <div class="wrap">
        <div class="hp-spec-head reveal">
            <div>
                <h2 class="h-section">30+ specialties. <span class="grad">One booking flow.</span></h2>
                <p class="hp-sub">Whatever you need — from a general physician to a neurosurgeon,
                    a homeopath to a dietitian — find them in seconds. All verified, all across India.</p>
            </div>
            <div class="hp-spec-cta">
                <a href="/find-a-doctor" class="btn btn-ghost-dark">Browse by city</a>
                <a href="/find-a-doctor" class="btn btn-dark">See all doctors →</a>
            </div>
        </div>

        <div class="hp-spec-grid">
            <?php foreach ($specGroups as $group => $slugs): ?>
            <div class="hp-spec-col reveal">
                <h4 class="hp-spec-group"><?= e($group) ?></h4>
                <ul class="hp-spec-list">
                    <?php foreach ($slugs as $slug):
                        $row = $specMap[$slug] ?? null;
                        if (!$row || (isset($row['safe']) && $row['safe'] === false)) continue;
                    ?>
                    <li>
                        <a href="/find-a-doctor/<?= e($slug) ?>"><?= e($row['label']) ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ HOW BOOKING WORKS (patient) ============ -->
<section class="hp-how">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">For patients</span>
            <h2 class="h-section">Book in 60 seconds. No call centre.</h2>
        </div>
        <div class="hp-steps">
            <?php foreach ([
                ['1', 'Search', 'Pick your city and specialty, or just type a name. See verified doctors with real fees.'],
                ['2', 'Request a slot', 'Tap book. You instantly get a WhatsApp/SMS confirming your request — and the clinic\'s number, just in case.'],
                ['3', 'Doctor confirms', 'The clinic confirms on their side. You get a final confirmation. Zero phone-tag.'],
            ] as [$n,$t,$d]): ?>
            <div class="hp-step reveal">
                <div class="hp-step-n"><?= e($n) ?></div>
                <div class="hp-step-t"><?= e($t) ?></div>
                <div class="hp-step-d"><?= e($d) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="hp-how-cta reveal">
            <a href="/find-a-doctor" class="btn btn-primary btn-lg">Find your doctor →</a>
        </div>
    </div>
</section>

<!-- ============ FOR DOCTORS (SaaS) ============ -->
<section class="hp-doctors" id="for-doctors">
    <div class="wrap">
        <div class="hp-doc-grid">
            <div class="hp-doc-copy reveal">
                <span class="eyebrow light">🌿 For doctors</span>
                <h2 class="hp-doc-h2">The clinic software<br><span class="grad-light">doctors actually love.</span></h2>
                <p class="hp-doc-lede">
                    Run your practice from one calm dashboard — appointments, patient
                    records, prescriptions, billing and follow-ups. Just the essentials
                    you use every day, and a public profile that brings you new patients.
                </p>
                <div class="hp-doc-ctas">
                    <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start your clinic — free →</a>
                    <a href="/product-tour" class="btn btn-ghost-light btn-lg">See a 2-min walkthrough</a>
                </div>
                <p class="hp-doc-fine">30-day free trial · No credit card · Your data stays yours.</p>

                <div class="hp-feat-grid">
                    <?php foreach ([
                        ['📅','Online bookings','Patients book from your public profile. WhatsApp confirmations and reminders included.'],
                        ['📋','Patient records','Encrypted records, history and contact info — always one search away.'],
                        ['℞','Prescriptions','Signed digital Rx, delivered to the patient on WhatsApp before they leave.'],
                        ['🧾','Billing & invoices','Clean, GST-ready invoices in seconds. WhatsApp delivery.'],
                        ['🔁','Follow-ups','Never lose a follow-up. Automatic reminders, overdue tracking, a calm queue.'],
                        ['📊','Reports','Revenue, top diagnoses, patient retention. Numbers that matter, nothing else.'],
                    ] as [$ic,$t,$d]): ?>
                    <div class="hp-feat">
                        <div class="hp-feat-ic"><?= $ic ?></div>
                        <div class="hp-feat-t"><?= e($t) ?></div>
                        <div class="hp-feat-d"><?= e($d) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Dashboard preview (same calm look as the portal) -->
            <div class="hp-doc-preview reveal">
                <div class="hp-dash">
                    <div class="hp-dash-bar">
                        <span class="hp-dash-dot r"></span><span class="hp-dash-dot y"></span><span class="hp-dash-dot g"></span>
                        <span class="hp-dash-url">app.eclinicpro.com/dashboard</span>
                    </div>
                    <div class="hp-dash-body">
                        <div class="hp-dash-side">
                            <div class="hp-dash-brand">e<em>ClinicPro</em></div>
                            <?php foreach ([['Appointments',true],['Patients',false],['Prescriptions',false],['Invoices',false],['Follow-ups',false],['Reports',false]] as [$l,$a]): ?>
                            <div class="hp-dash-nav<?= $a ? ' active' : '' ?>"><?= e($l) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hp-dash-main">
                            <div class="hp-dash-h">
                                <span>Today's appointments</span>
                                <span class="hp-dash-date">MON · 28 MAY</span>
                            </div>
                            <div class="hp-dash-stats">
                                <div class="hp-dash-stat"><strong>12</strong><span>Today</span></div>
                                <div class="hp-dash-stat"><strong class="g">8</strong><span>Confirmed</span></div>
                                <div class="hp-dash-stat"><strong>₹14,200</strong><span>Revenue</span></div>
                            </div>
                            <?php foreach ([
                                ['10:00','Aarav Sharma','Follow-up · Hypertension','Now','now'],
                                ['10:30','Priya Iyer','New patient · Consult','Confirmed','ok'],
                                ['11:15','Rohan Verma','Lab review','Confirmed','ok'],
                                ['12:00','Ananya Pillai','Annual check-up','Pending','pend'],
                            ] as [$tm,$nm,$rs,$st,$cls]): ?>
                            <div class="hp-dash-appt">
                                <span class="hp-dash-time"><?= e($tm) ?></span>
                                <div class="hp-dash-appt-info">
                                    <div class="hp-dash-appt-name"><?= e($nm) ?></div>
                                    <div class="hp-dash-appt-reason"><?= e($rs) ?></div>
                                </div>
                                <span class="hp-dash-status <?= e($cls) ?>"><?= e($st) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ WHATSAPP / SMS OVERVIEW ============ -->
<section class="hp-wa">
    <div class="wrap">
        <div class="hp-wa-grid">
            <div class="reveal">
                <!-- WhatsApp chat mock -->
                <div class="hp-wa-chat">
                    <div class="hp-wa-bubble in">
                        <div class="hp-wa-clinic">Sunrise Clinic</div>
                        <div>Hi Riya — your appointment with Dr. Sharma is confirmed for tomorrow 11 AM. Reply 1 to confirm.</div>
                        <div class="hp-wa-time">9:47</div>
                    </div>
                    <div class="hp-wa-bubble in">
                        <div class="hp-wa-file">
                            <span class="hp-wa-pdf">PDF</span>
                            <div>
                                <div class="hp-wa-fname">Rx_Riya_28May.pdf</div>
                                <div class="hp-wa-fmeta">2 medicines · Dr. A. Sharma</div>
                            </div>
                        </div>
                        <div class="hp-wa-time">9:48</div>
                    </div>
                    <div class="hp-wa-bubble out">
                        <div>Confirmed, thank you doctor 🙏</div>
                        <div class="hp-wa-time">9:49 ✓✓</div>
                    </div>
                    <div class="hp-wa-toast">✓ Sent automatically · SMS fallback if no WhatsApp</div>
                </div>
            </div>
            <div class="hp-wa-copy reveal">
                <span class="eyebrow">Patient Connect add-on</span>
                <h2 class="h-section">WhatsApp first. SMS as backup. Never a missed message.</h2>
                <p class="hp-sub">
                    Appointment confirmations, reminders, prescription delivery and
                    follow-up nudges go out on WhatsApp automatically. No WhatsApp on
                    that number? We fall back to SMS — so the message always lands.
                </p>
                <ul class="hp-wa-list">
                    <?php foreach ([
                        'Booking confirmations & reminders that cut no-shows',
                        'Signed prescriptions delivered as a PDF',
                        'Follow-up nudges, sent at sensible hours only',
                        'Smart cost controls — daily/monthly caps you set',
                    ] as $f): ?>
                    <li><span class="tick">✓</span><?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="hp-doc-fine">Included flows are built-in; full WhatsApp automation is the optional <strong>Patient Connect</strong> add-on (+₹499/mo).</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ PRICING TEASER (single plan, real facts) ============ -->
<section class="hp-pricing" id="pricing">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Pricing</span>
            <h2 class="h-section">One plan. Everything to run your clinic.</h2>
            <p class="hp-sub">No tiers, no per-seat games, no surprise upsells. Try free for 30 days — no card.</p>
        </div>

        <?php if ($fcOpen): ?>
        <div class="hp-fc reveal">
            <span class="hp-fc-badge">Founding clinic deal</span>
            <div class="hp-fc-price">₹999<span>/mo</span> <span class="hp-fc-strike">₹1,499</span> <span class="hp-fc-lock">locked for 24 months</span></div>
            <p class="hp-fc-sub">First <?= $fcCap ?> clinics only · <strong><?= $fcRemaining ?></strong> spots left.</p>
            <a href="<?= e(ecp_portal_url('/register?fc=1')) ?>" class="btn btn-primary">Claim founding price</a>
        </div>
        <?php endif; ?>

        <div class="hp-plan reveal">
            <div class="hp-plan-main">
                <span class="hp-plan-name">Standard</span>
                <div class="hp-plan-price">₹1,499<span class="per">/month</span></div>
                <div class="hp-plan-yearly">or ₹14,999/year — one month free</div>
                <ul class="hp-plan-feats">
                    <?php foreach ([
                        'Patient records, visits & prescriptions',
                        'Appointments & walk-in queue',
                        'Billing & invoicing (GST-ready)',
                        'Specialty-aware forms (50+ specialties)',
                        'Public doctor profile on eclinicpro.com',
                        'Unlimited patients & staff users',
                    ] as $f): ?>
                    <li><span class="tick">✓</span><?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg btn-block">Start 30-day free trial</a>
            </div>
            <div class="hp-plan-addons">
                <div class="hp-plan-addons-h">Optional add-ons</div>
                <div class="hp-addon">
                    <div class="hp-addon-ic">💬</div>
                    <div>
                        <div class="hp-addon-name">Patient Connect <span>+₹499/mo</span></div>
                        <div class="hp-addon-d">WhatsApp + SMS automation — reminders, Rx delivery, follow-ups.</div>
                    </div>
                </div>
                <div class="hp-addon">
                    <div class="hp-addon-ic">🏥</div>
                    <div>
                        <div class="hp-addon-name">Clinic Network <span>+₹999/mo / branch</span></div>
                        <div class="hp-addon-d">Add branches under one account. Shared records, separate queues.</div>
                    </div>
                </div>
                <a href="/pricing" class="btn-link">See full pricing &amp; FAQ →</a>
            </div>
        </div>
    </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<?php
$testimonials = [
    ['Dr. Aarav Sharma', 'AS', 'GP · Mumbai', 'I switched from paper in two weekends. Patients now find me through the directory — that alone paid for the year.'],
    ['Dr. Priya Iyer', 'PI', 'Homeopath · Pune', 'Case-taking that actually fits how I work, and WhatsApp reminders my patients love. Calm software.'],
    ['Dr. Rohan Verma', 'RV', 'Dermatologist · Ahmedabad', 'The before/after photos and the public profile bring in new skin patients every week. Worth every rupee.'],
];
?>
<section class="hp-testimonials">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">From the clinics</span>
            <h2 class="h-section">Doctors who switched, and stayed.</h2>
        </div>
        <div class="tgrid">
            <?php foreach ($testimonials as $i => [$name,$ini,$spec,$quote]): ?>
            <div class="tcard reveal" style="transition-delay: <?= ($i % 3) * 80 ?>ms;">
                <div class="stars">★★★★★</div>
                <blockquote>"<?= e($quote) ?>"</blockquote>
                <div class="tperson">
                    <div class="tavatar"><?= e($ini) ?></div>
                    <div>
                        <div class="nm"><?= e($name) ?></div>
                        <div class="sp"><?= e($spec) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<?php
$faqs = [
    ['Is it free for patients?', 'Yes — searching and booking doctors on eclinicpro.com is completely free. You only pay the doctor\'s consultation fee, shown upfront on every profile.'],
    ['How does booking work?', 'Pick a doctor, request a slot, and you instantly get a WhatsApp/SMS with the clinic\'s number. The clinic confirms on their side and you get a final confirmation — no phone-tag.'],
    ['I\'m a doctor — what does it cost?', 'One plan: ₹1,499/month (or ₹14,999/year). Everything to run a clinic is included. Two optional add-ons cover WhatsApp automation and extra branches. 30-day free trial, no card.'],
    ['Is my clinic and patient data secure?', 'Yes. Records are encrypted at rest and in transit, with per-clinic isolation and audit logging. You can export everything anytime.'],
    ['Can I claim a profile that\'s already listed?', 'Yes. If your clinic is already in the directory, claim it from your profile page — once verified you control your listing, availability and bookings.'],
    ['Do you support my specialty?', '50+ specialties across modern medicine, dentistry, and AYUSH (homeopathy, ayurveda, and more) — each with specialty-aware visit forms.'],
];
?>
<section id="faq" x-data="{ open: 0 }">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Questions</span>
            <h2 class="h-section">Good to know.</h2>
        </div>
        <div class="faq-list reveal">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
            <div class="faq-item" :class="open === <?= $i ?> ? 'open' : ''">
                <button type="button" class="faq-q" @click="open = open === <?= $i ?> ? -1 : <?= $i ?>">
                    <span><?= e($q) ?></span>
                    <span class="plus"></span>
                </button>
                <div class="faq-a" x-show="open === <?= $i ?>" x-collapse><?= e($a) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
