<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\CrmLeadService;
use App\Services\StaffInvitationService;
use App\Support\Layout;

final class CrmController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $status = $request->query['status'] ?? '';

        return Response::html(Layout::page('crm/index', [
            'leads' => CrmLeadService::list($clinicId, $status !== '' ? $status : null),
            'counts' => CrmLeadService::kanbanCounts($clinicId),
            'status' => $status,
            'sourceChart' => CrmLeadService::sourceConversion($clinicId),
            'staff' => StaffInvitationService::staffList($clinicId),
        ], 'CRM & Leads'));
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        return Response::html(Layout::page('crm/form', [
            'lead' => null,
            'staff' => StaffInvitationService::staffList((int) RequestContext::clinicId()),
        ], 'Add lead'));
    }

    public function edit(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $lead = CrmLeadService::find($clinicId, (int) $id);
        if ($lead === null) {
            return Response::html('Lead not found', 404);
        }

        return Response::html(Layout::page('crm/form', [
            'lead' => $lead,
            'staff' => StaffInvitationService::staffList($clinicId),
        ], 'Edit lead'));
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $id = !empty($request->post['lead_id']) ? (int) $request->post['lead_id'] : null;
        $newId = CrmLeadService::save($clinicId, $id, $request->post);
        AuditService::log($request, $id ? 'UPDATE' : 'INSERT', 'crm_leads', $newId);

        return Response::redirect('/crm?status=' . urlencode($request->post['status'] ?? 'new'));
    }

    public function convert(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $patient = CrmLeadService::convertToPatient((int) RequestContext::clinicId(), (int) $id);

        return Response::redirect('/patients/' . ($patient['id'] ?? '') . '?converted=1');
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('crm')) {
            return Response::html(Layout::page('errors/module', ['module' => 'crm', 'label' => 'CRM'], 'Module inactive'), 402);
        }

        return null;
    }
}
