<?php
// =====================================================================
// notify.php — marketing-side bridge into the unified notifications queue.
//
// The portal (app/) and the marketing site both write to ONE notifications
// table. This file lets plain-PHP marketing pages (find-a-doctor booking,
// L.php confirm) enqueue WhatsApp-first messages without pulling in the MVC
// app. The portal's NotificationProcessor cron then sends them — applying
// MessagingPolicy (rules + quota + quiet hours) + WhatsApp→SMS fallback.
//
// Centralization: every enqueued row carries patient_identity_id so the
// message ties to the GLOBAL person, not a per-clinic chart.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Enqueue a WhatsApp-first notification. clinic_id 0 = platform/marketing
 * origin (bypasses per-clinic quota in MessagingPolicy).
 *
 * @param array<string,mixed> $payload  template variables
 */
function ecp_enqueue_notification(
    ?int $patientIdentityId,
    string $phone,
    string $template,
    array $payload,
    int $clinicId = 0,
    string $channel = 'whatsapp'
): bool {
    $db = ecp_db();
    if (!$db) return false;

    $phone = trim($phone);
    if ($phone === '') return false;

    $stmt = $db->prepare(
        'INSERT INTO notifications
            (clinic_id, patient_id, patient_identity_id, channel, template,
             to_number, payload, status, scheduled_at)
         VALUES
            (:cid, NULL, :iid, :ch, :tpl, :to, :payload, "queued", NOW())'
    );
    return $stmt->execute([
        'cid'     => $clinicId,
        'iid'     => $patientIdentityId,
        'ch'      => $channel,
        'tpl'     => $template,
        'to'      => $phone,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * Record a positive messaging opt-in for a phone number (marketing-side bridge
 * into the messaging_consent ledger). Idempotent — keeps the first opt-in.
 *
 * Meta's Acceptable Use policy requires a demonstrable opt-in. We call this when
 * a person proves control of their number and chooses to use the service:
 * OTP verification (source 'otp_verify') and directory booking (source
 * 'booking'). Best-effort: never breaks the calling flow if the table is
 * unmigrated.
 */
function ecp_record_optin(string $phone, string $source, ?int $identityId = null): bool {
    $db = ecp_db();
    if (!$db) return false;

    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($digits) < 10) return false;
    $tail = substr($digits, -10);

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $ip = $ip ? substr(explode(',', (string) $ip)[0], 0, 45) : null;

    try {
        $stmt = $db->prepare(
            'INSERT INTO messaging_consent (phone_tail, source, patient_identity_id, raw_phone, ip)
             VALUES (:t, :s, :iid, :r, :ip)
             ON DUPLICATE KEY UPDATE created_at = created_at'
        );
        return $stmt->execute([
            't'   => $tail,
            's'   => substr($source, 0, 32),
            'iid' => $identityId,
            'r'   => substr($phone, 0, 32),
            'ip'  => $ip,
        ]);
    } catch (\Throwable $e) {
        return false; // table unmigrated — don't break the flow
    }
}

/**
 * Doctor confirmed a directory lead from the L/{token} page.
 * Marks the lead book_confirmed (idempotent) + queues the patient confirmation.
 * Returns true if newly confirmed.
 */
function ecp_lead_confirm(int $leadId): bool {
    $db = ecp_db();
    if (!$db) return false;

    // Load the lead with patient + clinic context.
    $stmt = $db->prepare(
        'SELECT dl.*, dd.name AS clinic_name, dd.doctor_name AS directory_doctor_name,
                dd.phone AS clinic_phone, dd.address AS clinic_address,
                dd.area AS clinic_area, dd.city AS clinic_city,
                pi.id AS identity_id, pi.name AS patient_name, pi.phone AS patient_phone
           FROM directory_leads dl
           JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
      LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
          WHERE dl.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $leadId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) return false;
    if (($lead['type'] ?? '') === 'book_confirmed') return true; // idempotent

    $db->prepare(
        "UPDATE directory_leads
            SET type = 'book_confirmed', confirmed_at = NOW(), doctor_contacted_patient = 1
          WHERE id = :id"
    )->execute(['id' => $leadId]);

    // Notify the patient (WhatsApp-first; processor handles SMS fallback).
    if (!empty($lead['patient_phone'])) {
        ecp_enqueue_notification(
            $lead['identity_id'] ? (int) $lead['identity_id'] : null,
            (string) $lead['patient_phone'],
            'patient_confirmed',
            ecp_patient_confirmed_payload($lead),
        );
    }
    return true;
}

/**
 * Build payload for patient_request_sent (6 template vars).
 *
 * @param array<string, mixed> $doctor directory_doctors row
 * @param array<string, mixed> $patientIdentity patient_identities row
 * @param array<string, mixed> $extra ['preferred_date'=>?, 'preferred_time'=>?]
 * @return array<string, string>
 */
function ecp_patient_request_sent_payload(array $doctor, array $patientIdentity, array $extra = []): array
{
    $clinicName = trim((string) ($doctor['name'] ?? ''));
    $doctorName = trim((string) ($doctor['doctor_name'] ?? ''));
    if ($doctorName === '') {
        $doctorName = $clinicName !== '' ? $clinicName : 'your doctor';
    }
    if ($clinicName === '') {
        $clinicName = $doctorName;
    }

    [$appointmentDate, $appointmentTime] = ecp_lead_fmt_appointment_parts(
        isset($extra['preferred_date']) ? (string) $extra['preferred_date'] : null,
        isset($extra['preferred_time']) ? (string) $extra['preferred_time'] : null,
    );

    return [
        'patient_name'     => trim((string) ($patientIdentity['name'] ?? '')) ?: 'there',
        'doctor_name'      => $doctorName,
        'clinic_name'      => $clinicName,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'clinic_address'   => ecp_lead_clinic_address($doctor),
    ];
}

/** @param array<string, mixed> $row */
function ecp_lead_clinic_address(array $row): string
{
    $addressParts = array_values(array_filter([
        trim((string) ($row['address'] ?? $row['clinic_address'] ?? '')),
        trim((string) ($row['area'] ?? $row['clinic_area'] ?? '')),
        trim((string) ($row['city'] ?? $row['clinic_city'] ?? '')),
    ], static fn (string $p) => $p !== ''));

    return $addressParts !== []
        ? implode(', ', $addressParts)
        : 'Contact the clinic for directions';
}

/** @return array{0: string, 1: string} */
function ecp_lead_fmt_appointment_parts(?string $date, ?string $time): array
{
    if ($date === null || $date === '') {
        return ['To be confirmed', 'To be confirmed'];
    }
    $dateTs = strtotime($date);
    $appointmentDate = $dateTs ? date('D, j M Y', $dateTs) : $date;
    if ($time === null || $time === '') {
        return [$appointmentDate, 'To be confirmed'];
    }
    $timeTs = strtotime('2000-01-01 ' . $time);
    $appointmentTime = $timeTs ? date('g:i A', $timeTs) : $time;

    return [$appointmentDate, $appointmentTime];
}

/**
 * Build payload for doctor_new_lead (7 template vars).
 * Template opens with "Hello Dr. {{1}}" — doctor_name omits the Dr. prefix.
 *
 * @param array<string, mixed> $doctor directory_doctors row
 * @param array<string, mixed> $patientIdentity patient_identities row
 * @param array<string, mixed> $extra ['preferred_date'=>?, 'preferred_time'=>?, 'reason'=>?]
 * @return array<string, string>
 */
function ecp_doctor_new_lead_payload(array $doctor, array $patientIdentity, array $extra = []): array
{
    $clinicName = trim((string) ($doctor['name'] ?? ''));
    $doctorName = trim((string) ($doctor['doctor_name'] ?? ''));
    if ($doctorName === '') {
        $doctorName = $clinicName !== '' ? $clinicName : 'Doctor';
    }
    $doctorName = preg_replace('/^Dr\.?\s+/i', '', $doctorName) ?? $doctorName;
    if ($clinicName === '') {
        $clinicName = $doctorName;
    }

    [$appointmentDate, $appointmentTime] = ecp_lead_fmt_appointment_parts(
        isset($extra['preferred_date']) ? (string) $extra['preferred_date'] : null,
        isset($extra['preferred_time']) ? (string) $extra['preferred_time'] : null,
    );

    $reason = trim((string) ($extra['reason'] ?? ''));
    if ($reason === '') {
        $reason = 'Consultation';
    }

    return [
        'doctor_name'      => $doctorName,
        'patient_name'     => trim((string) ($patientIdentity['name'] ?? '')) ?: 'Patient',
        'patient_phone'    => trim((string) ($patientIdentity['phone'] ?? '')) ?: 'Not provided',
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'reason'           => $reason,
        'clinic_name'      => $clinicName,
    ];
}

/**
 * Build payload for the patient_confirmed wa_templates row.
 *
 * @param array<string, mixed> $lead
 * @return array<string, string>
 */
function ecp_patient_confirmed_payload(array $lead): array
{
    $clinicName = trim((string) ($lead['clinic_name'] ?? ''));
    $doctorName = trim((string) ($lead['directory_doctor_name'] ?? ''));
    if ($doctorName === '') {
        $doctorName = $clinicName !== '' ? $clinicName : 'your doctor';
    }

    [$appointmentDate, $appointmentTime] = ecp_lead_fmt_appointment_parts(
        isset($lead['preferred_date']) ? (string) $lead['preferred_date'] : null,
        isset($lead['preferred_time']) ? (string) $lead['preferred_time'] : null,
    );

    return [
        'patient_name' => trim((string) ($lead['patient_name'] ?? '')) ?: 'there',
        'doctor_name' => $doctorName,
        'clinic_name' => $clinicName !== '' ? $clinicName : 'the clinic',
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'clinic_address' => ecp_lead_clinic_address($lead),
    ];
}
