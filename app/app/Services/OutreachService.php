<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * OutreachService — the doctor-acquisition worklist behind /admin/outreach.
 *
 * Segments NON-JOINED directory doctors by how many patient leads they got in
 * the current calendar month, so the sales team can reach out ("you received N
 * leads this month — join eClinicPro"). Joins per-clinic outreach state from
 * directory_outreach (status / opt-out / contacted history).
 *
 * Built to scale to thousands of clinics: every list query is paginated and the
 * CSV export streams in capped chunks. Bulk SEND lives in a later phase; this
 * service only reads the segment + writes per-clinic status.
 */
final class OutreachService
{
    public const PER_PAGE = 50;

    /** Valid statuses an admin can set (mirrors the directory_outreach enum). */
    public const STATUSES = ['not_contacted', 'contacted', 'interested', 'joined', 'not_now', 'opted_out'];

    /**
     * Build the WHERE/HAVING + bindings shared by list() and count()/export().
     * Filters: minLeads (this-month threshold), city, specialty, status, q (name).
     *
     * @param array<string,mixed> $f
     * @return array{where:string, having:string, bind:array<string,mixed>}
     */
    private static function filterSql(array $f): array
    {
        // Only non-joined doctors are acquisition targets.
        $where = ['dd.is_claimed = 0'];
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
            $where[] = '(dd.name LIKE :q OR dd.doctor_name LIKE :q)';
            $bind[':q'] = '%' . $f['q'] . '%';
        }
        if (!empty($f['status'])) {
            // 'not_contacted' also matches clinics with no outreach row yet.
            if ($f['status'] === 'not_contacted') {
                $where[] = "COALESCE(o.status, 'not_contacted') = 'not_contacted'";
            } else {
                $where[] = 'o.status = :status';
                $bind[':status'] = (string) $f['status'];
            }
        }

        // Lead-count threshold is on the aggregate → HAVING.
        $having = '';
        $min = isset($f['min_leads']) ? max(0, (int) $f['min_leads']) : 0;
        if ($min > 0) {
            $having = 'HAVING leads_this_month >= :min_leads';
            $bind[':min_leads'] = $min;
        }

        return ['where' => implode(' AND ', $where), 'having' => $having, 'bind' => $bind];
    }

    /**
     * One page of the segment. Each row = a non-joined clinic + its lead counts
     * + outreach status. Newest/highest-volume first.
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public static function segment(array $filters, int $page = 1): array
    {
        $f = self::filterSql($filters);
        $offset = max(0, ($page - 1)) * self::PER_PAGE;

        $sql =
            "SELECT dd.id, dd.name, dd.doctor_name, dd.phone, dd.intl_phone,
                    dd.whatsapp_status, dd.city, dd.state, dd.area, dd.specialty,
                    COALESCE(o.status, 'not_contacted') AS status,
                    o.last_channel, o.last_contacted_at, o.contacted_count,
                    o.opted_out, o.notes,
                    SUM(dl.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS leads_this_month,
                    COUNT(dl.id) AS leads_total,
                    MAX(dl.created_at) AS last_lead_at
               FROM directory_doctors dd
               JOIN directory_leads dl
                    ON dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted'
          LEFT JOIN directory_outreach o ON o.directory_doctor_id = dd.id
              WHERE {$f['where']}
              GROUP BY dd.id
              {$f['having']}
              ORDER BY leads_this_month DESC, leads_total DESC
              LIMIT :lim OFFSET :off";

        $stmt = Database::connection()->prepare($sql);
        foreach ($f['bind'] as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total clinics matching the filter — for pagination. Counts grouped rows.
     *
     * @param array<string,mixed> $filters
     */
    public static function count(array $filters): int
    {
        $f = self::filterSql($filters);
        $sql =
            "SELECT COUNT(*) FROM (
                SELECT dd.id,
                       SUM(dl.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS leads_this_month
                  FROM directory_doctors dd
                  JOIN directory_leads dl
                       ON dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted'
             LEFT JOIN directory_outreach o ON o.directory_doctor_id = dd.id
                 WHERE {$f['where']}
                 GROUP BY dd.id
                 {$f['having']}
             ) t";

        $stmt = Database::connection()->prepare($sql);
        foreach ($f['bind'] as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Full filtered segment for CSV export (capped to avoid runaway memory).
     *
     * @param array<string,mixed> $filters
     * @return list<array<string,mixed>>
     */
    public static function exportRows(array $filters, int $cap = 5000): array
    {
        $f = self::filterSql($filters);
        $sql =
            "SELECT dd.id, dd.name, dd.doctor_name, dd.phone, dd.intl_phone,
                    dd.city, dd.state, dd.area, dd.specialty,
                    COALESCE(o.status, 'not_contacted') AS status,
                    o.last_contacted_at, o.contacted_count,
                    SUM(dl.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS leads_this_month,
                    COUNT(dl.id) AS leads_total,
                    MAX(dl.created_at) AS last_lead_at
               FROM directory_doctors dd
               JOIN directory_leads dl
                    ON dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted'
          LEFT JOIN directory_outreach o ON o.directory_doctor_id = dd.id
              WHERE {$f['where']}
              GROUP BY dd.id
              {$f['having']}
              ORDER BY leads_this_month DESC, leads_total DESC
              LIMIT :cap";

        $stmt = Database::connection()->prepare($sql);
        foreach ($f['bind'] as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':cap', $cap, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upsert one clinic's outreach status/notes (manual admin edit).
     * Setting status to 'opted_out' also stamps the opt-out flag.
     */
    public static function setStatus(int $doctorId, string $status, ?string $notes, ?int $adminId): bool
    {
        if ($doctorId <= 0 || !in_array($status, self::STATUSES, true)) {
            return false;
        }
        $optedOut = $status === 'opted_out' ? 1 : 0;

        $stmt = Database::connection()->prepare(
            'INSERT INTO directory_outreach
                (directory_doctor_id, status, notes, opted_out, opted_out_at, updated_by)
             VALUES
                (:did, :st, :notes, :oo, :ooat, :uid)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                notes = VALUES(notes),
                opted_out = VALUES(opted_out),
                opted_out_at = IF(VALUES(opted_out) = 1, COALESCE(opted_out_at, NOW()), opted_out_at),
                updated_by = VALUES(updated_by)'
        );
        return $stmt->execute([
            ':did' => $doctorId,
            ':st' => $status,
            ':notes' => $notes !== null && $notes !== '' ? mb_substr($notes, 0, 2000) : null,
            ':oo' => $optedOut,
            ':ooat' => $optedOut ? date('Y-m-d H:i:s') : null,
            ':uid' => $adminId,
        ]);
    }

    /** Distinct cities/specialties present among non-joined doctors with leads (for filter dropdowns). */
    public static function filterOptions(): array
    {
        $pdo = Database::connection();
        $cities = $pdo->query(
            "SELECT DISTINCT dd.city
               FROM directory_doctors dd
               JOIN directory_leads dl ON dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted'
              WHERE dd.is_claimed = 0 AND dd.city <> ''
              ORDER BY dd.city LIMIT 300"
        )->fetchAll(PDO::FETCH_COLUMN);
        $specialties = $pdo->query(
            "SELECT DISTINCT dd.specialty
               FROM directory_doctors dd
               JOIN directory_leads dl ON dl.directory_doctor_id = dd.id AND dl.type = 'book_submitted'
              WHERE dd.is_claimed = 0 AND dd.specialty IS NOT NULL AND dd.specialty <> ''
              ORDER BY dd.specialty LIMIT 300"
        )->fetchAll(PDO::FETCH_COLUMN);

        return ['cities' => $cities, 'specialties' => $specialties];
    }
}
