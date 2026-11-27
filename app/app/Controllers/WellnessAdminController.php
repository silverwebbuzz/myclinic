<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Wellness\CreatorAdminService;
use App\Services\Wellness\CreatorService;
use App\Services\Wellness\EventService;
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
            'signupSources' => CreatorAdminService::signupSources(),
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

    // ---- Camps & events (Phase 2) ---------------------------------------------

    /** GET /admin/wellness/events?status=submitted&q= */
    public function events(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'submitted');
        $tabs = ['submitted', 'upcoming', 'ended', 'changes_requested', 'draft', 'rejected', 'unpublished', 'cancelled'];
        if (!in_array($status, $tabs, true)) {
            $status = 'submitted';
        }
        $q = trim((string) ($request->query['q'] ?? ''));

        return Response::html(View::render('admin/wellness_events', [
            'admin' => RequestContext::superAdmin(),
            'status' => $status,
            'tabs' => $tabs,
            'q' => $q,
            'counts' => EventService::statusCounts(),
            'rows' => EventService::adminList($status, $q),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** GET /admin/wellness/events/{id} */
    public function event(Request $request, string $id): Response
    {
        $e = EventService::adminFind((int) $id);
        if ($e === null) {
            return Response::redirect('/admin/wellness/events');
        }

        return Response::html(View::render('admin/wellness_event_detail', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'e' => $e,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    /** POST /admin/wellness/events/{id}/review — decision publish|changes|reject|unpublish, note */
    public function eventReview(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/wellness/events/' . (int) $id . '?error=expired');
        }
        $res = EventService::review((int) $id, (string) ($request->post['decision'] ?? ''), (string) ($request->post['note'] ?? ''), $this->adminId());

        return Response::redirect('/admin/wellness/events/' . (int) $id . '?' . ($res['ok'] ? 'message=' . urlencode((string) $request->post['decision']) : 'error=' . urlencode((string) $res['error'])));
    }

    public function eventPoster(Request $request, string $id): Response
    {
        $e = EventService::adminFind((int) $id);

        return $e === null ? Response::html('Not found', 404) : EventService::posterResponse($e);
    }

    /** GET /admin/wellness/events/new?kind= — list a camp / event as eClinicPro Desk. */
    public function eventNew(Request $request): Response
    {
        $desk = EventService::deskCreator();
        if ($desk === null) {
            return Response::html('Run the Wellness Hub Phase 1 patch first (it creates the eClinicPro Desk account).', 500);
        }
        $kind = ($request->query['kind'] ?? '') === 'event' ? 'event' : 'free_camp';
        $form = EventService::blank($kind, $desk);
        $form['contact_name'] = '';

        return $this->adminEventForm($form, null);
    }

    /** POST /admin/wellness/events/new — publishes at once under eClinicPro Desk. */
    public function eventCreate(Request $request): Response
    {
        $desk = EventService::deskCreator();
        if ($desk === null || !CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/wellness/events');
        }
        $kind = ($request->post['kind'] ?? '') === 'event' ? 'event' : 'free_camp';
        $poster = $_FILES['poster'] ?? null;
        $res = EventService::save($desk, null, $kind, $request->post, is_array($poster) ? $poster : null, true, $this->adminId() ?? 0);
        if (!$res['ok']) {
            return $this->adminEventForm($request->post + EventService::blank($kind, $desk) + ['kind' => $kind], $res['error']);
        }
        CreatorService::log((int) $desk['id'], 'event_published', 'Listed by admin', $this->adminId());

        return Response::redirect('/admin/wellness/events/' . (int) $res['id'] . '?message=publish');
    }

    /** GET /admin/wellness/reports?status=open */
    public function reports(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'open');

        return Response::html(View::render('admin/wellness_reports', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'status' => in_array($status, ['open', 'resolved', 'dismissed'], true) ? $status : 'open',
            'rows' => EventService::reports($status),
        ]));
    }

    /** POST /admin/wellness/reports/{id} — status resolved|dismissed, note */
    public function resolveReport(Request $request, string $id): Response
    {
        if (CsrfService::verify($request->post['_csrf'] ?? null)) {
            EventService::resolveReport((int) $id, (string) ($request->post['status'] ?? ''), (string) ($request->post['note'] ?? ''), $this->adminId());
        }
        $back = (string) ($request->post['return_to'] ?? '');

        return Response::redirect(preg_match('#^/admin/wellness/(reports|events/\d+)$#', $back) ? $back : '/admin/wellness/reports');
    }

    /** @param array<string,mixed> $form */
    private function adminEventForm(array $form, ?string $error): Response
    {
        return Response::html(View::render('admin/wellness_event_new', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'form' => $form,
            'categories' => EventService::categories((string) $form['kind']),
            'cities' => EventService::cities(),
            'error' => $error,
        ]));
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
