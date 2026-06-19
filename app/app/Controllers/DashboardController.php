<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
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
        if ((int) ($clinic['onboarding_step'] ?? 1) < 5) {
            return Response::redirect(OnboardingService::resumeUrl($clinicId));
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $stats = DashboardService::stats($clinicId);
        $today = self::todayAppointments($clinicId);
        $checklist = ChecklistService::progress($clinicId, $clinic, $config);

        // Phase 4: follow-up widget. Best-effort — empty before Phase 4 SQL.
        $followUps = ['overdue' => [], 'overdue_count' => 0, 'due_week' => 0, 'done_month' => 0];
        try {
            $followUps = \App\Services\FollowUpService::dashboardData($clinicId);
        } catch (\Throwable $e) {
            // follow_ups table doesn't exist yet.
        }

        return Response::html(Layout::page('dashboard/index', [
            'stats' => $stats,
            'todayAppointments' => $today['appointments'],
            'todayCounts' => $today['counts'],
            'todayDate' => $today['date'],
            'checklist' => $checklist,
            'currency' => $clinic['currency'] ?? 'INR',
            'clinic' => $clinic,
            'isDirectoryListed' => (bool) ($clinic['is_directory_listed'] ?? false),
            'followUps' => $followUps,
        ], 'Dashboard'));
    }

    public function queueApi(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $today = self::todayAppointments((int) $clinicId);

        return Response::json([
            'queue_html' => \App\Support\View::render('appointments/_today_panel', [
                'appointments' => $today['appointments'],
                'counts' => $today['counts'],
                'date' => $today['date'],
                'csrf' => \App\Services\CsrfService::token(),
            ]),
            'stats' => DashboardService::stats($clinicId),
            'refreshed_at' => date('c'),
        ]);
    }

    /**
     * Today's appointments (all statuses) + per-status counts, scoped to the
     * clinic. Mirrors AppointmentController::index so the dashboard panel shows
     * the same data as the Appointments page, locked to today.
     *
     * @return array{appointments: array<int, array<string, mixed>>, counts: array<string, int>, date: string}
     */
    private static function todayAppointments(int $clinicId): array
    {
        $date = date('Y-m-d');
        $appointments = AppointmentService::forDate($clinicId, $date, null);

        $counts = [
            'all' => count($appointments),
            'scheduled' => 0,
            'confirmed' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'no_show' => 0,
            'cancelled' => 0,
        ];
        foreach ($appointments as $a) {
            $s = (string) ($a['status'] ?? 'scheduled');
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }

        return ['appointments' => $appointments, 'counts' => $counts, 'date' => $date];
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
