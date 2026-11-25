<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Application;
use App\Core\Database;
use App\Http\Response;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\CorpMailer;
use App\Support\Locale\LocalDate;
use Mpdf\Mpdf;

/**
 * Clinic → company monthly statements (design §6, R6). A RECORD of the checks
 * the clinic completed for the company (via eClinicPro bookings) at the agreed
 * prices. eClinicPro never collects or passes on money: the clinic marks a
 * statement sent / paid by hand, the company may mark it disputed. A check is
 * billed once (UNIQUE case_id); voiding a draft frees its checks again.
 */
final class StatementService
{
    public const STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'disputed' => 'Disputed', 'void' => 'Void'];

    /** @return list<array<string, mixed>> this clinic's statements, newest first */
    public static function forClinic(int $clinicId): array
    {
        try {
            $st = Database::connection()->prepare(
                "SELECT s.*, COALESCE(NULLIF(c.trade_name, ''), c.legal_name) AS company_name
                   FROM corp_provider_statements s JOIN corp_companies c ON c.id = s.company_id
                  WHERE s.clinic_id = :clinic /* cross-company: clinic sees its own statements */
                  ORDER BY s.id DESC LIMIT 100"
            );
            $st->execute(['clinic' => $clinicId]);

            return $st->fetchAll();
        } catch (\Throwable $e) {
            return [];   // 1E patch not run yet
        }
    }

    /**
     * Completed checks at this clinic for the company in the period, not yet on a statement, priced.
     *
     * @return list<array<string, mixed>>
     */
    public static function billable(int $clinicId, int $companyId, string $from, string $to): array
    {
        $st = Database::connection()->prepare(
            "SELECT c.id AS case_id, c.exam_type_id, c.completed_on, b.id AS booking_id, b.employment_id, e.full_name, e.employee_no, et.name AS exam_name,
                    r.price, r.valid_from, r.valid_to
               FROM corp_screening_cases c
               JOIN corp_bookings b ON b.id = c.booking_id AND b.company_id = c.company_id
               JOIN corp_employments e ON e.id = c.employment_id AND e.company_id = c.company_id
               JOIN oh_exam_types et ON et.id = c.exam_type_id
               LEFT JOIN corp_provider_links l ON l.company_id = c.company_id AND l.clinic_id = b.clinic_id
               LEFT JOIN corp_rate_items r ON r.company_id = c.company_id AND r.link_id = l.id AND r.exam_type_id = c.exam_type_id
              WHERE c.company_id = :cid AND b.clinic_id = :clinic AND c.status = 'completed' AND c.completion_source = 'eclinicpro'
                AND c.completed_on >= :d1 AND c.completed_on <= :d2
                AND NOT EXISTS (SELECT 1 FROM corp_statement_lines sl WHERE sl.company_id = c.company_id AND sl.case_id = c.id)
              ORDER BY c.completed_on, e.full_name"
        );
        $st->execute(['cid' => $companyId, 'clinic' => $clinicId, 'd1' => $from, 'd2' => $to]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $inRange = $r['price'] !== null
                && ($r['valid_from'] === null || (string) $r['valid_from'] <= (string) $r['completed_on'])
                && ($r['valid_to'] === null || (string) $r['valid_to'] >= (string) $r['completed_on']);
            $r['price_agreed'] = $inRange ? 1 : 0;
            $r['price'] = $inRange ? (float) $r['price'] : 0.0;
        }

        return $rows;
    }

    /** @return array{ok: bool, error?: string, id?: int} a draft for one company and month (Y-m) */
    public static function generate(int $clinicId, int $userId, int $companyId, string $month): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            return ['ok' => false, 'error' => 'Choose a month.'];
        }
        $link = self::activeOrPastLink($clinicId, $companyId);
        if ($link === null) {
            return ['ok' => false, 'error' => 'That company is not in your corporate work.'];
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $lines = self::billable($clinicId, $companyId, $from, $to);
        if ($lines === []) {
            return ['ok' => false, 'error' => 'Nothing to bill for ' . date('M Y', strtotime($from)) . ': no completed checks that aren\'t already on a statement.'];
        }
        $currency = (string) ($link['currency_code'] ?: 'INR');
        $total = array_sum(array_column($lines, 'price'));
        $pdo = Database::connection();
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO corp_provider_statements (company_id, clinic_id, statement_no, period_from, period_to, currency_code, total, lines_count, status, created_by, created_at, updated_at)
                 VALUES (:cid, :clinic, :tmp, :f, :t, :cur, :tot, :n, 'draft', :by, :cr, :up)"
            )->execute(['cid' => $companyId, 'clinic' => $clinicId, 'tmp' => 'TMP-' . bin2hex(random_bytes(8)), 'f' => $from, 't' => $to, 'cur' => $currency,
                'tot' => round($total, 3), 'n' => count($lines), 'by' => $userId, 'cr' => $now, 'up' => $now]);
            $id = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE corp_provider_statements SET statement_no = :no WHERE id = :id AND company_id = :cid')
                ->execute(['no' => 'ST-' . $clinicId . '-' . str_replace('-', '', $month) . '-' . $id, 'id' => $id, 'cid' => $companyId]);
            $ins = $pdo->prepare(
                'INSERT INTO corp_statement_lines (company_id, statement_id, booking_id, case_id, employment_id, exam_type_id, service_date, price, price_agreed)
                 VALUES (:cid, :sid, :bid, :case, :eid, :ex, :d, :p, :agreed)'
            );
            foreach ($lines as $l) {
                $ins->execute(['cid' => $companyId, 'sid' => $id, 'bid' => (int) $l['booking_id'], 'case' => (int) $l['case_id'], 'eid' => (int) $l['employment_id'],
                    'ex' => (int) $l['exam_type_id'], 'd' => $l['completed_on'], 'p' => $l['price'], 'agreed' => $l['price_agreed']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[StatementService] generate: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Some of these checks were just put on another statement. Please try again.'];
        }
        self::renderPdf($id);

        return ['ok' => true, 'id' => $id];
    }

    /** send | paid | void (void only for drafts: frees the checks). @return array{ok: bool, error?: string} */
    public static function act(int $clinicId, int $userId, int $id, string $action): array
    {
        $s = self::find($clinicId, $id);
        if ($s === null) {
            return ['ok' => false, 'error' => 'Statement not found.'];
        }
        $now = LocalDate::nowUtc();
        $pdo = Database::connection();
        $allowed = ['send' => ['draft'], 'paid' => ['sent', 'disputed'], 'void' => ['draft']];
        if (!in_array($s['status'], $allowed[$action] ?? [], true)) {
            return ['ok' => false, 'error' => 'Not possible for a ' . strtolower(self::STATUSES[$s['status']]) . ' statement.'];
        }
        $cid = (int) $s['company_id'];
        if ($action === 'void') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM corp_statement_lines WHERE statement_id = :id AND company_id = :cid')->execute(['id' => $id, 'cid' => $cid]);
            $pdo->prepare("UPDATE corp_provider_statements SET status = 'void', updated_at = :up WHERE id = :id AND company_id = :cid")->execute(['up' => $now, 'id' => $id, 'cid' => $cid]);
            $pdo->commit();

            return ['ok' => true];
        }
        $set = $action === 'send' ? "status = 'sent', sent_at = :at" : "status = 'paid', paid_at = :at";
        $pdo->prepare("UPDATE corp_provider_statements SET {$set}, updated_at = :up WHERE id = :id AND company_id = :cid")
            ->execute(['at' => $now, 'up' => $now, 'id' => $id, 'cid' => $cid]);
        CorpAudit::log($cid, 'provider', $userId, $action === 'send' ? 'statement_sent' : 'statement_paid', 'corp_provider_statement', $id,
            ['clinic' => $s['clinic_name'], 'statement' => $s['statement_no'], 'total' => $s['currency_code'] . ' ' . number_format((float) $s['total'], 2)]);
        if ($action === 'send') {
            self::emailCompany($s);
        }

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null a statement of this clinic */
    public static function find(int $clinicId, int $id): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT s.*, t.name AS clinic_name, COALESCE(NULLIF(c.trade_name, ''), c.legal_name) AS company_name
               FROM corp_provider_statements s JOIN tenants t ON t.id = s.clinic_id JOIN corp_companies c ON c.id = s.company_id
              WHERE s.id = :id AND s.clinic_id = :clinic LIMIT 1"
        );
        $st->execute(['id' => $id, 'clinic' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /** Stream the PDF. Caller has checked access (clinic or company). */
    public static function pdfResponse(array $s): Response
    {
        $root = realpath(self::root());
        $abs = $root !== false && !empty($s['pdf_path']) ? realpath($root . '/' . $s['pdf_path']) : false;
        if ($abs === false || !str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return Response::html('Statement file not found', 404);
        }

        return new Response((string) file_get_contents($abs), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) $s['statement_no']) . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function root(): string
    {
        return dirname(Application::basePath()) . '/storage/corporate_statements';
    }

    // ------------------------------------------------------------------

    private static function renderPdf(int $id): void
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT s.*, t.name AS clinic_name, t.address AS clinic_address, t.gstin, t.registration_number, t.phone AS clinic_phone, t.email AS clinic_email,
                    c.legal_name, c.address_line1, c.city, c.tax_reg_no
               FROM corp_provider_statements s JOIN tenants t ON t.id = s.clinic_id JOIN corp_companies c ON c.id = s.company_id
              WHERE s.id = :id AND s.company_id = c.id LIMIT 1"
        );
        $st->execute(['id' => $id]);
        $s = $st->fetch();
        if (!$s || !class_exists(Mpdf::class)) {
            return;
        }
        $ls = $pdo->prepare(
            'SELECT sl.service_date, sl.price, sl.price_agreed, e.full_name, e.employee_no, et.name AS exam_name
               FROM corp_statement_lines sl JOIN corp_employments e ON e.id = sl.employment_id AND e.company_id = sl.company_id
               JOIN oh_exam_types et ON et.id = sl.exam_type_id
              WHERE sl.company_id = :cid AND sl.statement_id = :sid ORDER BY sl.service_date, e.full_name'
        );
        $ls->execute(['cid' => (int) $s['company_id'], 'sid' => $id]);
        $h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $money = static fn ($v): string => $h($s['currency_code']) . ' ' . number_format((float) $v, 2);
        $rows = '';
        foreach ($ls->fetchAll() as $l) {
            $rows .= '<tr><td>' . $h(date('d M Y', strtotime((string) $l['service_date']))) . '</td><td>' . $h($l['full_name']) . ' <span style="color:#64748b">(' . $h($l['employee_no']) . ')</span></td>'
                . '<td>' . $h($l['exam_name']) . '</td><td align="right">' . ((int) $l['price_agreed'] === 1 ? $money($l['price']) : '<span style="color:#b45309">price not agreed</span>') . '</td></tr>';
        }
        $html = '<div style="font-family:sans-serif;font-size:9.5pt;color:#0f172a;">'
            . '<table width="100%"><tr><td><div style="font-size:14pt;font-weight:bold;">' . $h($s['clinic_name']) . '</div><div style="color:#475569;font-size:8.5pt;">' . $h($s['clinic_address'])
            . ($s['gstin'] ? '<br>GSTIN ' . $h($s['gstin']) : '') . ($s['clinic_phone'] ? '<br>' . $h($s['clinic_phone']) : '') . ($s['clinic_email'] ? ' · ' . $h($s['clinic_email']) : '') . '</div></td>'
            . '<td align="right" style="color:#475569;font-size:8.5pt;">Statement no.<br><b style="font-size:11pt;color:#0f172a;">' . $h($s['statement_no']) . '</b><br>Period ' . $h(date('d M Y', strtotime((string) $s['period_from'])))
            . ' – ' . $h(date('d M Y', strtotime((string) $s['period_to']))) . '</td></tr></table>'
            . '<div style="margin:14px 0 6px;font-size:15pt;font-weight:bold;">Statement of occupational health services</div>'
            . '<div style="margin-bottom:10px;">To: <b>' . $h($s['legal_name']) . '</b>' . ($s['address_line1'] ? ', ' . $h($s['address_line1']) : '') . ($s['city'] ? ', ' . $h($s['city']) : '')
            . ($s['tax_reg_no'] ? ' · Tax no. ' . $h($s['tax_reg_no']) : '') . '</div>'
            . '<table width="100%" cellpadding="5" style="border-collapse:collapse;"><tr style="background:#f1f5f9;"><th align="left">Date</th><th align="left">Employee</th><th align="left">Check</th><th align="right">Agreed price</th></tr>'
            . $rows . '<tr style="font-weight:bold;border-top:1px solid #0f172a;"><td colspan="3">Total (' . (int) $s['lines_count'] . ' checks)</td><td align="right">' . $money($s['total']) . '</td></tr></table>'
            . '<div style="margin-top:14px;color:#64748b;font-size:8pt;">A record of services under your agreement with ' . $h($s['clinic_name']) . '. Payment is made directly between the two parties as agreed; '
            . 'eClinicPro does not collect or pass on payments. Taxes, if any, as per the clinic\'s own invoice. No medical details are shown.</div></div>';
        $dir = self::root() . '/' . (int) $s['company_id'];
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return;
        }
        if (!is_file(self::root() . '/.htaccess')) {
            @file_put_contents(self::root() . '/.htaccess', "Require all denied\n");
        }
        $uid = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
        $tmpDir = sys_get_temp_dir() . '/mpdf-' . $uid;
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            $tmpDir = sys_get_temp_dir();
        }
        $name = 'st-' . $id . '-' . bin2hex(random_bytes(6)) . '.pdf';
        try {
            $mpdf = new Mpdf(['format' => 'A4', 'tempDir' => $tmpDir, 'margin_top' => 14, 'margin_bottom' => 14]);
            $mpdf->WriteHTML($html);
            $mpdf->Output($dir . '/' . $name, \Mpdf\Output\Destination::FILE);
            $pdo->prepare('UPDATE corp_provider_statements SET pdf_path = :p WHERE id = :id AND company_id = :cid')
                ->execute(['p' => (int) $s['company_id'] . '/' . $name, 'id' => $id, 'cid' => (int) $s['company_id']]);
        } catch (\Throwable $e) {
            error_log('[StatementService] pdf: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed>|null the company's link to this clinic (any status that has history), with currency */
    private static function activeOrPastLink(int $clinicId, int $companyId): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT l.id, l.status, c.currency_code FROM corp_provider_links l JOIN corp_companies c ON c.id = l.company_id
              WHERE l.company_id = :cid AND l.clinic_id = :clinic AND l.status IN ('active', 'ended') LIMIT 1"
        );
        $st->execute(['cid' => $companyId, 'clinic' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /** @param array<string, mixed> $s */
    private static function emailCompany(array $s): void
    {
        $st = Database::connection()->prepare("SELECT id, email FROM corp_users WHERE company_id = :cid AND status = 'active'");
        $st->execute(['cid' => (int) $s['company_id']]);
        foreach ($st->fetchAll() as $u) {
            if (!isset(\App\Services\Corporate\CorpAuth::permissionsFor((int) $s['company_id'], (int) $u['id'])['billing.view'])) {
                continue;
            }
            CorpMailer::send((string) $u['email'], 'Statement ' . $s['statement_no'] . ' from ' . $s['clinic_name'], 'New clinic statement',
                [$s['clinic_name'] . ' sent a statement for health checks from ' . date('d M Y', strtotime((string) $s['period_from'])) . ' to ' . date('d M Y', strtotime((string) $s['period_to'])) . ': '
                    . (int) $s['lines_count'] . ' checks, ' . $s['currency_code'] . ' ' . number_format((float) $s['total'], 2) . '.',
                    'It is a record of the checks done at the agreed prices. Payment is between you and the clinic; eClinicPro doesn\'t handle it. If something looks wrong, mark it as disputed with a note.'],
                CorpMailer::appUrl() . '/company/reports/spend', 'Open the statement');
        }
    }
}
