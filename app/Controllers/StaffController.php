<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\StaffAttendanceService;
use App\Services\StaffLeaveService;
use App\Support\Layout;

final class StaffController
{
    public function attendance(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $year = (int) ($request->query['year'] ?? date('Y'));
        $month = (int) ($request->query['month'] ?? date('n'));

        return Response::html(Layout::page('staff/attendance', [
            'today' => StaffAttendanceService::todayForUser(),
            'report' => StaffAttendanceService::monthlyReport($clinicId, $year, $month),
            'year' => $year,
            'month' => $month,
        ], 'Staff attendance'));
    }

    public function clockIn(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $user = RequestContext::user();
        StaffAttendanceService::clockIn((int) RequestContext::clinicId(), (int) $user['id']);

        return Response::redirect('/staff/attendance?clocked_in=1');
    }

    public function clockOut(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $user = RequestContext::user();
        StaffAttendanceService::clockOut((int) RequestContext::clinicId(), (int) $user['id']);

        return Response::redirect('/staff/attendance?clocked_out=1');
    }

    public function leaves(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        return Response::html(Layout::page('staff/leaves', [
            'leaves' => StaffLeaveService::list((int) RequestContext::clinicId(), $request->query['status'] ?? null),
            'status' => $request->query['status'] ?? '',
        ], 'Staff leaves'));
    }

    public function requestLeave(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $user = RequestContext::user();
        StaffLeaveService::request((int) RequestContext::clinicId(), (int) $user['id'], $request->post);

        return Response::redirect('/staff/leaves?requested=1');
    }

    public function approveLeave(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        StaffLeaveService::approve((int) RequestContext::clinicId(), (int) $id);
        AuditService::log($request, 'UPDATE', 'staff_leaves', (int) $id);

        return Response::redirect('/staff/leaves?approved=1');
    }

    public function rejectLeave(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        StaffLeaveService::reject((int) RequestContext::clinicId(), (int) $id);
        AuditService::log($request, 'UPDATE', 'staff_leaves', (int) $id);

        return Response::redirect('/staff/leaves?rejected=1');
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('staff')) {
            return Response::html(Layout::page('errors/module', ['module' => 'staff', 'label' => 'Staff'], 'Module inactive'), 402);
        }

        return null;
    }
}
