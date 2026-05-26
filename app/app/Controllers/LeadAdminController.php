<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\LeadAnalyticsService;
use App\Support\View;

/**
 * /admin/leads — analytics + sales call list for the doctor-acquisition
 * funnel powered by directory_leads. Read-only for now; the settings
 * page (/admin/lead-settings) handles writes.
 */
final class LeadAdminController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/leads', [
            'admin'        => RequestContext::superAdmin(),
            'csrf'         => CsrfService::token(),
            'kpis'         => LeadAnalyticsService::kpis(),
            'recent'       => LeadAnalyticsService::recent(50),
            'topDoctors'   => LeadAnalyticsService::topDoctorsBySmsLeads(25, 30),
            'smsBreakdown' => LeadAnalyticsService::smsStatusBreakdown(30),
            'topCities'    => LeadAnalyticsService::topCitiesUnclaimed(10, 30),
            'series'       => LeadAnalyticsService::dailySeries(30),
        ]));
    }
}
