<?php
// =====================================================================
// api/search_doctors.php — paginated search endpoint.
// Thin wrapper around ecp_search_doctors() in partials/search_doctors_query.php.
//
//   GET /api/search_doctors?
//       q, country, state, city, area, spec, min_rating,
//       sort=relevance|distance|rating|fee_asc|fee_desc|claimed,
//       lat, lng, max_km, page=1, per_page=20
//
// Cached by CloudFlare for 60s — fine because:
//   - filter changes always re-fetch with a new query string (cache key change)
//   - directory updates take effect within a minute for everyone
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../partials/search_doctors_query.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/search_doctors] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
});

$resp = ecp_search_doctors([
    'q'          => $_GET['q']          ?? '',
    'country'    => $_GET['country']    ?? 'IN',
    'state'      => $_GET['state']      ?? '',
    'city'       => $_GET['city']       ?? '',
    'area'       => $_GET['area']       ?? '',
    'spec'       => $_GET['spec']       ?? '',
    'min_rating' => $_GET['min_rating'] ?? 0,
    'sort'       => $_GET['sort']       ?? 'relevance',
    'lat'        => isset($_GET['lat']) ? (float) $_GET['lat'] : null,
    'lng'        => isset($_GET['lng']) ? (float) $_GET['lng'] : null,
    'max_km'     => $_GET['max_km']     ?? 0,
    'page'       => $_GET['page']       ?? 1,
    'per_page'   => $_GET['per_page']   ?? 20,
]);

if (!$resp['ok']) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
