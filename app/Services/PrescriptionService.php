<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Support\SpecialtyAdapter;

final class PrescriptionService
{
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
            }
            if (!empty($row['remedy_id'])) {
                $row['remedy'] = RemedyService::find((int) $row['remedy_id']);
            }

            return $row;
        }, $rows);
    }

    /** @param list<array<string, mixed>> $lines */
    public static function syncForVisit(int $clinicId, int $visitId, int $patientId, array $lines): void
    {
        QueryBuilder::table('prescriptions')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->delete();

        $mode = SpecialtyAdapter::usesHomeopathicRx() ? 'homeopathic' : 'allopathic';
        $order = 0;
        foreach ($lines as $line) {
            if (empty($line['drug_id']) && empty($line['remedy_id']) && empty($line['dosage'])) {
                continue;
            }
            QueryBuilder::table('prescriptions')->insert([
                'clinic_id' => $clinicId,
                'visit_id' => $visitId,
                'patient_id' => $patientId,
                'mode' => $line['mode'] ?? $mode,
                'drug_id' => !empty($line['drug_id']) ? (int) $line['drug_id'] : null,
                'remedy_id' => !empty($line['remedy_id']) ? (int) $line['remedy_id'] : null,
                'potency' => $line['potency'] ?? null,
                'form' => $line['form'] ?? null,
                'dosage' => $line['dosage'] ?? null,
                'frequency' => $line['frequency'] ?? 'BD',
                'duration_days' => !empty($line['duration_days']) ? (int) $line['duration_days'] : null,
                'instructions' => $line['instructions'] ?? null,
                'sort_order' => $order++,
            ]);
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
