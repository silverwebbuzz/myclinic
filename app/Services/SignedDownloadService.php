<?php

declare(strict_types=1);

namespace App\Services;

final class SignedDownloadService
{
    private const TTL_SECONDS = 259200; // 72 hours

    public static function create(int $clinicId, int $patientId, string $type, int $resourceId): string
    {
        $payload = [
            'c' => $clinicId,
            'p' => $patientId,
            't' => $type,
            'r' => $resourceId,
            'e' => time() + self::TTL_SECONDS,
        ];
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $_ENV['JWT_SECRET'] ?? 'change-me-in-production');

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=') . '.' . $sig;
    }

    /** @return array{path: string, mime?: string}|null */
    public static function resolve(string $token, int $clinicId, int $patientId): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $json, $_ENV['JWT_SECRET'] ?? 'change-me-in-production');
        if (!hash_equals($expected, $parts[1])) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || (int) ($data['c'] ?? 0) !== $clinicId || (int) ($data['p'] ?? 0) !== $patientId) {
            return null;
        }
        if (($data['e'] ?? 0) < time()) {
            return null;
        }

        return self::pathForResource($clinicId, (string) ($data['t'] ?? ''), (int) ($data['r'] ?? 0));
    }

    /** @return array{path: string}|null */
    private static function pathForResource(int $clinicId, string $type, int $id): ?array
    {
        return match ($type) {
            'rx' => self::visitRx($clinicId, $id),
            'invoice' => self::invoicePdf($clinicId, $id),
            'lab' => self::labReport($clinicId, $id),
            'diet' => self::dietPdf($clinicId, $id),
            default => null,
        };
    }

    /** @return array{path: string}|null */
    private static function visitRx(int $clinicId, int $visitId): ?array
    {
        $visit = VisitService::find($clinicId, $visitId);
        if ($visit === null || empty($visit['rx_pdf_path'])) {
            return null;
        }

        return ['path' => $visit['rx_pdf_path']];
    }

    /** @return array{path: string}|null */
    private static function invoicePdf(int $clinicId, int $invoiceId): ?array
    {
        $inv = InvoiceService::findDetailed($clinicId, $invoiceId);
        if ($inv === null || empty($inv['pdf_path'])) {
            return null;
        }

        return ['path' => $inv['pdf_path']];
    }

    /** @return array{path: string}|null */
    private static function labReport(int $clinicId, int $orderId): ?array
    {
        $order = LabOrderService::findDetailed($clinicId, $orderId);
        if ($order === null || empty($order['report_path'])) {
            return null;
        }

        return ['path' => $order['report_path']];
    }

    /** @return array{path: string}|null */
    private static function dietPdf(int $clinicId, int $planId): ?array
    {
        $plan = DietService::find($clinicId, $planId);
        if ($plan === null || empty($plan['pdf_path'])) {
            return null;
        }

        return ['path' => $plan['pdf_path']];
    }
}
