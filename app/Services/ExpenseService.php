<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class ExpenseService
{
    /** @return list<array<string, mixed>> */
    public static function list(int $clinicId, ?string $from = null, ?string $to = null): array
    {
        $q = QueryBuilder::table('expenses')->forClinic($clinicId)->orderBy('expense_date', 'DESC');
        if ($from !== null) {
            $q->where('expense_date', '>=', $from);
        }
        if ($to !== null) {
            $q->where('expense_date', '<=', $to);
        }

        return $q->limit(100)->get();
    }

    public static function sumRange(int $clinicId, string $from, string $to): float
    {
        if (!Database::ping()) {
            return 0.0;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE clinic_id = ? AND expense_date BETWEEN ? AND ?',
        );
        $stmt->execute([$clinicId, $from, $to]);

        return (float) $stmt->fetchColumn();
    }

    public static function create(int $clinicId, array $data): int
    {
        $user = RequestContext::user();

        return QueryBuilder::table('expenses')->insert([
            'clinic_id' => $clinicId,
            'category' => $data['category'] ?? 'other',
            'description' => $data['description'] ?? 'Expense',
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'INR',
            'expense_date' => $data['expense_date'] ?? date('Y-m-d'),
            'paid_via' => $data['paid_via'] ?? 'cash',
            'entered_by' => $user['id'] ?? 1,
        ]);
    }
}
