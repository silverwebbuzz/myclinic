<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\HealthTipAdminService;
use App\Support\View;

/**
 * /admin/health-tips — review queue for contributor posts (recipes, exercises,
 * quick tips) + contributor access management.
 *
 * Nothing a contributor writes is public until it is published here.
 */
final class HealthTipAdminController
{
    /** GET /admin/health-tips?status=submitted */
    public function index(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'submitted');
        if (!isset(HealthTipAdminService::STATUSES[$status])) {
            $status = 'submitted';
        }
        $page = max(1, (int) ($request->query['page'] ?? 1));

        return Response::html(View::render('admin/health_tips', [
            'admin' => RequestContext::superAdmin(),
            'status' => $status,
            'counts' => HealthTipAdminService::statusCounts(),
            'rows' => HealthTipAdminService::listByStatus($status, $page),
            'page' => $page,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** GET /admin/health-tips/{id} — full preview + decision form. */
    public function show(Request $request, string $id): Response
    {
        $tip = HealthTipAdminService::find((int) $id);
        if ($tip === null) {
            return Response::redirect('/admin/health-tips?message=not_found');
        }

        return Response::html(View::render('admin/health_tip_detail', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'tip' => $tip,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    /** POST /admin/health-tips/{id}/review */
    public function review(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/health-tips/' . (int) $id);
        }
        $admin = RequestContext::superAdmin();
        $decision = (string) ($request->post['decision'] ?? '');
        $res = HealthTipAdminService::review(
            (int) $id,
            $decision,
            (string) ($request->post['note'] ?? ''),
            isset($admin['id']) ? (int) $admin['id'] : null,
        );
        if (!$res['ok']) {
            return Response::redirect('/admin/health-tips/' . (int) $id . '?error=' . urlencode((string) $res['error']));
        }
        $done = ['publish' => 'published', 'changes' => 'changes_requested', 'reject' => 'rejected', 'unpublish' => 'unpublished'];
        return Response::redirect('/admin/health-tips/' . (int) $id . '?message=' . ($done[$decision] ?? 'saved'));
    }

    /** GET /admin/health-tips/media/{id} — stream a post photo for review. */
    public function media(Request $request, string $id): Response
    {
        $file = HealthTipAdminService::mediaFile((int) $id);
        if ($file === null) {
            return Response::html('Not found', 404);
        }
        return new Response((string) file_get_contents($file['path']), 200, [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /admin/health-tips/contributors?phone= */
    public function contributors(Request $request): Response
    {
        $phone = trim((string) ($request->query['phone'] ?? ''));

        return Response::html(View::render('admin/health_tip_contributors', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'contributors' => HealthTipAdminService::contributors(),
            'phone' => $phone,
            'matches' => $phone !== '' ? HealthTipAdminService::findIdentitiesByPhone($phone) : null,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    /** POST /admin/health-tips/contributors/grant — identity_id (+ optional note, return_to). */
    public function grant(Request $request): Response
    {
        $back = (string) ($request->post['return_to'] ?? '');
        $back = preg_match('#^/admin/(signups/\d+|health-tips/contributors)$#', $back) ? $back : '/admin/health-tips/contributors';
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect($back);
        }
        $admin = RequestContext::superAdmin();
        $res = HealthTipAdminService::grant(
            (int) ($request->post['identity_id'] ?? 0),
            isset($request->post['admin_note']) ? (string) $request->post['admin_note'] : null,
            isset($admin['id']) ? (int) $admin['id'] : null,
        );
        return Response::redirect($back . '?' . ($res['ok'] ? 'message=contributor_granted' : 'error=' . urlencode((string) $res['error'])));
    }

    /** POST /admin/health-tips/contributors/{id}/status — active | suspended */
    public function setStatus(Request $request, string $id): Response
    {
        $back = (string) ($request->post['return_to'] ?? '');
        $back = preg_match('#^/admin/(signups/\d+|health-tips/contributors)$#', $back) ? $back : '/admin/health-tips/contributors';
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect($back);
        }
        $status = (string) ($request->post['status'] ?? '');
        HealthTipAdminService::setStatus((int) $id, $status);
        return Response::redirect($back . '?message=' . ($status === 'suspended' ? 'contributor_suspended' : 'contributor_activated'));
    }
}
