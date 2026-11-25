<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\ReportExportService;
use App\Services\Corporate\RiskService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/risks — risk register (Phase 2E). View: risk_register.manage or reports.view; edit: risk_register.manage. */
final class CorpRisksController
{
    private const EDIT = 'risk_register.manage';

    public function index(Request $request): Response
    {
        if ($deny = $this->denyView()) {
            return $deny;
        }
        $f = ['status' => (string) ($request->query['status'] ?? ''), 'branch' => (int) ($request->query['branch'] ?? 0), 'band' => (string) ($request->query['band'] ?? '')];
        $entries = RiskService::entries($this->cid(), $f, $this->scope(), CorpContext::locale()->today());

        return $this->render('corporate/risks', [
            'f' => $f,
            'entries' => $entries,
            'matrix' => RiskService::matrix($entries),
            'branches' => StructureService::branches($this->cid(), true, $this->scope()),
            'canEdit' => CorpAuth::can(self::EDIT),
            'canExport' => CorpAuth::can('reports.export'),
        ]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if ($deny = CorpAuth::guard(self::EDIT)) {
            return $deny;
        }
        $risk = (int) $id > 0 ? RiskService::find($this->cid(), (int) $id) : null;
        if ((int) $id > 0 && $risk === null) {
            return Response::html('Not found', 404);
        }

        return $this->render('corporate/risk_form', [
            'risk' => $risk,
            'old' => SessionFlash::pull('corp_risk_old'),
            'branches' => StructureService::branches($this->cid(), true, $this->scope()),
            'jobRoles' => StructureService::jobRoles($this->cid(), true),
            'owners' => RiskService::owners($this->cid()),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        return $this->form($request, $id);
    }

    public function save(Request $request, string $id = '0'): Response
    {
        if ($deny = CorpAuth::guard(self::EDIT)) {
            return $deny;
        }
        $res = RiskService::save($this->cid(), (int) (CorpContext::user()['id'] ?? 0), (int) $id, $request->post, $this->scope());
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not save.');
            SessionFlash::put('corp_risk_old', $request->post);

            return Response::redirect((int) $id > 0 ? '/company/risks/' . (int) $id : '/company/risks/new');
        }
        SessionFlash::put('corp_ok', 'Risk saved.');

        return Response::redirect('/company/risks');
    }

    public function update(Request $request, string $id): Response
    {
        return $this->save($request, $id);
    }

    public function export(Request $request): Response
    {
        if ($deny = $this->denyView() ?? CorpAuth::guard('reports.export')) {
            return $deny;
        }
        $format = array_key_exists($request->query['format'] ?? '', ReportExportService::FORMATS) ? (string) $request->query['format'] : 'xlsx';
        $entries = RiskService::entries($this->cid(), ['status' => (string) ($request->query['status'] ?? ''), 'branch' => (int) ($request->query['branch'] ?? 0)],
            $this->scope(), CorpContext::locale()->today());
        CorpAudit::byUser('report_exported', null, null, ['report' => 'risk register', 'format' => $format]);
        $c = CorpContext::company() ?? [];

        return ReportExportService::response($format, RiskService::exportRows($entries), 'Risk register',
            (string) (($c['trade_name'] ?? '') ?: ($c['legal_name'] ?? '')), 'risk-register');
    }

    // ------------------------------------------------------------------

    private function denyView(): ?Response
    {
        return CorpAuth::can(self::EDIT) || CorpAuth::can('reports.view') ? null : CorpAuth::guard(self::EDIT);
    }

    /** @return list<int>|null */
    private function scope(): ?array
    {
        return CorpAuth::can(self::EDIT) ? CorpAuth::branchScope(self::EDIT) : CorpAuth::branchScope('reports.view');
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }
}
