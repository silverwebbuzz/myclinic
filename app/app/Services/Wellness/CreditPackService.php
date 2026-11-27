<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;
use App\Services\MailService;
use PDO;

/**
 * Health Creators buy credit packs (Wellness Hub Phase 4) via Razorpay.
 *
 * Mirrors RxWhatsAppPackService: same Razorpay account + webhook URL, so every
 * order carries notes.purpose = 'wh_credit_pack' and WebhookController routes
 * those here BEFORE the subscription handler. Credit happens exactly once:
 * wh_orders.status flips pending → paid with a conditional UPDATE, and only
 * that request adds credits, issues the invoice number (ECW-YYYY-NNNNN) and
 * emails the receipt. Prices in settings are BEFORE GST.
 */
final class CreditPackService
{
    public const PURPOSE = 'wh_credit_pack';

    /** GST state codes, for the place of supply on the invoice. */
    private const STATE_CODES = [
        'jammu and kashmir' => '01', 'himachal pradesh' => '02', 'punjab' => '03', 'chandigarh' => '04', 'uttarakhand' => '05',
        'haryana' => '06', 'delhi' => '07', 'rajasthan' => '08', 'uttar pradesh' => '09', 'bihar' => '10', 'sikkim' => '11',
        'arunachal pradesh' => '12', 'nagaland' => '13', 'manipur' => '14', 'mizoram' => '15', 'tripura' => '16', 'meghalaya' => '17',
        'assam' => '18', 'west bengal' => '19', 'jharkhand' => '20', 'odisha' => '21', 'chhattisgarh' => '22', 'madhya pradesh' => '23',
        'gujarat' => '24', 'dadra and nagar haveli and daman and diu' => '26', 'maharashtra' => '27', 'karnataka' => '29', 'goa' => '30',
        'lakshadweep' => '31', 'kerala' => '32', 'tamil nadu' => '33', 'puducherry' => '34', 'andaman and nicobar islands' => '35',
        'telangana' => '36', 'andhra pradesh' => '37', 'ladakh' => '38',
    ];

    /** @return list<array{qty:int,price:float,gst:float,total:float,per_post:float}> */
    public static function packs(): array
    {
        $s = Quota::settings();
        $out = [];
        foreach ($s['packs'] as $p) {
            $gst = round($p['price'] * $s['gst_pct'] / 100, 2);
            $out[] = $p + ['gst' => $gst, 'total' => round($p['price'] + $gst, 2), 'per_post' => round($p['price'] / $p['qty'], 2)];
        }

        return $out;
    }

    /** @param array<string,mixed> $event Razorpay webhook body */
    public static function isPackEvent(array $event): bool
    {
        foreach (['payment', 'order'] as $entity) {
            if ((($event['payload'][$entity]['entity']['notes'] ?? [])['purpose'] ?? '') === self::PURPOSE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Start a purchase. Returns Razorpay checkout data, a redirect (dev without
     * Razorpay keys: credited at once), or an error.
     *
     * @param array<string,mixed> $creator
     * @param array<string,mixed> $billing buyer_name, buyer_gstin, buyer_state
     * @return array<string,mixed>
     */
    public static function checkout(array $creator, array $identity, int $packIndex, array $billing): array
    {
        $pack = self::packs()[$packIndex] ?? null;
        if ($pack === null) {
            return ['type' => 'error', 'message' => 'That pack is no longer available.'];
        }
        $name = CreatorService::clean((string) ($billing['buyer_name'] ?? ''), 160) ?? (string) ($creator['org_name'] ?: $creator['display_name']);
        $gstin = strtoupper(preg_replace('/\s+/', '', (string) ($billing['buyer_gstin'] ?? '')) ?? '');
        if ($gstin !== '' && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z\d]Z[A-Z\d]$/', $gstin)) {
            return ['type' => 'error', 'message' => 'GSTIN should be 15 characters, like 24ABCDE1234F1Z5 — or leave it empty.'];
        }
        $state = CreatorService::clean((string) ($billing['buyer_state'] ?? ''), 80) ?? (string) ($creator['state'] ?? '');

        $settings = Quota::settings();
        $key = (string) ($_ENV['RAZORPAY_KEY_ID'] ?? '');
        $secret = (string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? '');
        $creatorId = (int) $creator['id'];

        $orderRow = [
            'creator_id' => $creatorId, 'pack_qty' => $pack['qty'], 'base_amount' => $pack['price'], 'gst_pct' => $settings['gst_pct'],
            'gst_amount' => $pack['gst'], 'total_amount' => $pack['total'], 'buyer_name' => $name,
            'buyer_gstin' => $gstin !== '' ? $gstin : null, 'buyer_state' => $state !== '' ? $state : null,
            'buyer_email' => trim((string) ($creator['email'] ?? '')) ?: null,
        ];

        // Dev / no gateway: credited at once, like the other packs.
        if ($key === '' || $secret === '') {
            $orderId = 'sim_whc_' . $creatorId . '_' . bin2hex(random_bytes(5));
            self::insertOrder($orderRow + ['razorpay_order_id' => $orderId]);
            self::markPaid($orderId, null);

            return ['type' => 'redirect', 'url' => '/creator/credits?bought=' . $pack['qty']];
        }

        $body = json_encode([
            'amount' => (int) round($pack['total'] * 100),
            'currency' => 'INR',
            'receipt' => substr('whc_' . $creatorId . '_' . bin2hex(random_bytes(4)), 0, 40),
            'payment_capture' => 1,
            'notes' => ['purpose' => self::PURPOSE, 'creator_id' => (string) $creatorId, 'qty' => (string) $pack['qty']],
        ], JSON_THROW_ON_ERROR);
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        $orderId = is_array($data) ? (string) ($data['id'] ?? '') : '';
        if ($http < 200 || $http >= 300 || $orderId === '') {
            error_log('[CreditPack] order create failed (HTTP ' . $http . '): ' . (string) $response);

            return ['type' => 'error', 'message' => 'We couldn’t start the payment right now. Please try again in a few minutes.'];
        }
        self::insertOrder($orderRow + ['razorpay_order_id' => $orderId]);

        $phone = preg_replace('/\D+/', '', (string) ($identity['phone'] ?? '')) ?? '';

        return [
            'type' => 'razorpay',
            'key_id' => $key,
            'order_id' => $orderId,
            'amount' => (int) round($pack['total'] * 100),
            'currency' => 'INR',
            'name' => 'Wellness Hub credits: ' . $pack['qty'] . ' post' . ($pack['qty'] > 1 ? 's' : ''),
            'prefill' => [
                'name' => (string) $creator['display_name'],
                'email' => filter_var((string) ($creator['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $creator['email'] : '',
                'contact' => strlen($phone) > 10 ? substr($phone, -10) : $phone,
            ],
            'mode' => strtolower((string) ($_ENV['RAZORPAY_ENV'] ?? 'sandbox')) === 'production' ? 'production' : 'sandbox',
        ];
    }

    /** @param array<string,mixed> $row */
    private static function insertOrder(array $row): void
    {
        $cols = array_keys($row);
        Database::connection()->prepare(
            'INSERT INTO wh_orders (`' . implode('`, `', $cols) . '`) VALUES (:' . implode(', :', $cols) . ')'
        )->execute($row);
    }

    /** Ask Razorpay whether the order is paid, then credit. Return URL + webhook. Idempotent. */
    public static function verifyOrder(string $orderId): bool
    {
        $key = (string) ($_ENV['RAZORPAY_KEY_ID'] ?? '');
        $secret = (string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? '');
        $local = $orderId !== '' ? self::findOrder($orderId) : null;
        if ($local === null || $key === '' || $secret === '') {
            return false;
        }
        if ($local['status'] === 'paid') {
            return true;
        }
        $order = self::rzpGet('/orders/' . rawurlencode($orderId), $key, $secret);
        if (!is_array($order) || ($order['status'] ?? '') !== 'paid') {
            return false;
        }
        if ((int) ($order['amount_paid'] ?? $order['amount'] ?? 0) < (int) round((float) $local['total_amount'] * 100)) {
            error_log('[CreditPack] amount mismatch on ' . $orderId);

            return false;
        }
        $paymentId = null;
        foreach ((array) (self::rzpGet('/orders/' . rawurlencode($orderId) . '/payments', $key, $secret)['items'] ?? []) as $p) {
            if (($p['status'] ?? '') === 'captured') {
                $paymentId = (string) ($p['id'] ?? '');
                break;
            }
        }

        return self::markPaid($orderId, $paymentId);
    }

    /** Flip pending → paid once: credit, issue the invoice number, then PDF + email. */
    public static function markPaid(string $orderId, ?string $paymentId): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE wh_orders SET status = 'paid', paid_at = NOW(), razorpay_payment_id = :p WHERE razorpay_order_id = :o AND status <> 'paid'");
            $upd->execute(['p' => $paymentId, 'o' => $orderId]);
            if ($upd->rowCount() !== 1) {
                $pdo->commit();

                return (self::findOrder($orderId)['status'] ?? '') === 'paid';
            }
            $order = self::findOrder($orderId);
            Quota::adjust((int) $order['creator_id'], (int) $order['pack_qty'], 'purchase', (int) $order['id'], 'Bought ' . (int) $order['pack_qty'] . ' credit' . ((int) $order['pack_qty'] > 1 ? 's' : ''), null);
            $pdo->prepare('UPDATE wh_orders SET invoice_no = :n WHERE id = :id')->execute(['n' => self::nextInvoiceNo(), 'id' => (int) $order['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[CreditPack] markPaid ' . $orderId . ': ' . $e->getMessage());

            return false;
        }

        try {
            $order = self::findOrder($orderId);
            $path = WellnessInvoicePdf::generate($order, self::placeOfSupply($order));
            $pdo->prepare('UPDATE wh_orders SET pdf_path = :p WHERE id = :id')->execute(['p' => $path, 'id' => (int) $order['id']]);
            $creator = CreatorService::find((int) $order['creator_id']);
            $to = trim((string) ($order['buyer_email'] ?: ($creator['email'] ?? '')));
            if ($to !== '') {
                MailService::send($to, 'wellness_credit_receipt', [
                    'name' => (string) ($creator['display_name'] ?? ''),
                    'qty' => (int) $order['pack_qty'],
                    'total' => number_format((float) $order['total_amount'], 2),
                    'invoice_no' => (string) $order['invoice_no'],
                    'studio_url' => CreatorService::appUrl('/creator/credits'),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[CreditPack] invoice/email ' . $orderId . ': ' . $e->getMessage());   // credits are already in
        }

        return true;
    }

    public static function markFailed(string $orderId): void
    {
        try {
            Database::connection()->prepare("UPDATE wh_orders SET status = 'failed' WHERE razorpay_order_id = :o AND status = 'pending'")->execute(['o' => $orderId]);
        } catch (\Throwable) {
        }
    }

    /** Webhook (signature REQUIRED — money is credited). */
    public static function handleWebhook(string $payload, ?string $signature): bool
    {
        $valid = false;
        foreach (self::webhookSecrets() as $secret) {
            if ($signature !== null && $signature !== '' && hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            error_log('[CreditPack] webhook signature invalid/missing');

            return false;
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return true;
        }
        $type = (string) ($event['event'] ?? '');
        $orderId = (string) ($event['payload']['payment']['entity']['order_id'] ?? $event['payload']['order']['entity']['id'] ?? '');
        if ($orderId !== '') {
            if ($type === 'payment.failed') {
                self::markFailed($orderId);
            } elseif (in_array($type, ['payment.captured', 'order.paid'], true)) {
                self::verifyOrder($orderId);
            }
        }

        return true;
    }

    /** @return array<string,mixed>|null */
    public static function findOrder(string $orderId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_orders WHERE razorpay_order_id = :o LIMIT 1');
        $st->execute(['o' => $orderId]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function ordersFor(int $creatorId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_orders WHERE creator_id = :c ORDER BY id DESC LIMIT 100');
        $st->execute(['c' => $creatorId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Invoice PDF for one paid order (generated on first request if missing). */
    public static function invoiceResponse(array $order): \App\Http\Response
    {
        if ($order['status'] !== 'paid' || empty($order['invoice_no'])) {
            return \App\Http\Response::html('Invoice not available', 404);
        }
        $path = (string) ($order['pdf_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            $path = WellnessInvoicePdf::generate($order, self::placeOfSupply($order));
            Database::connection()->prepare('UPDATE wh_orders SET pdf_path = :p WHERE id = :id')->execute(['p' => $path, 'id' => (int) $order['id']]);
        }
        $isPdf = str_ends_with($path, '.pdf') && str_starts_with((string) file_get_contents($path, false, null, 0, 4), '%PDF');

        return new \App\Http\Response((string) file_get_contents($path), 200, [
            'Content-Type' => $isPdf ? 'application/pdf' : 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^\w\-]/', '_', (string) $order['invoice_no']) . ($isPdf ? '.pdf' : '.txt') . '"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{code:string,name:string,intra:bool} */
    public static function placeOfSupply(array $order): array
    {
        $company = require dirname(__DIR__, 3) . '/config/company.php';
        $sellerCode = (string) ($company['state_code'] ?? '24');
        $code = '';
        if (!empty($order['buyer_gstin'])) {
            $code = substr((string) $order['buyer_gstin'], 0, 2);
        } elseif (!empty($order['buyer_state'])) {
            $code = self::STATE_CODES[strtolower(trim((string) $order['buyer_state']))] ?? '';
        }
        if ($code === '') {
            $code = $sellerCode;   // unknown → treat as intra-state
        }
        $name = array_search($code, self::STATE_CODES, true);

        return ['code' => $code, 'name' => $name !== false ? ucwords((string) $name) : (string) ($order['buyer_state'] ?? ''), 'intra' => $code === $sellerCode];
    }

    /** ECW-YYYY-NNNNN (own series for Wellness credits; unique key on invoice_no). */
    private static function nextInvoiceNo(): string
    {
        $year = date('Y');
        $st = Database::connection()->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_no, '-', -1) AS UNSIGNED)), 0) + 1 FROM wh_orders WHERE invoice_no LIKE :p"
        );
        $st->execute(['p' => 'ECW-' . $year . '-%']);

        return sprintf('ECW-%s-%05d', $year, (int) $st->fetchColumn());
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

    /** @return array<string,mixed>|null */
    private static function rzpGet(string $path, string $key, string $secret): ?array
    {
        $ch = curl_init('https://api.razorpay.com/v1' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $key . ':' . $secret, CURLOPT_TIMEOUT => 20]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $response, true);

        return is_array($data) ? $data : null;
    }
}
