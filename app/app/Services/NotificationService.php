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

        self::queueWhatsApp(
            (int) $clinic['id'],
            (int) $patient['id'],
            (string) $patient['phone'],
            'appointment_reminder',
            [
                'patient_name' => $patient['name'],
                'clinic_name' => $clinic['name'],
                'scheduled_at' => $appointment['scheduled_at'],
                'hours_before' => $hoursBefore,
            ],
            date('Y-m-d H:i:s', $remindAt),
        );
    }

    /** @param array<string, mixed> $appointment @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    public static function queueCancellationNotice(array $appointment, array $patient, array $clinic): void
    {
        self::queueWhatsApp(
            (int) $clinic['id'],
            (int) $patient['id'],
            (string) $patient['phone'],
            'appointment_cancelled',
            [
                'patient_name' => $patient['name'],
                'clinic_name' => $clinic['name'],
                'scheduled_at' => $appointment['scheduled_at'],
            ],
            date('Y-m-d H:i:s', time() + 60),
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
