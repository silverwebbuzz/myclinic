<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyDocumentService;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAdminService;
use App\Services\Corporate\GeoAdminService;
use App\Services\Corporate\PlatformAdminScope;
use App\Services\Corporate\UserAdminService;
use App\Services\CsrfService;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\Locale;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Super admin › Workforce Health (/admin/corporate/*): company review queue
 * and lifecycle, document review, user MFA reset, countries & industries,
 * settings and eClinicPro team roles. Country admins see only their countries.
 */
final class CorporateAdminController
{
    // ------------------------------------------------------------------
    // Companies
    // ------------------------------------------------------------------

    public function companies(Request $request): Response
    {
        $countries = PlatformAdminScope::countries($this->admin());
        $status = (string) ($request->query['status'] ?? '');
        $q = mb_substr(trim((string) ($request->query['q'] ?? '')), 0, 80);

        return $this->render('admin/corporate_companies', [
            'companies' => CorpAdminService::listCompanies($countries, $status, $q),
            'counts' => CorpAdminService::statusCounts($countries),
            'status' => $status,
            'q' => $q,
            'countryNames' => array_map(static fn (array $c): string => (string) $c['name'], CountryRepo::allCountries()),
        ]);
    }

    public function company(Request $request, string $id): Response
    {
        $company = $this->companyOr404((int) $id);
        if ($company instanceof Response) {
            return $company;
        }

        return $this->render('admin/corporate_company', [
            'company' => $company,
            'loc' => Locale::forCompany($company),
            'country' => CountryRepo::country((string) $company['country_code']),
            'region' => !empty($company['region_id']) ? CountryRepo::region((string) $company['country_code'], (int) $company['region_id']) : null,
            'industry' => $this->industryName((int) ($company['industry_id'] ?? 0)),
            'documents' => CompanyDocumentService::list((int) $company['id']),
            'docTypes' => CompanyDocumentService::TYPES,
            'users' => UserAdminService::listUsers((int) $company['id']),
            'activity' => CorpAdminService::recentActivity((int) $company['id']),
            'labels' => \App\Controllers\Corporate\CorpActivityController::LABELS,
            'scope' => [
                'review' => PlatformAdminScope::can($this->admin(), 'review'),
                'status' => PlatformAdminScope::can($this->admin(), 'status'),
                'plan' => PlatformAdminScope::can($this->admin(), 'plan'),
                'reset' => PlatformAdminScope::can($this->admin(), 'reset'),
            ],
            'trialDays' => (int) CompanyService::setting('corp_trial_days', '30'),
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        return $this->act((int) $id, 'review', static fn (array $c, int $a) => CorpAdminService::approve($c, $a), 'Company approved. The trial has started and the owner was emailed.');
    }

    public function reject(Request $request, string $id): Response
    {
        $reason = (string) ($request->post['reason'] ?? '');

        return $this->act((int) $id, 'review', static fn (array $c, int $a) => CorpAdminService::reject($c, $a, $reason), 'Registration rejected and the owner was emailed.');
    }

    public function suspend(Request $request, string $id): Response
    {
        $reason = (string) ($request->post['reason'] ?? '');

        return $this->act((int) $id, 'status', static fn (array $c, int $a) => CorpAdminService::suspend($c, $a, $reason), 'Company suspended. Everyone was signed out.');
    }

    public function reactivate(Request $request, string $id): Response
    {
        return $this->act((int) $id, 'status', static fn (array $c, int $a) => CorpAdminService::reactivate($c, $a), 'Company reactivated.');
    }

    public function extendTrial(Request $request, string $id): Response
    {
        $days = (int) ($request->post['days'] ?? 0);

        return $this->act((int) $id, 'plan', static fn (array $c, int $a) => CorpAdminService::extendTrial($c, $a, $days), 'Trial extended by ' . $days . ' days.');
    }

    public function setPlan(Request $request, string $id): Response
    {
        $until = (string) ($request->post['plan_expires_at'] ?? '');
        $plan = (string) ($request->post['plan_id'] ?? '');

        return $this->act((int) $id, 'plan', static fn (array $c, int $a) => CorpAdminService::setPlan($c, $a, $until, $plan), 'Plan saved.');
    }

    public function reviewDocument(Request $request, string $id, string $docId): Response
    {
        $approve = ($request->post['decision'] ?? '') === 'approve';
        $note = (string) ($request->post['note'] ?? '');

        return $this->act((int) $id, 'review', static fn (array $c, int $a) => CompanyDocumentService::review((int) $c['id'], (int) $docId, $a, $approve, $note),
            $approve ? 'Document approved.' : 'Document rejected; the company sees your note.');
    }

    public function document(Request $request, string $id, string $docId): Response
    {
        $company = $this->companyOr404((int) $id);
        if ($company instanceof Response) {
            return $company;
        }
        $doc = CompanyDocumentService::find((int) $company['id'], (int) $docId);

        return $doc === null ? Response::html('Not found', 404) : CompanyDocumentService::response($doc);
    }

    public function resetUserMfa(Request $request, string $id, string $userId): Response
    {
        return $this->act((int) $id, 'reset', static fn (array $c, int $a) => CorpAdminService::resetUserMfa($c, $a, (int) $userId),
            'Two-step sign-in reset; the user was signed out and emailed.');
    }

    // ------------------------------------------------------------------
    // Countries & industries
    // ------------------------------------------------------------------

    public function geo(Request $request): Response
    {
        $allowed = PlatformAdminScope::countries($this->admin());
        $countries = array_values(array_filter(GeoAdminService::countries(),
            static fn (array $c): bool => $allowed === null || in_array((string) $c['code'], $allowed, true)));

        return $this->render('admin/corporate_geo', [
            'countries' => $countries,
            'industries' => PlatformAdminScope::isSuper($this->admin()) ? GeoAdminService::industries() : [],
            'isSuper' => PlatformAdminScope::isSuper($this->admin()),
        ]);
    }

    public function country(Request $request, string $code): Response
    {
        if (!PlatformAdminScope::canSeeCountry($this->admin(), $code) || ($country = GeoAdminService::country($code)) === null) {
            return Response::html('Not found', 404);
        }

        return $this->render('admin/corporate_country', [
            'country' => $country,
            'regions' => GeoAdminService::regions($code),
            'currencies' => GeoAdminService::currencies(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'isSuper' => PlatformAdminScope::isSuper($this->admin()),
        ]);
    }

    public function saveCountry(Request $request, string $code): Response
    {
        if (!PlatformAdminScope::canSeeCountry($this->admin(), $code)) {
            return Response::html('Not found', 404);
        }
        $res = GeoAdminService::updateCountry($code, $request->post, PlatformAdminScope::isSuper($this->admin()));

        return $this->back('/admin/corporate/geo/countries/' . rawurlencode(strtoupper($code)), $res, 'Country saved.');
    }

    public function addRegion(Request $request, string $code): Response
    {
        if (!PlatformAdminScope::canSeeCountry($this->admin(), $code)) {
            return Response::html('Not found', 404);
        }
        $res = GeoAdminService::addRegion($code, (string) ($request->post['name'] ?? ''), (string) ($request->post['code'] ?? ''), (string) ($request->post['timezone'] ?? ''));

        return $this->back('/admin/corporate/geo/countries/' . rawurlencode(strtoupper($code)), $res, 'Region added.');
    }

    public function toggleRegion(Request $request, string $id): Response
    {
        $res = GeoAdminService::toggleRegion((int) $id, PlatformAdminScope::countries($this->admin()));

        return $this->back($res['ok'] ? '/admin/corporate/geo/countries/' . rawurlencode((string) $res['country']) : '/admin/corporate/geo', $res, 'Region updated.');
    }

    public function addIndustry(Request $request): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }

        return $this->back('/admin/corporate/geo', GeoAdminService::addIndustry((string) ($request->post['name'] ?? ''), (string) ($request->post['isic_code'] ?? '')), 'Industry added.');
    }

    public function toggleIndustry(Request $request, string $id): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }
        GeoAdminService::toggleIndustry((int) $id);

        return $this->back('/admin/corporate/geo', ['ok' => true], 'Industry updated.');
    }

    // ------------------------------------------------------------------
    // Settings + team roles (super only)
    // ------------------------------------------------------------------

    public function settings(Request $request): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }

        return $this->render('admin/corporate_settings', [
            'trialDays' => (int) CompanyService::setting('corp_trial_days', '30'),
            'signupEnabled' => CompanyService::setting('corp_signup_enabled', '1') === '1',
            'mfaRequired' => CompanyService::setting('corp_mfa_required', '1') === '1',
            'mfaConfigured' => \App\Services\Corporate\CorpMfaService::isConfigured(),
            'admins' => GeoAdminService::platformAdmins(),
            'countries' => GeoAdminService::countries(),
            'roles' => PlatformAdminScope::ROLES,
            'me' => (int) ($this->admin()['id'] ?? 0),
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }
        $days = (int) ($request->post['trial_days'] ?? 30);
        if ($days < 1 || $days > 365) {
            return $this->back('/admin/corporate/settings', ['ok' => false, 'error' => 'Trial length must be 1–365 days.'], '');
        }
        CorpAdminService::saveSetting('corp_trial_days', (string) $days);
        CorpAdminService::saveSetting('corp_signup_enabled', !empty($request->post['signup_enabled']) ? '1' : '0');
        CorpAdminService::saveSetting('corp_mfa_required', !empty($request->post['mfa_required']) ? '1' : '0');

        return $this->back('/admin/corporate/settings', ['ok' => true], 'Settings saved.');
    }

    public function saveAdminRole(Request $request, string $id): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }
        $countries = $request->post['countries'] ?? [];
        $res = GeoAdminService::setAdminRole((int) $id, (int) ($this->admin()['id'] ?? 0), (string) ($request->post['role'] ?? ''), is_array($countries) ? $countries : []);

        return $this->back('/admin/corporate/settings', $res, 'Team member updated.');
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function admin(): array
    {
        return RequestContext::superAdmin() ?? [];
    }

    /** @return array<string, mixed>|Response */
    private function companyOr404(int $id): array|Response
    {
        $company = CompanyService::find($id);
        if ($company === null || !PlatformAdminScope::canSeeCountry($this->admin(), (string) $company['country_code'])) {
            return Response::html('Not found', 404);
        }

        return $company;
    }

    /** @param callable(array<string,mixed>, int): array{ok: bool, error?: string} $fn */
    private function act(int $companyId, string $action, callable $fn, string $okMessage): Response
    {
        $company = $this->companyOr404($companyId);
        if ($company instanceof Response) {
            return $company;
        }
        if (!PlatformAdminScope::can($this->admin(), $action)) {
            return $this->back('/admin/corporate/companies/' . $companyId, ['ok' => false, 'error' => 'Your role can\'t do that.'], '');
        }

        return $this->back('/admin/corporate/companies/' . $companyId, $fn($company, (int) ($this->admin()['id'] ?? 0)), $okMessage);
    }

    private function superOnly(): ?Response
    {
        return PlatformAdminScope::isSuper($this->admin()) ? null : Response::html('Only super admins can do this.', 403);
    }

    private function industryName(int $id): string
    {
        foreach (CompanyService::industries() as $i) {
            if ((int) $i['id'] === $id) {
                return (string) $i['name'];
            }
        }

        return '—';
    }

    /** @param array{ok: bool, error?: string} $res */
    private function back(string $url, array $res, string $okMessage): Response
    {
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok'] ? $okMessage : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect($url);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('corp_admin_ok'),
            'flashErr' => SessionFlash::pull('corp_admin_err'),
            'adminRole' => PlatformAdminScope::role($this->admin()),
        ]));
    }
}
