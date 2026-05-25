<?php
// =====================================================================
// api/patient_auth.php — JSON endpoints for the patient login modal.
//
// All endpoints return JSON. They never throw HTML errors at the user.
//
//   POST ?action=send_otp     body: { phone }
//   POST ?action=verify_otp   body: { phone, code, name? }
//   POST ?action=logout       body: {}
//   GET  ?action=me           — returns currently logged-in identity (or null)
//
// Front-end fetches with credentials: 'same-origin' so the cookie flows.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../partials/patient_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/** Quick helper: emit JSON and stop. */
function ecp_api_out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Read JSON body OR fall back to form-encoded POST. */
function ecp_api_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_starts_with(trim($raw), '{')) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($action) {

    // -----------------------------------------------------------------
    case 'send_otp': {
        if ($method !== 'POST') ecp_api_out(405, ['ok' => false, 'error' => 'method_not_allowed']);

        $in    = ecp_api_input();
        $phone = (string) ($in['phone'] ?? '');
        if ($phone === '') ecp_api_out(400, ['ok' => false, 'error' => 'phone_required']);

        $res = ecp_patient_send_otp($phone);

        if (!$res['ok']) {
            $status = match ($res['error']) {
                'invalid_phone'    => 400,
                'resend_too_soon'  => 429,
                'db_unavailable'   => 503,
                default            => 500,
            };
            ecp_api_out($status, [
                'ok'          => false,
                'error'       => $res['error'],
                'retry_after' => $res['retry_after'] ?? null,
            ]);
        }

        ecp_api_out(200, [
            'ok'       => true,
            'phone'    => $res['phone'],
            'mode'     => $res['mode'],
            // dev_code is null in live mode. The modal shows it when present
            // so testers don't need to tail a log file.
            'dev_code' => $res['dev_code'],
            'message'  => $res['mode'] === 'dev'
                ? 'OTP printed to storage/logs/otp.log (dev mode)'
                : 'OTP sent. Check your phone.',
        ]);
    }

    // -----------------------------------------------------------------
    case 'verify_otp': {
        if ($method !== 'POST') ecp_api_out(405, ['ok' => false, 'error' => 'method_not_allowed']);

        $in    = ecp_api_input();
        $phone = (string) ($in['phone'] ?? '');
        $code  = (string) ($in['code']  ?? '');
        $name  = isset($in['name']) ? (string) $in['name'] : null;

        if ($phone === '' || $code === '') {
            ecp_api_out(400, ['ok' => false, 'error' => 'phone_and_code_required']);
        }

        $res = ecp_patient_verify_otp($phone, $code, $name);
        if (!$res['ok']) {
            $status = match ($res['error']) {
                'invalid_code', 'invalid_input' => 400,
                'expired', 'no_code_issued'     => 410,
                'too_many_attempts'             => 429,
                'db_unavailable'                => 503,
                default                         => 500,
            };
            ecp_api_out($status, ['ok' => false, 'error' => $res['error']]);
        }

        // Shape the identity for the client (don't leak everything).
        $i = $res['identity'];
        ecp_api_out(200, [
            'ok'      => true,
            'is_new'  => $res['is_new'],
            'patient' => [
                'id'         => (int) $i['id'],
                'name'       => $i['name'],
                'first_name' => $i['first_name'] ?? null,
                'phone'      => $i['phone'],
                'email'      => $i['email'] ?? null,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    case 'me': {
        $i = ecp_patient_current();
        if (!$i) ecp_api_out(200, ['ok' => true, 'patient' => null]);
        ecp_api_out(200, [
            'ok' => true,
            'patient' => [
                'id'         => (int) $i['id'],
                'name'       => $i['name'],
                'first_name' => $i['first_name'] ?? null,
                'phone'      => $i['phone'],
                'email'      => $i['email'] ?? null,
            ],
        ]);
    }

    // -----------------------------------------------------------------
    case 'logout': {
        if ($method !== 'POST') ecp_api_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
        ecp_patient_logout();
        ecp_api_out(200, ['ok' => true]);
    }

    // -----------------------------------------------------------------
    default:
        ecp_api_out(400, ['ok' => false, 'error' => 'unknown_action']);
}
