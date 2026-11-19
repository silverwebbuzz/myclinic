<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * A company's clinic network (design §4, C1–C3): find clinics eClinicPro has
 * switched on for corporate work, ask to add them (the clinic owner accepts in
 * the clinic app), choose which branches each serves, and keep the agreed
 * prices (records only: no money moves through eClinicPro).
 *
 * Reads only the clinic's public business details (name, address, phone):
 * never patients, visits or anything clinical.
 */
final class ProviderService
{
    public const STATUSES = ['requested' => 'Waiting for the clinic', 'active' => 'Active', 'declined' => 'Declined by the clinic', 'ended' => 'Ended'];

    /** Clinic columns the company side may see. */
    private const CLINIC_COLS = 't.id, t.name, t.address, t.phone, t.email, t.country_code, t.timezone';

    /**
     * Enabled clinics in the company's countries (company + branch countries), not already active / requested.
     *
     * @return list<array<string, mixed>>
     */
    public static function search(int $companyId, string $q): array
    {
        $countries = self::countries($companyId);
        if ($countries === []) {
            return [];
        }
        $params = [];
        $in = [];
        foreach (array_values($countries) as $i => $cc) {
            $in[] = ':cc' . $i;
            $params['cc' . $i] = $cc;
        }
        $sql = 'SELECT ' . self::CLINIC_COLS . ' FROM tenants t
                 WHERE t.corporate_enabled = 1 AND t.is_active = 1 AND t.country_code IN (' . implode(',', $in) . ')';
        $q = trim($q);
        if ($q !== '') {
            $sql .= ' AND (t.name LIKE :q1 OR t.address LIKE :q2)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 80)) . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        $sql .= ' ORDER BY t.name LIMIT 50';
        $st = Database::connection()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        $linked = [];
        foreach (self::links($companyId) as $l) {
            $linked[(int) $l['clinic_id']] = $l['status'];
        }
        foreach ($rows as &$r) {
            $r['link_status'] = $linked[(int) $r['id']] ?? null;
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> the company's links (active first) with clinic details */
    public static function links(int $companyId, ?string $status = null): array
    {
        $sql = "SELECT l.id, l.clinic_id, l.status, l.branch_ids, l.note, l.ended_by, l.ended_reason, l.responded_at, l.created_at, l.updated_at,
                       t.name, t.address, t.phone, t.email, t.country_code, t.corporate_enabled,
                       (SELECT COUNT(*) FROM corp_rate_items r WHERE r.company_id = l.company_id AND r.link_id = l.id) AS rate_count
                  FROM corp_provider_links l JOIN tenants t ON t.id = l.clinic_id
                 WHERE l.company_id = :cid";
        $params = ['cid' => $companyId];
        if ($status !== null) {
            $sql .= ' AND l.status = :st';
            $params['st'] = $status;
        }
        $st = Database::connection()->prepare($sql . " ORDER BY FIELD(l.status, 'active', 'requested', 'declined', 'ended'), t.name");
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['branch_list'] = self::branchIds($r);
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $companyId, int $linkId): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT l.*, t.name, t.address, t.phone, t.email, t.country_code, t.timezone, t.corporate_enabled
               FROM corp_provider_links l JOIN tenants t ON t.id = l.clinic_id
              WHERE l.id = :id AND l.company_id = :cid LIMIT 1'
        );
        $st->execute(['id' => $linkId, 'cid' => $companyId]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $r['branch_list'] = self::branchIds($r);

        return $r;
    }

    /** @return list<int>|null null = all branches */
    public static function branchIds(array $link): ?array
    {
        if ($link['branch_ids'] === null || $link['branch_ids'] === '') {
            return null;
        }
        $ids = json_decode((string) $link['branch_ids'], true);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : null;
    }

    /**
     * Ask a clinic to join the network (or ask again after a decline / end).
     *
     * @param list<int>|null $branchIds null = all branches
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function request(int $companyId, int $userId, int $clinicId, ?array $branchIds, string $note): array
    {
        $clinic = self::clinic($clinicId);
        if ($clinic === null || !in_array((string) $clinic['country_code'], self::countries($companyId), true)) {
            return ['ok' => false, 'error' => 'That clinic isn\'t available for corporate work.'];
        }
        $branches = self::cleanBranches($companyId, $branchIds);
        if ($branches === false) {
            return ['ok' => false, 'error' => 'Choose branches from the list.'];
        }
        $note = mb_substr(trim($note), 0, 500);
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id, status FROM corp_provider_links WHERE company_id = :cid AND clinic_id = :clinic LIMIT 1');
        $st->execute(['cid' => $companyId, 'clinic' => $clinicId]);
        $existing = $st->fetch();
        $now = LocalDate::nowUtc();
        if ($existing && in_array($existing['status'], ['active', 'requested'], true)) {
            return ['ok' => false, 'error' => $existing['status'] === 'active' ? 'This clinic is already in your network.' : 'You already asked this clinic. Waiting for them to accept.', 'id' => (int) $existing['id']];
        }
        $json = $branches === null ? null : json_encode($branches);
        if ($existing) {
            $pdo->prepare(
                "UPDATE corp_provider_links SET status = 'requested', branch_ids = :b, note = :n, requested_by = :by, responded_by = 0, responded_at = NULL,
                        ended_by = NULL, ended_reason = NULL, updated_at = :up
                  WHERE id = :id AND company_id = :cid"
            )->execute(['b' => $json, 'n' => $note ?: null, 'by' => $userId, 'up' => $now, 'id' => (int) $existing['id'], 'cid' => $companyId]);
            $id = (int) $existing['id'];
        } else {
            $pdo->prepare(
                "INSERT INTO corp_provider_links (company_id, clinic_id, status, branch_ids, note, requested_by, created_at, updated_at)
                 VALUES (:cid, :clinic, 'requested', :b, :n, :by, :cr, :up)"
            )->execute(['cid' => $companyId, 'clinic' => $clinicId, 'b' => $json, 'n' => $note ?: null, 'by' => $userId, 'cr' => $now, 'up' => $now]);
            $id = (int) $pdo->lastInsertId();
        }
        CorpAudit::log($companyId, 'corp_user', $userId, 'provider_requested', 'corp_provider_link', $id, ['clinic' => $clinic['name']]);
        self::notifyClinic($companyId, $clinic, $note);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @param list<int>|null $branchIds
     * @return array{ok: bool, error?: string}
     */
    public static function setBranches(int $companyId, int $userId, int $linkId, ?array $branchIds): array
    {
        $link = self::find($companyId, $linkId);
        if ($link === null || !in_array($link['status'], ['active', 'requested'], true)) {
            return ['ok' => false, 'error' => 'Clinic not found in your network.'];
        }
        $branches = self::cleanBranches($companyId, $branchIds);
        if ($branches === false) {
            return ['ok' => false, 'error' => 'Choose branches from the list.'];
        }
        Database::connection()->prepare('UPDATE corp_provider_links SET branch_ids = :b, updated_at = :up WHERE id = :id AND company_id = :cid')
            ->execute(['b' => $branches === null ? null : json_encode($branches), 'up' => LocalDate::nowUtc(), 'id' => $linkId, 'cid' => $companyId]);
        CorpAudit::log($companyId, 'corp_user', $userId, 'provider_branches_changed', 'corp_provider_link', $linkId,
            ['clinic' => $link['name'], 'branches' => $branches === null ? 'all' : count($branches)]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function end(int $companyId, int $userId, int $linkId, string $reason): array
    {
        $link = self::find($companyId, $linkId);
        if ($link === null || !in_array($link['status'], ['active', 'requested'], true)) {
            return ['ok' => false, 'error' => 'Clinic not found in your network.'];
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if ($link['status'] === 'active' && mb_strlen($reason) < 5) {
            return ['ok' => false, 'error' => 'Write why you are ending this arrangement.'];
        }
        Database::connection()->prepare(
            "UPDATE corp_provider_links SET status = 'ended', ended_by = 'company', ended_reason = :r, updated_at = :up WHERE id = :id AND company_id = :cid"
        )->execute(['r' => $reason ?: 'Request withdrawn', 'up' => LocalDate::nowUtc(), 'id' => $linkId, 'cid' => $companyId]);
        CorpAudit::log($companyId, 'corp_user', $userId, $link['status'] === 'active' ? 'provider_ended' : 'provider_request_withdrawn', 'corp_provider_link', $linkId,
            ['clinic' => $link['name'], 'reason' => $reason]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Rate card (records only)
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public static function rates(int $companyId, int $linkId): array
    {
        $st = Database::connection()->prepare(
            'SELECT r.*, et.name AS exam_name, et.kind
               FROM corp_rate_items r JOIN oh_exam_types et ON et.id = r.exam_type_id
              WHERE r.company_id = :cid AND r.link_id = :lid ORDER BY et.sort_order, et.name'
        );
        $st->execute(['cid' => $companyId, 'lid' => $linkId]);

        return $st->fetchAll();
    }

    /**
     * Add or update the agreed price of one exam at one clinic.
     *
     * @param array<string, mixed> $post exam_type_id, price, valid_from, valid_to
     * @return array{ok: bool, error?: string}
     */
    public static function saveRate(int $companyId, int $userId, int $linkId, array $post): array
    {
        $link = self::find($companyId, $linkId);
        if ($link === null || $link['status'] === 'ended' || $link['status'] === 'declined') {
            return ['ok' => false, 'error' => 'Clinic not found in your network.'];
        }
        $examId = (int) ($post['exam_type_id'] ?? 0);
        $exam = array_values(array_filter(ProgrammeService::examTypes(), static fn ($x) => (int) $x['id'] === $examId))[0] ?? null;
        if ($exam === null) {
            return ['ok' => false, 'error' => 'Choose a check from the list.'];
        }
        $raw = str_replace([',', ' '], '', trim((string) ($post['price'] ?? '')));
        if ($raw === '' || !is_numeric($raw) || (float) $raw < 0 || (float) $raw > 99999999) {
            return ['ok' => false, 'error' => 'Enter the agreed price as a number, e.g. 450 or 450.50.'];
        }
        $from = trim((string) ($post['valid_from'] ?? ''));
        $to = trim((string) ($post['valid_to'] ?? ''));
        foreach ([$from, $to] as $d) {
            if ($d !== '' && !EmployeeService::isYmd($d)) {
                return ['ok' => false, 'error' => 'Check the dates.'];
            }
        }
        if ($from !== '' && $to !== '' && $to < $from) {
            return ['ok' => false, 'error' => 'The end date is before the start date.'];
        }
        $currency = self::companyCurrency($companyId);
        $now = LocalDate::nowUtc();
        Database::connection()->prepare(
            'INSERT INTO corp_rate_items (company_id, link_id, exam_type_id, price, currency_code, valid_from, valid_to, created_by, created_at, updated_at)
             VALUES (:cid, :lid, :ex, :p, :cur, :vf, :vt, :by, :cr, :up)
             ON DUPLICATE KEY UPDATE price = VALUES(price), currency_code = VALUES(currency_code), valid_from = VALUES(valid_from),
                                     valid_to = VALUES(valid_to), updated_at = VALUES(updated_at)'
        )->execute([
            'cid' => $companyId, 'lid' => $linkId, 'ex' => $examId, 'p' => round((float) $raw, 3), 'cur' => $currency,
            'vf' => $from ?: null, 'vt' => $to ?: null, 'by' => $userId, 'cr' => $now, 'up' => $now,
        ]);
        CorpAudit::log($companyId, 'corp_user', $userId, 'provider_rate_saved', 'corp_provider_link', $linkId,
            ['clinic' => $link['name'], 'exam' => $exam['name'], 'price' => $currency . ' ' . $raw]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public static function removeRate(int $companyId, int $userId, int $linkId, int $rateId): array
    {
        $st = Database::connection()->prepare(
            'SELECT r.id, et.name FROM corp_rate_items r JOIN oh_exam_types et ON et.id = r.exam_type_id
              WHERE r.id = :id AND r.company_id = :cid AND r.link_id = :lid LIMIT 1'
        );
        $st->execute(['id' => $rateId, 'cid' => $companyId, 'lid' => $linkId]);
        $rate = $st->fetch();
        if (!$rate) {
            return ['ok' => false, 'error' => 'Price not found.'];
        }
        Database::connection()->prepare('DELETE FROM corp_rate_items WHERE id = :id AND company_id = :cid')->execute(['id' => $rateId, 'cid' => $companyId]);
        CorpAudit::log($companyId, 'corp_user', $userId, 'provider_rate_removed', 'corp_provider_link', $linkId, ['exam' => $rate['name']]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------

    /** Company country + every branch country. @return list<string> */
    public static function countries(int $companyId): array
    {
        $st = Database::connection()->prepare(
            'SELECT country_code FROM corp_companies WHERE id = :cid
             UNION SELECT country_code FROM corp_branches WHERE company_id = :cid2 AND is_active = 1'
        );
        $st->execute(['cid' => $companyId, 'cid2' => $companyId]);

        return array_values(array_unique(array_filter(array_map('strval', $st->fetchAll(\PDO::FETCH_COLUMN)))));
    }

    public static function companyCurrency(int $companyId): string
    {
        $st = Database::connection()->prepare('SELECT currency_code FROM corp_companies WHERE id = :cid');
        $st->execute(['cid' => $companyId]);

        return (string) ($st->fetchColumn() ?: 'INR');
    }

    /** @return array<string, mixed>|null an enabled, active clinic */
    private static function clinic(int $clinicId): ?array
    {
        $st = Database::connection()->prepare('SELECT ' . self::CLINIC_COLS . ' FROM tenants t WHERE t.id = :id AND t.corporate_enabled = 1 AND t.is_active = 1 LIMIT 1');
        $st->execute(['id' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /**
     * @param list<int>|null $ids
     * @return list<int>|null|false null = all, false = invalid
     */
    private static function cleanBranches(int $companyId, ?array $ids): array|null|false
    {
        if ($ids === null) {
            return null;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return null;
        }
        $valid = array_map(static fn ($b) => (int) $b['id'], StructureService::branches($companyId));
        foreach ($ids as $id) {
            if (!in_array($id, $valid, true)) {
                return false;
            }
        }
        sort($ids);

        return $ids;
    }

    /** @param array<string, mixed> $clinic */
    private static function notifyClinic(int $companyId, array $clinic, string $note): void
    {
        $st = Database::connection()->prepare('SELECT legal_name, trade_name FROM corp_companies WHERE id = :cid');
        $st->execute(['cid' => $companyId]);
        $co = $st->fetch() ?: [];
        $name = (string) (($co['trade_name'] ?? '') ?: ($co['legal_name'] ?? 'A company'));
        $paras = [$name . ' would like to add ' . $clinic['name'] . ' to its network of clinics for employee health checks on eClinicPro.',
            'Open Settings › Corporate work in your clinic app to accept or decline. Nothing changes until you accept.'];
        if ($note !== '') {
            $paras[] = 'Their message: "' . $note . '"';
        }
        CorpMailer::send((string) ($clinic['email'] ?? ''), $name . ' wants to add your clinic for employee health checks', 'New corporate request', $paras,
            CorpMailer::appUrl() . '/settings/corporate', 'Open corporate requests');
    }
}
