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
use App\Services\RoleAccessService;
use App\Services\VisitService;
use App\Support\ClinicTime;
use App\Support\Layout;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $__perf = isset($_GET['perf']);
        $__t0 = microtime(true);
        $clinic = RequestContext::clinic();
        $clinicId = (int) $clinic['id'];
        $user = RequestContext::user() ?? [];
        if ((int) ($clinic['onboarding_step'] ?? 1) < 5 && RoleAccessService::isClinicAdmin($user)) {
            return Response::redirect(OnboardingService::resumeUrl($clinicId));
        }

        $__lap = [];
        $__m = function (string $k) use (&$__lap) { $__lap[$k] = microtime(true); };
        $__m('start');
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];  $__m('specialtyConfig');
        $stats = DashboardService::stats($clinicId);                    $__m('stats');
        $today = self::todayAppointments($clinicId, $user);             $__m('todayAppointments');
        $visitedToday = self::todayVisited($clinicId, $user);           $__m('todayVisited');
        $checklist = ChecklistService::progress($clinicId, $clinic, $config); $__m('checklist');

        // Phase 4: follow-up widget. Best-effort — empty before Phase 4 SQL.
        $followUps = ['overdue' => [], 'overdue_count' => 0, 'due_week' => 0, 'done_month' => 0];
        try {
            $followUps = \App\Services\FollowUpService::dashboardData($clinicId);
        } catch (\Throwable $e) {
            // follow_ups table doesn't exist yet.
        }
        $__m('followUps');
        $listingStatus = \App\Services\DoctorClaimService::listingStatus($clinic); $__m('listingStatus');
        if ($__perf) {
            $__parts = []; $__keys = array_keys($__lap);
            for ($i = 1; $i < count($__keys); $i++) {
                $__parts[] = sprintf('%s=%.0fms', $__keys[$i], ($__lap[$__keys[$i]] - $__lap[$__keys[$i-1]]) * 1000);
            }
            @file_put_contents(dirname(__DIR__, 2) . '/storage/logs/perf.log',
                '[' . date('H:i:s') . '] parts ' . implode('  ', $__parts) . "\n", FILE_APPEND);
        }

        $__tData = microtime(true);
        $__html = Layout::page('dashboard/index', [
            'stats' => $stats,
            'todayAppointments' => $today['appointments'],
            'todayCounts' => $today['counts'],
            'todayDate' => $today['date'],
            'visitedToday' => $visitedToday['visits'],
            'visitedTodayCount' => $visitedToday['count'],
            'visitedTodayDate' => $visitedToday['date'],
            'checklist' => $checklist,
            'currency' => $clinic['currency'] ?? 'INR',
            'clinic' => $clinic,
            'isDirectoryListed' => (bool) ($clinic['is_directory_listed'] ?? false),
            'listingStatus' => $listingStatus,
            'followUps' => $followUps,
        ], 'Dashboard');
        if ($__perf) {
            $__tRender = microtime(true);
            @file_put_contents(
                dirname(__DIR__, 2) . '/storage/logs/perf.log',
                sprintf(
                    "[%s] dashboard data=%.0fms render=%.0fms total=%.0fms\n",
                    date('H:i:s'),
                    ($__tData - $__t0) * 1000,
                    ($__tRender - $__tData) * 1000,
                    ($__tRender - $__t0) * 1000
                ),
                FILE_APPEND
            );
        }
        return Response::html($__html);
    }

    public function queueApi(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $user = RequestContext::user() ?? [];
        $today = self::todayAppointments((int) $clinicId, $user);
        $visitedToday = self::todayVisited((int) $clinicId, $user);

        return Response::json([
            'queue_html' => \App\Support\View::render('appointments/_today_panel', [
                'appointments' => $today['appointments'],
                'counts' => $today['counts'],
                'date' => $today['date'],
                'csrf' => \App\Services\CsrfService::token(),
            ]),
            'visited_html' => \App\Support\View::render('visits/_visited_today_panel', [
                'visits' => $visitedToday['visits'],
                'visitedTodayCount' => $visitedToday['count'],
                'date' => $visitedToday['date'],
                'panelTitle' => "Today's Completed visit",
            ]),
            'stats' => DashboardService::stats($clinicId),
            'refreshed_at' => date('c'),
        ]);
    }

    /**
     * Today's appointments (all statuses) + per-status counts.
     *
     * @return array{appointments: array<int, array<string, mixed>>, counts: array<string, int>, date: string}
     */
    /** @param array<string, mixed> $user */
    private static function todayAppointments(int $clinicId, array $user = []): array
    {
        $date = ClinicTime::today();
        $doctorId = RoleAccessService::resolveAppointmentDoctorId($user, null);
        $appointments = AppointmentService::forDate($clinicId, $date, $doctorId);

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

    /**
     * Today's completed visits for the dashboard panel.
     *
     * @return array{visits: list<array<string, mixed>>, count: int, date: string}
     */
    /** @param array<string, mixed> $user */
    private static function todayVisited(int $clinicId, array $user = []): array
    {
        $date = ClinicTime::today();
        $doctorId = RoleAccessService::resolveAppointmentDoctorId($user, null);
        $visits = VisitService::listCompletedForDate($clinicId, $date, $doctorId);

        return ['visits' => $visits, 'count' => count($visits), 'date' => $date];
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
