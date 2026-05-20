<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class WaitingListService
{
    public static function add(int $clinicId, int $patientId, int $doctorId, string $preferredDate): int
    {
        return QueryBuilder::table('waiting_list')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'preferred_date' => $preferredDate,
            'notified' => 0,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function forClinic(int $clinicId): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT w.*, p.name AS patient_name, p.phone, u.name AS doctor_name
             FROM waiting_list w
             INNER JOIN patients p ON p.id = w.patient_id
             INNER JOIN users u ON u.id = w.doctor_id
             WHERE w.clinic_id = ?
             ORDER BY w.preferred_date ASC, w.created_at ASC
             LIMIT 50',
        );
        $stmt->execute([$clinicId]);

        return $stmt->fetchAll() ?: [];
    }

    public static function notifyOnCancellation(int $clinicId, int $doctorId, string $date): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $stmt = Database::connection()->prepare(
            'SELECT w.*, p.name AS patient_name, p.phone, t.name AS clinic_name
             FROM waiting_list w
             INNER JOIN patients p ON p.id = w.patient_id
             INNER JOIN tenants t ON t.id = w.clinic_id
             WHERE w.clinic_id = ? AND w.doctor_id = ? AND w.preferred_date = ? AND w.notified = 0
             LIMIT 5',
        );
        $stmt->execute([$clinicId, $doctorId, $date]);
        $rows = $stmt->fetchAll() ?: [];
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['phone'])) {
                continue;
            }
            NotificationService::queueWhatsApp(
                $clinicId,
                (int) $row['patient_id'],
                (string) $row['phone'],
                'appointment_reminder',
                [
                    'patient_name' => $row['patient_name'] ?? '',
                    'clinic_name' => $row['clinic_name'] ?? '',
                    'scheduled_at' => $date . ' (slot available)',
                    'hours_before' => 0,
                ],
                date('Y-m-d H:i:s', time() + 120),
            );
            QueryBuilder::table('waiting_list')
                ->where('id', '=', (int) $row['id'])
                ->update(['notified' => 1]);
            $count++;
        }

        return $count;
    }
}
