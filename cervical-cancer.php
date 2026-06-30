<?php
// =====================================================================
// cervical-cancer.php — STATIC awareness campaign page.
//
//   URL: /cervical-cancer  (clean URL handled by .htaccess)
//
// Public health awareness page for Cervical Cancer. Content is grounded in
// two source documents (document/seeds/): the official PIB note on India's
// 28-Feb-2026 HPV Vaccination Campaign, and a patient-facing Gujarati
// awareness guide. Sections: figures & facts (PIB/GLOBOCAN), prevention,
// the screening TESTS (Pap smear / LBC / HPV DNA — with Indian costs and an
// age-wise schedule), the 2026 Government campaign, and an FAQ accordion.
// It closes with a city-aware list of gynecologists pulled from
// directory_doctors and a "search more" CTA into /find-a-doctor.
//
// SEO: header.php supplies canonical + OG/Twitter + Organization JSON-LD.
// This page adds MedicalWebPage + FAQPage JSON-LD (rich results) via
// $extraHead, plus page-scoped CSS (the .cc-* block) — no global styles.
//
// Teal palette is intentional: it's the cervical-cancer ribbon colour AND
// the eClinicPro brand (--teal-600), so it reuses the design system.
//
// NOTE: do NOT run the clean-URL router here — this page IS a dispatch
// target (/cervical-cancer -> this file).
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/cervical_doctors.php';

// ---- City-aware gynecologist block -----------------------------------
$ccCity    = ecp_cervical_pick_city();                       // e.g. "Ahmedabad" or null
$ccDoctors = $ccCity ? ecp_cervical_gynecologists($ccCity, 8) : [];
$ccCitySlug = $ccCity ? ecp_slug_for_city($ccCity) : '';
$ccSearchMoreUrl = $ccCitySlug
    ? '/find-a-doctor/gynecologist-in-' . $ccCitySlug
    : '/find-a-doctor';

// ---- FAQ content (single source — rendered AND fed to JSON-LD) --------
$ccFaqs = [
    [
        'q' => 'What is cervical cancer and what causes it?',
        'a' => 'Cervical cancer forms in the cervix — the lower part of the uterus that connects to the vagina. Its main cause is persistent infection with the Human Papillomavirus (HPV), the most common sexually transmitted infection. HPV is extremely common — by age 50 most women will have had it at some point — and in most people the body’s immunity clears it on its own, usually within about two years. Only when a high-risk infection stays for many years can it slowly turn into cancer. High-risk types 16 and 18 alone account for more than 80% of cervical cancer cases in India.',
    ],
    [
        'q' => 'How common is cervical cancer in India?',
        'a' => 'It is the second most common cancer among Indian women, with over 1,20,000 new cases and nearly 80,000 deaths every year (GLOBOCAN 2022). India accounts for about 25% of the world’s cervical cancer deaths — one in every five women globally who suffers from cervical cancer is from India. Yet it is the only cancer that can be prevented by a vaccine if given in time.',
    ],
    [
        'q' => 'What are the early warning signs?',
        'a' => 'In the early stage there are usually no symptoms — which is why screening matters. As it progresses you may notice: bleeding between periods, bleeding after intercourse, bleeding after menopause, foul-smelling or unusual vaginal discharge, persistent pelvic (lower abdomen) pain, pain during intercourse, or back/leg pain. These signs are not proof of cancer, but if you notice any of them, see a gynecologist immediately.',
    ],
    [
        'q' => 'Who is at higher risk?',
        'a' => 'Risk is higher with: HPV infection, smoking, long-standing HPV infection, weak immunity, never getting a Pap smear or HPV test, unprotected sex, and starting sexual activity at a very young age.',
    ],
    [
        'q' => 'What screening tests are available, and do they hurt?',
        'a' => 'The main tests are the Pap smear (finds early abnormal cell changes in the cervix), Liquid Based Cytology (LBC), and the HPV DNA test (detects the virus itself). A Pap smear is done by a gynecologist in about 5–10 minutes using a small brush to collect a few cells from the cervix, which are then checked in a lab. It does not hurt — you may feel only mild discomfort for a few seconds. These tests can spot abnormal cells many years before cancer develops.',
    ],
    [
        'q' => 'How much do the tests cost in India?',
        'a' => 'Costs are indicative only and vary by city and lab — please confirm with your provider. As a rough guide: a Pap smear starts from around ₹500, Liquid Based Cytology (LBC) from around ₹1,200, and a combined Pap + HPV DNA test from around ₹2,000. They are available at private hospitals, gynecologist clinics, reputed laboratories, and government / civil hospitals.',
    ],
    [
        'q' => 'What is India’s HPV Vaccination Campaign launched in 2026?',
        'a' => 'On 28 February 2026, the Prime Minister launched a nationwide HPV Vaccination Programme at Ajmer, Rajasthan. It offers the Gardasil-4 vaccine free of cost at government facilities to about 1.15 crore girls aged 14 across all States and UTs (girls turning 15 within 90 days of launch are also eligible). A single dose of Gardasil-4 is 93–100% effective against the HPV types that cause cervical cancer. With this, India joins over 160 countries with HPV vaccination in their national immunisation schedules.',
    ],
    [
        'q' => 'Who should get the HPV vaccine and how many doses?',
        'a' => 'The vaccine works best given before any sexual activity begins. By age: girls 9–14 years need 2 doses (the second after 6 months); ages 15–45 need 3 doses (at 0, 1–2, and 6 months). It is given as an injection in the arm. Even after vaccination, regular Pap smears are still important.',
    ],
    [
        'q' => 'Which HPV vaccines are available in India and at what cost?',
        'a' => 'Three vaccines are commonly available in the private market. Prices are approximate and vary by clinic: Cervavac (made by India’s Serum Institute, protects against 4 HPV types) from around ₹1,500 per dose; Gardasil-4 (4 types) from around ₹3,500; and Gardasil-9 (9 types, broadest protection) from around ₹9,000. The 2026 government campaign provides Gardasil-4 free for 14-year-old girls at government facilities.',
    ],
    [
        'q' => 'Is the HPV vaccine safe, and who should not take it?',
        'a' => 'HPV vaccines are among the most extensively studied vaccines globally, with over 500 million doses given worldwide since 2006. Under the campaign, girls are observed for 30 minutes after vaccination. It should not be given during moderate/severe illness (wait until recovery), to anyone with a known yeast allergy or prior allergic reaction to a vaccine, or during pregnancy. If pregnancy occurs after the first dose, the remaining doses can be taken after delivery.',
    ],
];

// =====================================================================
// SEO — page meta + structured data (MedicalWebPage + FAQPage)
// =====================================================================
$pageTitle = 'Cervical Cancer Awareness in India — Facts, HPV Vaccine & Screening | eClinicPro';
$metaDesc  = 'Cervical cancer is preventable. Understand the figures & facts, India’s 2026 HPV vaccination campaign, the screening tests (Pap smear, LBC, HPV DNA) with costs, an age-wise prevention schedule, FAQs, and find a gynecologist near you.';
$activePage = '';
$ogType = 'article';
$hideFinalCta = true; // we provide our own campaign CTA

$canonicalForLd = ecp_site_url('/cervical-cancer');

// FAQPage JSON-LD built from the same $ccFaqs array.
$faqLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => array_map(static function (array $f): array {
        return [
            '@type' => 'Question',
            'name'  => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ];
    }, $ccFaqs),
];

$webPageLd = [
    '@context' => 'https://schema.org',
    '@type'    => 'MedicalWebPage',
    'name'     => 'Cervical Cancer Awareness',
    'url'      => $canonicalForLd,
    'description' => $metaDesc,
    'about'    => [
        '@type' => 'MedicalCondition',
        'name'  => 'Cervical Cancer',
        'cause' => ['@type' => 'MedicalCause', 'name' => 'Human Papillomavirus (HPV) infection'],
        'possibleTreatment' => ['@type' => 'MedicalTherapy', 'name' => 'HPV Vaccination'],
        'typicalTest' => [
            ['@type' => 'MedicalTest', 'name' => 'Pap smear'],
            ['@type' => 'MedicalTest', 'name' => 'Liquid Based Cytology (LBC)'],
            ['@type' => 'MedicalTest', 'name' => 'HPV DNA test'],
        ],
    ],
    'audience' => ['@type' => 'PeopleAudience', 'audienceType' => 'Women'],
    'lastReviewed' => date('Y-m-d'),
];

$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

ob_start(); ?>
<script type="application/ld+json"><?= json_encode($webPageLd, $jsonFlags) ?></script>
<script type="application/ld+json"><?= json_encode($faqLd, $jsonFlags) ?></script>
<style>
/* ===== Cervical Cancer awareness page (page-scoped .cc-*) ============ */
.cc-hero{
  position:relative; overflow:hidden;
  background:
    radial-gradient(900px 480px at 80% -10%, rgba(15,155,110,.16), transparent 60%),
    linear-gradient(180deg, var(--teal-950) 0%, #0a5d44 55%, var(--teal-700) 100%);
  color:#fff; padding:84px 0 96px;
}
.cc-hero .wrap{position:relative; z-index:1; max-width:920px;}
.cc-ribbon{
  display:inline-flex; align-items:center; gap:8px;
  background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.22);
  color:#eafff7; font-size:13px; font-weight:600; letter-spacing:.02em;
  padding:7px 14px; border-radius:999px; margin-bottom:20px;
}
.cc-ribbon svg{width:15px;height:15px}
.cc-hero h1{font-size:clamp(30px,5vw,52px); line-height:1.08; font-weight:600; margin-bottom:18px;}
.cc-hero p.lede{font-size:clamp(16px,2.2vw,20px); line-height:1.6; color:rgba(255,255,255,.86); max-width:680px; margin-bottom:30px;}
.cc-hero-ctas{display:flex; flex-wrap:wrap; gap:12px;}
.cc-btn{display:inline-flex;align-items:center;gap:8px;font-weight:600;font-size:15px;border-radius:12px;padding:13px 22px;text-decoration:none;transition:transform .15s ease, box-shadow .15s ease;border:1px solid transparent;}
.cc-btn:hover{transform:translateY(-1px)}
.cc-btn-primary{background:#fff;color:var(--teal-800);box-shadow:0 8px 24px rgba(0,0,0,.18)}
.cc-btn-ghost{background:transparent;color:#fff;border-color:rgba(255,255,255,.4)}
.cc-btn-ghost:hover{background:rgba(255,255,255,.1)}

.cc-section{padding:64px 0;}
.cc-section .wrap{max-width:1080px;}
.cc-section-alt{background:var(--bg-2);}
.cc-eyebrow{display:block;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--teal-600);margin-bottom:10px;}
.cc-h2{font-size:clamp(24px,3.4vw,34px);line-height:1.15;font-weight:600;color:var(--ink);margin-bottom:14px;}
.cc-sub{font-size:17px;line-height:1.6;color:var(--mute);max-width:680px;margin-bottom:34px;}

/* Infographic stat tiles */
.cc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;}
.cc-stat{
  background:var(--bg);border:1px solid var(--line);border-radius:18px;padding:26px 22px;
  box-shadow:0 1px 2px rgba(0,0,0,.03);position:relative;overflow:hidden;
}
.cc-stat::before{content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,var(--teal-400),var(--teal-600));}
.cc-stat-num{font-size:clamp(30px,4vw,42px);font-weight:700;color:var(--teal-700);line-height:1;letter-spacing:-.02em;}
.cc-stat-num span{font-size:.55em;font-weight:600;color:var(--teal-600);}
.cc-stat-label{margin-top:10px;font-size:14.5px;line-height:1.5;color:var(--ink-2);}
.cc-stat-src{margin-top:8px;font-size:12px;color:var(--mute);}

/* Prevention / pillars */
.cc-pillars{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;}
.cc-pillar{background:var(--bg);border:1px solid var(--line);border-radius:18px;padding:26px;}
.cc-pillar .ico{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;background:var(--teal-50);color:var(--teal-700);margin-bottom:14px;}
.cc-pillar .ico svg{width:24px;height:24px}
.cc-pillar h3{font-size:18px;font-weight:600;color:var(--ink);margin-bottom:8px;}
.cc-pillar p{font-size:14.5px;line-height:1.6;color:var(--mute);}

/* Data tables (test costs + age schedule) */
.cc-table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:14px;background:var(--bg);}
.cc-table{width:100%;border-collapse:collapse;font-size:14.5px;min-width:420px;}
.cc-table th{background:var(--teal-50);color:var(--teal-800);text-align:left;font-weight:600;padding:13px 18px;font-size:13px;letter-spacing:.02em;}
.cc-table td{padding:13px 18px;border-top:1px solid var(--line);color:var(--ink-2);line-height:1.5;}
.cc-table tbody tr:nth-child(even){background:var(--bg-3);}
.cc-table td:last-child,.cc-table th:last-child{white-space:nowrap;}
.cc-note{font-size:13px;color:var(--mute);line-height:1.6;margin-top:12px;}

/* Government callout */
.cc-gov{background:linear-gradient(180deg,#fff, var(--teal-50));border:1px solid var(--teal-100);border-radius:22px;padding:34px;}
.cc-gov h3{font-size:21px;font-weight:600;color:var(--teal-800);margin-bottom:12px;display:flex;align-items:center;gap:10px;}
.cc-gov ul{list-style:none;display:grid;gap:14px;}
.cc-gov li{display:flex;gap:12px;font-size:15px;line-height:1.6;color:var(--ink-2);}
.cc-gov li svg{flex:0 0 auto;width:20px;height:20px;color:var(--teal-600);margin-top:2px;}
.cc-goal{margin-top:20px;background:var(--teal-600);color:#fff;border-radius:16px;padding:22px 26px;font-size:15px;line-height:1.65;}
.cc-goal strong{font-weight:600;}

/* FAQ accordion */
.cc-faq{max-width:820px;}
.cc-faq details{border:1px solid var(--line);border-radius:14px;background:var(--bg);margin-bottom:12px;overflow:hidden;}
.cc-faq summary{list-style:none;cursor:pointer;padding:18px 22px;font-size:16px;font-weight:600;color:var(--ink);display:flex;justify-content:space-between;align-items:center;gap:16px;}
.cc-faq summary::-webkit-details-marker{display:none}
.cc-faq summary .chev{flex:0 0 auto;transition:transform .2s ease;color:var(--teal-600)}
.cc-faq details[open] summary .chev{transform:rotate(180deg)}
.cc-faq .ans{padding:0 22px 20px;font-size:15px;line-height:1.7;color:var(--mute);}

/* Doctor cards */
.cc-docs{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}
.cc-doc{background:var(--bg);border:1px solid var(--line);border-radius:16px;padding:20px;display:flex;flex-direction:column;}
.cc-doc-top{display:flex;gap:14px;align-items:center;margin-bottom:14px;}
.cc-doc-av{flex:0 0 auto;width:50px;height:50px;border-radius:50%;display:grid;place-items:center;background:var(--teal-600);color:#fff;font-weight:600;font-size:16px;}
.cc-doc-name{font-size:16px;font-weight:600;color:var(--ink);line-height:1.25;}
.cc-doc-meta{font-size:13px;color:var(--mute);margin-top:3px;}
.cc-doc-rating{font-size:13px;color:var(--ink-2);margin-bottom:14px;display:flex;align-items:center;gap:6px;}
.cc-doc-rating .star{color:var(--amber)}
.cc-doc-actions{margin-top:auto;display:flex;gap:8px;flex-wrap:wrap;}
.cc-doc-actions a{flex:1 1 auto;text-align:center;font-size:13.5px;font-weight:600;padding:9px 12px;border-radius:10px;text-decoration:none;border:1px solid var(--line);color:var(--ink-2);transition:background .15s,border-color .15s;}
.cc-doc-actions a:hover{background:var(--bg-2)}
.cc-doc-actions a.is-primary{background:var(--teal-600);color:#fff;border-color:var(--teal-600);}
.cc-doc-actions a.is-primary:hover{background:var(--teal-700)}
.cc-docs-foot{margin-top:28px;text-align:center;}

/* Disclaimer */
.cc-disc{font-size:13px;color:var(--mute);line-height:1.6;max-width:820px;margin:0 auto;text-align:center;padding-top:8px;}

/* References & sources (sits just above the site footer) */
.cc-refs{background:var(--bg-2);border-top:1px solid var(--line);padding:40px 0;}
.cc-refs h2{font-size:18px;font-weight:600;color:var(--ink);margin-bottom:8px;}
.cc-refs > .wrap > p{font-size:14px;color:var(--mute);margin-bottom:16px;}
.cc-refs ul{list-style:none;display:grid;gap:12px;max-width:880px;}
.cc-refs li{font-size:14px;line-height:1.6;color:var(--ink-2);padding-left:18px;position:relative;}
.cc-refs li::before{content:"›";position:absolute;left:0;color:var(--teal-600);font-weight:700;}
.cc-refs em{color:var(--mute);font-style:italic;}
.cc-refs a{color:var(--teal-700);text-decoration:none;font-weight:500;word-break:break-word;}
.cc-refs a:hover{text-decoration:underline;}

@media (max-width:640px){
  .cc-hero{padding:60px 0 64px;}
  .cc-section{padding:48px 0;}
  .cc-gov{padding:24px;}
}
</style>
<?php
$extraHead = ob_get_clean();

require __DIR__ . '/partials/header.php';
?>

<!-- ============================ HERO ============================ -->
<section class="cc-hero">
  <div class="wrap">
    <span class="cc-ribbon">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2c-2 3-2 6 0 9 2-3 2-6 0-9zm0 9c-1.5 4-4.5 7-8 9 4.5.5 7.5-1.5 8-4 .5 2.5 3.5 4.5 8 4-3.5-2-6.5-5-8-9z"/></svg>
      Cervical Cancer Awareness · Vaccination Campaign Launched
    </span>
    <h1>Cervical cancer is one of the most preventable cancers — let’s end it together.</h1>
    <p class="lede">Almost all cervical cancers are caused by HPV, and almost all can be prevented through vaccination and regular screening. Know the facts, understand India’s national programme, and take the next step for the women you love.</p>
    <div class="cc-hero-ctas">
      <a class="cc-btn cc-btn-primary" href="#find-doctor">
        Find a gynecologist near you
      </a>
      <a class="cc-btn cc-btn-ghost" href="#tests">Tests &amp; screening</a>
    </div>
  </div>
</section>

<!-- ====================== FIGURES & FACTS ====================== -->
<section class="cc-section" id="facts">
  <div class="wrap">
    <span class="cc-eyebrow">Figures &amp; Facts</span>
    <h2 class="cc-h2">The numbers India can’t ignore</h2>
    <p class="cc-sub">Worldwide, cervical cancer caused around 660,000 new cases and 350,000 deaths in 2022 (WHO). In India it is the second most common cancer among women — yet it is one of the most preventable. These figures are drawn from WHO, GLOBOCAN 2022 and Government of India (PIB) public health sources.</p>
    <div class="cc-stats">
      <div class="cc-stat">
        <div class="cc-stat-num">2<span>nd</span></div>
        <div class="cc-stat-label">most common cancer among women in India.</div>
        <div class="cc-stat-src">Source: GLOBOCAN 2022 / PIB</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-num">1.2<span>lakh+</span></div>
        <div class="cc-stat-label">new cases and nearly 80,000 deaths in India every year.</div>
        <div class="cc-stat-src">Source: GLOBOCAN 2022</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-num">25<span>%</span></div>
        <div class="cc-stat-label">of the world’s cervical cancer deaths occur in India — 1 in 5 patients globally is Indian.</div>
        <div class="cc-stat-src">Source: WHO / PIB</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-num">80<span>%+</span></div>
        <div class="cc-stat-label">of India’s cases are caused by high-risk HPV types 16 &amp; 18.</div>
        <div class="cc-stat-src">Source: PIB</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-num">93–100<span>%</span></div>
        <div class="cc-stat-label">effectiveness of a Gardasil-4 dose against the HPV types it covers.</div>
        <div class="cc-stat-src">Source: PIB, 2026</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-num">1.15<span>crore</span></div>
        <div class="cc-stat-label">girls aged 14 to be vaccinated free under India’s 2026 campaign.</div>
        <div class="cc-stat-src">Source: PIB, Feb 2026</div>
      </div>
    </div>
  </div>
</section>

<!-- ====================== HOW TO PREVENT ====================== -->
<section class="cc-section cc-section-alt" id="prevention">
  <div class="wrap">
    <span class="cc-eyebrow">Three ways to protect</span>
    <h2 class="cc-h2">Prevention works — and it’s within reach</h2>
    <p class="cc-sub">Cervical cancer can be almost completely prevented when caught in time. It rests on three simple steps — each one saves lives.</p>
    <div class="cc-pillars">
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 11.5 8.5 21a2.12 2.12 0 0 1-3-3L15 8.5"/><path d="m14 7 3 3"/><path d="M19 3 14 8l2 2 5-5z"/></svg></div>
        <h3>1. Vaccinate</h3>
        <p>The HPV vaccine protects against the virus types that cause most cervical cancers. It is most effective when given before sexual activity begins — girls aged 9–14 need just 2 doses.</p>
      </div>
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <h3>2. Screen</h3>
        <p>A Pap smear (every 3 years) or HPV DNA test (every 5 years) from age 30 detects abnormal cells years before cancer forms — even after vaccination.</p>
      </div>
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
        <h3>3. Treat early</h3>
        <p>When changes are found early, treatment is simple and highly effective. Don’t wait for symptoms — by then, the disease may be advanced.</p>
      </div>
    </div>
  </div>
</section>

<!-- ================= TESTS & SCREENING ================= -->
<section class="cc-section" id="tests">
  <div class="wrap">
    <span class="cc-eyebrow">Tests &amp; screening</span>
    <h2 class="cc-h2">The tests that catch it early</h2>
    <p class="cc-sub">Screening can find abnormal cells <em>many years</em> before cancer develops. These are the tests used in India — simple, quick, and far cheaper than treating advanced disease.</p>

    <div class="cc-pillars">
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-4"/><rect x="9" y="2" width="6" height="9" rx="1"/></svg></div>
        <h3>Pap smear</h3>
        <p>A gynecologist gently collects a few cells from the cervix with a small brush (5–10 minutes). It’s painless — only mild discomfort for a few seconds — and finds early abnormal changes. <strong>From age 30, every 3 years.</strong></p>
      </div>
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2v7.31"/><path d="M14 9.3V2"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/><path d="M5.58 16.5h12.85"/></svg></div>
        <h3>Liquid Based Cytology (LBC)</h3>
        <p>An advanced version of the Pap smear where cells are preserved in a liquid for clearer lab analysis — often giving more reliable results.</p>
      </div>
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3v3"/><path d="M15 3v3"/><path d="M9 18v3"/><path d="M15 18v3"/><path d="M9 6c0 3 6 3 6 6s-6 3-6 6"/><path d="M15 6c0 3-6 3-6 6s6 3 6 6"/></svg></div>
        <h3>HPV DNA test</h3>
        <p>Detects the high-risk HPV virus itself, before it has caused cell changes. <strong>From age 30, every 5 years</strong> is an option instead of a 3-yearly Pap smear.</p>
      </div>
    </div>

    <h3 style="font-size:18px;font-weight:600;color:var(--ink);margin:34px 0 14px;">Approximate cost in India</h3>
    <div class="cc-table-wrap">
      <table class="cc-table">
        <thead><tr><th>Test</th><th>Approx. cost</th></tr></thead>
        <tbody>
          <tr><td>Pap Smear</td><td>Starts from ~₹500</td></tr>
          <tr><td>Liquid Based Cytology (LBC)</td><td>Starts from ~₹1,200</td></tr>
          <tr><td>Pap + HPV DNA Test</td><td>Starts from ~₹2,000</td></tr>
        </tbody>
      </table>
    </div>
    <p class="cc-note">Available at private hospitals, gynecologist clinics, reputed laboratories, and government / civil hospitals. Prices are indicative only and vary by city, lab and package — please confirm with your provider.</p>

    <h3 style="font-size:18px;font-weight:600;color:var(--ink);margin:36px 0 14px;">HPV vaccines available in India</h3>
    <div class="cc-table-wrap">
      <table class="cc-table">
        <thead><tr><th>Vaccine</th><th>Protects against</th><th>Approx. cost / dose</th><th>Note</th></tr></thead>
        <tbody>
          <tr><td>Cervavac</td><td>4 HPV types</td><td>Starts from ~₹1,500</td><td>Made in India by Serum Institute</td></tr>
          <tr><td>Gardasil-4</td><td>4 HPV types</td><td>Starts from ~₹3,500</td><td>Used free in the 2026 govt. campaign</td></tr>
          <tr><td>Gardasil-9</td><td>9 HPV types</td><td>Starts from ~₹9,000</td><td>Broadest protection</td></tr>
        </tbody>
      </table>
    </div>
    <p class="cc-note">Prices are indicative only and vary by clinic. Under India’s 2026 government campaign, Gardasil-4 is given free to 14-year-old girls at government health facilities.</p>

    <h3 style="font-size:18px;font-weight:600;color:var(--ink);margin:36px 0 14px;">Age-wise prevention schedule</h3>
    <div class="cc-table-wrap">
      <table class="cc-table">
        <thead><tr><th>Age</th><th>What to do</th><th>Doses / frequency</th></tr></thead>
        <tbody>
          <tr><td>9–14 years</td><td>HPV vaccine (best protection)</td><td>2 doses</td></tr>
          <tr><td>15–26 years</td><td>Catch-up HPV vaccine</td><td>3 doses</td></tr>
          <tr><td>27–45 years</td><td>HPV vaccine on doctor’s advice</td><td>3 doses</td></tr>
          <tr><td>30–65 years</td><td>Pap smear every 3 years <em>or</em> HPV DNA test every 5 years</td><td>Regular screening</td></tr>
          <tr><td>After 65 years</td><td>Screening may stop if the last 10 years of reports were normal</td><td>On doctor’s advice</td></tr>
        </tbody>
      </table>
    </div>
    <p class="cc-note">HPV vaccination works best before sexual activity begins. Even after vaccination, regular Pap smears remain important. It should not be taken during pregnancy.</p>
  </div>
</section>

<!-- ================= GOVERNMENT OF INDIA — 2026 CAMPAIGN ================= -->
<section class="cc-section cc-section-alt" id="government">
  <div class="wrap">
    <span class="cc-eyebrow">Government of India</span>
    <h2 class="cc-h2">Cervical Cancer Vaccination Campaign launched</h2>
    <p class="cc-sub">On <strong>28 February 2026</strong>, the Prime Minister launched a nationwide HPV Vaccination Programme at Ajmer, Rajasthan — providing the Gardasil-4 vaccine <strong>free of cost</strong> to about <strong>1.15 crore girls aged 14</strong> across all States and UTs, in line with the vision of <em>“Swasth Nari, Sashakt Parivar”</em>.</p>
    <div class="cc-gov">
      <h3>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:24px;height:24px"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>
        What the campaign means for you
      </h3>
      <ul>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          <span><strong>Free vaccine for 14-year-old girls:</strong> Gardasil-4 is given free at government health facilities. Girls turning 15 within 90 days of launch are also eligible during the intensive three-month drive.</span>
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          <span><strong>Easy registration:</strong> Self-register on the <strong>U-WIN</strong> platform, get pre-registered by a health worker, or simply walk in. Vaccination certificates are downloadable from U-WIN.</span>
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          <span><strong>Safe &amp; supervised:</strong> Given only at facilities with a cold-chain point and a medical officer; each girl is observed for 30 minutes after the dose. Sessions usually run 9 AM–2 PM. Don’t go on an empty stomach.</span>
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
          <span><strong>World-class backing:</strong> Procured in partnership with <strong>GAVI, the Vaccine Alliance</strong>; logistics tracked via <strong>eVIN</strong>. India now joins 160+ countries with HPV vaccination in their national immunisation schedule.</span>
        </li>
      </ul>
    </div>
    <div class="cc-goal">
      <strong>The global goal — WHO “90-70-90” by 2030:</strong> 90% of girls fully vaccinated against HPV by age 15, 70% of women screened with a high-performance test by ages 35 and 45, and 90% of women with cervical disease receiving treatment. India’s 2026 campaign is a major step toward this elimination target.
    </div>
    <p class="cc-note">Source: Press Information Bureau (PIB), Government of India — Cervical Cancer Vaccination Campaign, 28 February 2026; WHO; GLOBOCAN 2022.</p>
  </div>
</section>

<!-- ========================== FAQ ========================== -->
<section class="cc-section" id="faq">
  <div class="wrap">
    <span class="cc-eyebrow">Questions answered</span>
    <h2 class="cc-h2">Frequently asked questions</h2>
    <div class="cc-faq">
      <?php foreach ($ccFaqs as $f): ?>
      <details>
        <summary>
          <?= e($f['q']) ?>
          <svg class="chev" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="ans"><?= e($f['a']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============= FIND A GYNECOLOGIST (city-aware) ============= -->
<section class="cc-section cc-section-alt" id="find-doctor">
  <div class="wrap">
    <span class="cc-eyebrow">Take the next step</span>
    <h2 class="cc-h2">
      Gynecologists<?= $ccCity ? ' in ' . e($ccCity) : '' ?>
    </h2>
    <p class="cc-sub">A regular check-up with a gynecologist is the single most important thing you can do. <?= $ccCity ? 'Here are trusted gynecologists in ' . e($ccCity) . ' you can reach out to.' : 'Find a gynecologist near you and book a screening.' ?></p>

    <?php if ($ccDoctors): ?>
    <div class="cc-docs">
      <?php foreach ($ccDoctors as $d): ?>
      <div class="cc-doc">
        <div class="cc-doc-top">
          <div class="cc-doc-av"><?= e($d['initials']) ?></div>
          <div>
            <div class="cc-doc-name"><?= e($d['name']) ?></div>
            <div class="cc-doc-meta">Gynecologist<?= $d['area'] !== '' ? ' · ' . e($d['area']) : ($d['city'] !== '' ? ' · ' . e($d['city']) : '') ?></div>
          </div>
        </div>
        <?php if ($d['rating'] !== null && $d['rating'] > 0): ?>
        <div class="cc-doc-rating">
          <span class="star">★</span> <?= e(number_format($d['rating'], 1)) ?>
          <?php if ($d['reviews'] > 0): ?><span style="color:var(--mute)">(<?= (int) $d['reviews'] ?> reviews)</span><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="cc-doc-actions">
          <a class="is-primary" href="<?= e($d['profile_url']) ?>">View profile</a>
          <?php if ($d['gmaps_url'] !== ''): ?>
          <a href="<?= e($d['gmaps_url']) ?>" target="_blank" rel="noopener nofollow">View location</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="font-size:15px;color:var(--mute);">We’re building our gynecologist directory. Use the button below to search across all our listed doctors.</p>
    <?php endif; ?>

    <div class="cc-docs-foot">
      <a class="cc-btn cc-btn-primary" style="background:var(--teal-600);color:#fff;" href="<?= e($ccSearchMoreUrl) ?>">
        Search more gynecologists<?= $ccCity ? ' in ' . e($ccCity) : '' ?>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </a>
    </div>
  </div>
</section>

<!-- ====================== DISCLAIMER ====================== -->
<section class="cc-section" style="padding:32px 0 8px;">
  <div class="wrap">
    <p class="cc-disc">This page is for general awareness and education only and is not medical advice. Figures and costs are drawn from public health sources and may be updated over time; costs are indicative only. Always consult a qualified doctor for diagnosis, screening and treatment decisions.</p>
  </div>
</section>

<!-- ====================== REFERENCES ====================== -->
<section class="cc-refs">
  <div class="wrap">
    <h2>References &amp; sources</h2>
    <p>The information on this page is compiled from the following public, authoritative sources:</p>
    <ul>
      <li>Press Information Bureau (PIB), Government of India — <em>Cervical Cancer Vaccination Campaign Launched</em> (28 Feb 2026) &amp; cervical cancer / GLOBOCAN 2022 data —
        <a href="https://www.pib.gov.in/PressReleasePage.aspx?PRID=2233632&amp;reg=3&amp;lang=1" target="_blank" rel="noopener nofollow">pib.gov.in</a>
      </li>
      <li>World Health Organization — <em>Cervical cancer fact sheet</em> —
        <a href="https://www.who.int/news-room/fact-sheets/detail/cervical-cancer" target="_blank" rel="noopener nofollow">who.int</a>
      </li>
      <li>Centers for Disease Control and Prevention (CDC) — <em>Basic information about HPV and cancer</em> —
        <a href="https://www.cdc.gov/cancer/hpv/basic-information.html" target="_blank" rel="noopener nofollow">cdc.gov</a>
      </li>
      <li>National Library of Medicine (PMC) — peer-reviewed research on cervical cancer in India —
        <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC12702179/" target="_blank" rel="noopener nofollow">pmc.ncbi.nlm.nih.gov</a>
      </li>
      <li>GAVI, the Vaccine Alliance — <em>Gavi and Government of India partnership</em> —
        <a href="https://www.gavi.org/news/media-room/gavi-and-government-india-establish-new-partnership-protect-millions-children-2026" target="_blank" rel="noopener nofollow">gavi.org</a>
      </li>
    </ul>
  </div>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
