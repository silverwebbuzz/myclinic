<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\RxFormHelper;
use App\Support\SpecialtyAdapter;

final class PrescriptionService
{
    public const PER_PAGE = 20;

    /**
     * Returns prescriptions grouped by visit, paginated by visit count (not line count).
     *
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
        $where = ['rx.clinic_id = :clinic_id'];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.uhid LIKE :q2 OR d.name LIKE :q3 OR r.name LIKE :q4)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        if (!empty($filters['mode'])) {
            $where[] = 'rx.mode = :mode';
            $params['mode'] = $filters['mode'];
        }
        if (!empty($filters['patient_id'])) {
            $where[] = 'rx.patient_id = :pid';
            $params['pid'] = (int) $filters['patient_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'v.visited_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'v.visited_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::connection();

        // Total = distinct visits that have at least one matching rx line.
        $countStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT rx.visit_id) AS c
             FROM prescriptions rx
             JOIN patients p ON p.id = rx.patient_id
             JOIN visits v ON v.id = rx.visit_id
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE {$whereSql}",
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        // Page-window of visits, with summary fields.
        $visitsStmt = $pdo->prepare(
            "SELECT v.id AS visit_id, v.visited_at, v.patient_id, v.doctor_id,
                    p.name AS patient_name, p.uhid, p.phone AS patient_phone,
                    u.name AS doctor_name,
                    COUNT(rx.id) AS line_count
             FROM prescriptions rx
             JOIN patients p ON p.id = rx.patient_id
             JOIN visits v ON v.id = rx.visit_id
             JOIN users u ON u.id = v.doctor_id
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE {$whereSql}
             GROUP BY v.id, v.visited_at, v.patient_id, v.doctor_id, p.name, p.uhid, p.phone, u.name
             ORDER BY v.visited_at DESC, v.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
        );
        $visitsStmt->execute($params);
        $visits = $visitsStmt->fetchAll() ?: [];

        if ($visits === []) {
            return ['rows' => [], 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        }

        $visitIds = array_map(static fn (array $r) => (int) $r['visit_id'], $visits);
        $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
        $linesStmt = $pdo->prepare(
            "SELECT rx.*, d.name AS drug_name, r.name AS remedy_name
             FROM prescriptions rx
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE rx.clinic_id = ? AND rx.visit_id IN ({$placeholders})
             ORDER BY rx.visit_id DESC, rx.sort_order ASC, rx.id ASC",
        );
        $linesStmt->execute(array_merge([$clinicId], $visitIds));
        $lines = $linesStmt->fetchAll() ?: [];

        $linesByVisit = [];
        foreach ($lines as $line) {
            $linesByVisit[(int) $line['visit_id']][] = $line;
        }

        foreach ($visits as &$v) {
            $v['lines'] = $linesByVisit[(int) $v['visit_id']] ?? [];
        }
        unset($v);

        return [
            'rows' => $visits,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function forVisit(int $clinicId, int $visitId): array
    {
        $rows = QueryBuilder::table('prescriptions')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->orderBy('sort_order', 'ASC')
            ->get();

        return array_map(static function (array $row) {
            if (!empty($row['drug_id'])) {
                $row['drug'] = DrugService::find((int) $row['drug_id']);
                $row['drug_name'] = $row['drug']['name'] ?? null;
            }
            if (!empty($row['remedy_id'])) {
                $row['remedy'] = RemedyService::find((int) $row['remedy_id']);
                $row['remedy_name'] = $row['remedy']['name'] ?? null;
            }

            return $row;
        }, $rows);
    }

    /**
     * Resolve the medicine label for display / PDF (matches visit UI hydration).
     *
     * @param array<string, mixed> $line
     */
    public static function medicineName(array $line): string
    {
        $name = trim((string) (
            $line['drug_name'] ?? $line['remedy_name']
            ?? $line['drug']['name'] ?? $line['remedy']['name'] ?? ''
        ));
        if ($name === '' && empty($line['drug_id']) && empty($line['remedy_id'])) {
            // Free-typed medicine with no catalog pick — name is stored in `dosage`.
            $name = trim((string) ($line['dosage'] ?? ''));
        }

        return $name;
    }

    /**
     * Human-readable dose for print (dose_amount + dose_unit, else legacy dosage).
     *
     * @param array<string, mixed> $line
     */
    public static function dosageDisplay(array $line): string
    {
        $amount = $line['dose_amount'] ?? null;
        if ($amount !== null && $amount !== '') {
            $unit = trim((string) ($line['dose_unit'] ?? ''));
            $amt = is_numeric($amount) && (float) $amount == floor((float) $amount)
                ? (string) (int) (float) $amount
                : rtrim(rtrim((string) $amount, '0'), '.');

            return $unit !== '' ? $amt . ' ' . $unit : $amt;
        }

        // Legacy `dosage` column — skip when it holds a free-typed drug name.
        if (!empty($line['drug_id']) || !empty($line['remedy_id'])
            || trim((string) ($line['drug_name'] ?? $line['remedy_name'] ?? '')) !== '') {
            return trim((string) ($line['dosage'] ?? ''));
        }

        return '';
    }

    /**
     * Frequency label for print — preset label (e.g. "1-0-1 (BD)") when available.
     *
     * @param array<string, mixed> $line
     */
    public static function frequencyDisplay(array $line): string
    {
        $preset = trim((string) ($line['frequency_preset'] ?? ''));
        if ($preset !== '') {
            return self::frequencyPresetLabel($preset, $line);
        }

        return trim((string) ($line['frequency'] ?? ''));
    }

    /**
     * @param array<string, mixed> $line
     */
    public static function frequencyPresetLabel(string $preset, array $line): string
    {
        $preset = trim($preset);
        if ($preset === '') {
            return '';
        }

        $name = self::medicineName($line);
        $doseUnit = (string) ($line['dose_unit'] ?? '');
        $catalogForm = isset($line['drug']['form']) ? (string) $line['drug']['form'] : null;
        $form = RxFormHelper::inferForm($catalogForm, $doseUnit, $name);
        foreach (RxFormHelper::frequencyPresets($form) as $opt) {
            if (($opt['value'] ?? '') === $preset) {
                return (string) ($opt['label'] ?? $preset);
            }
        }

        return $preset;
    }

    public static function foodTimingLabel(?string $timing): string
    {
        return match ($timing ?? 'any') {
            'before' => 'Before food',
            'after' => 'After food',
            'empty' => 'Empty stomach',
            'bedtime' => 'At bedtime',
            default => 'Any time',
        };
    }

    /** @return list<array<string, mixed>> */
    public static function taperingSteps(array $line): array
    {
        $raw = $line['tapering_steps'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($raw)) {
            return [];
        }

        $lineDose = isset($line['dose_amount']) && $line['dose_amount'] !== ''
            ? (float) $line['dose_amount'] : null;

        return self::hydrateTaperingSteps(
            array_values(array_filter($raw, 'is_array')),
            $lineDose,
        );
    }

    /**
     * Ensure each tapering step has dose_amount (falls back to the line dose).
     *
     * @param list<array<string, mixed>> $steps
     * @return list<array<string, mixed>>
     */
    public static function hydrateTaperingSteps(array $steps, ?float $lineDoseAmount = null): array
    {
        return array_values(array_map(static function (array $step) use ($lineDoseAmount): array {
            if (!isset($step['dose_amount']) || $step['dose_amount'] === '' || $step['dose_amount'] === null) {
                if ($lineDoseAmount !== null) {
                    $step['dose_amount'] = $lineDoseAmount;
                }
            }

            return $step;
        }, $steps));
    }

    /**
     * Validate + normalize tapering JSON before save.
     * Schema per step: {days, preset, food, dose_amount}
     *
     * @param list<array<string, mixed>> $steps
     * @return list<array{days: int, preset: string, food: string, dose_amount: float|null}>
     */
    public static function normalizeTaperingStepsForSave(array $steps, ?float $lineDoseAmount = null): array
    {
        $normalized = [];
        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $days = isset($step['days']) && $step['days'] !== '' ? (int) $step['days'] : 0;
            if ($days < 1) {
                continue;
            }

            $food = (string) ($step['food'] ?? 'any');
            if (!in_array($food, ['any', 'before', 'after', 'empty', 'bedtime'], true)) {
                $food = 'any';
            }

            $doseAmount = null;
            if (isset($step['dose_amount']) && $step['dose_amount'] !== '' && $step['dose_amount'] !== null) {
                $doseAmount = (float) $step['dose_amount'];
            } elseif ($lineDoseAmount !== null) {
                $doseAmount = $lineDoseAmount;
            }

            $normalized[] = [
                'days' => $days,
                'preset' => trim((string) ($step['preset'] ?? '')),
                'food' => $food,
                'dose_amount' => $doseAmount,
            ];
        }

        return $normalized;
    }

    /** @param array<string, mixed> $step @param array<string, mixed> $line */
    public static function stepDoseDisplay(array $step, array $line): string
    {
        $amount = $step['dose_amount'] ?? null;
        if ($amount === null || $amount === '') {
            return self::dosageDisplay($line);
        }

        $unit = trim((string) ($line['dose_unit'] ?? ''));
        $amt = is_numeric($amount) && (float) $amount == floor((float) $amount)
            ? (string) (int) (float) $amount
            : rtrim(rtrim((string) $amount, '0'), '.');

        return $unit !== '' ? $amt . ' ' . $unit : $amt;
    }

    /**
     * Total units to dispense (dose × frequency × days). Null when not calculable (e.g. SOS).
     *
     * @param array<string, mixed> $line
     * @return array{qty: float, unit: string, display: string}|null
     */
    public static function totalQuantityToPurchase(array $line): ?array
    {
        $unit = trim((string) ($line['dose_unit'] ?? ''));
        if ($unit === '') {
            $unit = 'unit';
        }

        // Liquids / drops are dispensed as one bottle — not a summed ml/drop count.
        if (self::isSinglePackUnit($unit)) {
            return [
                'qty' => 1.0,
                'unit' => $unit,
                'display' => '1',
            ];
        }

        $taperSteps = self::taperingSteps($line);
        $total = 0.0;
        $calculable = false;

        if ($taperSteps !== []) {
            foreach ($taperSteps as $step) {
                $stepQty = self::quantityForStep($step, $line);
                if ($stepQty !== null) {
                    $total += $stepQty;
                    $calculable = true;
                }
            }
        } else {
            $lineQty = self::quantityForLine($line);
            if ($lineQty !== null) {
                $total = $lineQty;
                $calculable = true;
            }
        }

        if (!$calculable || $total <= 0) {
            return null;
        }

        return [
            'qty' => $total,
            'unit' => $unit,
            'display' => self::formatPurchaseQuantity($total, $unit),
        ];
    }

    /** @param array<string, mixed> $step @param array<string, mixed> $line */
    private static function quantityForStep(array $step, array $line): ?float
    {
        $doseAmount = $step['dose_amount'] ?? $line['dose_amount'] ?? null;
        if ($doseAmount === null || $doseAmount === '') {
            return null;
        }

        $days = (int) ($step['days'] ?? 0);
        if ($days < 1) {
            return null;
        }

        $perDay = RxFormHelper::dosesPerDay((string) ($step['preset'] ?? ''), null);
        if ($perDay === null || $perDay <= 0) {
            return null;
        }

        return (float) $doseAmount * $perDay * $days;
    }

    /** @param array<string, mixed> $line */
    private static function quantityForLine(array $line): ?float
    {
        $doseAmount = $line['dose_amount'] ?? null;
        if ($doseAmount === null || $doseAmount === '') {
            return null;
        }

        $days = (int) ($line['duration_days'] ?? 0);
        if ($days < 1) {
            return null;
        }

        $perDay = RxFormHelper::dosesPerDay(
            (string) ($line['frequency_preset'] ?? ''),
            (string) ($line['frequency'] ?? ''),
        );
        if ($perDay === null || $perDay <= 0) {
            return null;
        }

        return (float) $doseAmount * $perDay * $days;
    }

    private static function formatPurchaseQuantity(float $qty, string $unit): string
    {
        $qtyStr = abs($qty - round($qty)) < 0.001
            ? (string) (int) round($qty)
            : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

        $unit = $unit !== '' ? $unit : 'units';
        $qtyNum = (float) $qtyStr;
        if ($qtyNum !== 1.0 && !str_ends_with($unit, 's')) {
            $unit = match ($unit) {
                'capsule' => 'capsules',
                'tablet' => 'tablets',
                'drop' => 'drops',
                'drops' => 'drops',
                'sachet' => 'sachets',
                'puff' => 'puffs',
                'ml' => 'ml',
                default => $unit . 's',
            };
        }

        return $qtyStr . ' ' . $unit;
    }

    /** ml / drops are purchased as one bottle, not a summed volume/count. */
    private static function isSinglePackUnit(string $unit): bool
    {
        return in_array(strtolower(trim($unit)), ['ml', 'drops', 'drop'], true);
    }

    /** @param list<array<string, mixed>> $steps */
    public static function taperingTotalDays(array $steps): int
    {
        $total = 0;
        foreach ($steps as $step) {
            $total += (int) ($step['days'] ?? 0);
        }

        return $total;
    }

    /** @param list<array<string, mixed>> $lines */
    /**
     * True when a prescription line carries any meaningful medicine content.
     * A free-typed name (without a catalog pick) counts, as do frequency/dose —
     * so a half-filled line a doctor is mid-entry on is never treated as empty.
     *
     * @param array<string,mixed> $line
     */
    private static function lineHasContent(array $line): bool
    {
        $typedName = trim((string) ($line['drug_name'] ?? ''));
        $taper = $line['tapering_steps'] ?? null;
        if (is_string($taper) && $taper !== '') {
            $decoded = json_decode($taper, true);
            $taper = is_array($decoded) ? $decoded : null;
        }

        return !empty($line['drug_id']) || !empty($line['remedy_id'])
            || !empty($line['dosage']) || $typedName !== ''
            || !empty($line['frequency_preset']) || !empty($line['dose_amount'])
            || !empty($line['duration_days'])
            || (is_array($taper) && $taper !== []);
    }

    /**
     * Replace the prescription lines for a visit with the supplied set.
     *
     * SAFETY: this is a destructive delete-then-reinsert sync. A blank or
     * not-yet-hydrated autosave payload must never be allowed to wipe a visit's
     * existing medicines. So when the incoming payload carries NO content-bearing
     * lines but the visit already has prescriptions on record, we skip the sync
     * entirely (treat it as "nothing to change") unless the caller explicitly
     * asks to clear via $allowClear — the only path that may legitimately delete
     * every line is an intentional "remove all medicines" action.
     */
    /** @return array{synced: int, skipped: bool} */
    public static function syncForVisit(int $clinicId, int $visitId, int $patientId, array $lines, bool $allowClear = false): array
    {
        // Filter to the lines that actually carry medicine content up front, so
        // the wipe-guard and the insert loop agree on what "non-empty" means.
        $contentLines = array_values(array_filter($lines, static fn ($line) => is_array($line) && self::lineHasContent($line)));

        if ($contentLines === [] && !$allowClear) {
            // Nothing meaningful to save. Don't touch existing rows — a partial or
            // pre-hydration autosave previously deleted everything here. Only wipe
            // when the visit genuinely has no prescriptions yet (no-op anyway).
            $existing = QueryBuilder::table('prescriptions')
                ->forClinic($clinicId)
                ->where('visit_id', '=', $visitId)
                ->count();
            if ($existing > 0) {
                return ['synced' => 0, 'skipped' => true];
            }
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            QueryBuilder::table('prescriptions')
                ->forClinic($clinicId)
                ->where('visit_id', '=', $visitId)
                ->delete();

            // Only the content-bearing lines get reinserted (empty rows are dropped).
            $lines = $contentLines;

            $mode = SpecialtyAdapter::usesHomeopathicRx() ? 'homeopathic' : 'allopathic';
            $order = 0;
            $drugIdsUsed = [];
            $remedyIdsUsed = [];

            foreach ($lines as $line) {
                $typedName = trim((string) ($line['drug_name'] ?? ''));

                // If the doctor typed a medicine but didn't pick from the catalog
                // (no drug_id), preserve the typed name in `dosage` so it isn't lost
                // (prescriptions has no free-text name column).
                $dosage = $line['dosage'] ?? null;
                if ($dosage === null && empty($line['drug_id']) && empty($line['remedy_id']) && $typedName !== '') {
                    $dosage = mb_substr($typedName, 0, 60);
                }

                $preset = isset($line['frequency_preset']) && $line['frequency_preset'] !== ''
                    ? (string) $line['frequency_preset'] : null;

                $lineDose = isset($line['dose_amount']) && $line['dose_amount'] !== ''
                    ? (float) $line['dose_amount'] : null;
                $tapering = null;
                if (isset($line['tapering_steps']) && is_array($line['tapering_steps']) && $line['tapering_steps'] !== []) {
                    $tapering = self::normalizeTaperingStepsForSave($line['tapering_steps'], $lineDose);
                    if ($tapering === []) {
                        $tapering = null;
                    }
                }

                $row = [
                    'clinic_id' => $clinicId,
                    'visit_id' => $visitId,
                    'patient_id' => $patientId,
                    'mode' => $line['mode'] ?? $mode,
                    'drug_id' => !empty($line['drug_id']) ? (int) $line['drug_id'] : null,
                    'remedy_id' => !empty($line['remedy_id']) ? (int) $line['remedy_id'] : null,
                    'potency' => $line['potency'] ?? null,
                    'form' => $line['form'] ?? null,
                    'dosage' => $dosage,
                    'frequency' => RxFormHelper::legacyFrequency(
                        isset($line['frequency']) ? (string) $line['frequency'] : null,
                        $preset,
                    ),
                    'duration_days' => !empty($line['duration_days']) ? (int) $line['duration_days'] : null,
                    'instructions' => $line['instructions'] ?? null,
                    'sort_order' => $order++,
                ];

                // Phase 2/3 columns — wrapped because they don't exist until
                // phase2_migrations.sql Block 2 has been run.
                $optional = [
                    'frequency_preset' => $preset,
                    'tapering_steps' => $tapering !== null
                        ? json_encode($tapering, JSON_THROW_ON_ERROR) : null,
                    'dose_unit' => $line['dose_unit'] ?? null,
                    'dose_amount' => isset($line['dose_amount']) && $line['dose_amount'] !== '' ? (float) $line['dose_amount'] : null,
                    'food_timing' => in_array($line['food_timing'] ?? 'any', ['before','after','with','empty','bedtime','any'], true)
                                      ? ($line['food_timing'] ?? 'any') : 'any',
                    'mix_with' => $line['mix_with'] ?? null,
                ];

                try {
                    QueryBuilder::table('prescriptions')->insert(array_merge($row, $optional));
                } catch (\Throwable $e) {
                    // Pre-Phase-2 schema — retry with only the legacy columns.
                    QueryBuilder::table('prescriptions')->insert($row);
                }

                if (!empty($line['drug_id'])) {
                    $drugIdsUsed[] = (int) $line['drug_id'];
                }
                if (!empty($line['remedy_id'])) {
                    $remedyIdsUsed[] = (int) $line['remedy_id'];
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Phase 3: bump usage_count for ranked autocomplete. Wrapped so a
        // missing column never breaks the visit save.
        self::bumpUsageCounts($drugIdsUsed, $remedyIdsUsed);

        return ['synced' => count($lines), 'skipped' => false];
    }

    /**
     * Bump drugs.usage_count and remedies.usage_count for every id used
     * in this save. Best-effort — silent if columns don't exist.
     *
     * @param list<int> $drugIds
     * @param list<int> $remedyIds
     */
    private static function bumpUsageCounts(array $drugIds, array $remedyIds): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            if ($drugIds !== []) {
                $placeholders = implode(',', array_fill(0, count($drugIds), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE drugs SET usage_count = usage_count + 1 WHERE id IN ($placeholders)"
                );
                $stmt->execute($drugIds);
            }
            if ($remedyIds !== []) {
                $placeholders = implode(',', array_fill(0, count($remedyIds), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE remedies SET usage_count = usage_count + 1 WHERE id IN ($placeholders)"
                );
                $stmt->execute($remedyIds);
            }
        } catch (\Throwable $e) {
            // usage_count column doesn't exist yet — skip.
        }
    }

    /** @param list<array<string, mixed>> $lines @param list<string> $allergies */
    public static function validateLines(array $lines, array $allergies): array
    {
        $warnings = [];
        $selectedDrugs = [];
        foreach ($lines as $line) {
            if (!empty($line['drug_id'])) {
                $drug = DrugService::find((int) $line['drug_id']);
                if ($drug === null) {
                    continue;
                }
                $selectedDrugs[] = $drug;
                foreach (DrugService::allergyWarnings($drug, $allergies) as $w) {
                    $warnings[] = $w;
                }
            }
        }
        foreach ($lines as $line) {
            if (!empty($line['drug_id'])) {
                $drug = DrugService::find((int) $line['drug_id']);
                if ($drug === null) {
                    continue;
                }
                foreach (DrugService::interactionWarnings($drug, $selectedDrugs) as $w) {
                    $warnings[] = $w;
                }
            }
            if (!empty($line['remedy_id'])) {
                $remedy = RemedyService::find((int) $line['remedy_id']);
                if ($remedy !== null) {
                    foreach (RemedyService::dietaryWarnings($remedy) as $w) {
                        $warnings[] = $w;
                    }
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
