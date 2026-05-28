<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\FollowUpService;
use App\Services\VisitService;
use App\Support\Layout;

/**
 * FollowUpController — Phase 4.
 *
 *   POST /api/v1/visits/{id}/follow-up   — create/update a visit's follow-up
 *   GET  /api/v1/follow-ups/dashboard    — widget data
 *   POST /api/v1/follow-ups/{id}/complete
 *   POST /api/v1/follow-ups/{id}/reschedule
 *   POST /api/v1/follow-ups/{id}/cancel
 *   GET  /follow-ups                      — "all follow-ups" page
 */
final class FollowUpController
{
    public function saveForVisit(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $visitId = (int) $id;

        $visit = VisitService::find($clinicId, $visitId);
        if ($visit === null) {
            return Response::json(['error' => 'Visit not found'], 404);
        }

        $body = json_decode($request->rawBody ?? '{}', true);
        $dueDate = trim((string) ($body['due_date'] ?? ''));
        $reason = $body['reason'] ?? null;
        $reasonOther = isset($body['reason_other']) ? trim((string) $body['reason_other']) : null;
        if ($reasonOther === '') $reasonOther = null;

        FollowUpService::upsertForVisit(
            $clinicId,
            (int) $visit['patient_id'],
            $visitId,
            (int) ($user['id'] ?? 0) ?: null,
            $dueDate,
            $reason !== '' ? $reason : null,
            $reasonOther
        );

        AuditService::log($request, 'UPDATE', 'follow_ups', $visitId);
        return Response::json(['ok' => true]);
    }

    public function dashboardApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        return Response::json(FollowUpService::dashboardData($clinicId));
    }

    public function complete(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $body = json_decode($request->rawBody ?? '{}', true);
        $completedVisitId = isset($body['completed_visit_id']) ? (int) $body['completed_visit_id'] : null;

        FollowUpService::markDone($clinicId, (int) $id, $completedVisitId);
        return Response::json(['ok' => true]);
    }

    public function reschedule(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $body = json_decode($request->rawBody ?? '{}', true);
        $newDate = trim((string) ($body['due_date'] ?? ''));
        if ($newDate === '') {
            return Response::json(['error' => 'due_date required'], 422);
        }
        $newId = FollowUpService::reschedule($clinicId, (int) $id, $newDate);
        return Response::json(['ok' => $newId !== null, 'new_id' => $newId]);
    }

    public function cancel(Request $request, string $id): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        FollowUpService::cancel($clinicId, (int) $id);
        return Response::json(['ok' => true]);
    }

    /** Full-page list of follow-ups for the clinic. */
    public function index(Request $request): Response
    {
        if ($denied = ModuleGate::require('emr')) {
            return $denied;
        }
        $clinicId = (int) RequestContext::clinicId();
        $data = FollowUpService::dashboardData($clinicId);

        return Response::html(Layout::page('follow_ups/index', [
            'data' => $data,
        ], 'Follow-ups'));
    }
}
