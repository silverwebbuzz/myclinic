<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\CorpPlanStatus;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /company/billing — trial / plan status and how to renew. Open to every
 * signed-in user (an expired company lands here), but plan details are only
 * shown to people with billing.view; others are told to ask their admin.
 * Online payment comes later; for now renewal is arranged with eClinicPro.
 */
final class CorpBillingController
{
    public function index(Request $request): Response
    {
        $company = CompanyService::find((int) CorpContext::companyId()) ?? (CorpContext::company() ?? []);

        return Response::html(View::render('corporate/billing', [
            'csrf' => CsrfService::token(),
            'company' => $company,
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'plan' => CorpPlanStatus::for($company),
            'canSeeBilling' => CorpAuth::can('billing.view'),
            'trialDays' => (int) CompanyService::setting('corp_trial_days', '30'),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }
}
