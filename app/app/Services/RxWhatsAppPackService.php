<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use PDO;

/**
 * Buying extra WhatsApp prescription messages (packs) via Razorpay.
 *
 * Same Razorpay account + webhook URL as the subscription and the store, so
 * every pack order carries notes.purpose = 'rx_wa_pack'. WebhookController
 * routes those here BEFORE the subscription handler — otherwise the
 * subscription handler would treat the payment as a plan renewal.
 *
 * Credit happens exactly once: rx_wa_orders.status flips pending → paid with a
 * conditional UPDATE, and only that winning request adds to the balance.
 * Purchased messages never expire and are used after the free monthly ones.
 */
final class RxWhatsAppPackService
{
    public const PURPOSE = 'rx_wa_pack';
    private const GST_PERCENT = 18.0;

    /** @return array{base: float, gst: float, total: float} */
    public static function price(float $base): array
    {
        $gst = round($base * self::GST_PERCENT / 100, 2);

        return ['base' => round($base, 2), 'gst' => $gst, 'total' => round($base + $gst, 2)];
    }

    /** @param array<string, mixed> $notes */
    public static function isPackNotes(array $notes): bool
    {
        return ($notes['purpose'] ?? '') === self::PURPOSE;
    }

    /** @param array<string, mixed> $event Razorpay webhook body */
    public static function isPackEvent(array $event): bool
    {
        foreach (['payment', 'order'] as $entity) {
            if (self::isPackNotes((array) ($event['payload'][$entity]['entity']['notes'] ?? []))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Start a purchase. Returns the data for views/subscription/razorpay-checkout,
     * a redirect (dev without Razorpay keys: credited at once), or an error.
     *
     * @return array<string, mixed>
     */
    public static function checkout(int $clinicId, int $packIndex, ?int $userId): array
    {
        $packs = RxWhatsAppService::packs();
        $pack = $packs[$packIndex] ?? null;
        if ($pack === null) {
            return ['type' => 'error', 'message' => 'That pack is no longer available.'];
        }
        $price = self::price((float) $pack['price']);
        $qty = (int) $pack['qty'];

        $key = (string) ($_ENV['RAZORPAY_KEY_ID'] ?? '');
        $secret = (string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? '');

        // Dev / no gateway: mirror BillingGatewayService's simulate mode.
        if ($key === '' || $secret === '') {
            $orderId = 'sim_rxw_' . $clinicId . '_' . bin2hex(random_bytes(5));
            self::insertOrder($clinicId, $userId, $qty, $price, $orderId);
            self::markPaid($orderId, null);

            return ['type' => 'redirect', 'url' => '/prescriptions/whatsapp?bought=' . $qty];
        }

        $receipt = substr('rxw_' . $clinicId . '_' . bin2hex(random_bytes(4)), 0, 40);
        $body = json_encode([
            'amount' => (int) round($price['total'] * 100),
            'currency' => 'INR',
            'receipt' => $receipt,
            'payment_capture' => 1,
            'notes' => [
                'purpose' => self::PURPOSE,
                'clinic_id' => (string) $clinicId,
                'qty' => (string) $qty,
            ],
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        $orderId = is_array($data) ? (string) ($data['id'] ?? '') : '';

        if ($http < 200 || $http >= 300 || $orderId === '') {
            error_log('[RxWhatsAppPack] order create failed (HTTP ' . $http . '): ' . (string) $response);

            return ['type' => 'error', 'message' => 'We couldn\'t start the payment right now. Please try again in a few minutes.'];
        }

        self::insertOrder($clinicId, $userId, $qty, $price, $orderId);

        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
        $phone = preg_replace('/\D+/', '', (string) ($clinic['phone'] ?? '')) ?? '';
        $email = (string) ($clinic['email'] ?? '');

        return [
            'type' => 'razorpay',
            'key_id' => $key,
            'order_id' => $orderId,
            'amount' => (int) round($price['total'] * 100),
            'currency' => 'INR',
            'name' => 'WhatsApp prescriptions: ' . $qty . ' messages',
            'prefill' => [
                'name' => (string) ($clinic['name'] ?? ''),
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
                'contact' => strlen($phone) > 10 ? substr($phone, -10) : $phone,
            ],
            'mode' => strtolower((string) ($_ENV['RAZORPAY_ENV'] ?? 'sandbox')) === 'production' ? 'production' : 'sandbox',
        ];
    }

    /** @param array{base: float, gst: float, total: float} $price */
    private static function insertOrder(int $clinicId, ?int $userId, int $qty, array $price, string $orderId): void
    {
        Database::connection()->prepare(
            'INSERT INTO rx_wa_orders (clinic_id, user_id, qty, base_amount, gst_amount, total_amount, razorpay_order_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$clinicId, $userId, $qty, $price['base'], $price['gst'], $price['total'], $orderId]);

        // GST tax invoice (pending until paid) in the same series as plan
        // invoices: shows in Admin → Payments and the clinic's billing history.
        SaasInvoiceService::createPending(
            $clinicId,
            'rx_wa_pack',
            'one-time',
            ['base' => $price['base'], 'tax' => $price['gst'], 'gross' => $price['total'], 'percent' => self::GST_PERCENT],
            str_starts_with($orderId, 'sim_') ? 'simulate' : 'razorpay',
            $orderId,
            null,
            'WhatsApp prescription messages: pack of ' . number_format($qty),
        );
    }

    /**
     * Ask Razorpay (the source of truth) whether the order is paid, then credit.
     * Used by the return URL and the webhook. Idempotent.
     */
    public static function verifyOrder(string $orderId): bool
    {
        $key = (string) ($_ENV['RAZORPAY_KEY_ID'] ?? '');
        $secret = (string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? '');
        if ($orderId === '' || $key === '' || $secret === '') {
            return false;
        }

        $local = self::findOrder($orderId);
        if ($local === null) {
            return false;
        }
        if ($local['status'] === 'paid') {
            return true;
        }

        $order = self::rzpGet('/orders/' . rawurlencode($orderId), $key, $secret);
        if (!is_array($order) || ($order['status'] ?? '') !== 'paid') {
            return false;
        }
        // The amount Razorpay collected must be what we priced.
        if ((int) ($order['amount_paid'] ?? $order['amount'] ?? 0) < (int) round((float) $local['total_amount'] * 100)) {
            error_log('[RxWhatsAppPack] amount mismatch on ' . $orderId);

            return false;
        }

        $paymentId = null;
        $payments = self::rzpGet('/orders/' . rawurlencode($orderId) . '/payments', $key, $secret);
        foreach ((array) ($payments['items'] ?? []) as $p) {
            if (($p['status'] ?? '') === 'captured') {
                $paymentId = (string) ($p['id'] ?? '');
                break;
            }
        }

        return self::markPaid($orderId, $paymentId);
    }

    /** Flip pending → paid once and credit the clinic. */
    public static function markPaid(string $orderId, ?string $paymentId): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                "UPDATE rx_wa_orders SET status = 'paid', paid_at = NOW(), razorpay_payment_id = ?
                  WHERE razorpay_order_id = ? AND status <> 'paid'"
            );
            $upd->execute([$paymentId, $orderId]);
            if ($upd->rowCount() !== 1) {
                $pdo->commit();

                return (self::findOrder($orderId)['status'] ?? '') === 'paid';
            }
            $order = self::findOrder($orderId);
            RxWhatsAppService::credit(
                (int) $order['clinic_id'],
                (int) $order['qty'],
                'purchase',
                (int) $order['id'],
                isset($order['user_id']) ? (int) $order['user_id'] : null,
                'Bought ' . (int) $order['qty'] . ' messages (' . $orderId . ')',
            );
            $pdo->commit();

            // Tax invoice → paid, PDF generated, emailed to the clinic.
            SaasInvoiceService::markPaidByOrder($orderId, $paymentId);

            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[RxWhatsAppPack] markPaid ' . $orderId . ': ' . $e->getMessage());

            return false;
        }
    }

    public static function markFailed(string $orderId): void
    {
        try {
            Database::connection()
                ->prepare("UPDATE rx_wa_orders SET status = 'failed' WHERE razorpay_order_id = ? AND status = 'pending'")
                ->execute([$orderId]);
            SaasInvoiceService::markFailedByOrder($orderId);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Webhook for pack payments. The signature is REQUIRED (money is credited).
     * Credit only after re-checking the order with the Razorpay API.
     */
    public static function handleWebhook(string $payload, ?string $signature): bool
    {
        $valid = false;
        if ($signature !== null && $signature !== '') {
            foreach (self::webhookSecrets() as $secret) {
                if (hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
                    $valid = true;
                    break;
                }
            }
        }
        if (!$valid) {
            error_log('[RxWhatsAppPack] webhook signature invalid/missing');

            return false;
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return true;
        }
        $type = (string) ($event['event'] ?? '');
        $orderId = (string) ($event['payload']['payment']['entity']['order_id'] ?? $event['payload']['order']['entity']['id'] ?? '');
        if ($orderId === '') {
            return true;
        }
        if ($type === 'payment.failed') {
            self::markFailed($orderId);
        } elseif (in_array($type, ['payment.captured', 'order.paid'], true)) {
            self::verifyOrder($orderId);
        }

        return true; // acknowledged
    }

    /** @return array<string, mixed>|null */
    public static function findOrder(string $orderId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rx_wa_orders WHERE razorpay_order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<string> */
    private static function webhookSecrets(): array
    {
        $all = [];
        foreach (['RAZORPAY_WEBHOOK_SECRET', 'RAZORPAY_KEY_SECRET', 'RAZORPAY_LIVE_WEBHOOK_SECRET', 'RAZORPAY_LIVE_KEY_SECRET',
            'RAZORPAY_TEST_WEBHOOK_SECRET', 'RAZORPAY_TEST_KEY_SECRET'] as $var) {
            $all[] = (string) ($_ENV[$var] ?? '');
        }

        return array_values(array_unique(array_filter($all, static fn ($v) => $v !== '')));
    }

    /** @return array<string, mixed>|null */
    private static function rzpGet(string $path, string $key, string $secret): ?array
    {
        $ch = curl_init('https://api.razorpay.com/v1' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $response, true);

        return is_array($data) ? $data : null;
    }
}
