<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\WordPressSettings;
use PDO;

final class WordPressDoctorService
{
    /** @return list<array<string, mixed>> */
    public static function doctorsForAdmin(string $search = '', int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $search = trim($search);

        $pdo = Database::connection();
        $params = [];
        $where = 'u.is_active = 1 AND u.role IN (\'doctor\', \'admin\')';

        if ($search !== '') {
            $where .= ' AND (u.name LIKE :q1 OR u.email LIKE :q2 OR t.name LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM users u
             JOIN tenants t ON t.id = u.clinic_id
             WHERE {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_owner, u.clinic_id,
                    t.name AS clinic_name,
                    wdl.id AS link_id, wdl.wp_user_id, wdl.wp_username, wdl.status AS wp_status,
                    wdl.created_at AS wp_linked_at
             FROM users u
             JOIN tenants t ON t.id = u.clinic_id
             LEFT JOIN wordpress_doctor_links wdl ON wdl.user_id = u.id AND wdl.status = 'active'
             WHERE {$where}
             ORDER BY wdl.id IS NULL DESC, u.name ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'doctors' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return array{ok: bool, error?: string, link?: array<string, mixed>} */
    public static function grantAccess(int $userId, int $adminId): array
    {
        if (!WordPressSettings::isConfigured()) {
            return ['ok' => false, 'error' => 'WordPress is not configured. Set WORDPRESS_* in .env.'];
        }

        $user = QueryBuilder::table('users')
            ->where('id', '=', $userId)
            ->where('is_active', '=', 1)
            ->first();

        if ($user === null || !in_array((string) ($user['role'] ?? ''), ['doctor', 'admin'], true)) {
            return ['ok' => false, 'error' => 'Doctor not found.'];
        }

        $existing = QueryBuilder::table('wordpress_doctor_links')
            ->where('user_id', '=', $userId)
            ->first();

        if ($existing !== null && ($existing['status'] ?? '') === 'active') {
            return ['ok' => false, 'error' => 'This doctor already has WordPress access.'];
        }

        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'doctor-' . $userId . '@authors.eclinicpro.internal';
        }

        $displayName = trim((string) ($user['name'] ?? 'Doctor'));
        $username = self::uniqueUsername($displayName, $userId);
        $password = bin2hex(random_bytes(16));

        $created = WordPressService::createOrLinkAuthor($username, $email, $displayName, $password);
        if (!$created['ok']) {
            return [
                'ok' => false,
                'error' => (string) ($created['error'] ?? 'Could not create WordPress user. Check API credentials and permissions.'),
            ];
        }

        $wpUserId = (int) ($created['wp_user_id'] ?? 0);
        $existingLink = QueryBuilder::table('wordpress_doctor_links')
            ->where('wp_user_id', '=', $wpUserId)
            ->where('status', '=', 'active')
            ->first();
        if ($existingLink !== null && (int) ($existingLink['user_id'] ?? 0) !== $userId) {
            return [
                'ok' => false,
                'error' => 'This WordPress account is already linked to another doctor.',
            ];
        }

        $bridgePlain = WordPressService::generateBridgeToken();
        $directoryDoctorId = self::resolveDirectoryDoctorId((int) $user['clinic_id'], $displayName);

        $linkData = [
            'user_id' => $userId,
            'clinic_id' => (int) $user['clinic_id'],
            'directory_doctor_id' => $directoryDoctorId,
            'wp_user_id' => $wpUserId,
            'wp_username' => (string) ($created['wp_username'] ?? $username),
            'wp_email' => (string) ($created['wp_email'] ?? $email),
            'bridge_token_hash' => WordPressService::hashBridgeToken($bridgePlain),
            'linked_by_admin_id' => $adminId > 0 ? $adminId : null,
            'status' => 'active',
        ];

        if ($existing !== null && ($existing['status'] ?? '') === 'revoked') {
            QueryBuilder::table('wordpress_doctor_links')
                ->where('id', '=', (int) $existing['id'])
                ->update($linkData);
            $linkId = (int) $existing['id'];
        } else {
            QueryBuilder::table('wordpress_doctor_links')->insert($linkData);
            $linkId = (int) Database::connection()->lastInsertId();
        }

        return [
            'ok' => true,
            'link' => [
                'id' => $linkId,
                'wp_user_id' => $wpUserId,
                'wp_username' => (string) ($created['wp_username'] ?? $username),
            ],
            'linked_existing' => !empty($created['linked_existing']),
        ];
    }

    /**
     * @return array{ok: bool, error?: string, wp_deleted?: bool}
     */
    public static function revokeAccess(int $userId, int $adminId, bool $deleteWpUser = true): array
    {
        $link = QueryBuilder::table('wordpress_doctor_links')
            ->where('user_id', '=', $userId)
            ->where('status', '=', 'active')
            ->first();

        if ($link === null) {
            return ['ok' => false, 'error' => 'This doctor does not have active WordPress access.'];
        }

        $wpUserId = (int) ($link['wp_user_id'] ?? 0);
        $wpDeleted = false;

        if ($deleteWpUser && $wpUserId > 0 && WordPressSettings::isConfigured()) {
            $wpDeleted = WordPressService::deleteWpUser($wpUserId);
        }

        QueryBuilder::table('wordpress_doctor_links')
            ->where('id', '=', (int) $link['id'])
            ->update(['status' => 'revoked']);

        return [
            'ok' => true,
            'wp_deleted' => $wpDeleted,
        ];
    }

    /** Revoke local links whose WordPress author was deleted externally. */
    public static function syncActiveLinks(): int
    {
        if (!WordPressSettings::isConfigured()) {
            return 0;
        }

        $links = QueryBuilder::table('wordpress_doctor_links')
            ->where('status', '=', 'active')
            ->get();

        $revoked = 0;
        foreach ($links as $link) {
            $wpUserId = (int) ($link['wp_user_id'] ?? 0);
            if ($wpUserId > 0 && !WordPressService::wpUserExists($wpUserId)) {
                self::markRevoked((int) $link['id']);
                $revoked++;
            }
        }

        return $revoked;
    }

    /** @return array<string, mixed>|null */
    public static function linkForUser(int $userId): ?array
    {
        $row = QueryBuilder::table('wordpress_doctor_links')
            ->where('user_id', '=', $userId)
            ->where('status', '=', 'active')
            ->first();

        if ($row === null) {
            return null;
        }

        if (WordPressSettings::isConfigured()) {
            $wpUserId = (int) ($row['wp_user_id'] ?? 0);
            if ($wpUserId <= 0 || !WordPressService::wpUserExists($wpUserId)) {
                self::markRevoked((int) $row['id']);

                return null;
            }
        }

        return $row;
    }

    private static function markRevoked(int $linkId): void
    {
        QueryBuilder::table('wordpress_doctor_links')
            ->where('id', '=', $linkId)
            ->where('status', '=', 'active')
            ->update(['status' => 'revoked']);
    }

    /** @return list<int> */
    public static function authorIdsForDirectoryListing(PDO $db, array $row, string $entityType): array
    {
        $claimedTenantId = (int) ($row['claimed_tenant_id'] ?? 0);
        if ($claimedTenantId <= 0) {
            return [];
        }

        if ($entityType === 'doctor') {
            $directoryId = (int) ($row['id'] ?? 0);
            $stmt = $db->prepare(
                "SELECT wp_user_id FROM wordpress_doctor_links
                 WHERE status = 'active'
                   AND (directory_doctor_id = :did OR (clinic_id = :cid AND directory_doctor_id IS NULL))
                 LIMIT 5"
            );
            $stmt->execute(['did' => $directoryId, 'cid' => $claimedTenantId]);
        } else {
            $stmt = $db->prepare(
                "SELECT wp_user_id FROM wordpress_doctor_links
                 WHERE status = 'active' AND clinic_id = :cid"
            );
            $stmt->execute(['cid' => $claimedTenantId]);
        }

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private static function uniqueUsername(string $displayName, int $userId): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $displayName) ?? 'doctor');
        $base = trim($base, '-') ?: 'doctor';
        $base = substr($base, 0, 40);

        return $base . '-' . $userId;
    }

    private static function resolveDirectoryDoctorId(int $clinicId, string $doctorName): ?int
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, doctor_name, name, types
                 FROM directory_doctors
                 WHERE claimed_tenant_id = :cid AND is_active = 1
                 ORDER BY id ASC
                 LIMIT 10"
            );
            $stmt->execute(['cid' => $clinicId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return null;
        }

        if ($rows === []) {
            return null;
        }

        $needle = strtolower(trim($doctorName));
        foreach ($rows as $row) {
            $candidates = [
                strtolower(trim((string) ($row['doctor_name'] ?? ''))),
                strtolower(trim((string) ($row['name'] ?? ''))),
            ];
            foreach ($candidates as $c) {
                if ($c !== '' && ($c === $needle || str_contains($c, $needle) || str_contains($needle, $c))) {
                    return (int) $row['id'];
                }
            }
        }

        // Prefer an individual-doctor listing (doctor_name set) over a clinic row.
        foreach ($rows as $row) {
            if (trim((string) ($row['doctor_name'] ?? '')) !== '') {
                return (int) $row['id'];
            }
        }

        return (int) $rows[0]['id'];
    }
}
