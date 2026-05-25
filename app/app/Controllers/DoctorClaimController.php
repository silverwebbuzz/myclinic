<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DoctorClaimService;
use App\Support\View;

/**
 * Admin-side moderation of doctor claim + new-listing requests.
 * Mounted under /admin/claims (superadmin middleware).
 */
final class DoctorClaimController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/claims', [
            'admin'             => RequestContext::superAdmin(),
            'csrf'              => CsrfService::token(),
            'claims'            => DoctorClaimService::pending(),
            'pendingClaimCount' => DoctorClaimService::pendingCount(),
            'message'           => $request->query['message'] ?? null,
        ]));
    }

    public function show(Request $request): Response
    {
        $id = (int) ($request->route['id'] ?? 0);
        $claim = DoctorClaimService::find($id);
        if ($claim === null) {
            return Response::redirect('/admin/claims?message=not_found');
        }

        return Response::html(View::render('admin/claim_detail', [
            'admin'             => RequestContext::superAdmin(),
            'csrf'              => CsrfService::token(),
            'claim'             => $claim,
            'pendingClaimCount' => DoctorClaimService::pendingCount(),
        ]));
    }

    public function approve(Request $request): Response
    {
        $admin = RequestContext::superAdmin();
        $id    = (int) ($request->post['claim_id'] ?? 0);
        $notes = trim((string) ($request->post['notes'] ?? '')) ?: null;
        if ($admin === null || $id <= 0) {
            return Response::redirect('/admin/claims?message=invalid');
        }
        $userId = DoctorClaimService::approve($id, (int) $admin['id'], $notes);
        $msg = $userId !== null ? 'approved' : 'approve_failed';
        return Response::redirect('/admin/claims?message=' . $msg);
    }

    public function reject(Request $request): Response
    {
        $admin = RequestContext::superAdmin();
        $id    = (int) ($request->post['claim_id'] ?? 0);
        $notes = trim((string) ($request->post['notes'] ?? '')) ?: null;
        if ($admin === null || $id <= 0) {
            return Response::redirect('/admin/claims?message=invalid');
        }
        DoctorClaimService::reject($id, (int) $admin['id'], $notes);
        return Response::redirect('/admin/claims?message=rejected');
    }

    public function markDuplicate(Request $request): Response
    {
        $admin = RequestContext::superAdmin();
        $id    = (int) ($request->post['claim_id'] ?? 0);
        $notes = trim((string) ($request->post['notes'] ?? '')) ?: null;
        if ($admin === null || $id <= 0) {
            return Response::redirect('/admin/claims?message=invalid');
        }
        DoctorClaimService::markDuplicate($id, (int) $admin['id'], $notes);
        return Response::redirect('/admin/claims?message=duplicate');
    }
}
