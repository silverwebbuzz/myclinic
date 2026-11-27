<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\RxWhatsAppService;
use App\Support\MessagingSettings;
use App\Support\View;
use PDO;

/**
 * /admin/rx-whatsapp (super-admin) — WhatsApp prescription allowance:
 * default free per month, pack prices, per-clinic usage + override/on-off,
 * and manual credit grants.
 */
final class RxWhatsAppAdminController
{
    public function index(Request $request): Response
    {
        $pdo = Database::connection();
        $period = date('Y-m');
        $q = trim((string) ($request->query['q'] ?? ''));

        $sql = 'SELECT t.id, t.name, t.phone,
                       a.paid_balance, a.monthly_free_override, a.is_enabled,
                       COALESCE(m.free_used, 0) AS free_used,
                       (SELECT COUNT(*) FROM rx_wa_ledger l
                         WHERE l.clinic_id = t.id AND l.period_ym = ? AND l.entry = \'paid_use\') AS paid_used_month
                  FROM tenants t
                  LEFT JOIN rx_wa_accounts a ON a.clinic_id = t.id
                  LEFT JOIN rx_wa_monthly m ON m.clinic_id = t.id AND m.period_ym = ?';
        $params = [$period, $period];
        if ($q !== '') {
            $sql .= ' WHERE t.name LIKE ? OR t.id = ?';
            $params[] = '%' . $q . '%';
            $params[] = ctype_digit($q) ? (int) $q : 0;
        }
        $sql .= ' ORDER BY free_used DESC, a.paid_balance DESC, t.id DESC LIMIT 200';

        $clinics = [];
        $error = null;
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $error = 'Tables missing — run app/database/patches/2026_11_27_rx_whatsapp_allowance.sql. (' . $e->getMessage() . ')';
        }

        $totals = ['free_used' => 0, 'paid_used' => 0, 'revenue' => 0.0];
        try {
            $t = $pdo->prepare("SELECT
                    COALESCE(SUM(CASE WHEN entry = 'free_use' THEN 1 WHEN entry = 'free_refund' THEN -1 ELSE 0 END), 0) AS free_used,
                    COALESCE(SUM(CASE WHEN entry = 'paid_use' THEN 1 WHEN entry = 'paid_refund' THEN -1 ELSE 0 END), 0) AS paid_used
                  FROM rx_wa_ledger WHERE period_ym = ?");
            $t->execute([$period]);
            $row = $t->fetch(PDO::FETCH_ASSOC) ?: [];
            $totals['free_used'] = (int) ($row['free_used'] ?? 0);
            $totals['paid_used'] = (int) ($row['paid_used'] ?? 0);
            $r = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM rx_wa_orders WHERE status = 'paid' AND DATE_FORMAT(paid_at, '%Y-%m') = ?");
            $r->execute([$period]);
            $totals['revenue'] = (float) $r->fetchColumn();
        } catch (\Throwable $e) {
            // tables missing — error already shown
        }

        $orders = [];
        try {
            $orders = $pdo->query(
                "SELECT o.*, t.name AS clinic_name, si.id AS invoice_id, si.invoice_no
                   FROM rx_wa_orders o
                   LEFT JOIN tenants t ON t.id = o.clinic_id
                   LEFT JOIN saas_invoices si ON si.gateway_order_id = o.razorpay_order_id
                  ORDER BY o.id DESC LIMIT 30"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            // tables missing — error already shown
        }

        return Response::html(View::render('admin/rx_whatsapp', [
            'orders' => $orders,
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'clinics' => $clinics,
            'defaultFree' => RxWhatsAppService::defaultFreeMonthly(),
            'attachPdf' => RxWhatsAppService::attachPdf(),
            'packs' => RxWhatsAppService::packs(),
            'ready' => RxWhatsAppService::channelReady(),
            'totals' => $totals,
            'q' => $q,
            'message' => (string) ($request->query['message'] ?? ''),
            'error' => $error ?? (string) ($request->query['error'] ?? ''),
        ]));
    }

    /** POST /admin/rx-whatsapp/settings — default free/month + pack prices. */
    public function saveSettings(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/rx-whatsapp');
        }
        $free = max(0, min(100000, (int) ($request->post['free_monthly'] ?? 100)));
        MessagingSettings::set('rx_wa_free_monthly', (string) $free);
        MessagingSettings::set('rx_wa_attach_pdf', !empty($request->post['attach_pdf']) ? '1' : '0');

        $packs = [];
        $qtys = (array) ($request->post['pack_qty'] ?? []);
        $prices = (array) ($request->post['pack_price'] ?? []);
        foreach ($qtys as $i => $qty) {
            $qty = (int) $qty;
            $price = round((float) ($prices[$i] ?? 0), 2);
            if ($qty > 0 && $price > 0) {
                $packs[] = ['qty' => $qty, 'price' => $price];
            }
        }
        usort($packs, static fn ($a, $b) => $a['qty'] <=> $b['qty']);
        MessagingSettings::set('rx_wa_packs', json_encode($packs));

        return Response::redirect('/admin/rx-whatsapp?message=' . urlencode('Settings saved.'));
    }

    /** POST /admin/rx-whatsapp/clinic/{id} — per-clinic override, on/off, grant/deduct. */
    public function saveClinic(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/rx-whatsapp');
        }
        $clinicId = (int) $id;
        $override = trim((string) ($request->post['monthly_free_override'] ?? ''));
        $enabled = !empty($request->post['is_enabled']) ? 1 : 0;

        try {
            Database::connection()->prepare(
                'INSERT INTO rx_wa_accounts (clinic_id, monthly_free_override, is_enabled) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE monthly_free_override = VALUES(monthly_free_override), is_enabled = VALUES(is_enabled)'
            )->execute([$clinicId, $override === '' ? null : max(0, (int) $override), $enabled]);

            $adjust = (int) ($request->post['adjust'] ?? 0);
            $note = trim((string) ($request->post['note'] ?? '')) ?: 'Admin adjustment';
            if ($adjust > 0) {
                RxWhatsAppService::credit($clinicId, $adjust, 'admin_grant', null, null, $note);
            } elseif ($adjust < 0) {
                RxWhatsAppService::deduct($clinicId, -$adjust, $note);
            }
        } catch (\Throwable $e) {
            return Response::redirect('/admin/rx-whatsapp?error=' . urlencode($e->getMessage()));
        }

        return Response::redirect('/admin/rx-whatsapp?message=' . urlencode('Clinic #' . $clinicId . ' updated.') . '#c' . $clinicId);
    }
}
