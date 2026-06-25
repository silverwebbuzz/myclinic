<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\SeatService;
use App\Services\StaffInvitationService;
use App\Support\Layout;
use App\Support\SessionFlash;

final class TeamSettingsController
{
    public function index(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $passwordFlash = SessionFlash::pull('staff_password_flash');
        if (!is_array($passwordFlash)) {
            $passwordFlash = null;
        }

        return Response::html(Layout::page('settings/team', [
            'csrf' => \App\Services\CsrfService::token(),
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
            'staff' => StaffInvitationService::staffList($clinicId),
            'invitations' => StaffInvitationService::listForClinic($clinicId),
            'seatUsage' => SeatService::getSeatUsage($clinicId),
            'passwordFlash' => $passwordFlash,
            'loginUrl' => rtrim($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com', '/') . '/login',
        ], 'Team'));
    }

    public function inviteStaff(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        try {
            StaffInvitationService::invite(
                $clinicId,
                trim((string) ($request->post['name'] ?? '')),
                strtolower(trim((string) ($request->post['email'] ?? ''))),
                (string) ($request->post['role'] ?? 'receptionist'),
            );
            AuditService::log($request, 'INSERT', 'staff_invitations', 0);

            return Response::redirect('/settings/team?message=invited');
        } catch (\Throwable $e) {
            return Response::redirect('/settings/team?error=' . urlencode($e->getMessage()));
        }
    }

    public function createStaffAccount(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        try {
            $result = StaffInvitationService::createAccount(
                $clinicId,
                trim((string) ($request->post['name'] ?? '')),
                trim((string) ($request->post['login_id'] ?? '')) ?: null,
                (string) ($request->post['role'] ?? 'receptionist'),
            );
            AuditService::log($request, 'INSERT', 'users', (int) $result['user_id']);
            SessionFlash::put('staff_password_flash', [
                'user_id' => (int) $result['user_id'],
                'username' => $result['username'],
                'password' => $result['password'],
            ]);

            return Response::redirect('/settings/team?message=created');
        } catch (\Throwable $e) {
            return Response::redirect('/settings/team?error=' . urlencode($e->getMessage()));
        }
    }

    public function resetStaffPassword(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        try {
            $result = StaffInvitationService::resetStaffPassword($clinicId, (int) $id);
            AuditService::log($request, 'UPDATE', 'users', (int) $id);
            SessionFlash::put('staff_password_flash', [
                'user_id' => (int) $id,
                'password' => $result['password'],
            ]);

            return Response::redirect('/settings/team?message=password_reset');
        } catch (\Throwable $e) {
            return Response::redirect('/settings/team?error=' . urlencode($e->getMessage()));
        }
    }

    public function revokeInvite(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId !== null) {
            StaffInvitationService::revoke($clinicId, (int) $id);
        }

        return Response::redirect('/settings/team?message=revoked');
    }

    public function updateStaff(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $perms = [];
        if (!empty($request->post['permissions']) && is_array($request->post['permissions'])) {
            $perms = $request->post['permissions'];
        }

        StaffInvitationService::updateStaff($clinicId, (int) $id, [
            'role' => $request->post['role'] ?? null,
            'custom_permissions' => $perms,
            'is_active' => isset($request->post['is_active']) ? 1 : 0,
        ]);
        AuditService::log($request, 'UPDATE', 'users', (int) $id);

        return Response::redirect('/settings/team?message=updated');
    }
}
