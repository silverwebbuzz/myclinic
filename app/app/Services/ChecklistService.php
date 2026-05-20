<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class ChecklistService
{
    /** @return array{items: list<array{key: string, label: string, done: bool}>, percent: int, dismissed: bool} */
    public static function progress(int $clinicId, array $clinic, ?array $config): array
    {
        if (RedisClient::get("clinic:{$clinicId}:checklist_dismissed") === '1') {
            return ['items' => [], 'percent' => 100, 'dismissed' => true];
        }

        $items = [
            self::item('logo', 'Add clinic logo', !empty($clinic['logo_path'])),
            self::item('hours', 'Set working hours', self::hasWorkingHours($config)),
            self::item('staff', 'Invite a team member', self::staffCount($clinicId) > 1),
            self::item('patient', 'Add first patient', self::tableCount('patients', $clinicId) > 0),
            self::item('appointment', 'Book first appointment', self::tableCount('appointments', $clinicId) > 0),
            self::item('payment', 'Connect payment method', self::hasPaymentMethod($clinic, $config)),
            self::item('fee', 'Set consultation fee', (float) ($config['consultation_fee'] ?? 0) > 0),
        ];

        $done = count(array_filter($items, static fn ($i) => $i['done']));
        $percent = (int) round(($done / count($items)) * 100);

        return [
            'items' => $items,
            'percent' => $percent,
            'dismissed' => false,
        ];
    }

    public static function dismiss(int $clinicId): void
    {
        RedisClient::setex("clinic:{$clinicId}:checklist_dismissed", 86400 * 365, '1');
    }

    /** @param array<string, mixed>|null $config */
    private static function hasWorkingHours(?array $config): bool
    {
        $hours = $config['working_hours'] ?? null;
        if (is_string($hours)) {
            $hours = json_decode($hours, true);
        }
        if (!is_array($hours)) {
            return false;
        }
        foreach ($hours as $day) {
            if (!empty($day['enabled'])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $clinic @param array<string, mixed>|null $config */
    private static function hasPaymentMethod(array $clinic, ?array $config): bool
    {
        return !empty($clinic['stripe_customer_id'])
            || !empty($clinic['razorpay_customer_id'])
            || !empty($config['razorpay_key']);
    }

    private static function staffCount(int $clinicId): int
    {
        if (!Database::ping()) {
            return 1;
        }

        return QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_active', '=', 1)
            ->count();
    }

    private static function tableCount(string $table, int $clinicId): int
    {
        if (!Database::ping()) {
            return 0;
        }

        return QueryBuilder::table($table)->forClinic($clinicId)->count();
    }

    /** @return array{key: string, label: string, done: bool} */
    private static function item(string $key, string $label, bool $done): array
    {
        return compact('key', 'label', 'done');
    }
}
