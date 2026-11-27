<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;

/**
 * App-side door to the shared quota rules in /partials/wellness_quota.php
 * (the same file the main site's recipe / tip editor uses), so free slots and
 * credits behave identically wherever a post is submitted or reviewed.
 */
final class Quota
{
    private static bool $loaded = false;

    private static function load(): \PDO
    {
        if (!self::$loaded) {
            require_once dirname(__DIR__, 4) . '/partials/wellness_quota.php';   // app/app/Services/Wellness → repo root
            self::$loaded = true;
        }

        return Database::connection();
    }

    public static function enabled(): bool
    {
        return \wh_q_enabled(self::load());
    }

    /** @return array{ok:bool,kind:string,error?:string} */
    public static function check(int $creatorId, string $itemType, ?int $itemId, string $contentType): array
    {
        return \wh_q_check(self::load(), $creatorId, $itemType, $itemId, $contentType);
    }

    /** @return array{ok:bool,kind:string,error?:string} */
    public static function reserve(int $creatorId, string $itemType, int $itemId, string $contentType): array
    {
        return \wh_q_reserve(self::load(), $creatorId, $itemType, $itemId, $contentType);
    }

    public static function finalize(string $itemType, int $itemId): void
    {
        \wh_q_finalize(self::load(), $itemType, $itemId);
    }

    public static function release(string $itemType, int $itemId, string $why): void
    {
        \wh_q_release(self::load(), $itemType, $itemId, $why);
    }

    public static function adjust(int $creatorId, int $qty, string $entry, ?int $orderId, string $note, ?int $adminId): int
    {
        return \wh_q_adjust(self::load(), $creatorId, $qty, $entry, $orderId, $note, $adminId);
    }

    /** @param list<string> $types @return array{period:string,balance:int,types:array<string,array{limit:?int,used:int}>} */
    public static function status(int $creatorId, array $types): array
    {
        return \wh_q_status(self::load(), $creatorId, $types);
    }

    /** @return array{free:array<string,int>,packs:list<array{qty:int,price:float}>,gst_pct:float} */
    public static function settings(): array
    {
        return \wh_q_settings(self::load());
    }

    public static function blockedMessage(int $creatorId, string $contentType): string
    {
        $db = self::load();

        return \wh_q_blocked_message($contentType, (int) \wh_q_free_limit($db, \wh_q_account($db, $creatorId), $contentType));
    }

    /** @return array{paid_balance:int,free_monthly_override:?int} */
    public static function account(int $creatorId): array
    {
        return \wh_q_account(self::load(), $creatorId);
    }

    public static function setOverride(int $creatorId, ?int $freeMonthly): void
    {
        $db = self::load();
        \wh_q_account($db, $creatorId);
        $db->prepare('UPDATE wh_credit_accounts SET free_monthly_override = :o WHERE creator_id = :c')
           ->execute(['o' => $freeMonthly, 'c' => $creatorId]);
    }

    /** @return list<array<string,mixed>> */
    public static function ledger(int $creatorId, int $limit = 50): array
    {
        $st = self::load()->prepare('SELECT * FROM wh_ledger WHERE creator_id = :c ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
        $st->execute(['c' => $creatorId]);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
