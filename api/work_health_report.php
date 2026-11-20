<?php
// =====================================================================
// api/work_health_report.php — employee uploads a test report for an open
// lab / X-ray check (Workforce Health 1D-lite).
//
//   POST multipart: case_id, file, lab_name?, report_date?, note?
//   → { ok } | { ok:false, error }
//
// Auth: ecp_pid cookie; only the person's own open checks (no IDOR).
// CSRF: multipart can come from a cross-site form, so we require the
// X-Requested-With header (a plain form can't set it) and, when present,
// a same-origin Origin.
// The file goes to the examining doctor and the employee's own vault,
// never to the employer.
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    $fail(405, 'method_not_allowed');
}
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
    $fail(403, 'bad_origin');
}

try {
    $res = ecp_wh_report_upload((int) $me['id'], (int) ($_POST['case_id'] ?? 0), $_POST, $_FILES['file'] ?? null);
} catch (Throwable $e) {
    error_log('[api/work_health_report] ' . $e->getMessage());
    $fail(500, 'Upload failed. Please try again.');
}
if (!$res['ok']) {
    $fail(422, (string) ($res['error'] ?? 'Upload failed.'));
}
echo json_encode(['ok' => true]);
