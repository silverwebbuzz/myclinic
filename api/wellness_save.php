<?php
// =====================================================================
// api/wellness_save.php — a patient saves / un-saves a camp or event.
//
//   POST /api/wellness_save   JSON {"id": 12}  → {ok, saved}
//
// 401 when logged out (the page then opens the sign-in modal). JSON-only +
// same-origin check so a cross-site form can't toggle saves.
// Recipes / exercises / tips keep using /api/health_tip_save.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/wellness_events.php';

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
$body = json_decode(file_get_contents('php://input') ?: '', true);
$res = ecp_we_toggle_save((int) $me['id'], (int) (is_array($body) ? ($body['id'] ?? 0) : 0));
if (!$res['ok']) {
    $fail(match ($res['error'] ?? '') { 'not_found' => 404, 'not_available' => 503, default => 500 }, (string) $res['error']);
}
echo json_encode(['ok' => true, 'saved' => $res['saved']]);
