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
 * Doctor confirmed a directory lead from the L/{token} page.
 * Marks the lead book_confirmed (idempotent) + queues the patient confirmation.
 * Returns true if newly confirmed.
 */
function ecp_lead_confirm(int $leadId): bool {
    $db = ecp_db();
    if (!$db) return false;

    // Load the lead with patient + clinic context.
    $stmt = $db->prepare(
        'SELECT dl.*, dd.name AS clinic_name, dd.phone AS clinic_phone,
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
        $slot = $lead['preferred_date']
            ? date('d M Y', strtotime((string) $lead['preferred_date']))
              . ($lead['preferred_time'] ? ', ' . date('g:i A', strtotime('2000-01-01 ' . $lead['preferred_time'])) : '')
            : 'your requested time';

        ecp_enqueue_notification(
            $lead['identity_id'] ? (int) $lead['identity_id'] : null,
            (string) $lead['patient_phone'],
            'patient_confirmed',
            [
                'patient_name' => $lead['patient_name'] ?: 'there',
                'doctor_name'  => $lead['clinic_name'] ?: 'the clinic',
                'datetime'     => $slot,
                'clinic_phone' => $lead['clinic_phone'] ?: '',
            ]
        );
    }
    return true;
}
