<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class BillingPaymentService
{
    /** @return array{mode: string, order_id?: string, amount?: int, key_id?: string, qr_data?: string, message?: string} */
    public static function createRazorpayOrder(int $clinicId, int $invoiceId): array
    {
        $invoice = InvoiceService::findDetailed($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $keyId = $config['razorpay_key'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? '';
        $secret = $config['razorpay_secret'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? '';

        $due = (float) $invoice['total'] - (float) ($invoice['advance_paid'] ?? 0);
        $amountPaise = (int) round($due * 100);

        if ($keyId === '' || $secret === '') {
            $orderId = 'sim_order_' . $invoiceId . '_' . time();
            QueryBuilder::table('invoices')
                ->forClinic($clinicId)
                ->where('id', '=', $invoiceId)
                ->update(['razorpay_order_id' => $orderId]);

            return [
                'mode' => 'simulated',
                'order_id' => $orderId,
                'amount' => $amountPaise,
                'key_id' => 'sim_key',
                'qr_data' => 'upi://pay?pa=clinic@razorpay&pn=ManageClinic&am=' . ($due) . '&tn=INV' . $invoice['invoice_number'],
                'message' => 'Dev mode: use Check payment to simulate success',
            ];
        }

        $payload = json_encode([
            'amount' => $amountPaise,
            'currency' => $invoice['currency'] ?? 'INR',
            'receipt' => $invoice['invoice_number'],
            'notes' => ['clinic_id' => $clinicId, 'invoice_id' => $invoiceId],
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $keyId . ':' . $secret,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        $orderId = $data['id'] ?? null;
        if ($orderId === null) {
            throw new \RuntimeException($data['error']['description'] ?? 'Razorpay order failed');
        }

        QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('id', '=', $invoiceId)
            ->update(['razorpay_order_id' => $orderId]);

        return [
            'mode' => 'razorpay',
            'order_id' => $orderId,
            'amount' => $amountPaise,
            'key_id' => $keyId,
            'qr_data' => 'upi://pay?pa=merchant@upi&am=' . $due,
        ];
    }

    public static function checkPayment(int $clinicId, int $invoiceId): array
    {
        $invoice = InvoiceService::find($clinicId, $invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found');
        }

        if (($invoice['status'] ?? '') === 'paid') {
            return ['paid' => true, 'status' => 'paid'];
        }

        $orderId = $invoice['razorpay_order_id'] ?? '';
        if ($orderId === '' || str_starts_with($orderId, 'sim_order_')) {
            return ['paid' => false, 'status' => $invoice['status'], 'simulated' => true];
        }

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $keyId = $config['razorpay_key'] ?? $_ENV['RAZORPAY_KEY_ID'] ?? '';
        $secret = $config['razorpay_secret'] ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? '';

        $ch = curl_init('https://api.razorpay.com/v1/orders/' . $orderId . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $keyId . ':' . $secret,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        foreach ($data['items'] ?? [] as $payment) {
            if (($payment['status'] ?? '') === 'captured') {
                InvoiceService::markPaid($clinicId, $invoiceId, 'upi', $payment['id'] ?? null);

                return ['paid' => true, 'status' => 'paid'];
            }
        }

        return ['paid' => false, 'status' => $invoice['status']];
    }

    /** Simulate payment in dev */
    public static function simulatePay(int $clinicId, int $invoiceId): array
    {
        return InvoiceService::markPaid($clinicId, $invoiceId, 'upi', 'sim_' . time());
    }
}
