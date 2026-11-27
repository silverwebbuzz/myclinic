<?php
// =====================================================================
// partials/wellness_quota.php — Wellness Hub post quota + credits (Phase 4).
//
// ONE implementation, loaded by BOTH sides:
//   - eclinicpro.com  (my-health-tips: recipes, exercises, tips, videos)
//   - app.eclinicpro.com (Creator Studio camps / events, admin review, Razorpay)
// so the rules can never drift. Plain functions over a PDO handle — no
// framework, no other includes. Plan: document/wellness-hub-plan.md §4.
//
// Rules:
//   - Free camps: unlimited, never charged.
//   - Every other type: N free posts per type per calendar month (IST,
//     platform setting wh_free_monthly, per-creator override), then 1 credit.
//   - Submit reserves (free slot first, else a credit). Approve → used.
//     Reject / withdraw → released (slot or credit returned).
//   - A post keeps its charge: resubmitting after "changes", editing a live
//     post or republishing costs nothing more.
//   - Month = month of submission.
// =====================================================================

declare(strict_types=1);

const WH_Q_DEFAULT_FREE = 5;

/** @return array{free: array<string,int>, packs: list<array{qty:int,price:float}>, gst_pct: float} */
function wh_q_settings(PDO $db): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $out = [
        'free' => ['recipe' => 5, 'exercise' => 5, 'tip' => 5, 'video' => 5, 'event' => 5],
        'packs' => [['qty' => 1, 'price' => 49.0], ['qty' => 5, 'price' => 199.0], ['qty' => 10, 'price' => 299.0]],
        'gst_pct' => 18.0,
    ];
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('wh_free_monthly','wh_packs','wh_gst_pct')")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
        $free = json_decode((string) ($rows['wh_free_monthly'] ?? ''), true);
        if (is_array($free)) {
            foreach ($free as $k => $v) {
                $out['free'][(string) $k] = max(0, (int) $v);
            }
        }
        $packs = json_decode((string) ($rows['wh_packs'] ?? ''), true);
        if (is_array($packs) && $packs) {
            $clean = [];
            foreach ($packs as $p) {
                if ((int) ($p['qty'] ?? 0) > 0 && (float) ($p['price'] ?? 0) > 0) {
                    $clean[] = ['qty' => (int) $p['qty'], 'price' => round((float) $p['price'], 2)];
                }
            }
            if ($clean) {
                $out['packs'] = $clean;
            }
        }
        if (isset($rows['wh_gst_pct']) && is_numeric($rows['wh_gst_pct'])) {
            $out['gst_pct'] = max(0.0, (float) $rows['wh_gst_pct']);
        }
    } catch (Throwable $e) {
        // settings not seeded: defaults above
    }
    return $cache = $out;
}

/** Billing is live only once the Phase 4 tables exist. */
function wh_q_enabled(PDO $db): bool
{
    static $on = null;
    if ($on === null) {
        try {
            $on = $db->query("SHOW TABLES LIKE 'wh_post_charges'")->fetchColumn() !== false;
        } catch (Throwable $e) {
            $on = false;
        }
    }
    return $on;
}

function wh_q_period(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m');
}

/** Wellness creator id for a Health Tips contributor (null if none). */
function wh_q_creator_for_contributor(PDO $db, int $contributorId): ?int
{
    try {
        $st = $db->prepare('SELECT id FROM wh_creators WHERE legacy_contributor_id = :c LIMIT 1');
        $st->execute(['c' => $contributorId]);
        $id = $st->fetchColumn();
        return $id !== false ? (int) $id : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** @return array{paid_balance:int, free_monthly_override:?int} */
function wh_q_account(PDO $db, int $creatorId, bool $forUpdate = false): array
{
    $db->prepare('INSERT IGNORE INTO wh_credit_accounts (creator_id) VALUES (:c)')->execute(['c' => $creatorId]);
    $st = $db->prepare('SELECT paid_balance, free_monthly_override FROM wh_credit_accounts WHERE creator_id = :c' . ($forUpdate ? ' FOR UPDATE' : ''));
    $st->execute(['c' => $creatorId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['paid_balance' => 0, 'free_monthly_override' => null];
    return [
        'paid_balance' => (int) $r['paid_balance'],
        'free_monthly_override' => $r['free_monthly_override'] !== null ? (int) $r['free_monthly_override'] : null,
    ];
}

/** Free posts allowed this month for one type (null = unlimited, i.e. free camps). */
function wh_q_free_limit(PDO $db, array $account, string $contentType): ?int
{
    if ($contentType === 'free_camp') {
        return null;
    }
    return $account['free_monthly_override'] ?? (wh_q_settings($db)['free'][$contentType] ?? WH_Q_DEFAULT_FREE);
}

function wh_q_free_used(PDO $db, int $creatorId, string $contentType, string $period): int
{
    $st = $db->prepare(
        "SELECT COUNT(*) FROM wh_post_charges
         WHERE creator_id = :c AND content_type = :t AND period_ym = :p AND kind = 'free' AND status IN ('reserved','used')"
    );
    $st->execute(['c' => $creatorId, 't' => $contentType, 'p' => $period]);
    return (int) $st->fetchColumn();
}

/** @return array<string,mixed>|null the post's charge row */
function wh_q_charge(PDO $db, string $itemType, int $itemId): ?array
{
    $st = $db->prepare('SELECT * FROM wh_post_charges WHERE item_type = :t AND item_id = :i LIMIT 1');
    $st->execute(['t' => $itemType, 'i' => $itemId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Can this post be sent for review right now? Does not change anything.
 *
 * @return array{ok:bool, kind:string, error?:string}   kind = none | already | free | paid
 */
function wh_q_check(PDO $db, int $creatorId, string $itemType, ?int $itemId, string $contentType): array
{
    if (!wh_q_enabled($db) || $contentType === 'free_camp') {
        return ['ok' => true, 'kind' => 'none'];
    }
    if ($itemId !== null) {
        $ch = wh_q_charge($db, $itemType, $itemId);
        if ($ch && in_array($ch['status'], ['reserved', 'used'], true)) {
            return ['ok' => true, 'kind' => 'already'];
        }
    }
    $acc = wh_q_account($db, $creatorId);
    $limit = (int) wh_q_free_limit($db, $acc, $contentType);
    if (wh_q_free_used($db, $creatorId, $contentType, wh_q_period()) < $limit) {
        return ['ok' => true, 'kind' => 'free'];
    }
    if ($acc['paid_balance'] > 0) {
        return ['ok' => true, 'kind' => 'paid'];
    }
    return ['ok' => false, 'kind' => 'none', 'error' => 'no_credits'];
}

/**
 * Reserve a free slot (else 1 credit) for a post being sent for review.
 * Idempotent: a post that already holds a charge keeps it.
 *
 * @return array{ok:bool, kind:string, error?:string}
 */
function wh_q_reserve(PDO $db, int $creatorId, string $itemType, int $itemId, string $contentType): array
{
    if (!wh_q_enabled($db) || $contentType === 'free_camp') {
        return ['ok' => true, 'kind' => 'none'];
    }
    $own = !$db->inTransaction();
    if ($own) {
        $db->beginTransaction();
    }
    try {
        $acc = wh_q_account($db, $creatorId, true);   // row lock: one reservation at a time per creator
        $ch = wh_q_charge($db, $itemType, $itemId);
        if ($ch && in_array($ch['status'], ['reserved', 'used'], true)) {
            if ($own) {
                $db->commit();
            }
            return ['ok' => true, 'kind' => 'already'];
        }
        $period = wh_q_period();
        $limit = (int) wh_q_free_limit($db, $acc, $contentType);
        if (wh_q_free_used($db, $creatorId, $contentType, $period) < $limit) {
            $kind = 'free';
        } elseif ($acc['paid_balance'] > 0) {
            $kind = 'paid';
            $db->prepare('UPDATE wh_credit_accounts SET paid_balance = paid_balance - 1 WHERE creator_id = :c AND paid_balance > 0')
               ->execute(['c' => $creatorId]);
            wh_q_ledger_add($db, $creatorId, 'reserve', -1, $acc['paid_balance'] - 1, $itemType, $itemId, null, 'Used for a ' . $contentType, null);
        } else {
            if ($own) {
                $db->rollBack();
            }
            return ['ok' => false, 'kind' => 'none', 'error' => 'no_credits'];
        }
        $db->prepare(
            "INSERT INTO wh_post_charges (creator_id, item_type, item_id, content_type, period_ym, kind, status)
             VALUES (:c, :it, :ii, :ct, :p, :k, 'reserved')
             ON DUPLICATE KEY UPDATE creator_id = VALUES(creator_id), content_type = VALUES(content_type),
                                     period_ym = VALUES(period_ym), kind = VALUES(kind), status = 'reserved'"
        )->execute(['c' => $creatorId, 'it' => $itemType, 'ii' => $itemId, 'ct' => $contentType, 'p' => $period, 'k' => $kind]);
        if ($own) {
            $db->commit();
        }
        return ['ok' => true, 'kind' => $kind];
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[wh_q_reserve] ' . $e->getMessage());
        return ['ok' => false, 'kind' => 'none', 'error' => 'server_error'];
    }
}

/** Post approved: its reservation becomes final. */
function wh_q_finalize(PDO $db, string $itemType, int $itemId): void
{
    if (!wh_q_enabled($db)) {
        return;
    }
    $db->prepare("UPDATE wh_post_charges SET status = 'used' WHERE item_type = :t AND item_id = :i AND status = 'reserved'")
       ->execute(['t' => $itemType, 'i' => $itemId]);
}

/** Post rejected / withdrawn before approval: give the slot or credit back. */
function wh_q_release(PDO $db, string $itemType, int $itemId, string $why): void
{
    if (!wh_q_enabled($db)) {
        return;
    }
    $own = !$db->inTransaction();
    if ($own) {
        $db->beginTransaction();
    }
    try {
        $st = $db->prepare("SELECT * FROM wh_post_charges WHERE item_type = :t AND item_id = :i AND status = 'reserved' LIMIT 1 FOR UPDATE");
        $st->execute(['t' => $itemType, 'i' => $itemId]);
        $ch = $st->fetch(PDO::FETCH_ASSOC);
        if ($ch) {
            $db->prepare("UPDATE wh_post_charges SET status = 'released' WHERE id = :id")->execute(['id' => (int) $ch['id']]);
            if ($ch['kind'] === 'paid') {
                $acc = wh_q_account($db, (int) $ch['creator_id'], true);
                $db->prepare('UPDATE wh_credit_accounts SET paid_balance = paid_balance + 1 WHERE creator_id = :c')
                   ->execute(['c' => (int) $ch['creator_id']]);
                wh_q_ledger_add($db, (int) $ch['creator_id'], 'release', 1, $acc['paid_balance'] + 1, $itemType, $itemId, null, 'Credit returned (' . $why . ')', null);
            }
        }
        if ($own) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[wh_q_release] ' . $e->getMessage());
    }
}

/** Add (purchase / admin grant) or remove (admin deduct) credits. Returns the new balance. */
function wh_q_adjust(PDO $db, int $creatorId, int $qty, string $entry, ?int $orderId, string $note, ?int $adminId): int
{
    $own = !$db->inTransaction();
    if ($own) {
        $db->beginTransaction();
    }
    try {
        $acc = wh_q_account($db, $creatorId, true);
        $new = max(0, $acc['paid_balance'] + $qty);
        $db->prepare('UPDATE wh_credit_accounts SET paid_balance = :b WHERE creator_id = :c')->execute(['b' => $new, 'c' => $creatorId]);
        wh_q_ledger_add($db, $creatorId, $entry, $new - $acc['paid_balance'], $new, null, null, $orderId, $note, $adminId);
        if ($own) {
            $db->commit();
        }
        return $new;
    } catch (Throwable $e) {
        if ($own && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function wh_q_ledger_add(PDO $db, int $creatorId, string $entry, int $qty, int $balanceAfter, ?string $itemType, ?int $itemId, ?int $orderId, string $note, ?int $adminId): void
{
    $db->prepare(
        'INSERT INTO wh_ledger (creator_id, entry, qty, balance_after, item_type, item_id, order_id, note, created_by)
         VALUES (:c, :e, :q, :b, :it, :ii, :o, :n, :a)'
    )->execute([
        'c' => $creatorId, 'e' => $entry, 'q' => $qty, 'b' => $balanceAfter, 'it' => $itemType, 'ii' => $itemId,
        'o' => $orderId, 'n' => mb_substr($note, 0, 255), 'a' => $adminId,
    ]);
}

/**
 * This month, per approved type: free limit, free used, plus the paid balance.
 *
 * @param list<string> $types
 * @return array{period:string, balance:int, types: array<string, array{limit:?int, used:int}>}
 */
function wh_q_status(PDO $db, int $creatorId, array $types): array
{
    $period = wh_q_period();
    if (!wh_q_enabled($db)) {
        return ['period' => $period, 'balance' => 0, 'types' => []];
    }
    $acc = wh_q_account($db, $creatorId);
    $out = [];
    foreach ($types as $t) {
        $limit = wh_q_free_limit($db, $acc, $t);
        $out[$t] = ['limit' => $limit, 'used' => $limit === null ? 0 : wh_q_free_used($db, $creatorId, $t, $period)];
    }
    return ['period' => $period, 'balance' => $acc['paid_balance'], 'types' => $out];
}

/** Human message when a submit is blocked for lack of credits. */
function wh_q_blocked_message(string $contentType, int $limit): string
{
    $label = ['recipe' => 'recipes', 'exercise' => 'exercises', 'tip' => 'quick tips', 'video' => 'videos', 'event' => 'events'][$contentType] ?? 'posts';
    return 'You have used your ' . $limit . ' free ' . $label . ' for this month. Buy credits in Creator Studio → Credits & payments to send more (free posts reset on the 1st).';
}
