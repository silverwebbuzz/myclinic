<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\ProviderService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/providers — the company's clinic network and agreed prices (design §4, C1–C3). */
final class CorpProvidersController
{
    private const PERM = 'providers.manage';

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = $this->cid();
        $q = trim((string) ($request->query['q'] ?? ''));

        return $this->render('corporate/providers', [
            'links' => ProviderService::links($cid),
            'q' => $q,
            'results' => ProviderService::search($cid, $q),
            'branches' => StructureService::branches($cid, true),
        ]);
    }

    public function request(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = ProviderService::request($this->cid(), $this->uid(), (int) ($request->post['clinic_id'] ?? 0), $this->branchInput($request), (string) ($request->post['note'] ?? ''));

        return $this->back($res['ok'] ? '/company/providers' : '/company/providers?q=' . urlencode((string) ($request->post['q'] ?? '')), $res,
            'Request sent. The clinic will see it in its eClinicPro app; we\'ll email you when they answer.');
    }

    public function show(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = $this->cid();
        $link = ProviderService::find($cid, (int) $id);
        if ($link === null) {
            return Response::html('Not found', 404);
        }

        return $this->render('corporate/provider', [
            'link' => $link,
            'rates' => ProviderService::rates($cid, (int) $link['id']),
            'examTypes' => ProgrammeService::examTypes(),
            'branches' => StructureService::branches($cid, true),
            'currency' => ProviderService::companyCurrency($cid),
        ]);
    }

    public function branches(Request $request, string $id): Response
    {
        return $this->act((int) $id, fn () => ProviderService::setBranches($this->cid(), $this->uid(), (int) $id, $this->branchInput($request)), 'Branches saved.');
    }

    public function end(Request $request, string $id): Response
    {
        return $this->act((int) $id, fn () => ProviderService::end($this->cid(), $this->uid(), (int) $id, (string) ($request->post['reason'] ?? '')), 'Done.');
    }

    public function saveRate(Request $request, string $id): Response
    {
        return $this->act((int) $id, fn () => ProviderService::saveRate($this->cid(), $this->uid(), (int) $id, $request->post), 'Price saved.', '#prices');
    }

    public function removeRate(Request $request, string $id, string $rateId): Response
    {
        return $this->act((int) $id, fn () => ProviderService::removeRate($this->cid(), $this->uid(), (int) $id, (int) $rateId), 'Price removed.', '#prices');
    }

    // ------------------------------------------------------------------

    /** @return list<int>|null null = all branches */
    private function branchInput(Request $request): ?array
    {
        if (($request->post['scope'] ?? 'all') === 'all') {
            return null;
        }
        $ids = $request->post['branch_ids'] ?? [];

        return is_array($ids) ? array_map('intval', $ids) : null;
    }

    /** @param callable(): array{ok: bool, error?: string} $fn */
    private function act(int $id, callable $fn, string $ok, string $anchor = ''): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }

        return $this->back('/company/providers/' . $id . $anchor, $fn(), $ok);
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
