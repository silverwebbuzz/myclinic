<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\IncidentService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/incidents + absences on the employee page (Phase 2B). Permission: incidents.manage. */
final class CorpIncidentsController
{
    private const PERM = 'incidents.manage';

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $f = ['status' => (string) ($request->query['status'] ?? ''), 'type' => (string) ($request->query['type'] ?? ''), 'branch' => (int) ($request->query['branch'] ?? 0)];
        $scope = CorpAuth::branchScope(self::PERM);

        return Response::html(View::render('corporate/incidents', [
            'f' => $f,
            'rows' => IncidentService::incidents($this->cid(), $f, $scope),
            'branches' => StructureService::branches($this->cid(), true, $scope),
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }

    public function store(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = IncidentService::recordIncident($this->cid(), $this->uid(), $request->post, CorpAuth::branchScope(self::PERM), CorpContext::locale()->today());
        $back = !empty($request->post['employment_id']) ? '/company/employees/' . (int) $request->post['employment_id'] . '#incidents' : '/company/incidents';

        return $this->back($back, $res, !empty($request->post['needs_check']) && (!empty($request->post['employment_id']) || !empty($request->post['employee_no']))
            ? 'Incident recorded. A post-incident check is now due: book it from the due list.' : 'Incident recorded.');
    }

    public function status(Request $request, string $id, string $action): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }

        return $this->back('/company/incidents', IncidentService::setIncidentStatus($this->cid(), $this->uid(), (int) $id, $action, CorpAuth::branchScope(self::PERM)),
            ['close' => 'Incident closed.', 'reopen' => 'Incident reopened.', 'remove' => 'Incident removed.'][$action] ?? 'Done.');
    }

    public function storeAbsence(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = IncidentService::recordAbsence($this->cid(), $this->uid(), (int) $id, $request->post, CorpAuth::branchScope(self::PERM), CorpContext::locale()->today());

        return $this->back('/company/employees/' . (int) $id . '#incidents', $res,
            !empty($request->post['needs_rtw']) ? 'Absence recorded. A return-to-work check is due on the return date: book it from the due list.' : 'Absence recorded.');
    }

    public function removeAbsence(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = IncidentService::removeAbsence($this->cid(), $this->uid(), (int) $id, CorpAuth::branchScope(self::PERM));
        $emp = (int) ($request->post['employment_id'] ?? 0);

        return $this->back($emp > 0 ? '/company/employees/' . $emp . '#incidents' : '/company/incidents', $res, 'Absence removed (its return-to-work check is cancelled if it wasn\'t booked).');
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }

    private function uid(): int
    {
        return (int) (CorpContext::user()['id'] ?? 0);
    }

    /** @param array{ok: bool, error?: string} $res */
    private function back(string $url, array $res, string $okMessage): Response
    {
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $okMessage : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect($url);
    }
}
