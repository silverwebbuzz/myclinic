<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class DietService
{
    /** @return list<string> */
    public static function defaultWeekPlan(): array
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $plan = [];
        foreach ($days as $day) {
            $plan[$day] = [
                'breakfast' => 'Light breakfast per condition',
                'lunch' => 'Balanced meal',
                'dinner' => 'Early light dinner',
                'snacks' => 'Fruits / nuts as allowed',
            ];
        }

        return $plan;
    }

    public static function forVisit(int $clinicId, int $visitId): ?array
    {
        $row = QueryBuilder::table('diet_plans')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->orderBy('id', 'DESC')
            ->first();

        if ($row === null) {
            return null;
        }
        if (is_string($row['plan_json'] ?? null)) {
            $row['plan_json'] = json_decode($row['plan_json'], true);
        }

        return $row;
    }

    public static function find(int $clinicId, int $id): ?array
    {
        $row = QueryBuilder::table('diet_plans')->forClinic($clinicId)->where('id', '=', $id)->first();
        if ($row === null) {
            return null;
        }
        if (is_string($row['plan_json'] ?? null)) {
            $row['plan_json'] = json_decode($row['plan_json'], true);
        }

        return $row;
    }

    /** @param array<string, mixed> $data */
    public static function save(int $clinicId, int $visitId, int $patientId, array $data): array
    {
        $user = RequestContext::user();
        if (!empty($data['meals']) && is_array($data['meals'])) {
            $planJson = $data['meals'];
        } else {
            $planJson = $data['plan_json'] ?? self::defaultWeekPlan();
            if (is_string($planJson)) {
                $planJson = json_decode($planJson, true) ?: self::defaultWeekPlan();
            }
        }

        $antidotes = trim((string) ($data['antidotes_shown'] ?? ''));
        if ($antidotes === '' && !empty($data['include_homeo_warnings'])) {
            $antidotes = self::homeoAntidoteText($clinicId, $visitId);
        }

        $existing = self::forVisit($clinicId, $visitId);
        $payload = [
            'condition' => $data['condition'] ?? null,
            'veg_type' => $data['veg_type'] ?? 'veg',
            'plan_json' => json_encode($planJson),
            'antidotes_shown' => $antidotes ?: null,
            'prescribed_by' => $user['id'] ?? 1,
        ];

        if ($existing !== null) {
            QueryBuilder::table('diet_plans')
                ->where('id', '=', (int) $existing['id'])
                ->update($payload);

            return self::find($clinicId, (int) $existing['id']) ?? [];
        }

        $id = QueryBuilder::table('diet_plans')->insert(array_merge($payload, [
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'status' => 'draft',
        ]));

        return self::find($clinicId, $id) ?? [];
    }

    public static function share(int $clinicId, int $planId): array
    {
        $plan = self::find($clinicId, $planId);
        if ($plan === null) {
            throw new \RuntimeException('Diet plan not found');
        }

        $patient = PatientService::find($clinicId, (int) $plan['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient === null || $clinic === null) {
            throw new \RuntimeException('Patient or clinic not found');
        }

        if (is_string($plan['plan_json'] ?? null)) {
            $plan['plan_json'] = json_decode($plan['plan_json'], true);
        }

        $pdfPath = DietPdfService::generate($plan, $patient, $clinic);

        QueryBuilder::table('diet_plans')
            ->where('id', '=', $planId)
            ->update([
                'status' => 'shared',
                'shared_at' => date('Y-m-d H:i:s'),
                'pdf_path' => $pdfPath,
            ]);

        NotificationService::queueWhatsApp(
            $clinicId,
            (int) $patient['id'],
            (string) $patient['phone'],
            'rx_delivery',
            [
                'patient_name' => $patient['name'],
                'clinic_name' => $clinic['name'],
                'rx_url' => $pdfPath,
            ],
            date('Y-m-d H:i:s', time() + 120),
        );

        return self::find($clinicId, $planId) ?? [];
    }

    private static function homeoAntidoteText(int $clinicId, int $visitId): string
    {
        $rx = PrescriptionService::forVisit($clinicId, $visitId);
        $lines = [];
        foreach ($rx as $line) {
            if (!empty($line['remedy_id'])) {
                $remedy = RemedyService::find((int) $line['remedy_id']);
                if ($remedy !== null) {
                    foreach (RemedyService::dietaryWarnings($remedy) as $w) {
                        $lines[] = $w;
                    }
                }
            }
        }

        return implode("\n", array_unique($lines));
    }
}
