<?php
// =====================================================================
// api/patient_bookings.php — returns the logged-in patient's bookings.
//
//   GET /api/patient_bookings
//
// Combines:
//   - confirmed appointments at claimed clinics (table: appointments)
//   - pending lead requests to unclaimed clinics (table: directory_leads)
//
// Auth: existing ecp_pid cookie. 401 if logged out.
// =====================================================================

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../partials/patient_auth.php';
require_once __DIR__ . '/../partials/patient_appointments.php';

set_exception_handler(function (Throwable $e) {
    error_log('[api/patient_bookings] ' . $e->getMessage());
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

$data = ecp_patient_bookings((int) $me['id']);

echo json_encode([
    'ok'    => true,
    'count' => [
        'upcoming' => count($data['upcoming']),
        'past'     => count($data['past']),
        'pending'  => count($data['pending_leads']),
    ],
    'upcoming'      => $data['upcoming'],
    'past'          => $data['past'],
    'pending_leads' => $data['pending_leads'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
