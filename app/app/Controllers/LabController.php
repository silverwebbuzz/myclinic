<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\LabCatalogService;
use App\Services\LabOrderService;
use App\Services\LabReportPdfService;
use App\Support\Layout;

final class LabController
{
    public function index(Request $request): Response
    {
        return Response::redirect('/lab/catalog');
    }

    public function catalog(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        return Response::html(Layout::page('lab/catalog', [
            'tests' => LabCatalogService::listForClinic((int) RequestContext::clinicId()),
        ], 'Lab catalog'));
    }

    public function orders(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        return Response::html(Layout::page('lab/orders', [
            'orders' => LabOrderService::pendingOrders((int) RequestContext::clinicId()),
        ], 'Lab orders'));
    }

    public function showOrder(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $order = LabOrderService::findDetailed($clinicId, (int) $id);
        if ($order === null) {
            return Response::html('Order not found', 404);
        }

        return Response::html(Layout::page('lab/order', [
            'order' => $order,
            'barcodeHtml' => LabReportPdfService::barcodeLabelHtml(
                (string) $order['barcode'],
                (string) $order['patient_name'],
                (string) $order['test_name'],
            ),
        ], 'Lab order'));
    }

    public function orderFromVisit(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $order = LabOrderService::create(
            $clinicId,
            (int) $request->post['patient_id'],
            (int) $request->post['test_id'],
            !empty($request->post['visit_id']) ? (int) $request->post['visit_id'] : null,
        );
        AuditService::log($request, 'INSERT', 'lab_orders', (int) $order['id']);

        return Response::redirect('/lab/orders/' . $order['id']);
    }

    public function collectSample(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        LabOrderService::collectSample((int) RequestContext::clinicId(), (int) $id);

        return Response::redirect('/lab/orders/' . $id);
    }

    public function enterResults(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $results = [];
        $names = $request->post['parameter_name'] ?? [];
        $values = $request->post['value'] ?? [];
        if (is_array($names)) {
            foreach ($names as $i => $name) {
                if (trim((string) $name) === '') {
                    continue;
                }
                $results[] = [
                    'parameter_name' => $name,
                    'value' => $values[$i] ?? '',
                    'unit' => $request->post['unit'][$i] ?? null,
                    'normal_range' => $request->post['normal_range'][$i] ?? null,
                ];
            }
        }

        LabOrderService::enterResults((int) RequestContext::clinicId(), (int) $id, $results);

        return Response::redirect('/lab/orders/' . $id . '?resulted=1');
    }

    public function finalize(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        LabOrderService::finalizeReport((int) RequestContext::clinicId(), (int) $id);

        return Response::redirect('/lab/orders/' . $id . '?shared=1');
    }

    public function barcodePdf(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $order = LabOrderService::findDetailed((int) RequestContext::clinicId(), (int) $id);
        if ($order === null) {
            return Response::html('Not found', 404);
        }

        $html = LabReportPdfService::barcodeLabelHtml(
            (string) $order['barcode'],
            (string) $order['patient_name'],
            (string) $order['test_name'],
        );

        return Response::html('<html><body>' . $html . '</body></html>');
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('lab')) {
            return Response::html(Layout::page('errors/module', ['module' => 'lab', 'label' => 'Lab'], 'Module inactive'), 402);
        }

        return null;
    }
}
