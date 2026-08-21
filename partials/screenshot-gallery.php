<?php
/**
 * Product screenshot gallery — real screens from the app, click to enlarge.
 *
 * Drop the PNG/JPG files into /assets/img/screens/ using the names below.
 * Any file that is missing is skipped, so the section never shows a broken
 * image while screenshots are being refreshed.
 */
$ecpShots = [
    ['file' => 'dashboard.png',        'title' => 'Clinic dashboard',      'caption' => "Today's appointments, payment status and revenue at a glance."],
    ['file' => 'consultation.png',     'title' => 'Consultation screen',   'caption' => 'Complaint, symptoms, prescription, notes and payment on one page — with the patient’s history alongside.'],
    ['file' => 'calendar-month.png',   'title' => 'Appointment calendar',  'caption' => 'Day, week and month views. Click any empty slot to book.'],
    ['file' => 'calendar-booking.png', 'title' => 'Book in two clicks',    'caption' => 'Search the patient, pick a slot, done — without leaving the calendar.'],
    ['file' => 'walk-in.png',          'title' => 'Walk-in tokens',        'caption' => 'Register a walk-in and drop them into today’s queue instantly.'],
    ['file' => 'income-report.png',    'title' => 'Income & GST report',   'caption' => 'Collected, billed, GST and outstanding — for today or any date range.'],
];

$ecpShots = array_values(array_filter(
    $ecpShots,
    static fn (array $s): bool => is_file(__DIR__ . '/../assets/img/screens/' . $s['file']),
));
?>
<?php if ($ecpShots !== []): ?>
<section class="ecp-gallery" id="screenshots">
    <div class="wrap">
        <div class="ecp-gallery-head reveal">
            <p class="hp-eyebrow">See it in action</p>
            <h2 class="h-display">The software, screen by screen</h2>
            <p class="ecp-gallery-sub">Real screens from eClinicPro — not mockups. Click any image to view it full size.</p>
        </div>

        <div class="ecp-gallery-grid">
            <?php foreach ($ecpShots as $i => $shot): ?>
            <figure class="ecp-shot reveal" data-shot="<?= (int) $i ?>">
                <button type="button" class="ecp-shot-btn"
                        aria-label="View <?= htmlspecialchars($shot['title']) ?> full size">
                    <img src="/assets/img/screens/<?= htmlspecialchars($shot['file']) ?>"
                         alt="<?= htmlspecialchars($shot['title']) ?> — eClinicPro clinic management software"
                         loading="lazy" decoding="async" width="1200" height="800">
                    <span class="ecp-shot-zoom" aria-hidden="true">⤢</span>
                </button>
                <figcaption>
                    <strong><?= htmlspecialchars($shot['title']) ?></strong>
                    <span><?= htmlspecialchars($shot['caption']) ?></span>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="ecp-lb" id="ecpLightbox" hidden>
        <button type="button" class="ecp-lb-close" aria-label="Close">&times;</button>
        <button type="button" class="ecp-lb-nav ecp-lb-prev" aria-label="Previous">&#8249;</button>
        <figure class="ecp-lb-figure">
            <img src="" alt="">
            <figcaption></figcaption>
        </figure>
        <button type="button" class="ecp-lb-nav ecp-lb-next" aria-label="Next">&#8250;</button>
    </div>
</section>

<style>
    .ecp-gallery { padding: 72px 0 80px; background: #fff; }
    .ecp-gallery-head { text-align: center; max-width: 720px; margin: 0 auto 40px; }
    .ecp-gallery-sub { margin-top: 12px; color: #64748b; font-size: 1rem; line-height: 1.6; }
    .ecp-gallery-grid {
        display: grid; gap: 22px;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    }
    .ecp-shot { margin: 0; }
    .ecp-shot-btn {
        display: block; width: 100%; padding: 0; border: 1px solid #e2e8f0; cursor: zoom-in;
        border-radius: 14px; overflow: hidden; background: #f8fafc; position: relative;
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.18);
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .ecp-shot-btn:hover { transform: translateY(-3px); box-shadow: 0 14px 34px -12px rgba(15,23,42,.3); }
    .ecp-shot-btn img { display: block; width: 100%; height: auto; }
    .ecp-shot-zoom {
        position: absolute; right: 10px; bottom: 10px; width: 30px; height: 30px;
        display: grid; place-items: center; border-radius: 8px;
        background: rgba(15,23,42,.72); color: #fff; font-size: 15px;
    }
    .ecp-shot figcaption { padding: 14px 4px 0; }
    .ecp-shot figcaption strong { display: block; color: #0f172a; font-size: 1rem; }
    .ecp-shot figcaption span { display: block; margin-top: 4px; color: #64748b; font-size: .875rem; line-height: 1.55; }

    .ecp-lb {
        position: fixed; inset: 0; z-index: 1000; display: flex; align-items: center;
        justify-content: center; gap: 8px; padding: 24px; background: rgba(2,6,23,.9);
    }
    .ecp-lb[hidden] { display: none; }
    .ecp-lb-figure { margin: 0; max-width: min(1200px, 92vw); text-align: center; }
    .ecp-lb-figure img {
        max-width: 100%; max-height: 78vh; border-radius: 12px; background: #fff;
        box-shadow: 0 30px 60px -20px rgba(0,0,0,.6);
    }
    .ecp-lb-figure figcaption { margin-top: 12px; color: #e2e8f0; font-size: .9rem; }
    .ecp-lb-close {
        position: absolute; top: 16px; right: 20px; width: 40px; height: 40px;
        border: 0; border-radius: 10px; background: rgba(255,255,255,.12);
        color: #fff; font-size: 26px; line-height: 1; cursor: pointer;
    }
    .ecp-lb-nav {
        width: 44px; height: 44px; flex: none; border: 0; border-radius: 50%;
        background: rgba(255,255,255,.12); color: #fff; font-size: 28px; line-height: 1; cursor: pointer;
    }
    .ecp-lb-close:hover, .ecp-lb-nav:hover { background: rgba(255,255,255,.24); }
    @media (max-width: 640px) {
        .ecp-gallery { padding: 56px 0 60px; }
        .ecp-lb-nav { width: 38px; height: 38px; font-size: 22px; }
    }
</style>

<script>
(function () {
    var shots = <?= json_encode(array_map(static fn (array $s): array => [
        'src' => '/assets/img/screens/' . $s['file'],
        'title' => $s['title'],
        'caption' => $s['caption'],
    ], $ecpShots), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    var lb = document.getElementById('ecpLightbox');
    if (!lb || !shots.length) return;
    var img = lb.querySelector('img');
    var cap = lb.querySelector('figcaption');
    var idx = 0;

    function show(i) {
        idx = (i + shots.length) % shots.length;
        img.src = shots[idx].src;
        img.alt = shots[idx].title;
        cap.textContent = shots[idx].title + ' — ' + shots[idx].caption;
        lb.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function close() {
        lb.hidden = true;
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.ecp-shot-btn').forEach(function (btn, i) {
        btn.addEventListener('click', function () { show(i); });
    });
    lb.querySelector('.ecp-lb-close').addEventListener('click', close);
    lb.querySelector('.ecp-lb-prev').addEventListener('click', function () { show(idx - 1); });
    lb.querySelector('.ecp-lb-next').addEventListener('click', function () { show(idx + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) close(); });
    document.addEventListener('keydown', function (e) {
        if (lb.hidden) return;
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') show(idx - 1);
        if (e.key === 'ArrowRight') show(idx + 1);
    });
})();
</script>
<?php endif; ?>
