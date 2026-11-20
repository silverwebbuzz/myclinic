<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\OccHealth\ClinicCorporateService;
use App\Support\Layout;
use App\Support\SessionFlash;

/**
 * Clinic app › Settings › Corporate work (/settings/corporate). Clinic admins
 * only (RoleAccessService: /settings/* is admin-only). Shown once eClinicPro
 * has switched the clinic on for corporate work (design §4, C1/C2/C10).
 */
final class ClinicCorporateController
{
    public function index(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        $clinic = RequestContext::clinic() ?? [];

        return Response::html(Layout::page('settings/corporate', [
            'enabled' => ClinicCorporateService::enabled($clinicId),
            'links' => ClinicCorporateService::links($clinicId),
            'doctors' => ClinicCorporateService::doctors($clinicId),
            'statements' => \App\Services\OccHealth\StatementService::forClinic($clinicId),
            'clinicCountry' => (string) ($clinic['country_code'] ?? 'IN'),
            'countries' => \App\Support\Locale\CountryRepo::allCountries(),
            'flashOk' => SessionFlash::pull('clinic_corp_ok'),
            'flashErr' => SessionFlash::pull('clinic_corp_err'),
        ], 'Corporate work'));
    }

    public function accept(Request $request, string $id): Response
    {
        return $this->act(fn (int $clinicId, int $userId) => ClinicCorporateService::respond($clinicId, $userId, (int) $id, true),
            'Accepted. The company can now book employee checks with you.');
    }

    public function decline(Request $request, string $id): Response
    {
        return $this->act(fn (int $clinicId, int $userId) => ClinicCorporateService::respond($clinicId, $userId, (int) $id, false), 'Declined.');
    }

    public function end(Request $request, string $id): Response
    {
        return $this->act(fn (int $clinicId, int $userId) => ClinicCorporateService::end($clinicId, $userId, (int) $id, (string) ($request->post['reason'] ?? '')),
            'Ended. The company can no longer book with you.');
    }

    public function saveCredential(Request $request): Response
    {
        $file = isset($_FILES['document']) && is_array($_FILES['document']) ? $_FILES['document'] : null;

        return $this->act(static fn (int $clinicId) => ClinicCorporateService::saveCredential($clinicId, $request->post, $file),
            'Saved. eClinicPro will verify the registration, usually within one working day.', '#doctors');
    }

    public function credentialFile(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        $c = $clinicId !== null ? ClinicCorporateService::credential($clinicId, (int) $id) : null;

        return $c === null ? Response::html('Not found', 404) : ClinicCorporateService::fileResponse($c);
    }

    public function generateStatement(Request $request): Response
    {
        return $this->act(static fn (int $clinicId, int $userId) => \App\Services\OccHealth\StatementService::generate($clinicId, $userId,
            (int) ($request->post['company_id'] ?? 0), (string) ($request->post['month'] ?? '')), 'Draft statement created. Check it, then mark it sent.', '#statements');
    }

    public function statementAction(Request $request, string $id, string $action): Response
    {
        if (!in_array($action, ['send', 'paid', 'void'], true)) {
            return Response::html('Not found', 404);
        }

        return $this->act(static fn (int $clinicId, int $userId) => \App\Services\OccHealth\StatementService::act($clinicId, $userId, (int) $id, $action),
            ['send' => 'Marked as sent; the company was emailed.', 'paid' => 'Marked as paid.', 'void' => 'Draft voided; its checks can go on a new statement.'][$action], '#statements');
    }

    public function statementPdf(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        $s = $clinicId !== null ? \App\Services\OccHealth\StatementService::find($clinicId, (int) $id) : null;

        return $s === null ? Response::html('Not found', 404) : \App\Services\OccHealth\StatementService::pdfResponse($s);
    }

    /** @param callable(int, int): array{ok: bool, error?: string} $fn */
    private function act(callable $fn, string $ok, string $anchor = ''): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!ClinicCorporateService::enabled($clinicId)) {
            SessionFlash::put('clinic_corp_err', 'Corporate work is not switched on for your clinic.');

            return Response::redirect('/settings/corporate');
        }
        $res = $fn($clinicId, (int) (RequestContext::user()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'clinic_corp_ok' : 'clinic_corp_err', $res['ok'] ? $ok : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/settings/corporate' . $anchor);
    }
}
