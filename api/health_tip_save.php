<?php
// =====================================================================
// api/health_tip_save.php — a patient saves / un-saves a Health Tips post.
//
//   POST /api/health_tip_save   JSON {"id": 123}  → {ok, saved, count}
//
// Auth: ecp_pid cookie (SameSite=Lax). 401 when logged out — the page then
// sends the visitor to /patient to sign in. JSON-only + same-origin check so a
// cross-site form can't toggle saves.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/health_tips.php';

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
if (!ecp_ht_saves_enabled()) {
    $fail(503, 'not_available');
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$res = ecp_ht_toggle_save((int) $me['id'], (int) ($body['id'] ?? 0));
if (!$res['ok']) {
    $fail($res['error'] === 'not_found' ? 404 : 500, (string) $res['error']);
}
echo json_encode($res);
