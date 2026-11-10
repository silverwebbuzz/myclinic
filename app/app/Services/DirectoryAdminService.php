<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

// Public-site helpers for the profile URL (city/doctor|clinic/slug). They are
// plain functions with no side effects when loaded.
require_once dirname(__DIR__, 3) . '/partials/directory_profile.php';

/**
 * DirectoryAdminService — /admin/directory: every directory_doctors listing,
 * with Hide / Show so an admin can take a listing off the public site.
 *
 * Public pages show a row only when is_active = 1 AND status = 'OPERATIONAL'.
 * Hide sets is_active = 0 and dropped_reason = 'admin_hidden'; the marker makes
 * the importer (fetch_doctor/insert_db.php) leave the row hidden on re-import.
 * No hard delete: rows are re-imported by place_id, and the foreign keys would
 * cascade into patients' leads and wishlists.
 *
 * Every change clears the saved public pages (storage/page_cache, see
 * partials/page_cache.php), otherwise logged-out visitors keep seeing a hidden
 * listing until midnight.
 */
final class DirectoryAdminService
{
    public const PER_PAGE = 50;

    public const HIDDEN_REASON = 'admin_hidden';

    /** Visibility filter values → label. */
    public const VISIBILITY = [
        'visible' => 'Showing on site',
        'hidden'  => 'Hidden',
        'closed'  => 'Closed (Google)',
    ];

    /**
     * @param array<string,mixed> $f
     * @return array{where:string, bind:array<string,mixed>}
     */
    private static function filterSql(array $f): array
    {
        $where = ['1 = 1'];
        $bind = [];

        if (!empty($f['city'])) {
            $where[] = 'dd.city = :city';
            $bind[':city'] = (string) $f['city'];
        }
        if (!empty($f['specialty'])) {
            $where[] = 'dd.specialty = :specialty';
            $bind[':specialty'] = (string) $f['specialty'];
        }
        if (!empty($f['q'])) {
            $q = trim((string) $f['q']);
            if (ctype_digit($q)) {
                // Plain number: listing id OR phone digits.
                $where[] = '(dd.id = :qid OR dd.phone LIKE :qp1 OR dd.intl_phone LIKE :qp2)';
                $bind[':qid'] = (int) $q;
                $bind[':qp1'] = $bind[':qp2'] = '%' . $q . '%';
            } else {
                // Distinct names per use: EMULATE_PREPARES is off, reuse → HY093.
                $where[] = '(dd.name LIKE :q1 OR dd.doctor_name LIKE :q2)';
                $bind[':q1'] = $bind[':q2'] = '%' . $q . '%';
            }
        }
        switch ($f['visibility'] ?? '') {
            case 'visible':
                $where[] = "dd.is_active = 1 AND dd.status = 'OPERATIONAL'";
                break;
            case 'hidden':
                $where[] = 'dd.is_active = 0';
                break;
            case 'closed':
                $where[] = "dd.status <> 'OPERATIONAL'";
                break;
        }
        if (($f['claimed'] ?? '') === '1') {
            $where[] = 'dd.is_claimed = 1';
        } elseif (($f['claimed'] ?? '') === '0') {
            $where[] = 'dd.is_claimed = 0';
        }

        return ['where' => implode(' AND ', $where), 'bind' => $bind];
    }

    /** @param array<string,mixed> $filters */
    public static function count(array $filters): int
    {
        $f = self::filterSql($filters);
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM directory_doctors dd WHERE {$f['where']}");
        foreach ($f['bind'] as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * One page of listings, newest first, each with its public URL and
     * patient booking count.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public static function page(array $filters, int $page): array
    {
        $f = self::filterSql($filters);
        $stmt = Database::connection()->prepare(
            "SELECT dd.*,
                    (SELECT COUNT(*) FROM directory_leads dl
                      WHERE dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted') AS bookings
               FROM directory_doctors dd
              WHERE {$f['where']}
              ORDER BY dd.id DESC
              LIMIT :lim OFFSET :off"
        );
        foreach ($f['bind'] as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $page - 1) * self::PER_PAGE, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'id'          => (int) $r['id'],
                'name'        => (string) ($r['name'] ?? ''),
                'doctor_name' => (string) ($r['doctor_name'] ?? ''),
                'specialty'   => (string) ($r['specialty'] ?? ''),
                'city'        => (string) ($r['city'] ?? ''),
                'area'        => (string) ($r['area'] ?? ''),
                'phone'       => (string) ($r['phone'] ?? ''),
                'rating'      => $r['rating'] !== null ? (float) $r['rating'] : null,
                'reviews'     => (int) ($r['reviews'] ?? 0),
                'status'      => (string) ($r['status'] ?? ''),
                'is_active'   => (int) ($r['is_active'] ?? 0) === 1,
                'is_claimed'  => (int) ($r['is_claimed'] ?? 0) === 1 || !empty($r['claimed_tenant_id']),
                'hidden_by_admin' => ($r['dropped_reason'] ?? '') === self::HIDDEN_REASON,
                'bookings'    => (int) $r['bookings'],
                'url'         => ecp_directory_profile_url($r),
            ];
        }

        return $rows;
    }

    /** @return array{cities:list<string>, specialties:list<string>} */
    public static function filterOptions(): array
    {
        $db = Database::connection();

        return [
            'cities' => $db->query(
                "SELECT DISTINCT city FROM directory_doctors WHERE city <> '' ORDER BY city"
            )->fetchAll(PDO::FETCH_COLUMN),
            'specialties' => $db->query(
                "SELECT DISTINCT specialty FROM directory_doctors WHERE specialty IS NOT NULL AND specialty <> '' ORDER BY specialty"
            )->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    /** Hide (false) or show again (true). Returns false if the row doesn't exist. */
    public static function setVisible(int $id, bool $visible): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = Database::connection()->prepare(
            $visible
                // Only clear our own marker; the importer's reasons stay.
                ? 'UPDATE directory_doctors
                      SET is_active = 1,
                          dropped_reason = IF(dropped_reason = :r, NULL, dropped_reason)
                    WHERE id = :id'
                : 'UPDATE directory_doctors SET is_active = 0, dropped_reason = :r WHERE id = :id'
        );
        $stmt->execute([':r' => self::HIDDEN_REASON, ':id' => $id]);
        self::purgePublicCache();

        return $stmt->rowCount() > 0 || self::exists($id);
    }

    private static function exists(int $id): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM directory_doctors WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Drop saved profile pages (prof_*) and find-a-doctor listing pages (fad_*).
     * Clearing them all is simplest: a hidden doctor also appears on clinic
     * pages ("doctors at this clinic") and on city/specialty listings. Each
     * page rebuilds on its next visit.
     */
    public static function purgePublicCache(): int
    {
        $n = 0;
        $dir = dirname(__DIR__, 3) . '/storage/page_cache';
        foreach (['prof_', 'fad_'] as $prefix) {
            foreach (glob($dir . '/' . $prefix . '*.html') ?: [] as $file) {
                if (@unlink($file)) {
                    $n++;
                }
            }
        }

        return $n;
    }
}
