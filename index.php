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

        <?php
        // Emoji per specialty slug — friendly, scannable tiles (Nas-Daily style).
        $specIcons = [
            'general-physician'=>'🩺','cardiologist'=>'❤️','dermatologist'=>'✨','neurologist'=>'🧠',
            'pulmonologist'=>'🫁','gastroenterologist'=>'🍽️','endocrinologist'=>'⚖️','nephrologist'=>'💧',
            'oncologist'=>'🎗️','urologist'=>'🚹','general-surgeon'=>'🔪','neurosurgeon'=>'🧠',
            'orthopedic'=>'🦴','plastic-surgeon'=>'💉','critical-care'=>'🚑','dentist'=>'🦷',
            'orthodontist'=>'😁','pediatric-dentist'=>'🪥','gynecologist'=>'🌸','pediatrician'=>'👶',
            'ophthalmologist'=>'👁️','ent-specialist'=>'👂','homeopathy'=>'🌿','ayurveda'=>'🪔',
            'physiotherapist'=>'🤸','psychiatrist'=>'🧩','psychologist'=>'💭','dietitian'=>'🥗',
        ];
        ?>
        <div class="hp-spec-grid">
            <?php foreach ($specGroups as $group => $slugs): ?>
            <div class="hp-spec-col reveal">
                <h4 class="hp-spec-group"><?= e($group) ?></h4>
                <div class="hp-spec-tiles">
                    <?php foreach ($slugs as $slug):
                        $row = $specMap[$slug] ?? null;
                        if (!$row || (isset($row['safe']) && $row['safe'] === false)) continue;
                    ?>
                    <a href="/find-a-doctor/<?= e($slug) ?>" class="hp-spec-tile">
                        <span class="hp-spec-ic"><?= $specIcons[$slug] ?? '🩺' ?></span>
                        <span class="hp-spec-label"><?= e($row['label']) ?></span>
                        <span class="hp-spec-go">→</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="hp-spec-foot reveal">
            …and 30+ more — <a href="/find-a-doctor">browse the full directory →</a>
        </p>
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

            <!-- Dashboard preview — mirrors the real post-login portal dashboard
                 (white sidebar, the same 4 stat tiles, Today's queue panel) so
                 doctors see exactly what they're signing up for. -->
            <div class="hp-doc-preview reveal">
                <div class="hp-dash">
                    <div class="hp-dash-bar">
                        <span class="hp-dash-dot r"></span><span class="hp-dash-dot y"></span><span class="hp-dash-dot g"></span>
                        <span class="hp-dash-url">app.eclinicpro.com/dashboard</span>
                    </div>
                    <div class="hp-dash-body">
                        <div class="hp-dash-side">
                            <div class="hp-dash-clinic">
                                <span class="hp-dash-logo">S</span>
                                <div>
                                    <div class="hp-dash-cname">Sunrise Clinic</div>
                                    <div class="hp-dash-ctag">Clinic admin</div>
                                </div>
                            </div>
                            <?php foreach ([
                                ['🏠','Dashboard',true],['👥','Patients',false],['📅','Appointments',false],
                                ['℞','Prescriptions',false],['🧾','Invoices',false],['📊','Reports',false],['🔔','Follow-ups',false],
                            ] as [$ic,$l,$a]): ?>
                            <div class="hp-dash-nav<?= $a ? ' active' : '' ?>"><span><?= $ic ?></span><?= e($l) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hp-dash-main">
                            <div class="hp-dash-h">
                                <span>Dashboard</span>
                                <span class="hp-dash-date">Mon · 28 May</span>
                            </div>
                            <div class="hp-dash-stats">
                                <?php foreach ([
                                    ['👤','Patients today','24'],
                                    ['📅','Appointments pending','6'],
                                    ['💰','Revenue today','₹14,200'],
                                    ['🔔','Follow-ups due','5'],
                                ] as [$ic,$lbl,$val]): ?>
                                <div class="hp-dash-stat">
                                    <div class="hp-dash-stat-top"><span><?= e($lbl) ?></span><span><?= $ic ?></span></div>
                                    <strong><?= e($val) ?></strong>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="hp-dash-queue">
                                <div class="hp-dash-queue-h">Today's queue <span>Updated 10:02</span></div>
                                <?php foreach ([
                                    ['Aarav Sharma','Follow-up · Hypertension','Now','now'],
                                    ['Priya Iyer','New patient · Consult','Waiting','ok'],
                                    ['Rohan Verma','Lab review','Scheduled','pend'],
                                    ['Ananya Pillai','Annual check-up','Scheduled','pend'],
                                ] as [$nm,$rs,$st,$cls]): ?>
                                <div class="hp-dash-appt">
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
                <span class="eyebrow">Included in your plan</span>
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
                <p class="hp-doc-fine">WhatsApp + SMS messaging is <strong>built into every plan</strong> — no extra add-on, no surprise bill.</p>
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

        <div class="hp-plan-single reveal">
            <span class="hp-plan-name">Standard — everything included</span>
            <div class="hp-plan-price">₹1,499<span class="per">/month</span></div>
            <div class="hp-plan-yearly">or ₹14,999/year — one month free</div>
            <ul class="hp-plan-feats">
                <?php foreach ([
                    'Patient records, visits & prescriptions',
                    'Appointments & walk-in queue',
                    'Billing & invoicing (GST-ready)',
                    'WhatsApp + SMS messaging built in',
                    'Specialty-aware forms (50+ specialties)',
                    'Public doctor profile on eclinicpro.com',
                    'Unlimited patients & staff users',
                    'Reports, follow-ups & analytics',
                ] as $f): ?>
                <li><span class="tick">✓</span><?= e($f) ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg btn-block">Start 30-day free trial</a>
            <p class="hp-doc-fine center">No credit card. Cancel anytime. <a href="/pricing" style="color:var(--teal-600);font-weight:500;">See full pricing &amp; FAQ →</a></p>
        </div>
    </div>
</section>

<!-- ============ FAQ (split: patients + doctors) ============ -->
<?php
$faqPatients = [
    ['Is it free for patients?', 'Yes — searching and booking doctors on eclinicpro.com is completely free. You only pay the doctor\'s consultation fee, which is shown upfront on every profile.'],
    ['How does booking work?', 'Pick a doctor, request a slot, and you instantly get a WhatsApp/SMS with the clinic\'s number. The clinic confirms on their side and you get a final confirmation — no phone-tag.'],
    ['Do I need to create an account to book?', 'No password needed. You just verify your phone with a one-time OTP. After that you can see your bookings and history in your patient panel anytime.'],
    ['Are the doctors verified?', 'Yes. Every listed doctor is verified by our team before they appear in the directory, and claimed profiles are confirmed against the clinic.'],
    ['What if I need to cancel or the slot is wrong?', 'You always get the clinic\'s direct number with your booking, so you can call to reschedule. The doctor can also confirm or suggest a different time.'],
    ['Which cities and specialties are covered?', 'We\'re live across India with 50+ specialties — from general physicians and dentists to homeopaths, dermatologists, physiotherapists and more.'],
];
$faqDoctors = [
    ['What does it cost?', 'One plan: ₹1,499/month (or ₹14,999/year — one month free). Everything to run a clinic is included, with a 30-day free trial and no card required.'],
    ['Is WhatsApp/SMS an extra add-on?', 'No. WhatsApp-first messaging with SMS fallback — confirmations, reminders, prescription delivery and follow-up nudges — is built into every plan at no extra cost.'],
    ['Will patients actually find me?', 'Yes. Your public profile on eclinicpro.com\'s directory is included, so patients searching your city and specialty can discover and book you directly.'],
    ['Is my clinic and patient data secure?', 'Records are encrypted at rest and in transit, with per-clinic isolation and audit logging. You can export everything as PDF or JSON anytime.'],
    ['Can I claim a profile that\'s already listed?', 'Yes. If your clinic is already in the directory, claim it from your profile page — once verified you control your listing, availability and bookings.'],
    ['Does it fit my specialty?', 'The visit screen adapts to your specialty (homeopathy case-taking, dental charting, pediatric growth, derma photos, and more) so you\'re not fighting a generic form.'],
];
?>
<section id="faq" class="hp-faq" x-data="{ open: null }">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Questions</span>
            <h2 class="h-section">Good to know.</h2>
        </div>
        <div class="hp-faq-cols">
            <div class="hp-faq-col reveal">
                <h3 class="hp-faq-h"><span class="hp-faq-ic">🔍</span> For patients</h3>
                <div class="faq-list">
                    <?php foreach ($faqPatients as $i => [$q, $a]): $k = 'p' . $i; ?>
                    <div class="faq-item" :class="open === '<?= $k ?>' ? 'open' : ''">
                        <button type="button" class="faq-q" @click="open = open === '<?= $k ?>' ? null : '<?= $k ?>'">
                            <span><?= e($q) ?></span><span class="plus"></span>
                        </button>
                        <div class="faq-a" x-show="open === '<?= $k ?>'" x-collapse><?= e($a) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="hp-faq-col reveal">
                <h3 class="hp-faq-h"><span class="hp-faq-ic">🩺</span> For doctors</h3>
                <div class="faq-list">
                    <?php foreach ($faqDoctors as $i => [$q, $a]): $k = 'd' . $i; ?>
                    <div class="faq-item" :class="open === '<?= $k ?>' ? 'open' : ''">
                        <button type="button" class="faq-q" @click="open = open === '<?= $k ?>' ? null : '<?= $k ?>'">
                            <span><?= e($q) ?></span><span class="plus"></span>
                        </button>
                        <div class="faq-a" x-show="open === '<?= $k ?>'" x-collapse><?= e($a) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
