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
use App\Services\RoleAccessService;
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
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::resolveAppointmentDoctorId(
            $user,
            !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null,
        );
        $queue = AppointmentService::todayQueue($clinicId, $doctorId);

        // Phase 4: flag patients in the queue who have a pending follow-up
        // due within 7 days. Best-effort — empty before Phase 4 SQL.
        $followUpFlags = [];
        try {
            $patientIds = array_filter(array_map(static fn ($r) => (int) ($r['patient_id'] ?? 0), $queue));
            $followUpFlags = \App\Services\FollowUpService::pendingForPatients($clinicId, $patientIds);
        } catch (\Throwable $e) {
            // follow_ups table doesn't exist yet.
        }

        return Response::html(Layout::page('queue/index', [
            'queue' => $queue,
            'doctors' => AppointmentService::doctorsForClinic($clinicId),
            'doctorId' => $doctorId,
            'followUpFlags' => $followUpFlags,
            'canBookForAll' => RoleAccessService::canBookAppointmentsForAllDoctors($user),
            'isDoctorScoped' => RoleAccessService::appointmentDoctorScope($user) !== null,
        ], 'Today\'s queue'));
    }

    public function updateStatus(Request $request, string $id): Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::redirect('/login');
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $existing = AppointmentService::find($clinicId, (int) $id);
        if ($existing !== null && !RoleAccessService::canAccessAppointment($user, $existing)) {
            return Response::redirect(self::queueUrl($request) . (str_contains(self::queueUrl($request), '?') ? '&' : '?') . 'error=not_found');
        }

        $status = (string) ($request->post['status'] ?? '');
        $back = self::queueUrl($request);

        try {
            AppointmentService::updateStatus($clinicId, (int) $id, $status);
        } catch (\InvalidArgumentException $e) {
            return Response::redirect($back . (str_contains($back, '?') ? '&' : '?') . 'error=invalid_status');
        } catch (\RuntimeException $e) {
            return Response::redirect($back . (str_contains($back, '?') ? '&' : '?') . 'error=not_found');
        }

        AuditService::log($request, 'UPDATE', 'appointments', (int) $id);

        return Response::redirect($back . (str_contains($back, '?') ? '&' : '?') . 'message=status_updated');
    }

    public function callNext(Request $request): Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::redirect('/login');
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::resolveAppointmentDoctorId(
            $user,
            !empty($request->post['doctor_id']) ? (int) $request->post['doctor_id'] : null,
        );
        $next = AppointmentService::callNext($clinicId, $doctorId);
        $back = self::queueUrl($request);

        if ($next === null) {
            return Response::redirect($back . (str_contains($back, '?') ? '&' : '?') . 'message=queue_empty');
        }

        AuditService::log($request, 'UPDATE', 'appointments', (int) $next['id']);

        return Response::redirect($back . (str_contains($back, '?') ? '&' : '?') . 'message=called_next');
    }

    /** Preserve the doctor filter across status-change redirects. */
    private static function queueUrl(Request $request): string
    {
        $doctorId = (int) ($request->post['doctor_id'] ?? $request->query['doctor_id'] ?? 0);

        return $doctorId > 0 ? '/queue?doctor_id=' . $doctorId : '/queue';
    }

    public function api(Request $request): Response
    {
        if (!ModuleGate::check('appointments_basic')) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $doctorId = RoleAccessService::resolveAppointmentDoctorId(
            $user,
            !empty($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null,
        );
        $queue = AppointmentService::todayQueue($clinicId, $doctorId);

        // Same follow-up flags as the initial page load, so the badges don't
        // vanish on the first auto-refresh.
        $followUpFlags = [];
        try {
            $patientIds = array_filter(array_map(static fn ($r) => (int) ($r['patient_id'] ?? 0), $queue));
            $followUpFlags = \App\Services\FollowUpService::pendingForPatients($clinicId, $patientIds);
        } catch (\Throwable $e) {
            // follow_ups table doesn't exist yet.
        }

        return Response::json([
            'queue' => $queue,
            'html' => View::render('queue/_rows', [
                'queue' => $queue,
                'csrf' => CsrfService::token(),
                'followUpFlags' => $followUpFlags,
                'doctorId' => $doctorId,
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
