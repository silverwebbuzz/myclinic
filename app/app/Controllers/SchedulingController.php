<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
use App\Services\DoctorScheduleService;
use App\Services\SchedulingConfigService;
use App\Services\WaitingListService;
use App\Support\Layout;

final class SchedulingController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorId = (int) ($request->query['doctor_id'] ?? 0);
        $doctors = AppointmentService::doctorsForClinic($clinicId);
        if ($doctorId === 0 && $doctors !== []) {
            $doctorId = (int) $doctors[0]['id'];
        }

        return Response::html(Layout::page('scheduling/index', [
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'schedules' => $doctorId > 0 ? SchedulingConfigService::schedulesForDoctor($clinicId, $doctorId) : [],
            'waitingList' => WaitingListService::forClinic($clinicId),
            'googleCalendar' => SchedulingConfigService::googleCalendarStub(),
            'clinic' => RequestContext::clinic(),
        ], 'Advanced scheduling'));
    }

    public function saveSchedule(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        SchedulingConfigService::saveSchedule(
            (int) RequestContext::clinicId(),
            (int) $request->post['doctor_id'],
            $request->post,
        );

        return Response::redirect('/scheduling?doctor_id=' . (int) $request->post['doctor_id'] . '&saved=1');
    }

    public function syncFromHours(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $config = \App\Services\OnboardingService::specialtyConfig($clinicId) ?? [];
        $hours = $config['working_hours'] ?? null;
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }
        if (is_array($hours)) {
            DoctorScheduleService::syncFromWorkingHours(
                $clinicId,
                $hours,
                DoctorScheduleService::doctorIdsForClinic($clinicId),
            );
        }

        return Response::redirect('/scheduling?synced=1');
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('advanced_scheduling')) {
            return Response::html(Layout::page('errors/module', ['module' => 'advanced_scheduling', 'label' => 'Scheduling'], 'Module inactive'), 402);
        }

        return null;
    }
}
