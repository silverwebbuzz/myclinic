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
            'error'             => $request->query['error'] ?? null,
        ]));
    }

    public function show(Request $request, string $id): Response
    {
        $claim = DoctorClaimService::find((int) $id);
        if ($claim === null) {
            return Response::redirect('/admin/claims?message=not_found');
        }

        return Response::html(View::render('admin/claim_detail', [
            'admin'             => RequestContext::superAdmin(),
            'csrf'              => CsrfService::token(),
            'claim'             => $claim,
            'pendingClaimCount' => DoctorClaimService::pendingCount(),
            'error'             => $request->query['error'] ?? null,
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
        $result = DoctorClaimService::approve($id, (int) $admin['id'], $notes);
        if ($result['ok']) {
            return Response::redirect('/admin/claims?message=approved');
        }
        $error = $result['error'] ?? 'Approval failed for an unknown reason.';
        return Response::redirect('/admin/claims/' . $id . '?error=' . rawurlencode($error));
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
