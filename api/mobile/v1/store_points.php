<?php
// =====================================================================
// api/mobile/v1/store_points.php — eClinicPro Points & Refer and Earn. Bearer.
//
// Same data as the web "Points & Refer" tab (partials/patient_points.php):
// App\Services\Store\PointsService::summary(). Read-only: points are earned
// and spent by the server (checkout, delivery, cron), never by the app.
// Always call them POINTS in the UI (1 point = ₹1 off), never money/wallet.
//
//   GET ?action=summary → { enabled, balance, lots[], history[], rules, referral, share_text }
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_store.php';

ecp_ms_boot(true);
$me = ecp_m_require_patient();
$identityId = (int) $me['id'];

$action = (string) ($_GET['action'] ?? 'summary');

switch ($action) {

    case 'summary': {
        ecp_m_require_method('GET');
        try {
            if (!\App\Services\Store\PointsService::enabled()) {
                ecp_m_ok(['enabled' => false]);   // hide the Points screen
            }
            $s = \App\Services\Store\PointsService::summary($identityId);
        } catch (Throwable $e) {
            error_log('[mobile store_points] ' . $e->getMessage());
            ecp_m_ok(['enabled' => false]);   // points tables not installed yet
        }
        $r = $s['rules'];
        $min = (int) round($r['welcome_min_order_paise'] / 100);
        $s['share_text'] = 'Join me on eClinicPro! Sign up with my code ' . $s['referral']['code'] . ' and get '
            . (int) $r['welcome_points'] . ' welcome points (worth ₹' . (int) $r['welcome_points'] . ' off your first ₹' . $min
            . '+ order) on the eClinicPro Store: ' . $s['referral']['link'];
        ecp_m_ok(['enabled' => true] + $s);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}
