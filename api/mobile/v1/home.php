<?php
// =====================================================================
// api/mobile/v1/home.php — dashboard aggregate for the app's Home screen.
//
// ONE call so the app doesn't fan out into a request waterfall on launch.
// Returns only the patient-owned, fast-to-compute data:
//   - patient (greeting)
//   - next_appointment (soonest upcoming, from the same booking source)
//   - counts (upcoming, pending requests, saved doctors)
//
// Deliberately does NOT proxy blogs/tips here: those live on a SEPARATE
// WordPress server (see document 10). The app fetches the blog list and
// today's tip directly from the WP REST API so this endpoint stays fast
// and never blocks the dashboard on a cross-server call.
//
//   GET /api/mobile/v1/home.php   (Bearer)
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_appointments.php';

ecp_m_require_method('GET');
$me = ecp_m_require_patient();
$identityId = (int) $me['id'];

// Bookings — same unified source the appointments list uses.
$bookings = ecp_patient_bookings($identityId);
$nextAppointment = $bookings['upcoming'][0] ?? null;   // already sorted soonest-first

// Saved doctors count (wishlist).
$savedCount = 0;
$db = ecp_db();
if ($db) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM patient_wishlist WHERE identity_id = :iid');
    $stmt->execute(['iid' => $identityId]);
    $savedCount = (int) $stmt->fetchColumn();
}

ecp_m_ok([
    'patient'          => ecp_m_shape_patient($me),
    'next_appointment' => $nextAppointment,
    'counts'           => [
        'upcoming'         => count($bookings['upcoming']),
        'pending_requests' => count($bookings['pending_leads']),
        'saved_doctors'    => $savedCount,
    ],
    // Hints for the app; content itself is fetched from the WP REST API.
    'content_sources'  => [
        'blogs' => 'wordpress_rest',
        'tips'  => 'wordpress_rest',
    ],
]);
