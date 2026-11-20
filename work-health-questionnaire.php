<?php
// =====================================================================
// work-health-questionnaire.php?booking=N — the employee fills their
// pre-exam health questionnaire before a work health check (Phase 2D).
//
// Auth: ecp_pid cookie; only the person's own upcoming bookings (confirmed
// employment link). POST is a normal form: we require a same-origin Origin
// (or Referer) header, so another site can't submit it for them.
// Answers go to the examining doctor only; the employer is told "done".
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/patient_auth.php';
require_once __DIR__ . '/partials/work_health.php';

$me = ecp_patient_current();
if (!$me) {
    header('Location: /patient?tab=work');
    exit;
}
$bookingId = (int) ($_GET['booking'] ?? $_POST['booking'] ?? 0);
$booking = ecp_wh_q_booking((int) $me['id'], $bookingId);
$q = ecp_wh_questionnaire();
$error = null;
$saved = false;
$posted = [];

if ($booking !== null && $q !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $host = explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0];
    $src = (string) ($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
    $posted = is_array($_POST['q'] ?? null) ? $_POST['q'] : [];
    if ($src === '' || parse_url($src, PHP_URL_HOST) !== $host) {
        $error = 'Please submit the form from this page.';
    } else {
        $res = ecp_wh_q_save((int) $me['id'], $bookingId, $posted);
        $saved = $res['ok'];
        $error = $res['ok'] ? null : ($res['error'] ?? 'Could not save.');
        if ($saved) {
            $booking = ecp_wh_q_booking((int) $me['id'], $bookingId);
        }
    }
}
$prev = $posted !== [] ? $posted : ($booking['answers'] ?? []);

$pageTitle = 'Health questionnaire | eClinicPro';
$metaDesc = 'Pre-exam health questionnaire for your work health check.';
$activePage = 'patient';
$extraHead = '<meta name="robots" content="noindex, nofollow">';
require __DIR__ . '/partials/header.php';
?>
<style>
    .whq { max-width: 760px; margin: 0 auto; padding: 96px 16px 56px; }
    .whq h1 { font-size: clamp(24px, 4vw, 32px); margin: 0 0 6px; letter-spacing: -0.02em; }
    .whq .whq-sub { color: #55655c; margin: 0 0 18px; }
    .whq .whq-card { background: #fff; border: 1px solid #e6ece8; border-radius: 16px; padding: 20px 22px; margin-bottom: 14px; }
    .whq .whq-sec { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #6b7d73; margin: 8px 0 10px; }
    .whq .whq-item { padding: 12px 0; border-top: 1px solid #f0f4f1; }
    .whq .whq-item:first-of-type { border-top: 0; }
    .whq .whq-qt { font-weight: 600; color: #1c2a22; margin-bottom: 6px; }
    .whq .whq-opt { display: inline-flex; align-items: center; gap: 6px; margin: 0 18px 4px 0; font-size: 15px; }
    .whq input[type=text], .whq textarea { width: 100%; border: 1px solid #cfd8d2; border-radius: 10px; padding: 9px 11px; font-size: 15px; margin-top: 6px; box-sizing: border-box; }
    .whq .whq-ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
    .whq .whq-err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; }
    .whq .whq-note { font-size: 13px; color: #6b7d73; }
    .whq .whq-detail { display: none; }
    .whq .whq-item.yes .whq-detail { display: block; }
</style>
<main class="whq">
    <a href="/patient?tab=work" class="whq-note">← Work health</a>
    <?php if ($booking === null || $q === null): ?>
        <h1>Questionnaire not available</h1>
        <p class="whq-sub">This booking isn't open for a questionnaire (it may be cancelled or already done). Go back to Work health.</p>
    <?php else: ?>
        <h1><?= e($q['name']) ?></h1>
        <p class="whq-sub">For your health check at <strong><?= e($booking['clinic_name']) ?></strong> on <?= e(date('d M Y, h:i A', strtotime((string) $booking['scheduled_at']))) ?> (<?= e($booking['company_name']) ?>).</p>
        <?php if ($saved): ?><div class="whq-ok">Thank you, saved. The doctor will see your answers at the visit. You can change them here until then.</div><?php endif; ?>
        <?php if ($error): ?><div class="whq-err"><?= e($error) ?></div><?php endif; ?>
        <p class="whq-note"><?= e((string) ($q['intro'] ?? '')) ?></p>
        <form method="post" action="/work-health-questionnaire?booking=<?= (int) $bookingId ?>">
            <input type="hidden" name="booking" value="<?= (int) $bookingId ?>">
            <div class="whq-card">
                <?php $sec = ''; foreach ($q['items'] as $it): $code = (string) $it['code']; $pv = (string) ($prev[$code]['v'] ?? ''); $pd = (string) ($prev[$code]['d'] ?? ''); ?>
                    <?php if ($it['section'] !== $sec): $sec = (string) $it['section']; ?><div class="whq-sec"><?= e($sec) ?></div><?php endif; ?>
                    <div class="whq-item<?= $pv === 'yes' ? ' yes' : '' ?>">
                        <div class="whq-qt"><?= e($it['text']) ?><?= (int) $it['required'] === 1 ? '' : ' <span class="whq-note">(optional)</span>' ?></div>
                        <?php if ($it['type'] === 'yes_no'): ?>
                            <label class="whq-opt"><input type="radio" name="q[<?= e($code) ?>][v]" value="yes" <?= $pv === 'yes' ? 'checked' : '' ?> onchange="this.closest('.whq-item').classList.add('yes')"> Yes</label>
                            <label class="whq-opt"><input type="radio" name="q[<?= e($code) ?>][v]" value="no" <?= $pv === 'no' ? 'checked' : '' ?> onchange="this.closest('.whq-item').classList.remove('yes')"> No</label>
                            <?php if (!empty($it['detail_prompt'])): ?>
                                <div class="whq-detail"><input type="text" name="q[<?= e($code) ?>][d]" maxlength="500" value="<?= e($pd) ?>" placeholder="<?= e($it['detail_prompt']) ?>"></div>
                            <?php endif; ?>
                        <?php elseif ($it['type'] === 'choice'): ?>
                            <?php foreach ($it['options'] as $opt): ?>
                                <label class="whq-opt"><input type="radio" name="q[<?= e($code) ?>][v]" value="<?= e($opt) ?>" <?= $pv === $opt ? 'checked' : '' ?>> <?= e($opt) ?></label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <textarea name="q[<?= e($code) ?>][v]" rows="3" maxlength="1000"><?= e($pv) ?></textarea>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Save my answers</button>
            <p class="whq-note" style="margin-top:10px">Only the examining doctor sees your answers. Your employer is told only that the questionnaire is done.</p>
        </form>
    <?php endif; ?>
</main>
<?php
$hideFinalCta = true;
require __DIR__ . '/partials/footer.php';
