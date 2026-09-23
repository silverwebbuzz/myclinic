<?php
// =====================================================================
// api/mobile/v1/queue.php — live token queue for the app's Queue screen.
//
// The design shows "now serving A-09 · you are A-14 · 5 ahead". Nothing in
// the shared layer computed that, so this route adds the ONE query the web
// panel never needed, on top of the SAME booking source the appointments
// list uses (ecp_patient_bookings → partials/patient_appointments.php).
//
// HOW TOKENS ACTUALLY WORK (see AppointmentService::nextTokenNumber):
//   - Tokens are per CLINIC, per DAY — not per doctor. Two doctors in one
//     clinic draw from the same appointment_token_counters row.
//   - A token is assigned only when reception moves a visit to
//     'in_progress'. A scheduled/confirmed appointment has token_number
//     NULL until then, so the app must handle "no token yet".
//   - "Now serving" is therefore the highest token among today's
//     in_progress appointments at that clinic.
//
// BECAUSE OF THAT, `ahead` is an ESTIMATE of people in front of the patient
// and is only meaningful once they hold a token. The app should poll this
// while the Queue screen is open (≈30s is plenty — tokens move slowly) and
// fall back to the appointment time when `has_token` is false.
//
//   GET ?action=status                 (Bearer) → queue for the next visit
//   GET ?action=status&appointment_id= (Bearer) → queue for one appointment
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_appointments.php';

$action = $_GET['action'] ?? 'status';
if ($action !== 'status') ecp_m_err('unknown_action', 400);

ecp_m_require_method('GET');
$me = ecp_m_require_patient();

// Only ever consider THIS patient's own bookings — the appointment id from
// the query string is matched against that list, never queried directly, so
// a foreign id can't be probed.
$bookings = ecp_patient_bookings((int) $me['id']);
$wantedId = (int) ($_GET['appointment_id'] ?? 0);

// Pending leads have no appointment row (and no token), so consider only
// real appointments — _patient_shape_appt() tags those kind = 'appointment'.
$isAppt = static fn (array $r): bool => ($r['kind'] ?? '') === 'appointment';

$appt = null;
if ($wantedId > 0) {
    foreach (array_merge($bookings['upcoming'], $bookings['past']) as $row) {
        // NOTE: the shared shaper exposes the appointment id as `id`.
        if ($isAppt($row) && (int) ($row['id'] ?? 0) === $wantedId) { $appt = $row; break; }
    }
    if ($appt === null) ecp_m_err('not_found', 404);
} else {
    // Default: the soonest upcoming appointment (already sorted).
    foreach ($bookings['upcoming'] as $row) {
        if ($isAppt($row)) { $appt = $row; break; }
    }
}

if ($appt === null) {
    ecp_m_ok([
        'in_queue'    => false,
        'reason'      => 'no_upcoming_appointment',
        'appointment' => null,
        'queue'       => null,
    ]);
}

$apptId  = (int) ($appt['id'] ?? 0);
// _patient_shape_appt() drops clinic_id, so resolve it here — scoped to
// this identity, so it doubles as a re-check that the row is really theirs.
$clinicId = ecp_m_clinic_for_appointment((int) $me['id'], $apptId);
$myToken  = isset($appt['token_number']) && $appt['token_number'] !== null
                ? (int) $appt['token_number'] : null;
$isToday  = ecp_m_is_today((string) ($appt['when_iso'] ?? ''));

$serving   = $clinicId > 0 ? ecp_m_now_serving($clinicId) : null;
$waiting   = $clinicId > 0 ? ecp_m_waiting_count($clinicId) : 0;

// "Ahead" only means something once the patient holds a token AND someone
// is being seen. Never report a negative (their token can be below the one
// in the room when reception serves out of order).
$ahead = null;
if ($myToken !== null && $serving !== null) {
    $ahead = max(0, $myToken - $serving);
}

ecp_m_ok([
    'in_queue'    => $isToday,
    // Why the queue may not be live yet — lets the app pick its empty state
    // without re-deriving the rule from dates.
    'reason'      => $isToday ? null : 'appointment_not_today',
    'appointment' => [
        'appointment_id' => $apptId,
        'when_iso'       => $appt['when_iso']     ?? null,
        'status'         => $appt['status']       ?? null,
        'doctor_name'    => $appt['doctor_name']  ?? null,
        'clinic_id'      => $clinicId ?: null,
        'clinic_name'    => $appt['clinic_name']  ?? null,
        'clinic_phone'   => $appt['clinic_phone'] ?? null,
    ],
    'queue' => [
        'has_token'    => $myToken !== null,
        'my_token'     => $myToken,
        'now_serving'  => $serving,
        'ahead'        => $ahead,
        // People checked in and not yet finished — a useful crowd signal
        // even before this patient has a token of their own.
        'waiting'      => $waiting,
        // Tokens are clinic-wide, so say so: a patient seeing "A-09" for a
        // different doctor in the same clinic is not a bug.
        'token_scope'  => 'clinic_day',
        'checked_at'   => date('c'),
        'poll_seconds' => 30,
    ],
]);

// ---------------------------------------------------------------------

/**
 * The clinic an appointment belongs to, scoped to the owning identity.
 *
 * ecp_patient_bookings() selects clinic_id but _patient_shape_appt() does
 * not pass it through, and the web panel never needed it. Rather than
 * change that shared shape (it feeds the website too), look it up here —
 * joined through patients.identity_id so an appointment belonging to
 * someone else resolves to 0 and yields an empty queue, never another
 * clinic's numbers.
 */
function ecp_m_clinic_for_appointment(int $identityId, int $appointmentId): int {
    $db = ecp_db();
    if (!$db || $identityId <= 0 || $appointmentId <= 0) return 0;
    $stmt = $db->prepare(
        'SELECT a.clinic_id FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         WHERE a.id = :aid AND p.identity_id = :iid LIMIT 1'
    );
    $stmt->execute(['aid' => $appointmentId, 'iid' => $identityId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/**
 * The token currently in the consulting room at a clinic today.
 *
 * Highest token among today's in_progress appointments — matches how
 * AppointmentService assigns tokens (on the move to in_progress, from a
 * per-clinic per-day counter). NULL when the clinic has not started.
 */
function ecp_m_now_serving(int $clinicId): ?int {
    $db = ecp_db();
    if (!$db) return null;
    $stmt = $db->prepare(
        'SELECT MAX(token_number) FROM appointments
         WHERE clinic_id = :cid
           AND status = "in_progress"
           AND token_number IS NOT NULL
           AND scheduled_at >= CURDATE()
           AND scheduled_at <  CURDATE() + INTERVAL 1 DAY'
    );
    $stmt->execute(['cid' => $clinicId]);
    $v = $stmt->fetchColumn();
    return ($v === false || $v === null) ? null : (int) $v;
}

/**
 * How many people are still to be seen at this clinic today: arrived or
 * waiting, excluding anyone already finished or gone.
 */
function ecp_m_waiting_count(int $clinicId): int {
    $db = ecp_db();
    if (!$db) return 0;
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM appointments
         WHERE clinic_id = :cid
           AND status IN ("scheduled","confirmed","in_progress")
           AND scheduled_at >= CURDATE()
           AND scheduled_at <  CURDATE() + INTERVAL 1 DAY'
    );
    $stmt->execute(['cid' => $clinicId]);
    return (int) $stmt->fetchColumn();
}

/** True when an ISO datetime falls on the server's current date. */
function ecp_m_is_today(string $iso): bool {
    if ($iso === '') return false;
    $ts = strtotime($iso);
    return $ts !== false && date('Y-m-d', $ts) === date('Y-m-d');
}
