<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Services\CsrfService;
use App\Support\Layout;
use App\Support\View;

final class QueueController
{
    public function index(Request $request): Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::redirect('/dashboard');
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorId = !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;
        $queue = AppointmentService::todayQueue($clinicId, $doctorId);

        return Response::html(Layout::page('queue/index', [
            'queue' => $queue,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'doctorId' => $doctorId,
        ], 'Today\'s queue'));
    }

    public function updateStatus(Request $request, string $id): Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::redirect('/login');
        }

        $clinicId = (int) RequestContext::clinicId();
        $status = $request->post['status'] ?? 'confirmed';
        AppointmentService::updateStatus($clinicId, (int) $id, $status);
        AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

        return Response::redirect('/queue');
    }

    public function api(Request $request): Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorId = !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;

        return Response::json([
            'queue' => AppointmentService::todayQueue($clinicId, $doctorId),
            'html' => View::render('queue/_rows', [
                'queue' => AppointmentService::todayQueue($clinicId, $doctorId),
                'csrf' => CsrfService::token(),
            ]),
            'refreshed_at' => date('c'),
        ]);
    }

    public function display(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if ($clinic === null) {
            return Response::html('Clinic not found', 404);
        }

        $clinicId = (int) $clinic['id'];
        $queue = array_values(array_filter(
            AppointmentService::todayQueue($clinicId),
            static fn ($r) => in_array($r['status'], ['scheduled', 'confirmed', 'in_progress'], true),
        ));

        return Response::html(View::render('queue/display', [
            'clinic' => $clinic,
            'queue' => $queue,
        ]));
    }
}
