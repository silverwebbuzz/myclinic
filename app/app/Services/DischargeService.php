<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class DischargeService
{
    public static function forVisit(int $clinicId, int $visitId): ?array
    {
        $row = QueryBuilder::table('discharge_summaries')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->first();

        if ($row === null) {
            return null;
        }
        if (is_string($row['icd10_codes'] ?? null)) {
            $row['icd10_codes'] = json_decode($row['icd10_codes'], true);
        }
        if (is_string($row['medications_at_discharge'] ?? null)) {
            $row['medications_at_discharge'] = json_decode($row['medications_at_discharge'], true);
        }

        return $row;
    }

    /** @param array<string, mixed> $data */
    public static function saveDraft(int $clinicId, int $visitId, int $patientId, array $data): array
    {
        $existing = self::forVisit($clinicId, $visitId);
        $payload = self::buildPayload($data);

        if ($existing !== null) {
            QueryBuilder::table('discharge_summaries')
                ->forClinic($clinicId)
                ->where('id', '=', (int) $existing['id'])
                ->update($payload);

            return self::forVisit($clinicId, $visitId) ?? [];
        }

        $visit = VisitService::find($clinicId, $visitId);
        if ($visit !== null && empty($payload['final_diagnosis'])) {
            $payload['final_diagnosis'] = $visit['diagnosis'] ?? $visit['chief_complaint'];
            $payload['treatment_summary'] = $visit['clinical_notes'] ?? $visit['history'];
        }

        $id = QueryBuilder::table('discharge_summaries')->insert(array_merge($payload, [
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'status' => 'draft',
        ]));

        return QueryBuilder::table('discharge_summaries')->where('id', '=', $id)->first() ?? [];
    }

    public static function finalize(int $clinicId, int $visitId, ?string $signatureDataUri = null): array
    {
        $summary = self::forVisit($clinicId, $visitId);
        if ($summary === null) {
            throw new \RuntimeException('No discharge summary found');
        }

        $patient = PatientService::find($clinicId, (int) $summary['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient === null || $clinic === null) {
            throw new \RuntimeException('Patient or clinic not found');
        }

        $sigPath = null;
        if ($signatureDataUri !== null && $signatureDataUri !== '') {
            $sigPath = self::saveDoctorSignature($clinicId, $visitId, $signatureDataUri);
        }

        $pdfPath = DischargePdfService::generate($summary, $patient, $clinic, $sigPath);
        $shareToken = bin2hex(random_bytes(16));

        QueryBuilder::table('discharge_summaries')
            ->forClinic($clinicId)
            ->where('id', '=', (int) $summary['id'])
            ->update([
                'status' => 'finalized',
                'finalized_at' => date('Y-m-d H:i:s'),
                'doctor_signature_path' => $sigPath,
                'pdf_path' => $pdfPath,
                'share_token' => $shareToken,
            ]);

        NotificationService::queueWhatsApp(
            $clinicId,
            (int) $patient['id'],
            (string) $patient['phone'],
            'follow_up_reminder',
            [
                'patient_name' => $patient['name'],
                'clinic_name' => $clinic['name'],
                'discharge_url' => '/portal/discharge/' . $shareToken,
            ],
            date('Y-m-d H:i:s', time() + 120),
        );

        return self::forVisit($clinicId, $visitId) ?? [];
    }

    public static function findByShareToken(string $token): ?array
    {
        $row = QueryBuilder::table('discharge_summaries')->where('share_token', '=', $token)->first();
        if ($row === null || ($row['status'] ?? '') !== 'finalized') {
            return null;
        }

        return self::forVisit((int) $row['clinic_id'], (int) $row['visit_id']);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function buildPayload(array $data): array
    {
        $payload = [];
        foreach (['final_diagnosis', 'procedures_done', 'treatment_summary', 'follow_up_instructions', 'diet_at_discharge'] as $f) {
            if (array_key_exists($f, $data)) {
                $payload[$f] = $data[$f] === '' ? null : $data[$f];
            }
        }
        if (isset($data['condition_at_discharge'])) {
            $payload['condition_at_discharge'] = $data['condition_at_discharge'];
        }
        if (isset($data['medications_at_discharge'])) {
            $payload['medications_at_discharge'] = is_array($data['medications_at_discharge'])
                ? json_encode($data['medications_at_discharge'])
                : $data['medications_at_discharge'];
        }
        if (isset($data['icd10_codes'])) {
            $payload['icd10_codes'] = is_array($data['icd10_codes']) ? json_encode($data['icd10_codes']) : $data['icd10_codes'];
        }

        return $payload;
    }

    private static function saveDoctorSignature(int $clinicId, int $visitId, string $dataUri): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/discharge/' . $clinicId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $rel = '/uploads/discharge/' . $clinicId . '/doc-sig-' . $visitId . '.png';
        if (preg_match('#^data:image/png;base64,(.+)$#', $dataUri, $m)) {
            file_put_contents(dirname(__DIR__, 2) . '/public' . $rel, base64_decode($m[1]));
        }

        return $rel;
    }
}
