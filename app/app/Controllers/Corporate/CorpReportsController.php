<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\ReportExportService;
use App\Services\Corporate\ReportService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/reports — compliance by group, fitness outcome mix (suppressed under 5), exports (design §6, R1/R2). */
final class CorpReportsController
{
    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('reports.view')) {
            return $deny;
        }
        [$by, $f] = $this->filters($request);
        $cid = $this->cid();
        $today = CorpContext::locale()->today();
        $scope = CorpAuth::branchScope('reports.view');

        return Response::html(View::render('corporate/reports', [
            'by' => $by,
            'f' => $f,
            'report' => ReportService::compliance($cid, $by, $f, $scope, $today),
            'outcomes' => ReportService::outcomes($cid, $by === 'programme' ? 'branch' : $by, $scope, $today),
            'outcomesBy' => $by === 'programme' ? 'branch' : $by,
            'branches' => StructureService::branches($cid, false, $scope),
            'programmes' => ProgrammeService::programmes($cid),
            'canExport' => CorpAuth::can('reports.export'),
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function export(Request $request): Response
    {
        if ($deny = CorpAuth::guard('reports.export')) {
            return $deny;
        }
        [$by, $f] = $this->filters($request);
        $format = array_key_exists($request->query['format'] ?? '', ReportExportService::FORMATS) ? (string) $request->query['format'] : 'csv';
        $report = ReportService::compliance($this->cid(), $by, $f, CorpAuth::branchScope('reports.view'), CorpContext::locale()->today());
        CorpAudit::byUser('report_exported', null, null, ['report' => 'compliance by ' . $by, 'format' => $format]);
        $company = CorpContext::company() ?? [];
        $title = 'Screening compliance by ' . strtolower(ReportService::GROUPS[$by]);

        return ReportExportService::response($format, ReportService::exportRows($report, ReportService::GROUPS[$by]), $title,
            (string) (($company['trade_name'] ?? '') ?: ($company['legal_name'] ?? '')), 'compliance-by-' . $by);
    }

    /** @return array{0: string, 1: array{branch: int, programme: int}} */
    private function filters(Request $request): array
    {
        $by = (string) ($request->query['by'] ?? 'branch');

        return [array_key_exists($by, ReportService::GROUPS) ? $by : 'branch',
            ['branch' => (int) ($request->query['branch'] ?? 0), 'programme' => (int) ($request->query['programme'] ?? 0)]];
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }
}
