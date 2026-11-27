<?php
// =====================================================================
// partials/wellness_comments.php — Wellness Hub comments & Q/A (Phase 5).
//
// ONE implementation for both sides (plain functions over PDO, no other
// includes): the public pages + api/wellness_comments.php on eclinicpro.com,
// and Creator Studio / admin on app.eclinicpro.com (App\Services\Wellness\Comments).
//
// Rules (plan §7):
//   - Signed-in patients only. One level of replies.
//   - Posted at once, but HELD for admin when it has a link, phone number,
//     email or a blocked phrase, or after 3 reports.
//   - 10 per hour / 40 per day per person.
//   - A reply by the item's own creator = "Creator's answer"; a verified
//     doctor's reply shows the Verified Doctor badge.
//   - Names: "Priya S." (first name + initial); creators show their public name.
// =====================================================================

declare(strict_types=1);

const WH_C_MAX_HOUR = 10;
const WH_C_MAX_DAY = 40;
const WH_C_REPORTS_TO_HOLD = 3;
const WH_C_REPORT_REASONS = [
    'abusive' => 'Abusive or hateful', 'spam' => 'Spam or advertising', 'medical_misinfo' => 'Harmful medical advice',
    'personal_info' => 'Shares someone’s personal details', 'other' => 'Something else',
];

function wh_c_enabled(PDO $db): bool
{
    static $on = null;
    if ($on === null) {
        try {
            $on = $db->query("SHOW TABLES LIKE 'wh_comments'")->fetchColumn() !== false;
        } catch (Throwable $e) {
            $on = false;
        }
    }
    return $on;
}

/**
 * The item a comment belongs to: is it public, who created it, where it lives.
 *
 * @return array{public:bool,title:string,path:string,creator_id:?int,owner_identity_id:?int,owner_email:?string,owner_name:string}|null
 */
function wh_c_item(PDO $db, string $itemType, int $itemId): ?array
{
    if ($itemType === 'tip') {
        $st = $db->prepare(
            "SELECT t.type, t.slug, t.title, t.status, c.status AS c_status, c.identity_id AS c_identity,
                    w.id AS creator_id, w.identity_id AS w_identity, w.email AS w_email, COALESCE(w.display_name, c.display_name) AS owner_name
             FROM health_tips t JOIN health_tip_contributors c ON c.id = t.contributor_id
             LEFT JOIN wh_creators w ON w.legacy_contributor_id = c.id
             WHERE t.id = :id AND t.is_deleted = 0 LIMIT 1"
        );
        $st->execute(['id' => $itemId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        return [
            'public' => $r['status'] === 'published' && $r['c_status'] === 'active',
            'title' => (string) $r['title'],
            'path' => '/wellness/' . $r['type'] . '/' . $r['slug'],
            'creator_id' => $r['creator_id'] !== null ? (int) $r['creator_id'] : null,
            'owner_identity_id' => (int) ($r['w_identity'] ?? $r['c_identity']) ?: null,
            'owner_email' => $r['w_email'] ?: null,
            'owner_name' => (string) $r['owner_name'],
        ];
    }
    if ($itemType === 'event') {
        $st = $db->prepare(
            "SELECT e.kind, e.slug, e.title, e.status, w.id AS creator_id, w.identity_id, w.email, w.display_name, w.status AS w_status
             FROM wh_events e JOIN wh_creators w ON w.id = e.creator_id WHERE e.id = :id AND e.is_deleted = 0 LIMIT 1"
        );
        $st->execute(['id' => $itemId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return null;
        }
        return [
            'public' => in_array($r['status'], ['published', 'cancelled'], true) && $r['w_status'] === 'active',
            'title' => (string) $r['title'],
            'path' => '/wellness/' . ($r['kind'] === 'free_camp' ? 'camp' : 'event') . '/' . $r['slug'],
            'creator_id' => (int) $r['creator_id'],
            'owner_identity_id' => $r['identity_id'] !== null ? (int) $r['identity_id'] : null,
            'owner_email' => $r['email'] ?: null,
            'owner_name' => (string) $r['display_name'],
        ];
    }
    return null;
}

/** Why a text should wait for review (null = fine). */
function wh_c_hold_reason(PDO $db, string $body): ?string
{
    if (preg_match('#(https?://|www\.|\b[a-z0-9\-]{2,}\.(com|in|net|org|co|io|me|ly|info|biz|shop|xyz)\b)#i', $body)) {
        return 'link';
    }
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body)) {
        return 'email';
    }
    $digits = preg_replace('/[\s\-().]/', '', $body) ?? '';
    if (preg_match('/(\+?91)?[6-9]\d{9}/', $digits) || preg_match('/\d{10,}/', $digits)) {
        return 'phone';
    }
    static $words = null;
    if ($words === null) {
        $words = [];
        try {
            $st = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = 'wh_blocked_words' LIMIT 1");
            $st->execute();
            $list = json_decode((string) $st->fetchColumn(), true);
            $words = is_array($list) ? array_values(array_filter(array_map(static fn ($w) => mb_strtolower(trim((string) $w)), $list))) : [];
        } catch (Throwable $e) {
            $words = [];
        }
    }
    $lower = mb_strtolower($body);
    foreach ($words as $w) {
        if ($w !== '' && str_contains($lower, $w)) {
            return 'blocked_word';
        }
    }
    return null;
}

/** Active Wellness creator for a patient identity (for names / badges). */
function wh_c_creator_for_identity(PDO $db, int $identityId): ?array
{
    $st = $db->prepare("SELECT id, display_name, creator_type, verify_status FROM wh_creators WHERE identity_id = :i AND status = 'active' LIMIT 1");
    $st->execute(['i' => $identityId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Post a comment, question or reply.
 *
 * @return array{ok:bool,error?:string,id?:int,status?:string,notify?:list<array{type:string,email:string,name:string,title:string,path:string,excerpt:string}>}
 */
function wh_c_post(PDO $db, int $identityId, string $itemType, int $itemId, string $kind, string $body, ?int $parentId): array
{
    if (!wh_c_enabled($db)) {
        return ['ok' => false, 'error' => 'Comments are not open yet.'];
    }
    $item = wh_c_item($db, $itemType, $itemId);
    if ($item === null || !$item['public']) {
        return ['ok' => false, 'error' => 'This post is not available.'];
    }
    $body = trim(preg_replace('/[^\P{C}\n]+/u', '', $body) ?? '');
    $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;
    if (mb_strlen($body) < 2) {
        return ['ok' => false, 'error' => 'Please write something first.'];
    }
    if (mb_strlen($body) > 2000) {
        return ['ok' => false, 'error' => 'Please keep it under 2000 characters.'];
    }

    $parent = null;
    if ($parentId) {
        $st = $db->prepare('SELECT * FROM wh_comments WHERE id = :id AND item_type = :t AND item_id = :i AND parent_id IS NULL LIMIT 1');
        $st->execute(['id' => $parentId, 't' => $itemType, 'i' => $itemId]);
        $parent = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($parent === null || $parent['status'] !== 'visible') {
            return ['ok' => false, 'error' => 'You can’t reply to that comment.'];
        }
        $kind = 'reply';
    } elseif (!in_array($kind, ['comment', 'question'], true)) {
        $kind = 'comment';
    }

    // Rate limits.
    $st = $db->prepare('SELECT SUM(created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)), COUNT(*) FROM wh_comments WHERE identity_id = :i AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)');
    $st->execute(['i' => $identityId]);
    [$hour, $day] = array_map('intval', $st->fetch(PDO::FETCH_NUM) ?: [0, 0]);
    if ($hour >= WH_C_MAX_HOUR || $day >= WH_C_MAX_DAY) {
        return ['ok' => false, 'error' => 'You’ve posted a lot in a short time. Please try again a little later.'];
    }

    $hold = wh_c_hold_reason($db, $body);
    $status = $hold === null ? 'visible' : 'held';
    $creator = wh_c_creator_for_identity($db, $identityId);
    $isCreatorAnswer = $parent !== null && $item['owner_identity_id'] !== null && $item['owner_identity_id'] === $identityId;

    $db->prepare(
        'INSERT INTO wh_comments (item_type, item_id, identity_id, parent_id, kind, body, status, hold_reason, is_creator_answer, author_creator_id)
         VALUES (:t, :i, :a, :p, :k, :b, :s, :h, :ca, :cr)'
    )->execute([
        't' => $itemType, 'i' => $itemId, 'a' => $identityId, 'p' => $parent ? (int) $parent['id'] : null, 'k' => $kind,
        'b' => $body, 's' => $status, 'h' => $hold, 'ca' => $isCreatorAnswer ? 1 : 0, 'cr' => $creator ? (int) $creator['id'] : null,
    ]);
    $id = (int) $db->lastInsertId();

    return ['ok' => true, 'id' => $id, 'status' => $status, 'notify' => $status === 'visible' ? wh_c_notifications($db, $id) : []];
}

/**
 * Emails a visible comment should trigger (caller sends them with its own mailer):
 *   - new question → the item's creator
 *   - reply to a question → the person who asked (once per question)
 *
 * @return list<array{type:string,email:string,name:string,title:string,path:string,excerpt:string}>
 */
function wh_c_notifications(PDO $db, int $commentId): array
{
    $st = $db->prepare('SELECT * FROM wh_comments WHERE id = :id LIMIT 1');
    $st->execute(['id' => $commentId]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c || $c['status'] !== 'visible') {
        return [];
    }
    $item = wh_c_item($db, (string) $c['item_type'], (int) $c['item_id']);
    if ($item === null) {
        return [];
    }
    $excerpt = mb_substr((string) $c['body'], 0, 300);
    $out = [];
    if ($c['kind'] === 'question' && $item['owner_email'] && $item['owner_identity_id'] !== (int) $c['identity_id']) {
        $out[] = ['type' => 'new_question', 'email' => (string) $item['owner_email'], 'name' => $item['owner_name'], 'title' => $item['title'], 'path' => $item['path'] . '#c-' . $commentId, 'excerpt' => $excerpt];
    }
    if ($c['kind'] === 'reply' && $c['parent_id']) {
        $st = $db->prepare(
            "SELECT q.id, q.kind, q.identity_id, q.notified_at, pi.email, COALESCE(pi.first_name, pi.name) AS name
             FROM wh_comments q JOIN patient_identities pi ON pi.id = q.identity_id WHERE q.id = :id LIMIT 1"
        );
        $st->execute(['id' => (int) $c['parent_id']]);
        $q = $st->fetch(PDO::FETCH_ASSOC);
        if ($q && $q['kind'] === 'question' && (int) $q['identity_id'] !== (int) $c['identity_id'] && empty($q['notified_at'])
            && filter_var((string) $q['email'], FILTER_VALIDATE_EMAIL)) {
            $db->prepare('UPDATE wh_comments SET notified_at = NOW() WHERE id = :id')->execute(['id' => (int) $q['id']]);
            $out[] = ['type' => 'answered', 'email' => (string) $q['email'], 'name' => (string) $q['name'], 'title' => $item['title'], 'path' => $item['path'] . '#c-' . (int) $q['id'], 'excerpt' => $excerpt];
        }
    }
    return $out;
}

/**
 * Thread for an item: visible top-level comments (newest first) with visible
 * replies (oldest first). The viewer also sees their own held ones.
 *
 * @return list<array<string,mixed>>
 */
function wh_c_thread(PDO $db, string $itemType, int $itemId, ?int $viewerId): array
{
    if (!wh_c_enabled($db)) {
        return [];
    }
    $st = $db->prepare(
        "SELECT c.*, pi.name AS p_name, pi.first_name AS p_first, pi.last_name AS p_last,
                w.display_name AS cr_name, w.creator_type AS cr_type, w.verify_status AS cr_verify, w.slug AS cr_slug
         FROM wh_comments c
         JOIN patient_identities pi ON pi.id = c.identity_id
         LEFT JOIN wh_creators w ON w.id = c.author_creator_id AND w.status = 'active'
         WHERE c.item_type = :t AND c.item_id = :i
           AND (c.status = 'visible' OR (c.status = 'held' AND c.identity_id = :v))
         ORDER BY c.id ASC LIMIT 500"
    );
    $st->execute(['t' => $itemType, 'i' => $itemId, 'v' => $viewerId ?? 0]);
    $top = [];
    $replies = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['author'] = wh_c_author($r);
        $r['replies'] = [];
        if ($r['parent_id']) {
            $replies[(int) $r['parent_id']][] = $r;
        } else {
            $top[(int) $r['id']] = $r;
        }
    }
    foreach ($replies as $pid => $list) {
        if (isset($top[$pid])) {
            $top[$pid]['replies'] = $list;
        }
    }
    return array_reverse(array_values($top));
}

/** @return array{name:string,badge:?string,creator_slug:?string} */
function wh_c_author(array $r): array
{
    if (!empty($r['cr_name'])) {
        $badge = null;
        if (($r['cr_verify'] ?? '') === 'verified' && ($r['cr_type'] ?? '') !== 'platform') {
            $badge = ($r['cr_type'] ?? '') === 'doctor' ? 'Verified Doctor' : 'Verified Organisation';
        }
        return ['name' => (string) $r['cr_name'], 'badge' => $badge, 'creator_slug' => (string) $r['cr_slug']];
    }
    $first = trim((string) ($r['p_first'] ?: explode(' ', trim((string) $r['p_name']))[0] ?? ''));
    $last = trim((string) ($r['p_last'] ?: (explode(' ', trim((string) $r['p_name']))[1] ?? '')));
    $name = $first !== '' && strtolower($first) !== 'patient' ? $first : 'eClinicPro member';
    return ['name' => $name . ($last !== '' && $name !== 'eClinicPro member' ? ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.' : ''), 'badge' => null, 'creator_slug' => null];
}

function wh_c_count(PDO $db, string $itemType, int $itemId): int
{
    if (!wh_c_enabled($db)) {
        return 0;
    }
    $st = $db->prepare("SELECT COUNT(*) FROM wh_comments WHERE item_type = :t AND item_id = :i AND status = 'visible'");
    $st->execute(['t' => $itemType, 'i' => $itemId]);
    return (int) $st->fetchColumn();
}

/** @return array{ok:bool,error?:string} */
function wh_c_report(PDO $db, int $commentId, int $identityId, string $reason): array
{
    if (!isset(WH_C_REPORT_REASONS[$reason])) {
        return ['ok' => false, 'error' => 'Please choose a reason.'];
    }
    $st = $db->prepare('SELECT identity_id, status FROM wh_comments WHERE id = :id LIMIT 1');
    $st->execute(['id' => $commentId]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c || $c['status'] !== 'visible') {
        return ['ok' => false, 'error' => 'Not found.'];
    }
    if ((int) $c['identity_id'] === $identityId) {
        return ['ok' => false, 'error' => 'You can’t report your own comment.'];
    }
    $ins = $db->prepare('INSERT IGNORE INTO wh_comment_reports (comment_id, identity_id, reason) VALUES (:c, :i, :r)');
    $ins->execute(['c' => $commentId, 'i' => $identityId, 'r' => $reason]);
    if ($ins->rowCount() === 1) {
        $db->prepare('UPDATE wh_comments SET report_count = report_count + 1 WHERE id = :id')->execute(['id' => $commentId]);
        $db->prepare("UPDATE wh_comments SET status = 'held', hold_reason = 'reported' WHERE id = :id AND status = 'visible' AND report_count >= :n")
           ->execute(['id' => $commentId, 'n' => WH_C_REPORTS_TO_HOLD]);
    }
    return ['ok' => true];
}

/** Author removes their own comment / question / reply. */
function wh_c_delete_own(PDO $db, int $commentId, int $identityId): bool
{
    $st = $db->prepare("UPDATE wh_comments SET status = 'deleted' WHERE id = :id AND identity_id = :i AND status IN ('visible','held')");
    $st->execute(['id' => $commentId, 'i' => $identityId]);
    return $st->rowCount() === 1;
}

/** The item's own creator hides a comment on their post. */
function wh_c_hide_by_owner(PDO $db, int $commentId, int $ownerIdentityId): bool
{
    $st = $db->prepare('SELECT item_type, item_id FROM wh_comments WHERE id = :id LIMIT 1');
    $st->execute(['id' => $commentId]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        return false;
    }
    $item = wh_c_item($db, (string) $c['item_type'], (int) $c['item_id']);
    if ($item === null || $item['owner_identity_id'] !== $ownerIdentityId) {
        return false;
    }
    $db->prepare("UPDATE wh_comments SET status = 'hidden', hidden_by = 'creator' WHERE id = :id AND status IN ('visible','held')")
       ->execute(['id' => $commentId]);
    return true;
}
