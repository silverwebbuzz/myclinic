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

        $hasBranch = \App\Services\Corporate\StructureService::branches((int) $company['id'], true) !== [];
        $hasJobRole = \App\Services\Corporate\StructureService::jobRoles((int) $company['id'], true) !== [];
        $hasEmployee = \App\Services\Corporate\EmployeeService::counts((int) $company['id'], null)['active'] > 0;
        $hasProfile = \App\Services\Corporate\CompanyProfileService::isComplete($company);
        $hasDocs = \App\Services\Corporate\CompanyDocumentService::list((int) $company['id']) !== [];

        // [label, hint, done, href] — href null = coming soon, '' = no button (handled elsewhere on the page)
        $checklist = [
            ['Confirm your email', 'Click the link we sent to ' . ($user['email'] ?? '') . '.', !empty($user['email_verified_at']), ''],
            ['Secure your login', 'Set up an authenticator app (two-step sign-in).', !empty($user['mfa_enabled_at']), '/company/security'],
            ['Complete your company profile', 'Address, ' . ($country['company_id_label'] ?? 'registration number') . ' and licence details.', $hasProfile, CorpAuth::can('company.edit') ? '/company/profile' : ''],
            ['Upload your trade licence', 'Needed before we can approve your account.', $hasDocs, CorpAuth::can('company.edit') ? '/company/profile#documents' : ''],
            ['Invite your team', 'HR, HSE and branch managers.', $teamInvited, CorpAuth::can('users.manage') ? '/company/users' : ''],
            ['Add your branches', 'Offices, sites and yards where your people work.', $hasBranch, CorpAuth::can('branches.manage') ? '/company/branches' : ''],
            ['Add job roles', 'Screening is assigned by job role.', $hasJobRole, CorpAuth::can('jobroles.manage') ? '/company/job-roles' : ''],
            ['Add your employees', 'One by one, or import them from Excel / CSV.', $hasEmployee, $hasBranch ? (CorpAuth::can('employees.import') ? '/company/employees/import' : (CorpAuth::can('employees.manage') ? '/company/employees/new' : '')) : ''],
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
            'screening' => $this->screeningTiles((int) $company['id'], (string) ($company['timezone'] ?? 'UTC')),
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

    /** @return array<string, int|null>|null null = no permission, or 1B tables not there yet */
    private function screeningTiles(int $companyId, string $tz): ?array
    {
        if (!CorpAuth::can('screening.view_status')) {
            return null;
        }
        try {
            return \App\Services\Corporate\ScreeningCaseService::counts($companyId, CorpAuth::branchScope('screening.view_status'),
                \App\Support\Locale\LocalDate::today($tz));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
