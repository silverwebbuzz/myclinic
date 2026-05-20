<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\ApiKeyService;
use App\Services\AppointmentService;
use App\Services\InvoiceService;
use App\Services\PatientService;
use App\Services\VisitService;

final class ApiV1Controller
{
    public function patients(Request $request): Response
    {
        if ($denied = $this->requireScope('patients:read')) {
            return $denied;
        }
        $clinicId = RequestContext::clinicId();
        $result = PatientService::search($clinicId, [
            'q' => $request->query['q'] ?? '',
        ], max(1, (int) ($request->query['page'] ?? 1)));

        return Response::json([
            'data' => array_map([$this, 'patientResource'], $result['rows']),
            'meta' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    public function patient(Request $request, int $id): Response
    {
        if ($denied = $this->requireScope('patients:read')) {
            return $denied;
        }
        $row = PatientService::find(RequestContext::clinicId(), $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['data' => $this->patientResource($row)]);
    }

    public function createPatient(Request $request): Response
    {
        if ($denied = $this->requireScope('patients:write')) {
            return $denied;
        }
        $body = $this->jsonBody($request);
        $patient = PatientService::create(RequestContext::clinicId(), $body);

        return Response::json(['data' => ['id' => (int) ($patient['id'] ?? 0)]], 201);
    }

    public function appointments(Request $request): Response
    {
        if ($denied = $this->requireScope('appointments:read')) {
            return $denied;
        }
        $clinicId = RequestContext::clinicId();
        $start = $request->query['start'] ?? date('Y-m-d');
        $end = $request->query['end'] ?? date('Y-m-d', strtotime('+7 days'));
        $events = AppointmentService::calendarEvents($clinicId, $start, $end);

        return Response::json(['data' => $events]);
    }

    public function appointment(Request $request, int $id): Response
    {
        if ($denied = $this->requireScope('appointments:read')) {
            return $denied;
        }
        $row = AppointmentService::findDetailed(RequestContext::clinicId(), $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['data' => $row]);
    }

    public function createAppointment(Request $request): Response
    {
        if ($denied = $this->requireScope('appointments:write')) {
            return $denied;
        }
        $body = $this->jsonBody($request);
        $row = AppointmentService::create(RequestContext::clinicId(), $body);

        return Response::json(['data' => $row], 201);
    }

    public function visits(Request $request): Response
    {
        if ($denied = $this->requireScope('visits:read')) {
            return $denied;
        }
        $rows = VisitService::listRecent(RequestContext::clinicId(), (int) ($request->query['limit'] ?? 50));

        return Response::json(['data' => $rows]);
    }

    public function visit(Request $request, int $id): Response
    {
        if ($denied = $this->requireScope('visits:read')) {
            return $denied;
        }
        $row = VisitService::find(RequestContext::clinicId(), $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['data' => $row]);
    }

    public function invoices(Request $request): Response
    {
        if ($denied = $this->requireScope('invoices:read')) {
            return $denied;
        }
        $rows = InvoiceService::list(RequestContext::clinicId(), [
            'status' => $request->query['status'] ?? null,
            'q' => $request->query['q'] ?? null,
        ], (int) ($request->query['limit'] ?? 50));

        return Response::json(['data' => $rows]);
    }

    public function invoice(Request $request, int $id): Response
    {
        if ($denied = $this->requireScope('invoices:read')) {
            return $denied;
        }
        $row = InvoiceService::findDetailed(RequestContext::clinicId(), $id);
        if ($row === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        return Response::json(['data' => $row]);
    }

    /** @param array<string, mixed> $row */
    private function patientResource(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'uhid' => $row['uhid'] ?? null,
            'name' => $row['name'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'gender' => $row['gender'] ?? null,
            'dob' => $row['dob'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        if ($request->rawBody === null || $request->rawBody === '') {
            return $request->post;
        }
        $decoded = json_decode($request->rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function requireScope(string $scope): ?Response
    {
        $auth = RequestContext::apiAuth();
        if ($auth === null || !ApiKeyService::hasScope($auth, $scope)) {
            return Response::json(['error' => 'Insufficient scope', 'required' => $scope], 403);
        }

        return null;
    }
}
