<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class DoctorIncentiveService
{
    public static function saveDoctorConfig(int $clinicId, int $doctorId, float $percent, float $flatFee): void
    {
        QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('id', '=', $doctorId)
            ->where('role', '=', 'doctor')
            ->update([
                'incentive_percent' => $percent,
                'incentive_flat_fee' => $flatFee,
            ]);
    }

    /** @return list<array<string, mixed>> */
    public static function doctorsWithConfig(int $clinicId): array
    {
        return QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '=', 'doctor')
            ->where('is_active', '=', 1)
            ->get();
    }

    public static function calculateMonth(int $clinicId, string $periodMonth): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $from = $periodMonth . '-01';
        $to = date('Y-m-t', strtotime($from));
        $doctors = self::doctorsWithConfig($clinicId);
        $count = 0;

        foreach ($doctors as $doc) {
            $doctorId = (int) $doc['id'];
            $stmt = Database::connection()->prepare(
                'SELECT COALESCE(SUM(i.total), 0) AS rev
                 FROM invoices i
                 INNER JOIN visits v ON v.id = i.visit_id
                 WHERE v.clinic_id = ? AND v.doctor_id = ?
                 AND i.status IN (\'paid\',\'partial\')
                 AND DATE(COALESCE(i.paid_at, i.created_at)) BETWEEN ? AND ?',
            );
            $stmt->execute([$clinicId, $doctorId, $from, $to]);
            $revenue = (float) $stmt->fetchColumn();
            $percent = (float) ($doc['incentive_percent'] ?? 0);
            $flat = (float) ($doc['incentive_flat_fee'] ?? 0);
            $gross = ($revenue * $percent / 100) + $flat;
            $tds = round($gross * 0.1, 2);
            $net = $gross - $tds;

            $existing = QueryBuilder::table('doctor_incentives')
                ->forClinic($clinicId)
                ->where('doctor_id', '=', $doctorId)
                ->where('period_month', '=', $periodMonth)
                ->first();

            $payload = [
                'revenue_generated' => $revenue,
                'incentive_percent' => $percent,
                'flat_fee' => $flat,
                'tds_amount' => $tds,
                'net_payable' => $net,
            ];

            if ($existing !== null) {
                QueryBuilder::table('doctor_incentives')
                    ->where('id', '=', (int) $existing['id'])
                    ->update($payload);
            } else {
                QueryBuilder::table('doctor_incentives')->insert(array_merge($payload, [
                    'clinic_id' => $clinicId,
                    'doctor_id' => $doctorId,
                    'period_month' => $periodMonth,
                ]));
            }
            $count++;
        }

        return $count;
    }

    /** @return list<array<string, mixed>> */
    public static function listForPeriod(int $clinicId, string $periodMonth): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT di.*, u.name AS doctor_name
             FROM doctor_incentives di
             INNER JOIN users u ON u.id = di.doctor_id
             WHERE di.clinic_id = ? AND di.period_month = ?
             ORDER BY u.name',
        );
        $stmt->execute([$clinicId, $periodMonth]);

        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $clinicId, int $id): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT di.*, u.name AS doctor_name, u.email AS doctor_email
             FROM doctor_incentives di
             INNER JOIN users u ON u.id = di.doctor_id
             WHERE di.clinic_id = ? AND di.id = ?',
        );
        $stmt->execute([$clinicId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function calculateAllClinics(?string $periodMonth = null): int
    {
        $periodMonth = $periodMonth ?? date('Y-m', strtotime('first day of last month'));
        $rows = QueryBuilder::table('clinic_modules')
            ->where('module_id', '=', 'incentives')
            ->where('is_active', '=', 1)
            ->get();
        $total = 0;
        foreach ($rows as $row) {
            $total += self::calculateMonth((int) $row['clinic_id'], $periodMonth);
        }

        return $total;
    }
}
