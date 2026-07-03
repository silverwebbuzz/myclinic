<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\WordPressSettings;
use PDO;

final class WordPressDoctorService
{
  /** @return array{doctors: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public static function doctorsForAdmin(string $search = '', int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $search = trim($search);

        $pdo = Database::connection();
        $params = [];
        $where = "dd.is_active = 1 AND dd.status = 'OPERATIONAL'";

        if ($search !== '') {
            $where .= ' AND (dd.name LIKE :q1 OR dd.doctor_name LIKE :q2 OR dd.city LIKE :q3 OR t.name LIKE :q4)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $from = "FROM directory_doctors dd
             LEFT JOIN tenants t ON t.id = dd.claimed_tenant_id AND t.is_active = 1
             LEFT JOIN users u ON u.clinic_id = dd.claimed_tenant_id AND u.is_active = 1 AND u.is_owner = 1
             LEFT JOIN wordpress_doctor_links wdl ON wdl.directory_doctor_id = dd.id AND wdl.status = 'active'";

        $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT dd.id) {$from} WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT dd.id,
                    dd.name,
                    dd.doctor_name,
                    dd.city,
                    dd.state,
                    dd.specialty,
                    dd.is_claimed,
                    dd.claimed_tenant_id,
                    t.name AS tenant_name,
                    u.id AS portal_user_id,
                    u.email AS portal_email,
                    wdl.id AS link_id,
                    wdl.wp_user_id,
                    wdl.wp_username,
                    wdl.status AS wp_status,
                    wdl.created_at AS wp_linked_at
             {$from}
             WHERE {$where}
             ORDER BY wdl.id IS NULL DESC,
                      dd.is_claimed DESC,
                      COALESCE(NULLIF(TRIM(dd.doctor_name), ''), dd.name) ASC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        $doctors = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $doctors[] = self::normalizeDirectoryAdminRow($row);
        }

        return [
            'doctors' => $doctors,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function normalizeDirectoryAdminRow(array $row): array
    {
        $doctorName = trim((string) ($row['doctor_name'] ?? ''));
        $listingName = trim((string) ($row['name'] ?? ''));
        $displayName = $doctorName !== '' ? ($doctorName !== $listingName && $listingName !== '' ? $doctorName : ($doctorName ?: $listingName)) : $listingName;
        if ($displayName === '') {
            $displayName = 'Directory listing #' . (int) ($row['id'] ?? 0);
        }

        $email = trim((string) ($row['portal_email'] ?? ''));
        if ($email === '') {
            $email = '—';
        }

        $clinicLabel = trim((string) ($row['tenant_name'] ?? ''));
        if ($clinicLabel === '') {
            $clinicLabel = $listingName;
        }

        $city = trim((string) ($row['city'] ?? ''));
        $state = trim((string) ($row['state'] ?? ''));
        $location = trim(implode(', ', array_filter([$city, $state])));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $displayName,
            'listing_name' => $listingName,
            'email' => $email,
            'clinic_name' => $clinicLabel,
            'location' => $location,
            'is_claimed' => !empty($row['is_claimed']),
            'portal_user_id' => !empty($row['portal_user_id']) ? (int) $row['portal_user_id'] : null,
            'link_id' => $row['link_id'] ?? null,
            'wp_user_id' => $row['wp_user_id'] ?? null,
            'wp_username' => $row['wp_username'] ?? null,
            'wp_status' => $row['wp_status'] ?? null,
            'wp_linked_at' => $row['wp_linked_at'] ?? null,
        ];
    }

    /** @return array{ok: bool, error?: string, link?: array<string, mixed>, linked_existing?: bool} */
    public static function grantAccess(int $directoryDoctorId, int $adminId): array
    {
        if (!WordPressSettings::isConfigured()) {
            return ['ok' => false, 'error' => 'WordPress is not configured.'];
        }

        $listing = self::findDirectoryListing($directoryDoctorId);
        if ($listing === null) {
            return ['ok' => false, 'error' => 'Directory listing not found.'];
        }

        $existing = QueryBuilder::table('wordpress_doctor_links')
            ->where('directory_doctor_id', '=', $directoryDoctorId)
            ->first();

        if ($existing !== null && ($existing['status'] ?? '') === 'active') {
            return ['ok' => false, 'error' => 'This listing already has WordPress access.'];
        }

        $portalUser = self::resolvePortalUserForListing($listing);
        $portalUserId = $portalUser !== null ? (int) ($portalUser['id'] ?? 0) : null;
        $clinicId = (int) ($listing['claimed_tenant_id'] ?? 0) ?: null;

        $displayName = self::directoryDisplayName($listing);
        $email = trim((string) ($portalUser['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'listing-' . $directoryDoctorId . '@authors.eclinicpro.internal';
        }

        $username = self::uniqueUsername($displayName, $directoryDoctorId);
        $password = bin2hex(random_bytes(16));

        $created = WordPressService::createOrLinkAuthor($username, $email, $displayName, $password);
        if (!$created['ok']) {
            return [
                'ok' => false,
                'error' => (string) ($created['error'] ?? 'Could not create WordPress user.'),
            ];
        }

        $wpUserId = (int) ($created['wp_user_id'] ?? 0);
        $existingWpLink = QueryBuilder::table('wordpress_doctor_links')
            ->where('wp_user_id', '=', $wpUserId)
            ->where('status', '=', 'active')
            ->first();
        if ($existingWpLink !== null && (int) ($existingWpLink['directory_doctor_id'] ?? 0) !== $directoryDoctorId) {
            return ['ok' => false, 'error' => 'This WordPress account is already linked to another listing.'];
        }

        $linkData = [
            'user_id' => $portalUserId,
            'clinic_id' => $clinicId,
            'directory_doctor_id' => $directoryDoctorId,
            'wp_user_id' => $wpUserId,
            'wp_username' => (string) ($created['wp_username'] ?? $username),
            'wp_email' => (string) ($created['wp_email'] ?? $email),
            'bridge_token_hash' => WordPressService::hashBridgeToken(WordPressService::generateBridgeToken()),
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

    /** @return array{ok: bool, error?: string, wp_deleted?: bool} */
    public static function revokeAccess(int $directoryDoctorId, int $adminId, bool $deleteWpUser = true): array
    {
        $link = QueryBuilder::table('wordpress_doctor_links')
            ->where('directory_doctor_id', '=', $directoryDoctorId)
            ->where('status', '=', 'active')
            ->first();

        if ($link === null) {
            return ['ok' => false, 'error' => 'This listing does not have active WordPress access.'];
        }

        $wpUserId = (int) ($link['wp_user_id'] ?? 0);
        $wpDeleted = false;

        if ($deleteWpUser && $wpUserId > 0 && WordPressSettings::isConfigured()) {
            $wpDeleted = WordPressService::deleteWpUser($wpUserId);
        }

        QueryBuilder::table('wordpress_doctor_links')
            ->where('id', '=', (int) $link['id'])
            ->update(['status' => 'revoked']);

        return ['ok' => true, 'wp_deleted' => $wpDeleted];
    }

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
        $user = QueryBuilder::table('users')
            ->where('id', '=', $userId)
            ->where('is_active', '=', 1)
            ->first();

        if ($user === null) {
            return null;
        }

        $row = QueryBuilder::table('wordpress_doctor_links')
            ->where('user_id', '=', $userId)
            ->where('status', '=', 'active')
            ->first();

        if ($row === null) {
            $clinicId = (int) ($user['clinic_id'] ?? 0);
            if ($clinicId > 0) {
                $listingIds = self::directoryIdsForClinic($clinicId);
                if ($listingIds !== []) {
                    $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
                    $stmt = Database::connection()->prepare(
                        "SELECT * FROM wordpress_doctor_links
                         WHERE status = 'active' AND directory_doctor_id IN ({$placeholders})
                         ORDER BY id ASC LIMIT 1"
                    );
                    $stmt->execute($listingIds);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                }
            }
        }

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

    /** @return list<int> */
    public static function authorIdsForDirectoryListing(PDO $db, array $row, string $entityType): array
    {
        $directoryId = (int) ($row['id'] ?? 0);
        if ($directoryId <= 0) {
            return [];
        }

        if ($entityType === 'doctor') {
            $stmt = $db->prepare(
                "SELECT wp_user_id FROM wordpress_doctor_links
                 WHERE status = 'active' AND directory_doctor_id = :did
                 LIMIT 5"
            );
            $stmt->execute(['did' => $directoryId]);
        } else {
            $claimedTenantId = (int) ($row['claimed_tenant_id'] ?? 0);
            if ($claimedTenantId <= 0) {
                return [];
            }
            $stmt = $db->prepare(
                "SELECT wp_user_id FROM wordpress_doctor_links wdl
                 INNER JOIN directory_doctors dd ON dd.id = wdl.directory_doctor_id
                 WHERE wdl.status = 'active' AND dd.claimed_tenant_id = :cid"
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

    /** @return array<string, mixed>|null */
    private static function findDirectoryListing(int $directoryDoctorId): ?array
    {
        if ($directoryDoctorId <= 0) {
            return null;
        }

        $row = QueryBuilder::table('directory_doctors')
            ->where('id', '=', $directoryDoctorId)
            ->where('is_active', '=', 1)
            ->first();

        return $row ?: null;
    }

    /** @param array<string, mixed> $listing @return array<string, mixed>|null */
    private static function resolvePortalUserForListing(array $listing): ?array
    {
        $clinicId = (int) ($listing['claimed_tenant_id'] ?? 0);
        if ($clinicId <= 0) {
            return null;
        }

        $owner = QueryBuilder::table('users')
            ->where('clinic_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->where('is_owner', '=', 1)
            ->first();

        if ($owner !== null) {
            return $owner;
        }

        return QueryBuilder::table('users')
            ->where('clinic_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->where('role', '=', 'doctor')
            ->orderBy('id', 'ASC')
            ->first();
    }

    /** @param array<string, mixed> $listing */
    private static function directoryDisplayName(array $listing): string
    {
        $doctorName = trim((string) ($listing['doctor_name'] ?? ''));
        $name = trim((string) ($listing['name'] ?? ''));

        return $doctorName !== '' ? $doctorName : ($name !== '' ? $name : 'Doctor');
    }

    /** @return list<int> */
    private static function directoryIdsForClinic(int $clinicId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id FROM directory_doctors
                 WHERE claimed_tenant_id = :cid AND is_active = 1
                 ORDER BY id ASC'
            );
            $stmt->execute(['cid' => $clinicId]);
            $ids = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function uniqueUsername(string $displayName, int $directoryDoctorId): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $displayName) ?? 'doctor');
        $base = trim($base, '-') ?: 'doctor';
        $base = substr($base, 0, 40);

        return $base . '-d' . $directoryDoctorId;
    }

    private static function markRevoked(int $linkId): void
    {
        QueryBuilder::table('wordpress_doctor_links')
            ->where('id', '=', $linkId)
            ->where('status', '=', 'active')
            ->update(['status' => 'revoked']);
    }
}
