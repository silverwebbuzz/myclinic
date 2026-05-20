<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\RadiologyService;
use App\Support\Layout;

final class RadiologyController
{
    public function index(Request $request): Response
    {
        if ($denied = ModuleGate::require('radiology')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $page = max(1, (int) ($request->query['page'] ?? 1));

        $filters = [
            'q' => $request->query['q'] ?? '',
            'modality' => $request->query['modality'] ?? '',
            'status' => $request->query['status'] ?? '',
            'from' => $request->query['from'] ?? '',
            'to' => $request->query['to'] ?? '',
        ];

        $result = RadiologyService::listForClinic($clinicId, $filters, $page);

        return Response::html(Layout::page('radiology/index', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
        ], 'Radiology'));
    }

    public function show(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('radiology')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $order = RadiologyService::find($clinicId, (int) $id);
        if ($order === null) {
            return Response::html('Order not found', 404);
        }

        return Response::html(Layout::page('radiology/show', [
            'order' => $order,
        ], 'Radiology order #' . $id));
    }
}
