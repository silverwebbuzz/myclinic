<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Razorpay for store orders (Orders API + Checkout.js), on the SAME Razorpay
 * account as clinic subscriptions. Store orders are tagged notes.purpose = "store"
 * so the shared /webhooks/razorpay endpoint can route them here.
 *
 * Money truth = Razorpay's API. The browser's "success" callback is only a
 * hint: we verify its signature AND fetch the payment from Razorpay before
 * marking anything paid. markPaid() is idempotent, so the checkout callback,
 * the webhook and the reconciliation check can all call it safely.
 */
final class StorePaymentService
{
    private const API = 'https://api.razorpay.com/v1';

    public static function configured(): bool
    {
        return self::key() !== '' && self::secret() !== '';
    }

    /**
     * The store's own on/off for live payments, independent of the main site.
     * Credentials are NOT entered in admin: they come from app/.env —
     *   live    = RAZORPAY_LIVE_KEY_ID/_SECRET, or the site's RAZORPAY_KEY_ID/_SECRET when that is an rzp_live_ key
     *   sandbox = RAZORPAY_TEST_KEY_ID/_SECRET, or the site's RAZORPAY_KEY_ID/_SECRET when that is an rzp_test_ key
     * (Razorpay live and test mode always use different key pairs.)
     * Switch only when no store payment is in progress: refunds use the keys active at that time.
     */
    public static function mode(): string
    {
        $m = StoreSettings::get('store_razorpay_mode');
        if ($m === 'production' || $m === 'sandbox') {
            return $m;
        }

        return self::siteMode();   // not chosen yet: follow the main site
    }

    /** The main site's mode (its single key pair in app/.env). */
    public static function siteMode(): string
    {
        $key = (string) ($_ENV['RAZORPAY_KEY_ID'] ?? '');
        if (str_starts_with($key, 'rzp_live_')) {
            return 'production';
        }
        if (str_starts_with($key, 'rzp_test_')) {
            return 'sandbox';
        }

        return strtolower((string) ($_ENV['RAZORPAY_ENV'] ?? 'sandbox')) === 'production' ? 'production' : 'sandbox';
    }

    /** @return array{mode: string, site_mode: string, configured: bool, key_hint: string, live_ready: bool, sandbox_ready: bool} */
    public static function settingsStatus(): array
    {
        [$live] = self::pair('live');
        [$test] = self::pair('test');

        return [
            'mode' => self::mode(), 'site_mode' => self::siteMode(), 'configured' => self::configured(),
            'key_hint' => self::key() !== '' ? substr(self::key(), 0, 13) . '…' : '',
            'live_ready' => $live !== '', 'sandbox_ready' => $test !== '',
        ];
    }

    /** @return array{ok: bool, error?: string} */
    public static function setMode(string $mode): array
    {
        if (!in_array($mode, ['production', 'sandbox'], true)) {
            return ['ok' => false, 'error' => 'Choose Live or Sandbox.'];
        }
        [$id, $secret] = self::pair($mode === 'production' ? 'live' : 'test');
        if ($id === '' || $secret === '') {
            return ['ok' => false, 'error' => $mode === 'production'
                ? 'No live keys found in app/.env (RAZORPAY_LIVE_KEY_ID / RAZORPAY_LIVE_KEY_SECRET, or rzp_live_ keys in RAZORPAY_KEY_ID).'
                : 'No sandbox keys found in app/.env. Add RAZORPAY_TEST_KEY_ID and RAZORPAY_TEST_KEY_SECRET (your rzp_test_ keys) once.'];
        }
        StoreSettings::set('store_razorpay_mode', $mode);
        StoreAudit::log('store.razorpay_mode', 'setting', null, null, ['mode' => $mode]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} checks the active keys with a harmless API call */
    public static function testConnection(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'error' => 'No keys configured for the selected mode.'];
        }
        $res = self::api('GET', '/orders?count=1');
        if (isset($res['error'])) {
            return ['ok' => false, 'error' => (string) ($res['error']['description'] ?? 'Razorpay rejected the keys.')];
        }

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // 1. Start payment
    // ------------------------------------------------------------------

    /**
     * Razorpay order for a pending store order (reused across retries).
     *
     * @return array{ok: bool, error?: string, checkout?: array<string, mixed>}
     */
    public static function start(array $order): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'error' => 'Online payment isn\'t available right now. Please try again later.'];
        }
        if ($order['status'] !== 'pending_payment' || $order['payment_status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This order can no longer be paid.'];
        }
        if (!empty($order['expires_at']) && strtotime((string) $order['expires_at']) <= time()) {
            return ['ok' => false, 'error' => 'The payment window for this order has closed. Please check out again from your cart.'];
        }
        $amount = (int) $order['grand_total_paise'];
        if ($amount < 100) {
            return ['ok' => false, 'error' => 'Order amount is too small to pay online.'];
        }

        // Reuse an open Razorpay order for the same amount (retries after a failed/closed attempt).
        $st = Database::connection()->prepare(
            "SELECT * FROM store_payments WHERE order_id = :o AND status IN ('created','attempted','failed')
               AND amount_paise = :a ORDER BY id DESC LIMIT 1"
        );
        $st->execute(['o' => (int) $order['id'], 'a' => $amount]);
        $pay = $st->fetch() ?: null;

        if ($pay === null) {
            $res = self::api('POST', '/orders', [
                'amount' => $amount,
                'currency' => 'INR',
                'receipt' => substr((string) $order['order_no'], 0, 40),
                'payment_capture' => 1,
                'notes' => ['purpose' => 'store', 'order_no' => (string) $order['order_no']],
            ]);
            if (empty($res['id'])) {
                error_log('[StorePayment] order create failed: ' . json_encode($res['error'] ?? $res));

                return ['ok' => false, 'error' => 'We couldn\'t start the payment. Please try again in a minute.'];
            }
            QueryBuilder::table('store_payments')->insert([
                'order_id' => (int) $order['id'],
                'gateway' => 'razorpay',
                'rzp_order_id' => (string) $res['id'],
                'amount_paise' => $amount,
                'currency' => 'INR',
                'status' => 'created',
            ]);
            $rzpOrderId = (string) $res['id'];
        } else {
            $rzpOrderId = (string) $pay['rzp_order_id'];
        }

        $phone = substr(preg_replace('/\D/', '', (string) $order['contact_phone']) ?? '', -10);

        return ['ok' => true, 'checkout' => [
            'key' => self::key(),
            'order_id' => $rzpOrderId,
            'amount' => $amount,
            'currency' => 'INR',
            'name' => 'eClinicPro Store',
            'description' => 'Order ' . $order['order_no'],
            'prefill' => array_filter([
                'name' => (string) $order['contact_name'],
                'contact' => $phone,
                'email' => (string) ($order['contact_email'] ?? ''),
            ]),
            'notes' => ['order_no' => (string) $order['order_no']],
            'theme' => ['color' => '#059669'],
            'mode' => self::mode(),
        ]];
    }

    // ------------------------------------------------------------------
    // 2. Confirm (checkout callback / webhook / reconciliation)
    // ------------------------------------------------------------------

    /** Checkout.js success callback: signature = HMAC_SHA256(order_id|payment_id, key_secret). */
    public static function verifyCheckoutSignature(string $rzpOrderId, string $paymentId, string $signature): bool
    {
        if (self::secret() === '' || $rzpOrderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rzpOrderId . '|' . $paymentId, self::secret()), $signature);
    }

    /**
     * Ask Razorpay whether this Razorpay order has a captured payment; if so,
     * mark our order paid. Returns the resulting state.
     *
     * @return 'paid'|'pending'|'failed'|'unknown'
     */
    public static function confirm(string $rzpOrderId): string
    {
        $pay = QueryBuilder::table('store_payments')->where('rzp_order_id', '=', $rzpOrderId)->first();
        if ($pay === null) {
            return 'unknown';
        }
        if (in_array($pay['status'], ['captured', 'partially_refunded', 'refunded'], true)) {
            return 'paid';
        }
        $list = self::api('GET', '/orders/' . rawurlencode($rzpOrderId) . '/payments');
        if (!isset($list['items']) || !is_array($list['items'])) {
            return 'unknown';   // API unreachable — webhook / next check will catch up
        }
        $failed = null;
        foreach ($list['items'] as $p) {
            if (($p['status'] ?? '') === 'captured') {
                return self::markPaid($pay, $p) ? 'paid' : 'unknown';
            }
            if (($p['status'] ?? '') === 'failed') {
                $failed = $p;
            }
        }
        if ($failed !== null) {
            self::markAttemptFailed($pay, $failed);

            return 'failed';
        }

        return 'pending';
    }

    /**
     * Idempotent "payment captured" transition. Only the call that flips
     * payment_status pending → paid does the work (stock commit, seller
     * sub-orders, cart clear, emails).
     *
     * @param array<string, mixed> $pay store_payments row
     * @param array<string, mixed> $rzpPayment Razorpay payment entity (status captured)
     */
    public static function markPaid(array $pay, array $rzpPayment): bool
    {
        $pdo = Database::connection();
        $orderId = (int) $pay['order_id'];
        $paymentId = (string) ($rzpPayment['id'] ?? '');
        $amount = (int) ($rzpPayment['amount'] ?? 0);
        $refundNeeded = false;

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM store_orders WHERE id = :id FOR UPDATE');
            $st->execute(['id' => $orderId]);
            $order = $st->fetch();
            if (!$order) {
                $pdo->rollBack();

                return false;
            }
            self::recordTxn((int) $pay['id'], 'capture', $paymentId, $amount, 'captured', $rzpPayment);
            if (in_array($order['payment_status'], ['paid', 'partially_refunded', 'refunded'], true)) {
                $pdo->commit();

                return true;   // already done (webhook + callback both arrive, or already refunded)
            }

            // Late payment for an order we already expired/cancelled: try to take the stock back.
            if ($order['status'] !== 'pending_payment') {
                if (!self::reReserve($orderId)) {
                    $refundNeeded = true;
                }
            }
            if ($amount !== (int) $order['grand_total_paise']) {
                error_log("[StorePayment] amount mismatch on {$order['order_no']}: paid $amount, expected {$order['grand_total_paise']}");
                $refundNeeded = true;
            }

            QueryBuilder::table('store_payments')->where('id', '=', (int) $pay['id'])->update([
                'rzp_payment_id' => $paymentId,
                'status' => 'captured',
                'method' => mb_substr((string) ($rzpPayment['method'] ?? ''), 0, 20) ?: null,
                'captured_at' => date('Y-m-d H:i:s'),
            ]);

            if ($refundNeeded) {
                if ($order['status'] === 'pending_payment') {
                    // Stock and points are still held for this order — give them back before refunding.
                    OrderService::releaseStock($orderId);
                    PointsService::release($orderId, 'Payment refunded');
                }
                QueryBuilder::table('store_orders')->where('id', '=', $orderId)->update([
                    'payment_status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'),
                ]);
                OrderService::history($orderId, null, 'payment', (int) $pay['id'], $order['status'], 'refund_pending', 'webhook', null,
                    'Payment received after the order could not be fulfilled — full refund issued');
                $pdo->commit();
                self::refundFull($orderId, $pay['id'] ? (int) $pay['id'] : 0, $paymentId, $amount, 'late_payment');

                return true;
            }

            // Normal path. Reserved stock becomes sold stock.
            $items = $pdo->prepare('SELECT variant_id, qty, product_id FROM store_order_items WHERE order_id = :o ORDER BY variant_id');
            $items->execute(['o' => $orderId]);
            $rows = $items->fetchAll();
            $commit = $pdo->prepare(
                'UPDATE store_product_variants
                    SET stock_qty = IF(stock_qty > :q1, stock_qty - :q2, 0),
                        reserved_qty = IF(reserved_qty > :q3, reserved_qty - :q4, 0),
                        updated_at = updated_at
                  WHERE id = :v'
            );
            $move = $pdo->prepare(
                "INSERT INTO store_inventory_movements (variant_id, delta, reason, ref_type, ref_id, actor_type)
                 VALUES (:v, :d, 'commit', 'order', :o, 'system')"
            );
            $sold = $pdo->prepare('UPDATE store_products SET sold_count = sold_count + :q WHERE id = :p');
            foreach ($rows as $r) {
                $q = (int) $r['qty'];
                $commit->execute(['q1' => $q - 1, 'q2' => $q, 'q3' => $q - 1, 'q4' => $q, 'v' => (int) $r['variant_id']]);
                $move->execute(['v' => (int) $r['variant_id'], 'd' => -$q, 'o' => $orderId]);
                $sold->execute(['q' => $q, 'p' => (int) $r['product_id']]);
            }

            $now = date('Y-m-d H:i:s');
            QueryBuilder::table('store_orders')->where('id', '=', $orderId)->update([
                'status' => 'paid', 'payment_status' => 'paid', 'paid_at' => $now, 'cancelled_at' => null,
            ]);
            $sla = max(1, StoreSettings::int('store_vendor_accept_sla_hours', 48));
            $pdo->prepare(
                "UPDATE store_vendor_orders SET status = 'new', accept_by = :ab, cancel_reason = NULL, cancelled_by_type = NULL
                  WHERE order_id = :o"
            )->execute(['ab' => date('Y-m-d H:i:s', time() + $sla * 3600), 'o' => $orderId]);
            OrderService::history($orderId, null, 'order', $orderId, (string) $order['status'], 'paid', 'webhook', null,
                'Payment ' . $paymentId . ' captured (' . ($rzpPayment['method'] ?? 'online') . ')');

            // The checked-out items leave the customer's cart.
            $pdo->prepare(
                'DELETE ci FROM store_cart_items ci
                   JOIN store_carts c ON c.id = ci.cart_id
                   JOIN store_order_items oi ON oi.variant_id = ci.variant_id AND oi.order_id = :o
                  WHERE c.identity_id = :i'
            )->execute(['o' => $orderId, 'i' => (int) $order['identity_id']]);

            foreach (array_unique(array_map(static fn ($r) => (int) $r['product_id'], $rows)) as $pid) {
                ProductService::refreshDenormalized($pid);
            }
            if (!empty($order['coupon_id'])) {
                // A coupon counts as used only once the order is paid.
                CouponService::redeem((int) $order['coupon_id'], $orderId, (int) $order['identity_id'], (int) $order['discount_paise']);
            }
            PointsService::confirmPaid($orderId);   // held points are now spent
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[StorePayment::markPaid] ' . $e->getMessage());

            return false;
        }

        // After commit, best-effort: never let email trouble undo a paid order.
        StoreNotifier::orderPaid($orderId);

        return true;
    }

    /** @param array<string, mixed> $pay */
    private static function markAttemptFailed(array $pay, array $rzpPayment): void
    {
        self::recordTxn((int) $pay['id'], 'attempt', (string) ($rzpPayment['id'] ?? ''), (int) ($rzpPayment['amount'] ?? 0), 'failed', $rzpPayment);
        Database::connection()->prepare(
            "UPDATE store_payments SET status = 'failed', failure_code = :c, failure_reason = :r
              WHERE id = :id AND status <> 'captured'"
        )->execute([
            'c' => mb_substr((string) ($rzpPayment['error_code'] ?? ''), 0, 80) ?: null,
            'r' => mb_substr((string) ($rzpPayment['error_description'] ?? ''), 0, 500) ?: null,
            'id' => (int) $pay['id'],
        ]);
        // The order stays pending_payment: the customer can retry until it expires.
    }

    /** Re-reserve stock for an expired/cancelled order that got paid late. All-or-nothing. */
    private static function reReserve(int $orderId): bool
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT variant_id, qty FROM store_order_items WHERE order_id = :o ORDER BY variant_id');
        $st->execute(['o' => $orderId]);
        $rows = $st->fetchAll();
        $upd = $pdo->prepare(
            'UPDATE store_product_variants SET reserved_qty = reserved_qty + :q
              WHERE id = :v AND is_active = 1 AND stock_qty >= reserved_qty + :q2'
        );
        $done = [];
        foreach ($rows as $r) {
            $upd->execute(['q' => (int) $r['qty'], 'v' => (int) $r['variant_id'], 'q2' => (int) $r['qty']]);
            if ($upd->rowCount() !== 1) {
                // All-or-nothing: give back what we just took, then the caller refunds.
                $undo = $pdo->prepare('UPDATE store_product_variants SET reserved_qty = IF(reserved_qty > :q, reserved_qty - :q2, 0) WHERE id = :v');
                foreach ($done as [$v, $q]) {
                    $undo->execute(['q' => $q, 'q2' => $q, 'v' => $v]);
                }

                return false;
            }
            $done[] = [(int) $r['variant_id'], (int) $r['qty']];
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Refunds
    // ------------------------------------------------------------------

    /** Full refund of a captured payment (late payment / amount mismatch). */
    public static function refundFull(int $orderId, int $paymentRowId, string $rzpPaymentId, int $amount, string $reason): bool
    {
        $refundNo = 'RF' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $refundId = QueryBuilder::table('store_refunds')->insert([
            'refund_no' => $refundNo,
            'order_id' => $orderId,
            'payment_id' => $paymentRowId,
            'amount_paise' => $amount,
            'reason' => $reason,
            'status' => 'pending',
            'initiated_by_type' => 'system',
        ]);
        $res = self::refund($rzpPaymentId, $amount, ['purpose' => 'store', 'refund_no' => $refundNo, 'reason' => $reason]);
        if (!empty($res['id'])) {
            QueryBuilder::table('store_refunds')->where('id', '=', $refundId)->update([
                'rzp_refund_id' => (string) $res['id'],
                'status' => ($res['status'] ?? '') === 'processed' ? 'processed' : 'pending',
            ]);
            QueryBuilder::table('store_orders')->where('id', '=', $orderId)->update([
                'status' => 'refunded', 'payment_status' => 'refunded',
            ]);
            QueryBuilder::table('store_payments')->where('id', '=', $paymentRowId)->update([
                'status' => 'refunded', 'refunded_paise' => $amount,
            ]);
            OrderService::history($orderId, null, 'payment', $paymentRowId, 'paid', 'refunded', 'system', null, "Full refund $refundNo ($reason)");

            return true;
        }
        error_log('[StorePayment] refund FAILED for order ' . $orderId . ': ' . json_encode($res['error'] ?? $res));
        QueryBuilder::table('store_refunds')->where('id', '=', $refundId)->update(['status' => 'failed']);
        OrderService::history($orderId, null, 'payment', $paymentRowId, 'paid', 'refund_failed', 'system', null,
            'Automatic refund failed: refund manually from the Razorpay dashboard');

        return false;
    }

    /**
     * Raw Razorpay refund (full or partial). Returns the refund entity ('id' set) or an 'error'.
     *
     * @param array<string, string> $notes
     * @return array<string, mixed>
     */
    public static function refund(string $rzpPaymentId, int $amountPaise, array $notes): array
    {
        if (!self::configured() || $rzpPaymentId === '' || $amountPaise <= 0) {
            return ['error' => ['description' => 'not configured / invalid']];
        }

        return self::api('POST', '/payments/' . rawurlencode($rzpPaymentId) . '/refund', [
            'amount' => $amountPaise,
            'speed' => 'normal',
            'notes' => $notes,
        ]);
    }

    // ------------------------------------------------------------------
    // 3. Webhook (routed from /webhooks/razorpay when notes.purpose = store)
    // ------------------------------------------------------------------

    /** True when a Razorpay webhook body belongs to the store (not subscriptions). */
    public static function isStoreEvent(array $event): bool
    {
        foreach (['payment', 'order', 'refund'] as $entity) {
            if ((($event['payload'][$entity]['entity']['notes']['purpose'] ?? '') === 'store')) {
                return true;
            }
        }
        // Fallback: a Razorpay order id we created for the store.
        $rzpOrderId = (string) ($event['payload']['payment']['entity']['order_id'] ?? $event['payload']['order']['entity']['id'] ?? '');
        try {
            return $rzpOrderId !== '' && QueryBuilder::table('store_payments')->where('rzp_order_id', '=', $rzpOrderId)->first() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return bool false only for a bad signature (Razorpay will retry); true = acknowledged
     */
    public static function handleWebhook(string $payload, ?string $signature, ?string $eventId): bool
    {
        // Test- and live-mode webhooks can carry different secrets: accept any secret that is ours.
        $valid = false;
        foreach ($signature !== null ? self::webhookSecrets() : [] as $secret) {
            if (hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
                $valid = true;
                break;
            }
        }
        // Store money: the signature is REQUIRED (no "missing header" bypass).
        if (!$valid) {
            error_log('[StorePayment] webhook signature invalid/missing');

            return false;
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return true;
        }
        $type = (string) ($event['event'] ?? '');
        $eventId = $eventId !== null && $eventId !== '' ? $eventId : hash('sha256', $payload);

        // Dedupe: the same event delivered twice is recorded once.
        $ins = Database::connection()->prepare(
            "INSERT IGNORE INTO store_webhook_events (provider, event_id, event_type, signature_ok, payload)
             VALUES ('razorpay', :e, :t, 1, :p)"
        );
        $ins->execute(['e' => mb_substr($eventId, 0, 120), 't' => mb_substr($type, 0, 80), 'p' => $payload]);
        if ($ins->rowCount() === 0) {
            return true;   // already processed
        }

        $status = 'processed';
        $error = null;
        try {
            $payment = $event['payload']['payment']['entity'] ?? [];
            $rzpOrderId = (string) ($payment['order_id'] ?? $event['payload']['order']['entity']['id'] ?? '');
            if (in_array($type, ['payment.captured', 'order.paid'], true) && $rzpOrderId !== '') {
                self::confirm($rzpOrderId);   // re-check with the API, then markPaid
            } elseif ($type === 'payment.failed' && $rzpOrderId !== '') {
                $pay = QueryBuilder::table('store_payments')->where('rzp_order_id', '=', $rzpOrderId)->first();
                if ($pay !== null) {
                    self::markAttemptFailed($pay, $payment);
                }
            } elseif (str_starts_with($type, 'refund.')) {
                $refund = $event['payload']['refund']['entity'] ?? [];
                if (!empty($refund['id'])) {
                    $map = ['refund.processed' => 'processed', 'refund.failed' => 'failed'];
                    if (isset($map[$type])) {
                        QueryBuilder::table('store_refunds')->where('rzp_refund_id', '=', (string) $refund['id'])->update([
                            'status' => $map[$type],
                            'processed_at' => $map[$type] === 'processed' ? date('Y-m-d H:i:s') : null,
                        ]);
                    }
                }
            } else {
                $status = 'ignored';
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            error_log('[StorePayment::handleWebhook] ' . $error);
        }
        Database::connection()->prepare(
            "UPDATE store_webhook_events SET status = :s, error = :err, attempts = attempts + 1, processed_at = NOW()
              WHERE provider = 'razorpay' AND event_id = :e"
        )->execute(['s' => $status, 'err' => $error, 'e' => mb_substr($eventId, 0, 120)]);

        return true;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private static function recordTxn(int $paymentRowId, string $type, string $ref, int $amount, string $status, array $payload): void
    {
        if ($ref === '') {
            return;
        }
        Database::connection()->prepare(
            'INSERT IGNORE INTO store_payment_transactions (payment_id, type, gateway_ref, amount_paise, status, payload)
             VALUES (:p, :t, :r, :a, :s, :j)'
        )->execute([
            'p' => $paymentRowId, 't' => $type, 'r' => mb_substr($ref, 0, 40), 'a' => $amount, 's' => $status,
            'j' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<string, mixed> decoded JSON ('error' key on transport failure) */
    private static function api(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::API . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => self::key() . ':' . self::secret(),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? new \stdClass());
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['error' => ['description' => 'transport: ' . $err]];
        }
        $data = json_decode((string) $raw, true);

        return is_array($data) ? $data : ['error' => ['description' => 'bad json']];
    }

    private static function key(): string
    {
        return self::pair(self::mode() === 'production' ? 'live' : 'test')[0];
    }

    private static function secret(): string
    {
        return self::pair(self::mode() === 'production' ? 'live' : 'test')[1];
    }

    /**
     * Key pair for 'live' or 'test' from app/.env: the dedicated RAZORPAY_LIVE_* / RAZORPAY_TEST_*
     * variables, else the site's RAZORPAY_KEY_* when it is that kind of key.
     *
     * @return array{0: string, 1: string}
     */
    private static function pair(string $env): array
    {
        $up = strtoupper($env);
        $id = (string) ($_ENV["RAZORPAY_{$up}_KEY_ID"] ?? '');
        $secret = (string) ($_ENV["RAZORPAY_{$up}_KEY_SECRET"] ?? '');
        if ($id === '' || $secret === '') {
            $siteId = (string) ($_ENV['RAZORPAY_KEY_ID'] ?? '');
            if (str_starts_with($siteId, 'rzp_' . $env . '_')) {
                return [$siteId, (string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? '')];
            }

            return ['', ''];
        }

        return [$id, $secret];
    }

    /** @return list<string> every webhook/key secret we own (live + test), so both modes' webhooks verify */
    private static function webhookSecrets(): array
    {
        $all = [];
        foreach (['RAZORPAY_WEBHOOK_SECRET', 'RAZORPAY_KEY_SECRET', 'RAZORPAY_LIVE_WEBHOOK_SECRET', 'RAZORPAY_LIVE_KEY_SECRET',
            'RAZORPAY_TEST_WEBHOOK_SECRET', 'RAZORPAY_TEST_KEY_SECRET'] as $var) {
            $all[] = (string) ($_ENV[$var] ?? '');
        }

        return array_values(array_unique(array_filter($all, static fn ($v) => $v !== '')));
    }
}
