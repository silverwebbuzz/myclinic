<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Support\SpecialtyAdapter;

final class VitalsService
{
    /** @param array<string, mixed> $data */
    public static function saveForVisit(int $clinicId, int $visitId, int $patientId, array $data): array
    {
        $existing = QueryBuilder::table('vitals')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->first();

        $row = self::normalize($data);
        $row['clinic_id'] = $clinicId;
        $row['visit_id'] = $visitId;
        $row['patient_id'] = $patientId;
        $row['recorded_by'] = RequestContext::user()['id'] ?? null;

        if ($existing !== null) {
            unset($row['clinic_id'], $row['visit_id'], $row['patient_id']);
            QueryBuilder::table('vitals')
                ->forClinic($clinicId)
                ->where('id', '=', (int) $existing['id'])
                ->update($row);

            return QueryBuilder::table('vitals')->where('id', '=', (int) $existing['id'])->first() ?? [];
        }

        $id = QueryBuilder::table('vitals')->insert($row);

        return QueryBuilder::table('vitals')->where('id', '=', $id)->first() ?? [];
    }

    public static function forVisit(int $clinicId, int $visitId): ?array
    {
        $row = QueryBuilder::table('vitals')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->first();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function history(int $clinicId, int $patientId, int $limit = 12): array
    {
        return QueryBuilder::table('vitals')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('recorded_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /** @param array<string, mixed> $vitals @return list<array{field: string, message: string, level: string}> */
    public static function rangeWarnings(array $vitals): array
    {
        $ranges = SpecialtyAdapter::normalRanges();
        $warnings = [];
        foreach ($ranges as $field => $range) {
            if (!isset($vitals[$field]) || $vitals[$field] === '' || $vitals[$field] === null) {
                continue;
            }
            $val = (float) $vitals[$field];
            $label = $range['label'] ?? $field;
            if (isset($range['min']) && $val < $range['min']) {
                $warnings[] = ['field' => $field, 'message' => "{$label} below normal ({$val})", 'level' => 'warn'];
            }
            if (isset($range['max']) && $val > $range['max']) {
                $warnings[] = ['field' => $field, 'message' => "{$label} above normal ({$val})", 'level' => 'warn'];
            }
        }

        return $warnings;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function normalize(array $data): array
    {
        $extra = [];
        $row = [];
        $columns = [
            'bp_systolic', 'bp_diastolic', 'blood_sugar', 'sugar_type', 'weight_kg', 'height_cm',
            'temperature', 'spo2', 'pulse_rate', 'tsh', 't3', 't4', 'skin_score',
        ];

        foreach ($columns as $col) {
            if (array_key_exists($col, $data) && $data[$col] !== '' && $data[$col] !== null) {
                $row[$col] = $data[$col];
            }
        }

        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, 'extra.') && $value !== '' && $value !== null) {
                $extra[substr((string) $key, 6)] = $value;
            }
        }
        if (isset($data['extra']) && is_array($data['extra'])) {
            $extra = array_merge($extra, $data['extra']);
        }
        if (isset($data['extra_vitals']) && is_array($data['extra_vitals'])) {
            $extra = array_merge($extra, $data['extra_vitals']);
        }
        if ($extra !== []) {
            $row['extra_vitals'] = json_encode($extra);
        }

        return $row;
    }

    /** @return array<string, list<float|string>> */
    public static function chartSeries(int $clinicId, int $patientId): array
    {
        $rows = array_reverse(self::history($clinicId, $patientId, 12));
        $series = [
            'labels' => [],
            'bp_systolic' => [],
            'weight_kg' => [],
            'blood_sugar' => [],
        ];
        foreach ($rows as $r) {
            $series['labels'][] = date('d M', strtotime($r['recorded_at']));
            $series['bp_systolic'][] = $r['bp_systolic'] ?? null;
            $series['weight_kg'][] = $r['weight_kg'] ?? null;
            $series['blood_sugar'][] = $r['blood_sugar'] ?? null;
        }

        return $series;
    }
}
