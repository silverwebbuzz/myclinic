<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Support\Locale\LocalDate;

/**
 * The company's spend on clinic checks (design §6, R6/R7): statements the
 * clinics sent (records only; payment is between the two parties) plus an
 * estimate for checks done but not yet on a statement, at the agreed prices.
 */
final class SpendService
{
    private const MONTHS = 12;

    /** @return list<array<string, mixed>> statements the company can see (not drafts / void) */
    public static function statements(int $companyId): array
    {
        $st = Database::connection()->prepare(
            "SELECT s.id, s.statement_no, s.period_from, s.period_to, s.currency_code, s.total, s.lines_count, s.status, s.pdf_path,
                    s.dispute_note, s.sent_at, s.paid_at, t.name AS clinic_name
               FROM corp_provider_statements s JOIN tenants t ON t.id = s.clinic_id
              WHERE s.company_id = :cid AND s.status IN ('sent', 'paid', 'disputed')
              ORDER BY s.period_from DESC, s.id DESC LIMIT 100"
        );
        $st->execute(['cid' => $companyId]);

        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null a statement the company may open */
    public static function find(int $companyId, int $id): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT s.*, t.name AS clinic_name, t.email AS clinic_email FROM corp_provider_statements s JOIN tenants t ON t.id = s.clinic_id
              WHERE s.id = :id AND s.company_id = :cid AND s.status IN ('sent', 'paid', 'disputed') LIMIT 1"
        );
        $st->execute(['id' => $id, 'cid' => $companyId]);

        return $st->fetch() ?: null;
    }

    /** @return array{ok: bool, error?: string} */
    public static function dispute(int $companyId, int $userId, int $id, string $note): array
    {
        $s = self::find($companyId, $id);
        if ($s === null || $s['status'] === 'disputed') {
            return ['ok' => false, 'error' => 'That statement can\'t be disputed.'];
        }
        $note = mb_substr(trim($note), 0, 500);
        if (mb_strlen($note) < 5) {
            return ['ok' => false, 'error' => 'Write what is wrong (the clinic sees this).'];
        }
        Database::connection()->prepare(
            "UPDATE corp_provider_statements SET status = 'disputed', dispute_note = :n, updated_at = :up WHERE id = :id AND company_id = :cid"
        )->execute(['n' => $note, 'up' => LocalDate::nowUtc(), 'id' => $id, 'cid' => $companyId]);
        CorpAudit::log($companyId, 'corp_user', $userId, 'statement_disputed', 'corp_provider_statement', $id, ['statement' => $s['statement_no'], 'note' => $note]);
        CorpMailer::send((string) ($s['clinic_email'] ?? ''), 'Statement ' . $s['statement_no'] . ' disputed', 'A company disputed your statement',
            ['The company disputed statement ' . $s['statement_no'] . ' (' . $s['currency_code'] . ' ' . number_format((float) $s['total'], 2) . ').', 'Their note: "' . $note . '"',
                'Please sort it out with them directly; you can mark it paid in Corporate work once settled.'],
            CorpMailer::appUrl() . '/settings/corporate#statements', 'Open statements');

        return ['ok' => true];
    }

    /**
     * Spend per clinic for the last 12 months: billed (statements sent / paid / disputed, by period) and
     * an estimate for completed checks not yet on any statement, at the agreed price.
     *
     * @return array{months: list<string>, clinics: list<array{name: string, billed: array<string, float>, unbilled: array<string, float>}>, currency: string}
     */
    public static function summary(int $companyId, string $today): array
    {
        $months = [];
        $start = date('Y-m-01', strtotime(substr($today, 0, 7) . '-01 -' . (self::MONTHS - 1) . ' months'));
        for ($i = 0; $i < self::MONTHS; $i++) {
            $months[] = date('Y-m', strtotime($start . ' +' . $i . ' months'));
        }
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT s.clinic_id, t.name, DATE_FORMAT(s.period_from, '%Y-%m') AS m, SUM(s.total) AS total
               FROM corp_provider_statements s JOIN tenants t ON t.id = s.clinic_id
              WHERE s.company_id = :cid AND s.status IN ('sent', 'paid', 'disputed') AND s.period_from >= :start
              GROUP BY s.clinic_id, t.name, m"
        );
        $st->execute(['cid' => $companyId, 'start' => $start]);
        $clinics = [];
        foreach ($st->fetchAll() as $r) {
            $k = (int) $r['clinic_id'];
            $clinics[$k] ??= ['name' => (string) $r['name'], 'billed' => [], 'unbilled' => []];
            $clinics[$k]['billed'][(string) $r['m']] = (float) $r['total'];
        }
        $un = $pdo->prepare(
            "SELECT b.clinic_id, t.name, DATE_FORMAT(c.completed_on, '%Y-%m') AS m, SUM(COALESCE(r.price, 0)) AS total
               FROM corp_screening_cases c
               JOIN corp_bookings b ON b.id = c.booking_id AND b.company_id = c.company_id
               JOIN tenants t ON t.id = b.clinic_id
               LEFT JOIN corp_provider_links l ON l.company_id = c.company_id AND l.clinic_id = b.clinic_id
               LEFT JOIN corp_rate_items r ON r.company_id = c.company_id AND r.link_id = l.id AND r.exam_type_id = c.exam_type_id
              WHERE c.company_id = :cid AND c.status = 'completed' AND c.completion_source = 'eclinicpro' AND c.completed_on >= :start
                AND NOT EXISTS (SELECT 1 FROM corp_statement_lines sl JOIN corp_provider_statements s2 ON s2.id = sl.statement_id AND s2.company_id = sl.company_id
                                 WHERE sl.company_id = c.company_id AND sl.case_id = c.id AND s2.status IN ('sent', 'paid', 'disputed'))
              GROUP BY b.clinic_id, t.name, m"
        );
        $un->execute(['cid' => $companyId, 'start' => $start]);
        foreach ($un->fetchAll() as $r) {
            $k = (int) $r['clinic_id'];
            $clinics[$k] ??= ['name' => (string) $r['name'], 'billed' => [], 'unbilled' => []];
            $clinics[$k]['unbilled'][(string) $r['m']] = (float) $r['total'];
        }
        usort($clinics, static fn ($a, $b) => strcmp($a['name'], $b['name']));

        return ['months' => $months, 'clinics' => array_values($clinics), 'currency' => ProviderService::companyCurrency($companyId)];
    }
}
