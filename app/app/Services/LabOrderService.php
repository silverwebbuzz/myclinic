<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class LabOrderService
{
    /** @return list<array<string, mixed>> */
    public static function forVisit(int $clinicId, int $visitId): array
    {
        return self::fetchOrdersForVisit($clinicId, $visitId);
    }

    /** @return list<array<string, mixed>> */
    private static function fetchOrdersForVisit(int $clinicId, int $visitId): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT lo.*, ltc.test_name, ltc.test_code, ltc.parameters
             FROM lab_orders lo
             INNER JOIN lab_tests_catalog ltc ON ltc.id = lo.test_id
             WHERE lo.clinic_id = ? AND lo.visit_id = ?
             ORDER BY lo.ordered_at DESC',
        );
        $stmt->execute([$clinicId, $visitId]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            if (is_string($row['parameters'] ?? null)) {
                $row['parameters'] = json_decode($row['parameters'], true);
            }
        }

        return $rows;
    }

    public static function create(int $clinicId, int $patientId, int $testId, ?int $visitId = null): array
    {
        $user = RequestContext::user();
        $barcode = 'LAB' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));

        $id = QueryBuilder::table('lab_orders')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'ordered_by' => $user['id'] ?? 1,
            'test_id' => $testId,
            'barcode' => $barcode,
            'status' => 'ordered',
        ]);

        return self::findDetailed($clinicId, $id) ?? [];
    }

    public static function collectSample(int $clinicId, int $orderId): array
    {
        $user = RequestContext::user();
        QueryBuilder::table('lab_orders')
            ->forClinic($clinicId)
            ->where('id', '=', $orderId)
            ->update([
                'status' => 'sample_collected',
                'sample_collected_at' => date('Y-m-d H:i:s'),
                'collected_by' => $user['id'] ?? null,
            ]);

        return self::findDetailed($clinicId, $orderId) ?? [];
    }

    /** @param list<array{parameter_name: string, value: string, unit?: string, normal_range?: string}> $results */
    public static function enterResults(int $clinicId, int $orderId, array $results): array
    {
        $order = self::findDetailed($clinicId, $orderId);
        if ($order === null) {
            throw new \RuntimeException('Order not found');
        }

        $user = RequestContext::user();
        QueryBuilder::table('lab_results')->where('lab_order_id', '=', $orderId)->delete();

        $hasCritical = false;
        $parameters = $order['parameters'] ?? [];

        foreach ($results as $r) {
            $flag = self::computeFlag($r, $parameters);
            if (str_starts_with($flag, 'critical')) {
                $hasCritical = true;
            }
            QueryBuilder::table('lab_results')->insert([
                'lab_order_id' => $orderId,
                'parameter_name' => $r['parameter_name'],
                'value' => $r['value'],
                'unit' => $r['unit'] ?? null,
                'normal_range' => $r['normal_range'] ?? null,
                'flag' => $flag,
                'entered_by' => $user['id'] ?? 1,
            ]);
        }

        QueryBuilder::table('lab_orders')
            ->forClinic($clinicId)
            ->where('id', '=', $orderId)
            ->update(['status' => 'resulted', 'resulted_at' => date('Y-m-d H:i:s')]);

        if ($hasCritical) {
            self::notifyDoctorCritical($clinicId, $order);
        }

        return self::findDetailed($clinicId, $orderId) ?? [];
    }

    public static function finalizeReport(int $clinicId, int $orderId): array
    {
        $order = self::findDetailed($clinicId, $orderId);
        if ($order === null) {
            throw new \RuntimeException('Order not found');
        }

        $patient = PatientService::find($clinicId, (int) $order['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient === null || $clinic === null) {
            throw new \RuntimeException('Patient or clinic not found');
        }

        $results = QueryBuilder::table('lab_results')->where('lab_order_id', '=', $orderId)->get();
        $order['results'] = $results;

        $pdfPath = LabReportPdfService::generate($order, $patient, $clinic);
        $shareToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        QueryBuilder::table('lab_orders')
            ->forClinic($clinicId)
            ->where('id', '=', $orderId)
            ->update([
                'status' => 'shared',
                'report_path' => $pdfPath,
                'share_token' => $shareToken,
                'share_expires_at' => $expires,
            ]);

        NotificationService::queueWhatsApp(
            $clinicId,
            (int) $patient['id'],
            (string) $patient['phone'],
            'lab_report_ready',
            [
                'patient_name' => $patient['name'],
                'clinic_name' => $clinic['name'],
                'test_name' => $order['test_name'] ?? '',
                'report_url' => '/lab/report/' . $shareToken,
            ],
            date('Y-m-d H:i:s', time() + 60),
        );

        return self::findDetailed($clinicId, $orderId) ?? [];
    }

    public static function findByShareToken(string $token): ?array
    {
        $row = QueryBuilder::table('lab_orders')->where('share_token', '=', $token)->first();
        if ($row === null) {
            return null;
        }
        if (!empty($row['share_expires_at']) && strtotime($row['share_expires_at']) < time()) {
            return null;
        }

        return self::findDetailed((int) $row['clinic_id'], (int) $row['id']);
    }

    /** @return array<string, mixed>|null */
    public static function findDetailed(int $clinicId, int $orderId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT lo.*, ltc.test_name, ltc.test_code, ltc.parameters, p.name AS patient_name, p.uhid
             FROM lab_orders lo
             INNER JOIN lab_tests_catalog ltc ON ltc.id = lo.test_id
             INNER JOIN patients p ON p.id = lo.patient_id
             WHERE lo.clinic_id = ? AND lo.id = ?',
        );
        $stmt->execute([$clinicId, $orderId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (is_string($row['parameters'] ?? null)) {
            $row['parameters'] = json_decode($row['parameters'], true);
        }
        $row['results'] = QueryBuilder::table('lab_results')->where('lab_order_id', '=', $orderId)->get();

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public static function pendingOrders(int $clinicId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT lo.*, ltc.test_name, p.name AS patient_name, p.uhid
             FROM lab_orders lo
             INNER JOIN lab_tests_catalog ltc ON ltc.id = lo.test_id
             INNER JOIN patients p ON p.id = lo.patient_id
             WHERE lo.clinic_id = ? AND lo.status IN ('ordered','sample_collected','processing')
             ORDER BY lo.ordered_at ASC
             LIMIT 50",
        );
        $stmt->execute([$clinicId]);

        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $result @param list<array<string, mixed>> $paramDefs */
    private static function computeFlag(array $result, array $paramDefs): string
    {
        $val = (float) $result['value'];
        foreach ($paramDefs as $p) {
            if (($p['name'] ?? '') !== ($result['parameter_name'] ?? '')) {
                continue;
            }
            $min = $p['min'] ?? null;
            $max = $p['max'] ?? null;
            $cl = $p['critical_low'] ?? null;
            $ch = $p['critical_high'] ?? null;
            if ($cl !== null && $val < (float) $cl) {
                return 'critical_low';
            }
            if ($ch !== null && $val > (float) $ch) {
                return 'critical_high';
            }
            if ($min !== null && $val < (float) $min) {
                return 'low';
            }
            if ($max !== null && $val > (float) $max) {
                return 'high';
            }

            return 'normal';
        }

        return 'normal';
    }

    /** @param array<string, mixed> $order */
    private static function notifyDoctorCritical(int $clinicId, array $order): void
    {
        $visit = !empty($order['visit_id']) ? VisitService::findDetailed($clinicId, (int) $order['visit_id']) : null;
        if ($visit === null || empty($visit['doctor_id'])) {
            return;
        }
        $doctor = QueryBuilder::table('users')->where('id', '=', (int) $visit['doctor_id'])->first();
        if ($doctor === null || empty($doctor['phone'])) {
            return;
        }
        NotificationService::queueWhatsApp(
            $clinicId,
            null,
            (string) $doctor['phone'],
            'lab_report_ready',
            [
                'patient_name' => $order['patient_name'] ?? '',
                'clinic_name' => 'CRITICAL LAB',
                'test_name' => $order['test_name'] ?? '',
            ],
            date('Y-m-d H:i:s'),
        );
    }
}
