<?php
// =====================================================================
// book-a-demo.php — request a tailored demo
// =====================================================================
require_once __DIR__ . '/partials/helpers.php';

$pageTitle = 'Book a Demo — eClinicPro';
$metaDesc = 'See your specialty\'s templates and workflow live on eClinicPro. 15-minute tailored demo, no pressure.';
$activePage = '';

$submitted = false;
$formError = null;
$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'clinic_name' => '',
    'specialty' => 'gp',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $k => $_) {
        $form[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    if ($form['name'] === '' || $form['email'] === '') {
        $formError = 'Please share your name and a work email so we can confirm the demo.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $formError = 'That email looks off — could you double-check?';
    } elseif (ecp_save_demo_request($form)) {
        $submitted = true;
    } else {
        $formError = 'Something went wrong saving your request. Email us at hello@eclinicpro.com instead.';
    }
}

require __DIR__ . '/partials/header.php';
?>

<section style="padding: 140px 0 100px;">
    <div class="wrap">
        <div style="display: grid; grid-template-columns: 1fr 1.05fr; gap: 80px; align-items: start;" class="demo-grid">

            <!-- ============ SIDEBAR ============ -->
            <div class="reveal">
                <span class="eyebrow">15-minute demo</span>
                <h1 class="h-display" style="font-size: 42px; letter-spacing: -1.2px; margin-top: 14px; margin-bottom: 18px;">
                    A 15-minute look at your clinic on eClinicPro.
                </h1>
                <p class="lede" style="font-size: 17px; margin-bottom: 28px;">
                    Tell us about your clinic. We'll set up a tailored demo using your specialty's templates and walk you through it live.
                </p>

                <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 32px;">
                    <?php
                    $points = [
                        ['✓', 'Tailored to your specialty', 'GP, dental, homeo, derma, peds, physio — we configure the demo to match.'],
                        ['🎥', 'Live walk-through', 'Real screens, real workflow. Bring your hardest question.'],
                        ['📋', 'Migration plan', "We'll show you exactly how to move your existing records."],
                        ['🛡️', 'No pressure', 'No sales pitch. No follow-up calls unless you ask for them.'],
                    ];
                    foreach ($points as [$ic, $t, $d]): ?>
                    <div style="display: flex; gap: 14px; align-items: flex-start;">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; flex-shrink: 0; font-size: 14px;">
                            <?= $ic ?>
                        </div>
                        <div>
                            <div style="font-size: 15px; font-weight: 500;"><?= e($t) ?></div>
                            <div style="font-size: 13.5px; color: var(--mute); margin-top: 2px; line-height: 1.55;"><?= e($d) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 40px; padding: 20px 22px; background: var(--bg-2); border-radius: 14px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #C6EBDE, #2DC08A); display: grid; place-items: center; color: #fff; font-size: 14px; font-weight: 500;">NK</div>
                        <div>
                            <div style="font-size: 14px; font-weight: 500;">Naomi Kestler</div>
                            <div style="font-size: 12px; color: var(--mute);">Customer Success · eClinicPro</div>
                        </div>
                    </div>
                    <p style="font-size: 13px; color: var(--mute); line-height: 1.55; font-style: italic;">
                        "I run demos for clinics in 14 time zones. Bring your messy spreadsheet, your old EMR, your weirdest specialty workflow — I love them all."
                    </p>
                </div>
            </div>

            <!-- ============ FORM / CONFIRMATION ============ -->
            <div class="reveal">
                <?php if ($submitted): ?>

                <div style="background: #fff; border-radius: 18px; border: 0.5px solid var(--line); padding: 56px; text-align: center;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--teal-50); color: var(--teal-700); display: grid; place-items: center; margin: 0 auto 22px; font-size: 28px;">✓</div>
                    <h2 style="font-size: 28px; font-weight: 300; letter-spacing: -0.6px; margin-bottom: 12px;">Request received.</h2>
                    <p style="font-size: 15px; color: var(--mute); max-width: 380px; margin: 0 auto 24px; line-height: 1.6;">
                        Thanks, <strong style="color: var(--ink);"><?= e($form['name']) ?></strong>. We'll email
                        <strong style="color: var(--ink);"><?= e($form['email']) ?></strong> within 24 hours with available demo times.
                    </p>
                    <p style="font-size: 13px; color: var(--mute); margin-bottom: 28px;">
                        In the meantime, feel free to <a href="<?= e(ecp_portal_url('/register')) ?>" style="color: var(--teal-600);">start a free account</a> and explore.
                    </p>
                    <a href="/" class="btn btn-dark">Back to home</a>
                </div>

                <?php else: ?>

                <div style="background: #fff; border-radius: 18px; border: 0.5px solid var(--line); padding: 32px;">
                    <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 0.5px solid var(--line);">
                        <div style="font-size: 12px; color: var(--mute); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 500;">Request a demo</div>
                        <div style="font-size: 18px; font-weight: 500; margin-top: 4px;">Tell us about your clinic</div>
                        <div style="font-size: 13px; color: var(--mute); margin-top: 2px;">We'll reply within a working day with available times.</div>
                    </div>

                    <?php if ($formError): ?>
                    <div style="margin-bottom: 18px; padding: 12px 14px; background: #fee; border: 1px solid #fcc; border-radius: 10px; color: #b00; font-size: 13.5px;">
                        ⚠️ <?= e($formError) ?>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="/book-a-demo">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;" class="demo-fields">
                            <div>
                                <label style="font-size: 12px; font-weight: 500; color: var(--ink-2); display: block; margin-bottom: 6px;">
                                    Your name <span style="color: var(--red, #c00);">*</span>
                                </label>
                                <input type="text" name="name" required value="<?= e($form['name']) ?>" placeholder="Dr. Jane Patel"
                                       style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 0.5px solid var(--line); font-family: inherit; font-size: 14px;">
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 500; color: var(--ink-2); display: block; margin-bottom: 6px;">
                                    Work email <span style="color: var(--red, #c00);">*</span>
                                </label>
                                <input type="email" name="email" required value="<?= e($form['email']) ?>" placeholder="jane@clinic.com"
                                       style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 0.5px solid var(--line); font-family: inherit; font-size: 14px;">
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 500; color: var(--ink-2); display: block; margin-bottom: 6px;">Phone <span style="color: var(--mute); font-weight: 400;">(optional)</span></label>
                                <input type="tel" name="phone" value="<?= e($form['phone']) ?>" placeholder="+91 ..."
                                       style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 0.5px solid var(--line); font-family: inherit; font-size: 14px;">
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 500; color: var(--ink-2); display: block; margin-bottom: 6px;">Clinic name</label>
                                <input type="text" name="clinic_name" value="<?= e($form['clinic_name']) ?>" placeholder="Patel Family Care"
                                       style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 0.5px solid var(--line); font-family: inherit; font-size: 14px;">
                            </div>
                            <div style="grid-column: span 2;">
                                <label style="font-size: 12px; font-weight: 500; color: var(--ink-2); display: block; margin-bottom: 6px;">Specialty</label>
                                <select name="specialty" style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 0.5px solid var(--line); font-family: inherit; font-size: 14px; background: #fff;">
                                    <?php foreach ([
                                        'gp' => 'General practice',
                                        'dental' => 'Dental',
                                        'homeopathy' => 'Homeopathy',
                                        'derma' => 'Dermatology',
                                        'peds' => 'Pediatrics',
                                        'physio' => 'Physiotherapy',
                                        'other' => 'Other',
                                    ] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= $form['specialty'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top: 14px;">
                            <label style="font-size: 12px; font-weight: 500; color: var(--ink-2); display: block; margin-bottom: 6px;">
                                Anything we should prepare for? <span style="color: var(--mute); font-weight: 400;">(optional)</span>
                            </label>
                            <textarea name="message" rows="3" placeholder="Currently on Practo, moving 2,000 records. Want to focus on dental charting and recall."
                                      style="width: 100%; padding: 10px 14px; border-radius: 10px; border: 0.5px solid var(--line); font-family: inherit; font-size: 14px; resize: vertical;"><?= e($form['message']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; margin-top: 22px;">
                            Request demo →
                        </button>
                        <p style="font-size: 12px; color: var(--mute); text-align: center; margin-top: 14px;">
                            By submitting you agree to be contacted about your demo. We won't sell your info to anyone — ever.
                        </p>
                    </form>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Trust strip -->
<section style="padding-bottom: 80px;">
    <div class="wrap" style="text-align: center;">
        <p style="font-size: 13px; color: var(--mute); margin-bottom: 20px;">
            <?= ecp_num(ecp_active_clinic_count()) ?> clinics use eClinicPro across <?= ecp_num(ecp_country_count()) ?> countries
        </p>
        <div style="display: flex; gap: 14px; flex-wrap: wrap; justify-content: center; opacity: 0.7;">
            <?php
            $list = ['Sunrise Family · IN', 'Whitfield Dental · CA', 'PediaCare · NG', 'Skin & Co · ES', 'Riverside Physio · UK', 'Iyer Homeopathy · IN'];
            foreach ($list as $i => $c): ?>
            <span style="font-size: 13px; color: var(--mute); font-weight: 500;"><?= e($c) ?><?= $i < count($list) - 1 ? ' ·' : '' ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
@media (max-width: 800px) {
    .demo-grid { grid-template-columns: 1fr !important; gap: 40px !important; }
    .demo-fields { grid-template-columns: 1fr !important; }
}
</style>

<?php
$hideFinalCta = true;
require __DIR__ . '/partials/footer.php';
?>
