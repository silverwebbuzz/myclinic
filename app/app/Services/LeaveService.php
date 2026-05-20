<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class LeaveService
{
    /** @return list<array<string, mixed>> */
    public static function forDoctor(int $clinicId, int $doctorId, ?string $month = null): array
    {
        $query = QueryBuilder::table('doctor_leaves')
            ->forClinic($clinicId)
            ->where('doctor_id', '=', $doctorId);

        $rows = $query->orderBy('leave_date', 'ASC')->get();

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = $month . '-01';
            $end = date('Y-m-d', strtotime($month . '-01 +1 month'));
            $rows = array_filter($rows, static fn ($r) => ($r['leave_date'] ?? '') >= $start && ($r['leave_date'] ?? '') < $end);
        }

        return array_values($rows);
    }

    /** @return list<array<string, mixed>> */
    public static function conflictingAppointments(int $clinicId, int $doctorId, string $date, string $session): array
    {
        if (!Database::ping()) {
            return [];
        }

        $pdo = Database::connection();
        $sql = "SELECT a.*, p.name AS patient_name FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                WHERE a.clinic_id = ? AND a.doctor_id = ? AND DATE(a.scheduled_at) = ?
                AND a.status NOT IN ('cancelled', 'no_show')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clinicId, $doctorId, $date]);
        $rows = $stmt->fetchAll() ?: [];

        if ($session === 'full') {
            return $rows;
        }

        return array_filter($rows, static function (array $row) use ($session) {
            $time = date('H:i', strtotime($row['scheduled_at']));

            return $session === 'morning' ? $time < '13:00' : $time >= '13:00';
        });
    }

    public static function add(int $clinicId, int $doctorId, string $date, string $session, ?string $reason): int
    {
        $existing = QueryBuilder::table('doctor_leaves')
            ->forClinic($clinicId)
            ->where('doctor_id', '=', $doctorId)
            ->where('leave_date', '=', $date)
            ->where('session', '=', $session)
            ->first();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $id = QueryBuilder::table('doctor_leaves')->insert([
            'clinic_id' => $clinicId,
            'doctor_id' => $doctorId,
            'leave_date' => $date,
            'session' => in_array($session, ['full', 'morning', 'evening'], true) ? $session : 'full',
            'reason' => $reason,
        ]);
        SlotService::invalidate($clinicId, $doctorId, $date);

        return $id;
    }

    public static function remove(int $clinicId, int $leaveId): void
    {
        $leave = QueryBuilder::table('doctor_leaves')
            ->forClinic($clinicId)
            ->where('id', '=', $leaveId)
            ->first();

        QueryBuilder::table('doctor_leaves')
            ->forClinic($clinicId)
            ->where('id', '=', $leaveId)
            ->delete();

        if ($leave !== null) {
            SlotService::invalidate($clinicId, (int) $leave['doctor_id'], (string) $leave['leave_date']);
        }
    }
}
