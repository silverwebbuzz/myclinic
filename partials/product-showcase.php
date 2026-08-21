<?php
/**
 * Product showcase — one section per screen, image and copy alternating
 * left/right down the page. Each block: the real screenshot, what the screen
 * is for, and the features it puts in the doctor's hands.
 *
 * Images live in /assets/img/screens/. A block whose image is missing is
 * skipped, so the page never shows a broken frame.
 */
$ecpScreens = [
    [
        'file'    => 'dashbord.png',
        'eyebrow' => 'Dashboard',
        'title'   => 'The whole day on one screen',
        'body'    => 'Reception opens eClinicPro and knows exactly where the clinic stands — who is waiting, who is with the doctor, who has paid and what the day has earned so far.',
        'points'  => [
            'Live status tiles — waiting, in consult, completed',
            'Payment column with Cash / Online mark-paid in one click',
            'Revenue, patients and follow-ups due, updated as you work',
            'Book or add a walk-in without leaving the page',
        ],
    ],
    [
        'file'    => 'Patient-visit.png',
        'eyebrow' => 'Consultation',
        'title'   => 'A full consultation without switching tabs',
        'body'    => 'Complaint, symptoms, prescription, notes, charges and payment sit on one page, with the patient’s history, vitals and past reports alongside — so nothing has to be looked up mid-consult.',
        'points'  => [
            'Chief complaint, symptom picker and diagnosis with ICD-10',
            'Prescriptions with frequency, duration, tapering and templates',
            'Notes and separate lab / investigation findings',
            'History summary — last 3 visits, vitals and medications',
            'Charges, discount, GST and paid/due settled with the visit',
        ],
    ],
    [
        'file'    => 'Calender.png',
        'eyebrow' => 'Calendar',
        'title'   => 'Every appointment, the way you like to see it',
        'body'    => 'Day, week and month views over one schedule. Colour tells you the status at a glance, and any empty slot is a booking waiting to happen.',
        'points'  => [
            'Day, week, month and list views',
            'Colour-coded: waiting, arrived, in consult, completed, no-show',
            'Click an empty slot to book straight into it',
            'Multi-doctor schedules with leave and working hours respected',
        ],
    ],
    [
        'file'    => 'Book-an-appointment.png',
        'eyebrow' => 'Booking',
        'title'   => 'Booked in under ten seconds',
        'body'    => 'Search an existing patient or register a new one inline, pick the doctor and slot, and the appointment is on the calendar — no page reloads, no separate form.',
        'points'  => [
            'Search by name, phone or UHID — or add a new patient inline',
            'Only genuinely free slots are offered',
            'Pre-booked, walk-in, follow-up or online visit types',
            'WhatsApp / SMS confirmation and reminders sent automatically',
        ],
    ],
    [
        'file'    => 'Walk-in.png',
        'eyebrow' => 'Walk-ins',
        'title'   => 'Walk-ins handled like everyone else',
        'body'    => 'Real clinics run on walk-ins. Register one at the desk and they join today’s queue with a token — or give them a fixed slot if the day allows it.',
        'points'  => [
            'Token generated instantly, queued after pre-booked patients',
            'New patient registered from the same form',
            'Optional slot if you would rather give a fixed time',
            'Flows into the same queue, consultation and billing',
        ],
    ],
    [
        'file'    => 'Report.png',
        'eyebrow' => 'Reports',
        'title'   => 'Income and GST, ready for your CA',
        'body'    => 'Today by default, any range on demand. What you collected, what you billed, what is still due — and the tax split your accountant asks for every quarter.',
        'points'  => [
            'Collected, billed, GST and outstanding at a glance',
            'Cash / online split so the day’s cash box reconciles',
            'GST per rate — taxable value and tax collected',
            'Invoice-level detail, downloadable as CSV',
        ],
    ],
];

$ecpScreens = array_values(array_filter(
    $ecpScreens,
    static fn (array $s): bool => is_file(__DIR__ . '/../assets/img/screens/' . $s['file']),
));
?>
<?php if ($ecpScreens !== []): ?>
<section class="ecp-showcase" id="screenshots">
    <div class="wrap">
        <div class="ecp-showcase-head reveal">
            <p class="hp-eyebrow">See it in action</p>
            <h2 class="h-display">The software, screen by screen</h2>
            <p class="ecp-showcase-sub">Real screens from eClinicPro — not mockups.</p>
        </div>

        <?php foreach ($ecpScreens as $i => $screen): ?>
        <div class="ecp-row reveal <?= $i % 2 === 1 ? 'is-flipped' : '' ?>">
            <div class="ecp-row-media">
                <img src="/assets/img/screens/<?= htmlspecialchars($screen['file']) ?>"
                     alt="<?= htmlspecialchars($screen['title']) ?> — eClinicPro clinic management software"
                     loading="lazy" decoding="async" width="1600" height="900">
            </div>
            <div class="ecp-row-copy">
                <p class="ecp-row-eyebrow"><?= htmlspecialchars($screen['eyebrow']) ?></p>
                <h3 class="ecp-row-title"><?= htmlspecialchars($screen['title']) ?></h3>
                <p class="ecp-row-body"><?= htmlspecialchars($screen['body']) ?></p>
                <ul class="ecp-row-points">
                    <?php foreach ($screen['points'] as $point): ?>
                    <li><span aria-hidden="true">✓</span><?= htmlspecialchars($point) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<style>
    .ecp-showcase { padding: 76px 0 84px; background: #fff; }
    .ecp-showcase-head { text-align: center; max-width: 720px; margin: 0 auto 56px; }
    .ecp-showcase-sub { margin-top: 12px; color: #64748b; font-size: 1rem; }

    /* Alternating rows: image one side, copy the other. */
    .ecp-row {
        display: grid; align-items: center; gap: 48px;
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        padding: 40px 0;
    }
    .ecp-row + .ecp-row { border-top: 1px solid #eef2f7; }
    .ecp-row.is-flipped .ecp-row-media { order: 2; }
    .ecp-row.is-flipped .ecp-row-copy  { order: 1; }

    .ecp-row-media img {
        display: block; width: 100%; height: auto; border-radius: 16px;
        border: 1px solid #e2e8f0; background: #f8fafc;
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 18px 40px -22px rgba(15,23,42,.45);
    }

    .ecp-row-eyebrow {
        display: inline-block; margin: 0 0 10px; padding: 4px 10px; border-radius: 999px;
        background: #ecfdf5; color: #047857;
        font-size: .6875rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    }
    .ecp-row-title { margin: 0; font-size: 1.6rem; line-height: 1.25; color: #0f172a; letter-spacing: -.01em; }
    .ecp-row-body  { margin: 12px 0 0; color: #475569; font-size: 1rem; line-height: 1.65; }
    .ecp-row-points { margin: 20px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; }
    .ecp-row-points li {
        display: grid; grid-template-columns: 22px 1fr; align-items: start;
        color: #334155; font-size: .9375rem; line-height: 1.5;
    }
    .ecp-row-points span {
        display: grid; place-items: center; width: 18px; height: 18px; margin-top: 2px;
        border-radius: 50%; background: #d1fae5; color: #047857; font-size: 11px; font-weight: 700;
    }

    @media (max-width: 900px) {
        .ecp-showcase { padding: 56px 0 60px; }
        .ecp-showcase-head { margin-bottom: 36px; }
        .ecp-row { grid-template-columns: 1fr; gap: 24px; padding: 32px 0; }
        /* Stacked: image always above its copy, whichever side it sits on. */
        .ecp-row.is-flipped .ecp-row-media,
        .ecp-row.is-flipped .ecp-row-copy { order: 0; }
        .ecp-row-title { font-size: 1.35rem; }
    }
</style>
<?php endif; ?>
