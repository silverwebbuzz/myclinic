<?php
// =====================================================================
// api/wishlist.php — patient shortlist of saved doctors (max 5).
//
//   GET                — list current patient's wishlist (joined with directory_doctors)
//   POST ?action=add   body: { doctor_id, note? }
//   POST ?action=remove body: { doctor_id }
//
// All endpoints require a logged-in patient (ecp_pid cookie). Anonymous
// callers get 401, which is the modal's cue to open and ask the user
// to sign in.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../partials/patient_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ecp_wl_out(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ecp_wl_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '' && str_starts_with(trim($raw), '{')) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
    }
    return $_POST;
}

$me = ecp_patient_current();
if (!$me) ecp_wl_out(401, ['ok' => false, 'error' => 'login_required']);

$db = ecp_db();
if (!$db) ecp_wl_out(503, ['ok' => false, 'error' => 'db_unavailable']);

$identityId = (int) $me['id'];
$action     = $_GET['action'] ?? $_POST['action'] ?? '';
$method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET → list
if ($method === 'GET' && $action === '') {
    $stmt = $db->prepare(
        'SELECT dd.id, dd.name, dd.doctor_name, dd.specialty, dd.area, dd.city,
                dd.state, dd.phone, dd.gmaps_url, dd.photo_reference,
                dd.rating, dd.reviews, w.added_at, w.note
         FROM patient_wishlist w
         JOIN directory_doctors dd ON dd.id = w.doctor_id
         WHERE w.identity_id = :iid AND dd.is_active = 1
         ORDER BY w.added_at DESC'
    );
    $stmt->execute(['iid' => $identityId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = array_map(static function (array $r): array {
        $display = trim((string) ($r['doctor_name'] ?? ''));
        if ($display === '') $display = (string) $r['name'];
        $forInitials = preg_replace('/^Dr\.?\s+/i', '', $display) ?? $display;
        $parts = preg_split('/\s+/', trim($forInitials)) ?: [''];
        $first = mb_substr($parts[0] ?? 'D', 0, 1);
        $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return [
            'id'           => (int) $r['id'],
            'name'         => $display,
            'firstInitial' => $first,
            'lastInitial'  => $last,
            'specLabel'    => ecp_specialty_label($r['specialty'] ?? null),
            'area'         => $r['area']  ?? '',
            'city'         => $r['city']  ?? '',
            'phone'        => $r['phone'] ?? null,
            'gmaps_url'    => $r['gmaps_url'] ?? null,
            'rating'       => isset($r['rating']) ? (float) $r['rating'] : 0,
            'reviews'      => (int) ($r['reviews'] ?? 0),
            'added_at'     => $r['added_at'],
            'note'         => $r['note'] ?? null,
        ];
    }, $rows);

    ecp_wl_out(200, ['ok' => true, 'items' => $items, 'count' => count($items), 'max' => 5]);
}

// POST add / remove
if ($method !== 'POST') ecp_wl_out(405, ['ok' => false, 'error' => 'method_not_allowed']);

$in = ecp_wl_input();
$doctorId = (int) ($in['doctor_id'] ?? 0);
if ($doctorId <= 0) ecp_wl_out(400, ['ok' => false, 'error' => 'doctor_id_required']);

if ($action === 'add') {
    // Enforce 5-doctor cap at the app layer (DB allows more in case we
    // raise the limit later for premium accounts).
    $cnt = $db->prepare('SELECT COUNT(*) FROM patient_wishlist WHERE identity_id = :iid');
    $cnt->execute(['iid' => $identityId]);
    $current = (int) $cnt->fetchColumn();

    $already = $db->prepare(
        'SELECT 1 FROM patient_wishlist WHERE identity_id = :iid AND doctor_id = :did'
    );
    $already->execute(['iid' => $identityId, 'did' => $doctorId]);
    $alreadyIn = (bool) $already->fetchColumn();

    if (!$alreadyIn && $current >= 5) {
        ecp_wl_out(409, ['ok' => false, 'error' => 'limit_reached', 'limit' => 5]);
    }

    $note = isset($in['note']) ? substr((string) $in['note'], 0, 200) : null;

    $db->prepare(
        'INSERT INTO patient_wishlist (identity_id, doctor_id, note)
         VALUES (:iid, :did, :note)
         ON DUPLICATE KEY UPDATE note = COALESCE(VALUES(note), note)'
    )->execute(['iid' => $identityId, 'did' => $doctorId, 'note' => $note]);

    ecp_wl_out(200, ['ok' => true, 'doctor_id' => $doctorId, 'in_wishlist' => true]);
}

if ($action === 'remove') {
    $db->prepare(
        'DELETE FROM patient_wishlist WHERE identity_id = :iid AND doctor_id = :did'
    )->execute(['iid' => $identityId, 'did' => $doctorId]);
    ecp_wl_out(200, ['ok' => true, 'doctor_id' => $doctorId, 'in_wishlist' => false]);
}

ecp_wl_out(400, ['ok' => false, 'error' => 'unknown_action']);
