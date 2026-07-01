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
// cat: about | screening | vaccine | general (used by FAQ filters UI)
$ccFaqs = [
  [
    'cat' => 'about',
    'q' => 'What is cervical cancer and what causes it?',
    'a' => 'Cervical cancer forms in the cervix — the lower part of the uterus that connects to the vagina. Its main cause is persistent infection with the Human Papillomavirus (HPV), the most common sexually transmitted infection. HPV is extremely common — by age 50 most women will have had it at some point — and in most people the body’s immunity clears it on its own, usually within about two years. Only when a high-risk infection stays for many years can it slowly turn into cancer. High-risk types 16 and 18 alone account for more than 80% of cervical cancer cases in India.',
  ],
  [
    'cat' => 'about',
    'q' => 'How common is cervical cancer in India?',
    'a' => 'It is the second most common cancer among Indian women, with over 1,20,000 new cases and nearly 80,000 deaths every year (GLOBOCAN 2022). India accounts for about 25% of the world’s cervical cancer deaths — one in every five women globally who suffers from cervical cancer is from India. Yet it is the only cancer that can be prevented by a vaccine if given in time.',
  ],
  [
    'cat' => 'about',
    'q' => 'Can cervical cancer be prevented?',
    'a' => 'Yes — it is one of the most preventable cancers. Prevention rests on three steps: (1) the HPV vaccine, ideally given to girls aged 9–14 before any exposure; (2) regular screening (Pap smear or HPV DNA test) from age 30, which finds abnormal cells before they ever become cancer; and (3) treating any pre-cancerous changes early. Together, vaccination plus screening can prevent the large majority of cases.',
  ],
  [
    'cat' => 'about',
    'q' => 'How long does cervical cancer take to develop?',
    'a' => 'It develops slowly. According to the WHO, it usually takes 15–20 years for abnormal cells to turn into cancer (about 5–10 years in women with weakened immunity, such as untreated HIV). This long window is exactly why screening works so well — a Pap smear or HPV test can catch the warning changes many years before cancer forms, when they are easy to treat.',
  ],
  [
    'cat' => 'about',
    'q' => 'What are the early warning signs?',
    'a' => 'In the early stage there are usually no symptoms — which is why screening matters. As it progresses you may notice: bleeding between periods, bleeding after intercourse, bleeding after menopause, foul-smelling or unusual vaginal discharge, persistent pelvic (lower abdomen) pain, pain during intercourse, or back/leg pain. As the disease advances, signs can include swelling in the legs, and unexplained weight loss, fatigue or loss of appetite. These signs are not proof of cancer, but if you notice any of them, see a gynecologist immediately.',
  ],
  [
    'cat' => 'about',
    'q' => 'Who is at higher risk?',
    'a' => 'Risk is higher with: HPV infection, smoking, long-standing HPV infection, weak immunity, never getting a Pap smear or HPV test, unprotected sex, and starting sexual activity at a very young age.',
  ],
  [
    'cat' => 'about',
    'q' => 'How is cervical cancer treated, and can it be cured?',
    'a' => 'Yes — when found early, cervical cancer is highly treatable with good long-term survival. Pre-cancerous changes caught by screening are usually removed in a simple day procedure (such as cryotherapy/thermal ablation, LEEP/LLETZ, or a cone biopsy) before they ever become cancer. If invasive cancer has formed, a specialist team plans treatment based on the stage — this may involve surgery, radiotherapy, chemotherapy, and newer targeted or immunotherapy, along with supportive care. The earlier it is caught, the simpler and more effective the treatment.',
  ],
  [
    'cat' => 'screening',
    'q' => 'What screening tests are available, and do they hurt?',
    'a' => 'The main tests are the Pap smear (finds early abnormal cell changes in the cervix), Liquid Based Cytology (LBC), and the HPV DNA test (detects the virus itself). A Pap smear is done by a gynecologist in about 5–10 minutes using a small brush to collect a few cells from the cervix, which are then checked in a lab. It does not hurt — you may feel only mild discomfort for a few seconds. These tests can spot abnormal cells many years before cancer develops.',
  ],
  // [
  //   'cat' => 'screening',
  //   'q' => 'How much do the tests cost in India?',
  //   'a' => 'Costs are indicative only and vary by city and lab — please confirm with your provider. As a rough guide: a Pap smear starts from around ₹500, Liquid Based Cytology (LBC) from around ₹1,200, and a combined Pap + HPV DNA test from around ₹2,000. They are available at private hospitals, gynecologist clinics, reputed laboratories, and government / civil hospitals.',
  // ],
  [
    'cat' => 'vaccine',
    'q' => 'What is India’s HPV Vaccination Campaign launched in 2026?',
    'a' => 'On 28 February 2026, the Prime Minister launched a nationwide HPV Vaccination Programme at Ajmer, Rajasthan. It offers the Gardasil-4 vaccine free of cost at government facilities to about 1.15 crore girls aged 14 across all States and UTs (girls turning 15 within 90 days of launch are also eligible). A single dose of Gardasil-4 is 93–100% effective against the HPV types that cause cervical cancer. With this, India joins over 160 countries with HPV vaccination in their national immunisation schedules.',
  ],
  // [
  //   'cat' => 'vaccine',
  //   'q' => 'Who should get the HPV vaccine and how many doses?',
  //   'a' => 'The vaccine works best given before any sexual activity begins. By age: girls 9–14 years need 2 doses (the second after 6 months); ages 15–45 need 3 doses (at 0, 1–2, and 6 months). It is given as an injection in the arm. Even after vaccination, regular Pap smears are still important.',
  // ],
  // [
  //   'cat' => 'vaccine',
  //   'q' => 'Can adults or already sexually active women still get the vaccine?',
  //   'a' => 'Yes. The vaccine is most effective before exposure to HPV, but women aged 15–45 can still benefit and may be advised the vaccine on a doctor’s recommendation (as a 3-dose schedule). It will not treat an existing infection, but it can still protect against HPV types you have not yet encountered. Talk to your gynecologist about whether it is right for you.',
  // ],
  [
    'cat' => 'general',
    'q' => 'If I have had the HPV vaccine, do I still need screening?',
    'a' => 'Yes — this is very important. The HPV vaccine does not cover every cancer-causing HPV type, so regular Pap smears or HPV DNA tests are still needed even after vaccination. Vaccination and screening work together: the vaccine prevents most infections, and screening catches anything the vaccine does not. From age 30, continue with a Pap smear every 3 years or an HPV DNA test every 5 years.',
  ],
  // [
  //   'cat' => 'vaccine',
  //   'q' => 'Which HPV vaccines are available in India and at what cost?',
  //   'a' => 'Three vaccines are commonly available in the private market. Prices are approximate and vary by clinic: Cervavac (made by India’s Serum Institute, protects against 4 HPV types) from around ₹1,500 per dose; Gardasil-4 (4 types) from around ₹3,500; and Gardasil-9 (9 types, broadest protection) from around ₹9,000. The 2026 government campaign provides Gardasil-4 free for 14-year-old girls at government facilities.',
  // ],
  // [
  //   'cat' => 'vaccine',
  //   'q' => 'Is the HPV vaccine safe, and who should not take it?',
  //   'a' => 'HPV vaccines are among the most extensively studied vaccines globally, with over 500 million doses given worldwide since 2006. Under the campaign, girls are observed for 30 minutes after vaccination. It should not be given during moderate/severe illness (wait until recovery), to anyone with a known yeast allergy or prior allergic reaction to a vaccine, or during pregnancy. If pregnancy occurs after the first dose, the remaining doses can be taken after delivery.',
  // ],
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
<script type="application/ld+json">
  <?= json_encode($webPageLd, $jsonFlags) ?>
</script>
<script type="application/ld+json">
  <?= json_encode($faqLd, $jsonFlags) ?>
</script>
<style>
  /* ===== Cervical Cancer awareness page (page-scoped .cc-*) ============ */
  .cc-hero {
    position: relative;
    overflow: hidden;
    background:
      radial-gradient(900px 480px at 80% -10%, rgba(15, 155, 110, .16), transparent 60%),
      linear-gradient(180deg, var(--teal-950) 0%, #0a5d44 55%, var(--teal-700) 100%);
    color: #fff;
    padding: 84px 0 96px;
  }

  .cc-hero .wrap {
    position: relative;
    z-index: 1;
    max-width: 1180px;
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
    gap: 48px;
    align-items: center;
  }

  .cc-hero-text {
    min-width: 0;
  }

  .cc-hero-media {
    min-width: 0;
  }

  /* Responsive 16:9 video frame */
  .cc-video {
    position: relative;
    width: 100%;
    padding-top: 56.25%;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 24px 60px rgba(0, 0, 0, .35);
    border: 1px solid rgba(255, 255, 255, .16);
    background: #000;
  }

  .cc-video iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
  }

  @media (max-width:880px) {
    .cc-hero .wrap {
      grid-template-columns: 1fr;
      gap: 34px;
    }

    .cc-hero-media {
      order: 2;
    }
  }

  .cc-ribbon {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .22);
    color: #eafff7;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .02em;
    padding: 7px 14px;
    border-radius: 999px;
    margin-bottom: 20px;
  }

  .cc-ribbon svg {
    width: 15px;
    height: 15px
  }

  .cc-hero h1 {
    font-size: clamp(30px, 5vw, 52px);
    line-height: 1.08;
    font-weight: 600;
    margin-bottom: 18px;
  }

  .cc-hero p.lede {
    font-size: clamp(16px, 2.2vw, 20px);
    line-height: 1.6;
    color: rgba(255, 255, 255, .86);
    margin-bottom: 30px;
  }

  .cc-hero-ctas {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
  }

  .cc-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 15px;
    border-radius: 12px;
    padding: 13px 22px;
    text-decoration: none;
    transition: transform .15s ease, box-shadow .15s ease;
    border: 1px solid transparent;
  }

  .cc-btn:hover {
    transform: translateY(-1px)
  }

  .cc-btn-primary {
    background: #fff;
    color: var(--teal-800);
    box-shadow: 0 8px 24px rgba(0, 0, 0, .18)
  }

  .cc-btn-ghost {
    background: transparent;
    color: #fff;
    border-color: rgba(255, 255, 255, .4)
  }

  .cc-btn-ghost:hover {
    background: rgba(255, 255, 255, .1)
  }

  .cc-section {
    padding: 64px 0;
  }

  .cc-section .wrap {
    max-width: 1080px;
  }

  .cc-section-alt {
    background: var(--bg-2);
  }

  .cc-eyebrow {
    display: block;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--teal-600);
    margin-bottom: 10px;
  }

  .cc-h2 {
    font-size: clamp(24px, 3.4vw, 34px);
    line-height: 1.15;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 14px;
  }

  .cc-sub {
    font-size: 17px;
    line-height: 1.6;
    color: var(--mute);
    max-width: 680px;
    margin-bottom: 34px;
  }

  /* Figures & Facts — header + stat grid + remember callout */
  .cc-facts-wrap {
    max-width: 1180px;
  }

  .cc-facts-head {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
    gap: 40px;
    align-items: center;
    margin-bottom: 42px;
  }

  .cc-facts-intro .cc-sub {
    max-width: none;
    margin-bottom: 0;
  }

  .cc-eyebrow-line::before {
    content: "";
    display: block;
    width: 42px;
    height: 3px;
    background: var(--teal-600);
    border-radius: 2px;
    margin-bottom: 12px;
  }

  .cc-h2 .cc-highlight {
    color: var(--teal-700);
  }

  .cc-facts-hero {
    margin: 0;
  }

  .cc-facts-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 18px;
    object-fit: cover;
    box-shadow: 0 12px 40px rgba(15, 155, 110, .12);
  }

  .cc-facts-img-ph {
    /* aspect-ratio: 5/4; */
    border-radius: 18px;
    overflow: hidden;
    background: linear-gradient(135deg, var(--teal-50) 0%, #e8f5f0 50%, var(--bg-2) 100%);
    border: 1px dashed var(--teal-200);
    display: grid;
    place-items: center;
    min-height: 220px;
  }

  .cc-facts-img-ph span {
    font-size: 13px;
    color: var(--mute);
    font-weight: 500;
  }

  .cc-facts-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
  }

  .cc-stat {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 22px 20px 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .cc-stat::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, var(--teal-500), var(--teal-700));
  }

  .cc-stat-ico {
    width: 40px;
    height: 40px;
    margin-bottom: 12px;
    flex: 0 0 auto;
  }

  .cc-stat-ico svg {
    width: 100%;
    height: 100%;
  }

  .cc-stat-num {
    font-size: clamp(28px, 3.6vw, 38px);
    font-weight: 700;
    color: var(--teal-700);
    line-height: 1;
    letter-spacing: -.02em;
  }

  .cc-stat-num span {
    font-size: .52em;
    font-weight: 600;
    color: var(--teal-600);
  }

  .cc-stat-label {
    margin-top: 8px;
    font-size: 14px;
    line-height: 1.5;
    color: var(--ink-2);
    flex: 1 1 auto;
  }

  .cc-stat-src {
    margin-top: 12px;
    font-size: 11.5px;
    color: var(--mute);
  }

  .cc-remember {
    grid-column: 3/5;
    grid-row: 2;
    background: linear-gradient(135deg, #f0faf6 0%, var(--teal-50) 100%);
    border: 1px solid var(--teal-100);
    border-radius: 18px;
    padding: 24px 26px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
  }

  .cc-remember-ico {
    flex: 0 0 auto;
    width: 44px;
    height: 44px;
  }

  .cc-remember-ico svg {
    width: 100%;
    height: 100%;
  }

  .cc-remember h3 {
    font-size: 17px;
    font-weight: 600;
    color: var(--teal-700);
    margin-bottom: 8px;
  }

  .cc-remember .cc-remember-lead {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink);
    line-height: 1.45;
    margin-bottom: 6px;
  }

  .cc-remember .cc-remember-tags {
    font-size: 13.5px;
    color: var(--mute);
    margin-bottom: 10px;
  }

  .cc-remember .cc-remember-end {
    font-size: 14.5px;
    line-height: 1.5;
    color: var(--ink-2);
  }

  .cc-remember .cc-remember-end strong {
    color: var(--teal-700);
    font-weight: 600;
  }

  @media (max-width:1024px) {
    .cc-facts-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cc-remember {
      grid-column: 1/-1;
      grid-row: auto;
    }
  }

  @media (max-width:880px) {
    .cc-facts-head {
      grid-template-columns: 1fr;
      gap: 28px;
    }

    .cc-facts-hero {
      order: -1;
    }
  }

  @media (max-width:560px) {
    .cc-facts-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Prevention / pillars */
  .cc-pillars {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 18px;
  }

  .cc-pillar {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 26px;
  }

  .cc-pillar .ico {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: var(--teal-50);
    color: var(--teal-700);
    margin-bottom: 14px;
  }

  .cc-pillar .ico svg {
    width: 24px;
    height: 24px
  }

  .cc-pillar h3 {
    font-size: 18px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
  }

  .cc-pillar p {
    font-size: 14.5px;
    line-height: 1.6;
    color: var(--mute);
  }

  /* Prevention spotlight (mockup-style) */
  .cc-prev-wrap {
    max-width: 1180px;
  }

  .cc-prev-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 26px;
    align-items: center;
    margin-bottom: 22px;
  }

  .cc-prev-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--teal-700);
    color: #fff;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: 10px;
  }

  .cc-prev-badge svg {
    width: 14px;
    height: 14px;
  }

  .cc-prev-title {
    font-size: clamp(30px, 4.8vw, 52px);
    line-height: 1.08;
    font-weight: 700;
    color: #161d24;
    margin-bottom: 14px;
  }

  .cc-prev-title .cc-highlight {
    color: var(--teal-700);
  }

  .cc-prev-intro {
    font-size: 17px;
    line-height: 1.6;
    color: var(--mute);
    max-width: 580px;
  }

  .cc-prev-hero {
    margin: 0;
  }

  .cc-prev-hero-ph {
    width: 100%;
    border: 1px dashed var(--teal-200);
    border-radius: 20px;
    min-height: 240px;
    aspect-ratio: 16/8;
    background: linear-gradient(135deg, #f7fbfa 0%, var(--teal-50) 45%, #eef5f2 100%);
    display: grid;
    place-items: center;
  }

  .cc-prev-hero-ph span {
    font-size: 13px;
    color: var(--mute);
  }

  .cc-prev-hero-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 20px;
  }

  .cc-prev-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
  }

  .cc-prev-card {
    background: var(--bg);
    border: 1px solid #dde3e6;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
  }

  .cc-prev-card-media {
    margin: 0;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: linear-gradient(135deg, #f4fbf8 0%, #eaf6f1 100%);
  }

  .cc-prev-card-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .cc-prev-card-ph {
    height: 100%;
    display: grid;
    place-items: center;
    border-bottom: 1px dashed #cde6dd;
  }

  .cc-prev-card-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-prev-card-body {
    padding: 18px 18px 20px;
  }

  .cc-prev-icon {
    width: 54px;
    height: 54px;
    border-radius: 50%;
    margin-top: -44px;
    margin-bottom: 12px;
    background: #fff;
    border: 1px solid #dbe7e2;
    box-shadow: 0 4px 10px rgba(15, 155, 110, .08);
    display: grid;
    place-items: center;
    color: var(--teal-700);
  }

  .cc-prev-icon svg {
    width: 26px;
    height: 26px;
  }

  .cc-prev-card h3 {
    font-size: clamp(28px, 3vw, 34px);
    font-weight: 700;
    color: #1c2630;
    margin-bottom: 8px;
  }

  .cc-prev-card h3 span {
    color: var(--teal-700);
  }

  .cc-prev-card p {
    font-size: 14.5px;
    line-height: 1.68;
    color: #4d5a66;
  }

  .cc-prev-bar {
    margin-top: 14px;
    background: #f2f7f5;
    border: 1px solid #dce8e3;
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
  }

  .cc-prev-bar-left {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--teal-700);
    font-weight: 700;
  }

  .cc-prev-bar-left svg {
    width: 22px;
    height: 22px;
  }

  .cc-prev-links {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }

  .cc-prev-links span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #33404a;
    font-size: 14px;
  }

  .cc-prev-links span+span {
    padding-left: 12px;
    border-left: 1px solid #bfd2ca;
  }

  .cc-prev-links svg {
    width: 18px;
    height: 18px;
    color: var(--teal-700);
  }

  .cc-prev-bar-end {
    font-size: 29px;
    color: var(--teal-700);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
  }

  .cc-prev-bar-end svg {
    width: 17px;
    height: 17px;
    color: #f87171;
  }

  @media (max-width:1080px) {
    .cc-prev-cards {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cc-prev-card:last-child {
      grid-column: 1/-1;
    }
  }

  @media (max-width:880px) {
    .cc-prev-head {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width:640px) {
    .cc-prev-cards {
      grid-template-columns: 1fr;
    }

    .cc-prev-card:last-child {
      grid-column: auto;
    }

    .cc-prev-bar-end {
      font-size: 20px;
      white-space: normal;
    }
  }

  /* Tests & screening spotlight (mockup-style) */
  .cc-tests {
    background: #f6f9f8;
  }

  .cc-tests-wrap {
    max-width: 1180px;
  }

  .cc-tests-head {
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
    gap: 26px;
    align-items: center;
    margin-bottom: 16px;
  }

  .cc-tests-kicker {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--teal-700);
    margin-bottom: 10px;
  }

  .cc-tests-title {
    font-size: clamp(34px, 4.6vw, 52px);
    line-height: 1.08;
    font-weight: 700;
    color: #171f28;
    margin-bottom: 10px;
  }

  .cc-tests-title .cc-highlight {
    color: var(--teal-700);
  }

  .cc-tests-intro {
    font-size: 15px;
    line-height: 1.62;
    color: #536170;
    max-width: 520px;
  }

  .cc-tests-hero {
    margin: 0;
  }

  .cc-tests-hero-ph {
    width: 100%;
    aspect-ratio: 16/8;
    border-radius: 20px;
    border: 1px dashed #c6e3d9;
    background: linear-gradient(135deg, #edf7f3 0%, #e6f2ee 55%, #f8fbfa 100%);
    display: grid;
    place-items: center;
  }

  .cc-tests-hero-ph span,
  .cc-tests-card-ph span,
  .cc-vax-bottle-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-tests-hero-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 20px;
  }

  .cc-tests-cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-top: 12px;
  }

  .cc-tests-card {
    background: #fff;
    border: 1px solid #dce5e2;
    border-radius: 14px;
    overflow: hidden;
  }

  .cc-tests-card-media {
    margin: 0;
    aspect-ratio: 16/8;
    background: linear-gradient(135deg, #edf8f5, #f4fbf8);
  }

  .cc-tests-card-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .cc-tests-card-ph {
    height: 100%;
    display: grid;
    place-items: center;
  }

  .cc-tests-card-body {
    padding: 14px 14px 16px;
  }

  .cc-tests-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #fff;
    border: 1px solid #d7e7e1;
    color: var(--teal-700);
    margin-top: -34px;
    position: relative;
    z-index: 2;
  }

  .cc-tests-icon svg {
    width: 22px;
    height: 22px;
  }

  .cc-tests-card h3 {
    font-size: 23px;
    color: var(--teal-700);
    font-weight: 700;
    margin: 10px 0 8px;
  }

  .cc-tests-card p {
    font-size: 13.5px;
    line-height: 1.62;
    color: #536170;
  }

  .cc-cost-box,
  .cc-vax-box,
  .cc-age-box {
    margin-top: 12px;
    background: #fff;
    border: 1px solid #dce6e2;
    border-radius: 14px;
    padding: 14px;
  }

  .cc-box-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 18px;
    font-weight: 700;
    color: #1f2a35;
    margin-bottom: 12px;
  }

  .cc-box-title svg {
    width: 20px;
    height: 20px;
    color: var(--teal-700);
  }

  .cc-cost-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .cc-cost-item {
    background: #f8fbfa;
    border: 1px solid #e3ece8;
    border-radius: 12px;
    padding: 12px;
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .cc-cost-item .ico {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #e9f5f0;
    color: var(--teal-700);
  }

  .cc-cost-item .ico svg {
    width: 20px;
    height: 20px;
  }

  .cc-cost-item h4 {
    font-size: 16px;
    color: #1f2a35;
    font-weight: 700;
    margin-bottom: 2px;
  }

  .cc-cost-item p {
    font-size: 12px;
    color: #5f6c77;
    margin-bottom: 2px;
  }

  .cc-cost-item strong {
    font-size: 33px;
    color: var(--teal-700);
    font-weight: 700;
  }

  .cc-tests-note {
    font-size: 11.5px;
    color: #7b8793;
    margin-top: 8px;
  }

  .cc-vax-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
  }

  .cc-vax-card {
    background: #f8fbfa;
    border: 1px solid #e3ece8;
    border-radius: 12px;
    padding: 12px;
    display: grid;
    grid-template-columns: 72px 1fr;
    gap: 10px;
    align-items: start;
  }

  .cc-vax-bottle-ph {
    width: 72px;
    height: 72px;
    border-radius: 10px;
    border: 1px dashed #c5ddd3;
    background: linear-gradient(135deg, #ecf7f3, #f8fcfa);
    display: grid;
    place-items: center;
  }

  .cc-vax-name {
    font-size: 24px;
    font-weight: 700;
    color: var(--teal-700);
    margin-bottom: 2px;
  }

  .cc-vax-meta {
    font-size: 12px;
    color: #5d6a76;
    line-height: 1.5;
  }

  .cc-vax-meta strong {
    color: #2f3a44;
    font-weight: 700;
  }

  .cc-age-track {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
    position: relative;
  }

  .cc-age-track::before {
    content: "";
    position: absolute;
    left: 6%;
    right: 6%;
    top: 13px;
    height: 2px;
    background: #8cd0b7;
    z-index: 0;
  }

  .cc-age-item {
    position: relative;
    z-index: 1;
    background: #f9fcfb;
    border: 1px solid #e4ece9;
    border-radius: 10px;
    padding: 18px 10px 12px;
  }

  .cc-age-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #11a071;
    position: absolute;
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
  }

  .cc-age-item h4 {
    font-size: 13px;
    font-weight: 700;
    color: #1f2b36;
    margin-bottom: 6px;
    text-align: center;
  }

  .cc-age-item p {
    font-size: 11.5px;
    line-height: 1.45;
    color: #5f6c77;
    text-align: center;
  }

  .cc-tests-cta {
    margin-top: 14px;
    background: #e8f3ef;
    border: 1px solid #d0e5dc;
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }

  .cc-tests-cta-left {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--teal-700);
    font-size: 15px;
    font-weight: 700;
  }

  .cc-tests-cta-left svg {
    width: 24px;
    height: 24px;
  }

  .cc-tests-cta-links {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
  }

  .cc-tests-cta-links span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    color: #40505d;
  }

  .cc-tests-cta-links svg {
    width: 15px;
    height: 15px;
    color: var(--teal-700);
  }

  .cc-tests-cta-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--teal-700);
    color: #fff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 14px;
    border-radius: 10px;
  }

  .cc-tests-cta-btn svg {
    width: 15px;
    height: 15px;
  }

  @media (max-width:980px) {
    .cc-tests-head {
      grid-template-columns: 1fr;
    }

    .cc-tests-cards,
    .cc-cost-grid,
    .cc-vax-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .cc-tests-card:last-child,
    .cc-cost-item:last-child,
    .cc-vax-card:last-child {
      grid-column: 1/-1;
    }

    .cc-age-track {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .cc-age-track::before {
      display: none;
    }
  }

  @media (max-width:640px) {

    .cc-tests-cards,
    .cc-cost-grid,
    .cc-vax-grid,
    .cc-age-track {
      grid-template-columns: 1fr;
    }

    .cc-tests-card:last-child,
    .cc-cost-item:last-child,
    .cc-vax-card:last-child {
      grid-column: auto;
    }
  }

  /* How it starts / progression (mockup-style) */
  .cc-how-wrap {
    max-width: 1180px;
  }

  .cc-how-head {
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
    gap: 28px;
    align-items: center;
    margin-bottom: 20px;
  }

  .cc-how-kicker {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--teal-700);
    margin-bottom: 10px;
  }

  .cc-how-title {
    font-size: clamp(28px, 3.8vw, 42px);
    line-height: 1.12;
    font-weight: 700;
    color: #152028;
    margin-bottom: 12px;
  }

  .cc-how-title .cc-highlight {
    color: var(--teal-700);
  }

  .cc-how-intro {
    font-size: 15px;
    line-height: 1.65;
    color: #4f5d68;
    max-width: 560px;
  }

  .cc-how-intro strong {
    color: var(--teal-700);
  }

  .cc-how-anatomy {
    margin: 0;
    position: relative;
  }

  .cc-how-anatomy-ph {
    width: 100%;
    aspect-ratio: 16/10;
    border-radius: 20px;
    border: 1px dashed #c6e3d9;
    background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 40%, #f8fbfa 100%);
    display: grid;
    place-items: center;
    min-height: 200px;
  }

  .cc-how-anatomy-ph span,
  .cc-how-card-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-how-anatomy-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 20px;
    object-fit: cover;
  }

  .cc-how-cervix-callout {
    position: absolute;
    bottom: 14px;
    left: 14px;
    right: 14px;
    background: rgba(255, 255, 255, .92);
    border: 1px solid #e8d4e0;
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 12px;
    line-height: 1.5;
    color: #4f5d68;
  }

  .cc-how-cervix-callout strong {
    color: var(--teal-800);
    font-weight: 700;
  }

  .cc-how-track {
    display: flex;
    align-items: stretch;
    gap: 8px;
    margin-bottom: 12px;
  }

  .cc-how-card {
    flex: 1 1 0;
    min-width: 0;
    background: #fff;
    border: 1px solid #dce6e2;
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
    display: flex;
    flex-direction: column;
  }

  .cc-how-card-media {
    margin: 0 0 10px;
  }

  .cc-how-card-ph {
    width: 100%;
    aspect-ratio: 4/3;
    border-radius: 10px;
    border: 1px dashed #d5e5de;
    background: linear-gradient(135deg, #f5f0fa, #faf5f8);
    display: grid;
    place-items: center;
    min-height: 72px;
  }

  .cc-how-card h3 {
    font-size: 15px;
    font-weight: 700;
    color: #1f2a35;
    margin-bottom: 6px;
  }

  .cc-how-card p {
    font-size: 12px;
    line-height: 1.55;
    color: #5a6772;
    flex: 1 1 auto;
    margin-bottom: 10px;
  }

  .cc-how-card p em {
    font-style: italic;
  }

  .cc-how-time {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #eef7f3;
    border: 1px solid #cfe8dc;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 11px;
    font-weight: 700;
    color: var(--teal-800);
    align-self: flex-start;
  }

  .cc-how-time svg {
    width: 14px;
    height: 14px;
    color: var(--teal-700);
  }

  .cc-how-arrow {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    color: var(--teal-500);
    font-size: 22px;
    font-weight: 700;
    padding-top: 40px;
  }

  .cc-how-bar {
    position: relative;
    height: 8px;
    background: #e8f3ef;
    border-radius: 999px;
    margin: 8px 0 20px;
    overflow: visible;
  }

  .cc-how-bar::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, var(--teal-600), var(--teal-400));
    border-radius: 999px;
  }

  .cc-how-bar-labels {
    display: flex;
    justify-content: space-between;
    gap: 4px;
    margin-top: -4px;
    margin-bottom: 18px;
  }

  .cc-how-bar-labels span {
    font-size: 10.5px;
    font-weight: 600;
    color: var(--teal-700);
    text-align: center;
    flex: 1;
  }

  .cc-how-footer {
    background: linear-gradient(135deg, #f0faf6 0%, var(--teal-50) 100%);
    border: 1px solid var(--teal-100);
    border-radius: 14px;
    padding: 16px 18px;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 14px 20px;
    align-items: center;
  }

  .cc-how-footer-head {
    display: flex;
    align-items: center;
    gap: 10px;
    grid-column: 1/-1;
  }

  .cc-how-footer-head svg {
    width: 28px;
    height: 28px;
    color: var(--teal-700);
  }

  .cc-how-footer-head h3 {
    font-size: 17px;
    font-weight: 700;
    color: var(--teal-800);
  }

  .cc-how-footer-text {
    font-size: 14px;
    line-height: 1.55;
    color: #2f3d48;
  }

  .cc-how-footer-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    grid-column: 1/-1;
  }

  .cc-how-footer-pills span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #3f4d58;
    background: #fff;
    border: 1px solid #d5e5de;
    border-radius: 999px;
    padding: 6px 12px;
  }

  .cc-how-footer-pills svg {
    width: 15px;
    height: 15px;
    color: var(--teal-700);
  }

  @media (max-width:1024px) {
    .cc-how-track {
      flex-wrap: wrap;
    }

    .cc-how-card {
      flex: 1 1 calc(50% - 20px);
    }

    .cc-how-arrow {
      display: none;
    }
  }

  @media (max-width:880px) {
    .cc-how-head {
      grid-template-columns: 1fr;
    }

    .cc-how-bar-labels {
      flex-wrap: wrap;
    }

    .cc-how-bar-labels span {
      flex: 1 1 45%;
    }
  }

  @media (max-width:560px) {
    .cc-how-card {
      flex: 1 1 100%;
    }

    .cc-how-footer {
      grid-template-columns: 1fr;
    }
  }

  /* Risk & age spotlight (mockup-style) */
  .cc-risk-wrap {
    max-width: 1180px;
  }

  .cc-risk-head {
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
    gap: 28px;
    align-items: center;
    margin-bottom: 20px;
  }

  .cc-risk-kicker {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--teal-700);
    margin-bottom: 10px;
  }

  .cc-risk-title {
    font-size: clamp(30px, 4.2vw, 46px);
    line-height: 1.1;
    font-weight: 700;
    color: #152028;
    margin-bottom: 12px;
  }

  .cc-risk-title .cc-highlight {
    color: var(--teal-700);
  }

  .cc-risk-intro {
    font-size: 15px;
    line-height: 1.65;
    color: #4f5d68;
    max-width: 560px;
  }

  .cc-risk-intro strong {
    color: var(--teal-700);
  }

  .cc-risk-hero {
    margin: 0;
  }

  .cc-risk-hero-ph {
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 20px;
    border: 1px dashed #c6e3d9;
    background: linear-gradient(135deg, #edf7f3 0%, #e6f2ee 55%, #f8fbfa 100%);
    display: grid;
    place-items: center;
    min-height: 200px;
  }

  .cc-risk-hero-ph span,
  .cc-risk-banner-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-risk-hero-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 20px;
    object-fit: cover;
  }

  .cc-risk-cols {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 14px;
  }

  .cc-risk-panel {
    border-radius: 16px;
    padding: 18px;
    border: 1px solid transparent;
  }

  .cc-risk-panel--factors {
    background: linear-gradient(180deg, #f3faf7 0%, #eaf6f1 100%);
    border-color: #cfe8dc;
  }

  .cc-risk-panel--ages {
    background: linear-gradient(180deg, #fff8f0 0%, #fef3e8 100%);
    border-color: #f5dcc4;
  }

  .cc-risk-panel-head {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
  }

  .cc-risk-panel-ico {
    flex: 0 0 auto;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
  }

  .cc-risk-panel-ico svg {
    width: 22px;
    height: 22px;
  }

  .cc-risk-panel-ico--green {
    background: var(--teal-700);
  }

  .cc-risk-panel-ico--orange {
    background: #e67e22;
  }

  .cc-risk-panel h3 {
    font-size: 20px;
    font-weight: 700;
  }

  .cc-risk-panel--factors h3 {
    color: var(--teal-800);
  }

  .cc-risk-panel--ages h3 {
    color: #c05621;
  }

  .cc-risk-factors {
    list-style: none;
    display: grid;
  }

  .cc-risk-factors li {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(15, 155, 110, .12);
    font-size: 13.5px;
    line-height: 1.5;
    color: #3f4d58;
  }

  .cc-risk-factors li:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .cc-risk-factors .ico {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #cfe5dc;
    display: grid;
    place-items: center;
    color: var(--teal-700);
  }

  .cc-risk-factors .ico svg {
    width: 18px;
    height: 18px;
  }

  .cc-risk-timeline {
    list-style: none;
    position: relative;
    padding-left: 8px;
  }

  .cc-risk-timeline::before {
    content: "";
    position: absolute;
    left: 24px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: #f0c9a8;
  }

  .cc-risk-timeline li {
    display: grid;
    grid-template-columns: 34px 1fr;
    gap: 12px;
    align-items: start;
    padding: 10px 0;
    position: relative;
    font-size: 13.5px;
    line-height: 1.55;
    color: #3f4d58;
  }

  .cc-risk-timeline .dot {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #e67e22;
    display: grid;
    place-items: center;
    color: #e67e22;
    position: relative;
    z-index: 1;
  }

  .cc-risk-timeline .dot svg {
    width: 16px;
    height: 16px;
  }

  .cc-risk-timeline strong {
    color: #1f2a35;
    font-weight: 700;
  }

  .cc-risk-banner {
    background: linear-gradient(135deg, #f4f8f6 0%, #eef5f2 100%);
    border: 1px solid #dce8e3;
    border-radius: 16px;
    padding: 16px 18px;
    display: grid;
    grid-template-columns: auto 1fr minmax(120px, .55fr);
    gap: 16px;
    align-items: center;
    overflow: hidden;
  }

  .cc-risk-banner-ico {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #cfe5dc;
    display: grid;
    place-items: center;
    color: var(--teal-700);
  }

  .cc-risk-banner-ico svg {
    width: 26px;
    height: 26px;
  }

  .cc-risk-banner p {
    font-size: 14px;
    line-height: 1.55;
    color: #2f3d48;
  }

  .cc-risk-banner strong {
    color: var(--teal-700);
    font-weight: 700;
  }

  .cc-risk-banner-visual {
    margin: 0;
    min-height: 90px;
  }

  .cc-risk-banner-ph {
    width: 100%;
    height: 100%;
    min-height: 90px;
    border-radius: 12px;
    border: 1px dashed #c6e3d9;
    background: rgba(255, 255, 255, .6);
    display: grid;
    place-items: center;
  }

  .cc-risk-banner-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 12px;
    object-fit: cover;
  }

  @media (max-width:880px) {
    .cc-risk-head {
      grid-template-columns: 1fr;
    }

    .cc-risk-banner {
      grid-template-columns: auto 1fr;
    }

    .cc-risk-banner-visual {
      grid-column: 1/-1;
    }
  }

  @media (max-width:720px) {
    .cc-risk-cols {
      grid-template-columns: 1fr;
    }
  }

  /* Treatment spotlight (mockup-style) */
  .cc-treat-wrap {
    max-width: 1180px;
  }

  .cc-treat-head {
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
    gap: 28px;
    align-items: center;
    margin-bottom: 20px;
  }

  .cc-treat-kicker {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--teal-700);
    margin-bottom: 10px;
  }

  .cc-treat-title {
    font-size: clamp(30px, 4.2vw, 46px);
    line-height: 1.1;
    font-weight: 700;
    color: #152028;
    margin-bottom: 12px;
  }

  .cc-treat-title .cc-highlight {
    color: var(--teal-700);
  }

  .cc-treat-intro {
    font-size: 15px;
    line-height: 1.65;
    color: #4f5d68;
    max-width: 540px;
  }

  .cc-treat-hero {
    margin: 0;
  }

  .cc-treat-hero-ph {
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 20px;
    border: 1px dashed #c6e3d9;
    background: linear-gradient(135deg, #edf7f3 0%, #e6f2ee 55%, #f8fbfa 100%);
    display: grid;
    place-items: center;
    min-height: 200px;
  }

  .cc-treat-hero-ph span,
  .cc-treat-illus-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-treat-hero-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 20px;
    object-fit: cover;
  }

  .cc-treat-cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 14px;
  }

  .cc-treat-card {
    border-radius: 16px;
    padding: 18px 18px 14px;
    border: 1px solid transparent;
  }

  .cc-treat-card--pre {
    background: linear-gradient(180deg, #f3faf7 0%, #eaf6f1 100%);
    border-color: #cfe8dc;
  }

  .cc-treat-card--inv {
    background: linear-gradient(180deg, #fff8f0 0%, #fef3e8 100%);
    border-color: #f5dcc4;
  }

  .cc-treat-card-top {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 14px;
  }

  .cc-treat-ico {
    flex: 0 0 auto;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    color: #fff;
  }

  .cc-treat-ico svg {
    width: 22px;
    height: 22px;
  }

  .cc-treat-ico--pre {
    background: var(--teal-700);
  }

  .cc-treat-ico--inv {
    background: #e67e22;
  }

  .cc-treat-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 6px;
  }

  .cc-treat-card--pre h3 {
    color: var(--teal-800);
  }

  .cc-treat-card--inv h3 {
    color: #c05621;
  }

  .cc-treat-card .cc-treat-sub {
    font-size: 13px;
    line-height: 1.55;
    color: #5a6772;
    margin-bottom: 12px;
  }

  .cc-treat-list {
    list-style: none;
    display: grid;
    gap: 10px;
    margin-bottom: 14px;
  }

  .cc-treat-list li {
    font-size: 13.5px;
    line-height: 1.5;
    color: #3f4d58;
    padding-left: 14px;
    position: relative;
  }

  .cc-treat-list li::before {
    content: "";
    position: absolute;
    left: 0;
    top: 8px;
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }

  .cc-treat-card--pre .cc-treat-list li::before {
    background: var(--teal-600);
  }

  .cc-treat-card--inv .cc-treat-list li::before {
    background: #e67e22;
  }

  .cc-treat-list strong {
    font-weight: 700;
    color: #1f2a35;
  }

  .cc-treat-illus {
    margin: 0;
  }

  .cc-treat-illus-ph {
    width: 100%;
    /* aspect-ratio: 16/7; */
    border-radius: 12px;
    border: 1px dashed #d5e5de;
    background: rgba(255, 255, 255, .55);
    display: grid;
    place-items: center;
    min-height: 100px;
  }

  .cc-treat-card--inv .cc-treat-illus-ph {
    border-color: #ecd5bc;
    background: rgba(255, 255, 255, .45);
  }

  .cc-treat-footer {
    background: linear-gradient(135deg, #f0faf6 0%, var(--teal-50) 100%);
    border: 1px solid var(--teal-100);
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    gap: 14px;
    align-items: center;
  }

  .cc-treat-footer-ico {
    flex: 0 0 auto;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #cfe5dc;
    display: grid;
    place-items: center;
    color: var(--teal-700);
  }

  .cc-treat-footer-ico svg {
    width: 26px;
    height: 26px;
  }

  .cc-treat-footer p {
    font-size: 14px;
    line-height: 1.55;
    color: #2f3d48;
  }

  .cc-treat-footer strong {
    color: var(--teal-700);
    font-weight: 700;
  }

  @media (max-width:880px) {
    .cc-treat-head {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width:720px) {
    .cc-treat-cards {
      grid-template-columns: 1fr;
    }
  }

  /* Two-column info cards (symptoms, risk) */
  .cc-two {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 18px;
  }

  .cc-card {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 26px;
  }

  .cc-card h3 {
    font-size: 17px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 14px;
  }

  .cc-card ul {
    list-style: none;
    display: grid;
    gap: 10px;
  }

  .cc-card li {
    position: relative;
    padding-left: 24px;
    font-size: 14.5px;
    line-height: 1.55;
    color: var(--ink-2);
  }

  .cc-card li::before {
    content: "";
    position: absolute;
    left: 2px;
    top: 7px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--teal-400);
  }

  .cc-card-warn {
    border-color: var(--teal-100);
    background: linear-gradient(180deg, #fff, var(--teal-50));
  }

  .cc-card-alert {
    border-color: rgba(255, 159, 10, .35);
    background: linear-gradient(180deg, #fff, #FFF6E8);
  }

  .cc-card-alert li::before {
    background: var(--amber);
  }

  /* Data tables (test costs + age schedule) */
  .cc-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--bg);
  }

  .cc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14.5px;
    min-width: 420px;
  }

  .cc-table th {
    background: var(--teal-50);
    color: var(--teal-800);
    text-align: left;
    font-weight: 600;
    padding: 13px 18px;
    font-size: 13px;
    letter-spacing: .02em;
  }

  .cc-table td {
    padding: 13px 18px;
    border-top: 1px solid var(--line);
    color: var(--ink-2);
    line-height: 1.5;
  }

  .cc-table tbody tr:nth-child(even) {
    background: var(--bg-3);
  }

  .cc-table td:last-child,
  .cc-table th:last-child {
    white-space: nowrap;
  }

  .cc-note {
    font-size: 13px;
    color: var(--mute);
    line-height: 1.6;
    margin-top: 12px;
  }

  /* Government campaign spotlight (mockup-style) */
  .cc-gov-wrap {
    max-width: 1180px;
  }

  .cc-gov-head {
    display: grid;
    grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr);
    gap: 28px;
    align-items: center;
    margin-bottom: 24px;
  }

  .cc-gov-emblem {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #4a5a66;
    margin-bottom: 12px;
  }

  .cc-gov-emblem-ph {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px dashed #c5d5cf;
    background: #f3f8f6;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
  }

  .cc-gov-emblem-ph span {
    font-size: 8px;
    color: var(--mute);
    text-transform: none;
    letter-spacing: 0;
    font-weight: 500;
  }

  .cc-gov-title {
    font-size: clamp(30px, 4.2vw, 46px);
    line-height: 1.1;
    font-weight: 700;
    color: #152028;
    margin-bottom: 12px;
  }

  .cc-gov-intro {
    font-size: 15px;
    line-height: 1.65;
    color: #4f5d68;
    max-width: 560px;
  }

  .cc-gov-intro strong {
    color: #1f2a35;
  }

  .cc-gov-hero {
    margin: 0;
  }

  .cc-gov-hero-ph {
    width: 100%;
    aspect-ratio: 16/9;
    border-radius: 22px;
    border: 1px dashed #c6e3d9;
    background: linear-gradient(135deg, #edf7f3 0%, #e6f2ee 55%, #f8fbfa 100%);
    display: grid;
    place-items: center;
    min-height: 220px;
  }

  .cc-gov-hero-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-gov-hero-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 22px;
    object-fit: cover;
  }

  .cc-gov-features {
    background: #fff;
    border: 1px solid #dce6e2;
    border-radius: 16px;
    padding: 22px 20px 20px;
    margin-bottom: 14px;
  }

  .cc-gov-features h3 {
    font-size: 22px;
    font-weight: 700;
    color: #1a2731;
    margin-bottom: 18px;
  }

  .cc-gov-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
  }

  .cc-gov-item {
    text-align: center;
    padding: 0 8px;
  }

  .cc-gov-ico {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    margin: 0 auto 12px;
    background: #e8f5f1;
    border: 1px solid #cfe5dc;
    display: grid;
    place-items: center;
    color: var(--teal-700);
  }

  .cc-gov-ico svg {
    width: 30px;
    height: 30px;
  }

  .cc-gov-item h4 {
    font-size: 14px;
    font-weight: 700;
    color: #1f2b36;
    margin-bottom: 8px;
    line-height: 1.35;
  }

  .cc-gov-item p {
    font-size: 12.5px;
    line-height: 1.55;
    color: #5a6772;
  }

  .cc-gov-item p strong {
    color: #2f3a44;
  }

  .cc-gov-who {
    background: linear-gradient(135deg, #0d6b52 0%, var(--teal-700) 55%, #0a5d44 100%);
    border-radius: 16px;
    padding: 20px 22px;
    color: #fff;
  }

  .cc-gov-who-top {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, .18);
  }

  .cc-gov-who-top svg {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    margin-top: 2px;
  }

  .cc-gov-who-top p {
    font-size: 14px;
    line-height: 1.55;
    color: rgba(255, 255, 255, .92);
  }

  .cc-gov-who-top strong {
    font-weight: 700;
    color: #fff;
  }

  .cc-gov-who-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
  }

  .cc-gov-who-stat {
    display: flex;
    gap: 10px;
    align-items: flex-start;
  }

  .cc-gov-who-stat .ico {
    flex: 0 0 auto;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .14);
    display: grid;
    place-items: center;
  }

  .cc-gov-who-stat .ico svg {
    width: 22px;
    height: 22px;
  }

  .cc-gov-who-stat p {
    font-size: 12.5px;
    line-height: 1.5;
    color: rgba(255, 255, 255, .9);
  }

  .cc-gov-who-stat strong {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    display: block;
    line-height: 1.1;
    margin-bottom: 2px;
  }

  @media (max-width:1024px) {
    .cc-gov-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 20px;
    }

    .cc-gov-who-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width:880px) {
    .cc-gov-head {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width:560px) {
    .cc-gov-grid {
      grid-template-columns: 1fr;
    }

    .cc-gov-item {
      text-align: left;
      display: grid;
      grid-template-columns: 64px 1fr;
      gap: 12px;
      align-items: start;
      padding: 0;
    }

    .cc-gov-ico {
      margin: 0;
    }
  }

  /* FAQ spotlight (mockup-style) */
  .cc-faq-wrap {
    max-width: 1180px;
  }

  .cc-faq-layout {
    display: grid;
    grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
    gap: 36px;
    align-items: start;
  }

  .cc-faq-kicker {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--teal-700);
    margin-bottom: 10px;
  }

  .cc-faq-title {
    font-size: clamp(30px, 4vw, 44px);
    line-height: 1.12;
    font-weight: 700;
    color: #152028;
    margin-bottom: 10px;
  }

  .cc-faq-lead {
    font-size: 15px;
    line-height: 1.6;
    color: #5a6772;
    margin-bottom: 18px;
    max-width: 360px;
  }

  .cc-faq-visual {
    margin: 0 0 16px;
  }

  .cc-faq-img-ph {
    width: 100%;
    aspect-ratio: 4/3;
    border-radius: 18px;
    border: 1px dashed #c6e3d9;
    background: linear-gradient(135deg, #edf7f3 0%, #e8f3ef 55%, #f8fbfa 100%);
    display: grid;
    place-items: center;
    min-height: 200px;
  }

  .cc-faq-img-ph span {
    font-size: 12px;
    color: var(--mute);
  }

  .cc-faq-img {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 18px;
    object-fit: cover;
  }

  .cc-faq-callout {
    background: linear-gradient(135deg, #f0faf6 0%, var(--teal-50) 100%);
    border: 1px solid var(--teal-100);
    border-radius: 14px;
    padding: 14px 16px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }

  .cc-faq-callout svg {
    flex: 0 0 auto;
    width: 28px;
    height: 28px;
    color: var(--teal-700);
    margin-top: 2px;
  }

  .cc-faq-callout p {
    font-size: 13.5px;
    line-height: 1.55;
    color: #2f3d48;
  }

  .cc-faq-panel {
    background: #fff;
    border: 1px solid #dce6e2;
    border-radius: 16px;
    padding: 18px;
  }

  .cc-faq-search {
    position: relative;
    margin-bottom: 14px;
  }

  .cc-faq-search svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: var(--teal-700);
    pointer-events: none;
  }

  .cc-faq-search input {
    width: 100%;
    border: 1px solid #d5e3de;
    border-radius: 999px;
    padding: 12px 16px 12px 42px;
    font-size: 14px;
    color: #1f2a35;
    background: #f9fcfb;
  }

  .cc-faq-search input:focus {
    outline: none;
    border-color: var(--teal-500);
    box-shadow: 0 0 0 3px rgba(15, 155, 110, .12);
  }

  .cc-faq-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
  }

  .cc-faq-filter {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #cfe0d9;
    background: #fff;
    color: #2f3d48;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s, border-color .15s, color .15s;
  }

  .cc-faq-filter svg {
    width: 14px;
    height: 14px;
    color: var(--teal-700);
  }

  .cc-faq-filter.is-active {
    background: var(--teal-700);
    border-color: var(--teal-700);
    color: #fff;
  }

  .cc-faq-filter.is-active svg {
    color: #fff;
  }

  .cc-faq-list {
    border-top: 1px solid #e4ece9;
  }

  .cc-faq-item {
    border-bottom: 1px solid #e4ece9;
  }

  .cc-faq-item.is-hidden {
    display: none;
  }

  .cc-faq-item details summary {
    list-style: none;
    cursor: pointer;
    padding: 16px 4px;
    display: grid;
    grid-template-columns: 42px 1fr 24px;
    gap: 10px;
    align-items: center;
  }

  .cc-faq-item details summary::-webkit-details-marker {
    display: none
  }

  .cc-faq-num {
    font-size: 18px;
    font-weight: 700;
    color: var(--teal-700);
    line-height: 1;
  }

  .cc-faq-q {
    font-size: 15px;
    font-weight: 600;
    color: #1f2a35;
    line-height: 1.45;
  }

  .cc-faq-item .chev {
    transition: transform .2s ease;
    color: var(--teal-600);
  }

  .cc-faq-item details[open] .chev {
    transform: rotate(180deg);
  }

  .cc-faq-item .ans {
    padding: 0 4px 16px 52px;
    font-size: 14px;
    line-height: 1.65;
    color: #5a6772;
  }

  .cc-faq-empty {
    display: none;
    padding: 24px 8px;
    text-align: center;
    font-size: 14px;
    color: var(--mute);
  }

  .cc-faq-empty.is-visible {
    display: block;
  }

  @media (max-width:900px) {
    .cc-faq-layout {
      grid-template-columns: 1fr;
      gap: 24px;
    }

    .cc-faq-lead {
      max-width: none;
    }
  }

  @media (max-width:560px) {
    .cc-faq-item details summary {
      grid-template-columns: 36px 1fr 20px;
    }

    .cc-faq-item .ans {
      padding-left: 46px;
    }
  }

  /* Doctor cards */
  .cc-docs {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
  }

  .cc-doc {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    flex-direction: column;
  }

  .cc-doc-top {
    display: flex;
    gap: 14px;
    align-items: center;
    margin-bottom: 14px;
  }

  .cc-doc-av {
    flex: 0 0 auto;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: var(--teal-600);
    color: #fff;
    font-weight: 600;
    font-size: 16px;
  }

  .cc-doc-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--ink);
    line-height: 1.25;
  }

  .cc-doc-meta {
    font-size: 13px;
    color: var(--mute);
    margin-top: 3px;
  }

  .cc-doc-rating {
    font-size: 13px;
    color: var(--ink-2);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .cc-doc-rating .star {
    color: var(--amber)
  }

  .cc-doc-actions {
    margin-top: auto;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .cc-doc-actions a {
    flex: 1 1 auto;
    text-align: center;
    font-size: 13.5px;
    font-weight: 600;
    padding: 9px 12px;
    border-radius: 10px;
    text-decoration: none;
    border: 1px solid var(--line);
    color: var(--ink-2);
    transition: background .15s, border-color .15s;
  }

  .cc-doc-actions a:hover {
    background: var(--bg-2)
  }

  .cc-doc-actions a.is-primary {
    background: var(--teal-600);
    color: #fff;
    border-color: var(--teal-600);
  }

  .cc-doc-actions a.is-primary:hover {
    background: var(--teal-700)
  }

  .cc-docs-foot {
    margin-top: 28px;
    text-align: center;
  }

  /* Disclaimer */
  .cc-disc {
    font-size: 13px;
    color: var(--mute);
    line-height: 1.6;
    max-width: 820px;
    margin: 0 auto;
    text-align: center;
    padding-top: 8px;
  }

  /* References & sources (sits just above the site footer) */
  .cc-refs {
    background: var(--bg-2);
    border-top: 1px solid var(--line);
    padding: 40px 0;
  }

  .cc-refs h2 {
    font-size: 18px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
  }

  .cc-refs>.wrap>p {
    font-size: 14px;
    color: var(--mute);
    margin-bottom: 16px;
  }

  .cc-refs ul {
    list-style: none;
    display: grid;
    gap: 12px;
    max-width: 880px;
  }

  .cc-refs li {
    font-size: 14px;
    line-height: 1.6;
    color: var(--ink-2);
    padding-left: 18px;
    position: relative;
  }

  .cc-refs li::before {
    content: "›";
    position: absolute;
    left: 0;
    color: var(--teal-600);
    font-weight: 700;
  }

  .cc-refs em {
    color: var(--mute);
    font-style: italic;
  }

  .cc-refs a {
    color: var(--teal-700);
    text-decoration: none;
    font-weight: 500;
    word-break: break-word;
  }

  .cc-refs a:hover {
    text-decoration: underline;
  }

  @media (max-width:640px) {
    .cc-hero {
      padding: 60px 0 64px;
    }

    .cc-section {
      padding: 48px 0;
    }
  }






  .basics-section {
    max-width: 1440px;
    margin: 0 auto;
    padding: 48px 32px 0;
  }

  /* ── Top Row ────────────────────────────────── */
  .basics-row {
    display: flex;
    align-items: center;
    gap: 48px;
    margin-bottom: 36px;
  }

  /* Text Side */
  .basics-text {
    flex: 1 1 45%;
  }

  /* Label */
  .basics-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #1f6e3a;
    border-bottom: 2px solid #1f6e3a;
    display: inline-block;
    padding-bottom: 4px;
    margin-bottom: 16px;
  }

  /* Heading */
  .basics-heading {
    font-size: clamp(30px, 4.5vw, 52px);
    font-weight: 800;
    line-height: 1.1;
    color: #1a2e1a;
    margin-bottom: 18px;
  }

  .basics-heading-green {
    color: #1f6e3a;
  }

  /* Description */
  .basics-description {
    font-size: 14.5px;
    line-height: 1.75;
    color: #4a5e4a;
  }

  .basics-description strong {
    color: #1a2e1a;
    font-weight: 600;
  }

  /* Image Side */
  .basics-image {
    flex: 1 1 50%;
    display: flex;
    justify-content: flex-end;
  }

  .basics-image img {
    width: 100%;
    max-width: 520px;
    height: auto;
    object-fit: contain;
    border-radius: 20px;
    transition: transform 0.4s ease;
  }

  .basics-image img:hover {
    transform: scale(1.02);
  }

  /* ── Info Cards Row ─────────────────────────── */
  .info-cards-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
  }

  /* ── Info Card ──────────────────────────────── */
  .info-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e8ede8;
    padding: 20px 18px 20px 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 28px rgba(31, 110, 58, 0.13);
  }

  /* Left column: badge + illustration */
  .info-card-left {
    display: flex;
    flex-direction: column;
    align-items: left;
    gap: 10px;
    flex-shrink: 0;
  }

  /* Number badge */
  .info-num-badge {
    background-color: #1f6e3a;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.04em;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  /* Card illustration */
  .info-card-img {
    width: 100%;
    height: 180px;
    object-fit: contain;
    border-radius: 10px;
  }

  /* Right column: title + text */
  .info-card-content {
    flex: 1;
  }

  .info-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #1a2e1a;
    margin-bottom: 8px;
  }

  .info-card-text {
    font-size: 13px;
    line-height: 1.65;
    color: #4a5e4a;
  }

  .info-card-text strong {
    color: #1a2e1a;
    font-weight: 600;
  }

  /* ── Good News Banner ───────────────────────── */
  .good-news-banner {
    background: #f0f7f2;
    border: 1px solid #c8e6d0;
    border-radius: 16px;
    padding: 20px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 48px;
  }

  .good-news-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .good-news-icon {
    width: 44px;
    height: 44px;
    background: #fff;
    border: 1.5px solid #c8e6d0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .good-news-title {
    font-size: 14px;
    font-weight: 700;
    color: #1a2e1a;
    margin-bottom: 4px;
  }

  .good-news-text {
    font-size: 13.5px;
    color: #4a5e4a;
    line-height: 1.5;
  }

  .good-news-right-icon {
    flex-shrink: 0;
    opacity: 0.55;
  }

  /* ── Basics Responsive ──────────────────────── */
  @media (max-width: 900px) {
    .basics-row {
      flex-direction: column;
      gap: 24px;
    }

    .basics-text,
    .basics-image {
      flex: 1 1 100%;
    }

    .basics-image {
      justify-content: center;
    }

    .info-cards-row {
      grid-template-columns: 1fr;
    }
  }

  @media (min-width: 601px) and (max-width: 900px) {
    .info-cards-row {
      grid-template-columns: repeat(2, 1fr);
    }
  }





  .treated-section {
    max-width: 1440px;
    margin: 0 auto;
    padding: 48px 32px 64px;
  }

  /* ── Top Row ────────────────────────────────── */
  .treated-row {
    display: flex;
    align-items: center;
    gap: 48px;
    margin-bottom: 36px;
  }

  /* Text Side */
  .treated-text {
    flex: 1 1 50%;
  }

  /* Label */
  .treated-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #1f6e3a;
    border-bottom: 2px solid #1f6e3a;
    display: inline-block;
    padding-bottom: 4px;
    margin-bottom: 16px;
  }

  /* Heading */
  .treated-heading {
    font-size: clamp(26px, 4vw, 44px);
    font-weight: 800;
    line-height: 1.15;
    color: #1a2e1a;
    margin-bottom: 16px;
  }

  .treated-heading-green {
    color: #1f6e3a;
  }

  /* Description */
  .treated-description {
    font-size: 14.5px;
    line-height: 1.75;
    color: #4a5e4a;
    max-width: 440px;
  }

  /* Image Side */
  .treated-image {
    flex: 1 1 45%;
    display: flex;
    justify-content: flex-end;
  }

  .treated-image img {
    width: 100%;
    max-width: 500px;
    height: auto;
    object-fit: cover;
    border-radius: 20px;
    transition: transform 0.4s ease;
  }

  .treated-image img:hover {
    transform: scale(1.02);
  }

  /* ── Treatment Cards Row ────────────────────── */
  .treatment-cards-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 24px;
  }

  /* ── Treatment Card ─────────────────────────── */
  .treatment-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e8ede8;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .treatment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
  }

  /* Card Header */
  .tcard-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px 20px 16px;
    border-bottom: 1px solid #f0f0f0;
  }

  .tcard-header--green {
    background: #f4fbf6;
  }

  .tcard-header--orange {
    background: #fff8f4;
  }

  /* Header Icon circle */
  .tcard-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
  }

  .tcard-icon--green {
    background: #e0f5e9;
  }

  .tcard-icon--orange {
    background: #fde8d8;
  }

  /* Card Title */
  .tcard-title {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 5px;
  }

  .tcard-title--green {
    color: #1f6e3a;
  }

  .tcard-title--orange {
    color: #c85a00;
  }

  /* Card Subtitle */
  .tcard-subtitle {
    font-size: 13px;
    line-height: 1.55;
    color: #4a5e4a;
  }

  /* Card Body */
  .tcard-body {
    padding: 18px 20px 20px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
  }

  /* Bullet List */
  .tcard-list {
    flex: 1;
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 9px;
  }

  .tcard-list li {
    font-size: 13.5px;
    line-height: 1.5;
    color: #3a4e3a;
    padding-left: 18px;
    position: relative;
  }

  .tcard-list li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 7px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background-color: #1f6e3a;
  }

  #tcard-invasive .tcard-list li::before {
    background-color: #c85a00;
  }

  .tcard-list li strong {
    color: #1a2e1a;
    font-weight: 600;
  }

  /* Illustration */
  .tcard-illustration {
    flex-shrink: 0;
    width: 110px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .tcard-illus-img {
    width: 100%;
    height: auto;
    object-fit: contain;
    border-radius: 10px;
  }

  /* ── Treatment Bottom Banner ────────────────── */
  .treatment-banner {
    background: #f5f5f5;
    border-radius: 16px;
    padding: 20px 28px;
    display: flex;
    align-items: center;
  }

  .treatment-banner-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .treatment-banner-icon {
    width: 48px;
    height: 48px;
    background: #e0f5e9;
    border: 1.5px solid #c8e6d0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .treatment-banner-text {
    font-size: 14px;
    color: #3a4e3a;
    line-height: 1.55;
    margin-bottom: 3px;
  }

  .treatment-banner-highlight {
    font-size: 14px;
    font-weight: 700;
    color: #1f6e3a;
  }

  /* ── Treated Section Responsive ─────────────── */
  @media (max-width: 900px) {
    .treated-row {
      flex-direction: column;
      gap: 24px;
    }

    .treated-text,
    .treated-image {
      flex: 1 1 100%;
    }

    .treated-image {
      justify-content: center;
    }

    .treatment-cards-row {
      grid-template-columns: 1fr;
    }

    .tcard-body {
      flex-direction: column;
    }

    .tcard-illustration {
      width: 100%;
      max-width: 180px;
      margin: 0 auto;
    }
  }
</style>
<?php
$extraHead = ob_get_clean();

require __DIR__ . '/partials/header.php';
?>

<!-- ============================ HERO ============================ -->
<section class="cc-hero">
  <div class="wrap">
    <div class="cc-hero-text">
      <span class="cc-ribbon">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2c-2 3-2 6 0 9 2-3 2-6 0-9zm0 9c-1.5 4-4.5 7-8 9 4.5.5 7.5-1.5 8-4 .5 2.5 3.5 4.5 8 4-3.5-2-6.5-5-8-9z" />
        </svg>
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
    <div class="cc-hero-media">
      <div class="cc-video">
        <iframe
          src="https://www.youtube-nocookie.com/embed/X9ix-GQOX3U?rel=0"
          title="Cervical Cancer Awareness video"
          loading="lazy"
          frameborder="0"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
          referrerpolicy="strict-origin-when-cross-origin"
          allowfullscreen></iframe>
      </div>
    </div>
  </div>
</section>

<!-- ====================== WHAT IT IS ====================== -->
<!-- <section class="cc-section" id="what">
  <div class="wrap">
    <span class="cc-eyebrow">The basics</span>
    <h2 class="cc-h2">What is cervical cancer?</h2>
    <p class="cc-sub">Cervical cancer begins in the <strong>cervix</strong> — the lower, narrow part of the uterus (womb) that connects to the vagina. It happens when cells in the cervix begin to grow out of control. The good news: because it grows slowly and has clear warning signs under a microscope, it is one of the most preventable and, when caught early, most treatable cancers.</p>
    <div class="cc-pillars">
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 8v4" />
            <path d="M12 16h.01" />
          </svg></div>
        <h3>Where it forms</h3>
        <p>In the cervix — the “gateway” between the uterus and the vagina. Changes usually begin in the surface cells lining the cervix.</p>
      </div>
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0" />
            <path d="M9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0" />
          </svg></div>
        <h3>Main type</h3>
        <p><strong>Squamous cell carcinoma</strong> makes up most cases (up to ~90%); <strong>adenocarcinoma</strong> starts in the inner glandular cells.</p>
      </div>
      <div class="cc-pillar">
        <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m12 14 4-4" />
            <path d="M3.34 19a10 10 0 1 1 17.32 0" />
          </svg></div>
        <h3>The cause</h3>
        <p>Almost all cases are caused by long-lasting infection with high-risk <strong>HPV</strong> — making it the only cancer that a vaccine can prevent.</p>
      </div>
    </div>
  </div>
</section> -->

<section class="basics-section">

  <!-- Top Row: Text Left + Anatomy Image Right -->
  <div class="basics-row">
    <div class="basics-text">
      <p class="basics-label">THE BASICS</p>
      <h1 class="basics-heading">
        What is<br />
        <span class="basics-heading-green">Cervical Cancer?</span>
      </h1>
      <p class="basics-description">
        Cervical cancer begins in the <strong>cervix</strong> &mdash; the lower, narrow
        part of the uterus (womb) that connects to the vagina.
        It happens when cells in the cervix begin to grow
        out of control. The good news: because it grows slowly
        and has clear warning signs under a microscope,
        it is one of the most preventable and, when caught early,
        most treatable cancers.
      </p>
    </div>
    <div class="basics-image">
      <img src="/assets/img/eclinicpro-What-is-cervical-cancer.png"
        alt="Medical illustration of the uterus with a magnified view showing abnormal cells on the cervix"
        id="basics-img" />
    </div>
  </div>

  <!-- Info Cards Row: 01 Where it forms | 02 Main type | 03 The cause -->
  <div class="info-cards-row">

    <!-- Card 01: Where it forms -->
    <div class="info-card" id="info-card-where">
      <div class="info-card-left">
        <div class="info-num-badge">01</div>
        <img src="/assets/img/eclinicpro-Where-it-forms.png"
          alt="Illustration of the uterus highlighting where cervical cancer forms" class="info-card-img" />
      </div>
      <div class="info-card-content">
        <h3 class="info-card-title">Where it forms</h3>
        <p class="info-card-text">
          In the cervix &mdash; the
          &ldquo;gateway&rdquo; between
          the uterus and
          the vagina.
          Changes usually
          begin in the surface
          cells lining the cervix.
        </p>
      </div>
    </div>

    <!-- Card 02: Main type -->
    <div class="info-card" id="info-card-type">
      <div class="info-card-left">
        <div class="info-num-badge">02</div>
        <img src="/assets/img/eclinicpro-Main-type.png"
          alt="Microscopic illustration of abnormal squamous cells in cervical cancer" class="info-card-img" />
      </div>
      <div class="info-card-content">
        <h3 class="info-card-title">Main type</h3>
        <p class="info-card-text">
          Squamous cell
          carcinoma makes
          up most cases
          (up to ~90%);
          adenocarcinoma
          starts in the inner
          glandular cells.
        </p>
      </div>
    </div>

    <!-- Card 03: The cause -->
    <div class="info-card" id="info-card-cause">
      <div class="info-card-left">
        <div class="info-num-badge">03</div>
        <img src="/assets/img/eclinipro-The-cause.png" alt="3D illustration of the HPV virus" class="info-card-img" />
      </div>
      <div class="info-card-content">
        <h3 class="info-card-title">The cause</h3>
        <p class="info-card-text">
          Almost all cases are
          caused by long-lasting
          infection with high-risk
          <strong>HPV</strong> &mdash; making it the
          only cancer that a
          vaccine can prevent.
        </p>
      </div>
    </div>

  </div><!-- /info-cards-row -->

  <!-- Good News Banner -->
  <div class="good-news-banner" id="good-news-banner">
    <div class="good-news-left">
      <div class="good-news-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
          stroke="#1f6e3a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          <polyline points="9 12 11 14 15 10" />
        </svg>
      </div>
      <div>
        <p class="good-news-title">The good news</p>
        <p class="good-news-text">Regular screening, HPV vaccination, and early treatment can prevent most cervical
          cancers.</p>
      </div>
    </div>
    <div class="good-news-right-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1f6e3a"
        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path
          d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
        <path d="M12 21.7C9.5 20 5 17 5 13" />
        <path d="M12 21.7C14.5 20 19 17 19 13" />
      </svg>
    </div>
  </div>

</section>


<!-- ====================== SYMPTOMS ====================== -->
<!-- <section class="cc-section" id="symptoms">
  <div class="wrap">
    <span class="cc-eyebrow">Warning signs</span>
    <h2 class="cc-h2">Symptoms to watch for</h2>
    <p class="cc-sub"><strong>Early cervical cancer often has no symptoms at all</strong> — which is why regular screening is so important. When symptoms do appear, they should always be checked by a gynecologist. Having these signs does not mean you have cancer, but never ignore them.</p>
    <div class="cc-two">
      <div class="cc-card cc-card-warn">
        <h3>Early signs</h3>
        <ul>
          <li>Bleeding between periods</li>
          <li>Bleeding after intercourse</li>
          <li>Bleeding after menopause</li>
          <li>Unusual or foul-smelling vaginal discharge</li>
          <li>Pain during intercourse</li>
        </ul>
      </div>
      <div class="cc-card cc-card-alert">
        <h3>Advanced signs</h3>
        <ul>
          <li>Persistent pelvic, back or leg pain</li>
          <li>Swelling in the legs</li>
          <li>Unexplained weight loss, fatigue or loss of appetite</li>
          <li>Difficulty passing urine or stool</li>
        </ul>
      </div>
    </div>
    <p class="cc-note">If you notice any of these — especially abnormal bleeding or discharge — see a gynecologist promptly. Early detection saves lives. Source: WHO. See <a href="#references" style="color:var(--teal-700);">references</a>.</p>
  </div>
</section> -->

<!-- ====================== HOW IT STARTS / DEVELOPS ====================== -->
<section class="cc-section cc-section-alt" id="how">
  <div class="wrap cc-how-wrap">
    <div class="cc-how-head">
      <div>
        <span class="cc-how-kicker">How it starts</span>
        <h2 class="cc-how-title">From a common infection to cancer — <span class="cc-highlight">over many years</span></h2>
        <p class="cc-how-intro">Cervical cancer does not appear overnight. It usually takes <strong>15–20 years</strong> for abnormal cells to slowly turn into cancer (faster — about 5–10 years — in women with weak immunity, such as untreated HIV). That long window is exactly why screening works: it catches the warning changes long before cancer forms.</p>
      </div>
      <figure class="cc-how-anatomy">
        <!-- Replace placeholder: <img class="cc-how-anatomy-img" src="/path/to/uterus-cervix.jpg" alt="Uterus and cervix anatomy" loading="lazy"> -->
        <div class="cc-how-anatomy-ph" role="img" aria-label="Anatomy illustration — image coming soon">
          <!-- <span>Anatomy image — add later</span> -->
          <img src="assets/img/eclinicpro-Cervix.png" alt="Uterus and cervix anatomy" loading="lazy">
        </div>
        <div class="cc-how-cervix-callout"><strong>Cervix</strong> — the lower part of the uterus that connects to the vagina.</div>
      </figure>
    </div>

    <div class="cc-how-track">
      <article class="cc-how-card">
        <figure class="cc-how-card-media">
          <div class="cc-how-card-ph" role="img" aria-label="HPV virus — add later"> <img src="assets/img/eclinicpro-HPV-infection.png" alt="HPV virus" loading="lazy">
          </div>
        </figure>
        <h3>HPV infection</h3>
        <p>A high-risk HPV type infects the cervix, usually through sexual contact. In most women, immunity clears it within ~2 years with no harm.</p>
        <span class="cc-how-time">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
          </svg>0 – 2 years</span>
      </article>
      <div class="cc-how-arrow" aria-hidden="true">→</div>
      <article class="cc-how-card">
        <figure class="cc-how-card-media">
          <div class="cc-how-card-ph" role="img" aria-label="Persistent infection — add later"> <img src="assets/img/eclinicpro-Persistent-infection.png" alt="Persistent infection" loading="lazy">
          </div>
        </figure>
        <h3>Persistent infection</h3>
        <p>In some women the high-risk infection does not clear and lingers for years — this is what drives the risk.</p>
        <span class="cc-how-time"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
          </svg>2 – 10 years</span>
      </article>
      <div class="cc-how-arrow" aria-hidden="true">→</div>
      <article class="cc-how-card">
        <figure class="cc-how-card-media">
          <div class="cc-how-card-ph" role="img" aria-label="Pre-cancer cells — add later"> <img src="assets/img/eclinicpro-Pre-cancer.png" alt="Pre-cancer cells" loading="lazy">
          </div>
        </figure>
        <h3>Pre-cancer (dysplasia)</h3>
        <p>Cells start to change abnormally but are <em>not yet cancer</em>. These changes are picked up by a Pap smear — and can be simply treated.</p>
        <span class="cc-how-time"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
          </svg>10 – 15 years</span>
      </article>
      <div class="cc-how-arrow" aria-hidden="true">→</div>
      <article class="cc-how-card">
        <figure class="cc-how-card-media">
          <div class="cc-how-card-ph" role="img" aria-label="Cervical cancer — add later"> <img src="assets/img/eclinicpro-Cervical-cancer.png" alt="Cervical cancer" loading="lazy">
          </div>
        </figure>
        <h3>Cervical cancer</h3>
        <p>If pre-cancer is not found and treated, abnormal cells can become cancer and grow into deeper tissue over time.</p>
        <span class="cc-how-time"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M16 2v4M8 2v4M3 10h18" />
          </svg>15 – 20+ years</span>
      </article>
    </div>

    <div class="cc-how-bar" aria-hidden="true"></div>
    <div class="cc-how-bar-labels" aria-hidden="true">
      <span>0–2 yrs</span>
      <span>2–10 yrs</span>
      <span>10–15 yrs</span>
      <span>15–20+ yrs</span>
    </div>

    <div class="cc-how-footer">
      <div class="cc-how-footer-head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <h3>The good news</h3>
      </div>
      <p class="cc-how-footer-text">Regular screening (Pap smear/HPV test), HPV vaccination, and early treatment can prevent almost all cervical cancers.</p>
      <div class="cc-how-footer-pills">
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="m19 5-7 7-7-7" />
          </svg>HPV Vaccination</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M10 2v7.31" />
            <path d="M14 9.3V2" />
            <path d="M8.5 2h7" />
            <path d="M14 9.3a6.5 6.5 0 1 1-4 0" />
          </svg>Regular Screening</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 8v8" />
            <path d="M8 12h8" />
          </svg>Early Detection</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
          </svg>Timely Treatment</span>
      </div>
    </div>

    <p class="cc-note" style="margin-top:12px;">Source: WHO &amp; National Cancer Institute (NCI). See <a href="#references" style="color:var(--teal-700);">references</a>.</p>
  </div>
</section>

<!-- ====================== RISK & AGE ====================== -->
<section class="cc-section cc-section-alt" id="risk">
  <div class="wrap cc-risk-wrap">
    <div class="cc-risk-head">
      <div>
        <span class="cc-risk-kicker">Who &amp; when</span>
        <h2 class="cc-risk-title">Who is at risk, and <span class="cc-highlight">which ages matter</span></h2>
        <p class="cc-risk-intro">Any woman with a cervix can develop cervical cancer, but some factors increase the risk. It occurs most often in women <strong>over the age of 30</strong> — which is why screening is advised from then on, while vaccination is given much earlier, before exposure.</p>
      </div>
      <figure class="cc-risk-hero">
        <div class="cc-risk-hero-ph" role="img" aria-label="Uterus model illustration — image coming soon">
          <img src="/assets/img/eclinicpro-Who-When.png" alt="Uterus model illustration" loading="lazy">
        </div>
      </figure>
    </div>

    <div class="cc-risk-cols">
      <article class="cc-risk-panel cc-risk-panel--factors">
        <div class="cc-risk-panel-head">
          <div class="cc-risk-panel-ico cc-risk-panel-ico--green" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 9v4" />
              <path d="M12 17h.01" />
              <path d="m10.29 3.86-8.5 14.74A2 2 0 0 0 3.47 22h17.06a2 2 0 0 0 1.68-3.4l-8.5-14.74a2 2 0 0 0-3.42 0z" />
            </svg>
          </div>
          <h3>Higher-risk factors</h3>
        </div>
        <ul class="cc-risk-factors">
          <li>
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
              </svg></span>
            Long-lasting high-risk HPV infection
          </li>
          <li>
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 12H2" />
                <path d="M22 12h-4" />
                <path d="M7 12v4" />
                <path d="M13 8v8" />
              </svg></span>
            Smoking
          </li>
          <li>
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <path d="M12 8v8" />
                <path d="M8 12h8" />
              </svg></span>
            Weak immunity (e.g., untreated HIV)
          </li>
          <li>
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="9" r="3" />
                <circle cx="15" cy="15" r="3" />
                <path d="M11.5 11.5 12.5 12.5" />
              </svg></span>
            Unprotected sex / multiple partners
          </li>
          <li>
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="5" y="4" width="14" height="16" rx="2" />
                <path d="M9 9h6M9 13h6" />
              </svg></span>
            Never having a Pap smear or HPV test
          </li>
          <li>
            <span class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="4" />
                <path d="M6 20v-1a6 6 0 0 1 12 0v1" />
              </svg></span>
            Starting sexual activity at a very young age
          </li>
        </ul>
      </article>

      <article class="cc-risk-panel cc-risk-panel--ages">
        <div class="cc-risk-panel-head">
          <div class="cc-risk-panel-ico cc-risk-panel-ico--orange" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" />
              <path d="M16 2v4M8 2v4M3 10h18" />
            </svg>
          </div>
          <h3>Which ages matter most</h3>
        </div>
        <ol class="cc-risk-timeline">
          <li>
            <span class="dot" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="4" />
                <path d="M6 20v-1a6 6 0 0 1 12 0v1" />
              </svg></span>
            <span><strong>9–14 yrs:</strong> best age for the HPV vaccine — before exposure</span>
          </li>
          <li>
            <span class="dot" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              </svg></span>
            <span><strong>15–26 yrs:</strong> catch-up vaccination if not done earlier</span>
          </li>
          <li>
            <span class="dot" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
              </svg></span>
            <span><strong>30+ yrs:</strong> begin regular screening (Pap / HPV DNA)</span>
          </li>
          <li>
            <span class="dot" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 3v18h18" />
                <path d="m7 14 4-4 3 3 5-6" />
              </svg></span>
            <span><strong>30–65 yrs:</strong> the years cervical cancer is most common — keep screening</span>
          </li>
        </ol>
      </article>
    </div>

    <div class="cc-risk-banner">
      <div class="cc-risk-banner-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
      <p><strong>The good news:</strong> Cervical cancer is preventable. HPV vaccination, healthy choices, and regular screening can save lives.</p>
      <figure class="cc-risk-banner-visual">
        <div class="cc-risk-banner-ph" role="img" aria-label="HPV vaccine image — add later">
          <span>Banner image — add later</span>
        </div>
      </figure>
    </div>
  </div>
</section>

<!-- ====================== TREATMENT ====================== -->
<!-- <section class="cc-section" id="treatment">
  <div class="wrap cc-treat-wrap">
    <div class="cc-treat-head">
      <div>
        <span class="cc-treat-kicker">If something is found</span>
        <h2 class="cc-treat-title">How cervical cancer is <span class="cc-highlight">treated</span></h2>
        <p class="cc-treat-intro">Treatment depends on the stage, cell type, and your personal health. When caught early, cervical cancer is highly treatable and many women go on to live long, healthy lives.</p>
      </div>
      <figure class="cc-treat-hero">
        <div class="cc-treat-hero-ph" role="img" aria-label="Doctor and patient — image coming soon">
          <img src="/assets/img/eclinicpro-How-cervical-cancer-is-treated.png" alt="Doctor and patient" loading="lazy">
        </div>
      </figure>
    </div>

    <div class="cc-treat-cards">
      <article class="cc-treat-card cc-treat-card--pre">
        <div class="cc-treat-card-top">
          <div class="cc-treat-ico cc-treat-ico--pre" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <div>
            <h3>Pre-cancer (early treatment)</h3>
            <p class="cc-treat-sub">Abnormal cells can be removed before they turn into cancer — simple, safe, and effective.</p>
          </div>
        </div>
        <ul class="cc-treat-list">
          <li><strong>Cryotherapy:</strong> Freeze abnormal cells</li>
          <li><strong>LEEP / LLETZ:</strong> Remove abnormal tissue using a thin wire loop</li>
          <li><strong>Cone biopsy:</strong> Remove a cone-shaped piece of tissue</li>
          <li><strong>Close follow-up</strong></li>
        </ul>
        <figure class="cc-treat-illus">
          <div class="cc-treat-illus-ph" role="img" aria-label="Pre-cancer illustration — add later">
            <img src="/assets/img/eclinicpro-Pre-cancer-(early treatment) .png" alt="Pre-cancer on cervix diagram" loading="lazy">

          </div>
        </figure>
      </article>

      <article class="cc-treat-card cc-treat-card--inv">
        <div class="cc-treat-card-top">
          <div class="cc-treat-ico cc-treat-ico--inv" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              <path d="M12 8v8" />
              <path d="M8 12h8" />
            </svg>
          </div>
          <div>
            <h3>Invasive cancer</h3>
            <p class="cc-treat-sub">Treatment depends on the stage. It’s planned by a specialist team to give the best outcome.</p>
          </div>
        </div>
        <ul class="cc-treat-list">
          <li><strong>Surgery:</strong> To remove the cancer</li>
          <li><strong>Radiotherapy:</strong> High-energy rays to kill cancer cells</li>
          <li><strong>Chemotherapy:</strong> Medicines that kill cancer cells</li>
          <li><strong>Targeted therapy:</strong> Drugs that target specific genes or proteins</li>
          <li><strong>Immunotherapy:</strong> Helps your immune system fight cancer</li>
        </ul>
        <figure class="cc-treat-illus">
          <div class="cc-treat-illus-ph" role="img" aria-label="Invasive cancer illustration — add later">
            <img src="/assets/img/eclinicpro-Invasive-cancer.png" alt="Invasive cancer on cervix diagram" loading="lazy">
          </div>
        </figure>
      </article>
    </div>

    <div class="cc-treat-footer">
      <div class="cc-treat-footer-ico" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
          <path d="M12 8v8" />
          <path d="M8 12h8" />
        </svg>
      </div>
      <p>Every woman’s journey is unique. Your doctor will recommend the right treatment for you. <strong>Early detection makes all the difference.</strong></p>
    </div>
  </div>
</section> -->

<section class="treated-section">

  <!-- Top Row: Text Left + Doctor Image Right -->
  <div class="treated-row">
    <div class="treated-text">
      <p class="treated-label">IF SOMETHING IS FOUND</p>
      <h2 class="treated-heading">
        How cervical cancer is <span class="treated-heading-green">treated</span>
      </h2>
      <p class="treated-description">
        Treatment depends on the stage, cell type, and your personal health.
        When caught early, cervical cancer is highly treatable and many women
        go on to live long, healthy lives.
      </p>
    </div>
    <div class="treated-image">
      <img
        src="/assets/img/eclinicpro-How-cervical-cancer-is-treated.png"
        alt="Doctor consulting with a female patient about cervical cancer treatment options"
        id="treated-img" />
    </div>
  </div>

  <!-- Two Treatment Cards -->
  <div class="treatment-cards-row">

    <!-- Card A: Pre-cancer -->
    <div class="treatment-card" id="tcard-precancer">
      <div class="tcard-header tcard-header--green">
        <div class="tcard-header-icon tcard-icon--green">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1f6e3a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <polyline points="9 12 11 14 15 10" />
          </svg>
        </div>
        <div>
          <h3 class="tcard-title tcard-title--green">Pre-cancer (early treatment)</h3>
          <p class="tcard-subtitle">Abnormal cells can be removed before they turn into cancer &mdash; simple, safe, and effective.</p>
        </div>
      </div>
      <div class="tcard-body">
        <ul class="tcard-list">
          <li><strong>Cryotherapy:</strong> Freeze abnormal cells</li>
          <li><strong>LEEP / LLETZ:</strong> Remove abnormal tissue using a thin wire loop</li>
          <li><strong>Cone biopsy:</strong> Remove a cone-shaped piece of tissue</li>
          <li><strong>Close follow-up</strong></li>
        </ul>
        <div class="tcard-illustration">
          <img
            src="assets/img/eclinicpro-pre-cancer-bgremove.png"
            alt="Illustration of early cervical cancer cells in the cervix"
            class="tcard-illus-img" />
        </div>
      </div>
    </div>

    <!-- Card B: Invasive cancer -->
    <div class="treatment-card" id="tcard-invasive">
      <div class="tcard-header tcard-header--orange">
        <div class="tcard-header-icon tcard-icon--orange">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c85a00" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <line x1="12" y1="8" x2="12" y2="16" />
            <line x1="8" y1="12" x2="16" y2="12" />
          </svg>
        </div>
        <div>
          <h3 class="tcard-title tcard-title--orange">Invasive cancer</h3>
          <p class="tcard-subtitle">Treatment depends on the stage. It&rsquo;s planned by a specialist team to give the best outcome.</p>
        </div>
      </div>
      <div class="tcard-body">
        <ul class="tcard-list">
          <li><strong>Surgery:</strong> To remove the cancer</li>
          <li><strong>Radiotherapy:</strong> High-energy rays to kill cancer cells</li>
          <li><strong>Chemotherapy:</strong> Medicines that kill cancer cells</li>
          <li><strong>Targeted therapy:</strong> Drugs that target specific genes or proteins</li>
          <li><strong>Immunotherapy:</strong> Helps your immune system fight cancer</li>
        </ul>
        <div class="tcard-illustration">
          <img
            src="assets/img/eclinicpro-Invasive-cancer.png"
            alt="Illustration of advanced cervical cancer spread in the uterus"
            class="tcard-illus-img" />
        </div>
      </div>
    </div>

  </div><!-- /treatment-cards-row -->

  <!-- Bottom Banner -->
  <div class="treatment-banner" id="treatment-banner">
    <div class="treatment-banner-left">
      <div class="treatment-banner-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1f6e3a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
          <path d="M12 21.7C9.5 20 5 17 5 13" />
          <path d="M12 21.7C14.5 20 19 17 19 13" />
        </svg>
      </div>
      <div>
        <p class="treatment-banner-text">
          Every woman&rsquo;s journey is unique. Your doctor will recommend the right treatment for you.
        </p>
        <p class="treatment-banner-highlight">Early detection makes all the difference.</p>
      </div>
    </div>
  </div>

</section>



<!-- ====================== FIGURES & FACTS ====================== -->
<section class="cc-section cc-section-alt" id="facts">
  <div class="wrap cc-facts-wrap">
    <div class="cc-facts-head">
      <div class="cc-facts-intro">
        <span class="cc-eyebrow cc-eyebrow-line">Figures &amp; Facts</span>
        <h2 class="cc-h2">The numbers <span class="cc-highlight">India</span> can’t ignore</h2>
        <p class="cc-sub">Worldwide, cervical cancer caused around 660,000 new cases and 350,000 deaths in 2022 (WHO). In India it is the second most common cancer among women — yet it is one of the most preventable. These figures are drawn from WHO, GLOBOCAN 2022 and Government of India (PIB) public health sources.</p>
      </div>
      <figure class="cc-facts-hero">
        <!-- Replace placeholder with your image: <img class="cc-facts-img" src="/path/to/facts-hero.jpg" alt="Indian women — cervical cancer awareness" loading="lazy"> -->
        <div class="cc-facts-img-ph" role="img" aria-label="Awareness illustration — image coming soon">
          <img src="/assets/img/eclinicpro-Figures&Facts.png" alt="Indian women — cervical cancer awareness" loading="lazy">
          <!-- <span>Hero image — add later</span> -->
        </div>
      </figure>
    </div>

    <div class="cc-facts-grid">
      <div class="cc-stat">
        <div class="cc-stat-ico" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <circle cx="20" cy="20" r="20" fill="#fce7f3" /><text x="20" y="27" text-anchor="middle" font-size="20" font-weight="700" fill="#db2777">♀</text>
          </svg>
        </div>
        <div class="cc-stat-num">2<span>nd</span></div>
        <div class="cc-stat-label">most common cancer among women in India.</div>
        <div class="cc-stat-src">Source: GLOBOCAN 2022 / PIB</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-ico" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <rect width="40" height="40" rx="10" fill="#fef2f2" />
            <rect x="8" y="22" width="5" height="10" rx="1" fill="#f87171" />
            <rect x="15" y="16" width="5" height="16" rx="1" fill="#ef4444" />
            <rect x="22" y="10" width="5" height="22" rx="1" fill="#dc2626" />
            <path d="M28 8l4 4-2 2" stroke="#dc2626" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="cc-stat-num">1.2<span>lakh+</span></div>
        <div class="cc-stat-label">new cases and nearly 80,000 deaths in India every year.</div>
        <div class="cc-stat-src">Source: GLOBOCAN 2022</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-ico" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <rect width="40" height="40" rx="10" fill="#e0f2f1" />
            <circle cx="12" cy="14" r="4" fill="#0d9488" />
            <path d="M6 26c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="#0d9488" stroke-width="2" stroke-linecap="round" />
            <circle cx="22" cy="12" r="3.5" fill="#ec4899" />
            <path d="M17 24c0-2.8 2.2-5 5-5s5 2.2 5 5" stroke="#ec4899" stroke-width="1.8" stroke-linecap="round" />
            <circle cx="30" cy="14" r="3.5" fill="#0d9488" />
            <path d="M25 26c0-2.8 2.2-5 5-5s5 2.2 5 5" stroke="#0d9488" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </div>
        <div class="cc-stat-num">25<span>%</span></div>
        <div class="cc-stat-label">of the world’s cervical cancer deaths occur in India — 1 in 5 patients globally is Indian.</div>
        <div class="cc-stat-src">Source: WHO / PIB</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-ico" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <rect width="40" height="40" rx="10" fill="#fce7f3" />
            <circle cx="20" cy="20" r="9" fill="#f9a8d4" opacity=".5" />
            <circle cx="20" cy="20" r="5" fill="#ec4899" />
            <path d="M14 14c2-1 4-1 6 0M26 26c-2 1-4 1-6 0" stroke="#be185d" stroke-width="1.2" stroke-linecap="round" />
            <circle cx="17" cy="18" r="1" fill="#be185d" />
            <circle cx="23" cy="22" r="1" fill="#be185d" />
          </svg>
        </div>
        <div class="cc-stat-num">80<span>%+</span></div>
        <div class="cc-stat-label">of India’s cases are caused by high-risk HPV types 16 &amp; 18.</div>
        <div class="cc-stat-src">Source: PIB</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-ico" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <rect width="40" height="40" rx="10" fill="#e0f2f1" />
            <path d="M20 6l12 4v10c0 7.5-5.2 14.5-12 16-6.8-1.5-12-8.5-12-16V10l12-4z" fill="#0d9488" />
            <path d="M15 18l3.5 3.5L26 14" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="cc-stat-num">93–100<span>%</span></div>
        <div class="cc-stat-label">effectiveness of a Gardasil-4 dose against the HPV types it covers.</div>
        <div class="cc-stat-src">Source: PIB, 2026</div>
      </div>
      <div class="cc-stat">
        <div class="cc-stat-ico" aria-hidden="true">
          <svg viewBox="0 0 40 40" fill="none">
            <rect width="40" height="40" rx="10" fill="#fef9c3" />
            <circle cx="16" cy="16" r="7" fill="#fde68a" />
            <path d="M11 24c0-2.8 2.2-5 5-5s5 2.2 5 5" fill="#fde68a" />
            <circle cx="16" cy="15" r="1.2" fill="#92400e" />
            <path d="M14 17.5c.8.5 1.7.5 2.5 0" stroke="#92400e" stroke-width=".8" stroke-linecap="round" />
            <path d="M26 10l8 4v6c0 4-2.8 7.8-6 9-3.2-1.2-6-5-6-9v-6l6-2.5" fill="#0d9488" opacity=".85" />
            <path d="M27 16l1.5 1.5 3-3" stroke="#fff" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="cc-stat-num">1.15<span>crore</span></div>
        <div class="cc-stat-label">girls aged 14 to be vaccinated free under India’s 2026 campaign.</div>
        <div class="cc-stat-src">Source: PIB, Feb 2026</div>
      </div>

      <aside class="cc-remember">
        <div class="cc-remember-ico" aria-hidden="true">
          <svg viewBox="0 0 44 44" fill="none">
            <circle cx="22" cy="22" r="22" fill="#fef9c3" />
            <path d="M22 8c-1 4-4 6-7 7 1 5 3 8 7 9 4-1 6-4 7-9-3-1-6-3-7-7z" fill="#facc15" />
            <rect x="19" y="24" width="6" height="3" rx="1" fill="#0d9488" />
            <rect x="17" y="27" width="10" height="4" rx="2" fill="#0d9488" />
          </svg>
        </div>
        <div>
          <h3>Remember</h3>
          <p class="cc-remember-lead">Prevention today, protection for life.</p>
          <p class="cc-remember-tags">Vaccination &bull; Screening &bull; Awareness</p>
          <p class="cc-remember-end">Together we can <strong>end cervical cancer.</strong></p>
        </div>
      </aside>
    </div>
  </div>
</section>

<!-- ====================== HOW TO PREVENT ====================== -->
<section class="cc-section" id="prevention">
  <div class="wrap cc-prev-wrap">
    <div class="cc-prev-head">
      <div>
        <span class="cc-prev-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Three ways to protect
        </span>
        <h2 class="cc-prev-title">Prevention works —<br><span class="cc-highlight">and it’s within reach</span></h2>
        <p class="cc-prev-intro">Cervical cancer can be almost completely prevented when caught in time. It rests on <strong>three simple steps</strong> — each one saves lives.</p>
      </div>
      <figure class="cc-prev-hero">
        <!-- Replace placeholder with your top image: <img class="cc-prev-hero-img" src="/path/to/prevention-top.jpg" alt="Prevention awareness visual" loading="lazy"> -->
        <div class="cc-prev-hero-ph" role="img" aria-label="Prevention hero image coming soon">
          <img src="/assets/img/eclinicpro-Three-ways-to-protect.png" alt="Prevention awareness visual" loading="lazy">
          <!-- <span>Top hero image - add later</span> -->
        </div>
      </figure>
    </div>

    <div class="cc-prev-cards">
      <article class="cc-prev-card">
        <figure class="cc-prev-card-media">
          <!-- Replace placeholder with card image -->
          <div class="cc-prev-card-ph" role="img" aria-label="Vaccination image coming soon">
            <img src="/assets/img/eclinicpro-Vaccinate.png" alt="Vaccination image" loading="lazy">
            <!-- <span>Card image 1 - add later</span> -->
          </div>
        </figure>
        <div class="cc-prev-card-body">
          <div class="cc-prev-icon" aria-hidden="true">
            <!-- <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
            </svg> -->
          </div>
          <h3><span>1.</span> Vaccinate</h3>
          <p>The HPV vaccine protects against the virus types that cause most cervical cancers. It is most effective when given before sexual activity begins — girls aged 9–14 need just 2 doses.</p>
        </div>
      </article>

      <article class="cc-prev-card">
        <figure class="cc-prev-card-media">
          <!-- Replace placeholder with card image -->
          <div class="cc-prev-card-ph" role="img" aria-label="Screening consultation image coming soon">
            <img src="/assets/img/eclinicpro-Screen.png" alt="Screening consultation image" loading="lazy">
            <!-- <span>Card image 2 - add later</span> -->
          </div>
        </figure>
        <div class="cc-prev-card-body">
          <div class="cc-prev-icon" aria-hidden="true">
            <!-- <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="m21 21-4.35-4.35" />
              <circle cx="11" cy="11" r="7" />
              <path d="M11 8v6" />
              <path d="M8 11h6" />
            </svg> -->
          </div>
          <h3><span>2.</span> Screen</h3>
          <p>A Pap smear (every 3 years) or HPV DNA test (every 5 years) from age 30 detects abnormal cells years before cancer forms — even after vaccination.</p>
        </div>
      </article>

      <article class="cc-prev-card">
        <figure class="cc-prev-card-media">
          <!-- Replace placeholder with card image -->
          <div class="cc-prev-card-ph" role="img" aria-label="Early treatment image coming soon">
            <img src="/assets/img/eclinicpro-Treat-earlyy.png" alt="Early treatment image" loading="lazy">
            <!-- <span>Card image 3 - add later</span> -->
          </div>
        </figure>
        <div class="cc-prev-card-body">
          <div class="cc-prev-icon" aria-hidden="true">
            <!-- <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 21c4.97 0 9-4.03 9-9s-4.03-9-9-9-9 4.03-9 9 4.03 9 9 9z" />
              <path d="M9 12h6" />
              <path d="M12 9v6" />
            </svg> -->
          </div>
          <h3><span>3.</span> Treat early</h3>
          <p>When changes are found early, treatment is simple and highly effective. Don’t wait for symptoms — by then, the disease may be advanced.</p>
        </div>
      </article>
    </div>

    <div class="cc-prev-bar">
      <div class="cc-prev-bar-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 18h6" />
          <path d="M10 22h4" />
          <path d="M12 2a7 7 0 0 0-4 12.75c.54.4 1 1.02 1 1.75V18h6v-1.5c0-.73.46-1.35 1-1.75A7 7 0 0 0 12 2z" />
        </svg>
        <span>Remember:</span>
      </div>
      <div class="cc-prev-links">
        <span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="m19 5-7 7-7-7" />
            <path d="m12 12 7 7" />
          </svg>
          Vaccinate</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7" />
            <path d="m21 21-4.3-4.3" />
          </svg>Screen</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
          </svg>Protect &amp; Care</span>
      </div>
      <div class="cc-prev-bar-end">A healthy tomorrow starts today.<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="m12 21-1.45-1.32C5.4 15.36 2 12.28 2 8.5A4.5 4.5 0 0 1 6.5 4 5.2 5.2 0 0 1 12 7.09 5.2 5.2 0 0 1 17.5 4 4.5 4.5 0 0 1 22 8.5c0 3.78-3.4 6.86-8.55 11.18z" />
        </svg></div>
    </div>
  </div>
</section>

<!-- ================= TESTS & SCREENING ================= -->
<section class="cc-section cc-tests" id="tests">
  <div class="wrap cc-tests-wrap">
    <div class="cc-tests-head">
      <div>
        <span class="cc-tests-kicker">Tests &amp; screening</span>
        <h2 class="cc-tests-title">The tests that<br>catch it <span class="cc-highlight">early</span></h2>
        <p class="cc-tests-intro">Screening can find abnormal cells many years before cancer develops. These are the tests used in India — simple, quick, and far cheaper than treating advanced disease.</p>
      </div>
      <figure class="cc-tests-hero">
        <!-- Replace placeholder with your top image: <img class="cc-tests-hero-img" src="/path/to/tests-hero.jpg" alt="Doctor discussing cervical screening" loading="lazy"> -->
        <div class="cc-tests-hero-ph" role="img" aria-label="Tests section hero image coming soon">
          <img src="/assets/img/eclinicpro-Tests&screening.png" alt="Tests section hero image" loading="lazy">
          <!-- <span>Top hero image - add later</span> -->
        </div>
      </figure>
    </div>

    <div class="cc-tests-cards">
      <article class="cc-tests-card">
        <figure class="cc-tests-card-media">
          <!-- Replace placeholder with card image -->
          <div class="cc-tests-card-ph" role="img" aria-label="Pap smear image coming soon"><span>Card image 1 - add later</span></div>
        </figure>
        <div class="cc-tests-card-body">
          <div class="cc-tests-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 2v7.31" />
              <path d="M14 9.3V2" />
              <path d="M8.5 2h7" />
              <path d="M14 9.3a6.5 6.5 0 1 1-4 0" />
              <path d="M5.58 16.5h12.85" />
            </svg></div>
          <h3>Pap smear</h3>
          <p>A gynecologist gently collects a few cells from the cervix with a small brush (5–10 minutes). It’s painless — only mild discomfort for a few seconds — and finds early abnormal changes. From age 30, every 3 years.</p>
        </div>
      </article>
      <article class="cc-tests-card">
        <figure class="cc-tests-card-media">
          <!-- Replace placeholder with card image -->
          <div class="cc-tests-card-ph" role="img" aria-label="LBC test image coming soon"><span>Card image 2 - add later</span></div>
        </figure>
        <div class="cc-tests-card-body">
          <div class="cc-tests-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 2v7.31" />
              <path d="M14 9.3V2" />
              <path d="M8.5 2h7" />
              <path d="M14 9.3a6.5 6.5 0 1 1-4 0" />
              <path d="M5.58 16.5h12.85" />
            </svg></div>
          <h3>Liquid Based Cytology (LBC)</h3>
          <p>An advanced version of the Pap smear where cells are preserved in a liquid for clearer lab analysis — often giving more reliable results.</p>
        </div>
      </article>
      <article class="cc-tests-card">
        <figure class="cc-tests-card-media">
          <!-- Replace placeholder with card image -->
          <div class="cc-tests-card-ph" role="img" aria-label="HPV DNA test image coming soon"><span>Card image 3 - add later</span></div>
        </figure>
        <div class="cc-tests-card-body">
          <div class="cc-tests-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 3v3" />
              <path d="M15 3v3" />
              <path d="M9 18v3" />
              <path d="M15 18v3" />
              <path d="M9 6c0 3 6 3 6 6s-6 3-6 6" />
              <path d="M15 6c0 3-6 3-6 6s6 3 6 6" />
            </svg></div>
          <h3>HPV DNA test</h3>
          <p>Detects the high-risk HPV virus itself before it has caused cell changes. From age 30, every 5 years is an option instead of a 3-yearly Pap smear.</p>
        </div>
      </article>
    </div>

    <div class="cc-cost-box">
      <h3 class="cc-box-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
          <path d="M12 6v12" />
          <path d="M8.5 9.5h7" />
          <path d="M8.5 14.5h7" />
        </svg>Approximate cost in India</h3>
      <div class="cc-cost-grid">
        <div class="cc-cost-item">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 2v7.31" />
              <path d="M14 9.3V2" />
              <path d="M8.5 2h7" />
              <path d="M14 9.3a6.5 6.5 0 1 1-4 0" />
            </svg></div>
          <div>
            <h4>Pap Smear</h4>
            <p>Starts from</p><strong>₹500</strong>
          </div>
        </div>
        <div class="cc-cost-item">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 2v7.31" />
              <path d="M14 9.3V2" />
              <path d="M8.5 2h7" />
              <path d="M14 9.3a6.5 6.5 0 1 1-4 0" />
            </svg></div>
          <div>
            <h4>Liquid Based Cytology (LBC)</h4>
            <p>Starts from</p><strong>₹1,200</strong>
          </div>
        </div>
        <div class="cc-cost-item">
          <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 3v3" />
              <path d="M15 3v3" />
              <path d="M9 18v3" />
              <path d="M15 18v3" />
              <path d="M9 6c0 3 6 3 6 6s-6 3-6 6" />
            </svg></div>
          <div>
            <h4>Pap + HPV DNA Test</h4>
            <p>Starts from</p><strong>₹2,000</strong>
          </div>
        </div>
      </div>
      <p class="cc-tests-note">Available at private hospitals, gynecologist clinics, reputed laboratories, and government / civil hospitals. Prices are indicative and vary by city, lab and package — please confirm with your provider.</p>
    </div>

    <div class="cc-vax-box">
      <h3 class="cc-box-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 7 7 20" />
          <path d="m14 7 3 3" />
          <path d="M4 20a3 3 0 0 1 0-4l2-2" />
          <path d="M20 4a3 3 0 0 1 0 4l-2 2" />
        </svg>HPV vaccines available in India</h3>
      <div class="cc-vax-grid">
        <article class="cc-vax-card">
          <div class="cc-vax-bottle-ph" role="img" aria-label="Cervavac bottle image coming soon"><span>Bottle image</span></div>
          <div>
            <div class="cc-vax-name">Cervavac</div>
            <p class="cc-vax-meta"><strong>4</strong> HPV types<br>Starts from <strong>~₹1,500 / dose</strong><br>Made in India by Serum Institute</p>
          </div>
        </article>
        <article class="cc-vax-card">
          <div class="cc-vax-bottle-ph" role="img" aria-label="Gardasil 4 bottle image coming soon"><span>Bottle image</span></div>
          <div>
            <div class="cc-vax-name">Gardasil 4</div>
            <p class="cc-vax-meta"><strong>4</strong> HPV types<br>Starts from <strong>~₹3,500 / dose</strong><br>Used free in the 2026 govt. campaign</p>
          </div>
        </article>
        <article class="cc-vax-card">
          <div class="cc-vax-bottle-ph" role="img" aria-label="Gardasil 9 bottle image coming soon"><span>Bottle image</span></div>
          <div>
            <div class="cc-vax-name">Gardasil 9</div>
            <p class="cc-vax-meta"><strong>9</strong> HPV types<br>Starts from <strong>~₹9,000 / dose</strong><br>Broadest protection</p>
          </div>
        </article>
      </div>
      <p class="cc-tests-note">Prices are indicative and vary by city or clinic. Under India’s 2026 government campaign, Gardasil-4 is given free to 14-year-old girls at government health facilities.</p>
    </div>

    <div class="cc-age-box">
      <h3 class="cc-box-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 12h18" />
          <path d="M12 3v18" />
          <circle cx="12" cy="12" r="9" />
        </svg>Age-wise prevention schedule</h3>
      <div class="cc-age-track">
        <article class="cc-age-item"><span class="cc-age-dot"></span>
          <h4>9–14 years</h4>
          <p>HPV vaccine<br>(best protection)</p>
        </article>
        <article class="cc-age-item"><span class="cc-age-dot"></span>
          <h4>15–26 years</h4>
          <p>Catch-up<br>HPV vaccine</p>
        </article>
        <article class="cc-age-item"><span class="cc-age-dot"></span>
          <h4>27–45 years</h4>
          <p>HPV vaccine on<br>doctor’s advice</p>
        </article>
        <article class="cc-age-item"><span class="cc-age-dot"></span>
          <h4>30–65 years</h4>
          <p>Pap smear every 3 years /<br>HPV DNA test every 5 years</p>
        </article>
        <article class="cc-age-item"><span class="cc-age-dot"></span>
          <h4>After 65 years</h4>
          <p>Screening may stop if last 10 years reports were normal</p>
        </article>
      </div>
      <p class="cc-tests-note">HPV vaccination works best before sexual activity begins. Even after vaccination, regular Pap smears remain important. It should not be taken during pregnancy.</p>
    </div>

    <div class="cc-tests-cta">
      <div class="cc-tests-cta-left">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          <path d="m9 12 2 2 4-4" />
        </svg>
        <span>Prevention today, protection for life</span>
      </div>
      <div class="cc-tests-cta-links">
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="m19 5-7 7-7-7" />
          </svg>Get vaccinated</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>Go for regular screening</span>
        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
          </svg>Consult your doctor</span>
      </div>
      <a class="cc-tests-cta-btn" href="#find-doctor">Book your screening <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M5 12h14" />
          <path d="m12 5 7 7-7 7" />
        </svg></a>
    </div>
  </div>
</section>

<!-- ================= GOVERNMENT OF INDIA — 2026 CAMPAIGN ================= -->
<section class="cc-section" id="government">
  <div class="wrap cc-gov-wrap">
    <div class="cc-gov-head">
      <div>
        <div class="cc-gov-emblem">
          <!-- Replace with emblem image: <img src="/path/to/goi-emblem.png" alt="Government of India" width="36" height="36"> -->
          <div class="cc-gov-emblem-ph" aria-hidden="true"><span>Logo</span></div>
          Government of India
        </div>
        <h2 class="cc-gov-title">Cervical Cancer Vaccination Campaign launched</h2>
        <p class="cc-gov-intro">On <strong>28 February 2026</strong>, the Prime Minister launched a nationwide HPV Vaccination Programme at Ajmer, Rajasthan — providing the Gardasil-4 vaccine <strong>free of cost</strong> to about <strong>1.15 crore girls aged 14</strong> across all States and UTs, in line with the vision of <em>“Swasth Nari, Sashakt Parivar”</em>.</p>
      </div>
      <figure class="cc-gov-hero">
        <!-- Replace placeholder with your image: <img class="cc-gov-hero-img" src="/path/to/campaign-hero.jpg" alt="HPV vaccination campaign launch" loading="lazy"> -->
        <div class="cc-gov-hero-ph" role="img" aria-label="Campaign launch photo — image coming soon">
          <span>Hero image — add later</span>
        </div>
      </figure>
    </div>

    <div class="cc-gov-features">
      <h3>What the campaign means for you</h3>
      <div class="cc-gov-grid">
        <article class="cc-gov-item">
          <div class="cc-gov-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="m19 5-7 7-7-7" />
              <path d="m12 12 7 7" />
            </svg>
          </div>
          <div>
            <h4>Free vaccine for 14-year-old girls</h4>
            <p><strong>Gardasil-4</strong> is given free at government health facilities. Girls turning 15 within 90 days of launch are also eligible during the intensive three-month drive.</p>
          </div>
        </article>
        <article class="cc-gov-item">
          <div class="cc-gov-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
              <rect x="9" y="3" width="6" height="4" rx="1" />
              <path d="m9 12 2 2 4-4" />
            </svg>
          </div>
          <div>
            <h4>Easy registration</h4>
            <p>Self-register on the <strong>U-WIN</strong> platform, get pre-registered by a health worker, or simply walk in. Vaccination certificates are downloadable from U-WIN.</p>
          </div>
        </article>
        <article class="cc-gov-item">
          <div class="cc-gov-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <div>
            <h4>Safe &amp; supervised</h4>
            <p>Given only at facilities with a cold-chain point and a medical officer; each girl is observed for 30 minutes after the dose. Sessions usually run 9 AM–2 PM. Don’t go on an empty stomach.</p>
          </div>
        </article>
        <article class="cc-gov-item">
          <div class="cc-gov-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M2 12h20" />
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
            </svg>
          </div>
          <div>
            <h4>World-class backing</h4>
            <p>Procured in partnership with <strong>GAVI, the Vaccine Alliance</strong>; logistics tracked via <strong>eVIN</strong>. India now joins 160+ countries with HPV vaccination in their national immunisation schedule.</p>
          </div>
        </article>
      </div>
    </div>

    <div class="cc-gov-who">
      <div class="cc-gov-who-top">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" />
          <circle cx="12" cy="12" r="6" />
          <circle cx="12" cy="12" r="2" />
        </svg>
        <p><strong>The global goal — WHO “90-70-90” by 2030:</strong> India’s 2026 campaign is a major step toward this elimination target.</p>
      </div>
      <div class="cc-gov-who-grid">
        <div class="cc-gov-who-stat">
          <div class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <p><strong>90%</strong> of girls fully vaccinated against HPV by age 15</p>
        </div>
        <div class="cc-gov-who-stat">
          <div class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </div>
          <p><strong>70%</strong> of women screened with a high-performance test by ages 35 and 45</p>
        </div>
        <div class="cc-gov-who-stat">
          <div class="ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
              <path d="M12 8v8" />
              <path d="M8 12h8" />
            </svg>
          </div>
          <p><strong>90%</strong> of women with cervical disease receiving treatment</p>
        </div>
      </div>
    </div>

    <p class="cc-tests-note" style="margin-top:10px;">Source: Press Information Bureau (PIB), Government of India — Cervical Cancer Vaccination Campaign, 28 February 2026; WHO; GLOBOCAN 2022.</p>
  </div>
</section>

<!-- ========================== FAQ ========================== -->
<section class="cc-section cc-section-alt" id="faq">
  <div class="wrap cc-faq-wrap">
    <div class="cc-faq-layout">
      <aside class="cc-faq-side">
        <span class="cc-faq-kicker">Questions answered</span>
        <h2 class="cc-faq-title">Frequently asked questions</h2>
        <p class="cc-faq-lead">Find answers to common questions about cervical cancer, screening tests, and HPV vaccination.</p>
        <figure class="cc-faq-visual">
          <!-- Replace placeholder with your image: <img class="cc-faq-img" src="/path/to/faq-doctor.jpg" alt="Doctor discussing cervical health with patient" loading="lazy"> -->
          <div class="cc-faq-img-ph" role="img" aria-label="Doctor and patient illustration — image coming soon">
            <img src="/assets/img/eclinicpro-Frequently-asked-questions.png" alt="Doctor and patient" loading="lazy">
          </div>
        </figure>
        <div class="cc-faq-callout">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <p>Early screening, timely vaccination and awareness can help prevent cervical cancer.</p>
        </div>
      </aside>

      <div class="cc-faq-panel" id="cc-faq-panel">
        <div class="cc-faq-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
          <input type="search" id="cc-faq-search" placeholder="Search your question..." aria-label="Search FAQ questions">
        </div>
        <div class="cc-faq-filters" role="tablist" aria-label="Filter questions by topic">
          <button type="button" class="cc-faq-filter is-active" data-filter="all" role="tab" aria-selected="true">All Questions</button>
          <button type="button" class="cc-faq-filter" data-filter="about" role="tab" aria-selected="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
            </svg>
            About Cervical Cancer
          </button>
          <button type="button" class="cc-faq-filter" data-filter="screening" role="tab" aria-selected="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10 2v7.31" />
              <path d="M14 9.3V2" />
              <path d="M8.5 2h7" />
              <path d="M14 9.3a6.5 6.5 0 1 1-4 0" />
            </svg>
            Screening Tests
          </button>
          <button type="button" class="cc-faq-filter" data-filter="vaccine" role="tab" aria-selected="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
            HPV Vaccine
          </button>
          <button type="button" class="cc-faq-filter" data-filter="general" role="tab" aria-selected="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" />
              <path d="M8 12h8" />
            </svg>
            General
          </button>
        </div>

        <div class="cc-faq-list" id="cc-faq-list">
          <?php foreach ($ccFaqs as $i => $f): ?>
            <div class="cc-faq-item" data-cat="<?= e($f['cat']) ?>" data-q="<?= e(mb_strtolower($f['q'] . ' ' . $f['a'])) ?>">
              <details>
                <summary>
                  <span class="cc-faq-num"><?= sprintf('%02d', $i + 1) ?></span>
                  <span class="cc-faq-q"><?= e($f['q']) ?></span>
                  <svg class="chev" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m6 9 6 6 6-6" />
                  </svg>
                </summary>
                <div class="ans"><?= e($f['a']) ?></div>
              </details>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="cc-faq-empty" id="cc-faq-empty">No questions match your search. Try a different keyword or filter.</p>
      </div>
    </div>
  </div>
</section>
<script>
  (function() {
    var panel = document.getElementById('cc-faq-panel');
    if (!panel) return;
    var search = document.getElementById('cc-faq-search');
    var items = panel.querySelectorAll('.cc-faq-item');
    var empty = document.getElementById('cc-faq-empty');
    var activeFilter = 'all';

    function applyFaqFilter() {
      var q = (search && search.value ? search.value : '').trim().toLowerCase();
      var visible = 0;
      items.forEach(function(item) {
        var cat = item.getAttribute('data-cat') || '';
        var text = item.getAttribute('data-q') || '';
        var catOk = activeFilter === 'all' || cat === activeFilter;
        var searchOk = !q || text.indexOf(q) !== -1;
        var show = catOk && searchOk;
        item.classList.toggle('is-hidden', !show);
        if (show) visible++;
      });
      if (empty) empty.classList.toggle('is-visible', visible === 0);
    }

    panel.querySelectorAll('.cc-faq-filter').forEach(function(btn) {
      btn.addEventListener('click', function() {
        activeFilter = btn.getAttribute('data-filter') || 'all';
        panel.querySelectorAll('.cc-faq-filter').forEach(function(b) {
          var on = b === btn;
          b.classList.toggle('is-active', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        applyFaqFilter();
      });
    });

    if (search) {
      search.addEventListener('input', applyFaqFilter);
    }
  })();
</script>

<!-- ============= FIND A GYNECOLOGIST (city-aware) ============= -->
<section class="cc-section" id="find-doctor">
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
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14" />
          <path d="m12 5 7 7-7 7" />
        </svg>
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
<section class="cc-refs" id="references">
  <div class="wrap">
    <h2>References &amp; sources</h2>
    <p>The information on this page is compiled from the following public, authoritative sources. It is reviewed against official guidance, but medical knowledge evolves — always confirm with a qualified doctor.</p>
    <ul>
      <li>Press Information Bureau (PIB), Government of India — <em>Cervical Cancer Vaccination Campaign Launched</em> (28 Feb 2026) &amp; cervical cancer / GLOBOCAN 2022 data —
        <a href="https://www.pib.gov.in/PressReleasePage.aspx?PRID=2233632&amp;reg=3&amp;lang=1" target="_blank" rel="noopener nofollow">pib.gov.in</a>
      </li>
      <li>World Health Organization — <em>Cervical cancer fact sheet</em> —
        <a href="https://www.who.int/news-room/fact-sheets/detail/cervical-cancer" target="_blank" rel="noopener nofollow">who.int</a>
      </li>
      <li>National Cancer Institute (NCI), USA — <em>Cervical cancer: types, how it develops, stages &amp; treatment</em> —
        <a href="https://www.cancer.gov/types/cervical" target="_blank" rel="noopener nofollow">cancer.gov</a>
      </li>
      <li>Centers for Disease Control and Prevention (CDC) — <em>About cervical cancer</em> &amp; <em>Basic information about HPV and cancer</em> —
        <a href="https://www.cdc.gov/cervical-cancer/about/index.html" target="_blank" rel="noopener nofollow">cdc.gov/cervical-cancer</a>,
        <a href="https://www.cdc.gov/cancer/hpv/basic-information.html" target="_blank" rel="noopener nofollow">cdc.gov/cancer/hpv</a>
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