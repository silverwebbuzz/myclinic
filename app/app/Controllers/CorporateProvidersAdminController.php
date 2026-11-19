<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\PlatformAdminScope;
use App\Services\CsrfService;
use App\Services\OccHealth\ClinicCorporateService;
use App\Services\OccHealth\ProviderAdminService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Super admin › Workforce Health › Clinics (/admin/corporate/providers):
 * switch clinics on for corporate work, verify doctors' registrations.
 * Super + country admins (own countries) act; billing / support read only.
 */
final class CorporateProvidersAdminController
{
    public function index(Request $request): Response
    {
        $tab = ($request->query['tab'] ?? 'clinics') === 'doctors' ? 'doctors' : 'clinics';
        $countries = PlatformAdminScope::countries($this->admin());
        $status = (string) ($request->query['status'] ?? 'pending');

        return Response::html(View::render('admin/corporate_providers', [
            'tab' => $tab,
            'q' => trim((string) ($request->query['q'] ?? '')),
            'status' => $status,
            'clinics' => $tab === 'clinics' ? ProviderAdminService::clinics((string) ($request->query['q'] ?? ''), false, $countries) : [],
            'credentials' => $tab === 'doctors' ? ProviderAdminService::credentials($status, $countries) : [],
            'pending' => ProviderAdminService::pendingCount(),
            'canEdit' => $this->canEdit(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('corp_admin_ok'),
            'flashErr' => SessionFlash::pull('corp_admin_err'),
            'adminRole' => PlatformAdminScope::role($this->admin()),
        ]));
    }

    public function toggle(Request $request, string $id): Response
    {
        $clinic = ProviderAdminService::clinic((int) $id);
        if ($clinic === null || !$this->canEdit() || !PlatformAdminScope::canSeeCountry($this->admin(), (string) $clinic['country_code'])) {
            return Response::html('Not allowed', 403);
        }
        $on = ($request->post['on'] ?? '') === '1';
        $res = ProviderAdminService::setEnabled((int) $id, $on);
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok']
            ? ($on ? $clinic['name'] . ' can now take corporate work (we emailed the clinic).' : $clinic['name'] . ' switched off. Existing companies keep their history.')
            : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/admin/corporate/providers?q=' . urlencode((string) ($request->post['q'] ?? '')));
    }

    public function review(Request $request, string $id): Response
    {
        $c = ProviderAdminService::credential((int) $id);
        if ($c === null || !$this->canEdit() || !PlatformAdminScope::canSeeCountry($this->admin(), (string) $c['country_code'])) {
            return Response::html('Not allowed', 403);
        }
        $approve = ($request->post['decision'] ?? '') === 'verify';
        $res = ProviderAdminService::review((int) $id, (int) ($this->admin()['id'] ?? 0), $approve, (string) ($request->post['note'] ?? ''));
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok'] ? ($approve ? 'Verified.' : 'Rejected. The clinic sees your note.') : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/admin/corporate/providers?tab=doctors');
    }

    public function file(Request $request, string $id): Response
    {
        $c = ProviderAdminService::credential((int) $id);
        if ($c === null || !PlatformAdminScope::canSeeCountry($this->admin(), (string) $c['country_code'])) {
            return Response::html('Not found', 404);
        }

        return ClinicCorporateService::fileResponse($c);
    }

    /** @return array<string, mixed> */
    private function admin(): array
    {
        return RequestContext::superAdmin() ?? [];
    }

    private function canEdit(): bool
    {
        return in_array(PlatformAdminScope::role($this->admin()), ['super', 'country_admin'], true);
    }
}
