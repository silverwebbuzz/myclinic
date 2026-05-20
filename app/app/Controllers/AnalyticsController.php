<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AnalyticsService;
use App\Services\BillingExportService;
use App\Services\ExpenseService;
use App\Services\InvoiceService;
use App\Support\Layout;

final class AnalyticsController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $from = $request->query['from'] ?? date('Y-m-01');
        $to = $request->query['to'] ?? date('Y-m-d');

        return Response::html(Layout::page('analytics/index', [
            'revenueSeries' => AnalyticsService::revenueExpenseSeries($clinicId),
            'flowSeries' => AnalyticsService::patientFlowSeries($clinicId),
            'heatmap' => AnalyticsService::noShowHeatmap($clinicId),
            'pnl' => AnalyticsService::profitAndLoss($clinicId, $from, $to),
            'doctors' => AnalyticsService::doctorPerformance($clinicId, $from, $to),
            'expenses' => ExpenseService::list($clinicId, $from, $to),
            'from' => $from,
            'to' => $to,
        ], 'Analytics'));
    }

    public function storeExpense(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        ExpenseService::create((int) RequestContext::clinicId(), $request->post);

        return Response::redirect('/analytics?expense_added=1');
    }

    public function exportExcel(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $path = BillingExportService::excel(InvoiceService::list($clinicId, []));

        return Response::download($path, basename($path));
    }

    public function exportTally(Request $request): Response
    {
        if ($denied = ModuleGate::require('billing_pro')) {
            return $denied;
        }

        return Response::redirect('/billing/export/tally');
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('analytics')) {
            return Response::html(Layout::page('errors/module', ['module' => 'analytics', 'label' => 'Analytics'], 'Module inactive'), 402);
        }

        return null;
    }
}
