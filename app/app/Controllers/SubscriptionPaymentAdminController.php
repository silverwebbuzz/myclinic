<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\BillingGatewayService;
use App\Services\CsrfService;
use App\Services\DiscountService;
use App\Services\SaasInvoiceService;
use App\Support\View;
use PDO;

/**
 * SubscriptionPaymentAdminController — /admin/payments (super-admin only).
 *
 * Every plan purchase a clinic makes (signup checkout, renewals, upgrades)
 * from saas_invoices: who paid, how much, the Razorpay order + payment
 * reference, and whether it was their first payment or a repeat.
 *
 * "Re-check" asks Razorpay about a pending/failed order and, if it was in
 * fact captured (webhook + return-URL both missed), activates the plan and
 * marks the invoice paid — same path as the normal return URL. Never offered
 * on paid rows: verifyRazorpayOrder re-applies the plan term.
 */
final class SubscriptionPaymentAdminController
{
    private const PER_PAGE = 50;
    private const STATUSES = ['paid', 'pending', 'failed'];

    public function index(Request $request): Response
    {
        DiscountService::ensureSchema(); // list joins the redemption tables
        $filters = $this->filters($request);
        [$whereSql, $params] = $this->where($filters);
        $pdo = Database::connection();

        $rows = [];
        $total = 0;
        $stats = ['paid_count' => 0, 'paid_amount' => 0.0, 'pending' => 0, 'failed' => 0, 'repeat_clinics' => 0];
        $tableMissing = false;
        $pages = 1;
        $page = $filters['page'];

        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM saas_invoices i LEFT JOIN tenants t ON t.id = i.clinic_id $whereSql");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();
            $pages = max(1, (int) ceil($total / self::PER_PAGE));
            $page = min($page, $pages);
            $offset = ($page - 1) * self::PER_PAGE;

            $stmt = $pdo->prepare($this->listSql($whereSql) . ' LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Headline numbers ignore the filters — the whole-business view.
            $s = $pdo->query(
                "SELECT
                    SUM(status = 'paid') AS paid_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid_amount,
                    SUM(status = 'pending') AS pending,
                    SUM(status = 'failed') AS failed
                 FROM saas_invoices"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['paid_count'] = (int) ($s['paid_count'] ?? 0);
            $stats['paid_amount'] = (float) ($s['paid_amount'] ?? 0);
            $stats['pending'] = (int) ($s['pending'] ?? 0);
            $stats['failed'] = (int) ($s['failed'] ?? 0);
            $stats['repeat_clinics'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM (SELECT clinic_id FROM saas_invoices WHERE status = 'paid'
                  GROUP BY clinic_id HAVING COUNT(*) > 1) x"
            )->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[SubscriptionPaymentAdmin] list failed: ' . $e->getMessage());
            $tableMissing = true;
        }

        $clinicName = null;
        if ($filters['clinic'] > 0) {
            $clinicName = QueryBuilder::table('tenants')->where('id', '=', $filters['clinic'])->first()['name'] ?? null;
        }

        return Response::html(View::render('admin/payments', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filters' => $filters,
            'clinicName' => $clinicName,
            'stats' => $stats,
            'tableMissing' => $tableMissing,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** GET /admin/payments/export — CSV of the current filter (no paging). */
    public function export(Request $request): Response
    {
        DiscountService::ensureSchema();
        [$whereSql, $params] = $this->where($this->filters($request));

        $rows = [];
        try {
            $stmt = Database::connection()->prepare($this->listSql($whereSql));
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { /* table missing → empty export */ }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, [
            'Invoice No', 'Created', 'Paid At', 'Status', 'Clinic ID', 'Clinic', 'Paid By', 'Email', 'Phone',
            'Plan', 'Cycle', 'Period Start', 'Period End', 'Base (INR)', 'Tax (INR)', 'Amount (INR)',
            'Discount Code', 'Discount (INR)', 'Razorpay Order ID', 'Razorpay Payment ID', 'Clinic Payment #',
        ]);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['invoice_no'] ?? '', $r['created_at'] ?? '', $r['paid_at'] ?? '', $r['status'] ?? '',
                $r['clinic_id'] ?? '', $r['clinic_name'] ?? '', $r['payer_name'] ?? '',
                $r['payer_email'] ?? '', $r['payer_phone'] ?? '',
                $r['plan_id'] ?? '', $r['billing_cycle'] ?? '', $r['period_start'] ?? '', $r['period_end'] ?? '',
                $r['base_amount'] ?? '', $r['tax_amount'] ?? '', $r['amount'] ?? '',
                $r['discount_code'] ?? '', $r['discount_amount'] ?? '',
                $r['gateway_order_id'] ?? '', $r['gateway_payment_id'] ?? '',
                ($r['status'] ?? '') === 'paid' ? (int) $r['pay_seq'] : '',
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="subscription-payments-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /** POST /admin/payments/{id}/recheck — ask Razorpay if a pending/failed order was captured. */
    public function recheck(Request $request, string $id): Response
    {
        $back = $this->backUrl($request);
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect($back);
        }

        $invoice = QueryBuilder::table('saas_invoices')->where('id', '=', (int) $id)->first();
        if ($invoice === null) {
            return Response::redirect($this->withMessage($back, 'invoice_not_found'));
        }
        if (($invoice['status'] ?? '') === 'paid') {
            return Response::redirect($this->withMessage($back, 'already_paid_—_nothing_to_check'));
        }
        if (($invoice['gateway'] ?? '') !== 'razorpay' || empty($invoice['gateway_order_id'])) {
            return Response::redirect($this->withMessage($back, 'no_razorpay_order_on_this_invoice'));
        }
        if (!(BillingGatewayService::status()['razorpay_set'] ?? false)) {
            return Response::redirect($this->withMessage($back, 'razorpay_keys_not_configured'));
        }

        $ok = BillingGatewayService::verifyRazorpayOrder((string) $invoice['gateway_order_id']);
        if ($ok) {
            AuditService::log($request, 'UPDATE', 'saas_invoices', (int) $invoice['id']);
        }

        return Response::redirect($this->withMessage(
            $back,
            $ok ? 'payment_found_—_invoice_' . $invoice['invoice_no'] . '_marked_paid_and_plan_activated'
                : 'razorpay_shows_no_captured_payment_for_' . $invoice['gateway_order_id'],
        ));
    }

    /** GET /admin/payments/{id}/pdf — the invoice PDF (paid invoices only). */
    public function pdf(Request $request, string $id): Response
    {
        $invoice = QueryBuilder::table('saas_invoices')->where('id', '=', (int) $id)->first();
        if ($invoice === null || ($invoice['status'] ?? '') !== 'paid') {
            return Response::html('Invoice not found or not paid', 404);
        }

        $abs = $invoice['pdf_path'] ?? null;
        if (empty($abs) || !is_file((string) $abs)) {
            // Renders + persists pdf_path (emails the clinic only if it never was).
            SaasInvoiceService::generateAndEmail((int) $invoice['id']);
            $invoice = QueryBuilder::table('saas_invoices')->where('id', '=', (int) $id)->first();
            $abs = $invoice['pdf_path'] ?? null;
        }
        if (empty($abs) || !is_file((string) $abs)) {
            return Response::html('Invoice PDF unavailable', 404);
        }

        $name = 'eclinicpro-invoice-' . ($invoice['invoice_no'] ?? $id) . '.pdf';

        return Response::download((string) $abs, $name)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $name . '"');
    }

    /**
     * Rows for the list/export. pay_seq = this clinic's Nth paid invoice
     * (1 = first purchase, 2+ = renewal/repeat). dup_nearby flags another paid
     * invoice for the same clinic within 3 days — a likely double charge.
     */
    private function listSql(string $whereSql): string
    {
        return "SELECT i.*,
                       t.name AS clinic_name, t.email AS payer_email, t.phone AS payer_phone,
                       (SELECT u.name FROM users u WHERE u.clinic_id = i.clinic_id
                         ORDER BY (u.role = 'admin') DESC, u.id ASC LIMIT 1) AS payer_name,
                       (SELECT COUNT(*) FROM saas_invoices p
                         WHERE p.clinic_id = i.clinic_id AND p.status = 'paid' AND p.id <= i.id) AS pay_seq,
                       (SELECT COUNT(*) FROM saas_invoices p
                         WHERE p.clinic_id = i.clinic_id AND p.status = 'paid') AS clinic_paid_total,
                       (SELECT COUNT(*) FROM saas_invoices p
                         WHERE p.clinic_id = i.clinic_id AND p.status = 'paid' AND p.id <> i.id
                           AND i.status = 'paid'
                           AND ABS(TIMESTAMPDIFF(HOUR, p.paid_at, i.paid_at)) <= 72) AS dup_nearby,
                       (SELECT d.code FROM plan_discount_redemptions r
                          JOIN plan_discount_codes d ON d.id = r.discount_code_id
                         WHERE r.gateway_order_id = i.gateway_order_id LIMIT 1) AS discount_code,
                       (SELECT r.discount_amount FROM plan_discount_redemptions r
                         WHERE r.gateway_order_id = i.gateway_order_id LIMIT 1) AS discount_amount
                  FROM saas_invoices i
             LEFT JOIN tenants t ON t.id = i.clinic_id
                $whereSql
              ORDER BY i.created_at DESC, i.id DESC";
    }

    /** @return array{q: string, status: string, from: string, to: string, clinic: int, page: int} */
    private function filters(Request $request): array
    {
        $status = (string) ($request->query['status'] ?? '');
        $date = static fn ($v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';

        return [
            'q' => trim((string) ($request->query['q'] ?? '')),
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'from' => $date($request->query['from'] ?? ''),
            'to' => $date($request->query['to'] ?? ''),
            'clinic' => max(0, (int) ($request->query['clinic'] ?? 0)),
            'page' => max(1, (int) ($request->query['page'] ?? 1)),
        ];
    }

    /**
     * @param array{q: string, status: string, from: string, to: string, clinic: int, page: int} $f
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function where(array $f): array
    {
        $where = [];
        $params = [];
        if ($f['q'] !== '') {
            // Native prepares: one placeholder per occurrence (no reuse).
            $like = '%' . $f['q'] . '%';
            $where[] = '(i.gateway_payment_id LIKE :q1 OR i.gateway_order_id LIKE :q2 OR i.invoice_no LIKE :q3
                         OR t.name LIKE :q4 OR t.email LIKE :q5 OR t.phone LIKE :q6)';
            for ($n = 1; $n <= 6; $n++) {
                $params[":q$n"] = $like;
            }
        }
        if ($f['status'] !== '') {
            $where[] = 'i.status = :status';
            $params[':status'] = $f['status'];
        }
        if ($f['clinic'] > 0) {
            $where[] = 'i.clinic_id = :clinic';
            $params[':clinic'] = $f['clinic'];
        }
        if ($f['from'] !== '') {
            $where[] = 'DATE(i.created_at) >= :from';
            $params[':from'] = $f['from'];
        }
        if ($f['to'] !== '') {
            $where[] = 'DATE(i.created_at) <= :to';
            $params[':to'] = $f['to'];
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /** Return to the page the action came from (list or clinic detail) — local paths only. */
    private function backUrl(Request $request): string
    {
        $back = (string) ($request->post['back'] ?? '');

        return preg_match('#^/admin/(payments|clinics/\d+)(\?[^\r\n]*)?$#', $back) ? $back : '/admin/payments';
    }

    private function withMessage(string $url, string $message): string
    {
        $url = preg_replace('/([?&])message=[^&]*(&|$)/', '$1', $url) ?? $url;
        $url = rtrim($url, '?&');

        return $url . (str_contains($url, '?') ? '&' : '?') . 'message=' . urlencode($message);
    }
}
