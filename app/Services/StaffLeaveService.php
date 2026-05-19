<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class StaffLeaveService
{
    /** @return list<array<string, mixed>> */
    public static function list(int $clinicId, ?string $status = null): array
    {
        if (!\App\Core\Database::ping()) {
            return [];
        }

        $sql = 'SELECT sl.*, u.name AS staff_name FROM staff_leaves sl
                INNER JOIN users u ON u.id = sl.user_id
                WHERE sl.clinic_id = ?';
        $params = [$clinicId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND sl.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY sl.created_at DESC LIMIT 100';
        $stmt = \App\Core\Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public static function request(int $clinicId, int $userId, array $data): int
    {
        return QueryBuilder::table('staff_leaves')->insert([
            'clinic_id' => $clinicId,
            'user_id' => $userId,
            'leave_type' => $data['leave_type'] ?? 'CL',
            'from_date' => $data['from_date'] ?? date('Y-m-d'),
            'to_date' => $data['to_date'] ?? date('Y-m-d'),
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);
    }

    public static function approve(int $clinicId, int $leaveId): array
    {
        return self::setStatus($clinicId, $leaveId, 'approved');
    }

    public static function reject(int $clinicId, int $leaveId): array
    {
        return self::setStatus($clinicId, $leaveId, 'rejected');
    }

    private static function setStatus(int $clinicId, int $leaveId, string $status): array
    {
        $user = RequestContext::user();
        $leave = QueryBuilder::table('staff_leaves')->forClinic($clinicId)->where('id', '=', $leaveId)->first();
        if ($leave === null) {
            throw new \RuntimeException('Leave request not found');
        }

        QueryBuilder::table('staff_leaves')
            ->forClinic($clinicId)
            ->where('id', '=', $leaveId)
            ->update([
                'status' => $status,
                'approved_by' => $user['id'] ?? null,
            ]);

        $staff = QueryBuilder::table('users')->where('id', '=', (int) $leave['user_id'])->first();
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($staff !== null && !empty($staff['phone']) && $clinic !== null) {
            NotificationService::queueWhatsApp(
                $clinicId,
                null,
                (string) $staff['phone'],
                'follow_up_reminder',
                [
                    'patient_name' => 'Leave ' . $status,
                    'clinic_name' => $clinic['name'],
                ],
                date('Y-m-d H:i:s', time() + 60),
            );
        }

        return QueryBuilder::table('staff_leaves')->where('id', '=', $leaveId)->first() ?? [];
    }
}
