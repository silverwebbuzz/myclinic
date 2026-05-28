<?php
// =====================================================================
// L.php — lead landing page. URL: /L/{token}
//
// The SMS we send to unclaimed doctors links here. They see:
//   - Patient name, phone, preferred slot, reason
//   - Stats: "You've had X leads this month"
//   - Big CTA: "Claim your clinic on eClinicPro"
//   - Pause / unsubscribe link (so we stay TRAI-clean)
//
// Public — no auth required. The token IS the auth (16 hex chars, random).
// =====================================================================

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/directory_leads.php';
require_once __DIR__ . '/partials/notify.php';

// Token comes from the URL — .htaccess rewrites /L/abc123 to /L.php?t=abc123
$token  = (string) ($_GET['t'] ?? '');
$action = (string) ($_GET['action'] ?? '');
$lead   = ecp_lead_for_token($token);

// Inline pause action — doctor clicked "Pause SMS" on the landing page.
if ($action === 'pause' && $lead) {
    ecp_lead_pause_doctor((int) $lead['doctor_id'], 'doctor_landing_pause');
    $pausedJustNow = true;
}

// Confirm action — doctor tapped "Confirm appointment". Marks the lead
// confirmed (idempotent) and queues a WhatsApp/SMS confirmation to the patient.
if ($action === 'confirm' && $lead && empty($lead['confirmed_at'])) {
    $confirmed = ecp_lead_confirm((int) $lead['id']);
    if ($confirmed) {
        $confirmedJustNow = true;
        $lead['confirmed_at'] = date('Y-m-d H:i:s');  // reflect in this render
    }
}
$alreadyConfirmed = !empty($lead['confirmed_at']);

if (!$lead) {
    http_response_code(404);
    $pageTitle = 'Lead not found — eClinicPro';
    $metaDesc  = 'This lead link is invalid or has expired.';
    require __DIR__ . '/partials/header.php';
    ?>
    <main style="padding:120px 20px; text-align:center; max-width:480px; margin:0 auto;">
        <div style="font-size:48px;">🔗</div>
        <h1 style="font-size:24px; font-weight:600; margin:16px 0 8px;">Link not found</h1>
        <p style="color:#666; font-size:15px; line-height:1.5;">
            This lead link is invalid or has expired. If you received it by SMS,
            it may have already been viewed by someone else.
        </p>
        <a href="/find-a-doctor" style="display:inline-block; margin-top:24px; background:#0F9B6E; color:#fff; padding:12px 24px; border-radius:10px; text-decoration:none; font-weight:600;">
            Explore eClinicPro
        </a>
    </main>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

// Stamp the view (idempotent).
ecp_lead_mark_doctor_viewed((int) $lead['id']);

// Roll-up stats for this doctor to make the page feel "valuable".
$db = ecp_db();
$stats = ['leads_30d' => 0, 'leads_7d' => 0, 'last_lead_at' => null];
if ($db) {
    $s = $db->prepare(
        'SELECT
            SUM(type = "book_submitted" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS leads_30d,
            SUM(type = "book_submitted" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))  AS leads_7d,
            MAX(CASE WHEN type = "book_submitted" THEN created_at ELSE NULL END) AS last_lead_at
         FROM directory_leads WHERE directory_doctor_id = :id'
    );
    $s->execute(['id' => (int) $lead['doctor_id']]);
    $stats = $s->fetch(PDO::FETCH_ASSOC) ?: $stats;
}

$pageTitle = 'New patient lead — ' . ($lead['clinic_name'] ?? '');
$metaDesc  = 'A patient wants to book an appointment with you.';

require __DIR__ . '/partials/header.php';

$patientFirstName = $lead['patient_first_name']
    ?: explode(' ', (string) ($lead['patient_name'] ?? 'Patient'))[0];
$dateNice = $lead['preferred_date']
    ? date('l, j F Y', strtotime((string) $lead['preferred_date']))
    : 'Anytime';
$timeNice = $lead['preferred_time']
    ? date('g:i A', strtotime('2000-01-01 ' . $lead['preferred_time']))
    : '';
$createdNice = date('M j, g:i A', strtotime((string) $lead['created_at']));
?>

<main class="L-page">
    <div class="L-wrap">

        <?php if (!empty($pausedJustNow)): ?>
        <div class="L-paused-banner">
            ✓ SMS notifications paused for this clinic. You can claim your clinic anytime to resume.
        </div>
        <?php endif; ?>

        <?php if (!empty($confirmedJustNow)): ?>
        <div class="L-confirmed-banner">
            ✓ Appointment confirmed. We've let the patient know — they'll see you at the requested time.
        </div>
        <?php elseif ($alreadyConfirmed): ?>
        <div class="L-confirmed-banner">
            ✓ This appointment is already confirmed.
        </div>
        <?php endif; ?>

        <!-- Lead detail card -->
        <section class="L-card">
            <div class="L-banner">
                <span class="L-banner-tag">NEW LEAD</span>
                <span class="L-banner-time">Received <?= htmlspecialchars($createdNice) ?></span>
            </div>

            <div class="L-card-body">
                <h1 class="L-title">
                    A patient wants to book you
                </h1>
                <p class="L-sub">
                    For <strong><?= htmlspecialchars((string) $lead['clinic_name']) ?></strong>
                    <?php if ($lead['area'] || $lead['city']): ?>
                        · <?= htmlspecialchars(trim(($lead['area'] ?? '') . ($lead['area'] && $lead['city'] ? ', ' : '') . ($lead['city'] ?? ''))) ?>
                    <?php endif; ?>
                </p>

                <div class="L-grid">
                    <div class="L-row">
                        <span class="L-row-label">Patient</span>
                        <strong class="L-row-value"><?= htmlspecialchars((string) ($lead['patient_name'] ?? 'Anonymous')) ?></strong>
                    </div>
                    <div class="L-row">
                        <span class="L-row-label">Phone</span>
                        <?php if (!empty($lead['patient_phone'])): ?>
                            <a class="L-row-value L-phone" href="tel:<?= htmlspecialchars((string) $lead['patient_phone']) ?>">
                                📞 <?= htmlspecialchars((string) $lead['patient_phone']) ?>
                            </a>
                        <?php else: ?>
                            <span class="L-row-value L-muted">Hidden — claim to view</span>
                        <?php endif; ?>
                    </div>
                    <div class="L-row">
                        <span class="L-row-label">Preferred date</span>
                        <strong class="L-row-value"><?= htmlspecialchars($dateNice) ?></strong>
                    </div>
                    <?php if ($timeNice): ?>
                    <div class="L-row">
                        <span class="L-row-label">Preferred time</span>
                        <strong class="L-row-value"><?= htmlspecialchars($timeNice) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($lead['reason'])): ?>
                    <div class="L-row">
                        <span class="L-row-label">Reason for visit</span>
                        <strong class="L-row-value"><?= htmlspecialchars((string) $lead['reason']) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$alreadyConfirmed): ?>
                <a href="/L?action=confirm&t=<?= htmlspecialchars($token) ?>" class="L-cta-confirm">
                    ✓ Confirm appointment
                </a>
                <?php endif; ?>

                <?php if (!empty($lead['patient_phone'])): ?>
                <a href="tel:<?= htmlspecialchars((string) $lead['patient_phone']) ?>" class="L-cta-call">
                    📞 Call <?= htmlspecialchars($patientFirstName) ?> now
                </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Stats — the persuasion bit -->
        <section class="L-stats">
            <h2 class="L-stats-title">Your lead activity</h2>
            <div class="L-stats-grid">
                <div class="L-stat">
                    <div class="L-stat-num"><?= (int) ($stats['leads_30d'] ?? 0) ?></div>
                    <div class="L-stat-label">Patients in last 30 days</div>
                </div>
                <div class="L-stat">
                    <div class="L-stat-num"><?= (int) ($stats['leads_7d'] ?? 0) ?></div>
                    <div class="L-stat-label">This week</div>
                </div>
            </div>
            <?php if ((int) ($stats['leads_30d'] ?? 0) > 1): ?>
                <p class="L-stats-note">
                    💡 You're missing real patients. Claim your clinic to get every lead in real time,
                    manage your schedule, and never miss another booking.
                </p>
            <?php endif; ?>
        </section>

        <!-- Claim CTA — the sales hook -->
        <section class="L-claim">
            <div class="L-claim-body">
                <h2>Take control of your bookings</h2>
                <p>
                    Claim <strong><?= htmlspecialchars((string) $lead['clinic_name']) ?></strong> on eClinicPro
                    to get every lead instantly, take online appointments,
                    manage prescriptions, and run your clinic from one dashboard.
                </p>
                <ul class="L-feat">
                    <li>✓ Real-time SMS + WhatsApp lead alerts</li>
                    <li>✓ Online appointment booking on your own page</li>
                    <li>✓ Prescription, billing, and patient records</li>
                    <li>✓ Free trial — no credit card required</li>
                </ul>
                <button type="button" class="L-cta-claim"
                        onclick="if (window.ecpClaim) window.ecpClaim.open('claim', { id: <?= (int) $lead['doctor_id'] ?>, name: <?= json_encode($lead['clinic_name'], JSON_UNESCAPED_UNICODE) ?>, city: <?= json_encode($lead['city'], JSON_UNESCAPED_UNICODE) ?>, area: <?= json_encode($lead['area'], JSON_UNESCAPED_UNICODE) ?>, clinicName: <?= json_encode($lead['clinic_name'], JSON_UNESCAPED_UNICODE) ?>, doctorName: <?= json_encode($lead['doctor_name'], JSON_UNESCAPED_UNICODE) ?> });">
                    🚀 Claim my clinic free
                </button>
                <p class="L-trust">
                    Already a member? <a href="https://app.eclinicpro.com/doctor/login">Sign in</a>
                </p>
            </div>
        </section>

        <!-- Footer / unsubscribe -->
        <footer class="L-foot">
            Don't want lead alerts?
            <a href="/L?action=pause&t=<?= htmlspecialchars($token) ?>">Pause SMS notifications</a>
            for this clinic. You can resume anytime.
        </footer>
    </div>
</main>

<style>
.L-page { background: var(--bg-3, #fafafa); min-height: calc(100vh - 80px); padding: 32px 16px 80px; }
.L-wrap { max-width: 600px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }

.L-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.04); }
.L-banner { background: linear-gradient(135deg, var(--teal-700), var(--teal-400)); color: #fff; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; font-size: 12px; }
.L-banner-tag { background: rgba(255,255,255,0.25); padding: 3px 8px; border-radius: 4px; font-weight: 700; letter-spacing: 0.06em; }
.L-banner-time { opacity: 0.9; }
.L-card-body { padding: 22px 22px 24px; }
.L-title { font-size: 22px; font-weight: 700; letter-spacing: -0.4px; color: var(--ink); margin-bottom: 4px; }
.L-sub { font-size: 14px; color: var(--ink-2); margin-bottom: 18px; }

.L-grid { display: flex; flex-direction: column; gap: 12px; padding: 16px; background: var(--bg-2); border-radius: 12px; margin-bottom: 18px; }
.L-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; font-size: 14px; }
.L-row-label { color: var(--mute); font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
.L-row-value { color: var(--ink); font-weight: 600; text-align: right; }
.L-phone { color: var(--teal-700); text-decoration: none; }
.L-phone:hover { text-decoration: underline; }
.L-muted { color: var(--mute); font-weight: 500; }

.L-cta-confirm { display: block; width: 100%; padding: 14px; background: var(--teal-600); color: #fff; text-decoration: none; text-align: center; border-radius: 12px; font-weight: 700; font-size: 16px; transition: all .15s; margin-bottom: 10px; }
.L-cta-confirm:hover { background: var(--teal-700); box-shadow: 0 6px 16px rgba(15,155,110,0.3); }
.L-cta-call { display: block; width: 100%; padding: 14px; background: #fff; color: var(--teal-700); border: 1.5px solid var(--teal-400); text-decoration: none; text-align: center; border-radius: 12px; font-weight: 700; font-size: 16px; transition: all .15s; }
.L-cta-call:hover { background: var(--teal-50); }
.L-confirmed-banner { background: #e6f7ef; border: 1px solid #8fd9b6; color: #0a6b45; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600; }

.L-stats { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 20px 22px; }
.L-stats-title { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--mute); margin-bottom: 12px; }
.L-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.L-stat { background: var(--teal-50); border-radius: 12px; padding: 14px; text-align: center; }
.L-stat-num { font-size: 32px; font-weight: 800; color: var(--teal-700); line-height: 1; }
.L-stat-label { font-size: 12px; color: var(--ink-2); margin-top: 4px; font-weight: 500; }
.L-stats-note { margin-top: 12px; font-size: 13.5px; color: var(--ink-2); line-height: 1.5; background: #fff7e0; padding: 10px 12px; border-radius: 8px; }

.L-claim { background: linear-gradient(135deg, #fff 0%, var(--teal-50) 100%); border: 1px solid rgba(15,155,110,0.2); border-radius: 18px; padding: 24px; }
.L-claim h2 { font-size: 20px; font-weight: 700; letter-spacing: -0.3px; margin-bottom: 8px; }
.L-claim p { font-size: 14px; color: var(--ink-2); line-height: 1.55; margin-bottom: 14px; }
.L-claim p strong { color: var(--ink); font-weight: 600; }
.L-feat { list-style: none; padding: 0; margin: 0 0 18px; display: flex; flex-direction: column; gap: 8px; }
.L-feat li { font-size: 13.5px; color: var(--ink-2); }
.L-cta-claim { width: 100%; padding: 14px; background: var(--teal-600); color: #fff; border: 0; border-radius: 12px; font-weight: 700; font-size: 15.5px; cursor: pointer; transition: all .15s; }
.L-cta-claim:hover { background: var(--teal-700); box-shadow: 0 6px 16px rgba(15,155,110,0.3); }
.L-trust { text-align: center; font-size: 12.5px; color: var(--mute); margin-top: 10px; margin-bottom: 0; }
.L-trust a { color: var(--teal-700); }

.L-foot { text-align: center; font-size: 12px; color: var(--mute); padding: 8px 0; }
.L-foot a { color: var(--mute); text-decoration: underline; }

.L-paused-banner {
    background: #fff7e0; border: 1px solid #f5d97e; color: #6b4f00;
    padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600;
}

@media (max-width: 480px) {
    .L-card-body, .L-claim { padding: 18px; }
    .L-title { font-size: 19px; }
    .L-stat-num { font-size: 26px; }
}
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
