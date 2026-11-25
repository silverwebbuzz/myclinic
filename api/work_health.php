<?php
// =====================================================================
// api/work_health.php — patient panel "Work health" tab (JSON).
//
//   GET  /api/work_health                         → { items: [...] }
//   POST /api/work_health  {"id": 12, "answer": "confirm"|"decline"}
//
// Auth: ecp_pid cookie. Every read/write is limited to the signed-in
// person's own employment links (identity_id), so no IDOR. POST must be
// JSON + same origin (a cross-site form can't send application/json
// without a CORS preflight, and the Origin check covers the rest).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/work_health.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = static function (int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};

$me = ecp_patient_current();
if (!$me) {
    $fail(401, 'login_required');
}
$identityId = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'items' => array_map('ecp_wh_shape', ecp_wh_list($identityId))], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $fail(405, 'method_not_allowed');
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
    $fail(403, 'bad_origin');
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$res = ecp_wh_respond($identityId, (int) ($body['id'] ?? 0), (string) ($body['answer'] ?? ''));
if (!$res['ok']) {
    $fail($res['error'] === 'not_found' ? 404 : ($res['error'] === 'bad_action' ? 422 : 500), (string) $res['error']);
}
echo json_encode(['ok' => true, 'status' => $res['status']]);
