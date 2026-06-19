<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Gates\ModuleGate;

final class PortalDashboardService
{
    /** @return array<string, mixed> */
    public static function data(int $clinicId, int $patientId): array
    {
        $patient = PatientService::find($clinicId, $patientId);
        if ($patient === null) {
            return [];
        }

        $visits = self::visits($clinicId, $patientId);
        $invoices = self::invoices($clinicId, $patientId);
        $appointments = self::appointments($clinicId, $patientId);
        $clinic = \App\Core\QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();

        return [
            'patient' => $patient,
            'visits' => $visits,
            'invoices' => $invoices,
            'appointments' => $appointments,
            'canBook' => ModuleGate::check('appointments_basic'),
            'bookUrl' => '/book/' . ($clinic['slug'] ?? 'demo'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function visits(int $clinicId, int $patientId): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, visit_number, visited_at, diagnosis, chief_complaint, rx_pdf_path, status
             FROM visits WHERE clinic_id = ? AND patient_id = ? ORDER BY visited_at DESC LIMIT 20',
        );
        $stmt->execute([$clinicId, $patientId]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            if (!empty($row['rx_pdf_path'])) {
                $row['download_token'] = SignedDownloadService::create($clinicId, $patientId, 'rx', (int) $row['id']);
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function invoices(int $clinicId, int $patientId): array
    {
        $rows = InvoiceService::list($clinicId, ['patient_id' => $patientId]);
        foreach ($rows as &$row) {
            if (!empty($row['pdf_path'])) {
                $row['download_token'] = SignedDownloadService::create($clinicId, $patientId, 'invoice', (int) $row['id']);
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function appointments(int $clinicId, int $patientId): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.name AS doctor_name
             FROM appointments a
             INNER JOIN users u ON u.id = a.doctor_id
             WHERE a.clinic_id = ? AND a.patient_id = ?
             AND a.status IN ('scheduled','confirmed')
             AND a.scheduled_at >= NOW()
             ORDER BY a.scheduled_at ASC LIMIT 10",
        );
        $stmt->execute([$clinicId, $patientId]);

        return $stmt->fetchAll() ?: [];
    }
}
