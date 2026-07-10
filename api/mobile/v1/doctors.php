<?php
// =====================================================================
// api/mobile/v1/doctors.php — doctor discovery facade.
//
// Delegates search to the SAME ecp_search_doctors() the website's
// /api/search_doctors.php and /find-a-doctor use, so results, ranking,
// specialty labels, fees and distance are identical to web.
//
//   GET  ?action=search   q,country,state,city,area,spec,min_rating,
//                         sort,lat,lng,max_km,page,per_page
//   GET  ?action=detail&id=123
//   GET  ?action=saved                         (Bearer)
//   POST ?action=save     { doctor_id, note? } (Bearer)
//   POST ?action=unsave   { doctor_id }        (Bearer)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/find_doctor_search.php';

$action = $_GET['action'] ?? 'search';

switch ($action) {

    // ----- public search -------------------------------------------------
    case 'search': {
        $parsed = ecp_parse_find_doctor_filters($_GET, null);
        $resp = ecp_search_doctors([
            'q'          => $parsed['q'] ?? '',
            'country'    => $parsed['country'] ?? 'IN',
            'state'      => $parsed['state'] ?? '',
            'city'       => $parsed['city'] ?? '',
            'area'       => $parsed['area'] ?? '',
            'loc'        => $parsed['loc'] ?? '',
            'spec'       => $parsed['spec'] ?? '',
            'min_rating' => $parsed['min_rating'] ?? 0,
            'sort'       => $parsed['sort'] ?? 'relevance',
            'lat'        => isset($_GET['lat']) ? (float) $_GET['lat'] : null,
            'lng'        => isset($_GET['lng']) ? (float) $_GET['lng'] : null,
            'max_km'     => $_GET['max_km']   ?? 0,
            'page'       => $_GET['page']     ?? 1,
            'per_page'   => $_GET['per_page'] ?? 20,
        ]);
        if (!$resp['ok']) ecp_m_err('db_unavailable', 503);
        // ecp_search_doctors already returns {ok, items, page, per_page, has_more}.
        ecp_m_ok([
            'items'     => $resp['items'],
            'page'      => $resp['page'],
            'per_page'  => $resp['per_page'],
            'has_more'  => $resp['has_more'],
            'total'     => $resp['total'] ?? null,
        ]);
        break;
    }

    // ----- single doctor detail -----------------------------------------
    // Direct fetch by directory_doctors.id, shaped with the SAME helpers
    // the search results use (avatar, specialty label) so a detail payload
    // is a superset of a search card. (ecp_search_doctors has no id filter,
    // and the web profile page routes by SEO slug, which the app doesn't
    // have — so we query by id here, the one stable key the app holds.)
    case 'detail': {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) ecp_m_err('doctor_id_required', 400);
        $doctor = ecp_m_doctor_detail($id);
        if (!$doctor) ecp_m_err('not_found', 404);
        ecp_m_ok(['doctor' => $doctor]);
        break;
    }

    // ----- saved doctors (wishlist) list --------------------------------
    case 'saved': {
        $me = ecp_m_require_patient();
        ecp_m_ok(ecp_m_wishlist_list((int) $me['id']));
        break;
    }

    // ----- save / unsave -------------------------------------------------
    case 'save': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $doctorId = (int) ($in['doctor_id'] ?? 0);
        if ($doctorId <= 0) ecp_m_err('doctor_id_required', 400);
        $note = isset($in['note']) ? substr((string) $in['note'], 0, 200) : null;
        ecp_m_ok(ecp_m_wishlist_add((int) $me['id'], $doctorId, $note));
        break;
    }

    case 'unsave': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();
        $in = ecp_m_input();
        $doctorId = (int) ($in['doctor_id'] ?? 0);
        if ($doctorId <= 0) ecp_m_err('doctor_id_required', 400);
        ecp_m_ok(ecp_m_wishlist_remove((int) $me['id'], $doctorId));
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------
// Single directory doctor by id, shaped with the SAME helpers the search
// results use so the app can render a card and a detail from one model.
// ---------------------------------------------------------------------

function ecp_m_doctor_detail(int $id): ?array {
    $db = ecp_db();
    if (!$db) ecp_m_err('db_unavailable', 503);

    $stmt = $db->prepare(
        'SELECT * FROM directory_doctors
         WHERE id = :id AND is_active = 1 AND status = "OPERATIONAL" LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;

    $avatar     = ecp_directory_avatar($r);
    $clinicName = (string) ($r['name'] ?? '');
    $doctorName = trim((string) ($r['doctor_name'] ?? ''));
    $display    = $doctorName !== '' ? $doctorName : $clinicName;
    $country    = $r['country'] ?? 'IN';

    return [
        'id'          => (int) $r['id'],
        'name'        => $display,
        'doctorName'  => $doctorName ?: null,
        'clinicName'  => $clinicName,
        'hospital'    => ($doctorName !== '' && $clinicName !== $doctorName) ? $clinicName : '',
        'spec'        => $r['specialty'] ?? 'gp',
        'specLabel'   => ecp_specialty_label($r['specialty'] ?? null),
        'verified'    => (bool) ($r['is_claimed'] ?? 0),
        'is_claimed'  => (bool) ($r['is_claimed'] ?? 0),
        'rating'      => isset($r['rating']) ? (float) $r['rating'] : 0,
        'reviews'     => (int) ($r['reviews'] ?? 0),
        'area'        => $r['area']  ?? '',
        'city'        => $r['city']  ?? '',
        'state'       => $r['state'] ?? '',
        'country'     => $country,
        'fee'         => isset($r['consultation_fee']) && $r['consultation_fee'] !== null ? (float) $r['consultation_fee'] : 0,
        'currency'    => $r['consultation_fee_currency'] ?? ($country === 'IN' ? '₹' : '$'),
        'phone'       => $r['phone']     ?? null,
        'website'     => $r['website']   ?? null,
        'gmaps_url'   => $r['gmaps_url'] ?? null,
        'lat'         => isset($r['lat']) ? (float) $r['lat'] : null,
        'lng'         => isset($r['lng']) ? (float) $r['lng'] : null,
        'address'     => $r['address']   ?? null,
        'photo_url'   => $avatar['url'],
        'initials'    => $avatar['initials'],
        'avatar_gradient' => $avatar['gradient'],
        // What the app should offer as the booking action. Both paths go
        // through appointments.php?action=book (the lead flow) in v1.
        'can_request_appointment' => true,
    ];
}

// ---------------------------------------------------------------------
// Wishlist helpers — same SQL and 5-doctor cap as api/wishlist.php.
// (That file inlines its logic rather than exposing functions, so we
// mirror the exact same queries here. If wishlist.php is ever refactored
// into partials/, switch these to call the shared function instead.)
// ---------------------------------------------------------------------

function ecp_m_wishlist_list(int $identityId): array {
    $db = ecp_db();
    if (!$db) ecp_m_err('db_unavailable', 503);

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
        return [
            'id'        => (int) $r['id'],
            'name'      => $display,
            'specLabel' => ecp_specialty_label($r['specialty'] ?? null),
            'area'      => $r['area']  ?? '',
            'city'      => $r['city']  ?? '',
            'phone'     => $r['phone'] ?? null,
            'gmaps_url' => $r['gmaps_url'] ?? null,
            'rating'    => isset($r['rating']) ? (float) $r['rating'] : 0,
            'reviews'   => (int) ($r['reviews'] ?? 0),
            'added_at'  => $r['added_at'],
            'note'      => $r['note'] ?? null,
        ];
    }, $rows);

    return ['items' => $items, 'count' => count($items), 'max' => 5];
}

function ecp_m_wishlist_add(int $identityId, int $doctorId, ?string $note): array {
    $db = ecp_db();
    if (!$db) ecp_m_err('db_unavailable', 503);

    $cnt = $db->prepare('SELECT COUNT(*) FROM patient_wishlist WHERE identity_id = :iid');
    $cnt->execute(['iid' => $identityId]);
    $current = (int) $cnt->fetchColumn();

    $already = $db->prepare('SELECT 1 FROM patient_wishlist WHERE identity_id = :iid AND doctor_id = :did');
    $already->execute(['iid' => $identityId, 'did' => $doctorId]);
    $alreadyIn = (bool) $already->fetchColumn();

    if (!$alreadyIn && $current >= 5) {
        ecp_m_err('limit_reached', 409, ['limit' => 5]);
    }

    $db->prepare(
        'INSERT INTO patient_wishlist (identity_id, doctor_id, note)
         VALUES (:iid, :did, :note)
         ON DUPLICATE KEY UPDATE note = COALESCE(VALUES(note), note)'
    )->execute(['iid' => $identityId, 'did' => $doctorId, 'note' => $note]);

    return ['doctor_id' => $doctorId, 'in_wishlist' => true];
}

function ecp_m_wishlist_remove(int $identityId, int $doctorId): array {
    $db = ecp_db();
    if (!$db) ecp_m_err('db_unavailable', 503);
    $db->prepare('DELETE FROM patient_wishlist WHERE identity_id = :iid AND doctor_id = :did')
       ->execute(['iid' => $identityId, 'did' => $doctorId]);
    return ['doctor_id' => $doctorId, 'in_wishlist' => false];
}
