<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Gates\ModuleGate;

final class TelemedicineService
{
    public static function createMeetLink(): string
    {
        if (!empty($_ENV['GOOGLE_MEET_API_ENABLED'])) {
            // TODO: Google Meet API — create conference via Calendar API
            return 'https://meet.google.com/mc-' . bin2hex(random_bytes(4));
        }

        return 'https://meet.google.com/lookup/' . substr(bin2hex(random_bytes(8)), 0, 12);
    }

    /** @param array<string, mixed> $appointment */
    public static function applyToAppointment(int $clinicId, array $appointment): void
    {
        if (!ModuleGate::check('telemedicine')) {
            return;
        }
        if (($appointment['type'] ?? '') !== 'online') {
            return;
        }

        $meetLink = self::createMeetLink();
        QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', (int) $appointment['id'])
            ->update(['meet_link' => $meetLink]);

        $patient = PatientService::find($clinicId, (int) $appointment['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient === null || $clinic === null) {
            return;
        }

        if (!empty($patient['phone'])) {
            NotificationService::queueWhatsApp(
                $clinicId,
                (int) $patient['id'],
                (string) $patient['phone'],
                'appointment_reminder',
                [
                    'patient_name' => $patient['name'],
                    'clinic_name' => $clinic['name'],
                    'scheduled_at' => $appointment['scheduled_at'] . ' — Meet: ' . $meetLink,
                    'hours_before' => 0,
                ],
                date('Y-m-d H:i:s', time() + 60),
            );
        }

        if (!empty($patient['email'])) {
            MailService::send(
                (string) $patient['email'],
                'telemedicine_link',
                [
                    'patient_name' => $patient['name'],
                    'clinic_name' => $clinic['name'],
                    'meet_link' => $meetLink,
                    'scheduled_at' => $appointment['scheduled_at'],
                ],
                $clinicId,
            );
        }
    }

    public static function onConfirmed(int $clinicId, int $appointmentId): void
    {
        $appointment = AppointmentService::findDetailed($clinicId, $appointmentId);
        if ($appointment === null || !empty($appointment['meet_link'])) {
            return;
        }

        self::applyToAppointment($clinicId, $appointment);
    }
}
