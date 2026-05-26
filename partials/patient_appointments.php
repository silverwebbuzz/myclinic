<?php
// =====================================================================
// patient_appointments.php — aggregated booking history for the patient
// panel. Unions two sources:
//
//   1) appointments — confirmed bookings at CLAIMED clinics. Joined via
//      patients.identity_id (the link between a clinic chart and the
//      global identity).
//
//   2) directory_leads — booking requests sent to UNCLAIMED clinics.
//      These are "pending" — we sent the clinic an SMS but they haven't
//      confirmed (and probably never will until they claim).
//
// Returns one flat list sorted by date desc, with a `kind` field telling
// the UI which source it came from. The shape is what /api/patient_bookings
// returns to the front-end.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * @param int $identityId  patient_identities.id
 * @return array{upcoming: list<array>, past: list<array>, pending_leads: list<array>}
 */
function ecp_patient_bookings(int $identityId): array {
    $db = ecp_db();
    if (!$db) return ['upcoming' => [], 'past' => [], 'pending_leads' => []];

    // -------- 1) Real appointments at claimed clinics --------
    $stmt = $db->prepare(
        'SELECT
            a.id AS appointment_id,
            a.scheduled_at,
            a.status,
            a.chief_complaint,
            a.token_number,
            a.is_followup,
            a.type AS appt_type,
            t.id   AS clinic_id,
            t.name AS clinic_name,
            t.slug AS clinic_slug,
            t.phone AS clinic_phone,
            t.address AS clinic_address,
            u.id   AS doctor_id,
            u.name AS doctor_name,
            u.specialization AS doctor_spec
         FROM appointments a
         JOIN patients p   ON p.id = a.patient_id
         JOIN tenants  t   ON t.id = a.clinic_id
         LEFT JOIN users u ON u.id = a.doctor_id
         WHERE p.identity_id = :iid
         ORDER BY a.scheduled_at DESC
         LIMIT 100'
    );
    $stmt->execute(['iid' => $identityId]);
    $appts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $now = time();
    $upcoming = [];
    $past     = [];
    foreach ($appts as $a) {
        $ts = strtotime((string) $a['scheduled_at']);
        $shaped = _patient_shape_appt($a);
        if ($ts >= $now - 3600 && !in_array($a['status'], ['cancelled','no_show','completed'], true)) {
            $upcoming[] = $shaped;
        } else {
            $past[] = $shaped;
        }
    }

    // Sort upcoming ascending (next first), past descending (newest first).
    usort($upcoming, static fn ($x, $y) => strcmp((string) $x['when_iso'], (string) $y['when_iso']));

    // -------- 2) Pending leads to unclaimed clinics --------
    $stmt = $db->prepare(
        'SELECT
            dl.id, dl.preferred_date, dl.preferred_time, dl.reason,
            dl.sms_status, dl.created_at, dl.doctor_viewed_at,
            dd.id   AS doctor_directory_id,
            dd.name AS clinic_name,
            dd.doctor_name AS doctor_name,
            dd.specialty,
            dd.area, dd.city, dd.state,
            dd.phone AS clinic_phone,
            dd.is_claimed
         FROM directory_leads dl
         JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
         WHERE dl.patient_identity_id = :iid
           AND dl.type = "book_submitted"
         ORDER BY dl.created_at DESC
         LIMIT 100'
    );
    $stmt->execute(['iid' => $identityId]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pending = array_map('_patient_shape_lead', $leads);

    return [
        'upcoming'      => $upcoming,
        'past'          => $past,
        'pending_leads' => $pending,
    ];
}

function _patient_shape_appt(array $a): array {
    $ts = strtotime((string) $a['scheduled_at']);
    return [
        'kind'           => 'appointment',
        'id'             => (int) $a['appointment_id'],
        'when_iso'       => date('c', $ts),
        'when_date'      => date('D, j M Y', $ts),
        'when_time'      => date('g:i A', $ts),
        'status'         => $a['status'],
        'is_followup'    => (bool) $a['is_followup'],
        'token_number'   => $a['token_number'] ? (int) $a['token_number'] : null,
        'reason'         => $a['chief_complaint'] ?? null,
        'doctor_name'    => $a['doctor_name'] ?? null,
        'doctor_spec'    => $a['doctor_spec'] ?? null,
        'clinic_name'    => $a['clinic_name'],
        'clinic_slug'    => $a['clinic_slug'],
        'clinic_phone'   => $a['clinic_phone'] ?? null,
        'clinic_address' => $a['clinic_address'] ?? null,
    ];
}

function _patient_shape_lead(array $l): array {
    $datePretty = $l['preferred_date']
        ? date('D, j M Y', strtotime((string) $l['preferred_date']))
        : 'Anytime';
    $timePretty = $l['preferred_time']
        ? date('g:i A', strtotime('2000-01-01 ' . $l['preferred_time']))
        : '';
    return [
        'kind'              => 'lead',
        'id'                => (int) $l['id'],
        'when_iso'          => $l['preferred_date'] ? $l['preferred_date'] : (string) $l['created_at'],
        'when_date'         => $datePretty,
        'when_time'         => $timePretty,
        // 'pending' = SMS dispatched OK and waiting on the doctor.
        // 'awaiting_clinic' = SMS suppressed (quota/quiet) — clinic hasn't seen it yet.
        'status'            => match ($l['sms_status']) {
            'sent'              => $l['doctor_viewed_at'] ? 'clinic_viewed' : 'awaiting_clinic',
            'suppressed_quota',
            'suppressed_quiet',
            'suppressed_paused' => 'awaiting_clinic',
            'failed'            => 'delivery_failed',
            default             => 'awaiting_clinic',
        },
        'reason'            => $l['reason'] ?? null,
        'doctor_name'       => $l['doctor_name'] ?? null,
        'doctor_spec'       => $l['specialty'] ?? null,
        'clinic_name'       => $l['clinic_name'],
        'clinic_phone'      => $l['clinic_phone'] ?? null,
        'clinic_address'    => trim(($l['area'] ?? '') . (($l['area'] && $l['city']) ? ', ' : '') . ($l['city'] ?? '')),
        'requested_at'      => $l['created_at'],
        'doctor_viewed_at'  => $l['doctor_viewed_at'] ?? null,
    ];
}
