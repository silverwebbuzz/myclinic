<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class PharmacyPosService
{
    /** @param list<array{drug_id: int, qty: int}> $cart @return array<string, mixed> */
    public static function checkout(int $clinicId, array $cart, string $paymentMode, ?int $patientId = null): array
    {
        $user = RequestContext::user();
        $subtotal = 0.0;
        $lineItems = [];

        foreach ($cart as $item) {
            $drugId = (int) ($item['drug_id'] ?? 0);
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $used = PharmacyInventoryService::deductFifo($clinicId, $drugId, $qty);
            foreach ($used as $u) {
                $lineTotal = $u['qty'] * $u['unit_price'];
                $subtotal += $lineTotal;
                $lineItems[] = array_merge($u, ['drug_id' => $drugId, 'total' => $lineTotal]);
            }
            $drug = DrugService::find($drugId);
            if ($drug !== null && in_array($drug['schedule'] ?? 'OTC', ['H', 'H1'], true)) {
                self::logNarcotic($clinicId, $drugId, $qty, $patientId);
            }
        }

        $saleId = QueryBuilder::table('pharmacy_sales')->insert([
            'clinic_id' => $clinicId,
            'patient_id' => $patientId,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'payment_mode' => in_array($paymentMode, ['cash', 'upi', 'card'], true) ? $paymentMode : 'cash',
            'sold_by' => $user['id'] ?? null,
        ]);

        foreach ($lineItems as $line) {
            QueryBuilder::table('pharmacy_sale_items')->insert([
                'sale_id' => $saleId,
                'inventory_id' => $line['inventory_id'],
                'drug_id' => $line['drug_id'],
                'qty' => $line['qty'],
                'unit_price' => $line['unit_price'],
                'total' => $line['total'],
            ]);
        }

        return QueryBuilder::table('pharmacy_sales')->where('id', '=', $saleId)->first() ?? [];
    }

    /** @return list<array<string, mixed>> */
    public static function searchStock(int $clinicId, string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return array_slice(PharmacyInventoryService::list($clinicId), 0, 20);
        }

        $all = PharmacyInventoryService::list($clinicId);

        return array_values(array_filter($all, static fn ($r) => stripos($r['drug_name'] ?? '', $q) !== false));
    }

    private static function logNarcotic(int $clinicId, int $drugId, int $qty, ?int $patientId): void
    {
        $drug = DrugService::find($drugId);
        $schedule = in_array($drug['schedule'] ?? '', ['H', 'H1'], true) ? $drug['schedule'] : 'H';
        $remaining = 0;
        foreach (PharmacyInventoryService::list($clinicId) as $row) {
            if ((int) $row['drug_id'] === $drugId) {
                $remaining += (int) $row['quantity'];
            }
        }

        $patientName = null;
        if ($patientId !== null) {
            $p = PatientService::find($clinicId, $patientId);
            $patientName = $p['name'] ?? null;
        }

        QueryBuilder::table('pharmacy_narcotic_register')->insert([
            'clinic_id' => $clinicId,
            'drug_id' => $drugId,
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'qty' => $qty,
            'balance_after' => $remaining,
            'schedule' => $schedule,
            'recorded_by' => RequestContext::user()['id'] ?? null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function narcoticRegister(int $clinicId, int $limit = 100): array
    {
        return QueryBuilder::table('pharmacy_narcotic_register')
            ->forClinic($clinicId)
            ->orderBy('recorded_at', 'DESC')
            ->limit($limit)
            ->get();
    }
}
