<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Core\Database;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpAuthService;
use App\Services\Corporate\CorpContext;
use App\Services\CsrfService;
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

        $plan = \App\Services\Corporate\CorpPlanStatus::for($company);
        $trialDaysLeft = !empty($company['trial_ends_at'])
            ? LocalDate::daysUntil((string) $company['trial_ends_at'], $loc->timezone)
            : null;

        $st = Database::connection()->prepare(
            'SELECT (SELECT COUNT(*) FROM corp_users WHERE company_id = :cid) + (SELECT COUNT(*) FROM corp_invitations WHERE company_id = :cid2 AND revoked_at IS NULL)'
        );
        $st->execute(['cid' => (int) $company['id'], 'cid2' => (int) $company['id']]);
        $teamInvited = (int) $st->fetchColumn() > 1;

        $hasProfile = \App\Services\Corporate\CompanyProfileService::isComplete($company);
        $hasDocs = \App\Services\Corporate\CompanyDocumentService::list((int) $company['id']) !== [];

        // [label, hint, done, href] — href null = coming soon, '' = no button (handled elsewhere on the page)
        $checklist = [
            ['Confirm your email', 'Click the link we sent to ' . ($user['email'] ?? '') . '.', !empty($user['email_verified_at']), ''],
            ['Secure your login', 'Set up an authenticator app (two-step sign-in).', !empty($user['mfa_enabled_at']), '/company/security'],
            ['Complete your company profile', 'Address, ' . ($country['company_id_label'] ?? 'registration number') . ' and licence details.', $hasProfile, CorpAuth::can('company.edit') ? '/company/profile' : ''],
            ['Upload your trade licence', 'Needed before we can approve your account.', $hasDocs, CorpAuth::can('company.edit') ? '/company/profile#documents' : ''],
            ['Invite your team', 'HR, HSE and branch managers.', $teamInvited, CorpAuth::can('users.manage') ? '/company/users' : ''],
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
