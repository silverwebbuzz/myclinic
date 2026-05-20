<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\ChecklistService;
use App\Services\DashboardService;
use App\Services\OnboardingService;
use App\Support\Layout;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        $clinicId = (int) $clinic['id'];
        $step = (int) ($clinic['onboarding_step'] ?? 1);
        if ($step < 5) {
            return Response::redirect('/onboarding/plan-selection');
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $stats = DashboardService::stats($clinicId);
        $queue = DashboardService::todayQueue($clinicId);
        $lowStock = DashboardService::lowStockItems($clinicId);
        $checklist = ChecklistService::progress($clinicId, $clinic, $config);
        $hasPharmacy = ModuleGate::check('pharmacy');

        return Response::html(Layout::page('dashboard/index', [
            'stats' => $stats,
            'queue' => $queue,
            'lowStock' => $lowStock,
            'checklist' => $checklist,
            'hasPharmacy' => $hasPharmacy,
            'currency' => $clinic['currency'] ?? 'INR',
        ], 'Dashboard'));
    }

    public function queueApi(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $queue = DashboardService::todayQueue($clinicId);

        return Response::json([
            'queue' => $queue,
            'queue_html' => \App\Support\View::render('dashboard/_queue_rows', ['queue' => $queue]),
            'stats' => DashboardService::stats($clinicId),
            'refreshed_at' => date('c'),
        ]);
    }

    public function dismissChecklist(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId !== null) {
            ChecklistService::dismiss($clinicId);
        }

        return Response::redirect('/dashboard');
    }
}
