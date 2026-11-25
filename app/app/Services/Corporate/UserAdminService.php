<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * A company's own users: invite, accept, change roles, disable / enable,
 * reset two-step sign-in. Every action is scoped to one company and checked
 * against the actor's permissions:
 *  - you can't act on yourself here (use the Security page),
 *  - you can't act on someone whose roles carry permissions you don't hold,
 *  - the owner can't be disabled or lose Company Admin,
 *  - a company always keeps at least one active Company Admin.
 */
final class UserAdminService
{
    public const INVITE_DAYS = 7;

    /** @return list<array<string, mixed>> users + 'roles' (list of [id, name]) */
    public static function listUsers(int $companyId): array
    {
        $st = Database::connection()->prepare(
            "SELECT id, name, email, phone, status, is_owner, email_verified_at, mfa_enabled_at, last_login_at, created_at
               FROM corp_users WHERE company_id = :cid
              ORDER BY status = 'disabled', is_owner DESC, name"
        );
        $st->execute(['cid' => $companyId]);
        $users = $st->fetchAll();

        $rs = Database::connection()->prepare(
            'SELECT ur.user_id, ur.scope_id, r.id, r.name, r.scope_type, b.name AS branch_name
               FROM corp_user_roles ur JOIN corp_roles r ON r.id = ur.role_id
               LEFT JOIN corp_branches b ON b.id = ur.scope_id AND b.company_id = ur.company_id
              WHERE ur.company_id = :cid ORDER BY r.sort_order, r.name'
        );
        $rs->execute(['cid' => $companyId]);
        $byUser = [];
        foreach ($rs->fetchAll() as $row) {
            $byUser[(int) $row['user_id']][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'] . ($row['scope_type'] === 'branch' && $row['branch_name'] ? ' · ' . $row['branch_name'] : ''),
                'scope_id' => (int) $row['scope_id'],
            ];
        }
        foreach ($users as &$u) {
            $u['roles'] = $byUser[(int) $u['id']] ?? [];
        }
        unset($u);

        return $users;
    }

    /** @return list<array<string, mixed>> invitations not yet accepted or cancelled */
    public static function pendingInvites(int $companyId): array
    {
        $st = Database::connection()->prepare(
            'SELECT i.id, i.email, i.name, i.role_id, i.expires_at, i.created_at, r.name AS role_name, u.name AS invited_by_name
               FROM corp_invitations i
               JOIN corp_roles r ON r.id = i.role_id
               LEFT JOIN corp_users u ON u.id = i.invited_by AND u.company_id = i.company_id
              WHERE i.company_id = :cid AND i.accepted_at IS NULL AND i.revoked_at IS NULL
              ORDER BY i.created_at DESC'
        );
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------
    // Invitations
    // ------------------------------------------------------------------

    /**
     * @param array<string, true> $actorPerms
     * @return array{ok: bool, error?: string}
     */
    public static function invite(int $companyId, int $actorId, array $actorPerms, string $email, string $name, int $roleId, int $branchId = 0): array
    {
        $email = strtolower(trim($email));
        $name = trim($name);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return ['ok' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (mb_strlen($name) > 160) {
            return ['ok' => false, 'error' => 'Name is too long.'];
        }
        $role = RoleService::findRole($companyId, $roleId);
        if ($role === null || !RoleService::canAssign($role, $actorPerms)) {
            return ['ok' => false, 'error' => 'Please choose a role you\'re allowed to give.'];
        }
        $scopeId = self::scopeFor($companyId, $role, $branchId);
        if ($scopeId === null) {
            return ['ok' => false, 'error' => 'Choose which branch this ' . $role['name'] . ' looks after.'];
        }
        $existing = CompanyService::findUserByEmail($email);
        if ($existing !== null) {
            return ['ok' => false, 'error' => (int) $existing['company_id'] === $companyId
                ? 'This person already has a login for your company.'
                : 'This email already has an eClinicPro company login. Please use a different work email.'];
        }
        $st = Database::connection()->prepare(
            'SELECT id FROM corp_invitations WHERE company_id = :cid AND email = :email AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > :now LIMIT 1'
        );
        $st->execute(['cid' => $companyId, 'email' => $email, 'now' => LocalDate::nowUtc()]);
        if ($st->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'This person already has a pending invitation. Use "Resend" in the list below.'];
        }

        $token = bin2hex(random_bytes(32));
        Database::connection()->prepare(
            'INSERT INTO corp_invitations (company_id, email, name, role_id, scope_id, token_hash, invited_by, expires_at, created_at)
             VALUES (:cid, :email, :name, :rid, :scope, :hash, :by, :exp, :at)'
        )->execute([
            'cid' => $companyId,
            'email' => $email,
            'name' => $name !== '' ? $name : null,
            'rid' => $roleId,
            'scope' => $scopeId,
            'hash' => hash('sha256', $token),
            'by' => $actorId,
            'exp' => LocalDate::utcPlusSeconds(self::INVITE_DAYS * 86400),
            'at' => LocalDate::nowUtc(),
        ]);
        $inviteId = (int) Database::connection()->lastInsertId();
        self::sendInviteEmail($companyId, $actorId, $email, $name, (string) $role['name'], $token);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'user_invited', 'corp_invitation', $inviteId, ['email' => $email, 'role' => $role['name']]);

        return ['ok' => true];
    }

    /** New link + new 7 days; the old link stops working. */
    public static function resendInvite(int $companyId, int $actorId, int $inviteId): array
    {
        $inv = self::findInvite($companyId, $inviteId);
        if ($inv === null) {
            return ['ok' => false, 'error' => 'That invitation was already accepted or cancelled.'];
        }
        $token = bin2hex(random_bytes(32));
        Database::connection()->prepare(
            'UPDATE corp_invitations SET token_hash = :hash, expires_at = :exp WHERE id = :id AND company_id = :cid'
        )->execute([
            'hash' => hash('sha256', $token),
            'exp' => LocalDate::utcPlusSeconds(self::INVITE_DAYS * 86400),
            'id' => $inviteId,
            'cid' => $companyId,
        ]);
        $role = RoleService::findRole($companyId, (int) $inv['role_id']);
        self::sendInviteEmail($companyId, $actorId, (string) $inv['email'], (string) ($inv['name'] ?? ''), (string) ($role['name'] ?? ''), $token);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'invite_resent', 'corp_invitation', $inviteId, ['email' => $inv['email']]);

        return ['ok' => true];
    }

    public static function revokeInvite(int $companyId, int $actorId, int $inviteId): array
    {
        $st = Database::connection()->prepare(
            'UPDATE corp_invitations SET revoked_at = :t WHERE id = :id AND company_id = :cid AND accepted_at IS NULL AND revoked_at IS NULL'
        );
        $st->execute(['t' => LocalDate::nowUtc(), 'id' => $inviteId, 'cid' => $companyId]);
        if ($st->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'That invitation was already accepted or cancelled.'];
        }
        CorpAudit::log($companyId, 'corp_user', $actorId, 'invite_revoked', 'corp_invitation', $inviteId);

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null a still-valid invitation (+ company row) for a token */
    public static function findInviteByToken(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $st = Database::connection()->prepare(
            'SELECT * FROM corp_invitations
              WHERE token_hash = :h AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > :now LIMIT 1'
        );
        $st->execute(['h' => hash('sha256', $token), 'now' => LocalDate::nowUtc()]);
        $inv = $st->fetch();
        if (!$inv) {
            return null;
        }
        $company = CompanyService::find((int) $inv['company_id']);
        if (CorpAuthService::companyBlockMessage($company) !== null) {
            return null;
        }
        $inv['company'] = $company;

        return $inv;
    }

    /**
     * The invited person sets their name + password. Their email is proven by
     * the link, so it's marked verified.
     *
     * @return array{ok: bool, error?: string, company_id?: int, user_id?: int}
     */
    public static function acceptInvite(string $token, string $name, string $password, string $confirm): array
    {
        $inv = self::findInviteByToken($token);
        if ($inv === null) {
            return ['ok' => false, 'error' => 'This invitation has expired or was cancelled. Ask your company admin to send a new one.'];
        }
        $name = trim($name);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            return ['ok' => false, 'error' => 'Please enter your full name.'];
        }
        $policy = CorpPasswordPolicy::check($password, $confirm, (string) $inv['email'], $name);
        if ($policy !== null) {
            return ['ok' => false, 'error' => $policy];
        }
        if (CompanyService::findUserByEmail((string) $inv['email']) !== null) {
            return ['ok' => false, 'error' => 'This email already has an eClinicPro company login. Please sign in instead.'];
        }

        $companyId = (int) $inv['company_id'];
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $claim = $pdo->prepare('UPDATE corp_invitations SET accepted_at = :t WHERE id = :id AND accepted_at IS NULL AND revoked_at IS NULL');
            $claim->execute(['t' => $now, 'id' => (int) $inv['id']]);
            if ($claim->rowCount() !== 1) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'This invitation was already used.'];
            }
            $pdo->prepare(
                'INSERT INTO corp_users (company_id, name, email, password_hash, status, is_owner, email_verified_at, created_at, updated_at)
                 VALUES (:cid, :name, :email, :hash, \'active\', 0, :verified, :created, :updated)'
            )->execute([
                'cid' => $companyId,
                'name' => $name,
                'email' => (string) $inv['email'],
                'hash' => CorpPasswordPolicy::hash($password),
                'verified' => $now,
                'created' => $now,
                'updated' => $now,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO corp_user_roles (company_id, user_id, role_id, scope_id, granted_by, created_at)
                 VALUES (:cid, :uid, :rid, :scope, :by, :at)'
            )->execute([
                'cid' => $companyId,
                'uid' => $userId,
                'rid' => (int) $inv['role_id'],
                'scope' => (int) $inv['scope_id'],
                'by' => (int) $inv['invited_by'],
                'at' => $now,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[UserAdminService::acceptInvite] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not create your login. Please try again.'];
        }
        CorpAudit::log($companyId, 'corp_user', $userId, 'invite_accepted', 'corp_invitation', (int) $inv['id']);

        return ['ok' => true, 'company_id' => $companyId, 'user_id' => $userId];
    }

    // ------------------------------------------------------------------
    // Existing users
    // ------------------------------------------------------------------

    /**
     * Replace a user's roles.
     *
     * @param list<int> $roleIds
     * @param array<string, true> $actorPerms
     * @return array{ok: bool, error?: string}
     */
    public static function setRoles(int $companyId, int $actorId, array $actorPerms, int $userId, array $roleIds, array $branchByRole = []): array
    {
        $target = self::guardTarget($companyId, $actorId, $actorPerms, $userId);
        if (isset($target['error'])) {
            return ['ok' => false, 'error' => $target['error']];
        }
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return ['ok' => false, 'error' => 'Everyone needs at least one role. To stop someone signing in, disable them instead.'];
        }
        $names = [];
        $scopes = [];
        foreach ($roleIds as $rid) {
            $role = RoleService::findRole($companyId, $rid);
            if ($role === null || !RoleService::canAssign($role, $actorPerms)) {
                return ['ok' => false, 'error' => 'You can only give roles whose permissions you have yourself.'];
            }
            $scope = self::scopeFor($companyId, $role, (int) ($branchByRole[$rid] ?? 0));
            if ($scope === null) {
                return ['ok' => false, 'error' => 'Choose which branch the ' . $role['name'] . ' role covers.'];
            }
            $scopes[$rid] = $scope;
            $names[] = $role['name'] . ($scope > 0 ? ' (branch #' . $scope . ')' : '');
        }
        $adminRole = CompanyService::systemRoleId('company_admin');
        $keepsAdmin = in_array($adminRole, $roleIds, true);
        if ((int) $target['is_owner'] === 1 && !$keepsAdmin) {
            return ['ok' => false, 'error' => 'The account owner must stay a Company Admin.'];
        }
        if (!$keepsAdmin && self::isActiveAdmin($companyId, $userId) && self::activeAdminCount($companyId) <= 1) {
            return ['ok' => false, 'error' => 'Your company needs at least one active Company Admin.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM corp_user_roles WHERE company_id = :cid AND user_id = :uid')->execute(['cid' => $companyId, 'uid' => $userId]);
            $ins = $pdo->prepare(
                'INSERT INTO corp_user_roles (company_id, user_id, role_id, scope_id, granted_by, created_at) VALUES (:cid, :uid, :rid, :scope, :by, :at)'
            );
            foreach ($roleIds as $rid) {
                $ins->execute(['cid' => $companyId, 'uid' => $userId, 'rid' => $rid, 'scope' => $scopes[$rid], 'by' => $actorId, 'at' => LocalDate::nowUtc()]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[UserAdminService::setRoles] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save the roles. Please try again.'];
        }
        CorpAudit::log($companyId, 'corp_user', $actorId, 'roles_changed', 'corp_user', $userId, ['roles' => $names]);

        return ['ok' => true];
    }

    /**
     * @param array<string, true> $actorPerms
     * @return array{ok: bool, error?: string}
     */
    public static function setActive(int $companyId, int $actorId, array $actorPerms, int $userId, bool $active): array
    {
        $target = self::guardTarget($companyId, $actorId, $actorPerms, $userId);
        if (isset($target['error'])) {
            return ['ok' => false, 'error' => $target['error']];
        }
        if (!$active) {
            if ((int) $target['is_owner'] === 1) {
                return ['ok' => false, 'error' => 'The account owner can\'t be disabled.'];
            }
            if (self::isActiveAdmin($companyId, $userId) && self::activeAdminCount($companyId) <= 1) {
                return ['ok' => false, 'error' => 'Your company needs at least one active Company Admin.'];
            }
        }
        Database::connection()->prepare('UPDATE corp_users SET status = :s, updated_at = :t WHERE id = :id AND company_id = :cid')
            ->execute(['s' => $active ? 'active' : 'disabled', 't' => LocalDate::nowUtc(), 'id' => $userId, 'cid' => $companyId]);
        if (!$active) {
            CorpSessionService::revokeAllForUser($userId);
        }
        CorpAudit::log($companyId, 'corp_user', $actorId, $active ? 'user_enabled' : 'user_disabled', 'corp_user', $userId);

        return ['ok' => true];
    }

    /**
     * Clear someone's two-step sign-in (lost phone + lost backup codes). They
     * set it up again at their next sign-in; all their sessions end now.
     *
     * @param array<string, true> $actorPerms
     * @return array{ok: bool, error?: string}
     */
    public static function resetMfa(int $companyId, int $actorId, array $actorPerms, int $userId): array
    {
        $target = self::guardTarget($companyId, $actorId, $actorPerms, $userId);
        if (isset($target['error'])) {
            return ['ok' => false, 'error' => $target['error']];
        }
        if (empty($target['mfa_enabled_at'])) {
            return ['ok' => false, 'error' => 'This person hasn\'t set up two-step sign-in yet.'];
        }
        Database::connection()->prepare(
            'UPDATE corp_users SET mfa_secret_enc = NULL, mfa_enabled_at = NULL, mfa_last_step = NULL, mfa_recovery = NULL, updated_at = :t
              WHERE id = :id AND company_id = :cid'
        )->execute(['t' => LocalDate::nowUtc(), 'id' => $userId, 'cid' => $companyId]);
        CorpSessionService::revokeAllForUser($userId);
        CorpAudit::log($companyId, 'corp_user', $actorId, 'mfa_reset_by_admin', 'corp_user', $userId);

        $actor = CompanyService::findUser($actorId);
        CorpMailer::send(
            (string) $target['email'],
            'Your two-step sign-in was reset',
            'Your two-step sign-in was reset',
            [
                'Hi ' . (trim((string) strtok((string) $target['name'], ' ')) ?: 'there') . ',',
                ($actor['name'] ?? 'Your company admin') . ' reset two-step sign-in on your eClinicPro company login. You have been signed out everywhere.',
                'Next time you sign in, you\'ll be asked to scan a new QR code with your authenticator app.',
                'If you didn\'t ask for this, contact your company admin straight away.',
            ],
            CorpMailer::appUrl() . '/company/login',
            'Sign in',
        );

        return ['ok' => true];
    }

    // ------------------------------------------------------------------

    /**
     * The target user, or ['error' => …] when the actor may not manage them.
     *
     * @param array<string, true> $actorPerms
     * @return array<string, mixed>
     */
    private static function guardTarget(int $companyId, int $actorId, array $actorPerms, int $userId): array
    {
        if ($userId === $actorId) {
            return ['error' => 'You can\'t change your own access here. Ask another Company Admin.'];
        }
        $st = Database::connection()->prepare('SELECT * FROM corp_users WHERE id = :id AND company_id = :cid LIMIT 1');
        $st->execute(['id' => $userId, 'cid' => $companyId]);
        $target = $st->fetch();
        if (!$target) {
            return ['error' => 'User not found.'];
        }
        $targetPerms = array_keys(CorpAuth::permissionsFor($companyId, $userId));
        if (!RoleService::isWithin($targetPerms, $actorPerms, true)) {
            return ['error' => 'This person has access you don\'t have, so you can\'t change their account.'];
        }

        return $target;
    }

    private static function isActiveAdmin(int $companyId, int $userId): bool
    {
        $st = Database::connection()->prepare(
            "SELECT 1 FROM corp_user_roles ur
               JOIN corp_roles r ON r.id = ur.role_id AND r.company_id = 0 AND r.code = 'company_admin'
               JOIN corp_users u ON u.id = ur.user_id AND u.company_id = ur.company_id AND u.status = 'active'
              WHERE ur.company_id = :cid AND ur.user_id = :uid LIMIT 1"
        );
        $st->execute(['cid' => $companyId, 'uid' => $userId]);

        return $st->fetchColumn() !== false;
    }

    private static function activeAdminCount(int $companyId): int
    {
        $st = Database::connection()->prepare(
            "SELECT COUNT(DISTINCT ur.user_id) FROM corp_user_roles ur
               JOIN corp_roles r ON r.id = ur.role_id AND r.company_id = 0 AND r.code = 'company_admin'
               JOIN corp_users u ON u.id = ur.user_id AND u.company_id = ur.company_id AND u.status = 'active'
              WHERE ur.company_id = :cid"
        );
        $st->execute(['cid' => $companyId]);

        return (int) $st->fetchColumn();
    }

    /**
     * scope_id for a role: 0 for company-wide roles; a valid active branch of
     * the company for branch roles; null when a branch role has no valid branch.
     *
     * @param array<string, mixed> $role
     */
    private static function scopeFor(int $companyId, array $role, int $branchId): ?int
    {
        if (($role['scope_type'] ?? 'company') !== 'branch') {
            return 0;
        }
        if ($branchId <= 0) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT 1 FROM corp_branches WHERE id = :id AND company_id = :cid AND is_active = 1 LIMIT 1');
        $st->execute(['id' => $branchId, 'cid' => $companyId]);

        return $st->fetchColumn() !== false ? $branchId : null;
    }

    /** @return array<string, mixed>|null */
    private static function findInvite(int $companyId, int $inviteId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT * FROM corp_invitations WHERE id = :id AND company_id = :cid AND accepted_at IS NULL AND revoked_at IS NULL LIMIT 1'
        );
        $st->execute(['id' => $inviteId, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    private static function sendInviteEmail(int $companyId, int $actorId, string $email, string $name, string $roleName, string $token): void
    {
        $company = CompanyService::find($companyId);
        $actor = CompanyService::findUser($actorId);
        $companyName = (string) (($company['trade_name'] ?? '') ?: ($company['legal_name'] ?? 'your company'));
        CorpMailer::send(
            $email,
            ($actor['name'] ?? 'Your colleague') . ' invited you to ' . $companyName . ' on eClinicPro',
            'You\'re invited to ' . $companyName,
            [
                'Hi ' . (trim((string) strtok($name, ' ')) ?: 'there') . ',',
                ($actor['name'] ?? 'A colleague') . ' has invited you to manage workforce health for ' . $companyName . ' on eClinicPro Workforce Health, as ' . ($roleName !== '' ? $roleName : 'a team member') . '.',
                'Choose a password to get started. The link expires in ' . self::INVITE_DAYS . ' days. You\'ll also set up two-step sign-in with an authenticator app on your phone.',
            ],
            CorpMailer::appUrl() . '/company/invite?token=' . $token,
            'Accept invitation',
        );
    }
}
