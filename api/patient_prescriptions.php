<?php
// =====================================================================
// api/patient_prescriptions.php — patient-panel E-prescriptions CRUD (JSON).
// Data lives in patient_prescriptions (one table, both sources). Files live
// under storage/patient_rx/{owner_id}/ and are streamed via ?action=file.
//
//   GET  /api/patient_prescriptions              → list (both sources)
//   POST /api/patient_prescriptions?action=add   → self-upload (multipart)
//   POST /api/patient_prescriptions?action=remove → soft-remove a row
//   GET  /api/patient_prescriptions?action=file&id=… → stream the PDF/image
//
// Auth: ecp_pid cookie (ecp_patient_current). 401 when logged out.
// Every read/mutator re-checks owner_identity_id == logged-in identity (no IDOR).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/patient_prescriptions.php';

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

// The file stream sets its own headers; everything else is JSON.
if ($action === 'file') {
    ecp_rx_stream_file($ownerId, (int) ($_GET['id'] ?? 0));
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

set_exception_handler(function (Throwable $e) {
    error_log('[api/patient_prescriptions] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

switch ($action) {
    case 'add':
        ecp_rx_require_post($isPost);
        // multipart form: text fields in $_POST, file in $_FILES['file'].
        $file = $_FILES['file'] ?? null;
        echo json_encode(ecp_rx_add_upload($ownerId, $_POST, $file));
        break;

    case 'remove':
        ecp_rx_require_post($isPost);
        $body = ecp_rx_body();
        echo json_encode(ecp_rx_remove($ownerId, (int) ($body['id'] ?? 0)));
        break;

    default:
        echo json_encode([
            'ok'    => true,
            'items' => ecp_rx_list($ownerId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ecp_rx_require_post(bool $isPost): void
{
    if (!$isPost) {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }
}

/** JSON or form body for POSTs (add uses multipart, remove may use JSON). */
function ecp_rx_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        return json_decode($raw, true) ?: [];
    }
    return $_POST;
}

/** Stream a stored file inline, re-checking ownership. */
function ecp_rx_stream_file(int $ownerId, int $id): void
{
    $row = ecp_rx_find($ownerId, $id);
    if ($row === null || empty($row['file_path'])) {
        http_response_code(404);
        exit;
    }
    $abs = __DIR__ . '/../' . ltrim((string) $row['file_path'], '/');
    // Defence-in-depth: only ever serve from the patient_rx tree.
    $real = realpath($abs) ?: '';
    $base = realpath(__DIR__ . '/../storage/patient_rx') ?: '';
    if ($real === '' || $base === '' || !str_starts_with($real, $base) || !is_file($real)) {
        http_response_code(404);
        exit;
    }
    $mime = (string) ($row['file_mime'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="prescription-' . $id . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store');
    readfile($real);
}
