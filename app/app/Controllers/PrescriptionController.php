<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Core\QueryBuilder;
use App\Http\Request;
use App\Http\Response;
use App\Services\PatientService;
use App\Services\PrescriptionPdfService;
use App\Services\PrescriptionService;
use App\Services\VisitService;
use App\Support\Layout;

final class PrescriptionController
{
    public function index(Request $request): Response
    {
        if ($denied = ModuleGate::require('patients')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $page = max(1, (int) ($request->query['page'] ?? 1));

        $filters = [
            'q' => $request->query['q'] ?? '',
            'mode' => $request->query['mode'] ?? '',
            'patient_id' => $request->query['patient_id'] ?? '',
            'from' => $request->query['from'] ?? '',
            'to' => $request->query['to'] ?? '',
        ];

        $result = PrescriptionService::listForClinic($clinicId, $filters, $page);

        return Response::html(Layout::page('prescriptions/index', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
        ], 'Prescriptions'));
    }

    public function downloadPdf(Request $request, string $visitId): Response
    {
        $clinicId = (int) \App\Core\RequestContext::clinicId();
        $visit = VisitService::findDetailed($clinicId, (int) $visitId);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }

        $patient = PatientService::find($clinicId, (int) $visit['patient_id']) ?? [];
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
        $lines = PrescriptionService::forVisit($clinicId, (int) $visitId);

        try {
            $rel = PrescriptionPdfService::generate($visit, $patient, $clinic, $lines);
            $absolute = dirname(__DIR__, 2) . '/public' . $rel;
            if (!is_file($absolute)) {
                return Response::redirect('/prescriptions?error=' . urlencode('PDF could not be generated'));
            }
            $filename = 'rx-' . (string) ($patient['uhid'] ?? 'patient') . '-' . date('Ymd', strtotime((string) $visit['visited_at'])) . '.pdf';
            return Response::download($absolute, $filename);
        } catch (\Throwable $e) {
            error_log('[prescription PDF] ' . $e->getMessage());
            return Response::redirect('/prescriptions?error=' . urlencode('Could not generate PDF: ' . $e->getMessage()));
        }
    }
}
