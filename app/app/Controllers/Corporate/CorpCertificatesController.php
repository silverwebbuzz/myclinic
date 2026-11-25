<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CertificateService;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\CsrfService;
use App\Services\OccHealth\CertificatePdfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/certificates — fitness certificates as the employer may see them (design §4, C9). */
final class CorpCertificatesController
{
    private const PERM = 'screening.view_status';

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $view = (string) ($request->query['view'] ?? 'valid');
        $q = trim((string) ($request->query['q'] ?? ''));

        return Response::html(View::render('corporate/certificates', [
            'view' => array_key_exists($view, CertificateService::VIEWS) ? $view : 'valid',
            'q' => $q,
            'result' => CertificateService::search($this->cid(), ['view' => $view, 'q' => $q], CorpAuth::branchScope(self::PERM), CorpContext::locale()->today(), (int) ($request->query['page'] ?? 1)),
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function pdf(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cert = CertificateService::downloadable($this->cid(), (int) $id, CorpAuth::branchScope(self::PERM));
        if ($cert === null) {
            return Response::html('Not found', 404);
        }
        CorpAudit::byUser('certificate_downloaded', 'corp_fitness_certificate', (int) $cert['id'], ['certificate' => $cert['cert_no']]);

        return CertificatePdfService::response($cert);
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }
}
