<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\BillingExportService;
use App\Services\BillingPaymentService;
use App\Services\InvoiceService;
use App\Support\Layout;

final class BillingController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $filters = [
            'status' => $request->query['status'] ?? '',
            'q' => $request->query['q'] ?? '',
        ];

        return Response::html(Layout::page('billing/index', [
            'invoices' => InvoiceService::list($clinicId, $filters),
            'filters' => $filters,
        ], 'Billing'));
    }

    public function show(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $invoice = InvoiceService::findDetailed($clinicId, (int) $id);
        if ($invoice === null) {
            return Response::html('Invoice not found', 404);
        }

        $config = \App\Services\OnboardingService::specialtyConfig($clinicId) ?? [];

        return Response::html(Layout::page('billing/edit', [
            'invoice' => $invoice,
            'taxPercent' => $config['invoice_tax_percent'] ?? 0,
            'message' => $request->query['message'] ?? null,
        ], 'Invoice ' . $invoice['invoice_number']));
    }

    public function downloadPdf(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $invoice = InvoiceService::findDetailed($clinicId, (int) $id);
        if ($invoice === null) {
            return Response::html('Invoice not found', 404);
        }

        try {
            $patient = \App\Services\PatientService::find($clinicId, (int) $invoice['patient_id']) ?? [];
            $clinic = \App\Core\QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
            $rel = \App\Services\InvoicePdfService::generate($invoice, $patient, $clinic);
            $absolute = dirname(__DIR__, 2) . '/public' . $rel;
            if (!is_file($absolute)) {
                return Response::redirect('/billing/' . $id . '?error=' . urlencode('PDF could not be generated'));
            }
            return Response::download($absolute, 'invoice-' . $invoice['invoice_number'] . '.pdf');
        } catch (\Throwable $e) {
            error_log('[invoice download] ' . $e->getMessage());
            return Response::redirect('/billing/' . $id . '?error=' . urlencode('Could not generate PDF: ' . $e->getMessage()));
        }
    }

    public function update(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $items = [];
        $descriptions = $request->post['item_description'] ?? [];
        $qtys = $request->post['item_qty'] ?? [];
        $prices = $request->post['item_price'] ?? [];
        if (is_array($descriptions)) {
            foreach ($descriptions as $i => $desc) {
                if (trim((string) $desc) === '') {
                    continue;
                }
                $items[] = [
                    'description' => $desc,
                    'qty' => (int) ($qtys[$i] ?? 1),
                    'unit_price' => (float) ($prices[$i] ?? 0),
                    'item_type' => 'other',
                ];
            }
        }

        InvoiceService::update($clinicId, (int) $id, [
            'items' => $items,
            'discount_percent' => (float) ($request->post['discount_percent'] ?? 0),
            'tax_percent' => (float) ($request->post['tax_percent'] ?? 0),
            'notes' => $request->post['notes'] ?? null,
        ]);

        if (!empty($request->post['apply_advance'])) {
            InvoiceService::applyAdvance($clinicId, (int) $id);
        }

        AuditService::log($request, 'UPDATE', 'invoices', (int) $id);

        return Response::redirect('/billing/' . $id . '?message=saved');
    }

    public function payCash(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        InvoiceService::markPaid($clinicId, (int) $id, 'cash');
        AuditService::log($request, 'UPDATE', 'invoices', (int) $id);

        return Response::redirect('/billing/' . $id . '?message=paid');
    }

    public function exportExcel(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $path = BillingExportService::excel(InvoiceService::list($clinicId, [], 500));

        return Response::download($path, basename($path));
    }

    public function exportTally(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $clinic = RequestContext::clinic() ?? [];
        $path = BillingExportService::tallyXml(InvoiceService::list($clinicId, [], 500), $clinic);

        return Response::download($path, basename($path));
    }

    public function razorpayOrderApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('billing_pro')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        try {
            return Response::json(BillingPaymentService::createRazorpayOrder($clinicId, (int) $id));
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function checkPaymentApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('billing_pro')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        try {
            return Response::json(BillingPaymentService::checkPayment($clinicId, (int) $id));
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function simulatePayApi(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('billing_pro')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        BillingPaymentService::simulatePay($clinicId, (int) $id);

        return Response::json(['paid' => true]);
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('billing_pro')) {
            return Response::html(Layout::page('errors/module', [
                'module' => 'billing_pro',
                'label' => 'Billing Pro',
            ], 'Module inactive'), 402);
        }

        return null;
    }
}
