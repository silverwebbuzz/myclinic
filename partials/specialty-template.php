<?php
// =====================================================================
// specialty-template.php — shared body markup for all 6 specialty pages.
// Each thin wrapper (gps.php, dentists.php, etc.) sets $spec then requires this.
// =====================================================================
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/specialty-mocks.php';

if (!isset($spec) || !is_array($spec)) {
    http_response_code(500);
    echo 'specialty-template.php: missing $spec';
    return;
}
$specKey = $specKey ?? 'gp';

$pageTitle = $spec['label'] . ' — eClinicPro';
$metaDesc = $spec['heroBlurb'];
$activePage = 'specialties';

require __DIR__ . '/header.php';

$hv = $spec['heroV2'] ?? [];
$labelSingular = rtrim($spec['label'], 's');
$heroBadge = $hv['badge'] ?? 'Smart Software for ' . $spec['label'] . ' Clinics';
$heroTitle = $hv['title'] ?? $labelSingular . ' Clinic Management Software |';
$heroTitleAccent = $hv['titleAccent'] ?? 'Complete Patient & Clinic Management Solution';
$heroTagline = $hv['tagline'] ?? 'Simplify Your ' . $labelSingular . ' Practice with';
$heroBody = $hv['body'] ?? [
    $spec['heroBlurb'],
    'Spend less time on paperwork and more time with patients — appointments, billing, prescriptions, and reports in one place.',
];
$heroBar = $spec['heroBar'] ?? [
    ['patients', 'Patient Management', 'Store and manage complete patient history securely.'],
    ['calendar', 'Appointment Scheduling', 'Manage appointments, reminders & follow-ups easily.'],
    ['rx', 'Prescriptions', 'Create, save & print prescriptions quickly.'],
    ['billing', 'Billing & Invoices', 'Generate invoices, track payments & due.'],
    ['medicine', 'Medicine Management', 'Maintain medicine stock, inventory & alerts.'],
    ['reports', 'Reports & Analytics', 'Get insights with detailed reports & analytics.'],
];

$wc = $spec['whyChoose'] ?? [];
$whyBadge = $wc['badge'] ?? 'Challenges of Traditional Clinic Management';
$whyTitle = $wc['title'] ?? 'Why Choose';
$whyTitleBrand = $wc['titleBrand'] ?? 'EClinicPro';
$whyTitleSuffix = $wc['titleSuffix'] ?? 'for Your ' . $labelSingular . ' Clinic?';
$whySub = $wc['sub'] ?? 'Traditional paper-based clinic management can slow down your practice and create unnecessary challenges.';
$whyChallenges = $wc['challenges'] ?? [
    ['Lost Patient Records', 'Important patient information can be misplaced or lost.'],
    ['Missed Appointments', 'Manual scheduling leads to missed or double bookings.'],
    ['Billing Errors', 'Manual billing increases the risk of calculation mistakes.'],
    ['Difficulty Tracking Medicine Inventory', 'Hard to maintain accurate stock levels and availability.'],
    ['Time-consuming Manual Reports', 'Creating reports manually takes time and effort.'],
    ['Challenges Managing Follow-up Visits', 'Tracking follow-ups manually can lead to missed visits.'],
];
$whySolutions = $wc['solutions'] ?? [
    ['records', 'Patient Records', 'Store & access patient data securely.'],
    ['appointments', 'Smart Appointments', 'Schedule & manage appointments easily.'],
    ['billing', 'Billing Automation', 'Generate invoices & manage payments.'],
    ['inventory', 'Medicine Inventory', 'Track stock levels & manage medicines.'],
    // ['followup', 'Follow-up Reminders', 'Never miss a follow-up or patient check-in.'],
    ['reports', 'Reports & Analytics', 'Get insights & make better decisions.'],
];
$whyCtaTitle = $wc['ctaTitle'] ?? 'Focus More on';
$whyCtaHighlight = $wc['ctaHighlight'] ?? 'Patient Care';
$whyCtaTitleEnd = $wc['ctaTitleEnd'] ?? ', Not Paperwork.';
$whyCtaSub = $wc['ctaSub'] ?? 'EClinicPro automates your entire clinic so you spend more time with patients.';

$kf = $spec['keyFeatures'] ?? [];
$kfBadge = $kf['badge'] ?? 'All-in-One Solution for Modern ' . $spec['label'] . ' Clinics';
$kfTitle = $kf['title'] ?? 'Key Features of';
$kfTitleBrand = $kf['titleBrand'] ?? 'EClinicPro';
$kfTitleSuffix = $kf['titleSuffix'] ?? $labelSingular . ' Software';
$kfSub = $kf['sub'] ?? 'Everything you need to manage your clinic efficiently and focus on what matters most – your patients.';
$kfCards = $kf['cards'] ?? [
    ['icon' => 'patient', 'title' => 'Patient Management', 'items' => ['Complete digital patient records', 'Follow-up reminders', 'Medical history tracking', 'Secure cloud storage'], 'mock' => 'patient'],
    ['icon' => 'calendar', 'title' => 'Appointment Management', 'items' => ['Online and offline booking', 'Queue management', 'Automated reminders', 'Daily schedule overview'], 'mock' => 'calendar'],
    ['icon' => 'rx', 'title' => 'Prescription Management', 'items' => ['Digital prescriptions', 'Print and share easily', 'Save frequently used medicines'], 'mock' => 'rx'],
    ['icon' => 'billing', 'title' => 'Billing and Invoice Management', 'items' => ['Professional invoices', 'Track paid & due', 'Download and email invoices'], 'mock' => 'billing'],
    ['icon' => 'medicine', 'title' => 'Medicine and Inventory Management', 'items' => ['Stock management', 'Low stock alerts', 'Expiry date tracking'], 'mock' => 'medicine'],
    ['icon' => 'reports', 'title' => 'Reports and Analytics', 'items' => ['Daily patient reports', 'Revenue reports', 'Appointment statistics', 'Export for analysis'], 'mock' => 'reports'],
];
$kfWide = $kf['wideCard'] ?? [
    'icon' => 'clinic',
    'title' => 'Multi-Clinic and Multi-User Support',
    'items' => ['Manage multiple branches', 'Role-based access for staff', 'Cloud-based access from anywhere'],
    'branches' => [['Head Clinic', 'Main Branch', true], ['Branch Clinic', 'Branch 2', false]],
    'users' => [['Dr. Admin', 'Admin', 'admin'], ['Dr. Staff', 'Doctor', 'doctor']],
    'accessTitle' => 'Access your clinic data securely from anywhere, anytime.',
    'accessSub' => 'Secure. Reliable. Always Available.',
];

$ben = $spec['benefits'] ?? [];
$benBadge = $ben['badge'] ?? 'Better Management. Better Care. Better Practice.';
$benTitle = $ben['title'] ?? 'Benefits of Using ' . $labelSingular . ' Clinic Management Software';
$benIntro = $ben['intro'] ?? 'Using';
$benIntroBrand = $ben['introBrand'] ?? 'EClinicPro';
$benIntroSuffix = $ben['introSuffix'] ?? 'helps ' . strtolower($spec['label']) . ':';
$benItems = $ben['items'] ?? [
    ['time', 'Save Administrative Time', 'Automate routine tasks and focus more on patient care.'],
    ['paperwork', 'Reduce Paperwork', 'Go digital and eliminate manual paperwork.'],
    ['experience', 'Improve Patient Experience', 'Faster service, easy booking and better communication.'],
    ['efficiency', 'Increase Clinic Efficiency', 'Streamline operations and boost productivity.'],
    ['secure', 'Manage Records Securely', 'Store and access patient data safely in the cloud.'],
    ['reports', 'Generate Accurate Reports', 'Get detailed insights to make better decisions.'],
    // ['followup', 'Improve Follow-up Management', 'Never miss a follow-up with smart reminders.'],
    ['growth', 'Grow Your Practice Effectively', 'Better management leads to more patients and growth.'],
];
$benCtaTitle = $ben['ctaTitle'] ?? 'Smart Clinic Management. Better Patient Care. Stronger Practice Growth.';
$benCtaSub = $ben['ctaSub'] ?? 'EClinicPro empowers doctors to manage their clinics efficiently and deliver the best care to their patients.';
$benCtaTrust = $ben['ctaTrust'] ?? ['Secure', 'Reliable', 'Easy to Use'];

$cmp = $spec['comparison'] ?? [];
$cmpBadge = $cmp['badge'] ?? 'Make the Smart Choice for Your Clinic';
$cmpTitle = $cmp['title'] ?? 'Manual Management vs';
$cmpTitleBrand = $cmp['titleBrand'] ?? 'EClinicPro';
$cmpSub = $cmp['sub'] ?? 'See how EClinicPro transforms your clinic operations compared to traditional manual management.';
$cmpManualTitle = $cmp['manualTitle'] ?? 'Manual Management';
$cmpManualSub = $cmp['manualSub'] ?? 'Time-consuming, error-prone and hard to manage.';
$cmpManualItems = $cmp['manualItems'] ?? [
    ['records', 'Lost or Misplaced Records', 'Paper files can be lost or damaged.'],
    ['appointments', 'Missed Appointments', 'No reminders, leads to missed visits.'],
    ['rx', 'Prescription Errors', 'Handwritten prescriptions can have mistakes.'],
    ['billing', 'Billing Mistakes', 'Manual calculations cause billing errors.'],
    ['medicine', 'Stock Mismanagement', 'No proper tracking of medicines or stock.'],
    // ['followup', 'Follow-ups Missed', 'Difficult to remember follow-up visits.'],
    ['reports', 'No Proper Reports', 'Reports take time and are not accurate.'],
    ['clinic', 'Hard to Manage Multiple Clinics', 'Manual coordination is difficult.'],
];
$cmpRows = $cmp['compareRows'] ?? [
    ['records', 'Patient Records'],
    ['appointments', 'Appointment Booking'],
    ['rx', 'Prescription History'],
    ['billing', 'Billing & Invoices'],
    ['medicine', 'Medicine Management'],
    // ['followup', 'Follow-Up Tracking'],
    ['reports', 'Daily Reports'],
    ['clinic', 'Multi-Clinic Support'],
];
$cmpDigitalTitle = $cmp['digitalTitle'] ?? 'EClinicPro';
$cmpDigitalSub = $cmp['digitalSub'] ?? 'Smart, automated and efficient clinic management.';
$cmpDigitalItems = $cmp['digitalItems'] ?? [
    'Digital, secure, and easy-to-access patient records.',
    'Smart reminders for appointments.',
    'Instant prescription creation and access.',
    'Professional invoice generation and payment tracking.',
    'Real-time medicine stock and expiry tracking.',
    'Automated follow-up reminders.',
    'Instant daily, weekly, and monthly reports.',
    'Seamless multi-clinic and user management.',
];
$cmpCtaTitle = $cmp['ctaTitle'] ?? 'Save Time. Reduce Errors. Grow Your Practice.';
$cmpCtaSub = $cmp['ctaSub'] ?? 'Switch to EClinicPro and experience the power of smart clinic management.';

$faq = $spec['faq'] ?? [];
$faqBadge = $faq['badge'] ?? 'Get Clear Answers. Make Confident Decisions.';
$faqTitle = $faq['title'] ?? 'Frequently Asked';
$faqTitleAccent = $faq['titleAccent'] ?? 'Questions';
$faqSub = $faq['sub'] ?? 'Everything you need to know about EClinicPro ' . $labelSingular . ' Clinic Management Software.';
$faqItems = $faq['items'] ?? [
    ['software', 'What is Clinic Management Software?', 'A digital solution that helps doctors manage appointments, patient records, prescriptions, billing, inventory, and reports efficiently.'],
    ['clinic', 'Is EClinicPro suitable for small clinics?', 'Yes. EClinicPro is designed for both individual practitioners and large multi-clinic organizations.'],
    ['history', 'Can I manage patient history digitally?', 'Yes. EClinicPro stores complete patient history, prescriptions, visits, and treatment records securely.'],
    ['billing', 'Does EClinicPro support billing and invoices?', 'Yes. The software provides billing, payment tracking, invoice generation, and financial reporting.'],
    ['cloud', 'Is EClinicPro cloud-based?', 'Yes. You can access your clinic data securely from anywhere.'],
];
?>

<!-- ============ HERO ============ -->
<section class="spec-landing-hero">
    <div class="spec-hero-glow" aria-hidden="true"></div>
    <div class="wrap">
        <div class="spec-hero-grid">
            <div class="spec-hero-copy reveal">
                <span class="spec-hero-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M11 20A7 7 0 0113.5 6c.5 0 1 .1 1.5.3A5 5 0 0118 10a4 4 0 01.5 2 2 2 0 01-2 2h-1a2 2 0 00-2 2v1a2 2 0 01-2 2 2 2 0 01-2-2v-2.2A7 7 0 0111 20z" />
                    </svg>
                    <?= e($heroBadge) ?>
                </span>

                <h1 class="spec-hero-title">
                    <?= e($heroTitle) ?><br>
                    <span class="spec-hero-accent"><?= e($heroTitleAccent) ?></span>
                </h1>
                <div class="spec-hero-rule" aria-hidden="true"></div>

                <p class="spec-hero-tagline">
                    <?= e($heroTagline) ?> <strong>eClinicPro</strong>
                </p>

                <div class="spec-hero-body">
                    <?php foreach ($heroBody as $para): ?>
                        <p><?= e($para) ?></p>
                    <?php endforeach; ?>
                </div>

                <div class="spec-hero-ctas">
                    <a href="/book-a-demo" data-open-demo-modal class="btn btn-primary btn-lg spec-btn-demo">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" />
                            <path d="M16 2v4M8 2v4M3 10h18" />
                        </svg>
                        Book a Free Demo
                    </a>
                    <a href="#key-features" class="btn spec-btn-outline btn-lg">
                        Explore Features
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="spec-hero-visual reveal">
                <div class="spec-hero-dots" aria-hidden="true"></div>
                <div class="spec-hero-circle" aria-hidden="true"></div>
                <figure class="spec-hero-figure">
                    <!-- Add hero image: <img src="/assets/img/your-hero.webp" alt="eClinicPro dashboard for <?= e($spec['label']) ?>" loading="eager"> -->
                    <!-- <div class="spec-hero-img-ph" role="img" aria-label="Hero image — add later">
                        </div> -->
                    <img src="/assets/img/Smart-Software-for-Homeopathy-Clinics-bg.png" alt="eClinicPro dashboard for <?= e($spec['label']) ?>" loading="eager">
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- ============ HERO FEATURES BAR ============ -->
<section class="spec-hero-bar" aria-label="Key features">
    <div class="wrap">
        <div class="spec-hero-bar-grid">
            <?php foreach ($heroBar as [$iconKey, $title, $desc]): ?>
                <div class="spec-hero-bar-item reveal">
                    <div class="spec-hero-bar-ico" aria-hidden="true">
                        <?php if ($iconKey === 'patients'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                            </svg>
                        <?php elseif ($iconKey === 'calendar'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <path d="M16 2v4M8 2v4M3 10h18" />
                                <circle cx="12" cy="16" r="2" />
                            </svg>
                        <?php elseif ($iconKey === 'rx'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M10 5h4M8 9h8M12 9v10M7 19h10" />
                                <path d="M6 5l12 14" />
                            </svg>
                        <?php elseif ($iconKey === 'billing'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
                            </svg>
                        <?php elseif ($iconKey === 'medicine'): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M10 2h4v4h4v4h-4v4h-4v-4H6V6h4z" />
                                <rect x="4" y="14" width="16" height="8" rx="2" />
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M18 20V10M12 20V4M6 20v-6" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <h3 class="spec-hero-bar-title"><?= e($title) ?></h3>
                    <p class="spec-hero-bar-desc"><?= e($desc) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ WHY CHOOSE ============ -->
<section class="spec-why" aria-labelledby="spec-why-heading">
    <div class="wrap">
        <div class="spec-why-main">
            <div class="spec-why-left reveal">
                <span class="spec-why-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        <path d="M12 9v4M12 17h.01" />
                    </svg>
                    <?= e($whyBadge) ?>
                </span>
                <h2 class="spec-why-title" id="spec-why-heading">
                    <?= e($whyTitle) ?> <span class="spec-why-brand"><?= e($whyTitleBrand) ?></span> <?= e($whyTitleSuffix) ?>
                </h2>
                <p class="spec-why-sub"><?= e($whySub) ?></p>

                <div class="spec-challenges-grid">
                    <?php foreach ($whyChallenges as [$title, $desc]): ?>
                        <article class="spec-challenge-card reveal">
                            <div class="spec-challenge-ico" aria-hidden="true">
                                <!-- <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                    <path d="M12 9v4M12 17h.01" />
                                </svg> -->
                                <img src="/assets/img/Challenges-of-Traditional-Clinic-Management-icon.png" alt="eClinicPro dashboard for <?= e($spec['label']) ?>" loading="eager">

                            </div>
                            <div>
                                <h3 class="spec-challenge-title"><?= e($title) ?></h3>
                                <p class="spec-challenge-desc"><?= e($desc) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- <div class="spec-why-flow reveal" aria-hidden="true">
                <div class="spec-flow-node spec-flow-manual">
                    <div class="spec-flow-ico spec-flow-ico--warn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" />
                        </svg>
                    </div>
                    <strong>Manual Clinic</strong>
                    <span>Time-consuming &amp; Error-prone</span>
                </div>
                <div class="spec-flow-arrow">
                    <svg viewBox="0 0 48 120" fill="none" aria-hidden="true">
                        <path d="M24 4v88M24 92l-10-10M24 92l10-10" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="spec-flow-node spec-flow-digital">
                    <div class="spec-flow-ico spec-flow-ico--ok">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <rect x="2" y="3" width="20" height="14" rx="2" />
                            <path d="M8 21h8M12 17v4" />
                        </svg>
                    </div>
                    <strong>Digital Clinic</strong>
                    <span>Automated, Accurate &amp; Efficient</span>
                </div>
            </div> -->

            <div class="spec-why-showcase reveal">
                <div class="spec-showcase-stage">
                    <!-- Add showcase image: <img src="/assets/img/homeo-showcase.webp" alt="Doctor using eClinicPro" class="spec-showcase-img" loading="lazy"> -->
                    <!-- <div class="spec-showcase-img-ph" role="img" aria-label="Doctor with laptop — add image later">
                        </div> -->
                    <img src="/assets/img/Challenges-of-Traditional-Clinic-Management-bg.png" alt="eClinicPro dashboard for <?= e($spec['label']) ?>" loading="eager">

                    <!-- <div class="spec-showcase-bubbles">
                        <?php
                        $bubblePos = ['tl', 'tc', 'tr', 'bl', 'bc', 'br'];
                        foreach ($whySolutions as $i => [$key, $title, $desc]):
                            $pos = $bubblePos[$i] ?? 'tc';
                        ?>
                        <div class="spec-bubble spec-bubble--<?= e($pos) ?>">
                            <div class="spec-bubble-ico" aria-hidden="true">
                                <?php if ($key === 'records'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
                                <?php elseif ($key === 'appointments'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                <?php elseif ($key === 'billing'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                                <?php elseif ($key === 'inventory'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M10 2h4v4h4v4h-4v4h-4v-4H6V6h4z"/></svg>
                                <?php elseif ($key === 'followup'): ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                                <?php else: ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="spec-bubble-text">
                                <strong><?= e($title) ?></strong>
                                <span><?= e($desc) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div> -->
                </div>

                <div class="spec-showcase-bubbles-mobile">
                    <?php foreach ($whySolutions as [$key, $title, $desc]): ?>
                        <div class="spec-bubble-mobile">
                            <strong><?= e($title) ?></strong>
                            <span><?= e($desc) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="spec-why-cta">
        <div class="wrap spec-why-cta-inner reveal">
            <figure class="spec-why-cta-media">
                <!-- Add CTA image: <img src="/assets/img/homeo-cta.webp" alt="Homeopathy medicines" loading="lazy"> -->
                <!-- <div class="spec-why-cta-ph" role="img" aria-label="Mortar and pestle — add image later"></div> -->
                <img src="/assets/img/Challenges-of-Traditional-Clinic-Management-banner-img.png" alt="eClinicPro dashboard for <?= e($spec['label']) ?>" loading="eager">
            </figure>
            <div class="spec-why-cta-copy">
                <h3 class="spec-why-cta-title">
                    <?= e($whyCtaTitle) ?> <span class="spec-why-cta-hl"><?= e($whyCtaHighlight) ?></span><?= e($whyCtaTitleEnd) ?>
                </h3>
                <p class="spec-why-cta-sub"><?= e($whyCtaSub) ?></p>
            </div>
            <a href="/book-a-demo" data-open-demo-modal class="spec-why-cta-btn">
                Book Free Demo
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- ============ KEY FEATURES ============ -->
<section class="spec-kf" id="key-features" aria-labelledby="spec-kf-heading">
    <div class="spec-kf-dots spec-kf-dots--tl" aria-hidden="true"></div>
    <div class="spec-kf-dots spec-kf-dots--br" aria-hidden="true"></div>
    <div class="wrap">
        <header class="spec-kf-head reveal">
            <span class="spec-kf-badge">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M11 20A7 7 0 0113.5 6c.5 0 1 .1 1.5.3A5 5 0 0118 10a4 4 0 01.5 2 2 2 0 01-2 2h-1a2 2 0 00-2 2v1a2 2 0 01-2 2 2 2 0 01-2-2v-2.2A7 7 0 0111 20z" />
                </svg>
                <?= e($kfBadge) ?>
            </span>
            <div class="spec-kf-leaf" aria-hidden="true">
                <!-- <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M11 20A7 7 0 0113.5 6c.5 0 1 .1 1.5.3A5 5 0 0118 10a4 4 0 01.5 2 2 2 0 01-2 2h-1a2 2 0 00-2 2v1a2 2 0 01-2 2 2 2 0 01-2-2v-2.2A7 7 0 0111 20z" />
                </svg> -->
            </div>
            <h2 class="spec-kf-title" id="spec-kf-heading">
                <?= e($kfTitle) ?> <span class="spec-kf-brand"><?= e($kfTitleBrand) ?></span> <?= e($kfTitleSuffix) ?>
            </h2>
            <p class="spec-kf-sub"><?= e($kfSub) ?></p>
        </header>

        <div class="spec-kf-grid">
            <?php foreach ($kfCards as $i => $card):
                $num = $i + 1;
                $mock = $card['mock'] ?? 'generic';
            ?>
                <article class="spec-kf-card reveal">
                    <div class="spec-kf-card-copy">
                        <div class="spec-kf-card-head">
                            <div class="spec-kf-card-ico spec-kf-card-ico--<?= e($card['icon']) ?>" aria-hidden="true">
                                <?php if ($card['icon'] === 'patient'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                        <path d="M19 8v6M22 11h-6" />
                                    </svg>
                                <?php elseif ($card['icon'] === 'calendar'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <rect x="3" y="4" width="18" height="18" rx="2" />
                                        <path d="M16 2v4M8 2v4M3 10h18" />
                                    </svg>
                                <?php elseif ($card['icon'] === 'rx'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M14 2v6h6M9 15l6-6" />
                                    </svg>
                                <?php elseif ($card['icon'] === 'billing'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                        <path d="M2 10h20M7 15h.01M11 15h2" />
                                    </svg>
                                <?php elseif ($card['icon'] === 'medicine'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M10 2h4v4h4v4h-4v4h-4v-4H6V6h4z" />
                                        <rect x="4" y="14" width="16" height="8" rx="2" />
                                    </svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M18 20V10M12 20V4M6 20v-6" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <h3 class="spec-kf-card-title"><?= $num ?>. <?= e($card['title']) ?></h3>
                        </div>
                        <ul class="spec-kf-list">
                            <?php foreach ($card['items'] as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="spec-kf-card-visual">
                        <!-- Add image: <img src="/assets/img/kf-<?= e($mock) ?>.webp" alt="<?= e($card['title']) ?> screenshot" loading="lazy"> -->
                        <div class="spec-kf-mock spec-kf-mock--<?= e($mock) ?>" role="img" aria-label="<?= e($card['title']) ?> preview — add image later">
                            <?php if ($mock === 'patient'): ?>
                                <div class="spec-mock-ui">
                                    <div class="spec-mock-patient-head">
                                        <div class="spec-mock-avatar"></div>
                                        <div><strong>Anjali Sharma</strong><span>Age 32 · ID #P-1042</span></div>
                                    </div>
                                    <div class="spec-mock-menu">
                                        <span>Personal Information</span><span>Medical History</span><span>Treatment History</span><span>Follow-ups</span>
                                    </div>
                                </div>
                            <?php elseif ($mock === 'calendar'): ?>
                                <div class="spec-mock-ui">
                                    <div class="spec-mock-cal-head">April 2026</div>
                                    <div class="spec-mock-cal-grid">
                                        <?php for ($d = 1; $d <= 28; $d++): ?>
                                            <span class="<?= $d === 23 ? 'is-active' : '' ?>"><?= $d ?></span>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="spec-mock-appts">
                                        <div><span>10:00 AM</span> Rahul Verma</div>
                                        <div><span>02:30 PM</span> Priya Patel</div>
                                        <div><span>04:30 PM</span> Amit Shah</div>
                                    </div>
                                </div>
                            <?php elseif ($mock === 'rx'): ?>
                                <div class="spec-mock-ui">
                                    <div class="spec-mock-rx-head"><span>Rx</span> Prescription</div>
                                    <div class="spec-mock-rx-meta">Patient: Rahul Verma · 23 Apr 2026</div>
                                    <table class="spec-mock-table">
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Dose</th>
                                        </tr>
                                        <tr>
                                            <td>Arnica Montana</td>
                                            <td>30C</td>
                                        </tr>
                                        <tr>
                                            <td>Belladonna</td>
                                            <td>200C</td>
                                        </tr>
                                        <tr>
                                            <td>Nux Vomica</td>
                                            <td>30C</td>
                                        </tr>
                                    </table>
                                    <div class="spec-mock-sign">Dr. Hitesh Sharma</div>
                                </div>
                            <?php elseif ($mock === 'billing'): ?>
                                <div class="spec-mock-ui">
                                    <div class="spec-mock-inv-head"><strong>Invoice #INV-1258</strong><span>23 Apr 2026</span></div>
                                    <div class="spec-mock-inv-row"><span>Consultation Fee</span><span>₹ 500</span></div>
                                    <div class="spec-mock-inv-row"><span>Medicine</span><span>₹ 650</span></div>
                                    <div class="spec-mock-inv-row"><span>Discount</span><span>- ₹ 50</span></div>
                                    <div class="spec-mock-inv-total">Total: <strong>₹ 1,100</strong></div>
                                    <div class="spec-mock-inv-foot"><span>Paid: ₹ 800</span><span>Due: ₹ 300</span></div>
                                </div>
                            <?php elseif ($mock === 'medicine'): ?>
                                <div class="spec-mock-ui">
                                    <div class="spec-mock-stock-head">Medicine Stock</div>
                                    <table class="spec-mock-table">
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Stock</th>
                                        </tr>
                                        <tr>
                                            <td>Arnica 30</td>
                                            <td>120</td>
                                        </tr>
                                        <tr>
                                            <td>Belladonna 200</td>
                                            <td>85</td>
                                        </tr>
                                        <tr>
                                            <td>Nux Vomica 30</td>
                                            <td>12</td>
                                        </tr>
                                    </table>
                                    <div class="spec-mock-alert">Low Stock Alert: Nux Vomica 30</div>
                                </div>
                            <?php elseif ($mock === 'reports'): ?>
                                <div class="spec-mock-ui">
                                    <div class="spec-mock-stock-head">Monthly Revenue</div>
                                    <div class="spec-mock-chart"></div>
                                    <div class="spec-mock-stats">
                                        <div><strong>2,458</strong><span>Total Patients</span></div>
                                        <div><strong>320</strong><span>New Patients</span></div>
                                        <div><strong>1,856</strong><span>Appointments</span></div>
                                        <div><strong>+18.5%</strong><span>Revenue Growth</span></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <article class="spec-kf-card spec-kf-card--wide reveal">
                <div class="spec-kf-wide-copy">
                    <div class="spec-kf-card-head">
                        <div class="spec-kf-card-ico spec-kf-card-ico--clinic" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M3 21h18M5 21V7l7-4 7 4v14" />
                                <path d="M9 21v-6h6v6" />
                            </svg>
                        </div>
                        <h3 class="spec-kf-card-title">7. <?= e($kfWide['title']) ?></h3>
                    </div>
                    <ul class="spec-kf-list">
                        <?php foreach ($kfWide['items'] as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="spec-kf-wide-branches">
                    <!-- Add image: <img src="/assets/img/kf-branches.webp" alt="Multi-clinic branches" loading="lazy"> -->
                    <div class="spec-kf-branch-diagram" role="img" aria-label="Branch clinics diagram — add image later">
                        <?php foreach ($kfWide['branches'] as [$label, $city, $isHead]): ?>
                            <div class="spec-kf-branch <?= $isHead ? 'is-head' : '' ?>">
                                <strong><?= e($label) ?></strong>
                                <span><?= e($city) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="spec-kf-wide-users">
                    <div class="spec-kf-users-title">Users &amp; Roles</div>
                    <?php foreach ($kfWide['users'] as [$name, $role, $roleKey]): ?>
                        <div class="spec-kf-user">
                            <div class="spec-kf-user-av" aria-hidden="true"></div>
                            <div class="spec-kf-user-info">
                                <strong><?= e($name) ?></strong>
                                <span class="spec-kf-role spec-kf-role--<?= e($roleKey) ?>"><?= e($role) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="spec-kf-wide-access">

                    <img src="/assets/img/Access-your-clinic-data-securely.png" alt="Access from any device" loading="lazy">
                    <!-- Add image: <img src="/assets/img/kf-devices.webp" alt="Access from any device" loading="lazy"> -->
                    <!-- <div class="spec-kf-devices" role="img" aria-label="Cloud devices — add image later">
                        <div class="spec-kf-cloud">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z" />
                            </svg>
                        </div>
                        <div class="spec-kf-device-icons">
                            <span class="spec-kf-device spec-kf-device--phone"></span>
                            <span class="spec-kf-device spec-kf-device--laptop"></span>
                            <span class="spec-kf-device spec-kf-device--tablet"></span>
                        </div>
                    </div>
                    <p class="spec-kf-access-title"><?= e($kfWide['accessTitle']) ?></p>
                    <p class="spec-kf-access-sub">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                        <?= e($kfWide['accessSub']) ?>
                    </p> -->
                </div>
            </article>
        </div>
    </div>
</section>

<!-- ============ BENEFITS ============ -->
<section class="spec-ben" id="benefits" aria-labelledby="spec-ben-heading">
    <div class="wrap">
        <div class="spec-ben-main">
            <div class="spec-ben-copy reveal">
                <span class="spec-ben-badge">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M11 20A7 7 0 0113.5 6c.5 0 1 .1 1.5.3A5 5 0 0118 10a4 4 0 01.5 2 2 2 0 01-2 2h-1a2 2 0 00-2 2v1a2 2 0 01-2 2 2 2 0 01-2-2v-2.2A7 7 0 0111 20z" />
                    </svg>
                    <?= e($benBadge) ?>
                </span>
                <h2 class="spec-ben-title" id="spec-ben-heading"><?= e($benTitle) ?></h2>
                <p class="spec-ben-intro">
                    <?= e($benIntro) ?> <strong><?= e($benIntroBrand) ?></strong> <?= e($benIntroSuffix) ?>
                </p>

                <div class="spec-ben-grid">
                    <?php foreach ($benItems as [$iconKey, $title, $desc]): ?>
                        <article class="spec-ben-card reveal">
                            <div class="spec-ben-ico" aria-hidden="true">
                                <?php if ($iconKey === 'time'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <circle cx="12" cy="12" r="9" />
                                        <path d="M12 7v5l3 2" />
                                    </svg>
                                <?php elseif ($iconKey === 'paperwork'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M14 2v6h6" />
                                        <path d="M4 4l16 16" />
                                    </svg>
                                <?php elseif ($iconKey === 'experience'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <circle cx="12" cy="8" r="4" />
                                        <path d="M6 20v-1a6 6 0 0112 0v1" />
                                        <path d="M9 11l1 1 2-2M15 11l1 1" />
                                    </svg>
                                <?php elseif ($iconKey === 'efficiency'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M18 20V10M12 20V4M6 20v-6" />
                                        <path d="M12 8l4 4" />
                                    </svg>
                                <?php elseif ($iconKey === 'secure'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                        <path d="M9 12l2 2 4-4" />
                                    </svg>
                                <?php elseif ($iconKey === 'reports'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M14 2v6h6" />
                                        <circle cx="12" cy="14" r="3" />
                                    </svg>
                                    <!-- <?php elseif ($iconKey === 'followup'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                                        <path d="M13.73 21a2 2 0 01-3.46 0" />
                                    </svg> -->
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <circle cx="12" cy="8" r="4" />
                                        <path d="M6 20v-1a6 6 0 0112 0v1" />
                                        <path d="M12 12v4M10 16h4" />
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="spec-ben-card-body">
                                <h3 class="spec-ben-card-title"><?= e($title) ?></h3>
                                <p class="spec-ben-card-desc"><?= e($desc) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="spec-ben-visual reveal">
                <figure class="spec-ben-figure">
                    <!-- Add image: <img src="/assets/img/homeo-benefits-hero.webp" alt="Doctor using eClinicPro dashboard" class="spec-ben-img" loading="lazy"> -->
                    <div class="spec-ben-img-ph" role="img" aria-label="Doctor with dashboard — add image later">
                        <img src="/assets/img/Better-Management-Better-Care-Better-Practice.png" alt="Secure cloud storage" loading="lazy">
                        <!-- <div class="spec-ben-ph-dash" aria-hidden="true">
                            <div class="spec-ben-ph-sidebar"></div>
                            <div class="spec-ben-ph-main">
                                <span></span><span></span><span></span>
                            </div>
                        </div> -->
                    </div>
                </figure>
            </div>
        </div>
    </div>

    <div class="spec-ben-cta">
        <div class="wrap spec-ben-cta-inner reveal">
            <div class="spec-ben-cta-shield" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <path d="M9 12l2 2 4-4" />
                </svg>
            </div>
            <div class="spec-ben-cta-copy">
                <h3 class="spec-ben-cta-title"><?= e($benCtaTitle) ?></h3>
                <p class="spec-ben-cta-sub"><?= e($benCtaSub) ?></p>
            </div>
            <div class="spec-ben-cta-action">
                <a href="/book-a-demo" data-open-demo-modal class="spec-ben-cta-btn">
                    Book a Free Demo
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </a>
                <p class="spec-ben-cta-trust">
                    <?php foreach ($benCtaTrust as $i => $trust): ?>
                        <?php if ($i > 0): ?><span class="spec-ben-trust-dot" aria-hidden="true">✓</span><?php endif; ?>
                        <?= e($trust) ?>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============ COMPARISON ============ -->
<section class="spec-cmp" id="comparison" aria-labelledby="spec-cmp-heading">
    <div class="wrap">
        <header class="spec-cmp-head reveal">
            <span class="spec-cmp-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v4l3 2" />
                </svg>
                <?= e($cmpBadge) ?>
            </span>
            <h2 class="spec-cmp-title" id="spec-cmp-heading">
                <?= e($cmpTitle) ?> <span class="spec-cmp-brand"><?= e($cmpTitleBrand) ?></span>
            </h2>
            <p class="spec-cmp-sub"><?= e($cmpSub) ?></p>
        </header>

        <div class="spec-cmp-grid">
            <div class="spec-cmp-col spec-cmp-col--manual reveal">
                <div class="spec-cmp-col-head">
                    <div class="spec-cmp-col-ico spec-cmp-col-ico--bad" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z" />
                            <path d="M9 14l2 2 4-4" transform="rotate(45 12 12)" />
                            <path d="M4 4l16 16" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="spec-cmp-col-title spec-cmp-col-title--bad"><?= e($cmpManualTitle) ?></h3>
                        <p class="spec-cmp-col-sub"><?= e($cmpManualSub) ?></p>
                    </div>
                </div>
                <ul class="spec-cmp-list spec-cmp-list--bad">
                    <?php foreach ($cmpManualItems as [$iconKey, $title, $desc]): ?>
                        <li>
                            <span class="spec-cmp-li-ico spec-cmp-li-ico--bad" aria-hidden="true">
                                <?php if ($iconKey === 'records'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M14 2v6h6" />
                                    </svg>
                                <?php elseif ($iconKey === 'appointments'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <rect x="3" y="4" width="18" height="18" rx="2" />
                                        <path d="M16 2v4M8 2v4M3 10h18" />
                                    </svg>
                                <?php elseif ($iconKey === 'rx'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M9 15l6-6" />
                                    </svg>
                                <?php elseif ($iconKey === 'billing'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                        <path d="M2 10h20" />
                                    </svg>
                                <?php elseif ($iconKey === 'medicine'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M10 2h4v4h4v4h-4v4h-4v-4H6V6h4z" />
                                    </svg>
                                    <!-- <?php elseif ($iconKey === 'followup'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                                    </svg> -->
                                <?php elseif ($iconKey === 'reports'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M18 20V10M12 20V4M6 20v-6" />
                                    </svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M3 21h18M5 21V7l7-4 7 4v14" />
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <div>
                                <strong><?= e($title) ?> <span class="spec-cmp-warn" aria-hidden="true">!</span></strong>
                                <span><?= e($desc) ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="spec-cmp-col spec-cmp-col--table reveal">
                <div class="spec-cmp-table-wrap">
                    <table class="spec-cmp-table">
                        <thead>
                            <tr>
                                <th scope="col">Feature</th>
                                <th scope="col" class="spec-cmp-th--bad">Manual</th>
                                <th scope="col" class="spec-cmp-th--good">EClinicPro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cmpRows as [$iconKey, $feature]): ?>
                                <tr>
                                    <td>
                                        <span class="spec-cmp-feat">
                                            <span class="spec-cmp-feat-ico" aria-hidden="true">
                                                <?php if ($iconKey === 'records'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                                        <path d="M14 2v6h6" />
                                                    </svg>
                                                <?php elseif ($iconKey === 'appointments'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" />
                                                        <path d="M16 2v4M8 2v4M3 10h18" />
                                                    </svg>
                                                <?php elseif ($iconKey === 'rx'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                                        <path d="M9 15l6-6" />
                                                    </svg>
                                                <?php elseif ($iconKey === 'billing'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                                        <path d="M2 10h20" />
                                                    </svg>
                                                <?php elseif ($iconKey === 'medicine'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <path d="M10 2h4v4h4v4h-4v4h-4v-4H6V6h4z" />
                                                    </svg>
                                                    <!-- <?php elseif ($iconKey === 'followup'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                                                    </svg> -->
                                                <?php elseif ($iconKey === 'reports'): ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <path d="M18 20V10M12 20V4M6 20v-6" />
                                                    </svg>
                                                <?php else: ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                                        <path d="M3 21h18M5 21V7l7-4 7 4v14" />
                                                    </svg>
                                                <?php endif; ?>
                                            </span>
                                            <?= e($feature) ?>
                                        </span>
                                    </td>
                                    <td class="spec-cmp-cell-icon" aria-label="Not available">
                                        <span class="spec-cmp-x" aria-hidden="true">✕</span>
                                    </td>
                                    <td class="spec-cmp-cell-icon" aria-label="Available">
                                        <span class="spec-cmp-check" aria-hidden="true">✓</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="spec-cmp-col spec-cmp-col--digital reveal">
                <div class="spec-cmp-col-head">
                    <div class="spec-cmp-col-ico spec-cmp-col-ico--good" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M12 2l2 4h4l-3 3 1 5-4-2-4 2 1-5-3-3h4z" />
                            <rect x="3" y="14" width="18" height="8" rx="2" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="spec-cmp-col-title spec-cmp-col-title--good"><?= e($cmpDigitalTitle) ?></h3>
                        <p class="spec-cmp-col-sub"><?= e($cmpDigitalSub) ?></p>
                    </div>
                </div>
                <div class="spec-cmp-digital-body">
                    <ul class="spec-cmp-list spec-cmp-list--good">
                        <?php foreach ($cmpDigitalItems as $item): ?>
                            <li>
                                <span class="spec-cmp-li-check" aria-hidden="true">✓</span>
                                <span><?= e($item) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="spec-cmp-mocks">

                        <img src="/assets/img/eclinicpro-dashboard.png" alt="eClinicPro dashboard" loading="lazy">
                        <!-- Add images:
                        <img src="/assets/img/cmp-dashboard.webp" alt="eClinicPro dashboard" class="spec-cmp-mock spec-cmp-mock--desk" loading="lazy">
                        <img src="/assets/img/cmp-mobile.webp" alt="eClinicPro mobile app" class="spec-cmp-mock spec-cmp-mock--mob" loading="lazy">
                        -->
                        <!-- <div class="spec-cmp-mock spec-cmp-mock--desk" role="img" aria-label="Dashboard mockup — add image later">
                            <div class="spec-cmp-mock-dash" aria-hidden="true">
                                <div class="spec-cmp-mock-bar"></div>
                                <div class="spec-cmp-mock-panels"><span></span><span></span></div>
                            </div>
                        </div>
                        <div class="spec-cmp-mock spec-cmp-mock--mob" role="img" aria-label="Mobile app mockup — add image later">
                            <div class="spec-cmp-mock-phone" aria-hidden="true">
                                <span></span><span></span><span></span>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wrap">
        <div class="spec-cmp-cta reveal">
            <div class="spec-cmp-cta-shield" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    <path d="M9 12l2 2 4-4" />
                </svg>
            </div>
            <div class="spec-cmp-cta-copy">
                <h3 class="spec-cmp-cta-title"><?= e($cmpCtaTitle) ?></h3>
                <p class="spec-cmp-cta-sub"><?= e($cmpCtaSub) ?></p>
            </div>
            <a href="/book-a-demo" data-open-demo-modal class="spec-cmp-cta-btn">
                Book a Free Demo
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<section class="spec-faq" id="faq" aria-labelledby="spec-faq-heading">
    <div class="wrap">
        <header class="spec-faq-head reveal">
            <span class="spec-faq-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M9.5 9a2.5 2.5 0 115 0c0 2-2.5 2-2.5 4" />
                    <path d="M12 17h.01" />
                </svg>
                <?= e($faqBadge) ?>
            </span>
            <h2 class="spec-faq-title" id="spec-faq-heading">
                <?= e($faqTitle) ?> <span class="spec-faq-accent"><?= e($faqTitleAccent) ?></span>
            </h2>
            <div class="spec-faq-rule" aria-hidden="true">
                <span class="spec-faq-rule-line"></span>
                <span class="spec-faq-rule-leaf">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M11 20A7 7 0 0113.5 6c.5 0 1 .1 1.5.3A5 5 0 0118 10a4 4 0 01.5 2 2 2 0 01-2 2h-1a2 2 0 00-2 2v1a2 2 0 01-2 2 2 2 0 01-2-2v-2.2A7 7 0 0111 20z" />
                    </svg>
                </span>
                <span class="spec-faq-rule-line"></span>
            </div>
            <p class="spec-faq-sub"><?= e($faqSub) ?></p>
        </header>

        <div class="spec-faq-main">
            <div class="spec-faq-list reveal">
                <?php foreach ($faqItems as $i => [$iconKey, $question, $answer]): ?>
                    <details class="spec-faq-item" <?= $i === 0 ? ' open' : '' ?>>
                        <summary class="spec-faq-summary">
                            <span class="spec-faq-ico" aria-hidden="true">
                                <?php if ($iconKey === 'software'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <rect x="2" y="3" width="20" height="14" rx="2" />
                                        <path d="M8 21h8M12 17v4" />
                                        <circle cx="12" cy="10" r="2" />
                                    </svg>
                                <?php elseif ($iconKey === 'clinic'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M3 21h18M5 21V7l7-4 7 4v14" />
                                        <path d="M9 21v-6h6v6" />
                                    </svg>
                                <?php elseif ($iconKey === 'history'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M14 2v6h6" />
                                        <circle cx="9" cy="13" r="2" />
                                    </svg>
                                <?php elseif ($iconKey === 'billing'): ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" />
                                        <path d="M14 2v6h6" />
                                        <path d="M12 12h.01M12 16h.01" />
                                    </svg>
                                <?php else: ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                        <path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z" />
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <span class="spec-faq-q"><?= e($question) ?></span>
                            <span class="spec-faq-chevron" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 9l6 6 6-6" />
                                </svg>
                            </span>
                        </summary>
                        <div class="spec-faq-a">
                            <p><?= e($answer) ?></p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>

            <div class="spec-faq-visual reveal">
                <div class="spec-faq-leaves" aria-hidden="true"></div>
                <figure class="spec-faq-figure">
                    <!-- Add image: <img src="/assets/img/homeo-faq-hero.webp" alt="eClinicPro on laptop with homeopathy books" class="spec-faq-img" loading="lazy"> -->
                    <div class="spec-faq-img-ph" role="img" aria-label="Laptop with homeopathy books — add image later">
                        <img src="/assets/img/eClinicPro-on-laptop-bg.png" alt="eClinicPro on laptop" loading="lazy">
                        <!-- <div class="spec-faq-ph-laptop" aria-hidden="true">
                            <div class="spec-faq-ph-screen"></div>
                        </div>
                        <div class="spec-faq-ph-books" aria-hidden="true">
                            <span>Organon of Medicine</span>
                            <span>Materia Medica</span>
                            <span>Repertory</span>
                        </div> -->
                    </div>
                </figure>
            </div>
        </div>
    </div>
</section>

<!-- ============ STATS ============ -->
<!-- <section style="padding: 56px 0; background: var(--bg-2); border-top: 0.5px solid var(--line); border-bottom: 0.5px solid var(--line);">
    <div class="wrap">
        <div class="stats">
            <?php foreach ($spec['stats'] as [$v, $l]): ?>
            <div class="stat reveal">
                <div class="stat-num"><?= e($v) ?></div>
                <div class="stat-label"><?= e($l) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->

<!-- ============ FEATURES ============ -->
<!-- <section id="features" style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Built for <?= e(strtolower($spec['label'])) ?></span>
            <h2 class="h-section"><?= e($spec['featsHead'][0]) ?><br><?= e($spec['featsHead'][1]) ?></h2>
        </div>
        <div class="feat-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;">
            <?php foreach ($spec['feats'] as $i => [$icon, $t, $d]): ?>
            <div class="feat-item reveal" style="transition-delay: <?= ($i % 3) * 60 ?>ms;">
                <div class="ico" style="width: 40px; height: 40px; border-radius: 10px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; font-size: 18px; margin-bottom: 14px;"><?= $icon ?></div>
                <h4 style="font-size: 15px; font-weight: 500; margin-bottom: 6px;"><?= e($t) ?></h4>
                <p style="font-size: 13.5px; color: var(--mute); line-height: 1.55;"><?= e($d) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->

<!-- ============ WORKFLOW ============ -->
<!-- <section class="bg-grey" style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">A day in the clinic</span>
            <h2 class="h-section"><?= e($spec['workflowHead']) ?></h2>
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px; max-width: 760px; margin: 0 auto;">
            <?php foreach ($spec['workflow'] as $i => [$t, $d]): ?>
            <div class="reveal" style="display: grid; grid-template-columns: 48px 1fr; gap: 20px; padding: 24px 0; border-top: <?= $i === 0 ? 'none' : '0.5px solid var(--line)' ?>;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--teal-600); color: #fff; display: grid; place-items: center; font-weight: 500; font-size: 15px;"><?= $i + 1 ?></div>
                <div>
                    <h4 style="font-size: 17px; font-weight: 500; margin-bottom: 4px;"><?= e($t) ?></h4>
                    <p style="font-size: 14px; color: var(--mute); line-height: 1.6;"><?= e($d) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->

<!-- ============ PRICING ============ -->
<!-- <section style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Build your plan</span>
            <h2 class="h-section"><?= e($spec['pricingHead']) ?></h2>
            <p class="lede"><?= e($spec['pricingBlurb']) ?></p>
        </div>
        <div class="reveal" style="max-width: 520px; margin: 0 auto; background: var(--bg-2); border-radius: 18px; padding: 28px;">
            <div style="font-size: 12px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500; margin-bottom: 14px;">For: <?= e($spec['pricingLabel']) ?></div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach ($spec['pricingItems'] as [$item, $price]): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 0.5px solid var(--line);">
                    <span style="font-size: 14px;"><?= e($item) ?></span>
                    <span style="font-size: 14px; font-weight: 500;"><?= e($price) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--ink);">
                <span style="font-size: 14px; font-weight: 500;">Total per month</span>
                <span style="font-size: 32px; font-weight: 300; letter-spacing: -1px;">$<?= (int) $spec['pricingTotal'] ?><span style="font-size: 14px; color: var(--mute);">/mo</span></span>
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary">Configure this plan</a>
            </div>
        </div>
    </div>
</section> -->

<!-- ============ TESTIMONIALS ============ -->
<!-- <section class="bg-grey" style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">In their words</span>
            <h2 class="h-section"><?= e($spec['testimonialsHead']) ?></h2>
        </div>
        <div class="tgrid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <?php foreach ($spec['testimonials'] as $i => [$name, $loc, $quote]):
                $initials = '';
                foreach (explode(' ', $name) as $w) {
                    if ($w !== '' && ctype_upper($w[0])) {
                        $initials .= $w[0];
                        if (strlen($initials) >= 2) break;
                    }
                }
            ?>
            <div class="tcard reveal" style="transition-delay: <?= $i * 80 ?>ms; background: #fff; border: 0.5px solid var(--line); border-radius: 16px; padding: 28px;">
                <div class="stars" style="color: var(--amber); font-size: 14px; letter-spacing: 2px;">★★★★★</div>
                <blockquote style="font-size: 15px; font-weight: 300; line-height: 1.5; margin: 16px 0; color: var(--ink);">"<?= e($quote) ?>"</blockquote>
                <div class="tperson" style="display: flex; align-items: center; gap: 12px; margin-top: 18px;">
                    <div class="tavatar" style="width: 38px; height: 38px; border-radius: 50%; background: var(--teal-600); color: #fff; display: grid; place-items: center; font-weight: 500; font-size: 13px;"><?= e($initials) ?></div>
                    <div>
                        <div class="nm" style="font-size: 14px; font-weight: 500;"><?= e($name) ?></div>
                        <div class="sp" style="font-size: 12px; color: var(--mute);"><?= e($loc) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->

<!-- ============ MIGRATION ============ -->
<!-- <section style="padding: 100px 0;">
    <div class="wrap">
        <div class="section-head reveal" style="text-align: center;">
            <span class="eyebrow">Migration</span>
            <h2 class="h-section">We'll move you over.</h2>
            <p class="lede"><?= e($spec['migrationBlurb']) ?></p>
        </div>
        <div class="reveal" style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; max-width: 720px; margin: 0 auto;">
            <?php foreach ($spec['migrateFrom'] as $from): ?>
            <span style="font-size: 13px; padding: 8px 16px; background: var(--bg-2); border-radius: 999px; font-weight: 500; color: var(--ink-2);"><?= e($from) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->

<!-- ============ OTHER SPECIALTIES ============ -->
<?php
$allSpecs = require __DIR__ . '/specialty-data.php';
$otherSpecs = array_filter($allSpecs, fn($s) => $s['slug'] !== $spec['slug']);
?>
<!-- <section class="bg-grey" style="padding: 80px 0;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow">Other specialties</span>
            <h2 class="h-section" style="font-size: 28px;">Built for theirs too.</h2>
        </div>
        <div class="specialty-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; max-width: 900px; margin: 0 auto;">
            <?php foreach ($otherSpecs as $os): ?>
            <a href="/<?= e($os['slug']) ?>" class="specialty-card reveal" style="background: #fff; border: 0.5px solid var(--line); border-radius: 14px; padding: 20px; text-decoration: none; color: inherit; transition: border-color .15s, transform .15s;">
                <div style="font-size: 28px; margin-bottom: 8px;"><?= $os['icon'] ?></div>
                <div style="font-size: 14px; font-weight: 500;">For <?= e(strtolower($os['label'])) ?></div>
                <div style="font-size: 12px; color: var(--mute); margin-top: 2px;"><?= e($os['heroProof'][0]) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->

<style>
    /* ---- Specialty landing hero (mockup) ---- */
    .spec-landing-hero {
        position: relative;
        padding: 120px 0 64px;
        overflow: hidden;
        background: linear-gradient(180deg, #f7fbf9 0%, #fff 72%);
    }

    .spec-hero-glow {
        position: absolute;
        inset: 0;
        background: radial-gradient(ellipse at 72% 28%, rgba(15, 155, 110, 0.10) 0%, transparent 58%);
        pointer-events: none;
    }

    /* .spec-hero-grid {
        position: relative;
        display: grid;
        grid-template-columns: 1.05fr 1fr;
        gap: clamp(32px, 5vw, 64px);
        align-items: center;
    } */
    .spec-hero-grid {
        position: relative;
        display: grid;
        grid-template-columns: 0.9fr 1fr;
        gap: clamp(32px, 5vw, 64px);
        align-items: center;
    }

    .spec-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: var(--teal-50);
        color: var(--teal-800);
        font-size: 13px;
        font-weight: 600;
    }

    .spec-hero-badge svg {
        flex-shrink: 0;
        color: var(--teal-700);
    }

    .spec-hero-title {
        margin-top: 18px;
        font-size: clamp(30px, 4.2vw, 46px);
        font-weight: 700;
        line-height: 1.15;
        letter-spacing: -0.8px;
        color: var(--ink);
    }

    .spec-hero-accent {
        color: var(--teal-700);
        display: inline-block;
    }

    .spec-hero-rule {
        width: 72px;
        height: 4px;
        margin-top: 16px;
        border-radius: 999px;
        background: var(--teal-600);
    }

    .spec-hero-tagline {
        margin-top: 18px;
        font-size: clamp(17px, 2.2vw, 20px);
        font-weight: 600;
        color: var(--ink);
    }

    .spec-hero-tagline strong {
        color: var(--teal-700);
        font-weight: 700;
    }

    .spec-hero-body {
        margin-top: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 560px;
    }

    .spec-hero-body p {
        font-size: 15px;
        line-height: 1.65;
        color: var(--mute);
    }

    .spec-hero-ctas {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 28px;
    }

    .spec-btn-demo svg {
        flex-shrink: 0;
    }

    .spec-btn-outline {
        background: #fff;
        color: var(--teal-700);
        border: 1.5px solid var(--teal-600);
    }

    .spec-btn-outline:hover {
        background: var(--teal-50);
        border-color: var(--teal-700);
        transform: translateY(-1px);
    }

    .spec-hero-visual {
        position: relative;
        min-height: 320px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .spec-hero-circle {
        position: absolute;
        width: min(92%, 420px);
        aspect-ratio: 1;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(15, 155, 110, 0.12) 0%, rgba(15, 155, 110, 0.02) 70%);
        right: 0;
        top: 50%;
        transform: translateY(-50%);
        z-index: 0;
    }

    .spec-hero-dots {
        position: absolute;
        top: 8px;
        right: 12px;
        width: 88px;
        height: 88px;
        opacity: 0.35;
        background-image: radial-gradient(var(--teal-600) 1.5px, transparent 1.5px);
        background-size: 12px 12px;
        z-index: 1;
    }

    /* .spec-hero-figure {
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 560px;
        margin: 0;
    } */
    .spec-hero-figure {
        position: relative;
        z-index: 2;
        width: 100%;
        margin: 0;
        transform: scale(1.3);
        left: 30px;
    }

    .spec-hero-img-ph {
        width: 100%;
        aspect-ratio: 16 / 11;
        border-radius: 16px;
        background: linear-gradient(145deg, #eef6f3 0%, #dceee7 100%);
        border: 1px dashed rgba(15, 155, 110, 0.28);
    }

    .spec-hero-figure img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 16px;
    }

    .spec-hero-bar {
        padding: 40px 0 48px;
        background: #fff;
        border-bottom: 1px solid var(--line);
    }

    .spec-hero-bar-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 20px 16px;
    }

    .spec-hero-bar-item {
        text-align: center;
        padding: 8px 6px;
    }

    .spec-hero-bar-ico {
        width: 52px;
        height: 52px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: var(--teal-600);
        color: #fff;
        display: grid;
        place-items: center;
    }

    .spec-hero-bar-ico svg {
        width: 24px;
        height: 24px;
    }

    .spec-hero-bar-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--ink);
        margin-bottom: 6px;
        line-height: 1.3;
    }

    .spec-hero-bar-desc {
        font-size: 16px;
        line-height: 1.5;
        color: var(--mute);
        max-width: 180px;
        margin: 0 auto;
    }

    /* ---- Why Choose section ---- */
    .spec-why {
        padding: 72px 0 0;
        background: #fff;
    }

    .spec-why-main {
        display: grid;
        grid-template-columns: 0.7fr 1fr;
        gap: clamp(20px, 3vw, 36px);
        /* align-items: start; */
    }

    .spec-why-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 999px;
        background: #fdecec;
        color: #c62828;
        font-size: 12.5px;
        font-weight: 600;
    }

    .spec-why-badge svg {
        flex-shrink: 0;
        color: #e53935;
    }

    .spec-why-title {
        margin-top: 16px;
        font-size: clamp(26px, 3.2vw, 36px);
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.5px;
        color: #0d1b2a;
    }

    .spec-why-brand {
        color: var(--teal-700);
    }

    .spec-why-sub {
        margin-top: 12px;
        font-size: 15px;
        line-height: 1.6;
        color: var(--mute);
        max-width: 520px;
    }

    .spec-challenges-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 28px;
    }

    .spec-challenge-card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid var(--line);
        background: #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    }

    .spec-challenge-ico {
        flex-shrink: 0;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #fdecec;
        color: #e53935;
        display: grid;
        place-items: center;
    }

    .spec-challenge-ico svg {
        width: 18px;
        height: 18px;
    }

    .spec-challenge-title {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1.35;
        margin-bottom: 4px;
    }

    .spec-challenge-desc {
        font-size: 12px;
        line-height: 1.5;
        color: var(--mute);
    }

    .spec-why-flow {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding-top: 120px;
        position: relative;
    }

    .spec-why-flow::before {
        content: '';
        position: absolute;
        top: 80px;
        bottom: 40px;
        left: 50%;
        width: 2px;
        border-left: 2px dashed rgba(15, 155, 110, 0.35);
        transform: translateX(-50%);
        z-index: 0;
    }

    .spec-flow-node {
        position: relative;
        z-index: 1;
        text-align: center;
        max-width: 110px;
    }

    .spec-flow-node strong {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: var(--ink);
        margin-top: 8px;
    }

    .spec-flow-node span {
        display: block;
        font-size: 10px;
        line-height: 1.4;
        color: var(--mute);
        margin-top: 2px;
    }

    .spec-flow-ico {
        width: 44px;
        height: 44px;
        margin: 0 auto;
        border-radius: 12px;
        display: grid;
        place-items: center;
    }

    .spec-flow-ico svg {
        width: 22px;
        height: 22px;
    }

    .spec-flow-ico--warn {
        background: #fdecec;
        color: #e53935;
    }

    .spec-flow-ico--ok {
        background: var(--teal-50);
        color: var(--teal-700);
    }

    .spec-flow-arrow {
        color: var(--teal-600);
        line-height: 0;
    }

    .spec-flow-arrow svg {
        width: 36px;
        height: 72px;
    }

    .spec-why-showcase {
        min-width: 0;
    }

    .spec-showcase-stage {
        position: relative;
        height: 100%;
        min-height: 420px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .spec-showcase-stage img {
        height: 100%;
        object-fit: contain;
        object-position: center;
        width: 100%;
        display: block;
        transform: scale(1.3);
    }

    .spec-showcase-img-ph {
        width: min(100%, 340px);
        aspect-ratio: 4 / 5;
        border-radius: 16px;
        background: linear-gradient(160deg, #eef6f3 0%, #d4ebe3 100%);
        border: 1px dashed rgba(15, 155, 110, 0.28);
        position: relative;
        z-index: 1;
    }

    .spec-showcase-img {
        width: min(100%, 340px);
        height: auto;
        display: block;
        border-radius: 16px;
        position: relative;
        z-index: 1;
    }

    .spec-showcase-bubbles {
        display: block;
    }

    .spec-showcase-bubbles-mobile {
        display: none;
    }

    .spec-bubble {
        position: absolute;
        z-index: 2;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        max-width: 150px;
        padding: 8px 10px;
        border-radius: 10px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }

    .spec-bubble::after {
        content: '';
        position: absolute;
        width: 24px;
        height: 1px;
        border-top: 1px dashed rgba(15, 155, 110, 0.4);
    }

    .spec-bubble--tl {
        top: 4%;
        left: 0;
    }

    .spec-bubble--tl::after {
        right: -28px;
        top: 50%;
        width: 28px;
    }

    .spec-bubble--tc {
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        max-width: 160px;
    }

    .spec-bubble--tc::after {
        display: none;
    }

    .spec-bubble--tr {
        top: 8%;
        right: 0;
    }

    .spec-bubble--tr::after {
        left: -28px;
        top: 50%;
        width: 28px;
    }

    .spec-bubble--bl {
        bottom: 18%;
        left: 0;
    }

    .spec-bubble--bl::after {
        right: -28px;
        top: 50%;
        width: 28px;
    }

    .spec-bubble--bc {
        bottom: 4%;
        left: 50%;
        transform: translateX(-50%);
        max-width: 160px;
    }

    .spec-bubble--bc::after {
        display: none;
    }

    .spec-bubble--br {
        bottom: 12%;
        right: 0;
    }

    .spec-bubble--br::after {
        left: -28px;
        top: 50%;
        width: 28px;
    }

    .spec-bubble-ico {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--teal-600);
        color: #fff;
        display: grid;
        place-items: center;
    }

    .spec-bubble-ico svg {
        width: 14px;
        height: 14px;
    }

    .spec-bubble-text strong {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1.3;
    }

    .spec-bubble-text span {
        display: block;
        font-size: 9.5px;
        line-height: 1.4;
        color: var(--mute);
        margin-top: 2px;
    }

    .spec-why-cta {
        margin-top: 56px;
        background: linear-gradient(135deg, var(--teal-700) 0%, var(--teal-600) 55%, #12a87a 100%);
        position: relative;
        overflow: hidden;
    }

    .spec-why-cta::before,
    .spec-why-cta::after {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
        pointer-events: none;
    }

    .spec-why-cta::before {
        top: -60px;
        left: -40px;
    }

    .spec-why-cta::after {
        bottom: -80px;
        right: -30px;
    }

    .spec-why-cta-inner {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: clamp(20px, 4vw, 40px);
        align-items: center;
        padding: 36px 0;
    }

    .spec-why-cta-media {
        margin: 0;
    }

    .spec-why-cta-ph {
        width: 120px;
        height: 120px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px dashed rgba(255, 255, 255, 0.35);
    }

    .spec-why-cta-media img {
        width: 230px;
        height: auto;
        display: block;
        border-radius: 12px;
    }

    .spec-why-cta-title {
        font-size: clamp(22px, 3vw, 30px);
        font-weight: 700;
        color: #fff;
        line-height: 1.25;
    }

    .spec-why-cta-hl {
        position: relative;
        display: inline-block;
    }

    .spec-why-cta-hl::after {
        content: '';
        position: absolute;
        left: -2px;
        right: -2px;
        bottom: 2px;
        height: 8px;
        background: rgba(255, 193, 7, 0.55);
        z-index: -1;
        border-radius: 2px;
    }

    .spec-why-cta-sub {
        margin-top: 8px;
        font-size: 14px;
        line-height: 1.55;
        color: rgba(255, 255, 255, 0.88);
        max-width: 480px;
    }

    .spec-why-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 16px 28px;
        border-radius: 999px;
        background: #fff;
        color: var(--teal-700);
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
        transition: transform .15s, box-shadow .15s;
        flex-shrink: 0;
    }

    .spec-why-cta-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    /* ---- Key Features section ---- */
    .spec-kf {
        position: relative;
        padding: 80px 0 88px;
        background: #f9fafb;
        overflow: hidden;
    }

    .spec-kf-dots {
        position: absolute;
        width: 120px;
        height: 120px;
        opacity: 0.25;
        background-image: radial-gradient(var(--teal-600) 1.5px, transparent 1.5px);
        background-size: 12px 12px;
        pointer-events: none;
    }

    .spec-kf-dots--tl {
        top: 24px;
        left: 24px;
    }

    .spec-kf-dots--br {
        bottom: 24px;
        right: 24px;
    }

    .spec-kf-head {
        text-align: center;
        max-width: 760px;
        margin: 0 auto 48px;
    }

    .spec-kf-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: var(--teal-600);
        color: #fff;
        font-size: 12.5px;
        font-weight: 600;
    }

    .spec-kf-leaf {
        margin: 14px auto 0;
        color: var(--teal-600);
        line-height: 0;
    }

    .spec-kf-title {
        margin-top: 12px;
        font-size: clamp(26px, 3.5vw, 38px);
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.5px;
        color: #0d1b2a;
    }

    .spec-kf-brand {
        color: var(--teal-700);
    }

    .spec-kf-sub {
        margin-top: 12px;
        font-size: 15px;
        line-height: 1.6;
        color: var(--mute);
    }

    .spec-kf-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
    }

    .spec-kf-card {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        min-height: 260px;
    }

    .spec-kf-card-copy {
        padding: 22px 20px;
        display: flex;
        flex-direction: column;
    }

    .spec-kf-card-head {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px;
    }

    .spec-kf-card-ico {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--teal-50);
        color: var(--teal-700);
        display: grid;
        place-items: center;
    }

    .spec-kf-card-ico svg {
        width: 20px;
        height: 20px;
    }

    .spec-kf-card-title {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.35;
        color: var(--ink);
    }

    .spec-kf-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .spec-kf-list li {
        position: relative;
        padding-left: 22px;
        font-size: 12px;
        line-height: 1.45;
        color: var(--mute);
    }

    .spec-kf-list li::before {
        content: '';
        position: absolute;
        left: 0;
        top: 3px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--teal-50);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230F9B6E' stroke-width='3'%3E%3Cpath d='M5 13l4 4L19 7'/%3E%3C/svg%3E");
        background-size: 10px;
        background-repeat: no-repeat;
        background-position: center;
    }

    .spec-kf-card-visual {
        background: #f4f8f6;
        border-left: 1px solid var(--line);
        padding: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100%;
    }

    .spec-kf-card-visual img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .spec-kf-mock {
        width: 100%;
        height: 100%;
        min-height: 200px;
        border-radius: 10px;
        background: #fff;
        border: 1px dashed rgba(15, 155, 110, 0.2);
        padding: 10px;
        overflow: hidden;
    }

    .spec-mock-ui {
        font-size: 9px;
        color: var(--ink);
    }

    .spec-mock-patient-head {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-bottom: 10px;
    }

    .spec-mock-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #c8e6d8, #8fd4b5);
        flex-shrink: 0;
    }

    .spec-mock-patient-head strong {
        display: block;
        font-size: 10px;
    }

    .spec-mock-patient-head span {
        font-size: 8px;
        color: var(--mute);
    }

    .spec-mock-menu {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .spec-mock-menu span {
        padding: 5px 8px;
        background: var(--bg-2);
        border-radius: 6px;
        font-size: 8.5px;
    }

    .spec-mock-cal-head {
        font-weight: 700;
        font-size: 10px;
        margin-bottom: 6px;
    }

    .spec-mock-cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        margin-bottom: 8px;
    }

    .spec-mock-cal-grid span {
        text-align: center;
        font-size: 7px;
        padding: 2px 0;
        border-radius: 3px;
    }

    .spec-mock-cal-grid span.is-active {
        background: var(--teal-600);
        color: #fff;
        font-weight: 700;
    }

    .spec-mock-appts {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .spec-mock-appts div {
        display: flex;
        gap: 6px;
        font-size: 8px;
        padding: 4px 6px;
        background: var(--bg-2);
        border-radius: 4px;
    }

    .spec-mock-appts span {
        color: var(--teal-700);
        font-weight: 600;
        white-space: nowrap;
    }

    .spec-mock-rx-head {
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: 700;
        font-size: 10px;
        margin-bottom: 4px;
    }

    .spec-mock-rx-head span {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        background: var(--teal-600);
        color: #fff;
        display: grid;
        place-items: center;
        font-size: 9px;
    }

    .spec-mock-rx-meta {
        font-size: 8px;
        color: var(--mute);
        margin-bottom: 6px;
    }

    .spec-mock-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8px;
        margin-bottom: 6px;
    }

    .spec-mock-table th,
    .spec-mock-table td {
        padding: 3px 4px;
        text-align: left;
        border-bottom: 1px solid var(--line);
    }

    .spec-mock-table th {
        color: var(--mute);
        font-weight: 600;
    }

    .spec-mock-sign {
        font-size: 8px;
        font-style: italic;
        color: var(--teal-700);
        text-align: right;
    }

    .spec-mock-inv-head {
        display: flex;
        justify-content: space-between;
        font-size: 9px;
        margin-bottom: 8px;
    }

    .spec-mock-inv-row {
        display: flex;
        justify-content: space-between;
        font-size: 8px;
        padding: 3px 0;
        border-bottom: 1px solid var(--line);
    }

    .spec-mock-inv-total {
        font-size: 10px;
        margin-top: 8px;
        text-align: right;
    }

    .spec-mock-inv-total strong {
        color: var(--teal-700);
    }

    .spec-mock-inv-foot {
        display: flex;
        justify-content: space-between;
        font-size: 8px;
        color: var(--mute);
        margin-top: 4px;
    }

    .spec-mock-stock-head {
        font-weight: 700;
        font-size: 10px;
        margin-bottom: 6px;
    }

    .spec-mock-alert {
        margin-top: 8px;
        padding: 5px 8px;
        border-radius: 6px;
        background: #fdecec;
        color: #c62828;
        font-size: 8px;
        font-weight: 600;
    }

    .spec-mock-chart {
        height: 56px;
        margin: 8px 0;
        border-radius: 6px;
        background: linear-gradient(180deg, rgba(15, 155, 110, 0.15) 0%, transparent 100%);
        position: relative;
    }

    .spec-mock-chart::after {
        content: '';
        position: absolute;
        left: 8%;
        right: 8%;
        bottom: 12px;
        height: 2px;
        background: var(--teal-600);
        border-radius: 2px;
        transform: skewY(-8deg);
        box-shadow: 0 -18px 0 -4px rgba(15, 155, 110, 0.35), 0 -32px 0 -8px rgba(15, 155, 110, 0.2);
    }

    .spec-mock-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 6px;
    }

    .spec-mock-stats div {
        padding: 5px;
        background: var(--bg-2);
        border-radius: 5px;
        text-align: center;
    }

    .spec-mock-stats strong {
        display: block;
        font-size: 9px;
        color: var(--teal-700);
    }

    .spec-mock-stats span {
        font-size: 7px;
        color: var(--mute);
    }

    .spec-kf-card--wide {
        grid-column: 1 / -1;
        grid-template-columns: 1.1fr 1fr 0.9fr 1fr;
        min-height: auto;
    }

    .spec-kf-wide-copy {
        padding: 24px 22px;
    }

    .spec-kf-wide-branches,
    .spec-kf-wide-users,
    .spec-kf-wide-access {
        padding: 20px 16px;
        border-left: 1px solid var(--line);
        background: #fafcfb;
    }

    .spec-kf-branch-diagram {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        min-height: 180px;
        justify-content: center;
    }

    .spec-kf-branch {
        width: 100%;
        max-width: 160px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px dashed rgba(15, 155, 110, 0.3);
        background: #fff;
        text-align: center;
    }

    .spec-kf-branch.is-head {
        background: var(--teal-600);
        color: #fff;
        border-style: solid;
        border-color: var(--teal-700);
    }

    .spec-kf-branch strong {
        display: block;
        font-size: 11px;
    }

    .spec-kf-branch span {
        display: block;
        font-size: 10px;
        opacity: 0.85;
        margin-top: 2px;
    }

    .spec-kf-branch:not(.is-head)::before {
        content: '';
        display: block;
        width: 2px;
        height: 10px;
        margin: -10px auto 6px;
        border-left: 2px dashed rgba(15, 155, 110, 0.4);
    }

    .spec-kf-users-title {
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--ink);
    }

    .spec-kf-user {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .spec-kf-user-av {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: linear-gradient(135deg, #dceee7, #a8d8c4);
        flex-shrink: 0;
    }

    .spec-kf-user-info strong {
        display: block;
        font-size: 11px;
    }

    .spec-kf-role {
        display: inline-block;
        margin-top: 2px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 600;
    }

    .spec-kf-role--admin {
        background: var(--teal-50);
        color: var(--teal-800);
    }

    .spec-kf-role--doctor {
        background: #e3f0fc;
        color: #1565c0;
    }

    .spec-kf-role--nurse {
        background: #f3e8fd;
        color: #7b1fa2;
    }

    .spec-kf-role--reception {
        background: #fff3e0;
        color: #e65100;
    }

    .spec-kf-devices {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        min-height: 100px;
        margin-bottom: 14px;
    }

    .spec-kf-cloud {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--teal-50);
        color: var(--teal-700);
        display: grid;
        place-items: center;
    }

    .spec-kf-cloud svg {
        width: 24px;
        height: 24px;
    }

    .spec-kf-device-icons {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }

    .spec-kf-device {
        display: block;
        border-radius: 4px;
        border: 1.5px solid var(--teal-600);
        background: #fff;
    }

    .spec-kf-device--phone {
        width: 14px;
        height: 22px;
    }

    .spec-kf-device--laptop {
        width: 28px;
        height: 18px;
        border-radius: 3px;
    }

    .spec-kf-device--tablet {
        width: 18px;
        height: 24px;
    }

    .spec-kf-access-title {
        font-size: 14px;
        font-weight: 700;
        line-height: 1.4;
        color: var(--teal-700);
        margin-bottom: 8px;
    }

    .spec-kf-access-sub {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        color: var(--mute);
    }

    .spec-kf-access-sub svg {
        color: var(--teal-600);
        flex-shrink: 0;
    }

    /* ---- Benefits section ---- */
    .spec-ben {
        padding: 0 0 0;
        background: #fff;
    }

    .spec-ben-main {
        display: grid;
        grid-template-columns: 1.15fr 0.85fr;
        gap: clamp(28px, 4vw, 60px);
        align-items: start;
        padding: 72px 0 56px;
    }

    .spec-ben-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: var(--teal-50);
        color: var(--teal-800);
        font-size: 12.5px;
        font-weight: 600;
    }

    .spec-ben-badge svg {
        color: var(--teal-700);
        flex-shrink: 0;
    }

    .spec-ben-title {
        margin-top: 16px;
        font-size: clamp(24px, 3vw, 34px);
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.4px;
        color: #0d1b2a;
        max-width: 560px;
    }

    .spec-ben-intro {
        margin-top: 12px;
        font-size: 15px;
        color: var(--ink);
        font-weight: 500;
    }

    .spec-ben-intro strong {
        color: var(--teal-700);
        font-weight: 700;
    }

    .spec-ben-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 28px;
    }

    .spec-ben-card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 16px;
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
    }

    .spec-ben-ico {
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--teal-600);
        color: #fff;
        display: grid;
        place-items: center;
    }

    .spec-ben-ico svg {
        width: 20px;
        height: 20px;
    }

    .spec-ben-card-title {
        font-size: 13.5px;
        font-weight: 700;
        color: var(--teal-700);
        line-height: 1.35;
        margin-bottom: 4px;
    }

    .spec-ben-card-desc {
        font-size: 12px;
        line-height: 1.5;
        color: var(--mute);
    }

    .spec-ben-visual {
        position: sticky;
        top: 100px;
    }

    .spec-ben-figure {
        margin: 0;
    }

    .spec-ben-img-ph {
        width: 100%;
        min-height: 680px;
        /* border-radius: 18px; */
        /* background: linear-gradient(160deg, #eef6f3 0%, #dceee7 100%); */
        /* border: 1px dashed rgba(15, 155, 110, 0.28); */
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        overflow: hidden;
        transform: scale(1.3);
    }

    .spec-ben-img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 18px;
    }

    .spec-ben-ph-dash {
        width: 90%;
        max-width: 320px;
        aspect-ratio: 4 / 3;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1);
        display: flex;
        overflow: hidden;
        opacity: 0.7;
    }

    .spec-ben-ph-sidebar {
        width: 28%;
        background: #1a2e28;
    }

    .spec-ben-ph-main {
        flex: 1;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .spec-ben-ph-main span {
        height: 12px;
        border-radius: 4px;
        background: var(--bg-2);
    }

    .spec-ben-ph-main span:first-child {
        width: 60%;
    }

    .spec-ben-ph-main span:nth-child(2) {
        width: 80%;
    }

    .spec-ben-ph-main span:nth-child(3) {
        width: 45%;
    }

    .spec-ben-cta {
        background: linear-gradient(135deg, #e8f5f0 0%, #d4ede4 50%, #c6e8dc 100%);
        border-top: 1px solid rgba(15, 155, 110, 0.12);
    }

    .spec-ben-cta-inner {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: clamp(20px, 4vw, 36px);
        align-items: center;
        padding: 36px 0;
    }

    .spec-ben-cta-shield {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #fff;
        color: var(--teal-700);
        display: grid;
        place-items: center;
        box-shadow: 0 6px 20px rgba(15, 155, 110, 0.12);
        flex-shrink: 0;
    }

    .spec-ben-cta-shield svg {
        width: 34px;
        height: 34px;
    }

    .spec-ben-cta-title {
        font-size: clamp(18px, 2.4vw, 22px);
        font-weight: 700;
        line-height: 1.35;
        color: var(--teal-800);
    }

    .spec-ben-cta-sub {
        margin-top: 8px;
        font-size: 14px;
        line-height: 1.55;
        color: var(--teal-700);
        max-width: 520px;
    }

    .spec-ben-cta-action {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
        flex-shrink: 0;
    }

    .spec-ben-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 14px 26px;
        border-radius: 999px;
        background: var(--teal-700);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        white-space: nowrap;
        transition: transform .15s, box-shadow .15s;
    }

    .spec-ben-cta-btn:hover {
        background: var(--teal-800);
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(15, 155, 110, 0.25);
    }

    .spec-ben-cta-trust {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 600;
        color: var(--teal-700);
    }

    .spec-ben-trust-dot {
        font-size: 10px;
        opacity: 0.7;
    }

    /* ---- Comparison section ---- */
    .spec-cmp {
        padding: 72px 0 56px;
        background: #f9fafb;
    }

    .spec-cmp-head {
        text-align: center;
        max-width: 720px;
        margin: 0 auto 40px;
    }

    .spec-cmp-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: var(--teal-50);
        color: var(--teal-800);
        font-size: 12.5px;
        font-weight: 600;
    }

    .spec-cmp-badge svg {
        color: var(--teal-700);
        flex-shrink: 0;
    }

    .spec-cmp-title {
        margin-top: 16px;
        font-size: clamp(26px, 3.5vw, 36px);
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.5px;
        color: #0d1b2a;
    }

    .spec-cmp-brand {
        color: var(--teal-700);
    }

    .spec-cmp-sub {
        margin-top: 12px;
        font-size: 15px;
        line-height: 1.6;
        color: var(--mute);
    }

    .spec-cmp-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr) minmax(0, 1.15fr);
        gap: 16px;
        align-items: stretch;
    }

    .spec-cmp-col {
        border-radius: 16px;
        padding: 20px 18px;
        min-width: 0;
    }

    .spec-cmp-col--manual {
        background: #fef2f2;
        border: 1px solid #fecaca;
    }

    .spec-cmp-col--table {
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        padding: 16px 12px;
    }

    .spec-cmp-col--digital {
        background: #ecf8f3;
        border: 1px solid #b8e6d4;
    }

    .spec-cmp-col-head {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .spec-cmp-col-ico {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }

    .spec-cmp-col-ico svg {
        width: 22px;
        height: 22px;
    }

    .spec-cmp-col-ico--bad {
        background: #fde8e8;
        color: #dc3545;
    }

    .spec-cmp-col-ico--good {
        background: var(--teal-50);
        color: var(--teal-700);
    }

    .spec-cmp-col-title {
        font-size: 15px;
        font-weight: 700;
        line-height: 1.3;
    }

    .spec-cmp-col-title--bad {
        color: #c62828;
    }

    .spec-cmp-col-title--good {
        color: var(--teal-800);
    }

    .spec-cmp-col-sub {
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--mute);
        margin-top: 2px;
    }

    .spec-cmp-list {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .spec-cmp-list--bad li {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        font-size: 11px;
        line-height: 1.45;
    }

    .spec-cmp-list--bad strong {
        display: block;
        font-size: 12px;
        color: #b71c1c;
        margin-bottom: 2px;
    }

    .spec-cmp-list--bad span:not(.spec-cmp-li-ico) {
        color: var(--mute);
    }

    .spec-cmp-warn {
        display: inline-grid;
        place-items: center;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #ef5350;
        color: #fff;
        font-size: 9px;
        font-weight: 800;
        vertical-align: middle;
        margin-left: 2px;
    }

    .spec-cmp-li-ico {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: grid;
        place-items: center;
    }

    .spec-cmp-li-ico svg {
        width: 14px;
        height: 14px;
    }

    .spec-cmp-li-ico--bad {
        background: #fde8e8;
        color: #e53935;
    }

    .spec-cmp-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .spec-cmp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11.5px;
    }

    .spec-cmp-table th,
    .spec-cmp-table td {
        padding: 10px 8px;
        border-bottom: 1px solid var(--line);
        text-align: left;
    }

    .spec-cmp-table th {
        font-size: 11px;
        font-weight: 700;
        color: var(--ink);
        background: var(--bg-2);
    }

    .spec-cmp-table th:first-child {
        border-radius: 8px 0 0 0;
    }

    .spec-cmp-table th:last-child {
        border-radius: 0 8px 0 0;
    }

    .spec-cmp-th--bad {
        color: #c62828;
    }

    .spec-cmp-th--good {
        color: var(--teal-700);
    }

    .spec-cmp-table tbody tr:last-child td {
        border-bottom: none;
    }

    .spec-cmp-feat {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: var(--ink);
        white-space: nowrap;
    }

    .spec-cmp-feat-ico {
        width: 26px;
        height: 26px;
        border-radius: 6px;
        background: var(--teal-50);
        color: var(--teal-700);
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }

    .spec-cmp-feat-ico svg {
        width: 14px;
        height: 14px;
    }

    .spec-cmp-cell-icon {
        text-align: center;
        width: 56px;
    }

    .spec-cmp-x {
        display: inline-grid;
        place-items: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #fde8e8;
        color: #e53935;
        font-size: 12px;
        font-weight: 700;
    }

    .spec-cmp-check {
        display: inline-grid;
        place-items: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--teal-50);
        color: var(--teal-700);
        font-size: 12px;
        font-weight: 700;
    }

    .spec-cmp-digital-body {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: start;
    }

    .spec-cmp-list--good li {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        font-size: 11px;
        line-height: 1.45;
        color: var(--ink-2);
        margin-bottom: 2px;
    }

    .spec-cmp-li-check {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: var(--teal-600);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        display: grid;
        place-items: center;
        margin-top: 1px;
    }

    .spec-cmp-mocks {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: center;
        flex-shrink: 0;
    }

    .spec-cmp-mock {
        border-radius: 10px;
        background: #fff;
        border: 1px dashed rgba(15, 155, 110, 0.28);
        overflow: hidden;
    }

    .spec-cmp-mock img {
        width: 100%;
        height: auto;
        display: block;
    }

    .spec-cmp-mock--desk {
        width: 120px;
        min-height: 80px;
        padding: 8px;
    }

    .spec-cmp-mock--mob {
        width: 56px;
        min-height: 96px;
        padding: 6px;
    }

    .spec-cmp-mock-dash {
        height: 100%;
    }

    .spec-cmp-mock-bar {
        height: 8px;
        background: #1a2e28;
        border-radius: 3px;
        margin-bottom: 6px;
    }

    .spec-cmp-mock-panels {
        display: flex;
        gap: 4px;
    }

    .spec-cmp-mock-panels span {
        flex: 1;
        height: 48px;
        background: var(--bg-2);
        border-radius: 4px;
    }

    .spec-cmp-mock-phone {
        display: flex;
        flex-direction: column;
        gap: 5px;
        padding: 4px 2px;
    }

    .spec-cmp-mock-phone span {
        height: 10px;
        background: var(--bg-2);
        border-radius: 3px;
    }

    .spec-cmp-mock-phone span:first-child {
        width: 70%;
    }

    .spec-cmp-cta {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: clamp(16px, 3vw, 28px);
        align-items: center;
        margin-top: 28px;
        padding: 24px 28px;
        border-radius: 16px;
        background: linear-gradient(135deg, #e8f5f0 0%, #d4ede4 100%);
        border: 1px solid rgba(15, 155, 110, 0.15);
    }

    .spec-cmp-cta-shield {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #fff;
        color: var(--teal-700);
        display: grid;
        place-items: center;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(15, 155, 110, 0.12);
    }

    .spec-cmp-cta-shield svg {
        width: 26px;
        height: 26px;
    }

    .spec-cmp-cta-title {
        font-size: clamp(16px, 2vw, 20px);
        font-weight: 700;
        color: var(--teal-800);
        line-height: 1.3;
    }

    .spec-cmp-cta-sub {
        margin-top: 4px;
        font-size: 13px;
        line-height: 1.5;
        color: var(--teal-700);
    }

    .spec-cmp-cta-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 999px;
        background: var(--teal-700);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        white-space: nowrap;
        transition: transform .15s, box-shadow .15s;
        flex-shrink: 0;
    }

    .spec-cmp-cta-btn:hover {
        background: var(--teal-800);
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(15, 155, 110, 0.25);
    }

    /* ---- FAQ section ---- */
    .spec-faq {
        padding: 72px 0 80px;
        background: #fff;
    }

    .spec-faq-head {
        text-align: center;
        max-width: 720px;
        margin: 0 auto 48px;
    }

    .spec-faq-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 999px;
        background: var(--teal-50);
        color: var(--teal-800);
        font-size: 12.5px;
        font-weight: 600;
    }

    .spec-faq-badge svg {
        color: var(--teal-700);
        flex-shrink: 0;
    }

    .spec-faq-title {
        margin-top: 16px;
        font-size: clamp(26px, 3.5vw, 38px);
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.5px;
        color: #0d1b2a;
    }

    .spec-faq-accent {
        color: var(--teal-700);
    }

    .spec-faq-rule {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        margin-top: 16px;
    }

    .spec-faq-rule-line {
        width: 48px;
        height: 3px;
        border-radius: 999px;
        background: var(--teal-600);
    }

    .spec-faq-rule-leaf {
        color: var(--teal-600);
        line-height: 0;
        display: grid;
        place-items: center;
    }

    .spec-faq-sub {
        margin-top: 14px;
        font-size: 15px;
        line-height: 1.6;
        color: var(--mute);
    }

    .spec-faq-main {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);
        gap: clamp(28px, 4vw, 48px);
        align-items: start;
    }

    .spec-faq-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .spec-faq-item {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 14px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .spec-faq-summary {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 20px;
        cursor: pointer;
        list-style: none;
    }

    .spec-faq-summary::-webkit-details-marker {
        display: none;
    }

    .spec-faq-ico {
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 2px solid var(--teal-100);
        background: var(--teal-50);
        color: var(--teal-700);
        display: grid;
        place-items: center;
    }

    .spec-faq-ico svg {
        width: 20px;
        height: 20px;
    }

    .spec-faq-q {
        flex: 1;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.4;
        color: var(--teal-800);
        text-align: left;
    }

    .spec-faq-chevron {
        flex-shrink: 0;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--teal-50);
        color: var(--teal-700);
        display: grid;
        place-items: center;
        transition: transform .2s ease;
    }

    .spec-faq-chevron svg {
        width: 16px;
        height: 16px;
    }

    .spec-faq-item[open] .spec-faq-chevron {
        transform: rotate(180deg);
    }

    .spec-faq-a {
        padding: 0 20px 18px 76px;
    }

    .spec-faq-a p {
        font-size: 13px;
        line-height: 1.6;
        color: var(--mute);
    }

    .spec-faq-visual {
        position: relative;
        min-height: 400px;
    }

    .spec-faq-leaves {
        position: absolute;
        inset: 0;
        opacity: 0.12;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'%3E%3Cpath fill='none' stroke='%230F9B6E' stroke-width='1.5' d='M60 20c-20 30-30 50-30 70 0 15 13 20 30 10 17 10 30 5 30-10 0-20-10-40-30-70z'/%3E%3C/svg%3E");
        background-size: 100px 100px;
        background-repeat: repeat;
        pointer-events: none;
    }

    .spec-faq-figure {
        margin: 0;
        position: relative;
        z-index: 1;
    }

    .spec-faq-img-ph {
        width: 100%;
        min-height: 400px;
        /* border-radius: 18px;
        background: linear-gradient(160deg, #f4faf7 0%, #e8f3ee 100%);
        border: 1px dashed rgba(15, 155, 110, 0.28); */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        /* padding: 24px; */
        gap: 16px;
        position: relative;
    }

    .spec-faq-img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 18px;
    }

    .spec-faq-ph-laptop {
        width: 75%;
        max-width: 280px;
        aspect-ratio: 16 / 10;
        background: #c0c0c0;
        border-radius: 10px 10px 0 0;
        padding: 8px 8px 0;
        margin-top: auto;
    }

    .spec-faq-ph-screen {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #1a2e28 0%, #2a4a40 40%, #fff 40%);
        border-radius: 4px 4px 0 0;
    }

    .spec-faq-ph-books {
        display: flex;
        gap: 6px;
        align-items: flex-end;
    }

    .spec-faq-ph-books span {
        display: block;
        width: 36px;
        padding: 28px 4px 6px;
        font-size: 6px;
        font-weight: 700;
        text-align: center;
        line-height: 1.2;
        color: #fff;
        border-radius: 3px 3px 0 0;
        writing-mode: vertical-rl;
        transform: rotate(180deg);
    }

    .spec-faq-ph-books span:nth-child(1) {
        background: #c4a574;
        height: 72px;
    }

    .spec-faq-ph-books span:nth-child(2) {
        background: #6aab82;
        height: 64px;
    }

    .spec-faq-ph-books span:nth-child(3) {
        background: #2d7a52;
        height: 80px;
    }

    @media (max-width: 1100px) {
        .spec-hero-bar-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 28px 20px;
        }

        .spec-hero-bar-desc {
            max-width: 220px;
        }

        .spec-why-main {
            grid-template-columns: 1fr;
        }

        .spec-why-flow {
            flex-direction: row;
            padding-top: 0;
            justify-content: center;
            gap: 16px;
            margin: 8px 0 16px;
        }

        .spec-why-flow::before {
            top: 50%;
            bottom: auto;
            left: 15%;
            right: 15%;
            width: auto;
            height: 0;
            border-left: none;
            border-top: 2px dashed rgba(15, 155, 110, 0.35);
            transform: translateY(-50%);
        }

        .spec-flow-arrow svg {
            width: 48px;
            height: 36px;
            transform: rotate(-90deg);
        }

        .spec-showcase-stage {
            min-height: 380px;
        }

        .spec-bubble {
            max-width: 130px;
        }

        .spec-kf-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .spec-kf-card--wide {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto auto;
        }

        .spec-kf-wide-copy {
            grid-column: 1 / -1;
        }

        .spec-kf-wide-access {
            grid-column: 1 / -1;
        }

        .spec-ben-main {
            grid-template-columns: 1fr;
        }

        .spec-ben-visual {
            position: static;
            order: 2;
        }

        .spec-ben-copy {
            order: 1;
        }

        .spec-ben-img-ph {
            min-height: 360px;
        }

        .spec-cmp-grid {
            grid-template-columns: 1fr;
        }

        .spec-cmp-digital-body {
            grid-template-columns: 1fr;
        }

        .spec-cmp-mocks {
            flex-direction: row;
            justify-content: center;
            margin-top: 8px;
        }

        .spec-faq-main {
            grid-template-columns: 1fr;
        }

        .spec-faq-visual {
            order: 2;
            min-height: 320px;
        }

        .spec-faq-list {
            order: 1;
        }

        .spec-faq-img-ph {
            min-height: 320px;
        }

        .spec-hero-grid {
            grid-template-columns: 1.5fr 1fr;
            align-items: end;
        }

        .spec-hero-figure {
            transform: scale(1.4);
            left: -30px;
        }

        .spec-showcase-stage img {
            transform: scale(1);
        }

    }

    @media (max-width: 900px) {
        .spec-landing-hero {
            padding: 100px 0 48px;
        }

        .spec-hero-grid {
            grid-template-columns: 1fr;
            gap: 40px;
        }

        .spec-hero-visual {
            min-height: 260px;
            order: 2;
        }

        .spec-hero-copy {
            order: 1;
        }

        .spec-hero-circle {
            right: 50%;
            transform: translate(50%, -50%);
        }

        .spec-challenges-grid {
            grid-template-columns: 1fr;
        }

        .spec-showcase-bubbles {
            display: none;
        }

        .spec-showcase-bubbles-mobile {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .spec-bubble-mobile {
            padding: 14px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: var(--bg-2);
        }

        .spec-bubble-mobile strong {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .spec-bubble-mobile span {
            font-size: 12px;
            line-height: 1.45;
            color: var(--mute);
        }

        .spec-showcase-stage {
            min-height: 280px;
        }

        .spec-why-cta-inner {
            grid-template-columns: 1fr;
            text-align: center;
            padding: 32px 0;
        }

        .spec-why-cta-media {
            justify-self: center;
        }

        .spec-why-cta-sub {
            margin-left: auto;
            margin-right: auto;
        }

        .spec-why-cta-btn {
            justify-self: center;
        }

        .spec-kf-grid {
            grid-template-columns: 1fr;
        }

        .spec-kf-card {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .spec-kf-card-visual {
            border-left: none;
            border-top: 1px solid var(--line);
            min-height: 200px;
        }

        .spec-kf-card--wide {
            grid-template-columns: 1fr;
        }

        .spec-kf-wide-branches,
        .spec-kf-wide-users,
        .spec-kf-wide-access {
            border-left: none;
            border-top: 1px solid var(--line);
        }

        .spec-ben-grid {
            grid-template-columns: 1fr;
        }

        .spec-ben-cta-inner {
            grid-template-columns: 1fr;
            text-align: center;
            padding: 32px 0;
        }

        .spec-ben-cta-shield {
            justify-self: center;
        }

        .spec-ben-cta-sub {
            margin-left: auto;
            margin-right: auto;
        }

        .spec-ben-cta-action {
            align-items: center;
            width: 100%;
        }

        .spec-ben-cta-trust {
            justify-content: center;
            flex-wrap: wrap;
        }

        .spec-cmp-cta {
            grid-template-columns: 1fr;
            text-align: center;
            padding: 24px 20px;
        }

        .spec-cmp-cta-shield {
            justify-self: center;
        }

        .spec-cmp-cta-btn {
            justify-self: center;
        }

        .spec-cmp-feat {
            white-space: normal;
        }

        .spec-faq-a {
            padding-left: 20px;
            padding-right: 16px;
        }

        .spec-hero-figure {
            transform: scale(1);
            left: 0;
        }

    }

    @media (max-width: 640px) {
        .spec-hero-bar-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 24px 16px;
        }

        .spec-hero-ctas {
            flex-direction: column;
        }

        .spec-hero-ctas .btn {
            width: 100%;
            justify-content: center;
        }

        .spec-why {
            padding-top: 56px;
        }

        .spec-why-flow {
            flex-direction: column;
            gap: 10px;
        }

        .spec-why-flow::before {
            display: none;
        }

        .spec-flow-arrow svg {
            transform: none;
            width: 36px;
            height: 56px;
        }

        .spec-showcase-bubbles-mobile {
            grid-template-columns: 1fr;
        }

        .spec-why-cta-btn {
            width: 100%;
            justify-content: center;
        }

        .spec-kf {
            padding: 56px 0 64px;
        }

        .spec-kf-head {
            margin-bottom: 32px;
        }

        .spec-ben-main {
            padding: 48px 0 40px;
        }

        .spec-ben-img-ph {
            min-height: 280px;
        }

        .spec-ben-cta-btn {
            width: 100%;
            justify-content: center;
        }

        .spec-cmp {
            padding: 48px 0 40px;
        }

        .spec-cmp-cta-btn {
            width: 100%;
            justify-content: center;
        }

        .spec-faq {
            padding: 48px 0 56px;
        }

        .spec-faq-head {
            margin-bottom: 32px;
        }

        .spec-faq-img-ph {
            min-height: 260px;
        }
    }

    @media (max-width: 800px) {

        .feat-grid,
        .tgrid {
            grid-template-columns: 1fr !important;
        }

        .specialty-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
    }

    @media (max-width: 420px) {
        .spec-hero-bar-grid {
            grid-template-columns: 1fr;
        }

        .spec-hero-bar-desc {
            max-width: 280px;
        }
    }
</style>

<?php
// Use the final CTA from the specialty data instead of the default
$hideFinalCta = true;
?>
<section class="cta-block" id="cta">
    <div class="wrap reveal">
        <h2><?= e($spec['ctaTitle']) ?></h2>
        <p class="lede"><?= e($spec['ctaSub']) ?><br>
            No credit card. No phone-tag with sales. Just a clean clinic.</p>
        <div class="hero-ctas">
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">Start free — no card needed</a>
            <a href="/book-a-demo" data-open-demo-modal class="btn btn-ghost-dark btn-lg">Schedule a 15-min demo →</a>
        </div>
    </div>
</section>

<?php
$demoDefaultSpecialty = $spec['label'] ?? 'General practice';
require __DIR__ . '/demo-modal.php';
require __DIR__ . '/footer.php'; ?>