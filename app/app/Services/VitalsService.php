<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Support\SpecialtyAdapter;

final class VitalsService
{
    public const PER_PAGE = 30;

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function listForClinic(int $clinicId, array $filters = [], int $page = 1): array
    {
        if (!Database::ping()) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => self::PER_PAGE];
        }
        $page = max(1, $page);
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;
        $params = ['clinic_id' => $clinicId];
        $where = ['v.clinic_id = :clinic_id'];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.uhid LIKE :q2)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if (!empty($filters['patient_id'])) {
            $where[] = 'v.patient_id = :pid';
            $params['pid'] = (int) $filters['patient_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'v.recorded_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'v.recorded_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['abnormal'])) {
            $where[] = '(v.bp_systolic >= 140 OR v.bp_diastolic >= 90 OR v.spo2 < 94 OR v.temperature >= 38)';
        }

        $where[] = self::hasValuesSql('v');

        $whereSql = implode(' AND ', $where);
        $pdo = Database::connection();

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM vitals v
             JOIN patients p ON p.id = v.patient_id
             WHERE {$whereSql}",
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT v.*, p.name AS patient_name, p.uhid
             FROM vitals v
             JOIN patients p ON p.id = v.patient_id
             WHERE {$whereSql}
             ORDER BY v.recorded_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
        );
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function saveForVisit(int $clinicId, int $visitId, int $patientId, array $data): array
    {
        $existing = QueryBuilder::table('vitals')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->first();

        $row = self::normalize($data);

        if (!self::hasRecordedValues($row)) {
            if ($existing !== null) {
                QueryBuilder::table('vitals')
                    ->forClinic($clinicId)
                    ->where('id', '=', (int) $existing['id'])
                    ->delete();
            }

            return [];
        }

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

        if ($row !== null) {
            return self::hasRecordedValues($row) ? self::hydrateForEdit($row) : null;
        }

        // Legacy compatibility: old rows may have null/0 clinic_id.
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT * FROM vitals
             WHERE visit_id = :visit_id
               AND (clinic_id IS NULL OR clinic_id = 0 OR clinic_id = :clinic_id)
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':visit_id' => $visitId,
            ':clinic_id' => $clinicId,
        ]);
        $legacy = $stmt->fetch() ?: null;
        if ($legacy !== null && empty($legacy['clinic_id'])) {
            QueryBuilder::table('vitals')
                ->where('id', '=', (int) $legacy['id'])
                ->update(['clinic_id' => $clinicId]);
            $legacy['clinic_id'] = $clinicId;
        }

        if ($legacy !== null && !self::hasRecordedValues($legacy)) {
            return null;
        }

        return $legacy !== null ? self::hydrateForEdit($legacy) : null;
    }

    /**
     * Decode extra_vitals JSON and normalize keys for edit form binding (e.g. hba1c).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function hydrateForEdit(array $row): array
    {
        $extra = [];
        $raw = $row['extra_vitals'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        } elseif (is_array($raw)) {
            $extra = $raw;
        }
        if (isset($row['extra']) && is_array($row['extra'])) {
            $extra = array_merge($extra, $row['extra']);
        }

        $normalized = [];
        foreach ($extra as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $k = strtolower((string) preg_replace('/^extra\./', '', (string) $key));
            if (in_array($k, ['hba1c', 'hba1c_percent', 'hba1c_%', 'hba1cpercent'], true)) {
                $normalized['hba1c'] = $value;
                continue;
            }
            $normKey = (string) preg_replace('/^extra\./', '', (string) $key);
            $normalized[$normKey] = $value;
        }

        if ($normalized !== []) {
            $row['extra'] = $normalized;
            $row['extra_vitals'] = $normalized;
        } else {
            unset($row['extra_vitals'], $row['extra']);
        }

        return $row;
    }

    /** @return list<array<string, mixed>> */
    public static function history(int $clinicId, int $patientId, int $limit = 12): array
    {
        $rows = QueryBuilder::table('vitals')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('recorded_at', 'DESC')
            ->limit($limit * 3)
            ->get();

        $filtered = [];
        foreach ($rows as $row) {
            if (!self::hasRecordedValues($row)) {
                continue;
            }
            $filtered[] = $row;
            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
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

    /** True when at least one measured vital value is present. */
    public static function hasRecordedValues(array $data): bool
    {
        foreach (self::valueColumns() as $col) {
            if (isset($data[$col]) && $data[$col] !== '' && $data[$col] !== null) {
                return true;
            }
        }

        $extra = $data['extra_vitals'] ?? $data['extra'] ?? null;
        if (is_string($extra) && $extra !== '' && $extra !== '{}') {
            $decoded = json_decode($extra, true);

            return is_array($decoded) && self::extraHasValues($decoded);
        }

        return is_array($extra) && self::extraHasValues($extra);
    }

    /** @return list<string> */
    private static function valueColumns(): array
    {
        return [
            'bp_systolic', 'bp_diastolic', 'blood_sugar', 'weight_kg', 'height_cm',
            'temperature', 'spo2', 'pulse_rate', 'tsh', 't3', 't4', 'skin_score',
        ];
    }

    /** SQL fragment: row has at least one stored vital measurement. */
    private static function hasValuesSql(string $alias): string
    {
        $parts = [];
        foreach (self::valueColumns() as $col) {
            $parts[] = "{$alias}.{$col} IS NOT NULL";
        }
        $parts[] = "({$alias}.extra_vitals IS NOT NULL AND {$alias}.extra_vitals != '' AND {$alias}.extra_vitals != '{}')";

        return '(' . implode(' OR ', $parts) . ')';
    }

    /** @param array<string, mixed> $extra */
    private static function extraHasValues(array $extra): bool
    {
        foreach ($extra as $value) {
            if ($value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
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
