<?php
// =====================================================================
// api/store_wishlist.php — eClinicPro Store product wishlist.
//
//   POST { product_id }   toggle; returns { ok, saved: bool, count }
//
// Requires a logged-in patient (ecp_pid). 401 = open the login modal.
// CSRF: the session cookie is SameSite=Lax (no cross-site POSTs), and we
// additionally require a JSON body, which a cross-site form can't send and a
// cross-site fetch can't send without a CORS preflight we never approve.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../store/_lib.php';

function store_wl_out(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    store_wl_out(405, ['ok' => false, 'error' => 'method_not_allowed']);
}
if (!str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    store_wl_out(415, ['ok' => false, 'error' => 'json_required']);
}

$me = ecp_patient_current();
if (!$me) {
    store_wl_out(401, ['ok' => false, 'error' => 'login_required']);
}
$in = json_decode((string) file_get_contents('php://input'), true);
$productId = (int) (is_array($in) ? ($in['product_id'] ?? 0) : 0);

try {
    $res = store_wishlist_toggle((int) $me['id'], $productId);
    if (!$res['ok']) {
        $status = ['db_unavailable' => 503, 'product_required' => 422, 'product_not_found' => 404][$res['error']] ?? 400;
        store_wl_out($status, $res);
    }
    store_wl_out(200, $res);
} catch (Throwable $e) {
    error_log('[api/store_wishlist] ' . $e->getMessage());
    store_wl_out(500, ['ok' => false, 'error' => 'server_error']);
}
