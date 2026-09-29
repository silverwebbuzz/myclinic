<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\ProductService;
use App\Services\Store\SettlementService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/** /admin/store/payouts + /admin/store/reports — seller settlement (super-admin). */
final class StorePayoutAdminController
{
    public function index(Request $request): Response
    {
        SettlementService::releaseMatured();
        $status = (string) ($request->query['status'] ?? '');
        try {
            $balances = SettlementService::allBalances();
            $sql = 'SELECT p.*, v.display_name FROM store_payouts p JOIN store_vendors v ON v.id = p.vendor_id'
                . ($status !== '' ? ' WHERE p.status = :s' : '') . ' ORDER BY p.id DESC LIMIT 200';
            $st = Database::connection()->prepare($sql);
            $st->execute($status !== '' ? ['s' => $status] : []);
            $payouts = $st->fetchAll();
            $missing = false;
            try {
                // Seller-requested payouts get a "Requested" tag (table from patch 2026_10_03).
                $requested = array_flip(array_map('intval', Database::connection()->query('SELECT payout_id FROM store_payout_requests')->fetchAll(\PDO::FETCH_COLUMN)));
                foreach ($payouts as &$po) {
                    $po['is_requested'] = isset($requested[(int) $po['id']]);
                }
                unset($po);
            } catch (\Throwable) {
            }
        } catch (\Throwable $e) {
            error_log('[StorePayoutAdmin::index] ' . $e->getMessage());
            $balances = $payouts = [];
            $missing = true;
        }

        return $this->render('admin/store_payouts', [
            'balances' => $balances, 'payouts' => $payouts, 'status' => $status, 'tableMissing' => $missing,
            'minPayout' => \App\Services\Store\StoreSettings::int('store_min_payout_paise', 10000),
            'batchSkipped' => SessionFlash::pull('store_batch_skipped') ?? [],
        ]);
    }

    public function createBatch(Request $request): Response
    {
        $res = SettlementService::createBatch($this->adminId());
        SessionFlash::put('store_ok', $res['created'] . ' payout(s) created as drafts. Review, approve, transfer, then mark paid with the UTR.');
        if ($res['skipped']) {
            SessionFlash::put('store_batch_skipped', $res['skipped']);
        }

        return Response::redirect('/admin/store/payouts');
    }

    public function adjust(Request $request): Response
    {
        $amount = ProductService::toPaise(ltrim((string) ($request->post['amount'] ?? ''), '-'));
        $sign = str_starts_with(trim((string) ($request->post['amount'] ?? '')), '-') ? -1 : 1;
        $res = $amount === null
            ? ['ok' => false, 'error' => 'Enter an amount like 250 or -250.']
            : SettlementService::adjust((int) ($request->post['vendor_id'] ?? 0), $sign * $amount, (string) ($request->post['memo'] ?? ''), $this->adminId());
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Adjustment added to the seller\'s available balance.' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/payouts');
    }

    public function show(Request $request, string $id): Response
    {
        $p = QueryBuilder::table('store_payouts')->where('id', '=', (int) $id)->first();
        if ($p === null) {
            return Response::html('Payout not found', 404);
        }

        return $this->render('admin/store_payout_detail', [
            'payout' => $p,
            'vendor' => VendorService::find((int) $p['vendor_id']),
            'bank' => VendorService::primaryBank((int) $p['vendor_id']),
            'entries' => SettlementService::ledger((int) $p['vendor_id'], 2000, (int) $p['id']),
            'request' => SettlementService::requestFor((int) $p['id']),
        ]);
    }

    public function transition(Request $request, string $id, string $action): Response
    {
        $res = SettlementService::transition((int) $id, $action, (string) ($request->post['reference'] ?? ''), (string) ($request->post['note'] ?? ''), $this->adminId());
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Payout updated.' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/payouts/' . (int) $id);
    }

    public function statement(Request $request, string $id): Response
    {
        $p = QueryBuilder::table('store_payouts')->where('id', '=', (int) $id)->first();

        return $p === null ? Response::html('Not found', 404) : self::csv(SettlementService::statementCsv($p), $p['payout_no'] . '.csv');
    }

    public function report(Request $request): Response
    {
        try {
            $rows = SettlementService::monthlyReport(12);
        } catch (\Throwable $e) {
            error_log('[StorePayoutAdmin::report] ' . $e->getMessage());
            $rows = [];
        }

        return $this->render('admin/store_report', ['rows' => $rows]);
    }

    public static function csv(string $body, string $filename): Response
    {
        return (new Response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^\w.\-]/', '_', $filename) . '"',
            'Cache-Control' => 'private, no-store',
        ]));
    }

    private function adminId(): int
    {
        return (int) (RequestContext::superAdmin()['id'] ?? 0);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
