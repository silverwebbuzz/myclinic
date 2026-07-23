<?php
// =====================================================================
// api/lab_pincode.php — lab home-collection serviceability check (JSON).
//
//   GET /api/lab_pincode.php?pin=380001
//     → { "ok": true, "serviceable": true, "pin": "380001",
//         "city": "Ahmedabad", "state": "Gujarat" }
//     → { "ok": true, "serviceable": false, "pin": "560500" }
//
// Data source: assets/data/serviceable-pincodes.json, an object keyed by
// pincode ({ "380001": {"city":..,"state":..} }) generated from
// document/serviceable-pincodes.csv by fetch_doctor/enrich_pincodes.php.
// (Lives under assets/ so it's tracked by git and deploys with the code.)
// The map is loaded once per request and cached in a static, so the
// ~150KB list is never shipped to the browser and never hits the DB.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400'); // pincode map rarely changes

/**
 * Load the serviceable-pincode map once. Returns [] if the file is missing
 * or malformed (fail-closed: nothing serviceable rather than a hard error).
 */
function ecp_pincode_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $path = __DIR__ . '/../assets/data/serviceable-pincodes.json';
    $map = [];
    if (is_readable($path)) {
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data)) {
            $map = $data;
        }
    }
    return $map;
}

$pin = preg_replace('/\D/', '', (string) ($_GET['pin'] ?? ''));

if (!preg_match('/^[1-9][0-9]{5}$/', (string) $pin)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_pincode']);
    exit;
}

$map = ecp_pincode_map();
$rec = $map[$pin] ?? null;

if (is_array($rec) && !empty($rec['city']) && !empty($rec['state'])) {
    echo json_encode([
        'ok'          => true,
        'serviceable' => true,
        'pin'         => $pin,
        'city'        => (string) $rec['city'],
        'state'       => (string) $rec['state'],
    ]);
    exit;
}

echo json_encode([
    'ok'          => true,
    'serviceable' => false,
    'pin'         => $pin,
]);
