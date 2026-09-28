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

require_once __DIR__ . '/../partials/patient_auth.php';

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
$db = ecp_db();
if (!$db) {
    store_wl_out(503, ['ok' => false, 'error' => 'db_unavailable']);
}

$in = json_decode((string) file_get_contents('php://input'), true);
$productId = (int) (is_array($in) ? ($in['product_id'] ?? 0) : 0);
if ($productId <= 0) {
    store_wl_out(422, ['ok' => false, 'error' => 'product_required']);
}
$identityId = (int) $me['id'];

try {
    $st = $db->prepare("SELECT id FROM store_products WHERE id = :p AND status = 'live' AND deleted_at IS NULL");
    $st->execute(['p' => $productId]);
    $exists = $st->fetchColumn() !== false;

    $st = $db->prepare('SELECT 1 FROM store_wishlist WHERE identity_id = :i AND product_id = :p');
    $st->execute(['i' => $identityId, 'p' => $productId]);
    $saved = $st->fetchColumn() !== false;

    if ($saved) {
        $db->prepare('DELETE FROM store_wishlist WHERE identity_id = :i AND product_id = :p')
           ->execute(['i' => $identityId, 'p' => $productId]);
        $saved = false;
    } elseif ($exists) {
        $db->prepare('INSERT IGNORE INTO store_wishlist (identity_id, product_id) VALUES (:i, :p)')
           ->execute(['i' => $identityId, 'p' => $productId]);
        $saved = true;
    } else {
        store_wl_out(404, ['ok' => false, 'error' => 'product_not_found']);
    }

    $st = $db->prepare('SELECT COUNT(*) FROM store_wishlist WHERE identity_id = :i');
    $st->execute(['i' => $identityId]);
    store_wl_out(200, ['ok' => true, 'saved' => $saved, 'count' => (int) $st->fetchColumn()]);
} catch (Throwable $e) {
    error_log('[api/store_wishlist] ' . $e->getMessage());
    store_wl_out(500, ['ok' => false, 'error' => 'server_error']);
}
