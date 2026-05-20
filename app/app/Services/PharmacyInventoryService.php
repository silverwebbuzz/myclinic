<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class PharmacyInventoryService
{
    /** @return list<array<string, mixed>> */
    public static function list(int $clinicId): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT pi.*, d.name AS drug_name, d.schedule, d.strength, d.form
             FROM pharmacy_inventory pi
             INNER JOIN drugs d ON d.id = pi.drug_id
             WHERE pi.clinic_id = ? AND pi.quantity > 0
             ORDER BY pi.expiry_date ASC, d.name ASC',
        );
        $stmt->execute([$clinicId]);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function lowStock(int $clinicId): array
    {
        return DashboardService::lowStockItems($clinicId, 50);
    }

    /** @return list<array<string, mixed>> */
    public static function expiringSoon(int $clinicId, int $days = 30): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT pi.*, d.name AS drug_name
             FROM pharmacy_inventory pi
             INNER JOIN drugs d ON d.id = pi.drug_id
             WHERE pi.clinic_id = ? AND pi.quantity > 0
             AND pi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY pi.expiry_date ASC',
        );
        $stmt->execute([$clinicId, $days]);

        return $stmt->fetchAll() ?: [];
    }

    public static function addBatch(int $clinicId, array $data): int
    {
        return QueryBuilder::table('pharmacy_inventory')->insert([
            'clinic_id' => $clinicId,
            'drug_id' => (int) $data['drug_id'],
            'batch_number' => $data['batch_number'] ?? ('B' . date('ymd') . random_int(100, 999)),
            'quantity' => (int) ($data['quantity'] ?? 0),
            'low_stock_threshold' => (int) ($data['low_stock_threshold'] ?? 10),
            'expiry_date' => $data['expiry_date'] ?? date('Y-m-d', strtotime('+1 year')),
            'purchase_price' => (float) ($data['purchase_price'] ?? 0),
            'selling_price' => (float) ($data['selling_price'] ?? 0),
            'supplier' => $data['supplier'] ?? null,
            'location' => $data['location'] ?? null,
        ]);
    }

    /** FIFO deduct — returns inventory rows used */
    public static function deductFifo(int $clinicId, int $drugId, int $qty): array
    {
        $batches = QueryBuilder::table('pharmacy_inventory')
            ->forClinic($clinicId)
            ->where('drug_id', '=', $drugId)
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date', 'ASC')
            ->get();

        $remaining = $qty;
        $used = [];
        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $batch['quantity']);
            $newQty = (int) $batch['quantity'] - $take;
            QueryBuilder::table('pharmacy_inventory')
                ->where('id', '=', (int) $batch['id'])
                ->update(['quantity' => $newQty]);
            $used[] = ['inventory_id' => (int) $batch['id'], 'qty' => $take, 'unit_price' => (float) ($batch['selling_price'] ?? 0)];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new \RuntimeException('Insufficient stock for drug ID ' . $drugId);
        }

        return $used;
    }
}
