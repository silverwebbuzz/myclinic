<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\Locale\CountryRepo;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/branches, /company/departments, /company/job-roles. */
final class CorpStructureController
{
    private const KINDS = [
        'branches' => ['kind' => 'branch', 'perm' => 'branches.manage', 'label' => 'Branch'],
        'departments' => ['kind' => 'department', 'perm' => 'departments.manage', 'label' => 'Department'],
        'job-roles' => ['kind' => 'job_role', 'perm' => 'jobroles.manage', 'label' => 'Job role'],
    ];

    public function branches(Request $request): Response
    {
        return $this->index('branches');
    }

    public function departments(Request $request): Response
    {
        return $this->index('departments');
    }

    public function jobRoles(Request $request): Response
    {
        return $this->index('job-roles');
    }

    public function saveBranch(Request $request): Response
    {
        return $this->save('branches', $request);
    }

    public function saveDepartment(Request $request): Response
    {
        return $this->save('departments', $request);
    }

    public function saveJobRole(Request $request): Response
    {
        return $this->save('job-roles', $request);
    }

    public function toggleBranch(Request $request, string $id): Response
    {
        return $this->toggle('branches', (int) $id);
    }

    public function toggleDepartment(Request $request, string $id): Response
    {
        return $this->toggle('departments', (int) $id);
    }

    public function toggleJobRole(Request $request, string $id): Response
    {
        return $this->toggle('job-roles', (int) $id);
    }

    // ------------------------------------------------------------------

    private function index(string $slug): Response
    {
        $cfg = self::KINDS[$slug];
        if ($deny = CorpAuth::guard($cfg['perm'])) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $data = [
            'slug' => $slug,
            'old' => SessionFlash::pull('corp_structure_old'),
        ];
        if ($slug === 'branches') {
            $company = CorpContext::company() ?? [];
            $countries = CountryRepo::activeCountries();
            // Always offer the company's own country even if it was switched off later.
            if (!in_array($company['country_code'] ?? '', array_column($countries, 'code'), true) && ($own = CountryRepo::country((string) ($company['country_code'] ?? ''))) !== null) {
                $countries[] = $own;
            }
            $regions = [];
            foreach ($countries as $c) {
                $regions[(string) $c['code']] = array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']],
                    CountryRepo::regions((string) $c['code']));
            }
            $data += [
                'rows' => StructureService::branches($cid),
                'countries' => $countries,
                'regions' => $regions,
                'types' => StructureService::BRANCH_TYPES,
                'timezones' => \DateTimeZone::listIdentifiers(),
            ];

            return $this->render('corporate/branches', $data);
        }
        $data['rows'] = $slug === 'departments' ? StructureService::departments($cid) : StructureService::jobRoles($cid);
        $data['risk'] = StructureService::RISK_LEVELS;

        return $this->render('corporate/structure_list', $data);
    }

    private function save(string $slug, Request $request): Response
    {
        $cfg = self::KINDS[$slug];
        if ($deny = CorpAuth::guard($cfg['perm'])) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $uid = (int) CorpContext::userId();
        $id = (int) ($request->post['id'] ?? 0);
        $res = match ($slug) {
            'branches' => StructureService::saveBranch($cid, $uid, $id, $request->post),
            'departments' => StructureService::saveDepartment($cid, $uid, $id, $request->post),
            'job-roles' => StructureService::saveJobRole($cid, $uid, $id, $request->post),
        };
        if (!$res['ok']) {
            SessionFlash::put('corp_structure_old', $request->post);
        }
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $cfg['label'] . ' saved.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/company/' . $slug);
    }

    private function toggle(string $slug, int $id): Response
    {
        $cfg = self::KINDS[$slug];
        if ($deny = CorpAuth::guard($cfg['perm'])) {
            return $deny;
        }
        $res = StructureService::toggle($cfg['kind'], (int) CorpContext::companyId(), (int) CorpContext::userId(), $id);
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $cfg['label'] . ' updated.' : ($res['error'] ?? 'Could not update.'));

        return Response::redirect('/company/' . $slug);
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
