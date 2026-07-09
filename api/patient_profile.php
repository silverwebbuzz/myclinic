<?php
// =====================================================================
// api/patient_profile.php — patient-panel "My Profile" tab (JSON).
// Reads/writes the logged-in person's OWN patient_identities row. Covers
// everything the Family members box does not: contact, address, emergency
// contact, medical notes, lifestyle, and profile photo.
//
//   GET  /api/patient_profile                 → current profile fields
//   POST /api/patient_profile?action=save     → partial save (JSON or form)
//   POST /api/patient_profile?action=photo     → upload avatar (multipart)
//   GET  /api/patient_profile?action=photo     → stream the avatar
//
// Auth: ecp_pid cookie (ecp_patient_current). 401 when logged out.
// Every read/write is scoped to the logged-in identity id (no IDOR).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/patient_profile.php';

$me = ecp_patient_current();
if (!$me) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}
$ownerId = (int) $me['id'];

$action = (string) ($_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// GET photo sets its own headers; everything else is JSON.
if ($action === 'photo' && !$isPost) {
    ecp_profile_stream_photo($ownerId);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e) {
    error_log('[api/patient_profile] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

switch ($action) {
    case 'check_phone':
        $in = ecp_profile_body();
        $phone = (string) ($in['phone'] ?? ($_GET['phone'] ?? ''));
        echo json_encode([
            'ok' => true,
            'available' => ecp_profile_phone_available($ownerId, $phone),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'send_phone_otp':
        ecp_profile_require_post($isPost);
        $in = ecp_profile_body();
        echo json_encode(ecp_profile_send_phone_otp($ownerId, (string) ($in['phone'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'verify_phone_otp':
        ecp_profile_require_post($isPost);
        $in = ecp_profile_body();
        $res = ecp_profile_verify_phone_otp($ownerId, (string) ($in['phone'] ?? ''), (string) ($in['code'] ?? ''));
        if (!empty($res['ok'])) {
            $res['profile'] = ecp_profile_get($ownerId);
        }
        echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'save':
        ecp_profile_require_post($isPost);
        echo json_encode(ecp_profile_save($ownerId, ecp_profile_body()));
        break;

    case 'photo':
        ecp_profile_require_post($isPost); // POST = upload (GET handled above)
        echo json_encode(ecp_profile_save_photo($ownerId, $_FILES['photo'] ?? null));
        break;

    default:
        echo json_encode([
            'ok'      => true,
            'profile' => ecp_profile_get($ownerId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ecp_profile_require_post(bool $isPost): void
{
    if (!$isPost) {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }
}

/** JSON or form body for POSTs (save uses JSON, photo uses multipart). */
function ecp_profile_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        return json_decode($raw, true) ?: [];
    }
    return $_POST;
}
