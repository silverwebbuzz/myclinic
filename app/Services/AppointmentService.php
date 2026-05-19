<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class AppointmentService
{
    public static function find(int $clinicId, int $id): ?array
    {
        $row = QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->first();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findDetailed(int $clinicId, int $id): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.uhid,
                    u.name AS doctor_name
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             INNER JOIN users u ON u.id = a.doctor_id
             WHERE a.clinic_id = ? AND a.id = ?',
        );
        $stmt->execute([$clinicId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function create(int $clinicId, array $data): array
    {
        $doctorId = (int) $data['doctor_id'];
        $scheduledAt = (string) $data['scheduled_at'];
        $type = (string) ($data['type'] ?? 'prebooked');

        $slots = SlotService::available($clinicId, $doctorId, date('Y-m-d', strtotime($scheduledAt)));
        $slotOk = false;
        foreach ($slots as $slot) {
            if ($slot['datetime'] === $scheduledAt && $slot['available']) {
                $slotOk = true;
                break;
            }
        }
        if (!$slotOk) {
            throw new \RuntimeException('Selected slot is no longer available.');
        }

        $tokenNumber = null;
        if ($type === 'walkin' && date('Y-m-d', strtotime($scheduledAt)) === date('Y-m-d')) {
            $tokenNumber = self::nextTokenNumber($clinicId);
        }

        $user = RequestContext::user();
        $id = QueryBuilder::table('appointments')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => (int) $data['patient_id'],
            'doctor_id' => $doctorId,
            'scheduled_at' => $scheduledAt,
            'slot_duration' => (int) ($data['slot_duration'] ?? 15),
            'type' => in_array($type, ['walkin', 'prebooked', 'online', 'followup'], true) ? $type : 'prebooked',
            'source' => $data['source'] ?? 'reception',
            'status' => $data['status'] ?? 'scheduled',
            'chief_complaint' => trim((string) ($data['chief_complaint'] ?? '')) ?: null,
            'token_number' => $tokenNumber,
            'is_followup' => !empty($data['is_followup']) ? 1 : 0,
            'parent_visit_id' => !empty($data['parent_visit_id']) ? (int) $data['parent_visit_id'] : null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'created_by' => $user['id'] ?? null,
        ]);

        SlotService::invalidateForAppointment($clinicId, $doctorId, $scheduledAt);
        DashboardService::invalidateStats($clinicId);

        $appointment = self::findDetailed($clinicId, $id);
        if ($appointment !== null) {
            $patient = PatientService::find($clinicId, (int) $appointment['patient_id']);
            $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
            if ($patient !== null && $clinic !== null) {
                NotificationService::queueAppointmentReminder($appointment, $patient, $clinic, 24);
                NotificationService::queueAppointmentReminder($appointment, $patient, $clinic, 1);
            }
            EventBus::fire('appointment.booked', [
                'appointment_id' => $id,
                'patient_id' => (int) $data['patient_id'],
            ], 'appointments', $id);

            TelemedicineService::applyToAppointment($clinicId, $appointment);
        }

        return $appointment ?? [];
    }

    /** @param array<string, mixed> $data */
    public static function update(int $clinicId, int $id, array $data): array
    {
        $existing = self::find($clinicId, $id);
        if ($existing === null) {
            throw new \RuntimeException('Appointment not found');
        }

        $update = [];
        foreach (['scheduled_at', 'doctor_id', 'chief_complaint', 'notes', 'type', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if ($update !== []) {
            QueryBuilder::table('appointments')
                ->forClinic($clinicId)
                ->where('id', '=', $id)
                ->update($update);

            if (($update['status'] ?? '') === 'confirmed') {
                TelemedicineService::onConfirmed($clinicId, $id);
            }

            SlotService::invalidateForAppointment($clinicId, (int) $existing['doctor_id'], $existing['scheduled_at']);
            if (isset($update['doctor_id']) || isset($update['scheduled_at'])) {
                $doc = (int) ($update['doctor_id'] ?? $existing['doctor_id']);
                $at = (string) ($update['scheduled_at'] ?? $existing['scheduled_at']);
                SlotService::invalidateForAppointment($clinicId, $doc, $at);
            }
        }

        return self::findDetailed($clinicId, $id) ?? [];
    }

    public static function cancel(int $clinicId, int $id): array
    {
        $existing = self::findDetailed($clinicId, $id);
        if ($existing === null) {
            throw new \RuntimeException('Appointment not found');
        }

        QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->update(['status' => 'cancelled']);

        SlotService::invalidateForAppointment($clinicId, (int) $existing['doctor_id'], $existing['scheduled_at']);
        DashboardService::invalidateStats($clinicId);

        $patient = PatientService::find($clinicId, (int) $existing['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient !== null && $clinic !== null) {
            NotificationService::queueCancellationNotice($existing, $patient, $clinic);
        }

        if (ModuleGate::check('advanced_scheduling')) {
            WaitingListService::notifyOnCancellation(
                $clinicId,
                (int) $existing['doctor_id'],
                date('Y-m-d', strtotime($existing['scheduled_at'])),
            );
        }

        EventBus::fire('appointment.cancelled', ['appointment_id' => $id], 'appointments', $id);

        return self::findDetailed($clinicId, $id) ?? [];
    }

    public static function updateStatus(int $clinicId, int $id, string $status): array
    {
        $allowed = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->update(['status' => $status]);

        DashboardService::invalidateStats($clinicId);

        return self::findDetailed($clinicId, $id) ?? [];
    }

    /** @return list<array<string, mixed>> */
    public static function todayQueue(int $clinicId, ?int $doctorId = null): array
    {
        if (!Database::ping()) {
            return [];
        }

        $today = date('Y-m-d');
        $sql = 'SELECT a.*, p.name AS patient_name, p.uhid, p.phone AS patient_phone,
                       u.name AS doctor_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN users u ON u.id = a.doctor_id
                WHERE a.clinic_id = ? AND DATE(a.scheduled_at) = ?
                AND a.status NOT IN (\'cancelled\')';
        $params = [$clinicId, $today];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        $sql .= ' ORDER BY a.token_number IS NULL, a.token_number ASC, a.scheduled_at ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function calendarEvents(int $clinicId, string $start, string $end, ?int $doctorId = null): array
    {
        if (!Database::ping()) {
            return [];
        }

        $sql = 'SELECT a.id, a.scheduled_at, a.status, a.type, p.name AS patient_name, u.name AS doctor_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN users u ON u.id = a.doctor_id
                WHERE a.clinic_id = ? AND a.scheduled_at >= ? AND a.scheduled_at < ?
                AND a.status != \'cancelled\'';
        $params = [$clinicId, $start, $end];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $colors = [
            'scheduled' => '#94a3b8',
            'confirmed' => '#3b82f6',
            'in_progress' => '#f59e0b',
            'completed' => '#10b981',
            'no_show' => '#ef4444',
        ];

        return array_map(static function (array $row) use ($colors) {
            $end = date('Y-m-d\TH:i:s', strtotime($row['scheduled_at'] . ' +15 minutes'));

            return [
                'id' => $row['id'],
                'title' => $row['patient_name'] . ' — ' . $row['doctor_name'],
                'start' => date('Y-m-d\TH:i:s', strtotime($row['scheduled_at'])),
                'end' => $end,
                'backgroundColor' => $colors[$row['status']] ?? '#64748b',
                'url' => '/appointments/' . $row['id'] . '/edit',
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    public static function doctorsForClinic(int $clinicId): array
    {
        $doctors = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '=', 'doctor')
            ->where('is_active', '=', 1)
            ->get();

        if ($doctors !== []) {
            return $doctors;
        }

        $owner = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_owner', '=', 1)
            ->where('is_active', '=', 1)
            ->first();

        return $owner !== null ? [$owner] : [];
    }

    private static function nextTokenNumber(int $clinicId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(MAX(token_number), 0) + 1 AS n FROM appointments
             WHERE clinic_id = ? AND DATE(scheduled_at) = CURDATE() AND token_number IS NOT NULL",
        );
        $stmt->execute([$clinicId]);

        return (int) ($stmt->fetch()['n'] ?? 1);
    }
}
