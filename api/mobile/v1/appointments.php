<?php
// =====================================================================
// api/mobile/v1/appointments.php — appointments + booking facade.
//
// BOOKING IS UNIFIED WITH WEB. A "book" here calls the SAME
// ecp_lead_create($doctorId, $me, 'book_submitted', …) that the website's
// /api/lead.php?action=submit calls. That function records the request in
// directory_leads and triggers the SAME doctor notification (SMS/WhatsApp
// via the existing cron + quota + quiet-hours logic). So a booking from
// the app behaves exactly like a booking from the website, and any future
// change to that flow (templates, caps, channels) reflects in the app with
// NO app update.
//
//   GET  ?action=list        (Bearer) → { upcoming, past, pending_leads }
//   POST ?action=book        (Bearer)
//        { doctor_id, preferred_date:"YYYY-MM-DD", preferred_time:"HH:MM", reason? }
//   POST ?action=track       (Bearer optional)  { doctor_id, type:"view"|"call" }
//
// Not in v1 (no backend yet — see document 10): confirmed slot booking,
// cancel, reschedule, video session, patient payments.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../partials/patient_appointments.php';
require_once __DIR__ . '/../../../partials/directory_leads.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ----- unified booking list (appointments + pending leads) ----------
    case 'list': {
        $me   = ecp_m_require_patient();
        $data = ecp_patient_bookings((int) $me['id']);   // SAME as web /api/patient_bookings
        ecp_m_ok([
            'count' => [
                'upcoming' => count($data['upcoming']),
                'past'     => count($data['past']),
                'pending'  => count($data['pending_leads']),
            ],
            'upcoming'      => $data['upcoming'],
            'past'          => $data['past'],
            'pending_leads' => $data['pending_leads'],
        ]);
        break;
    }

    // ----- book (= submit an appointment request / lead) ----------------
    case 'book': {
        ecp_m_require_method('POST');
        $me = ecp_m_require_patient();

        $in            = ecp_m_input();
        $doctorId      = (int)    ($in['doctor_id'] ?? 0);
        $preferredDate = (string) ($in['preferred_date'] ?? '');
        $preferredTime = (string) ($in['preferred_time'] ?? '');
        $reason        = (string) ($in['reason'] ?? '');

        if ($doctorId <= 0)        ecp_m_err('doctor_id_required', 400);
        if ($preferredDate === '') ecp_m_err('date_required', 400);
        if ($preferredTime === '') ecp_m_err('time_required', 400);

        // Same 7-day booking window the website enforces.
        $now    = strtotime(date('Y-m-d'));
        $picked = strtotime($preferredDate);
        if ($picked === false || $picked < $now) ecp_m_err('date_in_past', 400);
        if ($picked > $now + (7 * 86400))        ecp_m_err('date_out_of_window', 400);

        // Same spam guard: max 5 book_submitted per identity per hour.
        $db = ecp_db();
        if ($db) {
            $g = $db->prepare(
                'SELECT COUNT(*) FROM directory_leads
                 WHERE patient_identity_id = :iid AND type = "book_submitted"
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
            );
            $g->execute(['iid' => (int) $me['id']]);
            if ((int) $g->fetchColumn() >= 5) ecp_m_err('too_many_requests', 429);
        }

        // THE shared booking call. Do not reimplement — this fires the
        // doctor alert through the same channel/quota/cron pipeline as web.
        $res = ecp_lead_create($doctorId, $me, 'book_submitted', [
            'preferred_date' => $preferredDate,
            'preferred_time' => $preferredTime,
            'reason'         => $reason,
        ]);
        if (!$res['ok']) ecp_m_err($res['error'] ?? 'lead_failed', 400);

        ecp_m_ok([
            'lead_id'    => $res['lead_id'],
            'sms_status' => $res['sms_status'],
            'message'    => match ($res['sms_status']) {
                'sent'              => "We've notified the clinic. They'll call you within 24 hours.",
                'suppressed_quota'  => "Request recorded. The clinic will see it next time they sign in.",
                'suppressed_quiet'  => "Request recorded. The clinic will be notified after 8 AM.",
                'suppressed_paused' => "Request recorded. Note: this clinic has paused notifications.",
                'not_applicable'    => "Booking request received.",
                default             => "Request recorded.",
            },
        ]);
        break;
    }

    // ----- lightweight tracking (view/call) — auth optional -------------
    case 'track': {
        ecp_m_require_method('POST');
        $in       = ecp_m_input();
        $doctorId = (int) ($in['doctor_id'] ?? 0);
        $type     = (string) ($in['type'] ?? 'view');
        if (!in_array($type, ['view', 'call'], true)) ecp_m_err('invalid_type', 400);
        $me  = ecp_m_current();   // may be null — fine for 'view'
        $res = ecp_lead_create($doctorId, $me, $type, []);
        ecp_m_ok(['sms_status' => $res['sms_status'] ?? null]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}
