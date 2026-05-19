<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class ConsentService
{
    public static function forVisit(int $clinicId, int $visitId): ?array
    {
        return QueryBuilder::table('consent_forms')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->orderBy('signed_at', 'DESC')
            ->first() ?: null;
    }

    /** @param array<string, mixed> $data */
    public static function sign(int $clinicId, int $visitId, int $patientId, array $data, string $signatureDataUri): array
    {
        $visit = VisitService::findDetailed($clinicId, $visitId);
        $patient = PatientService::find($clinicId, $patientId);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($visit === null || $patient === null || $clinic === null) {
            throw new \RuntimeException('Invalid visit or patient');
        }

        $content = (string) ($data['form_content'] ?? '');
        $content = ConsentTemplateService::renderContent($content, [
            'patient_name' => $patient['name'],
            'uhid' => $patient['uhid'],
            'clinic_name' => $clinic['name'],
            'date' => date('d M Y'),
            'procedure' => $data['procedure'] ?? $visit['chief_complaint'] ?? '',
            'doctor_name' => $visit['doctor_name'] ?? '',
        ]);

        $hash = hash('sha256', $content . $signatureDataUri);

        $sigPath = self::saveSignatureImage($clinicId, $visitId, $signatureDataUri);
        $pdfPath = self::generatePdf($clinicId, $visitId, $content, $sigPath, $patient, $clinic);

        $request = RequestContext::user();
        $id = QueryBuilder::table('consent_forms')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'visit_id' => $visitId,
            'form_type' => $data['form_type'] ?? 'procedure',
            'form_content' => $content,
            'signed_by_name' => $data['signed_by_name'] ?? $patient['name'],
            'relationship' => $data['relationship'] ?? 'self',
            'signature_path' => $sigPath,
            'witness_name' => $data['witness_name'] ?? null,
            'content_hash' => $hash,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'pdf_path' => $pdfPath,
            'signed_at' => date('Y-m-d H:i:s'),
        ]);

        return QueryBuilder::table('consent_forms')->where('id', '=', $id)->first() ?? [];
    }

    public static function verifyHash(array $consent): bool
    {
        $expected = $consent['content_hash'] ?? '';
        $content = $consent['form_content'] ?? '';
        $sigFile = dirname(__DIR__, 2) . '/public' . ($consent['signature_path'] ?? '');
        $sigData = is_file($sigFile) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($sigFile)) : '';

        return hash_equals($expected, hash('sha256', $content . $sigData));
    }

    private static function saveSignatureImage(int $clinicId, int $visitId, string $dataUri): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/consent/' . $clinicId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $rel = '/uploads/consent/' . $clinicId . '/sig-' . $visitId . '.png';
        if (preg_match('#^data:image/png;base64,(.+)$#', $dataUri, $m)) {
            file_put_contents(dirname(__DIR__, 2) . '/public' . $rel, base64_decode($m[1]));
        }

        return $rel;
    }

    /** @param array<string, mixed> $patient @param array<string, mixed> $clinic */
    private static function generatePdf(int $clinicId, int $visitId, string $content, string $sigPath, array $patient, array $clinic): string
    {
        $dir = dirname(__DIR__, 2) . '/public/uploads/consent/' . $clinicId;
        $rel = '/uploads/consent/' . $clinicId . '/consent-' . $visitId . '.pdf';
        $html = '<h2>' . htmlspecialchars((string) $clinic['name']) . '</h2>'
            . '<p>Patient: ' . htmlspecialchars((string) $patient['name']) . '</p>'
            . '<div style="margin:12px 0;">' . nl2br(htmlspecialchars($content)) . '</div>'
            . '<p><img src="' . htmlspecialchars($sigPath) . '" style="max-height:80px;"></p>';

        if (class_exists(Mpdf::class)) {
            $mpdf = new Mpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output(dirname(__DIR__, 2) . '/public' . $rel, Destination::FILE);
        }

        return $rel;
    }
}
