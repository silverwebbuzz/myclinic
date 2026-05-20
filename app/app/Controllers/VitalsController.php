<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\VitalsService;
use App\Support\Layout;

final class VitalsController
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
            'patient_id' => $request->query['patient_id'] ?? '',
            'from' => $request->query['from'] ?? '',
            'to' => $request->query['to'] ?? '',
            'abnormal' => $request->query['abnormal'] ?? '',
        ];

        $result = VitalsService::listForClinic($clinicId, $filters, $page);

        return Response::html(Layout::page('vitals/index', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
        ], 'Vitals'));
    }
}
