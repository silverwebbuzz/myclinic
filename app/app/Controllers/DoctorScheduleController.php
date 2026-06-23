<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\DoctorScheduleService;
use App\Services\LeaveService;
use App\Services\RoleAccessService;
use App\Support\Layout;
use App\Support\WorkingHoursParser;

final class DoctorScheduleController
{
    public function index(Request $request): Response
    {
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::appointmentDoctorScope($user);
        if ($doctorId === null) {
            return Response::redirect('/dashboard?error=' . urlencode('This page is for clinic doctors only.'));
        }

        $clinicId = (int) RequestContext::clinicId();
        $workingHours = DoctorScheduleService::workingHoursForDoctor($clinicId, $doctorId);
        $slotDuration = DoctorScheduleService::slotDurationForClinic($clinicId);

        return Response::html(Layout::page('doctor/schedule', [
            'csrf' => \App\Services\CsrfService::token(),
            'workingHours' => $workingHours,
            'slotDuration' => $slotDuration,
            'leaves' => LeaveService::forDoctor($clinicId, $doctorId),
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
            'warning' => $request->query['warning'] ?? null,
        ], 'My schedule'));
    }

    public function saveHours(Request $request): Response
    {
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::appointmentDoctorScope($user);
        if ($doctorId === null) {
            return Response::redirect('/dashboard');
        }

        $clinicId = (int) RequestContext::clinicId();
        $workingHours = WorkingHoursParser::fromGroupedPost($request->post);
        $slotDuration = DoctorScheduleService::slotDurationForClinic($clinicId);

        try {
            DoctorScheduleService::syncForDoctor($clinicId, $doctorId, $workingHours, $slotDuration);
            AuditService::log($request, 'UPDATE', 'doctor_schedules', $doctorId);
        } catch (\Throwable $e) {
            return Response::redirect('/doctor/schedule?error=' . urlencode($e->getMessage()));
        }

        return Response::redirect('/doctor/schedule?message=hours_saved');
    }

    public function saveLeave(Request $request): Response
    {
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::appointmentDoctorScope($user);
        if ($doctorId === null) {
            return Response::redirect('/dashboard');
        }

        $clinicId = (int) RequestContext::clinicId();
        $date = (string) ($request->post['leave_date'] ?? '');
        $session = (string) ($request->post['session'] ?? 'full');
        $reason = trim((string) ($request->post['reason'] ?? '')) ?: null;

        $conflicts = LeaveService::conflictingAppointments($clinicId, $doctorId, $date, $session);
        if ($conflicts !== []) {
            $names = array_map(static fn ($r) => $r['patient_name'] ?? 'Patient', $conflicts);

            return Response::redirect('/doctor/schedule?warning=' . urlencode(
                'Conflicting appointments on this date: ' . implode(', ', array_slice($names, 0, 5)),
            ));
        }

        LeaveService::add($clinicId, $doctorId, $date, $session, $reason);
        AuditService::log($request, 'INSERT', 'doctor_leaves', $doctorId);

        return Response::redirect('/doctor/schedule?message=leave_saved');
    }

    public function removeLeave(Request $request, string $id): Response
    {
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::appointmentDoctorScope($user);
        if ($doctorId === null) {
            return Response::redirect('/dashboard');
        }

        $clinicId = (int) RequestContext::clinicId();
        $leave = \App\Core\QueryBuilder::table('doctor_leaves')
            ->forClinic($clinicId)
            ->where('id', '=', (int) $id)
            ->where('doctor_id', '=', $doctorId)
            ->first();

        if ($leave !== null) {
            LeaveService::remove($clinicId, (int) $id);
            AuditService::log($request, 'DELETE', 'doctor_leaves', (int) $id);
        }

        return Response::redirect('/doctor/schedule?message=leave_removed');
    }
}
