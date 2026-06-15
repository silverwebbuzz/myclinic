<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class NotificationService
{
    /** @param array<string, mixed> $payload */
    public static function queueWhatsApp(
        int $clinicId,
        ?int $patientId,
        string $phone,
        string $template,
        array $payload,
        string $scheduledAt,
    ): void {
        if (!Database::ping()) {
            return;
        }

        QueryBuilder::table('notifications')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'channel' => 'whatsapp',
            'template' => $template,
            'to_number' => $phone,
            'payload' => json_encode($payload),
            'status' => 'queued',
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /**
     * Queue an email notification — ONLY when a real address is supplied.
     * Email is optional throughout the app, so a null/blank/invalid address is
     * a silent no-op (no error, no queued row, no failed-send noise).
     *
     * @param array<string, mixed> $payload
     */
    public static function queueEmail(
        int $clinicId,
        ?int $patientId,
        ?string $email,
        string $template,
        array $payload,
        string $scheduledAt,
    ): void {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (!Database::ping()) {
            return;
        }

        QueryBuilder::table('notifications')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'channel' => 'email',
            'template' => $template,
            'to_email' => $email,
            'payload' => json_encode($payload),
            'status' => 'queued',
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /** @param array<string, mixed> $appointment @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    public static function queueAppointmentReminder(
        array $appointment,
        array $patient,
        array $clinic,
        int $hoursBefore = 24,
    ): void {
        $prefs = self::notificationPrefs((int) $clinic['id']);
        $key = $hoursBefore === 1 ? 'appointment_reminder_1h' : 'appointment_reminder_24h';
        if (empty($prefs[$key])) {
            return;
        }

        $scheduled = strtotime($appointment['scheduled_at']);
        $remindAt = $scheduled - ($hoursBefore * 3600);
        if ($remindAt <= time()) {
            return;
        }

        $reminderPayload = [
            'patient_name' => $patient['name'],
            'clinic_name' => $clinic['name'],
            'scheduled_at' => $appointment['scheduled_at'],
            'hours_before' => $hoursBefore,
        ];
        $remindAtStr = date('Y-m-d H:i:s', $remindAt);

        self::queueWhatsApp(
            (int) $clinic['id'],
            (int) $patient['id'],
            (string) $patient['phone'],
            'appointment_reminder',
            $reminderPayload,
            $remindAtStr,
        );

        // Also email the patient when we have an address on file (optional).
        self::queueEmail(
            (int) $clinic['id'],
            (int) $patient['id'],
            $patient['email'] ?? null,
            'appointment_reminder',
            $reminderPayload,
            $remindAtStr,
        );
    }

    /**
     * Online (self-service) booking acknowledgement. Sends two immediate
     * messages, both routed through the same WhatsApp→SMS pipeline, using the
     * existing admin-managed templates in `wa_templates` (seeded by
     * document/seeds/whatsapp_migrations.sql) and governed by `messaging_rules`:
     *
     *   1. To the doctor  — template `doctor_new_lead` (audience=doctor;
     *      WhatsApp even on trial — the conversion hook).
     *      vars: ["patient_name","datetime","reason","link"]
     *   2. To the patient — template `patient_request_sent`
     *      ("request sent, they'll confirm shortly, call directly: {clinic_phone}").
     *      vars: ["patient_name","doctor_name","datetime","clinic_phone"]
     *
     * This is a "request received" acknowledgement, not a confirmed slot —
     * the doctor follows up by phone. clinic_phone falls back to the clinic
     * owner's number (resolved by the caller). No-op per recipient if no phone.
     *
     * @param array<string, mixed> $appointment
     * @param array<string, mixed> $patient
     * @param array<string, mixed> $doctor
     * @param array<string, mixed> $clinic
     */
    public static function queueOnlineBooking(
        array $appointment,
        array $patient,
        array $doctor,
        array $clinic,
    ): void {
        $now = date('Y-m-d H:i:s');
        $doctorPhone = (string) ($doctor['phone'] ?? '');
        $patientPhone = (string) ($patient['phone'] ?? '');
        $when = (string) ($appointment['scheduled_at'] ?? '');
        // Number patients are told to call: the doctor's, else the clinic's.
        $clinicPhone = $doctorPhone !== '' ? $doctorPhone : (string) ($clinic['phone'] ?? '');
        $reason = (string) ($appointment['chief_complaint'] ?? 'Appointment request');

        // 1) Doctor alert — template_key `doctor_new_lead`, audience auto-routes
        //    to 'doctor' (key starts with `doctor_`). Payload keys match the
        //    template's `variables` contract: patient_name, datetime, reason, link.
        if ($doctorPhone !== '') {
            self::queueWhatsApp(
                (int) $clinic['id'],
                (int) $patient['id'],
                $doctorPhone,
                'doctor_new_lead',
                [
                    'patient_name' => $patient['name'] ?? 'Patient',
                    'datetime' => $when,
                    'reason' => $reason,
                    'link' => '',
                ],
                $now,
            );
        }

        // 2) Patient ack — template_key `patient_request_sent`.
        //    Contract vars: patient_name, doctor_name, datetime, clinic_phone.
        if ($patientPhone !== '') {
            self::queueWhatsApp(
                (int) $clinic['id'],
                (int) $patient['id'],
                $patientPhone,
                'patient_request_sent',
                [
                    'patient_name' => $patient['name'] ?? 'Patient',
                    'doctor_name' => $doctor['name'] ?? 'the doctor',
                    'datetime' => $when,
                    'clinic_phone' => $clinicPhone,
                ],
                $now,
            );
        }
    }

    /** @param array<string, mixed> $appointment @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    public static function queueCancellationNotice(array $appointment, array $patient, array $clinic): void
    {
        $payload = [
            'patient_name' => $patient['name'],
            'clinic_name' => $clinic['name'],
            'scheduled_at' => $appointment['scheduled_at'],
        ];
        $when = date('Y-m-d H:i:s', time() + 60);

        self::queueWhatsApp(
            (int) $clinic['id'],
            (int) $patient['id'],
            (string) $patient['phone'],
            'appointment_cancelled',
            $payload,
            $when,
        );

        self::queueEmail(
            (int) $clinic['id'],
            (int) $patient['id'],
            $patient['email'] ?? null,
            'appointment_cancelled',
            $payload,
            $when,
        );
    }

    /** @return array<string, mixed> */
    private static function notificationPrefs(int $clinicId): array
    {
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $prefs = $config['notification_prefs'] ?? null;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true);
        }

        return is_array($prefs) ? $prefs : [];
    }
}
