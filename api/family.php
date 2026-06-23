<?php
// =====================================================================
// api/family.php — patient-panel Family tab CRUD (JSON).
//
//   GET  /api/family                      → full family payload
//   POST /api/family?action=save_member   → create/update a member
//   POST /api/family?action=remove_member → soft-remove a member link
//   POST /api/family?action=save_policy    → create/update an insurance policy
//   POST /api/family?action=delete_policy  → delete a policy
//
// Auth: ecp_pid cookie (ecp_patient_current). 401 when logged out.
// Every mutator re-checks ownership inside the data layer (no IDOR).
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/patient_family.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/family] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

$me = ecp_patient_current();
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}
$ownerId = (int) $me['id'];

$action = (string) ($_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// Read JSON or form body for POSTs.
$body = [];
if ($isPost) {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $body = json_decode($raw, true) ?: [];
    } else {
        $body = $_POST;
    }
}

switch ($action) {
    case 'save_member':
        ecp_require_post($isPost);
        echo json_encode(ecp_fam_save_member($ownerId, $body));
        break;

    case 'remove_member':
        ecp_require_post($isPost);
        echo json_encode(ecp_fam_remove_member($ownerId, (int) ($body['member_id'] ?? 0)));
        break;

    case 'save_policy':
        ecp_require_post($isPost);
        echo json_encode(ecp_fam_save_policy($ownerId, $body));
        break;

    case 'delete_policy':
        ecp_require_post($isPost);
        echo json_encode(ecp_fam_delete_policy($ownerId, (int) ($body['policy_id'] ?? 0)));
        break;

    default:
        // GET — full family list.
        echo json_encode([
            'ok'      => true,
            'owner_id' => $ownerId,
            'members' => ecp_fam_list($ownerId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ecp_require_post(bool $isPost): void
{
    if (!$isPost) {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }
}
