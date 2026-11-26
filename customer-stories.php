<?php
// =====================================================================
// customer-stories.php — case studies
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Customer Stories — eClinicPro';
$metaDesc = 'Two clinics, two specialties. The hard numbers and human stories behind clinics that switched to eClinicPro.';
$activePage = '';

$stories = [
    [
        'id' => 'sunrise',
        'name' => 'Sunrise Family Clinic',
        'person' => 'Dr. Aarav Sharma · GP',
        'location' => 'Mumbai, India',
        'yrs' => 'Customer since 2024',
        'quote' => 'I went from 40 patients a day and chaos at the front desk to 55 patients a day and a calm clinic. The QR cards alone saved my nurses an hour every morning.',
        'metrics' => [['+38%', 'Patient volume'], ['8 min', 'Avg wait time'], ['0', 'Paper registers']],
        'body' => [
            "Sunrise Family Clinic in Bandra ran on paper for 11 years. Three OPD doctors, four nurses, one receptionist, and a queue that stretched into the corridor by 10am. Dr. Aarav Sharma had tried three EMRs before; none stuck.",
            "\"They were all built for hospitals. We're a clinic. We just need to see patients fast and not lose track of them.\" The team moved to eClinicPro over two weekends, importing 14 years of paper records via a phone-scan + OCR flow.",
            "Within a quarter, the front desk had cut check-in time by 80% using QR patient cards — a small printed card a patient brings, scanned with any phone. The chart loads in under 200ms. Vitals are captured on a tablet by the nurse before the patient sits down with the doctor.",
            "\"My favorite part is that I read fewer screens and see more patients. The system shows me what I need: vitals, last visit, allergies, and the suggested Rx template. Three minutes per follow-up instead of seven.\"",
        ],
        'modules' => ['Patient records', 'QR patient card', 'Digital Rx', 'Vitals & charts', 'Billing'],
        'tint' => 'linear-gradient(135deg, #C6EBDE, #2DC08A)',
        'initials' => 'AS',
    ],
    [
        'id' => 'priya',
        'name' => 'Dr. Priya Iyer Homeopathy',
        'person' => 'Dr. Priya Iyer · Homeopath',
        'location' => 'Pune, India',
        'yrs' => 'Customer since 2025',
        'quote' => "I'd given up on software ever understanding homeopathy. eClinicPro is the first time I've felt seen as a professional, not converted into a generic record.",
        'metrics' => [['15 min', 'Per case taken'], ['3,200', 'Remedies indexed'], ['92%', 'Follow-up adherence']],
        'body' => [
            "Dr. Priya Iyer practices classical homeopathy in Pune. Her case files used to live in physical Word documents — one per patient, sometimes 60-pages long. She'd email them to herself for backup.",
            "\"Every time I'd evaluate a software, the case-taking form was a joke. Five fields: chief complaint, examination, diagnosis, prescription. That's not how I work.\"",
            "eClinicPro's long-form case-taking template surprised her: mental generals, physical generals, particulars, modalities, aggravation/amelioration — built in, editable. The 3,200-remedy database with antidote rules earned her trust within a week.",
            "The antidote engine has caught her three times already. \"A patient was on Lycopodium and I almost gave Coffea for a follow-up symptom. The system warned me — they're antidotes. I'd have wasted three weeks of treatment.\"",
        ],
        'modules' => ['Patient records', 'Remedy database', 'Digital Rx', 'Billing'],
        'tint' => 'linear-gradient(135deg, #F0E0F8, #BF5AF2)',
        'initials' => 'PI',
    ],
];

require __DIR__ . '/partials/header.php';
?>

<section style="padding: 140px 0 60px; text-align: center; position: relative; overflow: hidden;">
    <div style="position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%); pointer-events: none;"></div>
    <div class="wrap" style="position: relative; max-width: 820px;">
        <span class="eyebrow" style="display: block; margin-bottom: 16px;">Customer stories</span>
        <h1 class="h-display" style="font-size: clamp(40px, 5.5vw, 60px); letter-spacing: -1.3px;">Clinics that switched. And never looked back.</h1>
        <p class="lede" style="font-size: 19px; margin-top: 22px; max-width: 640px; margin-left: auto; margin-right: auto;">
            Two clinics, two specialties. The hard numbers and the human stories behind them.
        </p>
    </div>
</section>

<section style="padding-top: 60px;">
    <div class="wrap" style="max-width: 1280px;">
        <div style="display: flex; flex-direction: column; gap: 32px;">
            <?php foreach ($stories as $i => $s):
                $columns = $i % 2 === 0 ? '5fr 7fr' : '7fr 5fr';
                $order = $i % 2 === 0 ? 0 : 1;
            ?>
            <article class="story-card reveal" style="background: #fff; border-radius: 24px; overflow: hidden; border: 0.5px solid var(--line); display: grid; grid-template-columns: <?= $columns ?>;">

                <div style="background: <?= e($s['tint']) ?>; padding: 48px; display: flex; flex-direction: column; justify-content: space-between; min-height: 460px; order: <?= $order ?>;">
                    <div style="font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.85); letter-spacing: 0.1em; text-transform: uppercase;">Case study</div>
                    <div>
                        <div style="font-size: 64px; font-weight: 300; letter-spacing: -2px; color: #fff; margin-bottom: 14px; line-height: 1;"><?= e($s['metrics'][0][0]) ?></div>
                        <div style="font-size: 14px; color: rgba(255,255,255,0.85); font-weight: 500;"><?= e($s['metrics'][0][1]) ?></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); display: grid; place-items: center; color: #fff; font-weight: 500; font-size: 16px;"><?= e($s['initials']) ?></div>
                        <div>
                            <div style="color: #fff; font-size: 14px; font-weight: 500;"><?= e($s['name']) ?></div>
                            <div style="color: rgba(255,255,255,0.75); font-size: 12px;"><?= e($s['location']) ?></div>
                        </div>
                    </div>
                </div>

                <div style="padding: 48px 44px;">
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 18px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <h3 style="font-size: 26px; font-weight: 500; letter-spacing: -0.4px;"><?= e($s['name']) ?></h3>
                            <div style="font-size: 13px; color: var(--mute); margin-top: 4px;"><?= e($s['person']) ?> · <?= e($s['location']) ?></div>
                        </div>
                        <span style="font-size: 11px; color: var(--teal-700); background: var(--teal-50); padding: 4px 9px; border-radius: 8px; font-weight: 500;"><?= e($s['yrs']) ?></span>
                    </div>

                    <blockquote style="font-size: 19px; font-weight: 300; letter-spacing: -0.3px; line-height: 1.4; color: var(--ink); border-left: 2px solid var(--teal-400); padding-left: 18px; margin: 20px 0 28px;">
                        "<?= e($s['quote']) ?>"
                    </blockquote>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 120px), 1fr)); gap: 12px; margin-bottom: 24px; padding: 18px 0; border-top: 0.5px solid var(--line); border-bottom: 0.5px solid var(--line);">
                        <?php foreach ($s['metrics'] as [$v, $l]): ?>
                        <div>
                            <div style="font-size: 22px; font-weight: 300; letter-spacing: -0.5px; color: var(--ink);"><?= e($v) ?></div>
                            <div style="font-size: 12px; color: var(--mute); margin-top: 2px;"><?= e($l) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <?php foreach ($s['body'] as $p): ?>
                        <p style="font-size: 15px; color: var(--ink-2); line-height: 1.65;"><?= e($p) ?></p>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 24px; padding-top: 20px; border-top: 0.5px solid var(--line);">
                        <div style="font-size: 11px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 500; margin-bottom: 10px;">Modules they use</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                            <?php foreach ($s['modules'] as $m): ?>
                            <span style="font-size: 12px; padding: 4px 11px; border-radius: 10px; background: var(--bg-2); color: var(--ink-2); font-weight: 500;"><?= e($m) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="padding-top: 80px; padding-bottom: 80px;">
    <div class="wrap">
        <div class="section-head reveal">
            <span class="eyebrow"><?= ecp_num(ecp_active_clinic_count()) ?> clinics, and counting</span>
            <h2 class="h-section" style="font-size: 32px;">A few of the names you might recognize.</h2>
        </div>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; max-width: 900px; margin: 0 auto;" class="logo-grid">
            <?php foreach ([
                'Sunrise Family · IN', 'Iyer Homeopathy · IN',
                'Skin & Co · ES', 'Hill Family · AU', 'Bright Smiles · AE', 'Riverside Physio · UK',
                'Northside Derma · US', 'Sato Clinic · JP', 'Vega Pediatrics · CO', 'Patel Wellness · UK',
            ] as $c): ?>
            <div style="background: var(--bg-2); border-radius: 12px; padding: 20px 18px; font-size: 13px; font-weight: 500; color: var(--ink-2); text-align: center;"><?= e($c) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
@media (max-width: 900px) {
    .story-card { grid-template-columns: 1fr !important; }
    .story-card > div:first-child { order: 0 !important; min-height: 320px !important; }
    .logo-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
