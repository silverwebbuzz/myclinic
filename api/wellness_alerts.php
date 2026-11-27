<?php
// =====================================================================
// api/wellness_alerts.php — "Email me new camps / events in my city".
//
//   POST JSON {action:"subscribe", kind:"free_camp"|"event", city, email?}   signed in
//   POST JSON {action:"unsubscribe", id}                                    signed in
//   GET  ?unsubscribe={token}                                               one-click link from the email (HTML)
//
// Email only. The daily sender is app/workers/wellness_camp_alerts.php.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/helpers.php';
require_once __DIR__ . '/../partials/wellness_alerts.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['unsubscribe'])) {
    $alert = ecp_wa_unsubscribe_token((string) $_GET['unsubscribe']);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    $what = $alert ? ($alert['kind'] === 'event' ? 'health events' : 'free health camps') . ' in ' . $alert['city_name'] : '';
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribed — eClinicPro</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:12vh auto;padding:0 20px;text-align:center;color:#1C1C1E">'
        . ($alert ? '<h1 style="font-weight:500">You’re unsubscribed</h1><p>We won’t email you about new ' . e($what) . ' any more.</p>'
                  : '<h1 style="font-weight:500">Link not valid</h1><p>This unsubscribe link has expired or was already used.</p>')
        . '<p><a href="/wellness/camps" style="color:#0B7F5A">Browse free health camps</a></p></div>';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$fail = static function (int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $fail(405, 'method_not_allowed');
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
    $fail(403, 'bad_origin');
}
$me = ecp_patient_current();
if (!$me) {
    $fail(401, 'login_required');
}
$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) {
    $fail(400, 'bad_request');
}

if (($in['action'] ?? '') === 'subscribe') {
    $res = ecp_wa_subscribe($me, (string) ($in['kind'] ?? 'free_camp'), strtolower((string) ($in['city'] ?? '')), (string) ($in['email'] ?? ''));
    echo json_encode($res);
    exit;
}
if (($in['action'] ?? '') === 'unsubscribe') {
    echo json_encode(['ok' => ecp_wa_unsubscribe((int) $me['id'], (int) ($in['id'] ?? 0))]);
    exit;
}
$fail(400, 'unknown_action');
