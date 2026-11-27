<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Wellness\CreatorAdminService;
use App\Services\Wellness\CreatorService;
use App\Support\View;

/**
 * /admin/wellness/creators — Health Creator applications, per-type permissions,
 * Verified Doctor / Organisation badge, suspend. (Wellness Hub, Phase 1.)
 */
final class WellnessAdminController
{
    /** GET /admin/wellness/creators?tab=applications&q= */
    public function creators(Request $request): Response
    {
        $tab = (string) ($request->query['tab'] ?? 'applications');
        if (!isset(CreatorAdminService::TABS[$tab])) {
            $tab = 'applications';
        }
        $q = trim((string) ($request->query['q'] ?? ''));

        return Response::html(View::render('admin/wellness_creators', [
            'admin' => RequestContext::superAdmin(),
            'tab' => $tab,
            'q' => $q,
            'counts' => CreatorAdminService::tabCounts(),
            'rows' => CreatorAdminService::list($tab, $q),
        ]));
    }

    /** GET /admin/wellness/creators/{id} */
    public function show(Request $request, string $id): Response
    {
        $c = CreatorAdminService::detail((int) $id);
        if ($c === null) {
            return Response::redirect('/admin/wellness/creators');
        }

        return Response::html(View::render('admin/wellness_creator_detail', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'c' => $c,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    /** POST /admin/wellness/creators/{id}/permission — content_type, decision, note */
    public function permission(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return $this->back($id, 'error=expired');
        }
        $res = CreatorAdminService::decidePermission(
            (int) $id,
            (string) ($request->post['content_type'] ?? ''),
            (string) ($request->post['decision'] ?? ''),
            (string) ($request->post['note'] ?? ''),
            $this->adminId(),
        );

        return $this->back($id, $res['ok'] ? 'message=permission_saved' : 'error=' . urlencode((string) $res['error']));
    }

    /** POST /admin/wellness/creators/{id}/verify — decision verify|reject|revoke, register, note */
    public function verify(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return $this->back($id, 'error=expired');
        }
        $res = CreatorAdminService::decideVerification(
            (int) $id,
            (string) ($request->post['decision'] ?? ''),
            (string) ($request->post['note'] ?? ''),
            (string) ($request->post['register'] ?? ''),
            $this->adminId(),
        );

        return $this->back($id, $res['ok'] ? 'message=verification_saved' : 'error=' . urlencode((string) $res['error']));
    }

    /** POST /admin/wellness/creators/{id}/status — active|suspended, reason */
    public function status(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return $this->back($id, 'error=expired');
        }
        $res = CreatorAdminService::setStatus(
            (int) $id,
            (string) ($request->post['status'] ?? ''),
            (string) ($request->post['reason'] ?? ''),
            $this->adminId(),
        );

        return $this->back($id, $res['ok'] ? 'message=status_saved' : 'error=' . urlencode((string) $res['error']));
    }

    /** POST /admin/wellness/creators/{id}/note — admin_note, is_trusted */
    public function note(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return $this->back($id, 'error=expired');
        }
        CreatorAdminService::saveNote((int) $id, (string) ($request->post['admin_note'] ?? ''), !empty($request->post['is_trusted']), $this->adminId());

        return $this->back($id, 'message=note_saved');
    }

    /** GET /admin/wellness/documents/{id} — private proof file. */
    public function document(Request $request, string $id): Response
    {
        $doc = CreatorAdminService::document((int) $id);

        return $doc === null ? Response::html('Not found', 404) : CreatorService::documentResponse($doc);
    }

    /** GET /admin/wellness/creators/{id}/avatar */
    public function avatar(Request $request, string $id): Response
    {
        $c = CreatorService::find((int) $id);

        return $c === null ? Response::html('Not found', 404) : CreatorService::avatarResponse($c);
    }

    private function back(string $id, string $query): Response
    {
        return Response::redirect('/admin/wellness/creators/' . (int) $id . '?' . $query);
    }

    private function adminId(): ?int
    {
        $admin = RequestContext::superAdmin();

        return isset($admin['id']) ? (int) $admin['id'] : null;
    }
}
