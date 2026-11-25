<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyDocumentService;
use App\Services\Corporate\CompanyProfileService;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\CsrfService;
use App\Support\Locale\CountryRepo;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/profile — company details and registration documents. */
final class CorpCompanyController
{
    public function show(Request $request): Response
    {
        if ($deny = CorpAuth::guard('company.view')) {
            return $deny;
        }
        // Re-read: CorpContext holds the row from the start of the request.
        $company = CompanyService::find((int) CorpContext::companyId()) ?? [];
        $cc = (string) ($company['country_code'] ?? '');

        return Response::html(View::render('corporate/profile', [
            'csrf' => CsrfService::token(),
            'company' => $company,
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'country' => CountryRepo::country($cc),
            'regions' => CountryRepo::regions($cc),
            'industries' => CompanyService::industries(),
            'sizeBands' => CompanyService::sizeBands(),
            'businessTypes' => CompanyProfileService::BUSINESS_TYPES,
            'documents' => CompanyDocumentService::list((int) $company['id']),
            'docTypes' => CompanyDocumentService::TYPES,
            'canEdit' => CorpAuth::can('company.edit'),
            'old' => SessionFlash::pull('corp_profile_old'),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function save(Request $request): Response
    {
        if ($deny = CorpAuth::guard('company.edit')) {
            return $deny;
        }
        $company = CompanyService::find((int) CorpContext::companyId()) ?? [];
        $res = CompanyProfileService::update($company, $request->post, (int) CorpContext::userId());
        if (!$res['ok']) {
            SessionFlash::put('corp_profile_old', $request->post);
        }
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Company profile saved.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/company/profile');
    }

    public function upload(Request $request): Response
    {
        if ($deny = CorpAuth::guard('company.edit')) {
            return $deny;
        }
        $file = $_FILES['document'] ?? [];
        $res = CompanyDocumentService::store((int) CorpContext::companyId(), (int) CorpContext::userId(),
            (string) ($request->post['doc_type'] ?? ''), is_array($file) ? $file : []);
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Document uploaded. We\'ll review it shortly.' : ($res['error'] ?? 'Upload failed.'));

        return Response::redirect('/company/profile#documents');
    }

    public function file(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('company.view')) {
            return $deny;
        }
        // Scoped by company: another company's document id simply isn't found.
        $doc = CompanyDocumentService::find((int) CorpContext::companyId(), (int) $id);

        return $doc === null ? Response::html('Not found', 404) : CompanyDocumentService::response($doc);
    }

    public function delete(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('company.edit')) {
            return $deny;
        }
        $res = CompanyDocumentService::delete((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id);
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Document deleted.' : ($res['error'] ?? 'Could not delete.'));

        return Response::redirect('/company/profile#documents');
    }
}
