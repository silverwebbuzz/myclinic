<?php
// =====================================================================
// index.php — eClinicPro homepage (dual-path: patients + doctors)
//
// Design/copy only. All booking, search, auth, and claim flows live in
// /find-a-doctor, the auth/claim modals, and the portal — this page only
// LINKS into them, never reimplements them.
// =====================================================================

require_once __DIR__ . '/partials/request_router.php';
$__ecpPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (ecp_dispatch_clean_url($_SERVER['REQUEST_URI'] ?? '/')) {
    return;
}
if ($__ecpPath !== '/' && $__ecpPath !== '/index.php') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/seo_slugs.php';

$pageTitle = 'eClinicPro — Book a doctor, or run your clinic';
$metaDesc  = 'Find and book verified doctors across India in 60 seconds — or run your whole practice on one simple, beautiful clinic system. One plan, ₹16,000/year.';
$activePage = '';

// ---- Real numbers from the DB (helpers fall back to safe floors) ----
$clinicCount = ecp_active_clinic_count();
$doctorCount = ecp_directory_doctor_count();

// ---- Specialty showcase pulled from the canonical specialty map ----
// Specialty mega-menu. Single source of truth is the specialty_master table —
// the SAME source the Find-a-doctor page reads — so the two menus stay in sync.
// Groups are keyed by the table's `category`; each item links by its URL slug
// (via ecp_slug_for_db_specialty). Falls back to a curated hardcoded list when
// the DB is unavailable so the homepage never breaks.
$specMap = function_exists('ecp_specialty_map') ? ecp_specialty_map() : [];
$dbIcons  = []; // url-slug => emoji, filled when the DB path is taken
$dbLabels = []; // url-slug => label, filled when the DB path is taken

// Fallback (used only if the DB query below yields nothing): curated columns.
$specGroups = [
    'General & specialists' => ['general-physician', 'cardiologist', 'dermatologist', 'neurologist', 'pulmonologist', 'gastroenterologist', 'endocrinologist', 'nephrologist', 'oncologist', 'urologist'],
    'Surgeons & critical care' => ['general-surgeon', 'neurosurgeon', 'orthopedic', 'plastic-surgeon', 'critical-care'],
    'Dental & child & eye / ENT' => ['dentist', 'orthodontist', 'pediatric-dentist', 'gynecologist', 'pediatrician', 'ophthalmologist', 'ent-specialist'],
    'Alternative & therapy' => ['homeopathy', 'ayurveda', 'physiotherapist', 'psychiatrist', 'psychologist', 'dietitian'],
];

// DB-driven groups (preferred). Build category => [url-slug, ...] from
// specialty_master, mirroring find-doctor-data.php's query + seo_safe rule.
// url_slug is a real column here, so we link by it directly (no reverse map).
if (function_exists('ecp_db') && ($__hpDb = ecp_db())) {
    try {
        $rows = $__hpDb->query(
            "SELECT url_slug, label, icon, category, seo_safe
               FROM specialty_master
              WHERE is_active = 1 AND seo_safe = 1
              ORDER BY sort_order ASC, label ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $dbGroups = [];
        foreach ($rows as $r) {
            $urlSlug = (string) ($r['url_slug'] ?? '');
            if ($urlSlug === '') continue;                     // unlinkable -> skip
            $dbGroups[$r['category']][] = $urlSlug;
            if (!empty($r['icon']))  $dbIcons[$urlSlug]  = $r['icon'];
            if (!empty($r['label'])) $dbLabels[$urlSlug] = $r['label'];
        }
        if ($dbGroups) {
            $specGroups = $dbGroups;                           // category => [url-slug,...]
        }
    } catch (Throwable $e) { /* keep hardcoded fallback */
    }
}

// WebSite + SearchAction JSON-LD (brand search box in Google).
$extraHead = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => 'eClinicPro',
    'url'      => ecp_site_url('/'),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => ecp_site_url('/find-a-doctor?q={search_term_string}'),
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES) . '</script>';

require __DIR__ . '/partials/header.php';
?>

<!-- ============ DUAL-PATH HERO ============ -->
<section class="hp-hero" id="top">
    <div class="hp-hero-bg"></div>
    <div class="nav-inner">
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
                            <div class="hp-path-title">Find a Doctor</div>
                            <div class="hp-path-sub">Find &amp; book a doctor in 60 seconds</div>
                        </div>
                        <span class="hp-path-arrow">→</span>
                    </a>
                    <!-- <a href="<?= e(ecp_portal_url('/register')) ?>" class="hp-path hp-path-doctor">
                            <div class="hp-path-ic">🩺</div>
                            <div class="hp-path-body">
                                <div class="hp-path-title">I'm a doctor</div>
                                <div class="hp-path-sub">Run my clinic — free for 30 days</div>
                            </div>
                            <span class="hp-path-arrow">→</span>
                        </a> -->
                </div>

                <div class="hp-hero-trust">
                    <span class="hp-stars">★★★★★</span>
                    <span><strong>4.8</strong> from patients · Free to search · No phone-tag</span>
                </div>
            </div>

            <!-- Live doctor-card preview (matches the directory result style) -->
            <!-- <div class="hp-hero-preview reveal">
                <div class="hp-preview-tag">⚡ Verified · Real availability</div>
                <div class="hp-preview-card">
                    <div class="hp-preview-search">Search doctors in your city…</div>
                    <?php
                    $previewDocs = [
                        ['AS', 'Dr. Aarav Sharma', 'Cardiology · 18 yrs · Apollo', '4.9', '₹1,200', 'Today 4:45 PM', 'linear-gradient(135deg,#2DC08A,#0B7F5A)'],
                        ['PI', 'Dr. Priya Iyer', 'Dermatology · 12 yrs · Fortis', '4.8', '₹950', 'Tomorrow 11 AM', 'linear-gradient(135deg,#60A5FA,#2563EB)'],
                        ['RV', 'Dr. Rohan Verma', 'Homeopathy · 22 yrs · Clinic', '4.7', '₹600', 'In 2 days', 'linear-gradient(135deg,#C084FC,#7C3AED)'],
                    ];
                    foreach ($previewDocs as [$ini, $name, $meta, $rating, $fee, $slot, $grad]):
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
            </div> -->

            <div class="hp-hero-preview reveal">
                <div class="image-box">
                    <img src="/assets/img/logos/carely-hero-img1.webp" alt="Doctor">
                    <div class="security">
                        <div class="security-item">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z" />
                            </svg>
                            <span>HIPAA Compliant</span>
                        </div>

                        <div class="security-item">
                            <svg viewBox="0 0 24 24">
                                <path d="M19 18H6a4 4 0 010-8 5 5 0 019.7-1.6A4.5 4.5 0 1119 18z" />
                                <path d="M12 13v4m-2-2h4" />
                            </svg>
                            <span>Secure Cloud Storage</span>
                        </div>

                        <div class="security-item">
                            <svg viewBox="0 0 24 24">
                                <path d="M3 12h4l2-5 4 10 2-5h6" />
                            </svg>
                            <span>99.9% Uptime</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ============ SPECIALTIES (patient discovery) ============ -->
<section class="hp-specialties" id="specialties">
    <div class="wrap">

        <!-- ── Header ── -->
        <div class="hp-spec-head reveal">
            <p class="hp-eyebrow">ALL YOU NEED, ALL IN ONE PLACE</p>
            <h2 class="h-section">30+ specialties. <span class="grad">One booking flow.</span></h2>
            <p class="hp-sub">Whatever you need — from a general physician to a neurosurgeon,
                a homeopath to a dietitian — find them in seconds. All verified, all across India.</p>
        </div>

        <!-- ── Filter Tabs ── -->
        <div class="hp-spec-tabs reveal">
            <button class="tab active" data-filter="all">All Specialties</button>
            <button class="tab" data-filter="medical">
                <span class="tab-ic">🩺</span> Medical
            </button>
            <button class="tab" data-filter="surgical">
                <span class="tab-ic">🔪</span> Surgical
            </button>
            <button class="tab" data-filter="dental">
                <span class="tab-ic">🦷</span> Dental
            </button>
            <button class="tab" data-filter="wellness">
                <span class="tab-ic">🌿</span> Wellness
            </button>
            <button class="tab" data-filter="mental">
                <span class="tab-ic">🧩</span> Mental Health
            </button>
        </div>

        <!-- ── Specialty Cards Grid ── -->
        <div class="hp-spec-grid" id="specGrid">

            <!-- ── Medical ── -->
            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">🩺</span>
                <div class="card-body">
                    <h4>General Physician</h4>
                    <p>Your first stop for fever, infections, and everyday health concerns.</p>
                </div>
                <a href="/find-a-doctor/general-physician" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">❤️</span>
                <div class="card-body">
                    <h4>Cardiologist</h4>
                    <p>Expert care for heart conditions, BP, cholesterol and more.</p>
                </div>
                <a href="/find-a-doctor/cardiologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">✨</span>
                <div class="card-body">
                    <h4>Dermatologist</h4>
                    <p>Skin, hair and nail treatments — acne to eczema and beyond.</p>
                </div>
                <a href="/find-a-doctor/dermatologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">🧠</span>
                <div class="card-body">
                    <h4>Neurologist</h4>
                    <p>Brain and nervous system specialist for migraines, epilepsy and more.</p>
                </div>
                <a href="/find-a-doctor/neurologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">🫁</span>
                <div class="card-body">
                    <h4>Pulmonologist</h4>
                    <p>Lung and respiratory specialist for asthma, COPD and infections.</p>
                </div>
                <a href="/find-a-doctor/pulmonologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">🍽️</span>
                <div class="card-body">
                    <h4>Gastroenterologist</h4>
                    <p>Digestive health expert — stomach, liver, intestine care.</p>
                </div>
                <a href="/find-a-doctor/gastroenterologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">⚖️</span>
                <div class="card-body">
                    <h4>Endocrinologist</h4>
                    <p>Hormones, diabetes, thyroid and metabolic disorder specialist.</p>
                </div>
                <a href="/find-a-doctor/endocrinologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">💧</span>
                <div class="card-body">
                    <h4>Nephrologist</h4>
                    <p>Kidney disease management including CKD and dialysis support.</p>
                </div>
                <a href="/find-a-doctor/nephrologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">🎗️</span>
                <div class="card-body">
                    <h4>Oncologist</h4>
                    <p>Cancer diagnosis, treatment planning and ongoing care.</p>
                </div>
                <a href="/find-a-doctor/oncologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="medical">
                <span class="card-ic">🚹</span>
                <div class="card-body">
                    <h4>Urologist</h4>
                    <p>Urinary tract and prostate health for men and women.</p>
                </div>
                <a href="/find-a-doctor/urologist" class="card-arrow">→</a>
            </div>

            <!-- ── Surgical ── -->
            <div class="hp-spec-card" data-group="surgical">
                <span class="card-ic">🔪</span>
                <div class="card-body">
                    <h4>General Surgeon</h4>
                    <p>Appendix, hernia, gallbladder and other common surgeries.</p>
                </div>
                <a href="/find-a-doctor/general-surgeon" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="surgical">
                <span class="card-ic">🧠</span>
                <div class="card-body">
                    <h4>Neurosurgeon</h4>
                    <p>Brain and spine surgical interventions by top specialists.</p>
                </div>
                <a href="/find-a-doctor/neurosurgeon" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="surgical">
                <span class="card-ic">🦴</span>
                <div class="card-body">
                    <h4>Orthopaedic</h4>
                    <p>Bone, joint and muscle surgeries including replacements.</p>
                </div>
                <a href="/find-a-doctor/orthopedic" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="surgical">
                <span class="card-ic">💉</span>
                <div class="card-body">
                    <h4>Plastic Surgeon</h4>
                    <p>Reconstructive and cosmetic surgeries with precision care.</p>
                </div>
                <a href="/find-a-doctor/plastic-surgeon" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="surgical">
                <span class="card-ic">🚑</span>
                <div class="card-body">
                    <h4>Critical Care</h4>
                    <p>Intensive care specialists for life-threatening conditions.</p>
                </div>
                <a href="/find-a-doctor/critical-care" class="card-arrow">→</a>
            </div>

            <!-- ── Dental ── -->
            <div class="hp-spec-card" data-group="dental">
                <span class="card-ic">🦷</span>
                <div class="card-body">
                    <h4>Dentist</h4>
                    <p>Routine cleanings, fillings, extractions and oral hygiene.</p>
                </div>
                <a href="/find-a-doctor/dentist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="dental">
                <span class="card-ic">😁</span>
                <div class="card-body">
                    <h4>Orthodontist</h4>
                    <p>Braces, aligners and teeth-straightening solutions.</p>
                </div>
                <a href="/find-a-doctor/orthodontist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="dental">
                <span class="card-ic">🪥</span>
                <div class="card-body">
                    <h4>Pediatric Dentist</h4>
                    <p>Child-friendly dental care from first tooth to teens.</p>
                </div>
                <a href="/find-a-doctor/pediatric-dentist" class="card-arrow">→</a>
            </div>

            <!-- ── Wellness ── -->
            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">🌸</span>
                <div class="card-body">
                    <h4>Gynaecologist</h4>
                    <p>Women's health, pregnancy, fertility and hormonal care.</p>
                </div>
                <a href="/find-a-doctor/gynecologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">👶</span>
                <div class="card-body">
                    <h4>Paediatrician</h4>
                    <p>Child health and development from newborns to adolescents.</p>
                </div>
                <a href="/find-a-doctor/pediatrician" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">👁️</span>
                <div class="card-body">
                    <h4>Ophthalmologist</h4>
                    <p>Eye exams, glasses, LASIK and retinal care specialists.</p>
                </div>
                <a href="/find-a-doctor/ophthalmologist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">👂</span>
                <div class="card-body">
                    <h4>ENT Specialist</h4>
                    <p>Ear, nose and throat conditions including sinusitis and hearing loss.</p>
                </div>
                <a href="/find-a-doctor/ent-specialist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">🌿</span>
                <div class="card-body">
                    <h4>Homeopathy</h4>
                    <p>Holistic treatment with natural remedies and personalised care.</p>
                </div>
                <a href="/find-a-doctor/homeopathy" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">🪔</span>
                <div class="card-body">
                    <h4>Ayurveda</h4>
                    <p>Ancient Indian healing — herbs, therapies and detox plans.</p>
                </div>
                <a href="/find-a-doctor/ayurveda" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">🤸</span>
                <div class="card-body">
                    <h4>Physiotherapist</h4>
                    <p>Rehabilitation and movement therapy for pain and injuries.</p>
                </div>
                <a href="/find-a-doctor/physiotherapist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="wellness">
                <span class="card-ic">🥗</span>
                <div class="card-body">
                    <h4>Dietitian</h4>
                    <p>Personalised nutrition plans for weight, diabetes and gut health.</p>
                </div>
                <a href="/find-a-doctor/dietitian" class="card-arrow">→</a>
            </div>

            <!-- ── Mental Health ── -->
            <div class="hp-spec-card" data-group="mental">
                <span class="card-ic">🧩</span>
                <div class="card-body">
                    <h4>Psychiatrist</h4>
                    <p>Medical mental health care — anxiety, depression, bipolar and more.</p>
                </div>
                <a href="/find-a-doctor/psychiatrist" class="card-arrow">→</a>
            </div>

            <div class="hp-spec-card" data-group="mental">
                <span class="card-ic">💭</span>
                <div class="card-body">
                    <h4>Psychologist</h4>
                    <p>Talk therapy and counselling for emotional wellbeing.</p>
                </div>
                <a href="/find-a-doctor/psychologist" class="card-arrow">→</a>
            </div>

        </div><!-- /grid -->

        <!-- ── Load More ── -->
        <div class="hp-load-more-wrap" id="loadMoreWrap">
            <p class="hp-load-more-count" id="loadMoreCount"></p>
            <button class="btn-load-more" id="loadMoreBtn">
                <span class="lm-icon">↓</span>
                Load more specialties
            </button>
        </div>

        <!-- ── Footer Banner ── -->
        <div class="hp-spec-banner reveal">
            <div class="banner-left">
                <span class="banner-emoji">🏥</span>
                <div>
                    <h3>Everything works together</h3>
                    <p>All specialties are connected under one seamless booking flow — find, compare
                        and book verified doctors across India in seconds.</p>
                </div>
            </div>
            <div class="banner-cta">
                <a href="/find-a-doctor" class="btn btn-teal">Explore all specialties →</a>
                <a href="/find-a-doctor" class="btn btn-ghost">Browse by city</a>
            </div>
        </div>

    </div><!-- /wrap -->
</section>


<!-- ============ HOW BOOKING WORKS (patient) ============ -->
<!-- <section class="hp-how">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">For patients</span>
            <h2 class="h-section">Book in 60 seconds. No call centre.</h2>
        </div>
        <div class="hp-steps">
            <?php foreach (
                [
                    ['1', 'Search', 'Pick your city and specialty, or just type a name. See verified doctors with real fees.'],
                    ['2', 'Request a slot', 'Tap book. You instantly get a WhatsApp/SMS confirming your request — and the clinic\'s number, just in case.'],
                    ['3', 'Doctor confirms', 'The clinic confirms on their side. You get a final confirmation. Zero phone-tag.'],
                ] as [$n, $t, $d]
            ): ?>
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
</section> -->

<section class="section">
    <div class="wrap">
        <!-- dot grid decoration -->
        <div class="dot-grid" id="dotGrid"></div>

        <!-- ── Header ── -->
        <div class="header">
            <div class="eyebrow">For Patients</div>
            <h1 class="headline">Book in 60 seconds. No call centre.</h1>
            <p class="subline">EclinicPro makes appointment booking simple, fast and hassle-free<br>for every patient.</p>
        </div>

        <!-- ── Body ── -->
        <div class="body">

            <!-- Steps + Benefits -->
            <div class="steps-col">

                <!-- Steps row -->
                <div class="steps-row">

                    <!-- Step 1 -->
                    <div class="step-card" style="margin-top:20px">
                        <div class="step-badge badge-green">01</div>
                        <div class="step-icon icon-green">
                            <!-- Search icon -->
                            <svg viewBox="0 0 24 24" fill="none" stroke="#1cb98f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="7" />
                                <line x1="16.5" y1="16.5" x2="22" y2="22" />
                            </svg>
                        </div>
                        <div class="step-title">Search</div>
                        <div class="step-desc">Search your city, specialty or doctor name and explore verified doctors with real fees.</div>
                    </div>

                    <!-- connector -->
                    <div class="step-connector">
                        <svg viewBox="0 0 80 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 10 Q40 2 78 10" stroke="#b0c8e0" stroke-width="1.5" stroke-dasharray="5 4" fill="none" />
                            <polygon points="74,6 80,10 74,14" fill="#b0c8e0" />
                        </svg>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-card" style="margin-top:20px">
                        <div class="step-badge badge-teal">02</div>
                        <div class="step-icon icon-teal">
                            <!-- Calendar icon -->
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2ab5d4" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="17" rx="3" />
                                <line x1="3" y1="9" x2="21" y2="9" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <circle cx="15.5" cy="15.5" r="3" fill="#e5f7fb" stroke="#2ab5d4" stroke-width="1.5" />
                                <polyline points="14.5,15.5 15.5,16.5 17,14.5" stroke="#2ab5d4" stroke-width="1.5" fill="none" />
                            </svg>
                        </div>
                        <div class="step-title">Request a slot</div>
                        <div class="step-desc">Choose a convenient date and time. You'll instantly get a request confirmation via WhatsApp/SMS.</div>
                    </div>

                    <!-- connector -->
                    <div class="step-connector">
                        <svg viewBox="0 0 80 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 10 Q40 2 78 10" stroke="#b0c8e0" stroke-width="1.5" stroke-dasharray="5 4" fill="none" />
                            <polygon points="74,6 80,10 74,14" fill="#b0c8e0" />
                        </svg>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-card" style="margin-top:20px">
                        <div class="step-badge badge-blue">03</div>
                        <div class="step-icon icon-blue">
                            <!-- Shield check -->
                            <svg viewBox="0 0 24 24" fill="none" stroke="#4a90d9" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2L3 6v6c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V6L12 2z" />
                                <polyline points="9,12 11,14 15,10" stroke="#4a90d9" stroke-width="2.2" fill="none" />
                            </svg>
                        </div>
                        <div class="step-title">Doctor confirms</div>
                        <div class="step-desc">The clinic confirms your appointment and you get a final confirmation. Zero phone-tag.</div>
                    </div>

                </div><!-- /steps-row -->

                <!-- Benefits row -->
                <div class="benefits">

                    <div class="benefit">
                        <div class="benefit-icon bi-green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#1cb98f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.9 9.44 19.79 19.79 0 01.87 4.2 2 2 0 012.86 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L7.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </div>
                        <div class="benefit-title">No Calls</div>
                        <div class="benefit-desc">Avoid busy call centres and long hold times.</div>
                    </div>

                    <div class="benefit">
                        <div class="benefit-icon bi-teal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#2ab5d4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="13,2 3,14 12,14 11,22 21,10 12,10 13,2" />
                            </svg>
                        </div>
                        <div class="benefit-title">Instant &amp; Easy</div>
                        <div class="benefit-desc">Book in under 60 seconds.</div>
                    </div>

                    <div class="benefit">
                        <div class="benefit-icon bi-purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#7c5cbf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                <path d="M7 11V7a5 5 0 0110 0v4" />
                            </svg>
                        </div>
                        <div class="benefit-title">Secure &amp; Private</div>
                        <div class="benefit-desc">Your data is safe with us.</div>
                    </div>

                    <div class="benefit">
                        <div class="benefit-icon bi-peach">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#e07a3a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" />
                                <path d="M9 12l2 2 4-4" />
                            </svg>
                        </div>
                        <div class="benefit-title">Trusted Clinics</div>
                        <div class="benefit-desc">Verified doctors and trusted by thousands.</div>
                    </div>

                </div><!-- /benefits -->

            </div><!-- /steps-col -->

            <!-- ── Phones ── -->
            <div class="phones-col">

                <img src="/assets/img/logos/remove-bg.png" alt="Doctor" style="width: 100%; height: 100%; object-fit: contain;">

            </div>
            <!-- /phones-col -->
        </div><!-- /body -->

        <!-- ── CTA ── -->
        <div class="cta-wrap">
            <a href="/find-a-doctor" class="btn btn-primary btn-lg">
                Find your doctor
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12,5 19,12 12,19" />
                </svg>
            </a>
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
                    <?php foreach (
                        [
                            ['📅', 'Online bookings', 'Patients book from your public profile. WhatsApp confirmations and reminders included.'],
                            ['📋', 'Patient records', 'Encrypted records, history and contact info — always one search away.'],
                            ['℞', 'Prescriptions', 'Signed digital Rx, delivered to the patient on WhatsApp before they leave.'],
                            ['🧾', 'Billing & invoices', 'Clean, GST-ready invoices in seconds. WhatsApp delivery.'],
                            ['🔁', 'Follow-ups', 'Never lose a follow-up. Automatic reminders, overdue tracking, a calm queue.'],
                            ['📊', 'Reports', 'Revenue, top diagnoses, patient retention. Numbers that matter, nothing else.'],
                        ] as [$ic, $t, $d]
                    ): ?>
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
                            <?php foreach (
                                [
                                    ['🏠', 'Dashboard', true],
                                    ['👥', 'Patients', false],
                                    ['📅', 'Appointments', false],
                                    ['℞', 'Prescriptions', false],
                                    ['🧾', 'Invoices', false],
                                    ['📊', 'Reports', false],
                                    ['🔔', 'Follow-ups', false],
                                ] as [$ic, $l, $a]
                            ): ?>
                                <div class="hp-dash-nav<?= $a ? ' active' : '' ?>"><span><?= $ic ?></span><?= e($l) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="hp-dash-main">
                            <div class="hp-dash-h">
                                <span>Dashboard</span>
                                <span class="hp-dash-date">Mon · 28 May</span>
                            </div>
                            <div class="hp-dash-stats">
                                <?php foreach (
                                    [
                                        ['👤', 'Patients today', '24'],
                                        ['📅', 'Appointments pending', '6'],
                                        ['💰', 'Revenue today', '₹14,200'],
                                        ['🔔', 'Follow-ups due', '5'],
                                    ] as [$ic, $lbl, $val]
                                ): ?>
                                    <div class="hp-dash-stat">
                                        <div class="hp-dash-stat-top"><span><?= e($lbl) ?></span><span><?= $ic ?></span></div>
                                        <strong><?= e($val) ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="hp-dash-queue">
                                <div class="hp-dash-queue-h">Today's queue <span>Updated 10:02</span></div>
                                <?php foreach (
                                    [
                                        ['Aarav Sharma', 'Follow-up · Hypertension', 'Now', 'now'],
                                        ['Priya Iyer', 'New patient · Consult', 'Waiting', 'ok'],
                                        ['Rohan Verma', 'Lab review', 'Scheduled', 'pend'],
                                        ['Ananya Pillai', 'Annual check-up', 'Scheduled', 'pend'],
                                    ] as [$nm, $rs, $st, $cls]
                                ): ?>
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
                    <?php foreach (
                        [
                            'Booking confirmations & reminders that cut no-shows',
                            'Signed prescriptions delivered as a PDF',
                            'Follow-up nudges, sent at sensible hours only',
                            'Smart cost controls — daily/monthly caps you set',
                        ] as $f
                    ): ?>
                        <li><span class="tick">✓</span><?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="hp-doc-fine">WhatsApp + SMS messaging is <strong>built into every plan</strong> — no extra add-on, no surprise bill.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============ PRICING TEASER (single plan, real facts) ============ -->
<section>
    <div class="page-wrap">

        <div class="heroo">
            <div class="hero-eyebroww">All-in-one Clinic Management Software</div>
            <h1 class="hero-titlee">One plan. Everything to run<br>your clinic.</h1>
            <p class="hero-subb">No tiers, no per-seat games, no surprise upsells.<br>
                Try free for <strong>30 days</strong> — <strong>no card.</strong></p>
        </div>

        <!-- <div class="pills-row">
            <div class="pill">
                <div class="pill-icon">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <path d="M12 8v8M8 12h8"></path>
                    </svg>
                </div>
                <div>
                    <div class="pill-title">Patient Records</div>
                    <div class="pill-sub">Store &amp; manage everything securely</div>
                </div>
            </div>
            <div class="pill">
                <div class="pill-icon">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <path d="M16 2v4M8 2v4M3 10h18" />
                        <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
                    </svg>
                </div>
                <div>
                    <div class="pill-title">Appointments</div>
                    <div class="pill-sub">Walk-in, bookings &amp; smart scheduling</div>
                </div>
            </div>
            <div class="pill">
                <div class="pill-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21" />
                        <path d="M9 10h.01M12 10h.01M15 10h.01" />
                    </svg>
                </div>
                <div>
                    <div class="pill-title">WhatsApp Alerts</div>
                    <div class="pill-sub">Automated reminders &amp; follow-ups</div>
                </div>
            </div>
            <div class="pill">
                <div class="pill-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <path d="M14 2v6h6M8 13h8M8 17h5" />
                    </svg>
                </div>
                <div>
                    <div class="pill-title">GST Billing</div>
                    <div class="pill-sub">GST-ready invoicing &amp; reports</div>
                </div>
            </div>
        </div> -->

        <div class="main-grid">

            <!-- LEFT: STATS -->
            <div class="side-col">
                <div class="right-card">
                    <div class="right-icon">
                        <svg viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round">

                            <!-- Building -->
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>

                            <!-- Door -->
                            <path d="M9 21v-6h6v6"></path>

                            <!-- Medical Cross -->
                            <path d="M12 7v4"></path>
                            <path d="M10 9h4"></path>

                        </svg>
                    </div>
                    <div>
                        <div class="right-title">500+ Clinics Using</div>
                        <div class="right-desc">Trusted by clinics across India</div>
                    </div>
                </div>

                <div class="right-card">
                    <div class="right-icon">
                        <!-- <svg viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <polyline points="9,12 11,14 15,10" />
                        </svg> -->

                        <!-- <div class="stat-icon"> -->
                        <svg viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <!-- </div> -->
                    </div>
                    <div>
                        <div class="right-title">50,000+ Patients Managed</div>
                        <div class="right-desc">Manage patient data with complete ease</div>
                    </div>
                </div>

                <div class="right-card">
                    <div class="right-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <polyline points="9,12 11,14 15,10" />
                        </svg>
                    </div>
                    <div>
                        <div class="right-title">99.9% Uptime</div>
                        <div class="right-desc">Reliable, secure &amp; always available </div>
                    </div>
                </div>

                <div class="right-card">
                    <div class="right-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <path d="M14 2v6h6M8 13h8M8 17h5"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="right-title">GST Billing</div>
                        <div class="right-desc">GST-ready invoicing & reports</div>
                    </div>
                </div>

            </div>

            <!-- RIGHT: FEATURE CARDS -->
            <div class="side-col right-col">

                <div class="right-card">
                    <div class="pill-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <path d="M12 8v8M8 12h8"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="right-title">Patient Records</div>
                        <div class="right-desc">Store & manage everything securely</div>
                    </div>
                </div>

                <div class="right-card">
                    <div class="right-icon">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                            <path d="M16 2v4M8 2v4M3 10h18"></path>
                            <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="right-title">Appointments</div>
                        <div class="right-desc">Walk-in, bookings & smart scheduling</div>
                    </div>
                </div>

                <div class="right-card">
                    <div class="right-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 21l1.65-3.8a9 9 0 1 1 3.4 2.9L3 21" />
                            <path d="M9 10h.01M12 10h.01M15 10h.01" />
                        </svg>
                    </div>
                    <div>
                        <div class="right-title">WhatsApp Reminders</div>
                        <div class="right-desc">Automated alerts reduce no-shows</div>
                    </div>
                </div>

                <div class="right-card">
                    <div class="right-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <polyline points="9,12 11,14 15,10" />
                        </svg>
                    </div>
                    <div>
                        <div class="right-title">100% GST Ready</div>
                        <div class="right-desc">Compliant billing, invoices &amp; reports</div>
                    </div>
                </div>



            </div>

            <!-- CENTER: PRICING CARD -->
            <div class="pricing-card">
                <div class="pricing-top">
                    <div class="pricing-top-label">Standard — Everything Included</div>
                </div>
                <div class="pricing-body">
                    <div class="price-main">
                        <span class="price-sym">₹</span><span class="price-num">16,000</span><span class="price-yr">/year</span>
                    </div>
                    <div class="price-meta">
                        <span class="price-original">₹17,988</span>
                        <span class="save-tag">SAVE 10%</span>
                        <span class="gst-txt">+ 18% GST at checkout</span>
                    </div>

                    <div class="feat-grid">
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Patient records, visits &amp; prescriptions</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Appointments &amp; walk-in queue</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Billing &amp; invoicing (GST-ready)</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">WhatsApp + SMS messaging built in</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Specialty-aware forms (50+ specialties)</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Public doctor profile on eclinicpro.com</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Unlimited patients &amp; staff users</span>
                        </div>
                        <div class="feat-item">
                            <div class="feat-check"><svg viewBox="0 0 12 12">
                                    <polyline points="2,6 5,9 10,3" />
                                </svg></div>
                            <span class="feat-txt">Reports, follow-ups &amp; analytics</span>
                        </div>
                    </div>

                    <a href="<?= e(ecp_portal_url('/register')) ?>" class="cta-btn">Start 30-day free trial</a>
                    <div class="trust-row">
                        <span class="trust-item"><svg viewBox="0 0 14 14">
                                <polyline points="2,7 5,10 12,3" />
                            </svg> No credit card</span>
                        <span class="trust-item"><svg viewBox="0 0 14 14">
                                <polyline points="2,7 5,10 12,3" />
                            </svg> Cancel anytime</span>
                        <span class="trust-item"><svg viewBox="0 0 14 14">
                                <polyline points="2,7 5,10 12,3" />
                            </svg> Setup in minutes</span>
                    </div>
                    <a href="#" class="see-link">See full pricing &amp; FAQ →</a>
                </div>
            </div>



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
    ['What does it cost?', 'One annual plan: ₹16,000/year (10% off ₹17,988; GST added at checkout). Everything to run a clinic is included, with a 30-day free trial and no card required.'],
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

<script>
    /* ─────────────────────────────────────────
           CONFIG
        ───────────────────────────────────────── */
    const PAGE_SIZE = 15; // cards per page

    /* ─────────────────────────────────────────
       ELEMENTS
    ───────────────────────────────────────── */
    const tabs = document.querySelectorAll('.tab');
    const allCards = Array.from(document.querySelectorAll('.hp-spec-card'));
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const loadMoreCount = document.getElementById('loadMoreCount');

    /* ─────────────────────────────────────────
       SCROLL-REVEAL (IntersectionObserver)
    ───────────────────────────────────────── */
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.08
        }
    );

    // Observe static reveal elements (header, tabs, banner)
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    /* ─────────────────────────────────────────
       STATE
    ───────────────────────────────────────── */
    let currentFilter = 'all';
    let visibleCount = 0; // how many cards are currently shown

    /* ─────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────── */

    // Returns cards matching the current filter
    function getFiltered() {
        return allCards.filter(card =>
            currentFilter === 'all' || card.dataset.group === currentFilter
        );
    }

    // Show/hide a card with optional stagger delay
    function showCard(card, delayMs) {
        card.style.display = '';
        card.style.transitionDelay = `${delayMs}ms`;
        card.classList.add('reveal');
        // double-rAF to ensure transition fires
        requestAnimationFrame(() => requestAnimationFrame(() => {
            card.classList.add('visible');
        }));
        observer.observe(card);
    }

    function hideCard(card) {
        card.classList.remove('reveal', 'visible');
        card.style.display = 'none';
        card.style.transitionDelay = '';
    }

    // Update counter text and button state
    function updateUI(filtered) {
        const total = filtered.length;
        const showing = Math.min(visibleCount, total);

        loadMoreCount.innerHTML =
            `Showing <span>${showing}</span> of <span>${total}</span> specialties`;

        if (total <= PAGE_SIZE) {
            // If 15 or fewer records → hide button
            loadMoreBtn.style.display = 'none';
        } else {
            loadMoreBtn.style.display = 'block';
        }

        if (showing >= total) {
            loadMoreBtn.classList.add('all-loaded');
            loadMoreBtn.innerHTML = `<span class="lm-icon">✓</span> All specialties shown`;
        } else {
            loadMoreBtn.classList.remove('all-loaded');
            loadMoreBtn.innerHTML = `<span class="lm-icon">↓</span> Load more specialties`;
        }
    }

    /* ─────────────────────────────────────────
       RENDER — apply filter + pagination
    ───────────────────────────────────────── */
    function render(resetCount) {
        if (resetCount) visibleCount = PAGE_SIZE;

        const filtered = getFiltered();
        const toShow = Math.min(visibleCount, filtered.length);
        const filteredSet = new Set(filtered);

        // Hide ALL cards first
        allCards.forEach(card => hideCard(card));

        // Show first `toShow` filtered cards with stagger
        filtered.slice(0, toShow).forEach((card, i) => {
            showCard(card, i * 40);
        });

        updateUI(filtered);
    }

    /* ─────────────────────────────────────────
       FILTER TABS
    ───────────────────────────────────────── */
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentFilter = tab.dataset.filter;
            render(true); // reset to first page
        });
    });

    /* ─────────────────────────────────────────
       LOAD MORE BUTTON
    ───────────────────────────────────────── */
    loadMoreBtn.addEventListener('click', () => {
        visibleCount += PAGE_SIZE;
        render(false); // keep adding, don't reset
    });

    /* ─────────────────────────────────────────
       INIT — first render
    ───────────────────────────────────────── */
    render(true);
</script>

<script>
    // Generate dot grid
    const grid = document.getElementById('dotGrid');
    for (let i = 0; i < 48; i++) {
        const s = document.createElement('span');
        grid.appendChild(s);
    }
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>