<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\SpendService;
use App\Services\CsrfService;
use App\Services\OccHealth\StatementService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/reports/spend — clinic statements (records only) and spend per clinic (design §6, R6/R7). */
final class CorpSpendController
{
    public function index(Request $request): Response
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $cid = $this->cid();

        return Response::html(View::render('corporate/spend', [
            'summary' => SpendService::summary($cid, CorpContext::locale()->today()),
            'statements' => SpendService::statements($cid),
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
        if ($deny = $this->deny()) {
            return $deny;
        }
        $s = SpendService::find($this->cid(), (int) $id);

        return $s === null ? Response::html('Not found', 404) : StatementService::pdfResponse($s);
    }

    public function dispute(Request $request, string $id): Response
    {
        if ($deny = $this->deny()) {
            return $deny;
        }
        $res = SpendService::dispute($this->cid(), (int) (CorpContext::user()['id'] ?? 0), (int) $id, (string) ($request->post['note'] ?? ''));
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Marked as disputed. We emailed the clinic your note.' : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/company/reports/spend');
    }

    /** Needs both: see reports AND see billing. */
    private function deny(): ?Response
    {
        return CorpAuth::guard('reports.view') ?? CorpAuth::guard('billing.view');
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }
}
