<?php

declare(strict_types=1);

namespace App\Services\Wellness;

use App\Core\Database;
use PDO;

/**
 * /admin/wellness/billing — prices (free posts per type, packs, GST %),
 * credit-pack orders and revenue; per-creator credits on the creator page.
 */
final class BillingAdminService
{
    public const TYPES = ['recipe' => 'Recipes', 'exercise' => 'Exercises', 'tip' => 'Quick tips', 'video' => 'Videos', 'event' => 'Events'];

    /**
     * @param array<string,mixed> $in free[type], pack_qty[], pack_price[], gst_pct
     * @return array{ok:bool,error?:string}
     */
    public static function saveSettings(array $in): array
    {
        $free = [];
        foreach (array_keys(self::TYPES) as $t) {
            $v = (int) ($in['free'][$t] ?? -1);
            if ($v < 0 || $v > 1000) {
                return ['ok' => false, 'error' => 'Free posts per month must be 0–1000 for every type.'];
            }
            $free[$t] = $v;
        }
        $packs = [];
        foreach ((array) ($in['pack_qty'] ?? []) as $i => $qty) {
            $qty = (int) $qty;
            $price = round((float) ($in['pack_price'][$i] ?? 0), 2);
            if ($qty <= 0 && $price <= 0) {
                continue;   // empty row
            }
            if ($qty <= 0 || $qty > 1000 || $price <= 0 || $price > 100000) {
                return ['ok' => false, 'error' => 'Each pack needs a number of posts (1–1000) and a price in rupees.'];
            }
            $packs[] = ['qty' => $qty, 'price' => $price];
        }
        if (!$packs) {
            return ['ok' => false, 'error' => 'Keep at least one pack.'];
        }
        usort($packs, static fn ($a, $b) => $a['qty'] <=> $b['qty']);
        $gst = round((float) ($in['gst_pct'] ?? 18), 2);
        if ($gst < 0 || $gst > 28) {
            return ['ok' => false, 'error' => 'GST % looks wrong.'];
        }
        $st = Database::connection()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, is_secret) VALUES (:k, :v, 0)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $st->execute(['k' => 'wh_free_monthly', 'v' => json_encode($free)]);
        $st->execute(['k' => 'wh_packs', 'v' => json_encode($packs)]);
        $st->execute(['k' => 'wh_gst_pct', 'v' => (string) $gst]);

        return ['ok' => true];
    }

    /** @return list<array<string,mixed>> */
    public static function orders(string $status): array
    {
        $where = in_array($status, ['paid', 'pending', 'failed'], true) ? 'o.status = :s' : '1=1';
        $st = Database::connection()->prepare(
            "SELECT o.*, w.display_name AS creator_name FROM wh_orders o LEFT JOIN wh_creators w ON w.id = o.creator_id
             WHERE $where ORDER BY o.id DESC LIMIT 300"
        );
        $st->execute($where === '1=1' ? [] : ['s' => $status]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{month_base:float,month_gst:float,month_total:float,month_n:int,all_total:float,credits_out:int} */
    public static function revenue(): array
    {
        try {
            $db = Database::connection();
            $m = $db->query(
                "SELECT COALESCE(SUM(base_amount),0) b, COALESCE(SUM(gst_amount),0) g, COALESCE(SUM(total_amount),0) t, COUNT(*) n
                 FROM wh_orders WHERE status = 'paid' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            )->fetch(PDO::FETCH_ASSOC);
            $all = (float) $db->query("SELECT COALESCE(SUM(total_amount),0) FROM wh_orders WHERE status = 'paid'")->fetchColumn();
            $out = (int) $db->query('SELECT COALESCE(SUM(paid_balance),0) FROM wh_credit_accounts')->fetchColumn();

            return ['month_base' => (float) $m['b'], 'month_gst' => (float) $m['g'], 'month_total' => (float) $m['t'], 'month_n' => (int) $m['n'], 'all_total' => $all, 'credits_out' => $out];
        } catch (\Throwable) {
            return ['month_base' => 0.0, 'month_gst' => 0.0, 'month_total' => 0.0, 'month_n' => 0, 'all_total' => 0.0, 'credits_out' => 0];
        }
    }

    /** @return array<string,mixed>|null */
    public static function order(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM wh_orders WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Grant (+) or deduct (−) credits, and/or set a per-creator free-posts override.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function adjustCreator(int $creatorId, int $qty, string $note, string $override, ?int $adminId): array
    {
        if (CreatorService::find($creatorId) === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }
        $note = CreatorService::clean($note, 200);
        if ($qty !== 0) {
            if ($note === null) {
                return ['ok' => false, 'error' => 'note_required'];
            }
            Quota::adjust($creatorId, $qty, $qty > 0 ? 'admin_grant' : 'admin_deduct', null, $note, $adminId);
            CreatorService::log($creatorId, $qty > 0 ? 'credits_granted' : 'credits_deducted', abs($qty) . ' — ' . $note, $adminId);
        }
        $override = trim($override);
        $newOverride = $override === '' ? null : max(0, min(1000, (int) $override));
        if ($newOverride !== Quota::account($creatorId)['free_monthly_override']) {
            Quota::setOverride($creatorId, $newOverride);
            CreatorService::log($creatorId, 'free_override', $newOverride === null ? 'Back to platform default' : $newOverride . ' free posts / type / month', $adminId);
        }

        return ['ok' => true];
    }
}
