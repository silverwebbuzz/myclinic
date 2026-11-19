<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\ScreeningCaseService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\Locale\LocalDate;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/screening — due checks, done elsewhere / waive, CSV. */
final class CorpScreeningController
{
    private const FILTER_KEYS = ['bucket', 'q', 'branch', 'job_role', 'programme', 'exam'];

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('screening.view_status')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $scope = CorpAuth::branchScope('screening.view_status');
        $today = $this->today();
        $filters = $this->filters($request);

        return Response::html(View::render('corporate/screening', [
            'filters' => $filters,
            'today' => $today,
            'counts' => ScreeningCaseService::counts($cid, $scope, $today),
            'result' => ScreeningCaseService::search($cid, $filters, $scope, $today, max(1, (int) ($request->query['page'] ?? 1))),
            'programmes' => ProgrammeService::programmes($cid),
            'branches' => StructureService::branches($cid, false, $scope),
            'jobRoles' => StructureService::jobRoles($cid),
            'examTypes' => ProgrammeService::examTypes(),
            'canManage' => CorpAuth::can('screening.manage'),
            'canExport' => CorpAuth::can('reports.export') || CorpAuth::can('employees.export'),
            'scoped' => $scope !== null,
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function doneElsewhere(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('screening.manage')) {
            return $deny;
        }
        $res = ScreeningCaseService::doneElsewhere((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id,
            (string) ($request->post['date'] ?? ''), (string) ($request->post['note'] ?? ''), CorpAuth::branchScope('screening.manage'), $this->today());
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Recorded as done. The next check is scheduled automatically.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect($this->back($request));
    }

    public function waive(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('screening.manage')) {
            return $deny;
        }
        $res = ScreeningCaseService::waive((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id,
            (string) ($request->post['reason'] ?? ''), CorpAuth::branchScope('screening.manage'));
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Check waived.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect($this->back($request));
    }

    public function export(Request $request): Response
    {
        if (!CorpAuth::can('reports.export') && !CorpAuth::can('employees.export')) {
            return CorpAuth::guard('reports.export') ?? Response::redirect('/company/screening');
        }
        $cid = (int) CorpContext::companyId();
        $filters = $this->filters($request);
        $csv = ScreeningCaseService::exportCsv($cid, $filters, CorpAuth::branchScope('screening.view_status'), $this->today());
        CorpAudit::byUser('screening_exported', null, null, ['filters' => array_filter($filters)]);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="screening-' . $filters['bucket'] . '-' . $this->today() . '.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    // ------------------------------------------------------------------

    private function today(): string
    {
        return LocalDate::today((string) (CorpContext::company()['timezone'] ?? 'UTC'));
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $k) {
            $f[$k] = mb_substr(trim((string) ($request->query[$k] ?? '')), 0, 80);
        }
        if (!array_key_exists($f['bucket'], ScreeningCaseService::BUCKETS) && $f['bucket'] !== 'open') {
            $f['bucket'] = 'overdue';
        }

        return $f;
    }

    /** Back to the list (or employee page) the form was on; only our own paths. */
    private function back(Request $request): string
    {
        $to = (string) ($request->post['back'] ?? '');

        return preg_match('#^/company/(screening|employees/\d+)(\?[A-Za-z0-9_=&%+.\-]*)?(\#[a-z\-]+)?$#', $to) === 1 ? $to : '/company/screening';
    }
}
