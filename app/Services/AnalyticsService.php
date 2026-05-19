<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class AnalyticsService
{
    /** @return array{labels: list<string>, revenue: list<float>, expenses: list<float>} */
    public static function revenueExpenseSeries(int $clinicId, int $months = 12): array
    {
        $labels = [];
        $revenue = [];
        $expenses = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $labels[] = date('M Y', strtotime($ym . '-01'));
            $revenue[] = self::sumMetricMonth($clinicId, 'revenue', $ym);
            $expenses[] = self::sumMetricMonth($clinicId, 'expenses', $ym);
        }

        return compact('labels', 'revenue', 'expenses');
    }

    /** @return array{labels: list<string>, visits: list<float>, new_patients: list<float>} */
    public static function patientFlowSeries(int $clinicId, int $months = 12): array
    {
        $labels = [];
        $visits = [];
        $newPatients = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $labels[] = date('M Y', strtotime($ym . '-01'));
            $visits[] = self::sumMetricMonth($clinicId, 'visits', $ym);
            $newPatients[] = self::sumMetricMonth($clinicId, 'new_patients', $ym);
        }

        return ['labels' => $labels, 'visits' => $visits, 'new_patients' => $newPatients];
    }

    /** @return list<array{day: int, hour: int, count: int}> */
    public static function noShowHeatmap(int $clinicId, int $days = 90): array
    {
        if (!Database::ping()) {
            return [];
        }

        $since = date('Y-m-d', strtotime("-{$days} days"));
        $stmt = Database::connection()->prepare(
            "SELECT DAYOFWEEK(scheduled_at) AS dow, HOUR(scheduled_at) AS hr, COUNT(*) AS c
             FROM appointments
             WHERE clinic_id = ? AND status = 'no_show' AND DATE(scheduled_at) >= ?
             GROUP BY dow, hr",
        );
        $stmt->execute([$clinicId, $since]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $out[] = ['day' => (int) $row['dow'], 'hour' => (int) $row['hr'], 'count' => (int) $row['c']];
        }

        return $out;
    }

    /** @return array{revenue: float, expenses: float, profit: float} */
    public static function profitAndLoss(int $clinicId, string $from, string $to): array
    {
        $revenue = self::sumMetricRange($clinicId, 'revenue', $from, $to);
        $expenses = self::sumMetricRange($clinicId, 'expenses', $from, $to);
        if ($revenue === 0.0 && $expenses === 0.0) {
            $revenue = self::liveRevenue($clinicId, $from, $to);
            $expenses = ExpenseService::sumRange($clinicId, $from, $to);
        }

        return ['revenue' => $revenue, 'expenses' => $expenses, 'profit' => $revenue - $expenses];
    }

    /** @return list<array<string, mixed>> */
    public static function doctorPerformance(int $clinicId, string $from, string $to): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT u.id, u.name,
                    COUNT(DISTINCT v.id) AS visit_count,
                    COALESCE(SUM(i.total), 0) AS revenue
             FROM users u
             LEFT JOIN visits v ON v.doctor_id = u.id AND v.clinic_id = u.clinic_id
                AND DATE(v.visited_at) BETWEEN ? AND ?
             LEFT JOIN invoices i ON i.visit_id = v.id AND i.status IN (\'paid\',\'partial\')
             WHERE u.clinic_id = ? AND u.role = \'doctor\' AND u.is_active = 1
             GROUP BY u.id, u.name
             ORDER BY revenue DESC',
        );
        $stmt->execute([$from, $to, $clinicId]);

        return $stmt->fetchAll() ?: [];
    }

    private static function sumMetricMonth(int $clinicId, string $key, string $ym): float
    {
        if (!Database::ping()) {
            return 0.0;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(metric_value), 0) FROM analytics_snapshots
             WHERE clinic_id = ? AND metric_key = ? AND DATE_FORMAT(date, \'%Y-%m\') = ?',
        );
        $stmt->execute([$clinicId, $key, $ym]);

        return (float) $stmt->fetchColumn();
    }

    private static function sumMetricRange(int $clinicId, string $key, string $from, string $to): float
    {
        if (!Database::ping()) {
            return 0.0;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(metric_value), 0) FROM analytics_snapshots
             WHERE clinic_id = ? AND metric_key = ? AND date BETWEEN ? AND ?',
        );
        $stmt->execute([$clinicId, $key, $from, $to]);

        return (float) $stmt->fetchColumn();
    }

    private static function liveRevenue(int $clinicId, string $from, string $to): float
    {
        if (!Database::ping()) {
            return 0.0;
        }

        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM invoices
             WHERE clinic_id = ? AND status IN ('paid','partial')
             AND DATE(COALESCE(paid_at, created_at)) BETWEEN ? AND ?",
        );
        $stmt->execute([$clinicId, $from, $to]);

        return (float) $stmt->fetchColumn();
    }
}
