<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\LeadSettingsService;
use App\Support\View;

/**
 * /admin/lead-settings — admin UI for the global SMS template + per-doctor
 * quota overrides. Read/write to directory_sms_settings + directory_sms_quotas.
 */
final class LeadSettingsController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/lead_settings', [
            'admin'     => RequestContext::superAdmin(),
            'csrf'      => CsrfService::token(),
            'settings'  => LeadSettingsService::get(),
            'overrides' => LeadSettingsService::listOverrides(),
            'message'   => $request->query['message'] ?? null,
        ]));
    }

    public function save(Request $request): Response
    {
        LeadSettingsService::save($request->post);
        return Response::redirect('/admin/lead-settings?message=saved');
    }

    public function saveDoctorQuota(Request $request): Response
    {
        $doctorId = (int) ($request->post['doctor_id'] ?? 0);
        if ($doctorId <= 0) return Response::redirect('/admin/lead-settings?message=invalid');

        $perDay   = isset($request->post['per_day'])   && $request->post['per_day']   !== '' ? (int) $request->post['per_day']   : null;
        $perWeek  = isset($request->post['per_week'])  && $request->post['per_week']  !== '' ? (int) $request->post['per_week']  : null;
        $perMonth = isset($request->post['per_month']) && $request->post['per_month'] !== '' ? (int) $request->post['per_month'] : null;
        $paused   = !empty($request->post['is_paused']);

        LeadSettingsService::saveDoctorQuota($doctorId, $perDay, $perWeek, $perMonth, $paused);
        return Response::redirect('/admin/lead-settings?message=override_saved');
    }
}
