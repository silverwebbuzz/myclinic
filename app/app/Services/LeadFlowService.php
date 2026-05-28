<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * LeadFlowService — lifecycle of a directory booking lead (non-joined doctor).
 *
 * Lead CREATION happens on the marketing side (partials/directory_leads.php),
 * which writes the directory_leads row + view_token and enqueues the patient +
 * doctor messages via partials/notify.php. This portal-side service owns the
 * parts that run inside the app / on cron / from the webhook:
 *
 *   - confirm($token)   — doctor tapped Confirm (via L/{token} page or WA button)
 *   - runNudges()       — cron: soft nudge (~2h) + appointment reminder (~2h before)
 *   - expireStale()     — cron: mark past-time unconfirmed leads as no_response
 *
 * Centralized: operates only on directory_leads (+ notifications queue). No new
 * booking table. patient_identity_id links everything to the global patient.
 */
final class LeadFlowService
{
    /** Doctor confirmed the lead. Idempotent. Queues the patient confirmation. */
    public static function confirm(string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT dl.*, dd.name AS doctor_name, dd.phone AS doctor_phone,
                    pi.name AS patient_name, pi.phone AS patient_phone
               FROM directory_leads dl
          LEFT JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
          LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
              WHERE dl.view_token = :t
              LIMIT 1'
        );
        $stmt->execute([':t' => $token]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lead) {
            return false;
        }
        if (($lead['type'] ?? '') === 'book_confirmed') {
            return true; // already confirmed — idempotent
        }

        $pdo->prepare(
            "UPDATE directory_leads
                SET type = 'book_confirmed', confirmed_at = NOW(),
                    doctor_contacted_patient = 1
              WHERE id = :id"
        )->execute([':id' => $lead['id']]);

        // Tell the patient (WhatsApp-first, SMS fallback handled by processor).
        if (!empty($lead['patient_phone'])) {
            self::enqueue(
                (int) ($lead['patient_identity_id'] ?? 0) ?: null,
                (string) $lead['patient_phone'],
                'patient_confirmed',
                [
                    'patient_name' => $lead['patient_name'] ?? 'there',
                    'doctor_name' => $lead['doctor_name'] ?? 'the clinic',
                    'datetime' => self::fmtSlot($lead),
                    'clinic_phone' => $lead['doctor_phone'] ?? '',
                ]
            );
        }
        return true;
    }

    /**
     * Cron (~every 15 min): two capped, time-aware patient touchpoints.
     * Returns count of messages queued.
     */
    public static function runNudges(): int
    {
        $pdo = Database::connection();
        $queued = 0;

        // --- Soft nudge: ~2h after booking, still unconfirmed, appt >3h away ---
        $soft = $pdo->query(
            "SELECT dl.*, dd.name AS doctor_name, dd.phone AS doctor_phone,
                    pi.name AS patient_name, pi.phone AS patient_phone
               FROM directory_leads dl
          LEFT JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
          LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
              WHERE dl.type = 'book_submitted'
                AND dl.confirmed_at IS NULL
                AND dl.soft_nudge_sent_at IS NULL
                AND dl.created_at <= NOW() - INTERVAL 2 HOUR
                AND (dl.preferred_date IS NULL
                     OR CONCAT(dl.preferred_date,' ',COALESCE(dl.preferred_time,'09:00'))
                        >= NOW() + INTERVAL 3 HOUR)
                AND pi.phone IS NOT NULL
              LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($soft as $lead) {
            // Don't message in the dead of night — defer to clinic hours (8am+).
            if ((int) date('G') < 8) {
                continue;
            }
            self::enqueue(
                (int) ($lead['patient_identity_id'] ?? 0) ?: null,
                (string) $lead['patient_phone'],
                'patient_soft_nudge',
                [
                    'patient_name' => $lead['patient_name'] ?? 'there',
                    'doctor_name' => $lead['doctor_name'] ?? 'the clinic',
                    'clinic_phone' => $lead['doctor_phone'] ?? '',
                ]
            );
            $pdo->prepare('UPDATE directory_leads SET soft_nudge_sent_at = NOW() WHERE id = :id')
                ->execute([':id' => $lead['id']]);
            $queued++;
        }

        // --- Appointment reminder: ~2h before, not yet reminded, not cancelled ---
        $rem = $pdo->query(
            "SELECT dl.*, dd.name AS doctor_name, dd.phone AS doctor_phone,
                    pi.name AS patient_name, pi.phone AS patient_phone
               FROM directory_leads dl
          LEFT JOIN directory_doctors dd ON dd.id = dl.directory_doctor_id
          LEFT JOIN patient_identities pi ON pi.id = dl.patient_identity_id
              WHERE dl.type IN ('book_submitted','book_confirmed')
                AND dl.reminder_sent_at IS NULL
                AND dl.preferred_date IS NOT NULL
                AND CONCAT(dl.preferred_date,' ',COALESCE(dl.preferred_time,'09:00'))
                    BETWEEN NOW() AND NOW() + INTERVAL 2 HOUR
                AND pi.phone IS NOT NULL
              LIMIT 200"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rem as $lead) {
            self::enqueue(
                (int) ($lead['patient_identity_id'] ?? 0) ?: null,
                (string) $lead['patient_phone'],
                'appointment_reminder',
                [
                    'patient_name' => $lead['patient_name'] ?? 'there',
                    'doctor_name' => $lead['doctor_name'] ?? 'the clinic',
                    'time' => $lead['preferred_time'] ?? '',
                    'clinic_phone' => $lead['doctor_phone'] ?? '',
                ]
            );
            $pdo->prepare('UPDATE directory_leads SET reminder_sent_at = NOW() WHERE id = :id')
                ->execute([':id' => $lead['id']]);
            $queued++;
        }

        return $queued;
    }

    /** Cron (hourly): mark unconfirmed leads whose slot has passed as no_response (call type). */
    public static function expireStale(): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE directory_leads
                SET type = 'call'
              WHERE type = 'book_submitted'
                AND confirmed_at IS NULL
                AND preferred_date IS NOT NULL
                AND CONCAT(preferred_date,' ',COALESCE(preferred_time,'09:00')) < NOW() - INTERVAL 1 DAY"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /** Enqueue a WhatsApp-first notification (clinic_id 0 = platform/marketing origin). */
    private static function enqueue(?int $identityId, string $phone, string $template, array $payload): void
    {
        // patient_id on notifications references the per-clinic patients table,
        // which doesn't apply to a global identity → store null, keep phone.
        \App\Core\QueryBuilder::table('notifications')->insert([
            'clinic_id' => 0,                  // platform/marketing origin (bypasses add-on gate)
            'patient_id' => null,
            'channel' => 'whatsapp',
            'template' => $template,
            'to_number' => $phone,
            'payload' => json_encode($payload),
            'status' => 'queued',
            'scheduled_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function fmtSlot(array $lead): string
    {
        $d = $lead['preferred_date'] ?? null;
        $t = $lead['preferred_time'] ?? null;
        if (!$d) {
            return 'your requested time';
        }
        $ts = strtotime($d . ' ' . ($t ?: '09:00'));
        return $ts ? date('d M Y, g:i A', $ts) : (string) $d;
    }
}
