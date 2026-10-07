<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;

/**
 * Refer & Earn (document/store-rewards-plan.md).
 *
 *  - Everyone has a code (ECP-RAHUL7K); it only WORKS once its owner's first order is fully delivered.
 *  - A friend signs up with the code (link /r/{code} → cookie ecp_ref, or typed) → store_referrals row
 *    + a pending 100-point lot for BOTH people, shown right away.
 *  - The friend's first order fully delivered → referral 'qualified', both lots get available_at =
 *    end of the return window. PointsService::matureDue() releases them (or, if that order was fully
 *    refunded, puts the referral back to 'signed_up' so the friend's next delivered order counts).
 *  - Soft fraud flags only (never block): the friend's delivery address or phone matches the referrer's.
 */
final class ReferralService
{
    public const COOKIE = 'ecp_ref';

    /** The person's code, created on first ask. @return array{code: string, active: bool} */
    public static function codeFor(int $identityId): array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT code, activated_at FROM store_referral_codes WHERE identity_id = :i');
        $st->execute(['i' => $identityId]);
        $row = $st->fetch();
        if (!$row) {
            $n = $pdo->prepare('SELECT first_name, name FROM patient_identities WHERE id = :i');
            $n->execute(['i' => $identityId]);
            $p = $n->fetch() ?: [];
            $stem = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) (($p['first_name'] ?? '') ?: explode(' ', (string) ($p['name'] ?? ''))[0])));
            $stem = substr($stem !== '' && $stem !== 'PATIENT' ? $stem : 'FRIEND', 0, 6);
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $ins = $pdo->prepare('INSERT IGNORE INTO store_referral_codes (identity_id, code) VALUES (:i, :c)');
            for ($try = 0; $try < 8; $try++) {
                $code = 'ECP-' . $stem;
                for ($k = 0; $k < 3; $k++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $ins->execute(['i' => $identityId, 'c' => $code]);
                if ($ins->rowCount() === 1) {
                    break;
                }
            }
            $st->execute(['i' => $identityId]);
            $row = $st->fetch() ?: ['code' => '', 'activated_at' => null];
        }

        return ['code' => (string) $row['code'], 'active' => $row['activated_at'] !== null];
    }

    /**
     * Refer & Earn card: the code (locked until the first delivered order), the share link,
     * and the friends who signed up with it (first name only).
     *
     * @return array<string, mixed>
     */
    public static function summary(int $identityId): array
    {
        $code = self::codeFor($identityId);
        $st = Database::connection()->prepare(
            "SELECT r.status, r.created_at, p.first_name, p.name
               FROM store_referrals r
               LEFT JOIN patient_identities p ON p.id = r.referee_identity_id
              WHERE r.referrer_identity_id = :i
              ORDER BY r.id DESC LIMIT 100"
        );
        $st->execute(['i' => $identityId]);
        $friends = array_map(static fn ($r) => [
            'name' => (string) ((($r['first_name'] ?? '') ?: explode(' ', (string) ($r['name'] ?? 'Friend'))[0]) ?: 'Friend'),
            // signed_up = waiting for their first delivered order · qualified = in return window · rewarded = points given
            'status' => (string) $r['status'],
            'joined_at' => (string) $r['created_at'],
        ], $st->fetchAll());
        $base = rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/');

        return [
            'code' => $code['code'],
            'active' => $code['active'],
            'link' => $base . '/r/' . $code['code'],
            'friends' => $friends,
            'rewarded' => count(array_filter($friends, static fn ($f) => $f['status'] === 'rewarded')),
        ];
    }

    public static function normalize(string $code): string
    {
        $code = strtoupper(trim($code));

        return preg_match('/^ECP-[A-Z]{1,6}[2-9A-Z]{3}$/', $code) ? $code : '';
    }

    /**
     * A NEW self-signup arrived with a code. Silently ignores anything that doesn't qualify
     * (points off, code unknown or not yet active, own code, already referred, signed up before launch).
     */
    public static function capture(int $refereeId, string $code): bool
    {
        $code = self::normalize($code);
        $launch = strtotime(StoreSettings::get('store_points_launch_at', ''));
        if ($code === '' || $launch === false || !PointsService::enabled()) {
            return false;
        }
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT identity_id FROM store_referral_codes WHERE code = :c AND activated_at IS NOT NULL');
        $st->execute(['c' => $code]);
        $referrerId = (int) $st->fetchColumn();
        if ($referrerId <= 0 || $referrerId === $refereeId) {
            return false;
        }
        $st = $pdo->prepare(
            "SELECT first_name, name FROM patient_identities
              WHERE id = :i AND source = 'self_signup' AND is_active = 1 AND created_at >= :l"
        );
        $st->execute(['i' => $refereeId, 'l' => date('Y-m-d H:i:s', $launch)]);
        $referee = $st->fetch();
        if (!$referee) {
            return false;
        }
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO store_referrals (referrer_identity_id, referee_identity_id, code) VALUES (:r, :e, :c)'
        );
        $ins->execute(['r' => $referrerId, 'e' => $refereeId, 'c' => $code]);
        if ($ins->rowCount() !== 1) {
            return false;
        }
        $refId = (int) $pdo->lastInsertId();
        $each = StoreSettings::int('store_points_referral_each', 100);
        $friend = ($referee['first_name'] ?? '') ?: explode(' ', (string) $referee['name'])[0];
        PointsService::grant($referrerId, 'referral', 'ref:' . $refId, $each, 'pending',
            'Waiting for ' . $friend . '\'s first order to be delivered', null, $refId);
        PointsService::grant($refereeId, 'referral', 'ref:' . $refId, $each, 'pending',
            'Waiting for your first order to be delivered', null, $refId);

        return true;
    }

    /**
     * Called once when an order becomes fully delivered (PointsService::onOrderDelivered).
     * Unlocks the owner's code; if they were referred and this is their first delivered order,
     * starts the return-window clock on both referral lots.
     */
    public static function onDelivered(int $orderId, int $identityId, string $availableAt): void
    {
        $pdo = Database::connection();
        self::codeFor($identityId);
        $pdo->prepare('UPDATE store_referral_codes SET activated_at = NOW() WHERE identity_id = :i AND activated_at IS NULL')
            ->execute(['i' => $identityId]);

        $st = $pdo->prepare("SELECT * FROM store_referrals WHERE referee_identity_id = :i AND status = 'signed_up'");
        $st->execute(['i' => $identityId]);
        $ref = $st->fetch();
        if (!$ref) {
            return;
        }
        $pdo->prepare(
            "UPDATE store_referrals SET status = 'qualified', qualifying_order_id = :o, flag_reason = :f
              WHERE id = :id AND status = 'signed_up'"
        )->execute(['o' => $orderId, 'f' => self::flag($orderId, (int) $ref['referrer_identity_id']), 'id' => (int) $ref['id']]);
        $pdo->prepare(
            "UPDATE store_point_lots SET available_at = :a, note = 'Available after the return window'
              WHERE referral_id = :r AND status = 'pending'"
        )->execute(['a' => $availableAt, 'r' => (int) $ref['id']]);
    }

    /** The qualifying order was fully refunded: wait for the friend's next delivered order instead. */
    public static function requalify(int $referralId): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            "UPDATE store_referrals SET status = 'signed_up', qualifying_order_id = NULL WHERE id = :id AND status = 'qualified'"
        )->execute(['id' => $referralId]);
        $pdo->prepare(
            "UPDATE store_point_lots SET available_at = NULL, note = 'Waiting for a delivered order (the last one was returned)'
              WHERE referral_id = :r AND status = 'pending'"
        )->execute(['r' => $referralId]);
    }

    /**
     * Admin: reject a referral that hasn't paid out yet. Both pending lots are cancelled.
     * (Points already given can be taken back with PointsService::adjust.)
     *
     * @return array{ok: bool, error?: string}
     */
    public static function reject(int $referralId, string $reason, int $adminId): array
    {
        $pdo = Database::connection();
        $upd = $pdo->prepare(
            "UPDATE store_referrals SET status = 'rejected', flag_reason = :f WHERE id = :id AND status IN ('signed_up','qualified')"
        );
        $upd->execute(['f' => mb_substr('Rejected by admin: ' . ($reason !== '' ? $reason : 'no reason given'), 0, 255), 'id' => $referralId]);
        if ($upd->rowCount() !== 1) {
            return ['ok' => false, 'error' => 'Only referrals that haven\'t paid out yet can be rejected.'];
        }
        $st = $pdo->prepare("SELECT id FROM store_point_lots WHERE referral_id = :r AND status = 'pending'");
        $st->execute(['r' => $referralId]);
        foreach ($st->fetchAll() as $lot) {
            PointsService::reversePending((int) $lot['id'], 'Referral not eligible', $adminId);
        }

        return ['ok' => true];
    }

    /** Admin review hint, never a block. */
    private static function flag(int $orderId, int $referrerId): ?string
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT contact_phone, ship_address_json FROM store_orders WHERE id = :id');
        $st->execute(['id' => $orderId]);
        $o = $st->fetch();
        if (!$o) {
            return null;
        }
        $st = $pdo->prepare('SELECT phone FROM patient_identities WHERE id = :i');
        $st->execute(['i' => $referrerId]);
        $referrerPhone = (string) $st->fetchColumn();
        $digits = static fn (string $p) => substr(preg_replace('/\D/', '', $p), -10);
        if ($referrerPhone !== '' && $digits($referrerPhone) === $digits((string) $o['contact_phone'])) {
            return 'Order phone matches the referrer';
        }
        $ship = json_decode((string) $o['ship_address_json'], true) ?: [];
        $key = static fn ($a) => strtolower(preg_replace('/\W+/', '', (string) ($a['line1'] ?? ''))) . '|' . (string) ($a['pincode'] ?? '');
        $st = $pdo->prepare('SELECT phone, line1, pincode FROM store_addresses WHERE identity_id = :i AND deleted_at IS NULL');
        $st->execute(['i' => $referrerId]);
        foreach ($st->fetchAll() as $a) {
            if ($key($a) === $key($ship)) {
                return 'Delivery address matches the referrer';
            }
            if ($digits((string) $a['phone']) === $digits((string) $o['contact_phone'])) {
                return 'Order phone matches a referrer address';
            }
        }

        return null;
    }
}
