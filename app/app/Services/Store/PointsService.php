<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * eClinicPro Points (document/store-rewards-plan.md). 1 point = ₹1 off a store order.
 *
 *  - Every earning is a LOT (store_point_lots): welcome 100, referral 100, loyalty 10%.
 *    Lots are spent oldest-expiry first; store_point_txns is the append-only ledger.
 *  - Welcome points: all at once, only on an order whose eligible value is ≥ ₹500.
 *    That order uses no other points. Below ₹500 they are kept for a later order.
 *  - Referral / loyalty / admin ("standard") points: up to 10% of the eligible value.
 *  - Eligible value = items after coupons, excluding delivery.
 *  - Points are a platform-funded discount (PricingService puts them in platform_discount_paise).
 *  - Order life: place → reserve() (points held), paid → confirmPaid(), unpaid/expired → release().
 *  - Loyalty: an order that used NO points earns 10% (pending lot at payment). Fully delivered →
 *    onOrderDelivered() starts the return-window clock; matureDue() (cron) makes it spendable,
 *    recalculated for anything refunded. Whole order cancelled → onOrderCancelled().
 *  - Pending lots are shown straight away; only 'available' lots can be spent.
 */
final class PointsService
{
    private const STANDARD_KINDS = "('referral','loyalty','admin')";

    public static function enabled(): bool
    {
        return StoreSettings::get('store_points_enabled', '0') === '1';
    }

    /**
     * Grant the welcome lot the first time we see an eligible person: an OTP self-signup,
     * created after store_points_launch_at. Lazy, so every signup path (web modal,
     * /patient, mobile app) is covered without hooking each one.
     */
    public static function ensureWelcome(int $identityId): void
    {
        $points = StoreSettings::int('store_points_welcome', 100);
        $launch = strtotime(StoreSettings::get('store_points_launch_at', ''));
        if ($identityId <= 0 || $points <= 0 || $launch === false || !self::enabled()) {
            return;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare("SELECT 1 FROM store_point_lots WHERE identity_id = :i AND uniq_key = 'welcome'");
        $st->execute(['i' => $identityId]);
        if ($st->fetchColumn()) {
            return;
        }
        $st = $pdo->prepare(
            "SELECT 1 FROM patient_identities
              WHERE id = :i AND source = 'self_signup' AND is_active = 1
                AND phone_verified_at IS NOT NULL AND created_at >= :l"
        );
        $st->execute(['i' => $identityId, 'l' => date('Y-m-d H:i:s', $launch)]);
        if (!$st->fetchColumn()) {
            return;
        }
        self::grant($identityId, 'welcome', 'welcome', $points, 'available', 'Welcome points', null, null);
    }

    /**
     * Points summary for the cart/checkout/panel. Points held by the person's own unpaid
     * order count as available: placing a new order releases that order first.
     *
     * @return array{available: int, pending: int, welcome: int, standard: int}
     */
    public static function balance(int $identityId): array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT kind, status, SUM(points_left) AS left_pts, SUM(points) AS pts
               FROM store_point_lots
              WHERE identity_id = :i AND status IN ('pending','available')
                AND (status = 'pending' OR points_left > 0 AND (expires_at IS NULL OR expires_at > NOW()))
              GROUP BY kind, status"
        );
        $st->execute(['i' => $identityId]);
        $welcome = $standard = $pending = 0;
        foreach ($st->fetchAll() as $r) {
            if ($r['status'] === 'pending') {
                $pending += (int) $r['pts'];
            } elseif ($r['kind'] === 'welcome') {
                $welcome += (int) $r['left_pts'];
            } else {
                $standard += (int) $r['left_pts'];
            }
        }
        $held = $pdo->prepare(
            "SELECT points_kind, SUM(points_used) AS pts FROM store_orders
              WHERE identity_id = :i AND status = 'pending_payment' AND points_state = 'reserved'
              GROUP BY points_kind"
        );
        $held->execute(['i' => $identityId]);
        foreach ($held->fetchAll() as $r) {
            if ($r['points_kind'] === 'welcome') {
                $welcome += (int) $r['pts'];
            } else {
                $standard += (int) $r['pts'];
            }
        }

        return ['available' => $welcome + $standard, 'pending' => $pending, 'welcome' => $welcome, 'standard' => $standard];
    }

    /**
     * Everything the patient panel / app shows: balance, spendable + pending lots (with the reason
     * they're pending), recent history, the rules in force, and Refer & Earn.
     *
     * @return array<string, mixed>
     */
    public static function summary(int $identityId): array
    {
        self::ensureWelcome($identityId);
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT id, kind, points, points_left, status, note, available_at, expires_at, created_at
               FROM store_point_lots
              WHERE identity_id = :i
                AND (status = 'pending' OR status = 'available' AND points_left > 0 AND (expires_at IS NULL OR expires_at > NOW()))
              ORDER BY status = 'pending', expires_at, id"
        );
        $st->execute(['i' => $identityId]);
        $lots = array_map(static fn ($l) => [
            'kind' => (string) $l['kind'],
            'status' => (string) $l['status'],
            'points' => $l['status'] === 'pending' ? (int) $l['points'] : (int) $l['points_left'],
            'note' => $l['note'],
            'available_at' => $l['status'] === 'pending' ? $l['available_at'] : null,   // null = waiting on an event
            'expires_at' => $l['status'] === 'available' ? $l['expires_at'] : null,
        ], $st->fetchAll());

        $st = $pdo->prepare(
            "SELECT t.type, t.points, t.note, t.created_at, l.kind, o.order_no
               FROM store_point_txns t
               JOIN store_point_lots l ON l.id = t.lot_id
               LEFT JOIN store_orders o ON o.id = t.order_id
              WHERE t.identity_id = :i AND t.points <> 0
              ORDER BY t.id DESC LIMIT 30"
        );
        $st->execute(['i' => $identityId]);
        $history = array_map(static fn ($t) => [
            'type' => (string) $t['type'], 'kind' => (string) $t['kind'], 'points' => (int) $t['points'],
            'note' => $t['note'], 'order_no' => $t['order_no'], 'at' => (string) $t['created_at'],
        ], $st->fetchAll());

        return [
            'balance' => self::balance($identityId),
            'lots' => $lots,
            'history' => $history,
            'rules' => [
                'welcome_points' => StoreSettings::int('store_points_welcome', 100),
                'welcome_min_order_paise' => StoreSettings::int('store_points_welcome_min_order_paise', 50000),
                'referral_points' => StoreSettings::int('store_points_referral_each', 100),
                'redeem_cap_pct' => StoreSettings::int('store_points_redeem_cap_bp', 1000) / 100,
                'loyalty_pct' => StoreSettings::int('store_points_loyalty_earn_bp', 1000) / 100,
                'expiry_days' => StoreSettings::int('store_points_expiry_days', 180),
                'return_window_days' => StoreSettings::int('store_default_return_window_days', 7),
            ],
            'referral' => ReferralService::summary($identityId),
        ];
    }

    /**
     * How many points this order would use (§1 of the plan).
     *
     * @return array{kind: ?string, points: int, balance: array, welcome_add_paise: ?int}
     *         welcome_add_paise: "Add ₹X more to use your welcome points" (null = not applicable)
     */
    public static function plan(int $identityId, int $eligiblePaise): array
    {
        self::ensureWelcome($identityId);
        $bal = self::balance($identityId);
        $out = ['kind' => null, 'points' => 0, 'balance' => $bal, 'welcome_add_paise' => null];
        if ($eligiblePaise <= 0) {
            return $out;
        }
        $maxByValue = intdiv($eligiblePaise, 100);   // points can't exceed the item value
        $minWelcome = StoreSettings::int('store_points_welcome_min_order_paise', 50000);

        if ($bal['welcome'] > 0) {
            if ($eligiblePaise >= $minWelcome) {
                return ['kind' => 'welcome', 'points' => min($bal['welcome'], $maxByValue)] + $out;
            }
            $out['welcome_add_paise'] = $minWelcome - $eligiblePaise;
        }
        if ($bal['standard'] > 0) {
            $capBp = max(0, min(10000, StoreSettings::int('store_points_redeem_cap_bp', 1000)));
            $cap = intdiv(intdiv($eligiblePaise * $capBp, 10000), 100);
            $use = min($bal['standard'], $cap, $maxByValue);
            if ($use > 0) {
                $out['kind'] = 'standard';
                $out['points'] = $use;
            }
        }

        return $out;
    }

    /**
     * Hold $points of $kind for a just-created order. Call INSIDE the checkout transaction,
     * after the person's older unpaid orders were released. False = balance changed meanwhile.
     */
    public static function reserve(int $identityId, int $orderId, string $kind, int $points): bool
    {
        if ($points <= 0) {
            return true;
        }

        return self::take($identityId, $orderId, $kind, $points, true) === $points;
    }

    /** Order paid: held points are now spent; an order that used none earns pending loyalty points. */
    public static function confirmPaid(int $orderId): void
    {
        $pdo = Database::connection();
        $upd = $pdo->prepare("UPDATE store_orders SET points_state = 'redeemed' WHERE id = :id AND points_state = 'reserved'");
        $upd->execute(['id' => $orderId]);
        if ($upd->rowCount() === 1) {
            return;
        }
        self::grantLoyalty($orderId);
        // Paid after it had expired (late payment, order revived): the customer was charged the
        // discounted total, so take the points again if they still have them.
        $st = $pdo->prepare("SELECT identity_id, points_used, points_kind FROM store_orders WHERE id = :id AND points_state = 'released'");
        $st->execute(['id' => $orderId]);
        $o = $st->fetch();
        if (!$o || (int) $o['points_used'] <= 0) {
            return;
        }
        $got = self::take((int) $o['identity_id'], $orderId, (string) $o['points_kind'], (int) $o['points_used'], false);
        if ($got < (int) $o['points_used']) {
            error_log("[PointsService] order $orderId paid late: re-took $got of {$o['points_used']} points");
        }
        $pdo->prepare("UPDATE store_orders SET points_state = 'redeemed' WHERE id = :id")->execute(['id' => $orderId]);
    }

    /** Unpaid order cancelled / expired / payment failed: give the held points back (same lots, same expiry). */
    public static function release(int $orderId, string $note = 'Order not paid'): void
    {
        self::giveBack($orderId, 'reserved', $note);
    }

    /**
     * The order just became fully delivered (OrderService::recomputeStatus, once).
     * Unlocks Refer & Earn for the buyer, qualifies their referral, starts the loyalty clock.
     */
    public static function onOrderDelivered(int $orderId): void
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT identity_id FROM store_orders WHERE id = :id');
        $st->execute(['id' => $orderId]);
        $identityId = (int) $st->fetchColumn();
        if ($identityId <= 0) {
            return;
        }
        // Return window end = the latest package's settle_after (delivered_at + window).
        $st = $pdo->prepare(
            "SELECT MAX(settle_after) FROM store_vendor_orders WHERE order_id = :o AND status IN ('delivered','completed')"
        );
        $st->execute(['o' => $orderId]);
        $availableAt = (string) ($st->fetchColumn() ?: '');
        if ($availableAt === '') {
            $availableAt = date('Y-m-d H:i:s', time() + max(0, StoreSettings::int('store_default_return_window_days', 7)) * 86400);
        }
        ReferralService::onDelivered($orderId, $identityId, $availableAt);
        $pdo->prepare(
            "UPDATE store_point_lots SET available_at = :a, note = 'Available after the return window'
              WHERE order_id = :o AND kind = 'loyalty' AND status = 'pending'"
        )->execute(['a' => $availableAt, 'o' => $orderId]);
    }

    /** Every package of a PAID order was cancelled: points used come back, pending loyalty is dropped. */
    public static function onOrderCancelled(int $orderId): void
    {
        self::giveBack($orderId, 'redeemed', 'Order cancelled');
        self::reverseLoyalty($orderId, 'Order cancelled');
    }

    /**
     * Cron: pending lots whose return window has ended become spendable.
     * Loyalty is recalculated on what was kept; a referral whose qualifying order was fully
     * refunded waits for the friend's next delivered order. Orders with an open return wait.
     *
     * @return int lots made available
     */
    public static function matureDue(int $limit = 200): int
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT l.*, r.qualifying_order_id FROM store_point_lots l
               LEFT JOIN store_referrals r ON r.id = l.referral_id
              WHERE l.status = 'pending' AND l.available_at IS NOT NULL AND l.available_at <= NOW()
              ORDER BY l.available_at LIMIT " . max(1, min(2000, $limit))
        );
        $st->execute();
        $openReturn = $pdo->prepare(
            "SELECT 1 FROM store_returns WHERE order_id = :o AND status IN ('" . implode("','", ReturnService::OPEN) . "') LIMIT 1"
        );
        $n = 0;
        $ready = [];   // identity_id => points made usable this run (one message each)
        foreach ($st->fetchAll() as $lot) {
            $orderId = (int) ($lot['kind'] === 'loyalty' ? $lot['order_id'] : $lot['qualifying_order_id']);
            if ($orderId <= 0) {
                continue;
            }
            $openReturn->execute(['o' => $orderId]);
            if ($openReturn->fetchColumn()) {
                continue;   // decide after the return is settled
            }
            [$paid, $refunded] = self::orderValue($orderId);
            $kept = max(0, $paid - $refunded);
            if ($lot['kind'] === 'loyalty') {
                $points = min((int) $lot['points'], self::loyaltyPoints($kept));
                if ($points <= 0) {
                    self::reverseLoyalty($orderId, 'Order refunded');
                    continue;
                }
            } else {
                if ($kept <= 0) {
                    ReferralService::requalify((int) $lot['referral_id']);
                    continue;
                }
                $points = (int) $lot['points'];
            }
            if (self::makeAvailable($lot, $points)) {
                $n++;
                $ready[(int) $lot['identity_id']] = ($ready[(int) $lot['identity_id']] ?? 0) + $points;
            }
            if ($lot['kind'] === 'referral') {
                $pdo->prepare("UPDATE store_referrals SET status = 'rewarded' WHERE id = :id AND status = 'qualified'")
                    ->execute(['id' => (int) $lot['referral_id']]);
            }
        }
        foreach ($ready as $identityId => $points) {
            StoreNotifier::pointsReady($identityId, $points, self::balance($identityId)['available']);
        }

        return $n;
    }

    /** Pending loyalty lot for a paid order that used no points (idempotent). */
    private static function grantLoyalty(int $orderId): void
    {
        if (!self::enabled()) {
            return;
        }
        $st = Database::connection()->prepare(
            "SELECT identity_id, items_subtotal_paise, discount_paise, points_used FROM store_orders WHERE id = :id AND payment_status = 'paid'"
        );
        $st->execute(['id' => $orderId]);
        $o = $st->fetch();
        if (!$o || (int) $o['points_used'] > 0) {
            return;
        }
        $points = self::loyaltyPoints((int) $o['items_subtotal_paise'] - (int) $o['discount_paise']);
        self::grant((int) $o['identity_id'], 'loyalty', 'loyalty:' . $orderId, $points, 'pending',
            'Available after delivery + return window', $orderId, null);
    }

    /** 10% (store_points_loyalty_earn_bp) of what was paid for items, in whole points. */
    public static function loyaltyPoints(int $itemsPaidPaise): int
    {
        $bp = max(0, min(10000, StoreSettings::int('store_points_loyalty_earn_bp', 1000)));

        return intdiv(intdiv(max(0, $itemsPaidPaise) * $bp, 10000), 100);
    }

    /** @return array{0: int, 1: int} [paid for items, refunded for items] in paise */
    private static function orderValue(int $orderId): array
    {
        $st = Database::connection()->prepare('SELECT line_total_paise, qty, qty_refunded FROM store_order_items WHERE order_id = :o');
        $st->execute(['o' => $orderId]);
        $paid = $refunded = 0;
        foreach ($st->fetchAll() as $it) {
            $paid += (int) $it['line_total_paise'];
            $refunded += PricingService::unitShare((int) $it['line_total_paise'], (int) $it['qty'], 0, (int) $it['qty_refunded']);
        }

        return [$paid, $refunded];
    }

    /** @param array<string, mixed> $lot */
    private static function makeAvailable(array $lot, int $points): bool
    {
        $days = max(1, StoreSettings::int('store_points_expiry_days', 180));
        $upd = Database::connection()->prepare(
            "UPDATE store_point_lots SET status = 'available', points = :p, points_left = :pl, note = NULL,
                    available_at = NOW(), expires_at = :e
              WHERE id = :id AND status = 'pending'"
        );
        $upd->execute(['p' => $points, 'pl' => $points, 'e' => date('Y-m-d H:i:s', time() + $days * 86400), 'id' => (int) $lot['id']]);
        if ($upd->rowCount() !== 1) {
            return false;
        }
        self::txn((int) $lot['identity_id'], (int) $lot['id'], $lot['order_id'] !== null ? (int) $lot['order_id'] : null, 'available', $points, null);

        return true;
    }

    private static function reverseLoyalty(int $orderId, string $note): void
    {
        $pdo = Database::connection();
        $st = $pdo->prepare("SELECT id, identity_id FROM store_point_lots WHERE order_id = :o AND kind = 'loyalty' AND status = 'pending'");
        $st->execute(['o' => $orderId]);
        $upd = $pdo->prepare("UPDATE store_point_lots SET status = 'reversed', note = :n WHERE id = :id AND status = 'pending'");
        foreach ($st->fetchAll() as $lot) {
            $upd->execute(['n' => $note, 'id' => (int) $lot['id']]);
            if ($upd->rowCount() === 1) {
                self::txn((int) $lot['identity_id'], (int) $lot['id'], $orderId, 'reverse', 0, $note);
            }
        }
    }

    /** Return the points an order took to the lots they came from, if the order is in $fromState. */
    private static function giveBack(int $orderId, string $fromState, string $note): void
    {
        $pdo = Database::connection();
        $upd = $pdo->prepare("UPDATE store_orders SET points_state = 'released' WHERE id = :id AND points_state = :f");
        $upd->execute(['id' => $orderId, 'f' => $fromState]);
        if ($upd->rowCount() !== 1) {
            return;
        }
        $st = $pdo->prepare(
            "SELECT lot_id, identity_id, -SUM(points) AS pts FROM store_point_txns
              WHERE order_id = :o AND type IN ('spend','refund') GROUP BY lot_id, identity_id HAVING pts > 0"
        );
        $st->execute(['o' => $orderId]);
        $back = $pdo->prepare('UPDATE store_point_lots SET points_left = points_left + :p WHERE id = :id');
        foreach ($st->fetchAll() as $r) {
            $back->execute(['p' => (int) $r['pts'], 'id' => (int) $r['lot_id']]);
            self::txn((int) $r['identity_id'], (int) $r['lot_id'], $orderId, 'refund', (int) $r['pts'], $note);
        }
    }

    /**
     * Admin: add points (a new 'admin' lot, spendable now, like referral points) or take points
     * away (from the soonest-expiring lots of any kind). Always logged with the admin and the reason.
     *
     * @return array{ok: bool, error?: string}
     */
    public static function adjust(int $identityId, int $points, string $note, int $adminId): array
    {
        $note = trim($note);
        if ($identityId <= 0 || $points === 0 || abs($points) > 100000) {
            return ['ok' => false, 'error' => 'Enter a points amount (positive to add, negative to remove).'];
        }
        if ($note === '') {
            return ['ok' => false, 'error' => 'Please write the reason; it is shown in the customer\'s history.'];
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($points > 0) {
                $lotId = self::grant($identityId, 'admin', 'admin:' . bin2hex(random_bytes(6)), $points, 'available', $note, null, null);
                $pdo->prepare("UPDATE store_point_txns SET type = 'adjust', actor_type = 'admin', actor_id = :a WHERE lot_id = :l")
                    ->execute(['a' => $adminId, 'l' => $lotId]);
            } else {
                $st = $pdo->prepare(
                    "SELECT id, points_left FROM store_point_lots
                      WHERE identity_id = :i AND status = 'available' AND points_left > 0 AND (expires_at IS NULL OR expires_at > NOW())
                      ORDER BY expires_at IS NULL, expires_at, id FOR UPDATE"
                );
                $st->execute(['i' => $identityId]);
                $lots = $st->fetchAll();
                $want = -$points;
                if (array_sum(array_map(static fn ($l) => (int) $l['points_left'], $lots)) < $want) {
                    $pdo->rollBack();

                    return ['ok' => false, 'error' => 'The customer has fewer spendable points than that.'];
                }
                $upd = $pdo->prepare('UPDATE store_point_lots SET points_left = points_left - :p WHERE id = :id');
                foreach ($lots as $lot) {
                    if ($want <= 0) {
                        break;
                    }
                    $p = min((int) $lot['points_left'], $want);
                    $upd->execute(['p' => $p, 'id' => (int) $lot['id']]);
                    self::txn($identityId, (int) $lot['id'], null, 'adjust', -$p, $note, 'admin', $adminId);
                    $want -= $p;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[PointsService::adjust] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not adjust points.'];
        }

        return ['ok' => true];
    }

    /** Admin: cancel a lot that hasn't become spendable yet (e.g. a suspicious referral). */
    public static function reversePending(int $lotId, string $note, int $adminId): bool
    {
        $pdo = Database::connection();
        $st = $pdo->prepare("SELECT identity_id, order_id FROM store_point_lots WHERE id = :id AND status = 'pending'");
        $st->execute(['id' => $lotId]);
        $lot = $st->fetch();
        if (!$lot) {
            return false;
        }
        $upd = $pdo->prepare("UPDATE store_point_lots SET status = 'reversed', note = :n WHERE id = :id AND status = 'pending'");
        $upd->execute(['n' => mb_substr($note, 0, 255), 'id' => $lotId]);
        if ($upd->rowCount() !== 1) {
            return false;
        }
        self::txn((int) $lot['identity_id'], $lotId, $lot['order_id'] !== null ? (int) $lot['order_id'] : null, 'reverse', 0, $note, 'admin', $adminId);

        return true;
    }

    /** Cron: available lots past their expiry. @return int lots expired */
    public static function expireDue(int $limit = 500): int
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT id, identity_id, points_left FROM store_point_lots
              WHERE status = 'available' AND expires_at IS NOT NULL AND expires_at <= NOW()
              ORDER BY expires_at LIMIT " . max(1, min(5000, $limit))
        );
        $st->execute();
        $upd = $pdo->prepare("UPDATE store_point_lots SET status = 'expired', points_left = 0 WHERE id = :id AND status = 'available'");
        $n = 0;
        foreach ($st->fetchAll() as $lot) {
            $upd->execute(['id' => (int) $lot['id']]);
            if ($upd->rowCount() === 1) {
                $n++;
                if ((int) $lot['points_left'] > 0) {
                    self::txn((int) $lot['identity_id'], (int) $lot['id'], null, 'expire', -(int) $lot['points_left'], 'Points expired');
                }
            }
        }

        return $n;
    }

    /**
     * Create a lot (idempotent on identity + uniq_key). 'available' lots get their expiry now;
     * 'pending' lots get it when they become available.
     *
     * @return int lot id (0 if it already existed)
     */
    public static function grant(int $identityId, string $kind, string $uniqKey, int $points, string $status, ?string $note, ?int $orderId, ?int $referralId): int
    {
        if ($points <= 0) {
            return 0;
        }
        $pdo = Database::connection();
        $days = max(1, StoreSettings::int('store_points_expiry_days', 180));
        $available = $status === 'available';
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO store_point_lots
                (identity_id, kind, uniq_key, points, points_left, status, referral_id, order_id, note, available_at, expires_at)
             VALUES (:i, :k, :u, :p, :pl, :s, :r, :o, :n, :aa, :ea)'
        );
        $ins->execute([
            'i' => $identityId, 'k' => $kind, 'u' => $uniqKey, 'p' => $points, 'pl' => $available ? $points : 0,
            's' => $status, 'r' => $referralId, 'o' => $orderId, 'n' => $note !== null ? mb_substr($note, 0, 255) : null,
            'aa' => $available ? date('Y-m-d H:i:s') : null,
            'ea' => $available ? date('Y-m-d H:i:s', time() + $days * 86400) : null,
        ]);
        if ($ins->rowCount() !== 1) {
            return 0;
        }
        $lotId = (int) $pdo->lastInsertId();
        // Pending lots don't count yet: the 'available' txn adds them to the balance later.
        self::txn($identityId, $lotId, $orderId, 'earn', $available ? $points : 0, $note);

        return $lotId;
    }

    /**
     * Take up to $points from the person's lots of $kind, oldest expiry first.
     * $all = true: take nothing unless the full amount is there. @return int points taken
     */
    private static function take(int $identityId, int $orderId, string $kind, int $points, bool $all): int
    {
        $pdo = Database::connection();
        $kinds = $kind === 'welcome' ? "('welcome')" : self::STANDARD_KINDS;
        $st = $pdo->prepare(
            "SELECT id, points_left FROM store_point_lots
              WHERE identity_id = :i AND status = 'available' AND points_left > 0 AND kind IN $kinds
                AND (expires_at IS NULL OR expires_at > NOW())
              ORDER BY expires_at IS NULL, expires_at, id FOR UPDATE"
        );
        $st->execute(['i' => $identityId]);
        $lots = $st->fetchAll();
        if ($all && array_sum(array_map(static fn ($l) => (int) $l['points_left'], $lots)) < $points) {
            return 0;
        }
        $upd = $pdo->prepare('UPDATE store_point_lots SET points_left = points_left - :p WHERE id = :id AND points_left >= :p2');
        $taken = 0;
        foreach ($lots as $lot) {
            if ($taken >= $points) {
                break;
            }
            $p = min((int) $lot['points_left'], $points - $taken);
            $upd->execute(['p' => $p, 'id' => (int) $lot['id'], 'p2' => $p]);
            if ($upd->rowCount() === 1) {
                $taken += $p;
                self::txn($identityId, (int) $lot['id'], $orderId, 'spend', -$p, null);
            }
        }

        return $taken;
    }

    private static function txn(int $identityId, int $lotId, ?int $orderId, string $type, int $points, ?string $note, string $actorType = 'system', ?int $actorId = null): void
    {
        Database::connection()->prepare(
            'INSERT INTO store_point_txns (identity_id, lot_id, order_id, type, points, note, actor_type, actor_id)
             VALUES (:i, :l, :o, :t, :p, :n, :at, :ai)'
        )->execute([
            'i' => $identityId, 'l' => $lotId, 'o' => $orderId, 't' => $type, 'p' => $points,
            'n' => $note !== null ? mb_substr($note, 0, 255) : null, 'at' => $actorType, 'ai' => $actorId,
        ]);
    }
}
