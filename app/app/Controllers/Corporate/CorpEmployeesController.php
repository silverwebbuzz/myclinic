<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\EmployeeService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\Locale\CountryRepo;
use App\Support\Locale\LocalDate;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /company/employees — the company's workforce list, add / edit, status
 * changes, history and CSV export. Branch Managers see only their branches.
 */
final class CorpEmployeesController
{
    private const FILTER_KEYS = ['q', 'branch', 'department', 'job_role', 'status', 'type', 'link'];

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('employees.view')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $scope = CorpAuth::branchScope('employees.view');
        $filters = $this->filters($request);
        $result = EmployeeService::search($cid, $filters, $scope, max(1, (int) ($request->query['page'] ?? 1)));

        return $this->render('corporate/employees', [
            'filters' => $filters,
            'result' => $result,
            'counts' => EmployeeService::counts($cid, $scope),
            'branches' => StructureService::branches($cid, false, $scope),
            'departments' => StructureService::departments($cid),
            'jobRoles' => StructureService::jobRoles($cid),
            'canManage' => CorpAuth::can('employees.manage'),
            'canExport' => CorpAuth::can('employees.export'),
            'notInvited' => CorpAuth::can('employees.manage') ? \App\Services\Corporate\EmployeeInviteService::notInvitedCount($cid, CorpAuth::branchScope('employees.manage')) : 0,
            'scoped' => $scope !== null,
        ]);
    }

    public function create(Request $request): Response
    {
        if ($deny = CorpAuth::guard('employees.manage')) {
            return $deny;
        }

        return $this->formPage(null, SessionFlash::pull('corp_employee_old'));
    }

    public function store(Request $request): Response
    {
        if ($deny = CorpAuth::guard('employees.manage')) {
            return $deny;
        }
        $res = EmployeeService::save((int) CorpContext::companyId(), (int) CorpContext::userId(), 0, $request->post, CorpAuth::branchScope('employees.manage'));
        if (!$res['ok']) {
            SessionFlash::put('corp_employee_old', $this->oldInput($request->post));
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not save.');

            return Response::redirect('/company/employees/new');
        }
        $inv = \App\Services\Corporate\EmployeeInviteService::invite((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $res['id'], CorpAuth::branchScope('employees.manage'));
        SessionFlash::put('corp_ok', 'Employee added. ' . ($inv['ok']
            ? 'We sent them an invitation by ' . implode(' and ', array_map(static fn (string $c): string => $c === 'whatsapp' ? 'WhatsApp' : 'email', $inv['channels'] ?? [])) . ' to sign in and confirm.'
            : 'They\'ll be asked to confirm the link when they sign in to eClinicPro with their mobile number.'));

        return Response::redirect('/company/employees/' . (int) $res['id']);
    }

    public function show(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('employees.view')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $emp = EmployeeService::find($cid, (int) $id, CorpAuth::branchScope('employees.view'));
        if ($emp === null) {
            return $this->notFound();
        }

        return $this->formPage($emp, SessionFlash::pull('corp_employee_old'));
    }

    public function update(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('employees.manage')) {
            return $deny;
        }
        $res = EmployeeService::save((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id, $request->post, CorpAuth::branchScope('employees.manage'));
        if (!$res['ok']) {
            SessionFlash::put('corp_employee_old', $this->oldInput($request->post));
        }
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Changes saved.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/company/employees/' . (int) $id . ($res['ok'] ? '' : '#edit'));
    }

    public function terminate(Request $request, string $id): Response
    {
        return $this->status($request, (int) $id, 'terminate', 'Marked as left. Their history is kept.');
    }

    public function leave(Request $request, string $id): Response
    {
        return $this->status($request, (int) $id, 'leave', 'Marked as on leave.');
    }

    public function returned(Request $request, string $id): Response
    {
        return $this->status($request, (int) $id, 'return', 'Marked as back at work.');
    }

    public function rehire(Request $request, string $id): Response
    {
        return $this->status($request, (int) $id, 'rehire', 'Re-hired. Their earlier history is kept.');
    }

    public function invite(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('employees.manage')) {
            return $deny;
        }
        $res = \App\Services\Corporate\EmployeeInviteService::invite((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id, CorpAuth::branchScope('employees.manage'));
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok']
            ? 'Invitation sent by ' . implode(' and ', array_map(static fn (string $c): string => $c === 'whatsapp' ? 'WhatsApp' : 'email', $res['channels'] ?? [])) . '.'
            : ($res['error'] ?? 'Could not send.'));

        return Response::redirect('/company/employees/' . (int) $id);
    }

    public function inviteAll(Request $request): Response
    {
        if ($deny = CorpAuth::guard('employees.manage')) {
            return $deny;
        }
        $r = \App\Services\Corporate\EmployeeInviteService::inviteAll((int) CorpContext::companyId(), (int) CorpContext::userId(), CorpAuth::branchScope('employees.manage'));
        $msg = number_format($r['sent']) . ' invitation' . ($r['sent'] === 1 ? '' : 's') . ' sent.';
        if ($r['unreachable'] > 0) {
            $msg .= ' ' . number_format($r['unreachable']) . ' couldn\'t be reached (no work email, and not yet an eClinicPro user for WhatsApp); ask them to sign in at eclinicpro.com/patient.';
        }
        if ($r['remaining'] > 0) {
            $msg .= ' ' . number_format($r['remaining']) . ' still to go: click again to continue.';
        }
        SessionFlash::put('corp_ok', $msg);

        return Response::redirect('/company/employees');
    }

    /** POST, returns JSON: the full ID number, for employees.manage only. Each view is logged. */
    public function revealId(Request $request, string $id): Response
    {
        if (!CorpAuth::can('employees.manage')) {
            return Response::json(['ok' => false, 'error' => 'Not allowed.'], 403);
        }
        $plain = EmployeeService::revealId((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id, CorpAuth::branchScope('employees.manage'));

        return $plain === null
            ? Response::json(['ok' => false, 'error' => 'Not available.'], 404)
            : Response::json(['ok' => true, 'id_number' => $plain]);
    }

    public function export(Request $request): Response
    {
        if ($deny = CorpAuth::guard('employees.export')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        // The export follows what the user may VIEW, narrowed by their filters.
        $csv = EmployeeService::exportCsv($cid, $this->filters($request), CorpAuth::branchScope('employees.view'));
        \App\Services\Corporate\CorpAudit::byUser('employees_exported', null, null, ['filters' => array_filter($this->filters($request))]);
        $name = 'employees-' . LocalDate::today((string) (CorpContext::company()['timezone'] ?? 'UTC')) . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    // ------------------------------------------------------------------

    private function status(Request $request, int $id, string $action, string $ok): Response
    {
        if ($deny = CorpAuth::guard('employees.manage')) {
            return $deny;
        }
        $res = EmployeeService::changeStatus((int) CorpContext::companyId(), (int) CorpContext::userId(), $id, $action,
            (string) ($request->post['date'] ?? ''), (string) ($request->post['reason'] ?? ''), CorpAuth::branchScope('employees.manage'));
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $ok : ($res['error'] ?? 'Could not update.'));

        return Response::redirect('/company/employees/' . $id);
    }

    /**
     * @param array<string, mixed>|null $emp
     * @param array<string, mixed>|null $old
     */
    private function formPage(?array $emp, ?array $old): Response
    {
        $cid = (int) CorpContext::companyId();
        $canManage = CorpAuth::can('employees.manage');
        $manageScope = CorpAuth::branchScope('employees.manage');
        // Pickers: active items, plus the employee's current one even if since deactivated.
        $branches = array_values(array_filter(StructureService::branches($cid, false, $canManage ? $manageScope : []),
            static fn (array $b): bool => (int) $b['is_active'] === 1 || ($emp !== null && (int) $b['id'] === (int) $emp['branch_id'])));
        $keep = static fn (array $rows, string $f): array => array_values(array_filter($rows,
            static fn (array $r): bool => (int) $r['is_active'] === 1 || ($emp !== null && (int) $r['id'] === (int) ($emp[$f] ?? 0))));
        $idTypes = [];
        foreach ($branches as $b) {
            $idTypes[(string) $b['country_code']] = EmployeeService::idTypesFor((string) $b['country_code']);
        }

        return $this->render('corporate/employee', [
            'emp' => $emp,
            'old' => $old,
            'events' => $emp !== null ? EmployeeService::events($cid, (int) $emp['id']) : [],
            'screening' => $emp !== null && CorpAuth::can('screening.view_status') ? $this->screeningFor($cid, (int) $emp['id']) : null,
            'bookings' => $emp !== null && CorpAuth::can('bookings.manage') ? $this->bookingsFor($cid, (int) $emp['id']) : null,
            'certificates' => $emp !== null && CorpAuth::can('screening.view_status') ? $this->certificatesFor($cid, (int) $emp['id']) : null,
            'branches' => $branches,
            'departments' => $keep(StructureService::departments($cid), 'department_id'),
            'jobRoles' => $keep(StructureService::jobRoles($cid), 'job_role_id'),
            'allDepartments' => array_column(StructureService::departments($cid), 'name', 'id'),
            'allJobRoles' => array_column(StructureService::jobRoles($cid), 'title', 'id'),
            'allBranches' => array_column(StructureService::branches($cid), 'name', 'id'),
            'countries' => CountryRepo::allCountries(),
            'idTypes' => $idTypes,
            'canManage' => $canManage && ($emp === null || EmployeeService::inScope($emp['branch_id'] !== null ? (int) $emp['branch_id'] : null, $manageScope)),
            'today' => LocalDate::today((string) ($emp['branch_timezone'] ?? CorpContext::company()['timezone'] ?? 'UTC')),
        ]);
    }

    /** @return list<array<string, mixed>>|null null = screening tables not there yet (1B patch not run) */
    /** @return list<array<string, mixed>>|null null = 1C patch not run yet */
    private function certificatesFor(int $companyId, int $employmentId): ?array
    {
        try {
            return \App\Services\Corporate\CertificateService::forEmployee($companyId, $employmentId, CorpContext::locale()->today());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return list<array<string, mixed>>|null null = 1C patch not run yet */
    private function bookingsFor(int $companyId, int $employmentId): ?array
    {
        try {
            return \App\Services\Corporate\BookingService::forEmployee($companyId, $employmentId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function screeningFor(int $companyId, int $employmentId): ?array
    {
        try {
            return \App\Services\Corporate\ScreeningCaseService::forEmployee($companyId, $employmentId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $k) {
            $f[$k] = mb_substr(trim((string) ($request->query[$k] ?? '')), 0, 80);
        }
        if ($f['status'] === '') {
            $f['status'] = 'current';
        }

        return $f;
    }

    /**
     * What to refill the form with after an error. Never the ID number.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function oldInput(array $post): array
    {
        unset($post['id_number'], $post['_csrf']);

        return array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $post);
    }

    private function notFound(): Response
    {
        SessionFlash::put('corp_err', 'That employee was not found, or is outside your branches.');

        return Response::redirect('/company/employees');
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
