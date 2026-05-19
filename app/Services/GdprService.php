<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use ZipArchive;

final class GdprService
{
    public static function exportPatientZip(int $clinicId, int $patientId): ?string
    {
        $patient = PatientService::find($clinicId, $patientId);
        if ($patient === null) {
            return null;
        }

        $visits = QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('visited_at', 'DESC')
            ->get();

        $invoices = QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->get();

        $exportDir = dirname(__DIR__, 2) . '/storage/exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $jsonPath = $exportDir . '/patient_' . $patientId . '_' . time() . '.json';
        file_put_contents($jsonPath, json_encode([
            'exported_at' => gmdate('c'),
            'patient' => self::redactForExport($patient),
            'visits' => $visits,
            'invoices' => $invoices,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zipPath = $exportDir . '/gdpr_' . $clinicId . '_' . $patientId . '_' . time() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            @unlink($jsonPath);

            return null;
        }

        $zip->addFile($jsonPath, 'patient-export.json');
        $zip->addFromString('README.txt', "ManageClinic GDPR export stub\nPatient ID: {$patientId}\n");
        $zip->close();
        @unlink($jsonPath);

        return $zipPath;
    }

    public static function anonymizePatient(int $clinicId, int $patientId): bool
    {
        $patient = PatientService::find($clinicId, $patientId);
        if ($patient === null) {
            return false;
        }

        $label = 'Anonymized #' . $patientId;
        QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('id', '=', $patientId)
            ->update([
                'name' => $label,
                'phone' => '0000000000',
                'email' => null,
                'address' => null,
                'photo_path' => null,
                'allergies' => null,
                'chronic_conditions' => null,
                'insurance_provider' => null,
                'insurance_id' => null,
                'referred_by' => null,
                'is_active' => 0,
            ]);

        $sql = 'UPDATE audit_log SET old_values = NULL, new_values = NULL
                WHERE clinic_id = ? AND table_name = ? AND record_id = ?';
        Database::connection()->prepare($sql)->execute([$clinicId, 'patients', $patientId]);

        return true;
    }

    /** @param array<string, mixed> $patient @return array<string, mixed> */
    private static function redactForExport(array $patient): array
    {
        unset($patient['qr_token']);

        return $patient;
    }
}
