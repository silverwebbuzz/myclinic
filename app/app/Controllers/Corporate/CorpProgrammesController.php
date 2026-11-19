<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /company/screening/library and /company/screening/programmes[/{id}] —
 * rule template library, adopting, and editing the company's programmes.
 *
 * View: screening.view_status · items: rules.customise · who it applies to: screening.manage
 */
final class CorpProgrammesController
{
    public function library(Request $request): Response
    {
        if ($deny = CorpAuth::guard('rules.customise')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $company = CorpContext::company() ?? [];
        // The company's own country plus every country it has a branch in.
        $countries = [(string) ($company['country_code'] ?? '')];
        foreach (StructureService::branches($cid, true) as $b) {
            $countries[] = (string) $b['country_code'];
        }
        $industryName = '';
        if ((int) ($company['industry_id'] ?? 0) > 0) {
            $st = Database::connection()->prepare('SELECT name FROM corp_industries WHERE id = :id');
            $st->execute(['id' => (int) $company['industry_id']]);
            $industryName = (string) ($st->fetchColumn() ?: '');
        }

        return $this->render('corporate/screening_library', [
            'templates' => ProgrammeService::library($cid, $countries, (int) ($company['industry_id'] ?? 0)),
            'industryName' => $industryName,
        ]);
    }

    public function adopt(Request $request, string $templateId): Response
    {
        if ($deny = CorpAuth::guard('rules.customise')) {
            return $deny;
        }
        $res = ProgrammeService::adopt((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $templateId);
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not adopt.');

            return Response::redirect(isset($res['id']) ? '/company/screening/programmes/' . (int) $res['id'] : '/company/screening/library');
        }
        SessionFlash::put('corp_ok', 'Template adopted. Review the checks, then choose who it applies to.');

        return Response::redirect('/company/screening/programmes/' . (int) $res['id']);
    }

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard('screening.view_status')) {
            return $deny;
        }
        $showArchived = ($request->query['archived'] ?? '') === '1';

        return $this->render('corporate/screening_programmes', [
            'programmes' => ProgrammeService::programmes((int) CorpContext::companyId(), $showArchived),
            'showArchived' => $showArchived,
            'canCustomise' => CorpAuth::can('rules.customise'),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($deny = CorpAuth::guard('rules.customise')) {
            return $deny;
        }
        $res = ProgrammeService::createOwn((int) CorpContext::companyId(), (int) CorpContext::userId(), (string) ($request->post['name'] ?? ''));
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not create.');

            return Response::redirect('/company/screening/programmes');
        }

        return Response::redirect('/company/screening/programmes/' . (int) $res['id']);
    }

    public function show(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard('screening.view_status')) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $p = ProgrammeService::find($cid, (int) $id);
        if ($p === null) {
            SessionFlash::put('corp_err', 'Programme not found.');

            return Response::redirect('/company/screening/programmes');
        }

        return $this->render('corporate/screening_programme', [
            'p' => $p,
            'items' => ProgrammeService::items($cid, (int) $id),
            'assignments' => ProgrammeService::assignments($cid, (int) $id),
            'covered' => ProgrammeService::coveredCount($cid, (int) $id),
            'update' => ProgrammeService::updateInfo($cid, $p),
            'examTypes' => ProgrammeService::examTypes(),
            'branches' => StructureService::branches($cid, true),
            'departments' => StructureService::departments($cid, true),
            'jobRoles' => StructureService::jobRoles($cid, true),
            'canCustomise' => CorpAuth::can('rules.customise'),
            'canAssign' => CorpAuth::can('screening.manage'),
            'old' => SessionFlash::pull('corp_programme_old') ?? [],
        ]);
    }

    public function settings(Request $request, string $id): Response
    {
        return $this->act('rules.customise', (int) $id, fn (int $cid, int $uid) => ProgrammeService::updateSettings($cid, $uid, (int) $id, $request->post), 'Saved.');
    }

    public function acceptUpdate(Request $request, string $id): Response
    {
        $accept = $request->post['accept'] ?? [];

        return $this->act('rules.customise', (int) $id, fn (int $cid, int $uid) => ProgrammeService::acceptUpdate($cid, $uid, (int) $id,
            (int) ($request->post['template_id'] ?? 0), is_array($accept) ? array_map('strval', $accept) : []), 'Programme updated to the new template version.');
    }

    public function addItem(Request $request, string $id): Response
    {
        $months = trim((string) ($request->post['frequency_months'] ?? ''));

        return $this->act('rules.customise', (int) $id, fn (int $cid, int $uid) => ProgrammeService::addItem($cid, $uid, (int) $id,
            (int) ($request->post['exam_type_id'] ?? 0), (string) ($request->post['category'] ?? ''), $months !== '' ? (int) $months : null), 'Check added.', '#items');
    }

    public function removeItem(Request $request, string $id, string $itemId): Response
    {
        return $this->act('rules.customise', (int) $id, fn (int $cid, int $uid) => ProgrammeService::removeItem($cid, $uid, (int) $id, (int) $itemId,
            (string) ($request->post['reason'] ?? '')), 'Check removed.', '#items');
    }

    public function frequency(Request $request, string $id, string $itemId): Response
    {
        return $this->act('rules.customise', (int) $id, fn (int $cid, int $uid) => ProgrammeService::setFrequency($cid, $uid, (int) $id, (int) $itemId,
            (int) ($request->post['frequency_months'] ?? 0)), 'Frequency updated.', '#items');
    }

    public function assign(Request $request, string $id): Response
    {
        $type = (string) ($request->post['target_type'] ?? '');
        $target = (int) ($request->post['target_' . preg_replace('/[^a-z_]/', '', $type)] ?? 0);
        if ($type === 'employee') {
            // Picked by employee no.
            $st = Database::connection()->prepare('SELECT id FROM corp_employments WHERE company_id = :cid AND employee_no = :no LIMIT 1');
            $st->execute(['cid' => (int) CorpContext::companyId(), 'no' => strtoupper(trim((string) ($request->post['target_employee_no'] ?? '')))]);
            $target = (int) ($st->fetchColumn() ?: 0);
        }

        return $this->act('screening.manage', (int) $id, fn (int $cid, int $uid) => ProgrammeService::addAssignment($cid, $uid, (int) $id, $type, $target),
            'It now applies to them. Their due dates are worked out automatically.', '#who');
    }

    public function unassign(Request $request, string $id, string $assignmentId): Response
    {
        return $this->act('screening.manage', (int) $id, fn (int $cid, int $uid) => ProgrammeService::removeAssignment($cid, $uid, (int) $id, (int) $assignmentId),
            'Removed.', '#who');
    }

    // ------------------------------------------------------------------

    /** @param callable(int, int): array{ok: bool, error?: string} $fn */
    private function act(string $perm, int $id, callable $fn, string $ok, string $anchor = ''): Response
    {
        if ($deny = CorpAuth::guard($perm)) {
            return $deny;
        }
        $res = $fn((int) CorpContext::companyId(), (int) CorpContext::userId());
        if ($res['ok']) {
            // Re-work due dates for everyone the change may affect.
            \App\Services\Corporate\ScreeningEngine::safeSyncCompany((int) CorpContext::companyId());
        }
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $ok : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/company/screening/programmes/' . $id . $anchor);
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
