<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DirectoryAdminService;
use App\Support\View;

/**
 * /admin/directory — every directory_doctors listing with Hide / Show.
 * See DirectoryAdminService for what each action does to the public site.
 */
final class DirectoryAdminController
{
    private const FILTER_KEYS = ['q', 'city', 'specialty', 'visibility', 'claimed'];

    /** GET /admin/directory */
    public function index(Request $request): Response
    {
        $filters = self::filters($request->query);
        $page = max(1, (int) ($request->query['page'] ?? 1));
        $total = DirectoryAdminService::count($filters);

        return Response::html(View::render('admin/directory', [
            'admin'      => RequestContext::superAdmin(),
            'csrf'       => CsrfService::token(),
            'rows'       => DirectoryAdminService::page($filters, $page),
            'filters'    => $filters,
            'options'    => DirectoryAdminService::filterOptions(),
            'visibility' => DirectoryAdminService::VISIBILITY,
            'page'       => $page,
            'pages'      => (int) max(1, ceil($total / DirectoryAdminService::PER_PAGE)),
            'total'      => $total,
            'message'    => (string) ($request->query['message'] ?? ''),
            'error'      => (string) ($request->query['error'] ?? ''),
        ]));
    }

    /** POST /admin/directory/{id}/hide */
    public function hide(Request $request, string $id): Response
    {
        return $this->toggle($request, (int) $id, false);
    }

    /** POST /admin/directory/{id}/show */
    public function show(Request $request, string $id): Response
    {
        return $this->toggle($request, (int) $id, true);
    }

    private function toggle(Request $request, int $id, bool $visible): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return self::back($request, ['error' => 'csrf']);
        }
        $ok = DirectoryAdminService::setVisible($id, $visible);

        return self::back($request, $ok ? ['message' => $visible ? 'shown' : 'hidden'] : ['error' => 'not_found']);
    }

    /**
     * Back to the list with the same filters/page (sent as return_qs).
     *
     * @param array<string,string> $extra
     */
    private static function back(Request $request, array $extra): Response
    {
        parse_str((string) ($request->post['return_qs'] ?? ''), $qs);
        $keep = array_intersect_key($qs, array_flip([...self::FILTER_KEYS, 'page']));
        $keep = array_filter($keep, static fn ($v) => is_string($v) && $v !== '');

        return Response::redirect('/admin/directory?' . http_build_query($keep + $extra));
    }

    /**
     * @param array<string,mixed> $q
     * @return array<string,string>
     */
    private static function filters(array $q): array
    {
        $out = [];
        foreach (self::FILTER_KEYS as $k) {
            $out[$k] = trim((string) ($q[$k] ?? ''));
        }
        if (!array_key_exists($out['visibility'], DirectoryAdminService::VISIBILITY)) {
            $out['visibility'] = '';
        }
        if (!in_array($out['claimed'], ['', '0', '1'], true)) {
            $out['claimed'] = '';
        }

        return $out;
    }
}
