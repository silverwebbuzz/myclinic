<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DoctorClaimService;
use App\Support\Layout;

/**
 * /onboarding/get-listed — in-portal "list my clinic on eClinicPro"
 * application page. Reuses DoctorClaimService and the existing admin
 * review queue. The tenant is already authenticated so no phone OTP.
 */
final class GetListedController
{
    public function show(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if (!$clinic) return Response::redirect('/login');

        // Already listed? Send them back to the dashboard with a flash hint.
        if (!empty($clinic['is_directory_listed'])) {
            return Response::redirect('/dashboard?message=already_listed');
        }

        $latest = DoctorClaimService::latestForTenantPhone((string) ($clinic['phone'] ?? ''));

        return Response::html(Layout::page('onboarding/get-listed', [
            'clinic'  => $clinic,
            'latest'  => $latest,
            'csrf'    => CsrfService::token(),
        ], 'Get listed'));
    }

    public function submit(Request $request): Response
    {
        $clinic = RequestContext::clinic();
        if (!$clinic) return Response::redirect('/login');
        if (!empty($clinic['is_directory_listed'])) {
            return Response::redirect('/dashboard?message=already_listed');
        }

        $tenantId = (int) ($clinic['id'] ?? 0);
        $id = DoctorClaimService::submitFromPortal($tenantId, $clinic, $request->post);
        if ($id === null) {
            return Response::redirect('/onboarding/get-listed?message=failed');
        }
        return Response::redirect('/onboarding/get-listed?message=submitted');
    }
}
