<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
use App\Services\AppointmentSlipService;
use App\Services\AuditService;
use App\Services\SlotService;
use App\Support\Layout;

final class AppointmentController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctors = AppointmentService::doctorsForClinic($clinicId);
        $doctorId = isset($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;

        $clinic = RequestContext::clinic();

        return Response::html(Layout::page('appointments/index', [
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'clinicSlug' => $clinic['slug'] ?? 'demo',
        ], 'Appointments'));
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $prefill = [
            'patient_id' => $request->query['patient_id'] ?? '',
            'doctor_id' => $request->query['doctor_id'] ?? '',
            'date' => $request->query['date'] ?? date('Y-m-d'),
            'type' => $request->query['type'] ?? 'prebooked',
            'is_followup' => !empty($request->query['followup']),
        ];

        return Response::html(Layout::page('appointments/form', [
            'appointment' => null,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'prefill' => $prefill,
            'error' => null,
            'hasTelemedicine' => ModuleGate::check('telemedicine'),
        ], 'Book appointment'));
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        try {
            $appointment = AppointmentService::create($clinicId, $this->dataFromRequest($request));
            AuditService::log($request, 'INSERT', 'appointments', (int) $appointment['id']);

            return Response::redirect('/appointments/' . $appointment['id'] . '/slip?booked=1');
        } catch (\Throwable $e) {
            return Response::html(Layout::page('appointments/form', [
                'appointment' => null,
                'doctors' => AppointmentService::doctorsForClinic($clinicId),
                'prefill' => $request->post,
                'error' => $e->getMessage(),
                'hasTelemedicine' => ModuleGate::check('telemedicine'),
            ], 'Book appointment'), 422);
        }
    }

    public function edit(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $appointment = AppointmentService::findDetailed($clinicId, (int) $id);
        if ($appointment === null) {
            return Response::html('Appointment not found', 404);
        }

        return Response::html(Layout::page('appointments/form', [
            'appointment' => $appointment,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'prefill' => [],
            'error' => null,
            'hasTelemedicine' => ModuleGate::check('telemedicine'),
        ], 'Edit appointment'));
    }

    public function update(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        try {
            $data = $this->dataFromRequest($request);
            if (!empty($request->post['scheduled_at']) && !empty($request->post['scheduled_time'])) {
                $data['scheduled_at'] = $request->post['scheduled_date'] . ' ' . $request->post['scheduled_time'] . ':00';
            }
            AppointmentService::update($clinicId, (int) $id, $data);
            AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

            return Response::redirect('/appointments?updated=1');
        } catch (\Throwable $e) {
            $appointment = AppointmentService::findDetailed($clinicId, (int) $id);

            return Response::html(Layout::page('appointments/form', [
                'appointment' => $appointment,
                'doctors' => AppointmentService::doctorsForClinic($clinicId),
                'prefill' => [],
                'error' => $e->getMessage(),
            ], 'Edit appointment'), 422);
        }
    }

    public function cancel(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        AppointmentService::cancel($clinicId, (int) $id);
        AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

        return Response::redirect('/appointments?cancelled=1');
    }

    public function slip(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $appointment = AppointmentService::findDetailed($clinicId, (int) $id);
        if ($appointment === null) {
            return Response::html('Not found', 404);
        }

        $clinic = RequestContext::clinic();
        $path = AppointmentSlipService::generate($appointment, $clinic ?? []);

        if (!empty($request->query['booked'])) {
            return Response::html(Layout::page('appointments/booked', [
                'appointment' => $appointment,
                'slipUrl' => $path,
            ], 'Appointment booked'));
        }

        return Response::redirect($path);
    }

    public function slotsApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorId = (int) ($request->query['doctor_id'] ?? 0);
        $date = $request->query['date'] ?? date('Y-m-d');

        if ($doctorId < 1) {
            return Response::json(['slots' => []]);
        }

        return Response::json([
            'slots' => SlotService::available($clinicId, $doctorId, $date),
            'refreshed_at' => date('c'),
        ]);
    }

    public function calendarApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('appointments_basic')) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $start = $request->query['start'] ?? date('Y-m-01');
        $end = $request->query['end'] ?? date('Y-m-t 23:59:59');
        $doctorId = !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;

        return Response::json(AppointmentService::calendarEvents($clinicId, $start, $end, $doctorId));
    }

    /** @return array<string, mixed> */
    private function dataFromRequest(Request $request): array
    {
        $date = $request->post['scheduled_date'] ?? date('Y-m-d');
        $time = $request->post['scheduled_time'] ?? '09:00';

        return [
            'patient_id' => (int) ($request->post['patient_id'] ?? 0),
            'doctor_id' => (int) ($request->post['doctor_id'] ?? 0),
            'scheduled_at' => $date . ' ' . $time . ':00',
            'type' => $request->post['type'] ?? 'prebooked',
            'chief_complaint' => $request->post['chief_complaint'] ?? '',
            'notes' => $request->post['notes'] ?? '',
            'is_followup' => !empty($request->post['is_followup']),
            'source' => 'reception',
        ];
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::html(Layout::page('errors/module', [
                'module' => 'appointments_basic',
                'label' => 'Appointments',
            ], 'Module inactive'), 402);
        }

        return null;
    }
}
