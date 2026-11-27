<?php
// =====================================================================
// api/wellness_report.php — "Report this listing" on a camp / event page.
//
//   POST /api/wellness_report   JSON {"id": 12, "reason": "hidden_fees", "details": "…"}  → {ok}
//
// Anyone may report (signed in or not); at most 5 an hour per IP.
// JSON-only + same-origin check so a cross-site form can't post.
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

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    $fail(400, 'bad_request');
}
$me = ecp_patient_current();
$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$res = ecp_we_report((int) ($body['id'] ?? 0), (string) ($body['reason'] ?? ''), (string) ($body['details'] ?? ''), $me ? (int) $me['id'] : null, $ip);
if (!$res['ok']) {
    $fail(match ($res['error'] ?? '') { 'not_found' => 404, 'too_many' => 429, 'bad_reason' => 400, default => 500 }, (string) $res['error']);
}

// Let the team know (best effort; never blocks the visitor).
try {
    require_once __DIR__ . '/../partials/mailer.php';
    if (function_exists('ecp_send_mail') && function_exists('ecp_email_template')) {
        $to = ecp_env('HEALTHTIPS_ADMIN_EMAIL') ?: (ecp_env('SMTP_TO_EMAIL') ?: 'wecare@eclinicpro.com');
        $url = ecp_portal_url('/admin/wellness/events/' . (int) $body['id']);
        ecp_send_mail($to, 'Wellness Hub: a listing was reported',
            ecp_email_template('A listing was reported', '<p>Reason: <strong>' . e(ECP_WE_REPORT_REASONS[(string) $body['reason']] ?? '') . '</strong></p>'
                . ($body['details'] ?? '' ? '<p>' . nl2br(e(mb_substr((string) $body['details'], 0, 1000))) . '</p>' : '')
                . '<p><a href="' . e($url) . '">Open it in admin</a></p>'));
    }
} catch (Throwable $e) {
    error_log('[api/wellness_report mail] ' . $e->getMessage());
}

echo json_encode(['ok' => true]);
