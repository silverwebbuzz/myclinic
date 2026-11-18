<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAuthService;
use App\Services\Corporate\CorpContext;
use App\Services\CsrfService;
use App\Services\SubscriptionStatus;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\SessionFlash;
use App\Support\View;

/** Company portal home: account status, trial, and the setup checklist. */
final class CorpDashboardController
{
    public function index(Request $request): Response
    {
        $company = CorpContext::company() ?? [];
        $user = CorpContext::user() ?? [];
        $loc = CorpContext::locale();
        $country = CountryRepo::country((string) ($company['country_code'] ?? ''));

        $plan = SubscriptionStatus::forClinic($company);
        $trialDaysLeft = !empty($company['trial_ends_at'])
            ? LocalDate::daysUntil((string) $company['trial_ends_at'], $loc->timezone)
            : null;

        $hasProfile = !empty($company['address_line1']) && !empty($company['registration_no']);

        // [label, hint, done, href] — href null = coming soon, '' = no button (handled elsewhere on the page)
        $checklist = [
            ['Confirm your email', 'Click the link we sent to ' . ($user['email'] ?? '') . '.', !empty($user['email_verified_at']), ''],
            ['Secure your login', 'Set up an authenticator app (two-step sign-in).', !empty($user['mfa_enabled_at']), null],
            ['Complete your company profile', 'Address, ' . ($country['company_id_label'] ?? 'registration number') . ' and licence details.', $hasProfile, null],
            ['Upload your trade licence', 'Needed before we can approve your account.', false, null],
            ['Invite your team', 'HR, HSE and branch managers.', false, null],
        ];

        return Response::html(View::render('corporate/dashboard', [
            'csrf' => CsrfService::token(),
            'company' => $company,
            'user' => $user,
            'loc' => $loc,
            'country' => $country,
            'roles' => CompanyService::roleNames((int) $company['id'], (int) $user['id']),
            'plan' => $plan,
            'trialDaysLeft' => $trialDaysLeft,
            'checklist' => $checklist,
            'welcome' => !empty($request->query['welcome']),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function resendVerification(Request $request): Response
    {
        $user = CorpContext::user() ?? [];
        if (!empty($user['email_verified_at'])) {
            SessionFlash::put('corp_ok', 'Your email is already confirmed.');
        } elseif (CorpAuthService::sendVerifyEmail($user)) {
            SessionFlash::put('corp_ok', 'We sent a new confirmation link to ' . $user['email'] . '.');
        } else {
            SessionFlash::put('corp_err', 'We couldn\'t send the email just now (or you asked several times in the last hour). Please try again later.');
        }

        return Response::redirect('/company/dashboard');
    }
}
